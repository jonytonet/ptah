<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stubs ─────────────────────────────────────────────────────────────────────

/** Soft-deletable model with a `status` column — see tests/migrations. */
class BulkActionStub extends Model
{
    use SoftDeletes;

    protected $table = 'bulk_action_stubs';

    protected $fillable = ['name', 'status'];
}

/** Custom bulk-action handler wired via crudConfig['bulkActions'][]['method']. */
class BulkApproveHandler
{
    public function handle(array $ids, string $model): void
    {
        BulkActionStub::whereIn('id', $ids)->update(['status' => 'approved']);
    }
}

/**
 * Covers HasCrudBulkActions (bulkDelete/bulkRestore/bulkForceDelete/
 * executeBulkAction) and HasCrudExport::bulkExport():
 *
 *  - Authorization: every mutating bulk action must go through the same
 *    fail-closed authorizeCrudAction() gate as the single-record actions.
 *  - Scoping: every bulk action must operate on scopedQuery()/buildBaseQuery()
 *    (company / master-detail lock), never a raw newQuery(), so a
 *    client-supplied id in selectedRows cannot reach a row outside the
 *    current scope (IDOR).
 */
class CrudBulkActionsTest extends TestCase
{
    private function makeConfig(array $permissions = [], array $extra = []): void
    {
        CrudConfig::create([
            'model' => BulkActionStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => BulkActionStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => $permissions,
            ], $extra),
        ]);
    }

    /** @return array{in: BulkActionStub, out: BulkActionStub} */
    private function seedTwo(): array
    {
        return [
            'in' => BulkActionStub::create(['name' => 'InScope', 'status' => 'active']),
            'out' => BulkActionStub::create(['name' => 'OutScope', 'status' => 'archived']),
        ];
    }

    /** A CRUD locked to status=active — "archived" rows are outside the scope. */
    private function lockedCrud()
    {
        return Livewire::test(BaseCrud::class, [
            'model' => BulkActionStub::class,
            'lockedFilters' => ['status' => 'active'],
        ]);
    }

    private function withBulkApproveAction(): void
    {
        $cfg = CrudConfig::where('model', BulkActionStub::class)->first();
        $cfg->update([
            'config' => array_merge($cfg->config, [
                'bulkActions' => [
                    ['label' => 'Approve', 'action' => 'approve', 'method' => BulkApproveHandler::class.'@handle'],
                ],
            ]),
        ]);
    }

    // ── Authorization: bulkDelete ────────────────────────────────────────────

    #[Test]
    public function bulk_delete_is_denied_for_an_anonymous_user_when_a_permission_identifier_is_set(): void
    {
        $this->makeConfig(['permissionIdentifier' => 'stub.bulk']);
        $record = BulkActionStub::create(['name' => 'Protected', 'status' => 'active']);

        Livewire::test(BaseCrud::class, ['model' => BulkActionStub::class])
            ->set('selectedRows', [(string) $record->id])
            ->call('bulkDelete');

        $this->assertNotNull(
            BulkActionStub::find($record->id),
            'Anonymous users must not bulk-delete when a permissionIdentifier is configured',
        );
    }

    #[Test]
    public function bulk_delete_still_soft_deletes_when_no_permission_identifier_is_configured(): void
    {
        $this->makeConfig();
        $record = BulkActionStub::create(['name' => 'Free', 'status' => 'active']);

        Livewire::test(BaseCrud::class, ['model' => BulkActionStub::class])
            ->set('selectedRows', [(string) $record->id])
            ->call('bulkDelete');

        $this->assertNotNull(BulkActionStub::withTrashed()->find($record->id)->deleted_at);
    }

    // ── Scoping: bulkDelete ──────────────────────────────────────────────────

    #[Test]
    public function bulk_delete_cannot_reach_a_row_outside_the_lock_scope(): void
    {
        $this->makeConfig();
        ['in' => $in, 'out' => $out] = $this->seedTwo();

        $this->lockedCrud()
            ->set('selectedRows', [(string) $in->id, (string) $out->id])
            ->call('bulkDelete');

        $this->assertNotNull(BulkActionStub::find($out->id), 'Out-of-scope row must survive');
        $this->assertNull(BulkActionStub::find($in->id), 'In-scope row must be soft deleted');
    }

    // ── Authorization + scoping: bulkRestore ─────────────────────────────────

    #[Test]
    public function bulk_restore_is_denied_for_an_anonymous_user_when_a_permission_identifier_is_set(): void
    {
        $this->makeConfig(['permissionIdentifier' => 'stub.bulk']);
        $record = BulkActionStub::create(['name' => 'Trashed', 'status' => 'active']);
        $record->delete();

        Livewire::test(BaseCrud::class, ['model' => BulkActionStub::class])
            ->set('selectedRows', [(string) $record->id])
            ->call('bulkRestore');

        $this->assertNotNull(
            BulkActionStub::withTrashed()->find($record->id)->deleted_at,
            'Anonymous users must not bulk-restore when a permissionIdentifier is configured',
        );
    }

    #[Test]
    public function bulk_restore_cannot_reach_a_trashed_row_outside_the_lock_scope(): void
    {
        $this->makeConfig();
        ['in' => $in, 'out' => $out] = $this->seedTwo();
        $in->delete();
        $out->delete();

        $this->lockedCrud()
            ->set('selectedRows', [(string) $in->id, (string) $out->id])
            ->call('bulkRestore');

        $this->assertNotNull(
            BulkActionStub::withTrashed()->find($out->id)->deleted_at,
            'Out-of-scope row must remain trashed',
        );
        $this->assertNull(BulkActionStub::find($in->id)->deleted_at, 'In-scope row must be restored');
    }

    // ── Authorization + scoping: bulkForceDelete ─────────────────────────────

    #[Test]
    public function bulk_force_delete_is_denied_for_an_anonymous_user_when_a_permission_identifier_is_set(): void
    {
        $this->makeConfig(['permissionIdentifier' => 'stub.bulk']);
        $record = BulkActionStub::create(['name' => 'Trashed', 'status' => 'active']);
        $record->delete();

        Livewire::test(BaseCrud::class, ['model' => BulkActionStub::class])
            ->set('selectedRows', [(string) $record->id])
            ->call('bulkForceDelete');

        $this->assertNotNull(
            BulkActionStub::withTrashed()->find($record->id),
            'Anonymous users must not bulk-force-delete when a permissionIdentifier is configured',
        );
    }

    #[Test]
    public function bulk_force_delete_cannot_reach_a_trashed_row_outside_the_lock_scope(): void
    {
        $this->makeConfig();
        ['in' => $in, 'out' => $out] = $this->seedTwo();
        $in->delete();
        $out->delete();

        $this->lockedCrud()
            ->set('selectedRows', [(string) $in->id, (string) $out->id])
            ->call('bulkForceDelete');

        $this->assertNotNull(BulkActionStub::withTrashed()->find($out->id), 'Out-of-scope row must survive force-delete');
        $this->assertNull(BulkActionStub::withTrashed()->find($in->id), 'In-scope row must be hard deleted');
    }

    // ── Authorization: executeBulkAction ─────────────────────────────────────

    #[Test]
    public function execute_bulk_action_is_denied_for_an_anonymous_user_when_a_permission_identifier_is_set(): void
    {
        $this->makeConfig(['permissionIdentifier' => 'stub.bulk']);
        $this->withBulkApproveAction();
        $record = BulkActionStub::create(['name' => 'Untouched', 'status' => 'active']);

        Livewire::test(BaseCrud::class, ['model' => BulkActionStub::class])
            ->set('selectedRows', [(string) $record->id])
            ->call('executeBulkAction', 'approve');

        $this->assertSame(
            'active',
            BulkActionStub::find($record->id)->status,
            'Denied user must not reach the custom bulk handler',
        );
    }

    #[Test]
    public function execute_bulk_action_still_runs_the_custom_handler_when_authorized(): void
    {
        $this->makeConfig();
        $this->withBulkApproveAction();
        $record = BulkActionStub::create(['name' => 'Free', 'status' => 'active']);

        Livewire::test(BaseCrud::class, ['model' => BulkActionStub::class])
            ->set('selectedRows', [(string) $record->id])
            ->call('executeBulkAction', 'approve');

        $this->assertSame('approved', BulkActionStub::find($record->id)->status);
    }

    // ── Scoping: executeBulkAction ───────────────────────────────────────────

    #[Test]
    public function execute_bulk_action_never_hands_an_out_of_scope_id_to_the_custom_handler(): void
    {
        $this->makeConfig();
        $this->withBulkApproveAction();
        ['in' => $in, 'out' => $out] = $this->seedTwo();

        $this->lockedCrud()
            ->set('selectedRows', [(string) $in->id, (string) $out->id])
            ->call('executeBulkAction', 'approve');

        $this->assertSame(
            'archived',
            BulkActionStub::find($out->id)->status,
            'The handler receives whatever ids this component gives it — an out-of-scope id must be filtered out BEFORE the handoff',
        );
        $this->assertSame('approved', BulkActionStub::find($in->id)->status, 'In-scope row must be approved');
    }

    #[Test]
    public function execute_bulk_action_skips_the_handler_entirely_when_no_selected_id_is_in_scope(): void
    {
        $this->makeConfig();
        $this->withBulkApproveAction();
        ['out' => $out] = $this->seedTwo();

        $this->lockedCrud()
            ->set('selectedRows', [(string) $out->id])
            ->call('executeBulkAction', 'approve');

        $this->assertSame('archived', BulkActionStub::find($out->id)->status);
    }

    // ── FIX 2: bulkExport must intersect selectedRows with the scoped query ──

    #[Test]
    public function bulk_export_excludes_an_id_outside_the_lock_scope(): void
    {
        $this->makeConfig();
        ['in' => $in, 'out' => $out] = $this->seedTwo();

        $url = null;
        $this->lockedCrud()
            ->set('selectedRows', [(string) $in->id, (string) $out->id])
            ->call('bulkExport', 'excel')
            ->assertDispatched('ptah:export-download', function ($event, $params) use (&$url) {
                $url = $params['url'] ?? null;

                return $url !== null;
            });

        $token = basename(parse_url($url, PHP_URL_PATH));
        $payload = Cache::get('ptah:export:'.$token);

        $this->assertIsArray($payload);
        $this->assertSame([$in->id], $payload['ids'], 'Only the in-scope id may reach the export token');
    }

    #[Test]
    public function bulk_export_is_a_no_op_when_every_selected_id_is_outside_the_scope(): void
    {
        $this->makeConfig();
        ['out' => $out] = $this->seedTwo();

        $this->lockedCrud()
            ->set('selectedRows', [(string) $out->id])
            ->call('bulkExport', 'excel')
            ->assertNotDispatched('ptah:export-download');
    }
}
