<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Ptah\Support\BlueprintPlan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * `ptah:blueprint spec.json` — a whole module from one spec.
 *
 * What would otherwise be a dozen commands typed in the right order — forge
 * each entity parents-first, migrate, configure the screens, sync the menu,
 * sync the permissions and grant them, seed — planned from one JSON file and
 * run in one go (see BlueprintPlan for the spec format).
 *
 * Each step's own output is captured, not echoed: the run prints one line per
 * step, and only a failing step's output — the part worth reading. The first
 * failure stops the run and lists what did not run, so the module is never
 * silently half-built.
 *
 * Uso:
 *   php artisan ptah:blueprint catalog.json --dry-run
 *   php artisan ptah:blueprint catalog.json
 *   php artisan ptah:blueprint catalog.json --no-migrate
 */
class BlueprintCommand extends Command
{
    protected $signature = 'ptah:blueprint
        {spec : Path to the JSON spec}
        {--dry-run : Print the plan, run nothing}
        {--no-migrate : Do not run migrate (and so do not seed)}
        {--force : Allow running in production}';

    protected $description = 'Build a whole module from a JSON spec: forge (FK order), migrate, config, menu, permissions, seed';

    public function handle(): int
    {
        $path = (string) $this->argument('spec');

        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->components->error("Spec not found: {$path}");

            return self::FAILURE;
        }

        $spec = json_decode((string) file_get_contents($path), true);

        if (! is_array($spec)) {
            $this->components->error('The spec is not valid JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $migrate = ! $this->option('no-migrate');

        if (! $migrate) {
            unset($spec['seed']);
        }

        try {
            $steps = BlueprintPlan::steps($spec, $migrate);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('Plan ('.count($steps).' steps, nothing run):');
            foreach ($steps as $i => $step) {
                $this->line('  '.($i + 1).'. php artisan '.self::commandLine($step['command'], $step['args']));
            }

            return self::SUCCESS;
        }

        if (app()->environment('production') && ! $this->option('force')) {
            $this->components->error('ptah:blueprint writes files, migrates and seeds — refused in production. Pass --force if that is intended.');

            return self::FAILURE;
        }

        foreach ($steps as $i => $step) {
            if ($step['command'] === 'ptah:menu-sync' && ! is_file(database_path('seeders/MenuRegistry.php'))) {
                $this->line("  <fg=gray>·</> {$step['label']}  <fg=gray>skipped — no MenuRegistry.php (entities without a module folder add no menu entry)</>");

                continue;
            }

            $args = $step['args'];
            if (in_array($step['command'], ['migrate', 'db:seed'], true) && app()->environment('production')) {
                $args['--force'] = true;
            }

            $buffer = new BufferedOutput;

            try {
                $code = Artisan::call($step['command'], $args, $buffer);
                $output = $buffer->fetch();
            } catch (\Throwable $e) {
                $code = 1;
                $output = $buffer->fetch().get_class($e).': '.$e->getMessage();
            }

            if ($code === 0) {
                $this->line("  <fg=green>✔</> {$step['label']}");

                continue;
            }

            $this->line("  <fg=red>✖</> {$step['label']}  (exit {$code})");
            $this->line(self::tail($output));
            $this->newLine();

            $rest = array_slice($steps, $i + 1);
            if ($rest !== []) {
                $this->line('Not run: '.implode(', ', array_column($rest, 'label')));
            }
            $this->line('Fix the cause and re-run: steps already done are skipped (ptah:forge never overwrites without --force).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line(count($steps).' steps done. Next: php artisan ptah:check');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private static function commandLine(string $command, array $args): string
    {
        $parts = [$command];

        foreach ($args as $key => $value) {
            foreach ((array) $value as $v) {
                if (! str_starts_with((string) $key, '--')) {
                    $parts[] = self::quote((string) $v);
                } elseif ($v === true) {
                    $parts[] = $key;
                } else {
                    $parts[] = $key.'="'.str_replace('"', '\\"', (string) $v).'"';
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * The same on every OS, so the dry run can be pasted as it stands — and
     * reads like the docs (`--fields="…"`); escapeshellarg() quotes per platform.
     */
    private static function quote(string $value): string
    {
        return preg_match('#^[\w/.:,=()|-]+$#', $value) === 1 ? $value : '"'.str_replace('"', '\\"', $value).'"';
    }

    /**
     * The last lines of a failed step — where the reason is.
     */
    private static function tail(string $output, int $lines = 15): string
    {
        $all = array_values(array_filter(array_map('rtrim', explode("\n", trim($output))), fn ($l) => $l !== ''));

        return '    '.implode("\n    ", array_slice($all, -$lines));
    }
}
