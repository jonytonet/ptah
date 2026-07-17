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
