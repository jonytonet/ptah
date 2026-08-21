<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class DensityGlobalStub extends Model
{
    protected $table = 'bulk_action_stubs';

    protected $fillable = ['name', 'status'];
}

/**
 * O eixo global de densidade (v1.18) só alcança um BaseCrud cujo wrapper NÃO
 * carrega um override local: data-density="global" não casa com nenhuma regra
 * .ptah-base-crud[data-density=...], então os tokens herdam do <html>. Estes
 * testes pinam o default e a migração de legado ('comfortable' persistido na
 * era pré-eixo era o default do dropdown, não uma escolha — vira 'global';
 * compact/spacious eram deliberados e ficam pinados).
 */
class CrudDensityGlobalDefaultTest extends TestCase
{
    private function makeConfig(): void
    {
        CrudConfig::create([
            'model' => DensityGlobalStub::class,
            'route' => '',
            'config' => [
                'crud' => DensityGlobalStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    #[Test]
    public function a_fresh_crud_renders_data_density_global_and_no_local_recipe_matches(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->assertSet('viewDensity', 'global')
            ->assertSeeHtml('data-density="global"');

        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
        $this->assertDoesNotMatchRegularExpression(
            '/\.ptah-base-crud\[data-density="global"\]/',
            (string) $css,
            'Nao pode existir regra local para "global" — o valor existe exatamente para NAO casar com nada e herdar do <html>.'
        );
    }

    #[Test]
    public function a_legacy_persisted_comfortable_is_migrated_to_global_on_load(): void
    {
        $this->makeConfig();

        // Persiste o formato legado exatamente como o dropdown antigo gravava.
        $c = Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class]);
        $c->call('setViewDensity', 'comfortable');

        // Nova visita a tela: o valor legado precisa carregar como 'global'.
        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->assertSet('viewDensity', 'global');
    }

    #[Test]
    public function a_deliberate_compact_stays_pinned_across_visits(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->call('setViewDensity', 'compact');

        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->assertSet('viewDensity', 'compact')
            ->assertSeeHtml('data-density="compact"');
    }

    #[Test]
    public function choosing_global_in_the_dropdown_unpins_a_previously_pinned_screen(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->call('setViewDensity', 'compact');

        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->call('setViewDensity', 'global')
            ->assertSet('viewDensity', 'global');

        Livewire::test(BaseCrud::class, ['model' => DensityGlobalStub::class])
            ->assertSet('viewDensity', 'global');
    }
}
