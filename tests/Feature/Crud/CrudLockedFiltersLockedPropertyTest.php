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

class LockedFiltersStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * lockedFilters enforces a master/detail lock on every query (see
 * HasCrudQuery::buildBaseQuery()/scopedQuery()) and is only ever assigned
 * server-side in mount() (a constructor param). #[Locked] closes the
 * client-writable hole: without it, a forged Livewire ->set() request could
 * overwrite lockedFilters and escape the very lock it exists to enforce.
 */
class CrudLockedFiltersLockedPropertyTest extends TestCase
{
    private function makeConfig(): void
    {
        CrudConfig::create([
            'model' => LockedFiltersStub::class,
            'route' => '',
            'config' => [
                'crud' => LockedFiltersStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    #[Test]
    public function the_locked_filters_property_carries_the_locked_attribute(): void
    {
        $property = new ReflectionProperty(BaseCrud::class, 'lockedFilters');
        $attributes = $property->getAttributes(Locked::class);

        $this->assertNotEmpty($attributes, 'lockedFilters must be #[Locked] — see BaseCrud.php');
    }

    #[Test]
    public function the_client_cannot_mutate_locked_filters_directly(): void
    {
        $this->makeConfig();
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, [
            'model' => LockedFiltersStub::class,
            'lockedFilters' => ['status' => 'active'],
        ])->set('lockedFilters', ['status' => 'hacked']);
    }
}
