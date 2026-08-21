<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use ReflectionProperty;

class CrudConfigLockStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * crudConfig governs cols, rows, totals, print, export, hooks, bulkActions and
 * permissions — every downstream check derives from it. It is only ever
 * assigned server-side (boot()/mount()/reloadCrudConfig(), all reading from
 * CrudConfigService/DB). #[Locked] closes the client-writable hole: without
 * it, a forged Livewire ->set() request could overwrite crudConfig with
 * arbitrary permissions/hooks, bypassing every check derived from it.
 */
class CrudCrudConfigLockedPropertyTest extends TestCase
{
    private function makeConfig(array $overrides = []): void
    {
        CrudConfig::create(array_merge([
            'model' => CrudConfigLockStub::class,
            'route' => '',
            'config' => [
                'crud' => CrudConfigLockStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ], $overrides));
    }

    #[Test]
    public function the_crud_config_property_carries_the_locked_attribute(): void
    {
        $property = new ReflectionProperty(BaseCrud::class, 'crudConfig');
        $attributes = $property->getAttributes(Locked::class);

        $this->assertNotEmpty($attributes, 'crudConfig must be #[Locked] — see BaseCrud.php');
    }

    #[Test]
    public function the_client_cannot_mutate_crud_config_directly(): void
    {
        $this->makeConfig();
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, [
            'model' => CrudConfigLockStub::class,
        ])->set('crudConfig', ['permissions' => ['showDeleteButton' => true]]);
    }

    /**
     * Proves #[Locked] does not break the legitimate editor → event → reload
     * flow: the CrudConfig editor persists the new config to the DB and
     * dispatches 'ptah:crud-config-updated'; BaseCrud's #[On] handler
     * (reloadCrudConfig(), server-side) must still pick it up.
     */
    #[Test]
    public function the_crud_config_updated_event_still_reloads_the_config_server_side(): void
    {
        $this->makeConfig();

        $component = Livewire::test(BaseCrud::class, [
            'model' => CrudConfigLockStub::class,
        ]);

        $this->assertSame([], $component->get('crudConfig')['permissions']);

        // Simulates the editor saving a new config to the DB.
        CrudConfig::where('model', CrudConfigLockStub::class)->where('route', '')->update([
            'config' => [
                'crud' => CrudConfigLockStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => ['showDeleteButton' => false],
            ],
        ]);

        $component->dispatch('ptah:crud-config-updated');

        $this->assertSame(false, $component->get('crudConfig')['permissions']['showDeleteButton']);
    }
}
