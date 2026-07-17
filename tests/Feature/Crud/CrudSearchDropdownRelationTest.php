<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub models: `items` belongs to `users` via category_id ──────────────────

class SdRelOwnerStub extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email'];
}

class SdRelItemStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(SdRelOwnerStub::class, 'category_id');
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers dot-notation relation support in colsSDLabel (e.g. "owner.name") for
 * the inline BaseCrud searchdropdown, model-mode: label resolution via
 * data_get(), search via whereHas(), and the colsSDOrder fallback when the
 * configured order column belongs to the relation instead of the base model.
 */
class CrudSearchDropdownRelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => SdRelItemStub::class,
            'route' => '',
            'config' => [
                'crud' => SdRelItemStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        // Relation label: colsSDOrder intentionally left at the
                        // default ("owner.name ASC") to exercise the fallback.
                        'colsNomeFisico' => 'category_id',
                        'colsNomeLogico' => 'Owner',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => SdRelItemStub::class,
                        'colsSDLabel' => 'owner.name',
                        'colsSDValor' => 'id',
                        'colsSDTipo' => 'model',
                    ],
                ],
                'permissions' => [],
            ],
        ]);

        $alice = SdRelOwnerStub::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob = SdRelOwnerStub::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        SdRelItemStub::create(['name' => 'Item Alice', 'category_id' => $alice->id]);
        SdRelItemStub::create(['name' => 'Item Bob', 'category_id' => $bob->id]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => SdRelItemStub::class]);
    }

    #[Test]
    public function search_dropdown_resolves_the_label_through_a_relation(): void
    {
        $itemAlice = SdRelItemStub::where('name', 'Item Alice')->first();

        $component = $this->crud()->call('searchDropdown', 'category_id', 'alice');

        $results = $component->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Alice', $results[0]['label']);
        $this->assertSame($itemAlice->id, $results[0]['value']);
    }

    #[Test]
    public function open_dropdown_lists_items_with_relation_labels(): void
    {
        $component = $this->crud()->call('openDropdown', 'category_id');

        $labels = array_column($component->get('sdResults')['category_id'], 'label');

        $this->assertEqualsCanonicalizing(['Alice', 'Bob'], $labels);
    }

    #[Test]
    public function search_dropdown_with_no_match_returns_no_results(): void
    {
        $component = $this->crud()->call('searchDropdown', 'category_id', 'nobody');

        $this->assertSame([], $component->get('sdResults')['category_id']);
    }

    #[Test]
    public function table_qualified_order_column_on_the_base_model_is_not_overridden_when_label_is_plain(): void
    {
        // Plain (non-relation) colsSDLabel + a table-qualified colsSDOrder on
        // the BASE model — valid, no relation involved. The "." fallback to
        // colsSDValor must trigger only when colsSDLabel itself is a
        // relation; it must NOT touch this case.
        $cfg = CrudConfig::where('model', SdRelItemStub::class)->first();
        $config = $cfg->config;
        $config['cols'] = array_map(function (array $col) {
            if (($col['colsNomeFisico'] ?? null) === 'category_id') {
                $col['colsSDLabel'] = 'name';
                $col['colsSDOrder'] = 'items.name ASC';
            }

            return $col;
        }, $config['cols']);
        $cfg->update(['config' => $config]);

        // Insertion order (by id) is Zebra, then Apple — the inverse of
        // alphabetical order — so an accidental fallback to "id" (or any
        // other column) would be visible in the returned order.
        SdRelItemStub::create(['name' => 'Zebra Corp']);
        SdRelItemStub::create(['name' => 'Apple Inc']);

        $component = $this->crud()->call('openDropdown', 'category_id');

        $labels = array_column($component->get('sdResults')['category_id'], 'label');

        $this->assertSame(
            ['Apple Inc', 'Item Alice', 'Item Bob', 'Zebra Corp'],
            $labels,
            'Table-qualified order column on the base model must be honoured as-is when colsSDLabel has no dot',
        );
    }

    // ── Security: unsafe relation column (SqlIdentifier guard) ────────────────

    #[Test]
    #[DataProvider('maliciousRelationLabels')]
    public function search_dropdown_discards_a_malicious_relation_column_without_error(string $maliciousLabel): void
    {
        $this->setColsSDLabel($maliciousLabel);

        // Must not throw — the real guard is SqlIdentifier::isSafe() rejecting
        // the column before it ever reaches whereRaw()/whereHas(). The outer
        // try/catch in resolveSearchDropdownResults is a last-resort net, not
        // the mechanism under test here.
        $component = $this->crud()->call('searchDropdown', 'category_id', 'alice');

        $results = $component->get('sdResults')['category_id'];

        // The guard silently skips the search filter (no WHERE/whereHas is
        // applied) instead of throwing or interpolating the payload — both
        // rows come back unfiltered, with a null label (the malicious column
        // doesn't resolve to a real attribute via data_get()).
        $this->assertCount(2, $results);
        foreach ($results as $item) {
            $this->assertNull($item['label']);
        }

        // Proof the malicious fragment never reached the database: the table
        // is untouched and both seeded rows are still there.
        $this->assertSame(2, SdRelItemStub::count());
    }

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

    /**
     * Overrides colsSDLabel on the category_id column of the CrudConfig
     * created in setUp(). colsSDOrder is pinned to a safe "id ASC" so the
     * test isolates the whereRaw/whereHas guard being exercised — without
     * this, the default colsSDOrder ("{label} ASC") would embed the very
     * same malicious payload into the ORDER BY direction and the case would
     * be swallowed by Laravel's own orderBy() validation instead of by the
     * SqlIdentifier guard this test targets.
     */
    private function setColsSDLabel(string $label): void
    {
        $cfg = CrudConfig::where('model', SdRelItemStub::class)->first();
        $config = $cfg->config;

        $config['cols'] = array_map(function (array $col) use ($label) {
            if (($col['colsNomeFisico'] ?? null) === 'category_id') {
                $col['colsSDLabel'] = $label;
                $col['colsSDOrder'] = 'id ASC';
            }

            return $col;
        }, $config['cols']);

        $cfg->update(['config' => $config]);
    }
}
