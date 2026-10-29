<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\BulkActionHandler;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\CrudScreenInspector;
use Ptah\Support\UploadDisk;
use Ptah\Tests\TestCase;

class L155Item extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'is_active', 'created_at'];
}

/** A service a hook receives in its constructor. */
class L155Recorder
{
    /** @var list<string> */
    public static array $calls = [];

    public function record(string $what): void
    {
        self::$calls[] = $what;
    }
}

class L155Hooks
{
    public function __construct(private L155Recorder $recorder) {}

    public function beforeCreate(array &$data, ?Model $record, object $component): void
    {
        if (($data['name'] ?? '') === 'reservado') {
            throw ValidationException::withMessages(['name' => 'Nome reservado.', 'outro' => 'Regra geral.']);
        }
        $this->recorder->record('beforeCreate');
    }

    public function afterDelete(array &$data, Model $record, object $component): void
    {
        $this->recorder->record('afterDelete:'.$record->getKey());
    }
}

class L155Bulk implements BulkActionHandler
{
    /** @var list<array{0: string, 1: list<int|string>}> */
    public static array $handled = [];

    public function handleBulkAction(array $ids, string $model, string $key): void
    {
        self::$handled[] = [$key, $ids];
    }
}

/**
 * Laudo 1.55.0 (JTEM): bulk actions drawn and gated per action, hooks
 * (validation in the modal, afterDelete, container), check --write, image
 * disk, money totals, default sort, break by period.
 */
class Laudo155Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        L155Recorder::$calls = [];
        L155Bulk::$handled = [];
        config()->set('ptah.crud.hook_namespaces', [__NAMESPACE__]);
        $this->actingAs(new GenericUser(['id' => 9]));
    }

    private function configure(array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => L155Item::class, 'route' => ''], ['config' => array_merge([
            'crud' => L155Item::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsGravar' => true, 'colsRenderer' => 'money'],
            ],
            'permissions' => [],
        ], $extra)]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => L155Item::class]);
    }

    // ── bulk actions ──────────────────────────────────────────────────────

    #[Test]
    public function a_bulk_action_with_its_own_permission_needs_neither_delete_nor_edit(): void
    {
        Gate::define('items.activate', fn () => true);
        $this->configure([
            'permissions' => ['showDeleteButton' => false, 'showEditButton' => false],
            'bulkActions' => [['label' => 'Ativar', 'action' => 'activate', 'method' => L155Bulk::class.'@activate', 'permission' => 'items.activate']],
        ]);
        $item = L155Item::create(['name' => 'A']);

        $crud = $this->crud()
            ->assertSeeHtml('wire:model.live="selectedRows"')
            ->set('selectedRows', [(string) $item->id])
            ->assertSee('Ativar');

        $crud->call('executeBulkAction', 'activate');

        $this->assertSame([['activate', [$item->id]]], L155Bulk::$handled);
    }

    #[Test]
    public function a_denied_bulk_action_is_neither_drawn_nor_run(): void
    {
        Gate::define('items.activate', fn () => false);
        $this->configure([
            'permissions' => ['showDeleteButton' => false],
            'bulkActions' => [['label' => 'Ativar', 'action' => 'activate', 'method' => L155Bulk::class.'@activate', 'permission' => 'items.activate']],
        ]);
        $item = L155Item::create(['name' => 'A']);

        $this->crud()
            ->assertDontSeeHtml('wire:model.live="selectedRows"')
            ->set('selectedRows', [(string) $item->id])
            ->assertDontSee('Ativar')
            ->call('executeBulkAction', 'activate');

        $this->assertSame([], L155Bulk::$handled);
    }

    // ── hooks ─────────────────────────────────────────────────────────────

    #[Test]
    public function a_hooks_validation_error_shows_in_the_modal_on_its_field(): void
    {
        $this->configure(['lifecycleHooks' => ['beforeCreate' => '@L155Hooks::beforeCreate']]);

        $this->crud()->call('openCreate')
            ->set('formData.name', 'reservado')
            ->call('save')
            ->assertSet('formErrors.name', 'Nome reservado.')
            ->assertSet('formErrors._general', 'Regra geral.');

        $this->assertSame(0, L155Item::count());
    }

    #[Test]
    public function a_hook_class_comes_from_the_container(): void
    {
        $this->configure(['lifecycleHooks' => ['beforeCreate' => '@L155Hooks::beforeCreate']]);

        $this->crud()->call('openCreate')->set('formData.name', 'livre')->call('save')->assertSet('formErrors', []);

        $this->assertSame(['beforeCreate'], L155Recorder::$calls);
    }

    #[Test]
    public function after_delete_runs_once_the_record_is_gone(): void
    {
        $this->configure(['lifecycleHooks' => ['afterDelete' => '@L155Hooks::afterDelete']]);
        $item = L155Item::create(['name' => 'A']);

        $this->crud()->call('confirmDelete', $item->id)->call('deleteRecord');

        $this->assertNull(L155Item::find($item->id));
        $this->assertSame(['afterDelete:'.$item->id], L155Recorder::$calls);
    }

    // ── ptah:check --write ────────────────────────────────────────────────

    #[Test]
    public function the_write_check_skips_screens_that_cannot_create_or_create_through_a_class_hook(): void
    {
        $cols = [['colsNomeFisico' => 'name', 'colsGravar' => true]];

        $off = CrudScreenInspector::roundTrip(new L155Item, ['cols' => $cols, 'permissions' => ['showCreateButton' => false]]);
        $this->assertSame('info', $off[0]['level']);

        $hooked = CrudScreenInspector::roundTrip(new L155Item, ['cols' => $cols, 'lifecycleHooks' => ['beforeCreate' => '@L155Hooks::beforeCreate']]);
        $this->assertSame('info', $hooked[0]['level']);
        $this->assertStringContainsString('L155Hooks', $hooked[0]['message']);

        $this->assertSame([], CrudScreenInspector::roundTrip(new L155Item, ['cols' => $cols]));
    }

    // ── image disk ────────────────────────────────────────────────────────

    #[Test]
    public function an_image_column_uses_its_disk_and_keeps_absolute_values(): void
    {
        config()->set('filesystems.disks.uploads', ['driver' => 'local', 'root' => sys_get_temp_dir(), 'url' => '/upload', 'visibility' => 'public']);

        $this->assertSame('uploads', UploadDisk::name(['colsUploadDisk' => 'uploads']));
        $this->assertSame('public', UploadDisk::name(['colsUploadDisk' => 'nope']));
        $this->assertSame('/upload/produtos/a.jpg', UploadDisk::url(['colsUploadDisk' => 'uploads'], 'produtos/a.jpg'));
        $this->assertSame('/upload/x.jpg', UploadDisk::url([], '/upload/x.jpg'));
        $this->assertStringEndsWith('/storage/images/a.jpg', UploadDisk::url([], 'images/a.jpg'));
    }

    // ── totals, sort, break ───────────────────────────────────────────────

    #[Test]
    public function a_money_columns_footer_total_is_formatted_as_money(): void
    {
        $this->configure(['totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]]]);
        L155Item::create(['name' => 'A', 'amount' => 1000]);
        L155Item::create(['name' => 'B', 'amount' => 234]);

        $this->crud()->assertSee('R$ 1.234,00');
    }

    #[Test]
    public function the_screen_opens_in_its_default_order(): void
    {
        $this->configure(['uiPreferences' => ['defaultSort' => 'name', 'defaultDirection' => 'asc']]);
        $this->crud()->assertSet('sort', 'name')->assertSet('direction', 'ASC');

        $this->configure(['uiPreferences' => ['defaultSort' => 'password', 'defaultDirection' => 'ASC']]);
        $this->crud()->assertSet('sort', 'id');
    }

    #[Test]
    public function a_date_break_by_month_groups_the_month(): void
    {
        $this->configure(['groupBreak' => 'created_at', 'groupBreakPeriod' => 'month']);
        L155Item::create(['name' => 'A', 'created_at' => Carbon::parse('2026-09-15')]);
        L155Item::create(['name' => 'B', 'created_at' => Carbon::parse('2026-10-02')]);
        L155Item::create(['name' => 'C', 'created_at' => Carbon::parse('2026-10-20')]);

        $html = $this->crud()->html();

        $this->assertSame(2, substr_count($html, 'class="ptah-c-break_row"'));
        $this->assertStringContainsString('September 2026', $html);
        $this->assertStringContainsString('October 2026', $html);
    }
}
