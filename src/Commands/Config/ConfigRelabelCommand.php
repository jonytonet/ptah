<?php

declare(strict_types=1);

namespace Ptah\Commands\Config;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ptah\Models\CrudConfig;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Support\LabelHumanizer;

/**
 * Batch-relabels `colsNomeLogico` across every `crud_configs` row using the
 * same humanizer `ptah:forge`/`ptah:config` already apply to NEW columns
 * (`LabelHumanizer::make`). Rows created before the dictionary grew (or before
 * an accent was added to it) keep their original, un-accented label forever —
 * this command lets an admin catch up without hand-editing every screen.
 *
 * Safety: by default a column is only relabeled when its current label is
 * provably just the de-accented (ASCII) form of what the humanizer would now
 * produce — `Str::ascii($current) === Str::ascii($new) && $current !== $new`
 * (e.g. "Situacao" → "Situação"). A deliberately custom label ("Meu Título")
 * never matches this and is left untouched. `--all` bypasses the heuristic
 * (escape hatch) and relabels every column whose label differs from the
 * humanizer output — it still requires confirmation before persisting.
 */
class ConfigRelabelCommand extends Command
{
    protected $signature = 'ptah:config:relabel
        {--dry-run : Preview the changes without persisting anything}
        {--all : Relabel every colsNomeLogico that differs from the humanizer output, bypassing the accent-only heuristic}';

    protected $description = 'Batch-relabel colsNomeLogico using LabelHumanizer (accent fixes by default; --all for a full relabel)';

    public function handle(CrudConfigService $configService): int
    {
        $rows = CrudConfig::query()->get();

        if ($rows->isEmpty()) {
            $this->info('No crud_configs found — nothing to relabel.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $all = (bool) $this->option('all');

        $plan = $this->buildPlan($rows, $all);

        if ($plan === []) {
            $this->info('Nothing to relabel — every colsNomeLogico is already up to date (or custom; run --all to override).');

            return self::SUCCESS;
        }

        $this->renderPlan($plan);

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry-run: no changes were persisted.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Apply the relabeling above?', false)) {
            $this->warn('Aborted — no changes were persisted.');

            return self::SUCCESS;
        }

        $updated = $this->applyPlan($plan, $configService);

        $this->newLine();
        $this->info("Relabeled {$updated} config(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, CrudConfig>  $rows
     * @return array<int, array{row: CrudConfig, changes: array<int, array{field: string, from: string, to: string}>}>
     */
    protected function buildPlan(Collection $rows, bool $all): array
    {
        $plan = [];

        foreach ($rows as $row) {
            $cols = $row->config['cols'] ?? [];
            $changes = [];

            foreach ($cols as $index => $col) {
                if (empty($col['colsNomeFisico'])) {
                    continue;
                }

                $atual = (string) ($col['colsNomeLogico'] ?? '');
                $novo = LabelHumanizer::make((string) $col['colsNomeFisico']);

                if ($atual === $novo) {
                    continue;
                }

                // Default heuristic: only catch "same word, missing accents" —
                // never touch a deliberately custom label. --all bypasses this.
                if (! $all && Str::ascii($atual) !== Str::ascii($novo)) {
                    continue;
                }

                $changes[$index] = [
                    'field' => (string) $col['colsNomeFisico'],
                    'from' => $atual,
                    'to' => $novo,
                ];
            }

            if ($changes !== []) {
                $plan[] = ['row' => $row, 'changes' => $changes];
            }
        }

        return $plan;
    }

    /**
     * @param  array<int, array{row: CrudConfig, changes: array<int, array{field: string, from: string, to: string}>}>  $plan
     */
    protected function applyPlan(array $plan, CrudConfigService $configService): int
    {
        $updated = 0;

        // Only the persisting path reaches applyPlan() — --dry-run returns from
        // handle() before this is called. Wrapping the whole batch means a
        // mid-run failure (e.g. schema-validation error on one row) rolls back
        // every relabel already applied in this run, instead of leaving the set
        // half-migrated.
        DB::transaction(function () use ($plan, $configService, &$updated): void {
            foreach ($plan as $entry) {
                /** @var CrudConfig $row */
                $row = $entry['row'];
                $config = $row->config;

                foreach ($entry['changes'] as $index => $change) {
                    $config['cols'][$index]['colsNomeLogico'] = $change['to'];
                }

                $configService->save((string) $row->model, $config, (string) ($row->route ?? ''));
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * @param  array<int, array{row: CrudConfig, changes: array<int, array{field: string, from: string, to: string}>}>  $plan
     */
    protected function renderPlan(array $plan): void
    {
        foreach ($plan as $entry) {
            $row = $entry['row'];
            $route = (string) ($row->route ?? '');
            $label = (string) $row->model.($route !== '' ? " @{$route}" : '');

            $this->line("  <fg=cyan>{$label}</>");
            $this->table(
                ['Field', 'Before', 'After'],
                collect($entry['changes'])->map(fn ($c) => [$c['field'], $c['from'], $c['to']])->toArray()
            );
        }
    }
}
