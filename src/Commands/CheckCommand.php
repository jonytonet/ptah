<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\CrudScreenInspector;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * `ptah:check` — every configured BaseCrud screen, smoke-tested in one command.
 *
 * Instead of opening each screen in a browser (or, for an agent, reading the
 * model, the migration and the config of each one): the screen is RENDERED
 * through Livewire exactly as a page would mount it — the listing query, the
 * eager loads, the Blade — and its config is compared with the model and the
 * table, which catches what renders without error but does not work (a column
 * that is not in the table, a form field outside `$fillable`, a NOT NULL
 * column the form never fills).
 *
 * Read-only by default. `--write` also creates, updates and deletes one record
 * per screen inside a transaction that is always rolled back; it is refused in
 * production without `--force`.
 *
 * Uso:
 *   php artisan ptah:check
 *   php artisan ptah:check Product
 *   php artisan ptah:check --as=1 --guard=web
 *   php artisan ptah:check --write
 *   php artisan ptah:check --json
 */
class CheckCommand extends Command
{
    protected $signature = 'ptah:check
        {model? : Only screens whose model matches (class name or short name)}
        {--as= : Render as this user id (screens behind permissions return 403 anonymously)}
        {--guard= : Guard for --as (default: the default guard)}
        {--write : Also create/update/delete one record per screen, rolled back}
        {--force : Allow --write in production}
        {--json : Machine-readable output}';

    protected $description = 'Smoke-test every configured BaseCrud screen: render it and check its config against the model and table';

    public function handle(): int
    {
        if ($this->option('write') && app()->environment('production') && ! $this->option('force')) {
            $this->components->error('--write is refused in production (it writes, then rolls back). Pass --force if that is intended.');

            return self::FAILURE;
        }

        if ($this->option('as') !== null) {
            $guard = $this->option('guard') ?: config('auth.defaults.guard');

            if (! Auth::guard($guard)->onceUsingId($this->option('as'))) {
                $this->components->error("No user with id {$this->option('as')} on guard \"{$guard}\".");

                return self::FAILURE;
            }

            Auth::shouldUse($guard);
        }

        $filter = (string) ($this->argument('model') ?? '');
        $screens = CrudConfig::query()->orderBy('model')->orderBy('route')->get()
            ->filter(fn (CrudConfig $c) => $filter === '' || self::matches($c->model, $filter))
            ->values();

        if ($screens->isEmpty()) {
            $this->components->info($filter === '' ? 'No BaseCrud screen is configured (crud_configs is empty).' : "No configured screen matches \"{$filter}\".");

            return self::SUCCESS;
        }

        $results = $screens->map(fn (CrudConfig $c) => $this->checkScreen($c))->all();

        return $this->report($results);
    }

    /**
     * @return array{model: string, route: string, status: string, findings: list<array{level: string, message: string}>}
     */
    private function checkScreen(CrudConfig $row): array
    {
        $config = $row->config;
        $route = (string) ($row->route ?? '');
        $findings = [];

        $class = CrudScreenInspector::resolveModelClass((string) ($config['crud'] ?? $row->model));

        if ($class === null) {
            $findings[] = ['level' => 'error', 'message' => 'model "'.($config['crud'] ?? $row->model).'" does not resolve to an Eloquent class'];
        } else {
            $model = new $class;
            $findings = CrudScreenInspector::inspect($model, $config);

            $tableMissing = array_filter($findings, fn ($f) => str_starts_with($f['message'], 'table '));

            if ($tableMissing === []) {
                if ($render = $this->render($row->model, $route)) {
                    $findings[] = $render;
                }

                if ($this->option('write')) {
                    $findings = array_merge($findings, CrudScreenInspector::roundTrip($model, $config));
                }
            }
        }

        $levels = array_column($findings, 'level');

        return [
            'model' => (string) $row->model,
            'route' => $route,
            'status' => in_array('error', $levels, true) ? 'error' : (in_array('warning', $levels, true) ? 'warning' : 'ok'),
            'findings' => $findings,
        ];
    }

    /**
     * Mounts the screen as a page would, under the screen's own path so a
     * route-specific config is the one loaded.
     *
     * @return array{level: string, message: string}|null
     */
    private function render(string $model, string $route): ?array
    {
        $previous = app('request');
        app()->instance('request', Request::create('/'.ltrim($route, '/')));

        try {
            Livewire::mount(BaseCrud::class, ['model' => $model]);

            return null;
        } catch (HttpExceptionInterface $e) {
            $hint = $e->getStatusCode() === 403 && ! Auth::check() ? ' — run with --as=<user id>' : '';

            return ['level' => $e->getStatusCode() === 403 ? 'warning' : 'error', 'message' => "render: HTTP {$e->getStatusCode()}{$hint}"];
        } catch (\Throwable $e) {
            return ['level' => 'error', 'message' => 'render: '.class_basename($e).': '.self::oneLine($e->getMessage()).' at '.self::where($e)];
        } finally {
            app()->instance('request', $previous);
        }
    }

    /**
     * @param  list<array{model: string, route: string, status: string, findings: list<array{level: string, message: string}>}>  $results
     */
    private function report(array $results): int
    {
        $counts = array_count_values(array_column($results, 'status')) + ['ok' => 0, 'warning' => 0, 'error' => 0];

        if ($this->option('json')) {
            $this->line((string) json_encode(['screens' => $results, 'summary' => $counts], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $counts['error'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $mark = ['ok' => '<fg=green>✔</>', 'warning' => '<fg=yellow>⚠</>', 'error' => '<fg=red>✖</>'];

        foreach ($results as $r) {
            $this->line($mark[$r['status']].' '.$r['model'].($r['route'] !== '' ? "  [{$r['route']}]" : ''));

            foreach ($r['findings'] as $f) {
                $this->line('    '.($f['level'] === 'error' ? 'error' : 'warn ').'  '.$f['message']);
            }
        }

        $this->newLine();
        $this->line(count($results)." screens: {$counts['ok']} ok, {$counts['warning']} with warnings, {$counts['error']} failing"
            .($this->option('write') ? ' (write round-trip rolled back)' : ''));

        return $counts['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private static function matches(string $model, string $filter): bool
    {
        $normalized = str_replace('/', '\\', $model);

        return strcasecmp($normalized, str_replace('/', '\\', $filter)) === 0
            || strcasecmp(class_basename($normalized), $filter) === 0;
    }

    /**
     * The first frame in the application or in ptah — where to look first.
     */
    private static function where(\Throwable $e): string
    {
        $base = str_replace('\\', '/', base_path()).'/';

        foreach (array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace()) as $frame) {
            $file = str_replace('\\', '/', (string) ($frame['file'] ?? ''));

            $ours = ! str_contains($file, '/vendor/') || str_contains($file, '/jonytonet/ptah/src/');

            if ($file !== '' && $ours && ! str_contains($file, '/storage/framework/')) {
                return str_replace($base, '', $file).':'.($frame['line'] ?? '?');
            }
        }

        return str_replace($base, '', str_replace('\\', '/', $e->getFile())).':'.$e->getLine();
    }

    private static function oneLine(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        return mb_strlen($message) > 240 ? mb_substr($message, 0, 240).'…' : $message;
    }
}
