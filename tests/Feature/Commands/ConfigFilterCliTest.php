<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Services\Crud\FilterService;
use Ptah\Support\FilterRule;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class FilterCliStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * `ptah:config --filter=` never worked, and the docs said it did.
 *
 * Three layers disagreed: FilterParser emitted `field`, ConfigSchemaValidator
 * required `colsNomeFisico` (so every call failed validation), and the command
 * wrote to `config['filters']` while the runtime reads `customFilters` — so
 * even a filter that had somehow passed validation would never have been
 * applied. The interactive wizard added a fourth vocabulary
 * (`colsFilterField`), inert for the same reason.
 *
 * These tests pin the whole chain, because the failure was never in one layer:
 * it was the layers not agreeing.
 */
class ConfigFilterCliTest extends TestCase
{
    private function config(): array
    {
        return CrudConfig::where('model', ModelKey::canonical(FilterCliStub::class))->first()?->config ?? [];
    }

    #[Test]
    public function the_documented_example_no_longer_fails(): void
    {
        // The literal example from docs/KnownLimitations.md, which claimed
        // "these flags now work correctly via CLI" while exiting non-zero.
        $this->artisan('ptah:config', [
            'model' => FilterCliStub::class,
            '--filter' => ['status:boolean:label=Active'],
            '--non-interactive' => true,
        ])->assertExitCode(0);
    }

    #[Test]
    public function the_filter_lands_in_the_section_the_runtime_reads(): void
    {
        $this->artisan('ptah:config', [
            'model' => FilterCliStub::class,
            '--filter' => ['status:select:label=Situacao:operator==:options=a:A,b:B'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $config = $this->config();

        $this->assertArrayHasKey(FilterRule::SECTION, $config, 'O filtro precisa ir para customFilters.');
        $this->assertArrayNotHasKey(
            FilterRule::LEGACY_SECTION,
            $config,
            "A secao 'filters' e orfa: nada no runtime a le, e escrever nela era o bug."
        );
        $this->assertSame('status', $config[FilterRule::SECTION][0]['field']);
    }

    #[Test]
    public function the_runtime_actually_applies_what_the_cli_wrote(): void
    {
        // The end-to-end assertion the previous shape could never satisfy: the
        // service that filters the query has to produce a DTO from the stored
        // config. Anything less proves only that a JSON blob was written.
        $this->artisan('ptah:config', [
            'model' => FilterCliStub::class,
            '--filter' => ['status:select:label=Situacao:operator==:options=a:A,b:B'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $dtos = app(FilterService::class)->processCustomFilters(
            $this->config()[FilterRule::SECTION],
            ['status' => 'a']
        );

        $this->assertCount(1, $dtos, 'O runtime precisa gerar um filtro a partir do que o CLI gravou.');
        $this->assertSame('status', $dtos[0]->field);
    }

    #[Test]
    public function the_wizards_vocabulary_is_normalised_to_the_runtimes(): void
    {
        // FilterWizard emits colsFilterField/colsFilterLabel/colsFilterOperator
        // — none of which processCustomFilters() reads. Normalisation is what
        // makes an interactively-added filter work at all.
        $normalized = FilterRule::normalize([
            'colsFilterField' => 'status',
            'colsFilterLabel' => 'Situacao',
            'colsFilterOperator' => 'LIKE',
            'colsFilterType' => 'text',
        ]);

        $this->assertNotNull($normalized);
        $this->assertSame('status', $normalized['field']);
        $this->assertSame('Situacao', $normalized['label']);
        $this->assertSame('LIKE', $normalized['operator']);
    }

    #[Test]
    public function a_filter_without_a_field_is_rejected(): void
    {
        // The only thing processCustomFilters() cannot default.
        $this->assertNull(FilterRule::normalize(['label' => 'orphan']));
    }

    #[Test]
    public function the_doctor_migrates_the_legacy_section(): void
    {
        CrudConfig::create([
            'model' => ModelKey::canonical(FilterCliStub::class),
            'route' => '',
            'config' => [
                'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']],
                FilterRule::LEGACY_SECTION => [['field' => 'status', 'label' => 'Situacao']],
            ],
        ]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('legacy filters key')
            ->assertExitCode(1);

        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $config = $this->config();

        $this->assertArrayNotHasKey(FilterRule::LEGACY_SECTION, $config);
        $this->assertSame('status', $config[FilterRule::SECTION][0]['field']);
    }
}
