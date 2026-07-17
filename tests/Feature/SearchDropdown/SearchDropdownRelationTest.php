<?php

declare(strict_types=1);

// ── Stub models: standalone SearchDropdown resolves model-mode classes as ──────
// `App\Models\{model}` with no FQCN fallback (see resolveModelClass()), so the
// relation stubs for this test must live in that exact namespace.

namespace App\Models {

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class Owner extends Model
    {
        protected $table = 'users';

        protected $fillable = ['name', 'email'];
    }

    class Item extends Model
    {
        protected $table = 'items';

        protected $fillable = ['name', 'status', 'amount', 'category_id'];

        public function owner(): BelongsTo
        {
            return $this->belongsTo(Owner::class, 'category_id');
        }

        /**
         * Same relation as owner(), under a camelCase name — the common real-world
         * case (e.g. "ownerCompany", "orderItems"). Eloquent's toArray() snake-cases
         * relation keys (relationsToArray()), so this method name is what exposes
         * the label-resolution bug: reading "ownerCompany.name" back from the
         * ARRAY produced by toArray() silently resolves to null, because the array
         * key is "owner_company", not "ownerCompany".
         */
        public function ownerCompany(): BelongsTo
        {
            return $this->belongsTo(Owner::class, 'category_id');
        }
    }
}

namespace Ptah\Tests\Feature\SearchDropdown {

    use App\Models\Item;
    use App\Models\Owner;
    use Livewire\Livewire;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use Ptah\Livewire\SearchDropdown\SearchDropdown;
    use Ptah\Tests\TestCase;

    /**
     * Covers dot-notation relation support in label/labelTwo/labelThree for the
     * standalone SearchDropdown component, model-mode: label resolution via
     * data_get(), search via orWhereHas(), the plain-column regression path,
     * and the SqlIdentifier guard against a malicious relation column.
     */
    class SearchDropdownRelationTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();

            $alice = Owner::create(['name' => 'Alice', 'email' => 'alice@example.com']);
            $bob = Owner::create(['name' => 'Bob', 'email' => 'bob@example.com']);

            Item::create(['name' => 'Item Alice', 'category_id' => $alice->id]);
            Item::create(['name' => 'Item Bob', 'category_id' => $bob->id]);
        }

        #[Test]
        public function search_resolves_the_label_through_a_relation(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'owner.name',
                'value' => 'id',
            ])->instance()->search('alice');

            $this->assertCount(1, $results);
            $this->assertSame('Alice', $results[0]['_label']);
            $this->assertSame($itemAlice->id, $results[0]['_value']);
        }

        /**
         * Regression test for the label-resolution bug: a camelCase relation
         * name (the common real-world case — "ownerCompany", "orderItems", etc.)
         * gets snake-cased to "owner_company" by Eloquent's toArray(). Before the
         * fix, label/labelTwo/labelThree were read via data_get() on the ARRAY
         * produced by toArray(), so "ownerCompany.name" resolved to null there
         * (the array only has "owner_company") and `_label` came back empty —
         * silently, with no error. The fix resolves labels from the Eloquent
         * MODEL instances (before toArray()), where data_get() walks the
         * relation by its real camelCase method name.
         */
        #[Test]
        public function search_resolves_the_label_through_a_camel_case_relation(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'ownerCompany.name',
                'value' => 'id',
            ])->instance()->search('alice');

            // The search filter itself (orWhereHas('ownerCompany', ...)) already
            // targets the real relation name and was never affected by the bug —
            // this proves it keeps working (only the matching row comes back).
            $this->assertCount(1, $results);
            $this->assertSame($itemAlice->id, $results[0]['_value']);

            // Proof the array really did get snake-cased by Eloquent...
            $this->assertArrayHasKey('owner_company', $results[0]['_raw']);
            $this->assertArrayNotHasKey('ownerCompany', $results[0]['_raw']);

            // ...yet the label still resolves correctly: this is the assertion
            // that fails (empty string) on the pre-fix code.
            $this->assertSame('Alice', $results[0]['_label']);

            // "_raw" is the clean model row: the internal "_ptahLabel" key
            // that loadDataViaModel() injects to resolve the camelCase
            // relation (see readLabel()) must never leak into it — it is
            // exposed as a sibling of "_raw" instead (consumed by
            // selectedItem(), see the "Selection" tests below).
            $this->assertArrayNotHasKey('_ptahLabel', $results[0]['_raw']);
            $this->assertSame('Alice', $results[0]['_ptahLabel']);
        }

        /**
         * Same bug class as the critical label fix, exercised on the
         * secondary slots: labelTwo/labelThree also resolve a camelCase
         * relation path via data_get() on the Model (readLabel()'s
         * "_ptahLabelTwo"/"_ptahLabelThree" keys), not on the toArray()'d
         * (and therefore snake-cased) row.
         */
        #[Test]
        public function search_resolves_label_two_and_label_three_through_a_camel_case_relation(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'name',
                'labelTwo' => 'ownerCompany.name',
                'labelThree' => 'ownerCompany.email',
                'value' => 'id',
            ])->instance()->search('alice');

            $this->assertCount(1, $results);
            $this->assertSame($itemAlice->id, $results[0]['_value']);
            $this->assertSame('Item Alice', $results[0]['_label']);
            $this->assertSame('Alice', $results[0]['_labelTwo']);
            $this->assertSame('alice@example.com', $results[0]['_labelThree']);

            // "_raw" stays clean regardless of which slot used the relation.
            $this->assertArrayNotHasKey('_ptahLabelTwo', $results[0]['_raw']);
            $this->assertArrayNotHasKey('_ptahLabelThree', $results[0]['_raw']);
        }

        #[Test]
        public function empty_term_lists_every_item_with_relation_labels(): void
        {
            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'owner.name',
                'value' => 'id',
                'orderByRaw' => 'id asc',
            ])->instance()->search('');

            $labels = array_column($results, '_label');

            $this->assertSame(['Alice', 'Bob'], $labels);
        }

        #[Test]
        public function no_match_returns_no_results(): void
        {
            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'owner.name',
                'value' => 'id',
            ])->instance()->search('nobody');

            $this->assertSame([], $results);
        }

        #[Test]
        public function plain_label_regression_still_uses_the_column_limited_select(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'name',
                'value' => 'id',
            ])->instance()->search('alice');

            $this->assertCount(1, $results);
            $this->assertSame('Item Alice', $results[0]['_label']);
            $this->assertSame($itemAlice->id, $results[0]['_value']);

            // Regression proof: the column-limited select() path (no relation
            // label involved) is still in effect — category_id was never
            // selected, so it must be absent from the raw row.
            $this->assertArrayNotHasKey('category_id', $results[0]['_raw']);
        }

        // ── Selection: camelCase relation label + clean "_raw" ─────────────────

        /**
         * selectedItem() now receives the FULL item produced by search() (the
         * blade passes it whole instead of just "_raw" — see the component's
         * docblock), so it must still resolve a camelCase relation label
         * correctly and never leak the internal "_ptahLabel" key into "_raw".
         */
        #[Test]
        public function selected_item_resolves_a_camel_case_relation_label_with_a_clean_raw(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $component = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'ownerCompany.name',
                'value' => 'id',
                'listens' => 'itemSelected',
            ]);

            $results = $component->instance()->search('alice');
            $item = $results[0];

            // Proof "_raw" reaching the browser is already clean — this is
            // exactly what the blade forwards to selectedItem() below.
            $this->assertArrayNotHasKey('_ptahLabel', $item['_raw']);

            $component->call('selectedItem', $item);

            $component->assertDispatched('itemSelected', [
                'useService' => null,
                'value' => $itemAlice->id,
                'label' => 'Alice',
                'searchTerm' => $itemAlice->id.' - Alice',
                'coringa' => '',
            ]);
        }

        #[Test]
        public function selected_item_dispatches_value_and_label_for_a_plain_label(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $component = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'name',
                'value' => 'id',
                'listens' => 'itemSelected',
            ]);

            $results = $component->instance()->search('alice');
            $item = $results[0];

            $component->call('selectedItem', $item);

            $component->assertDispatched('itemSelected', [
                'useService' => null,
                'value' => $itemAlice->id,
                'label' => 'Item Alice',
                'searchTerm' => $itemAlice->id.' - Item Alice',
                'coringa' => '',
            ]);
        }

        /**
         * Locks the event contract: the "label" dispatched by selectedItem()
         * must be the RAW column value, never the masked "_label" shown in
         * the dropdown UI (see selectedItem()'s docblock and
         * docs/SearchDropdown.md). This is the very reason "_ptahLabel" is
         * read directly instead of reusing "_label" — using the masked value
         * here would silently change what parent components receive.
         */
        #[Test]
        public function selected_item_dispatches_the_raw_label_value_not_the_masked_one(): void
        {
            $priced = Item::create(['name' => 'Priced Item', 'amount' => 1500]);

            $component = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'amount',
                'value' => 'id',
                'arraySearch' => ['name'],
                'maskOne' => 'money',
                'listens' => 'itemSelected',
            ]);

            $results = $component->instance()->search('Priced');
            $item = $results[0];

            // The UI-facing label is masked...
            $this->assertSame('R$ 1.500,00', $item['_label']);

            $component->call('selectedItem', $item);

            // ...but the dispatched one is the raw column value.
            $component->assertDispatched('itemSelected', [
                'useService' => null,
                'value' => $priced->id,
                'label' => 1500,
                'searchTerm' => $priced->id.' - 1500',
                'coringa' => '',
            ]);
        }

        /**
         * Backward-compatibility guard: a stale, already-published blade
         * (v1.9.0 shipped `$wire.selectedItem(item._raw)`) still calls
         * selectedItem() with the raw row alone — no "_raw"/"_ptahLabel"
         * wrapper — after a package-only update (the view is only refreshed
         * by re-publishing `--tag=ptah-views`). A plain (non-relation) label
         * must keep resolving correctly, never null/crash.
         */
        #[Test]
        public function selected_item_stays_backward_compatible_with_a_stale_blade_passing_the_raw_row(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $component = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'name',
                'value' => 'id',
                'listens' => 'itemSelected',
            ]);

            $results = $component->instance()->search('alice');
            $rawRow = $results[0]['_raw'];

            // The stale blade never had "_ptahLabel"/"_raw" wrapper — proof
            // this really is the bare row, not the full item.
            $this->assertArrayNotHasKey('_ptahLabel', $rawRow);
            $this->assertArrayNotHasKey('_raw', $rawRow);

            $component->call('selectedItem', $rawRow);

            $component->assertDispatched('itemSelected', [
                'useService' => null,
                'value' => $itemAlice->id,
                'label' => 'Item Alice',
                'searchTerm' => $itemAlice->id.' - Item Alice',
                'coringa' => '',
            ]);
        }

        /**
         * Same stale-blade scenario as above, but with a camelCase relation
         * label: the raw row alone has no "_ptahLabel" sibling to resolve it
         * from, and the row itself is already snake-cased by toArray(), so
         * the label degrades to an empty string. "value" (a plain column)
         * is unaffected, and nothing throws.
         */
        #[Test]
        public function selected_item_degrades_the_label_gracefully_for_a_camel_case_relation_with_a_stale_blade(): void
        {
            $itemAlice = Item::where('name', 'Item Alice')->first();

            $component = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => 'ownerCompany.name',
                'value' => 'id',
                'listens' => 'itemSelected',
            ]);

            $results = $component->instance()->search('alice');
            $rawRow = $results[0]['_raw'];

            $component->call('selectedItem', $rawRow);

            $component->assertDispatched('itemSelected', [
                'useService' => null,
                'value' => $itemAlice->id,
                'label' => '',
                'searchTerm' => $itemAlice->id.' - ',
                'coringa' => '',
            ]);
        }

        // ── Security: unsafe relation column (SqlIdentifier guard) ────────────

        #[Test]
        #[DataProvider('maliciousRelationLabels')]
        public function a_malicious_relation_column_is_discarded_without_error(string $maliciousLabel): void
        {
            $results = Livewire::test(SearchDropdown::class, [
                'model' => 'Item',
                'label' => $maliciousLabel,
                'value' => 'id',
                'orderByRaw' => 'id asc',
            ])->instance()->search('alice');

            // The guard silently skips the whereHas() filter for the malicious
            // column instead of throwing or interpolating the payload — unlike
            // the inline BaseCrud dropdown, the standalone component always
            // keeps a plain (non-relation) `value` column in the search OR
            // chain, so once the relation clause is discarded, the remaining
            // "id LIKE '%alice%'" clause matches nothing and the result set
            // is empty rather than "unfiltered".
            $this->assertSame([], $results);

            // Proof the malicious fragment never reached the database: the
            // table is untouched and both seeded rows are still there.
            $this->assertSame(2, Item::count());
        }

        /**
         * @return array<string, array{0: string}>
         */
        public static function maliciousRelationLabels(): array
        {
            return [
                'coordinator-example' => ['owner.name); DROP TABLE items;--'],
                'injection' => ['owner.name) OR 1=1 --'],
                'semicolon' => ['owner.name; DROP TABLE items'],
                'space' => ['owner.col alias'],
                'single-quote' => ["owner.col'"],
            ];
        }
    }
}
