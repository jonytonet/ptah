<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigComponent;
use Ptah\Models\CrudConfig;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

class GuardedOrder extends Model
{
    protected $table = 'guarded_orders';

    protected $fillable = ['title'];
}

/**
 * Tres defeitos publicados, achados na analise da 1.42:
 *  - `actionPermission` e `actionConfirm` eram gravados e nada os lia: toda
 *    acao aparecia para todos e rodava sem confirmar;
 *  - quem so podia criar nao via o Duplicar (a coluna de acoes exigia editar
 *    ou excluir);
 *  - salvar pelo editor apagava `permissions.history`/`.attachments`/`.import`.
 */
class CrudRowActionGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('guarded_orders', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });
        GuardedOrder::create(['title' => 'Pedido 1']);

        Gate::define('orders.approve', fn ($user) => (int) $user->getAuthIdentifier() === 1);
    }

    private function screen(array $action = [], array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => GuardedOrder::class, 'route' => ''], ['config' => [
            'crud' => GuardedOrder::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                array_merge(['colsNomeFisico' => 'id', 'colsNomeLogico' => 'Aprovar', 'colsTipo' => 'action', 'actionType' => 'livewire', 'actionValue' => 'approve(%id%)', 'actionIcon' => 'bx bx-check'], $action),
            ],
            'permissions' => $permissions,
        ]]);
    }

    private function html(): string
    {
        return Livewire::test(BaseCrud::class, ['model' => GuardedOrder::class])->html();
    }

    #[Test]
    public function an_action_with_a_permission_is_hidden_from_who_lacks_it(): void
    {
        $this->screen(['actionPermission' => 'orders.approve']);

        $this->actingAs(new GenericUser(['id' => 2]));
        $this->assertStringNotContainsString('approve(1)', $this->html());

        $this->actingAs(new GenericUser(['id' => 1]));
        $this->assertStringContainsString('approve(1)', $this->html());
    }

    #[Test]
    public function an_action_without_a_permission_shows_to_everyone_as_before(): void
    {
        $this->screen();

        $this->assertStringContainsString('approve(1)', $this->html());
    }

    #[Test]
    public function an_action_that_asks_for_confirmation_confirms(): void
    {
        $this->screen(['actionConfirm' => 'true', 'actionConfirmMessage' => 'Aprovar este pedido?']);
        $this->assertStringContainsString('wire:confirm="Aprovar este pedido?"', $this->html());

        $this->screen(['actionConfirm' => true]);
        $this->assertStringContainsString('wire:confirm="'.trans('ptah::ui.row_action_confirm').'"', $this->html());

        $this->screen();
        $this->assertStringNotContainsString('wire:confirm', $this->html());
    }

    #[Test]
    public function who_can_only_create_still_sees_duplicate(): void
    {
        $this->screen(permissions: ['showEditButton' => false, 'showDeleteButton' => false]);

        $this->assertStringContainsString('duplicateRecord(1)', $this->html());
    }

    #[Test]
    public function saving_in_the_editor_keeps_the_permissions_it_has_no_field_for(): void
    {
        config(['ptah.modules.permissions' => true]);
        $this->app->instance(PermissionService::class, new class extends PermissionService
        {
            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        });
        CrudConfig::create(['model' => 'Task', 'route' => '', 'config' => [
            'cols' => [['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text']],
            'permissions' => ['history' => 'tasks.audit', 'attachments' => 'tasks.files', 'import' => 'tasks.import', 'showCreateButton' => true],
        ]]);

        Livewire::test(CrudConfigComponent::class, ['model' => 'Task'])->call('openModal')->call('save');

        $permissions = CrudConfig::where('model', 'Task')->latest('id')->first()->config['permissions'];
        $this->assertSame('tasks.audit', $permissions['history'] ?? null);
        $this->assertSame('tasks.files', $permissions['attachments'] ?? null);
        $this->assertSame('tasks.import', $permissions['import'] ?? null);
    }
}
