<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Services\Cache\CacheService;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Tests\TestCase;

/**
 * Covers ptah:config:relabel — batch-fixes colsNomeLogico using LabelHumanizer,
 * without ever touching a deliberately custom label.
 */
class ConfigRelabelCommandTest extends TestCase
{
    private function seedConfig(string $model, array $cols): CrudConfig
    {
        return CrudConfig::create([
            'model' => $model,
            'route' => '',
            'config' => ['cols' => $cols],
        ]);
    }

    #[Test]
    public function dry_run_previews_the_accent_fix_without_persisting(): void
    {
        $this->seedConfig('Widget', [
            ['colsNomeFisico' => 'situacao', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text'],
        ]);

        // Note: both "Situacao" (before) and "Situação" (after) render on the same
        // table row/line — asserting only the "after" value avoids a false negative
        // from Laravel's output-mock matching consuming the shared line on the
        // first (broader) substring expectation.
        $this->artisan('ptah:config:relabel --dry-run')
            ->expectsOutputToContain('Situação')
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $row = CrudConfig::where('model', 'Widget')->first();
        $this->assertSame('Situacao', $row->config['cols'][0]['colsNomeLogico']);
    }

    #[Test]
    public function confirmed_run_persists_the_accent_fix(): void
    {
        $this->seedConfig('Widget', [
            ['colsNomeFisico' => 'situacao', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text'],
        ]);

        $this->artisan('ptah:config:relabel')
            ->expectsConfirmation('Apply the relabeling above?', 'yes')
            ->assertExitCode(0);

        $row = CrudConfig::where('model', 'Widget')->first();
        $this->assertSame('Situação', $row->config['cols'][0]['colsNomeLogico']);
    }

    #[Test]
    public function declining_the_confirmation_does_not_persist(): void
    {
        $this->seedConfig('Widget', [
            ['colsNomeFisico' => 'situacao', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text'],
        ]);

        $this->artisan('ptah:config:relabel')
            ->expectsConfirmation('Apply the relabeling above?', 'no')
            ->assertExitCode(0);

        $row = CrudConfig::where('model', 'Widget')->first();
        $this->assertSame('Situacao', $row->config['cols'][0]['colsNomeLogico']);
    }

    #[Test]
    public function custom_label_is_never_touched_without_all(): void
    {
        $this->seedConfig('Widget', [
            ['colsNomeFisico' => 'titulo', 'colsNomeLogico' => 'Meu Título', 'colsTipo' => 'text'],
        ]);

        $this->artisan('ptah:config:relabel --dry-run')
            ->expectsOutputToContain('Nothing to relabel')
            ->assertExitCode(0);

        $row = CrudConfig::where('model', 'Widget')->first();
        $this->assertSame('Meu Título', $row->config['cols'][0]['colsNomeLogico']);
    }

    #[Test]
    public function all_bypasses_the_heuristic_and_relabels_the_custom_field_too(): void
    {
        $this->seedConfig('Widget', [
            ['colsNomeFisico' => 'titulo', 'colsNomeLogico' => 'Meu Título', 'colsTipo' => 'text'],
        ]);

        $this->artisan('ptah:config:relabel --all')
            ->expectsConfirmation('Apply the relabeling above?', 'yes')
            ->assertExitCode(0);

        $row = CrudConfig::where('model', 'Widget')->first();
        $this->assertSame('Titulo', $row->config['cols'][0]['colsNomeLogico']);
    }

    #[Test]
    public function a_failure_mid_batch_rolls_back_the_whole_run(): void
    {
        $this->seedConfig('Widget1', [
            ['colsNomeFisico' => 'situacao', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text'],
        ]);
        $this->seedConfig('Widget2', [
            ['colsNomeFisico' => 'situacao', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text'],
        ]);

        // Succeeds for the first row, blows up while persisting the second —
        // simulating a failure in the middle of a batch run.
        $fake = new class($this->app->make(CacheService::class), $this->app->make(ConfigSchemaValidator::class)) extends CrudConfigService
        {
            public int $calls = 0;

            public function save(string $model, array $config, string $route = ''): CrudConfig
            {
                $this->calls++;
                if ($this->calls === 2) {
                    throw new \RuntimeException('simulated failure');
                }

                return parent::save($model, $config, $route);
            }
        };
        $this->app->instance(CrudConfigService::class, $fake);

        try {
            $this->artisan('ptah:config:relabel')
                ->expectsConfirmation('Apply the relabeling above?', 'yes')
                ->run();
            $this->fail('Expected the simulated failure to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        // The whole batch rolled back — Widget1's relabel, applied before the
        // failure, was not committed either.
        $row1 = CrudConfig::where('model', 'Widget1')->first();
        $row2 = CrudConfig::where('model', 'Widget2')->first();
        $this->assertSame('Situacao', $row1->config['cols'][0]['colsNomeLogico']);
        $this->assertSame('Situacao', $row2->config['cols'][0]['colsNomeLogico']);
    }

    #[Test]
    public function rerun_is_idempotent(): void
    {
        $this->seedConfig('Widget', [
            ['colsNomeFisico' => 'situacao', 'colsNomeLogico' => 'Situacao', 'colsTipo' => 'text'],
        ]);

        $this->artisan('ptah:config:relabel')->expectsConfirmation('Apply the relabeling above?', 'yes')->assertExitCode(0);

        // Second run: label is already "Situação" — nothing left to do.
        $this->artisan('ptah:config:relabel --dry-run')
            ->expectsOutputToContain('Nothing to relabel')
            ->assertExitCode(0);
    }
}
