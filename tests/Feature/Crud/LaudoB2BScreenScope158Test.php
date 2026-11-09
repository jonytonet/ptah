<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\CrudQueryScope;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\CrudScreenRoute;
use Ptah\Tests\TestCase;

class B2B158Item extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/** A range lock — what lockedFilters (equality only) cannot express. */
class B2B158AmountRange implements CrudQueryScope
{
    public function apply(Builder $query, array $params): void
    {
        $query->whereBetween('amount', [$params['from'], $params['to']]);
    }
}

/** A careless scope: a top-level orWhere must not escape the locks. */
class B2B158OrScope implements CrudQueryScope
{
    public function apply(Builder $query, array $params): void
    {
        $query->where('amount', '>', 100)->orWhere('amount', '<', 0);
    }
}

class B2B158NotAScope
{
    public function apply(Builder $query, array $params): void {}
}

/**
 * Laudo B2B (1.58.0): a BaseCrud mounted by another screen keeps its screen
 * config, and the host can lock any restriction through CrudQueryScope.
 */
class LaudoB2BScreenScope158Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9]));

        $cols = [
            ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
            ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
            ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
        ];

        CrudConfig::create(['model' => B2B158Item::class, 'route' => '', 'config' => [
            'crud' => B2B158Item::class, 'cols' => $cols, 'permissions' => [],
        ]]);
        CrudConfig::create(['model' => B2B158Item::class, 'route' => 'integration-monitor', 'config' => [
            'crud' => B2B158Item::class,
            'cols' => array_merge($cols, [['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Coluna Da Tela', 'colsTipo' => 'number']]),
            'permissions' => ['showCreateButton' => false],
        ]]);
    }

    // ── Item 1: which screen ────────────────────────────────────────────────

    #[Test]
    public function the_host_names_the_screen(): void
    {
        Livewire::test(BaseCrud::class, ['model' => B2B158Item::class, 'configRoute' => '/integration-monitor'])
            ->assertSet('configRoute', 'integration-monitor')
            ->assertSee('Coluna Da Tela');
    }

    #[Test]
    public function in_a_livewire_update_the_screen_is_the_page_not_the_endpoint(): void
    {
        $update = Request::create('/livewire/update', 'POST', [
            'components' => [['snapshot' => json_encode(['memo' => ['path' => 'integration-monitor', 'method' => 'GET']])]],
        ]);
        $update->headers->set('X-Livewire', '1');
        $this->app->instance('request', $update);

        $this->assertSame('integration-monitor', CrudScreenRoute::current());

        // No memo: Livewire's 'POST' placeholder is not a screen.
        $bare = Request::create('/livewire/update', 'POST', ['components' => [['snapshot' => '{}']]]);
        $bare->headers->set('X-Livewire', '1');
        $this->app->instance('request', $bare);

        $this->assertSame('', CrudScreenRoute::current());
    }

    #[Test]
    public function without_the_param_a_page_request_keeps_its_path(): void
    {
        $this->app->instance('request', Request::create('/integration-monitor'));

        $this->assertSame('integration-monitor', CrudScreenRoute::current());
    }

    #[Test]
    public function the_config_editor_gets_the_screen_from_the_crud(): void
    {
        $html = file_get_contents(__DIR__.'/../../../resources/views/livewire/base-crud/partials/_toolbar.blade.php');
        $this->assertStringContainsString("'configRoute' => \$configRoute", $html);

        Livewire::test(\Ptah\Livewire\BaseCrud\CrudConfig::class, ['model' => B2B158Item::class, 'configRoute' => 'integration-monitor'])
            ->assertSet('configRoute', 'integration-monitor');
    }

    // ── Item 2: the host's scope ────────────────────────────────────────────

    private function seedRows(): array
    {
        return [
            'in' => B2B158Item::create(['name' => 'Dentro', 'status' => 'active', 'amount' => 50]),
            'out' => B2B158Item::create(['name' => 'Fora', 'status' => 'active', 'amount' => 500]),
        ];
    }

    private function scoped(array $extra = [])
    {
        return Livewire::test(BaseCrud::class, array_merge([
            'model' => B2B158Item::class,
            'scope' => B2B158AmountRange::class,
            'scopeParams' => ['from' => 10, 'to' => 100],
        ], $extra));
    }

    #[Test]
    public function the_listing_shows_only_the_scope(): void
    {
        $this->seedRows();

        $rows = $this->scoped()->call('clearFilters')->instance()->rows;

        $this->assertSame(['Dentro'], collect($rows->items())->pluck('name')->all());
    }

    #[Test]
    public function single_record_actions_cannot_reach_outside_the_scope(): void
    {
        ['in' => $in, 'out' => $out] = $this->seedRows();

        $this->scoped()->call('openEdit', $out->id)->assertSet('editingId', null);
        $this->scoped()->call('openEdit', $in->id)->assertSet('editingId', $in->id);

        $this->scoped()->set('deletingId', $out->id)->call('deleteRecord');
        $this->assertDatabaseHas('items', ['id' => $out->id]);

        $this->scoped()
            ->set('editingId', $out->id)
            ->set('formData.name', 'Hacked')
            ->set('formData.status', 'active')
            ->call('save');
        $this->assertDatabaseHas('items', ['id' => $out->id, 'name' => 'Fora']);
    }

    #[Test]
    public function record_in_scope_checks_the_scope_by_key(): void
    {
        ['in' => $in, 'out' => $out] = $this->seedRows();
        $crud = $this->scoped()->instance();
        $check = (new \ReflectionMethod($crud, 'recordInScope'))->getClosure($crud);

        $this->assertTrue($check($in));
        $this->assertFalse($check($out));
    }

    #[Test]
    public function an_or_inside_the_scope_does_not_escape_the_locks(): void
    {
        B2B158Item::create(['name' => 'Grande Ativo', 'status' => 'active', 'amount' => 500]);
        B2B158Item::create(['name' => 'Negativo Arquivado', 'status' => 'archived', 'amount' => -5]);

        $rows = Livewire::test(BaseCrud::class, [
            'model' => B2B158Item::class,
            'lockedFilters' => ['status' => 'active'],
            'scope' => B2B158OrScope::class,
        ])->instance()->rows;

        $this->assertSame(['Grande Ativo'], collect($rows->items())->pluck('name')->all());
    }

    #[Test]
    public function a_class_that_is_not_a_scope_fails_loud(): void
    {
        // The test renderer wraps it in a ViewException; the message is ours.
        $this->expectExceptionMessage('must implement '.CrudQueryScope::class);

        Livewire::test(BaseCrud::class, ['model' => B2B158Item::class, 'scope' => B2B158NotAScope::class]);
    }

    #[Test]
    public function the_scope_cannot_be_changed_by_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->scoped()->set('scopeParams', ['from' => 0, 'to' => 1000]);
    }
}
