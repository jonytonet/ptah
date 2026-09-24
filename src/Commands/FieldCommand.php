<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ptah\Generators\CrudConfigGenerator;
use Ptah\Models\CrudConfig;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Support\EntityFieldEditor;
use Ptah\Support\FieldDefinition;
use Ptah\Support\SchemaInspector;

/**
 * `ptah:field` — add a field to an entity `ptah:forge` generated, everywhere at once.
 *
 *   php artisan ptah:field Catalog/Product add discount:decimal(5,2) nullable
 *   php artisan ptah:field Product add status:enum(draft|published):default(draft)
 *   php artisan ptah:field Product add notes:text nullable --dry-run
 *
 * One command for the five edits a new column takes: the migration, the
 * model's `$fillable` and `$casts`, the Store/Update rules (web and API), the
 * DTO, and a column in every crud_config of the entity. The field uses the
 * same syntax as `--fields` of ptah:forge; tokens after the first are joined
 * as modifiers, so `discount:decimal nullable` works.
 *
 * Only `add`. Renaming or dropping a column destroys data; that stays a
 * decision a developer makes and writes by hand.
 *
 * Every file it could not edit — missing, or hand-edited past recognition —
 * is listed with ⚠: the command never guesses where a line goes.
 */
class FieldCommand extends Command
{
    protected $signature = 'ptah:field
        {entity : Entity as given to ptah:forge (Product or Catalog/Product)}
        {action : add}
        {definition* : name:type[:modifiers] — same syntax as ptah:forge --fields}
        {--no-migration : Do not create the migration}
        {--no-config : Do not add the column to the crud_configs}
        {--dry-run : Show what would change, write nothing}';

    protected $description = 'Add a field to a generated entity: migration, model fillable/casts, request rules, DTO and crud config';

    /**
     * @var list<array{status: string, target: string, note: string}>
     */
    private array $report = [];

    public function handle(Filesystem $files, SchemaInspector $inspector): int
    {
        if ($this->argument('action') !== 'add') {
            $this->components->error('Only "add" is supported. Renaming or dropping a column destroys data — write that migration by hand.');

            return self::FAILURE;
        }

        $definition = implode(':', (array) $this->argument('definition'));
        $parsed = $inspector->fromString($definition);

        if (count($parsed) !== 1 || preg_match('/^[a-z_][a-z0-9_]*$/', $parsed[0]->name) !== 1) {
            $this->components->error("Invalid field \"{$definition}\" — expected one field as name:type[:modifiers], snake_case name.");

            return self::FAILURE;
        }

        $field = $parsed[0];
        [$entity, $subFolder] = self::splitEntity((string) $this->argument('entity'));
        $key = $subFolder !== '' ? "{$subFolder}/{$entity}" : $entity;
        $sub = $subFolder !== '' ? '/'.$subFolder : '';
        $dry = (bool) $this->option('dry-run');

        $modelPath = config('ptah.paths.models')."{$sub}/{$entity}.php";

        if (! $files->exists($modelPath)) {
            $this->components->error("Model not found: {$this->rel($modelPath)} — is \"{$key}\" the name given to ptah:forge?");

            return self::FAILURE;
        }

        $modelCode = $files->get($modelPath);
        $table = preg_match("/protected\s+\\\$table\s*=\s*'([^']+)'/", $modelCode, $t) === 1 ? $t[1] : Str::snake(Str::pluralStudly($entity));

        // ── migration ─────────────────────────────────────────────────────
        if (! $this->option('no-migration')) {
            $this->migration($files, $field, $table, $dry);
        }

        // ── model ─────────────────────────────────────────────────────────
        $code = EntityFieldEditor::addToFillable($modelCode, $field->name);
        if ($code !== null && $field->castType() !== 'string') {
            $code = EntityFieldEditor::addToCasts($code, $field->name, $field->castType());
        }
        $this->apply($files, $modelPath, $modelCode, $code, 'fillable'.($field->castType() !== 'string' ? ' + casts' : ''), $dry);

        // ── requests ──────────────────────────────────────────────────────
        $requests = config('ptah.paths.requests');
        foreach ([
            "{$requests}{$sub}/Store{$entity}Request.php" => $field->validationRuleStore(),
            "{$requests}{$sub}/Update{$entity}Request.php" => $field->validationRuleUpdate(),
            "{$requests}/API{$sub}/Create{$entity}ApiRequest.php" => $field->validationRuleStore(),
            "{$requests}/API{$sub}/Update{$entity}ApiRequest.php" => $field->validationRuleUpdate(),
        ] as $path => $rule) {
            $rule = str_replace('TABELA_AQUI', $table, $rule);

            if (! $files->exists($path)) {
                // A API so existe com --api; ausencia de request web e digna de nota.
                if (! str_contains($path, '/API/')) {
                    $this->note('warn', $this->rel($path), 'not found — add the rule by hand: '.$rule);
                }

                continue;
            }

            $before = $files->get($path);
            $this->apply($files, $path, $before, EntityFieldEditor::addRule($before, $field->name, $rule), 'rules', $dry);
        }

        // ── DTO ───────────────────────────────────────────────────────────
        $dtoPath = config('ptah.paths.dtos')."{$sub}/{$entity}DTO.php";
        if ($files->exists($dtoPath)) {
            $before = $files->get($dtoPath);
            $this->apply($files, $dtoPath, $before, EntityFieldEditor::addToDto($before, $field->name, $field->phpType(), $field->nullable), 'property + fromArray', $dry);
        } else {
            $this->note('warn', $this->rel($dtoPath), 'not found — if the entity has a DTO elsewhere, add the property by hand');
        }

        // ── crud configs ──────────────────────────────────────────────────
        if (! $this->option('no-config')) {
            $this->crudConfigs($key, $field, $dry);
        }

        return $this->printReport($dry);
    }

    private function migration(Filesystem $files, FieldDefinition $field, string $table, bool $dry): void
    {
        $name = "add_{$field->name}_to_{$table}_table";

        if (glob(database_path("migrations/*_{$name}.php"))) {
            $this->note('skip', "migration {$name}", 'already exists');

            return;
        }

        try {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $field->name)) {
                $this->note('skip', "migration {$name}", "column {$table}.{$field->name} already exists in the database");

                return;
            }
        } catch (\Throwable) {
            // Sem banco acessivel: gera a migration mesmo assim.
        }

        $path = database_path('migrations/'.date('Y_m_d_His')."_{$name}.php");
        $line = $field->migrationLine('            ');

        $code = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
{$line}
        });
    }

    /**
     * ATENCAO: o rollback APAGA a coluna e os dados dela. Para preservar os
     * dados, esvazie este metodo antes de um rollback em producao.
     */
    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            \$table->dropColumn('{$field->name}');
        });
    }
};

PHP;

        if (! $dry) {
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, $code);
        }

        $this->note('done', $this->rel($path), 'down() drops the column (data lost on rollback) — noted in the file');

        if (! $field->nullable && ! $field->hasDefault) {
            $this->note('warn', "{$table}.{$field->name}", 'NOT NULL without a default: on a table with rows, some databases refuse it — consider nullable or :default(x)');
        }
    }

    private function crudConfigs(string $key, FieldDefinition $field, bool $dry): void
    {
        $rows = CrudConfig::query()->where('model', $key)->get();

        if ($rows->isEmpty()) {
            $this->note('warn', "crud_config {$key}", 'none found — add the column with ptah:config');

            return;
        }

        $col = app(CrudConfigGenerator::class)->buildColFromField($field);
        $service = app(CrudConfigService::class);

        foreach ($rows as $row) {
            $config = $row->config;
            $label = "crud_config {$key}".($row->route ? " [{$row->route}]" : '');

            if (in_array($field->name, array_column($config['cols'] ?? [], 'colsNomeFisico'), true)) {
                $this->note('skip', $label, 'column already configured');

                continue;
            }

            $config['cols'][] = $col;

            if (! $dry) {
                $service->save($key, $config, (string) ($row->route ?? ''));
            }

            $this->note('done', $label, "column {$col['colsTipo']} \"{$col['colsNomeLogico']}\"");
        }
    }

    private function apply(Filesystem $files, string $path, string $before, ?string $after, string $what, bool $dry): void
    {
        if ($after === null) {
            $this->note('warn', $this->rel($path), "{$what}: could not find where the generator puts it — edit by hand");

            return;
        }

        if ($after === $before) {
            $this->note('skip', $this->rel($path), "{$what}: already there");

            return;
        }

        if (! $dry) {
            $files->put($path, $after);
        }

        $this->note('done', $this->rel($path), $what);
    }

    private function note(string $status, string $target, string $note): void
    {
        $this->report[] = ['status' => $status, 'target' => $target, 'note' => $note];
    }

    private function printReport(bool $dry): int
    {
        $mark = ['done' => '<fg=green>✔</>', 'skip' => '<fg=gray>·</>', 'warn' => '<fg=yellow>⚠</>'];

        foreach ($this->report as $r) {
            $this->line("  {$mark[$r['status']]} {$r['target']}  <fg=gray>{$r['note']}</>");
        }

        $pending = count(array_filter($this->report, fn ($r) => $r['status'] === 'warn'));

        $this->newLine();
        $this->line(($dry ? 'Dry run — nothing written. ' : '').($pending > 0 ? "{$pending} item(s) need a manual edit (⚠)." : 'Next: php artisan migrate'));

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitEntity(string $raw): array
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/[\\\\\/]/', $raw) ?: [])));
        $entity = Str::studly((string) array_pop($parts));

        return [$entity, implode('/', array_map(fn (string $p) => Str::studly($p), $parts))];
    }

    private function rel(string $path): string
    {
        $base = str_replace('\\', '/', base_path()).'/';

        return str_replace($base, '', str_replace('\\', '/', $path));
    }
}
