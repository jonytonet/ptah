<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class LockedScopeStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'owner_id'];
}

/**
 * `lockedFilters` is the parameter a consumer reaches for to scope a screen to
 * the logged-in user — "my attendances", "my orders" — while the same model also
 * powers a global admin list. Documented in docs/BaseCrud.md under
 * "Locking rows to a fixed scope".
 *
 * That documentation makes a security promise: the scope is inescapable. These
 * tests are that promise, because a doc claim with nothing enforcing it does not
 * hold a line — and the wrong way to do this (`initialFilter`) looks identical
 * on screen right up to the moment a user clears the filter panel.
 *
 * Four escape routes are covered, each a real thing a client can attempt:
 *   1. setting the locked column as an ordinary filter
 *   2. clearing every filter
 *   3. writing to `lockedFilters` directly over the wire
 *   4. asking for a single record by id that lies outside the scope (IDOR)
 *
 * The totalizador is included deliberately: it runs its own aggregate query, so
 * a lock enforced only on the listing would leak the true total of every row
 * while showing a filtered table — the sum is a data disclosure of its own.
 */
class CrudLockedFiltersScopeTest extends TestCase
{
    /** @var list<int> */
    private array $mineIds = [];

    /** @var list<int> */
    private array $theirsIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Owner 1 — three rows summing 60.
        foreach ([10, 20, 30] as $i => $amount) {
            $this->mineIds[] = LockedScopeStub::create([
                'name' => 'mine-'.$i,
                'amount' => $amount,
                'owner_id' => 1,
            ])->id;
        }

        // Owner 2 — two rows summing 300, which must never surface.
        foreach ([100, 200] as $i => $amount) {
            $this->theirsIds[] = LockedScopeStub::create([
                'name' => 'theirs-'.$i,
                'amount' => $amount,
                'owner_id' => 2,
            ])->id;
        }

        CrudConfig::updateOrCreate(
            ['model' => LockedScopeStub::class, 'route' => ''],
            ['config' => [
                'crud' => LockedScopeStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true],
                    ['colsNomeFisico' => 'owner_id', 'colsNomeLogico' => 'Owner', 'colsTipo' => 'number', 'colsGravar' => true],
                ],
                'totalizadores' => [
                    'enabled' => true,
                    'columns' => [['field' => 'amount', 'aggregate' => 'sum']],
                ],
                'permissions' => [],
            ]]
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function scoped(array $extra = []): Testable
    {
        return Livewire::test(BaseCrud::class, array_merge([
            'model' => LockedScopeStub::class,
            'lockedFilters' => ['owner_id' => 1],
        ], $extra));
    }

    /**
     * @return list<int>
     */
    private function visibleIds(Testable $component): array
    {
        return array_map(
            static fn (Model $row): int => (int) $row->getKey(),
            $component->instance()->rows()->items()
        );
    }

    #[Test]
    public function the_listing_shows_only_the_locked_owners_rows(): void
    {
        $this->assertEqualsCanonicalizing($this->mineIds, $this->visibleIds($this->scoped()));
    }

    #[Test]
    public function the_totalizador_sums_only_the_locked_owners_rows(): void
    {
        // 60, not 360: the aggregate runs through the same buildBaseQuery() as
        // the listing, so it cannot disagree with the visible rows.
        $totals = $this->scoped()->instance()->totalizadoresData();

        $this->assertSame(60, (int) $totals['amount']);
    }

    #[Test]
    public function filtering_the_locked_column_to_another_owner_reveals_nothing(): void
    {
        // The most natural attempt, and the one that works when a screen was
        // scoped with `initialFilter` instead: drive the locked column from the
        // client. The fixed `where` is ANDed on top, so the result is empty
        // rather than someone else's rows.
        $component = $this->scoped()->set('filters.owner_id', 2);

        $this->assertSame([], $this->visibleIds($component));
    }

    #[Test]
    public function clearing_every_filter_does_not_widen_the_scope(): void
    {
        // `clearFilters()` is exactly what defeats an `initialFilter`, and is the
        // reason the docs steer security-sensitive scoping here.
        $component = $this->scoped()->set('filters.owner_id', 2)->call('clearFilters');

        $this->assertEqualsCanonicalizing($this->mineIds, $this->visibleIds($component));
    }

    #[Test]
    public function the_property_itself_cannot_be_written_over_the_wire(): void
    {
        // #[Locked] on BaseCrud::$lockedFilters. Without it every guard above is
        // decoration: a forged payload would simply rewrite the scope.
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->scoped()->set('lockedFilters', ['owner_id' => 2]);
    }

    #[Test]
    public function a_record_outside_the_scope_cannot_be_opened_by_id(): void
    {
        // IDOR: the id is a plain integer a client can put on the wire, so the
        // single-record read has to be scoped too, not just the listing.
        $outsider = $this->theirsIds[0];

        $component = $this->scoped()->call('openEdit', $outsider);

        $component->assertSet('editingId', null);
        $this->assertSame([], $component->instance()->formData);
    }

    #[Test]
    public function an_in_scope_record_still_opens_normally(): void
    {
        // The counterpart, so the test above cannot pass merely because
        // openEdit() is broken for everything.
        $component = $this->scoped()->call('openEdit', $this->mineIds[0]);

        $component->assertSet('editingId', $this->mineIds[0]);
    }

    #[Test]
    public function without_the_lock_every_row_is_visible(): void
    {
        // Pins that the fixture really does contain the other owner's rows, so
        // every assertion above is measuring the lock and not an empty table.
        $component = Livewire::test(BaseCrud::class, ['model' => LockedScopeStub::class]);

        $this->assertEqualsCanonicalizing(
            array_merge($this->mineIds, $this->theirsIds),
            $this->visibleIds($component)
        );

        $this->assertSame(360, (int) $component->instance()->totalizadoresData()['amount']);
    }
}
