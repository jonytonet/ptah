<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class SdAliasStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class SdAliasServiceStub
{
    public function search(string $term): array
    {
        return [['value' => 1, 'label' => "matched:{$term}"]];
    }
}

/**
 * Covers HasCrudSearchDropdown::sdSettings()'s read-alias resolution: a
 * config written in the editor's legacy dialect (colsSDValueField/
 * colsSDLabelField/colsSDOrderBy/colsSDMode) or the CLI-wizard's dead dialect
 * (colsSdTable/colsSdSelectColumn/colsSdValueColumn/colsSdOrderBy/colsSdLimit)
 * must resolve identically to a config already written in the canonical keys.
 */
class CrudSearchDropdownAliasTest extends TestCase
{
    private function makeConfig(array $sdCol): void
    {
        CrudConfig::updateOrCreate(
            ['model' => SdAliasStub::class, 'route' => ''],
            ['config' => [
                'crud' => SdAliasStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    array_merge([
                        'colsNomeFisico' => 'category_id',
                        'colsNomeLogico' => 'Target',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                    ], $sdCol),
                ],
                'permissions' => [],
            ]],
        );
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => SdAliasStub::class]);
    }

    private function sdSettingsFor(array $sdCol): array
    {
        $this->makeConfig($sdCol);
        $col = collect(CrudConfig::where('model', SdAliasStub::class)->first()->config['cols'])
            ->firstWhere('colsNomeFisico', 'category_id');

        // sdSettings e protected de proposito (public seria wire-callable —
        // achado de revisao); o teste alcanca via reflexao.
        $component = $this->crud()->instance();
        $m = new \ReflectionMethod($component, 'sdSettings');

        return $m->invoke($component, $col);
    }

    // ── Editor legacy dialect ≡ canonical ────────────────────────────────────

    #[Test]
    public function editor_dialect_resolves_the_same_as_the_canonical_keys(): void
    {
        $canonical = $this->sdSettingsFor([
            'colsSDModel' => SdAliasStub::class,
            'colsSDValor' => 'id',
            'colsSDLabel' => 'name',
            'colsSDOrder' => 'name asc',
        ]);

        $viaEditorDialect = $this->sdSettingsFor([
            'colsSDModel' => SdAliasStub::class,
            'colsSDValueField' => 'id',
            'colsSDLabelField' => 'name',
            'colsSDOrderBy' => 'name asc',
        ]);

        $this->assertSame($canonical['value'], $viaEditorDialect['value']);
        $this->assertSame($canonical['label'], $viaEditorDialect['label']);
        $this->assertSame($canonical['order'], $viaEditorDialect['order']);
    }

    #[Test]
    public function editor_dialect_search_returns_the_same_results_as_canonical(): void
    {
        $this->makeConfig([
            'colsSDModel' => SdAliasStub::class,
            'colsSDValueField' => 'id',
            'colsSDLabelField' => 'name',
        ]);
        SdAliasStub::create(['name' => 'Alpha']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'alpha')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results[0]['label']);
    }

    // ── Wizard dead dialect ≡ canonical ──────────────────────────────────────

    #[Test]
    public function wizard_dialect_resolves_the_same_as_the_canonical_keys(): void
    {
        $canonical = $this->sdSettingsFor([
            'colsSDModel' => SdAliasStub::class,
            'colsSDValor' => 'id',
            'colsSDLabel' => 'name',
            'colsSDOrder' => 'name asc',
            'colsSDLimit' => 5,
        ]);

        $viaWizardDialect = $this->sdSettingsFor([
            'colsSdTable' => SdAliasStub::class,
            'colsSdValueColumn' => 'id',
            'colsSdSelectColumn' => 'name',
            'colsSdOrderBy' => 'name asc',
            'colsSdLimit' => 5,
        ]);

        $this->assertSame($canonical['model'], $viaWizardDialect['model']);
        $this->assertSame($canonical['value'], $viaWizardDialect['value']);
        $this->assertSame($canonical['label'], $viaWizardDialect['label']);
        $this->assertSame($canonical['order'], $viaWizardDialect['order']);
        $this->assertSame($canonical['limit'], $viaWizardDialect['limit']);
    }

    #[Test]
    public function wizard_dialect_search_returns_results(): void
    {
        $this->makeConfig([
            'colsSdTable' => SdAliasStub::class,
            'colsSdValueColumn' => 'id',
            'colsSdSelectColumn' => 'name',
        ]);
        SdAliasStub::create(['name' => 'Beta']);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'beta')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame('Beta', $results[0]['label']);
    }

    // ── Service mode via colsSDTipo + colsSDService + colsSDServiceMethod ───

    #[Test]
    public function service_mode_composes_the_model_string_from_service_plus_method(): void
    {
        $settings = $this->sdSettingsFor([
            'colsSDTipo' => 'service',
            'colsSDService' => SdAliasServiceStub::class,
            'colsSDServiceMethod' => 'search',
        ]);

        $this->assertSame(SdAliasServiceStub::class.'\\search', $settings['model']);
        $this->assertSame('service', $settings['tipo']);
    }

    #[Test]
    public function service_mode_search_calls_through_to_the_service(): void
    {
        $this->makeConfig([
            'colsSDTipo' => 'service',
            'colsSDService' => SdAliasServiceStub::class,
            'colsSDServiceMethod' => 'search',
        ]);

        $results = $this->crud()->call('searchDropdown', 'category_id', 'hello')->get('sdResults')['category_id'];

        $this->assertSame([['value' => 1, 'label' => 'matched:hello']], $results);
    }

    // ── colsSDMode='both' must never hijack colsSDTipo ──────────────────────

    #[Test]
    public function cols_sd_mode_both_falls_back_to_model_instead_of_hijacking_colssdtipo(): void
    {
        $settings = $this->sdSettingsFor([
            'colsSDModel' => SdAliasStub::class,
            'colsSDMode' => 'both',
        ]);

        $this->assertSame('model', $settings['tipo']);
    }

    #[Test]
    public function cols_sd_mode_model_or_service_does_alias_colssdtipo(): void
    {
        $settings = $this->sdSettingsFor([
            'colsSDModel' => SdAliasStub::class,
            'colsSDMode' => 'service',
            'colsSDService' => SdAliasServiceStub::class,
            'colsSDServiceMethod' => 'search',
        ]);

        $this->assertSame('service', $settings['tipo']);
    }
}
