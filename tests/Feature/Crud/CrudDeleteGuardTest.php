<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\GuardsDeletion;
use Ptah\Exceptions\CrudHookAbort;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class GuardedBrand extends Model implements GuardsDeletion
{
    use SoftDeletes;

    protected $table = 'guarded_brands';

    protected $fillable = ['name', 'in_use', 'deleted_by'];

    /** 'false' | 'throw' | null — what the `deleting` event does. */
    public static ?string $deleting = null;

    protected static function booted(): void
    {
        static::deleting(function () {
            if (self::$deleting === 'false') {
                return false;
            }
            if (self::$deleting === 'throw') {
                throw new \RuntimeException('estoque vinculado');
            }

            return null;
        });
    }

    public function deletionBlockedReason(): ?string
    {
        return $this->in_use ? 'Marca em uso por produtos ativos.' : null;
    }
}

class GuardedBrandHooks
{
    public function beforeDelete(array &$data, Model $record, object $component): void
    {
        if ($record->name === 'Fornecedor com NF') {
            throw new CrudHookAbort('Fornecedor com notas lançadas.');
        }
        if ($record->name === 'Hook quebrado') {
            throw new \LogicException('bug no hook');
        }
    }
}

/**
 * Achado do ERP (1.40.0): a exclusao nao tinha como dizer "nao pode". Um
 * `deleting` que devolvia false cancelava e o BaseCrud mostrava "Excluido"
 * com Desfazer; um que lancava virava 500; e o `deleted_by` era gravado antes,
 * deixando o registro ativo marcado como excluido.
 */
class CrudDeleteGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('guarded_brands', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('in_use')->default(false);
            $t->unsignedBigInteger('deleted_by')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        GuardedBrand::$deleting = null;
        config()->set('ptah.crud.hook_namespaces', [__NAMESPACE__]);
        CrudConfig::updateOrCreate(['model' => GuardedBrand::class, 'route' => ''], ['config' => [
            'crud' => GuardedBrand::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true]],
            'permissions' => [],
            'lifecycleHooks' => ['beforeDelete' => '@GuardedBrandHooks::beforeDelete'],
        ]]);

        $this->actingAs(new GenericUser(['id' => 9]));
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => GuardedBrand::class]);
    }

    private function assertRefused($crud, GuardedBrand $brand, string $reason): void
    {
        $crud->assertNotDispatched('crud-deleted')
            ->assertDispatched('ptah-toast', fn ($name, $p) => ($p['title'] ?? '') === $reason && ($p['color'] ?? '') === 'danger')
            ->assertNotDispatched('ptah-toast', fn ($name, $p) => ($p['title'] ?? '') === trans('ptah::ui.toast_deleted'));

        $fresh = GuardedBrand::withTrashed()->find($brand->id);
        $this->assertNull($fresh->deleted_at, 'O registro barrado nao pode sair da lista.');
        $this->assertNull($fresh->deleted_by, 'deleted_by gravado num registro que continua ativo.');
    }

    #[Test]
    public function the_model_reason_is_shown_before_the_confirmation_opens(): void
    {
        $brand = GuardedBrand::create(['name' => 'Acme', 'in_use' => true]);

        $this->crud()->call('confirmDelete', $brand->id)
            ->assertSet('showDeleteConfirm', false)
            ->assertDispatched('ptah-toast', title: 'Marca em uso por produtos ativos.', color: 'danger');
    }

    #[Test]
    public function the_model_reason_is_checked_again_on_delete(): void
    {
        $brand = GuardedBrand::create(['name' => 'Acme']);
        $crud = $this->crud()->call('confirmDelete', $brand->id)->assertSet('showDeleteConfirm', true);

        $brand->update(['in_use' => true]); // passou a estar em uso depois de abrir
        $crud->call('deleteRecord');

        $this->assertRefused($crud, $brand, 'Marca em uso por produtos ativos.');
    }

    #[Test]
    public function a_before_delete_hook_refuses_with_its_message(): void
    {
        $brand = GuardedBrand::create(['name' => 'Fornecedor com NF']);

        $crud = $this->crud()->call('confirmDelete', $brand->id)->call('deleteRecord');

        $this->assertRefused($crud, $brand, 'Fornecedor com notas lançadas.');
    }

    #[Test]
    public function a_before_delete_hook_that_breaks_refuses_instead_of_letting_the_delete_through(): void
    {
        $brand = GuardedBrand::create(['name' => 'Hook quebrado']);

        $crud = $this->crud()->call('confirmDelete', $brand->id)->call('deleteRecord');

        $this->assertRefused($crud, $brand, trans('ptah::ui.crud_delete_refused', ['message' => 'bug no hook']));
    }

    #[Test]
    public function a_deleting_event_returning_false_is_not_reported_as_deleted(): void
    {
        GuardedBrand::$deleting = 'false';
        $brand = GuardedBrand::create(['name' => 'Acme']);

        $crud = $this->crud()->call('confirmDelete', $brand->id)->call('deleteRecord');

        $this->assertRefused($crud, $brand, trans('ptah::ui.crud_delete_not_done'));
    }

    #[Test]
    public function a_deleting_event_that_throws_is_a_toast_not_a_500(): void
    {
        GuardedBrand::$deleting = 'throw';
        $brand = GuardedBrand::create(['name' => 'Acme']);

        $crud = $this->crud()->call('confirmDelete', $brand->id)->call('deleteRecord');

        $this->assertRefused($crud, $brand, trans('ptah::ui.crud_delete_refused', ['message' => 'estoque vinculado']));
    }

    #[Test]
    public function a_delete_that_goes_through_is_reported_and_stamped(): void
    {
        $brand = GuardedBrand::create(['name' => 'Acme']);

        $this->crud()->call('confirmDelete', $brand->id)->call('deleteRecord')
            ->assertDispatched('crud-deleted')
            ->assertDispatched('ptah-toast', title: trans('ptah::ui.toast_deleted'), undoId: $brand->id);

        $fresh = GuardedBrand::withTrashed()->find($brand->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(9, (int) $fresh->deleted_by);
    }

    #[Test]
    public function bulk_delete_skips_the_refused_rows_counts_them_and_keeps_them_selected(): void
    {
        $free = GuardedBrand::create(['name' => 'Livre']);
        $used = GuardedBrand::create(['name' => 'Usada', 'in_use' => true]);
        $withNf = GuardedBrand::create(['name' => 'Fornecedor com NF']);

        $crud = $this->crud()
            ->set('selectedRows', [$free->id, $used->id, $withNf->id])
            ->call('bulkDelete')
            ->assertDispatched('crud-bulk-deleted', count: 1)
            ->assertDispatched('ptah-toast', title: trans('ptah::ui.bulk_toast_deleted', ['n' => 1]))
            ->assertDispatched('ptah-toast', title: trans('ptah::ui.bulk_toast_delete_refused', ['n' => 2, 'reason' => 'Marca em uso por produtos ativos.'])
                .' '.trans('ptah::ui.bulk_toast_delete_refused_more', ['n' => 1]), color: 'danger');

        $this->assertEqualsCanonicalizing([(string) $used->id, (string) $withNf->id], array_map('strval', $crud->get('selectedRows')));
        $this->assertSoftDeleted('guarded_brands', ['id' => $free->id]);
        $this->assertNull(GuardedBrand::find($used->id)->deleted_at);
        $this->assertNull(GuardedBrand::find($withNf->id)->deleted_at);
    }

    #[Test]
    public function bulk_force_delete_answers_to_the_same_guards(): void
    {
        $used = GuardedBrand::create(['name' => 'Usada', 'in_use' => true]);
        $used->delete(); // na lixeira, mas ainda em uso

        $this->crud()->set('selectedRows', [$used->id])->call('bulkForceDelete')
            ->assertNotDispatched('crud-bulk-deleted');

        $this->assertNotNull(GuardedBrand::withTrashed()->find($used->id), 'Exclusao definitiva ignorou a guarda.');
    }
}
