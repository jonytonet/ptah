<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Ptah\Generators\BindingGenerator;
use Ptah\Generators\ControllerApiGenerator;
use Ptah\Generators\ControllerGenerator;
use Ptah\Generators\Contracts\GeneratorInterface;
use Ptah\Generators\CrudConfigGenerator;
use Ptah\Generators\DtoGenerator;
use Ptah\Generators\GeneratorResult;
use Ptah\Generators\MigrationGenerator;
use Ptah\Generators\ModelGenerator;
use Ptah\Generators\RepositoryGenerator;
use Ptah\Generators\RepositoryInterfaceGenerator;
use Ptah\Generators\RequestGenerator;
use Ptah\Generators\ResourceGenerator;
use Ptah\Generators\RouteGenerator;
use Ptah\Generators\ServiceGenerator;
use Ptah\Generators\ViewGenerator;
use Ptah\Support\EntityContext;
use Ptah\Support\MenuRegistryWriter;
use Ptah\Support\SchemaInspector;

/**
 * Main Ptah command — generates the complete structure for an entity.
 *
 * Usage:
 *   php artisan ptah:forge Product
 *   php artisan ptah:forge Product/ProductStock
 *   php artisan ptah:forge Product/ProductStock --table=product_stocks
 *   php artisan ptah:forge Product --fields="name:string,price:decimal(10,2):nullable,status:enum(active|inactive)"
 *   php artisan ptah:forge Product/ProductStock --fields="product_id:unsignedBigInteger,quantity:decimal(12,3)"
 *   php artisan ptah:forge Product --api
 *   php artisan ptah:forge Product --no-soft-deletes
 *   php artisan ptah:forge Product --force
 *
 * Architecture (SOLID):
 *  - Single Responsibility : each Generator handles one single artefact
 *  - Open/Closed           : new generators can be added without changing this command
 *  - Liskov Substitution   : all generators implement GeneratorInterface
 *  - Interface Segregation : GeneratorInterface is minimal (generate + shouldRun)
 *  - Dependency Inversion  : Filesystem and SchemaInspector are injected via constructor
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'ptah:forge
        {entity                  : Entity name in PascalCase, with optional subfolder (e.g.: Product, Product/ProductStock)}
        {--table=                : Table name in the database (default: plural snake_case of the entity)}
        {--fields=               : Field definitions: "name:string,price:decimal(10,2):nullable" }
        {--db                    : Read fields directly from the database table}
        {--api                   : Also generate the API structure in addition to web (API Controller, API Requests, Swagger and API Routes)}
        {--api-only              : Generate ONLY the API structure, without web views (legacy behaviour of --api)}
        {--no-soft-deletes       : Do not add SoftDeletes to the model}
        {--no-menu               : Do not add entry to MenuRegistry (skip automatic menu generation)}
        {--force                 : Overwrite existing files without confirmation}';

    protected $description = 'Forge — generates the complete structure for an entity (Model, Migration, DTO, Repository, Service, Controller, Requests, Resource, Views, Routes).';

    public function __construct(
        protected Filesystem          $files,
        protected SchemaInspector     $inspector,
        protected MenuRegistryWriter  $menuWriter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // ── Validate entity syntax before touching any file ─────────────────────
        $rawEntity = (string) $this->argument('entity');

        if (! $this->validateEntitySyntax($rawEntity)) {
            return self::FAILURE;
        }

        // ── Subfolder support: accepts Product/ProductStock or Product\ProductStock ──
        $parts     = array_values(array_filter(
            array_map('trim', preg_split('/[\\\\\\/]/', $rawEntity))
        ));
        $entity    = Str::studly((string) array_pop($parts));
        $subFolder = implode('/', array_map(fn(string $p) => Str::studly($p), $parts)); // e.g.: 'Product'

        $entityLower        = Str::snake($entity);
        $entityPlural       = Str::plural($entityLower);
        $entityPluralStudly = Str::studly($entityPlural);
        $table              = $this->option('table') ?: $entityPlural;
        $withViews          = ! $this->option('api-only'); // false only with --api-only
        $withApi            = $this->option('api') || $this->option('api-only');
        $withSoftDeletes    = ! $this->option('no-soft-deletes');
        $force              = (bool) $this->option('force');

        // ── Resolve fields ──────────────────────────────────────────────
        $fields = $this->resolveFields($table);

        // ── Build context (immutable, passed to all generators) ─────────
        $context = new EntityContext(
            entity:             $entity,
            entityLower:        $entityLower,
            entityPlural:       $entityPlural,
            entityPluralStudly: $entityPluralStudly,
            table:              $table,
            rootNamespace:      $this->laravel->getNamespace(),
            timestamp:          date('Y_m_d_His'),
            withViews:          $withViews,
            withSoftDeletes:    $withSoftDeletes,
            force:              $force,
            fields:             $fields,
            subFolder:          $subFolder,
            withApi:            $withApi,
        );

        // ── Header ──────────────────────────────────────────────────────
        $this->newLine();
        $displayName = $subFolder ? "{$subFolder}/{$entity}" : $entity;
        $this->components->info("Ptah Forge — Generating: <fg=yellow>{$displayName}</>");
        $modeLabel = match(true) {
            $withViews && $withApi => 'Web + API',
            $withApi              => 'API only',
            default               => 'Web',
        };
        $this->line("  <fg=gray>Table: {$table} | Fields: " . count($fields) .
            " | Modo: {$modeLabel}</>");
        $this->newLine();

        // ── Run generators ───────────────────────────────────────────────
        $results = $this->runGenerators($context);

        // ── Summary table ────────────────────────────────────────────────
        $this->printSummary($results);

        // ── Post-generation hints ────────────────────────────────────────
        $this->printNextSteps($context);

        $hasError = collect($results)->some(fn(GeneratorResult $r) => $r->isError());
        // ── Auto-register menu entry ─────────────────────────────────────────
        if (! $hasError && ! $this->option('no-menu') && $withViews) {
            $this->registerMenuEntry($entity, $subFolder, $entityLower);
        }
        return $hasError ? self::FAILURE : self::SUCCESS;
    }
    // ── Entity syntax validation ────────────────────────────────────────────────

    /**
     * Validates that the entity argument contains no option tokens glued to it.
     *
     * Common mistake: ptah:forge Clients/Client-fields="name:string"
     * instead of:     ptah:forge Clients/Client --fields="name:string"
     *
     * Each segment (split by / or \) must be a plain PascalCase identifier.
     * Returns false and prints a clear error message when it fails.
     */
    private function validateEntitySyntax(string $rawEntity): bool
    {
        $segments = array_values(array_filter(
            array_map('trim', preg_split('/[\\\\\/]/', $rawEntity))
        ));

        foreach ($segments as $segment) {
            if (! preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $segment)) {
                $this->newLine();
                $this->components->error(
                    "Invalid 'entity' argument: <fg=yellow>{$rawEntity}</>\n" .
                    "  Each segment must be a plain PascalCase name (letters and digits only).\n" .
                    "  It looks like an option was written without a space.\n" .
                    "  Example: <fg=green>php artisan ptah:forge Clients/Client --fields=\"...\"</>"
                );
                return false;
            }
        }

        return true;
    }
    // ── Orchestration ──────────────────────────────────────────────────────

    /**
     * Instantiates and runs all generators in the correct order.
     *
     * @return GeneratorResult[]
     */
    private function runGenerators(EntityContext $context): array
    {
        /** @var GeneratorInterface[] $generators */
        $generators = $this->buildGenerators();

        $results = [];

        foreach ($generators as $generator) {
            if (! $generator->shouldRun($context)) {
                continue;
            }

            // Special generators that produce multiple artefacts
            if ($generator instanceof RequestGenerator) {
                if ($context->withViews) {
                    // Web: Store + Update
                    $results[] = $generator->generateStore($context);
                    $results[] = $generator->generateUpdate($context);
                }
                if ($context->withApi) {
                    // API: Create + Update
                    $results[] = $generator->generateCreateApi($context);
                    $results[] = $generator->generateUpdateApi($context);
                }
                continue;
            }

            if ($generator instanceof RouteGenerator) {
                if ($context->withViews) {
                    $results[] = $generator->generateWebRoute($context);
                }
                if ($context->withApi) {
                    $results[] = $generator->generateApiRoute($context);
                }
                continue;
            }

            if ($generator instanceof ViewGenerator) {
                $results[] = $generator->generateView($context, 'index');
                continue;
            }

            $results[] = $generator->generate($context);
        }

        return $results;
    }

    /**
     * Returns all available generators.
     * To add a new generator, simply include it here (OCP).
     *
     * @return GeneratorInterface[]
     */
    private function buildGenerators(): array
    {
        return [
            new ModelGenerator($this->files),
            new MigrationGenerator($this->files),
            new BindingGenerator($this->files),
            new DtoGenerator($this->files),
            new RepositoryInterfaceGenerator($this->files),
            new RepositoryGenerator($this->files),
            new ServiceGenerator($this->files),
            new ControllerGenerator($this->files),
            new ControllerApiGenerator($this->files),
            new RequestGenerator($this->files),
            new ResourceGenerator($this->files),
            new CrudConfigGenerator($this->files),
            new ViewGenerator($this->files),
            new RouteGenerator($this->files),
        ];
    }

    // ── Field resolution ───────────────────────────────────────────────────

    /**
     * Resolves fields from the provided options.
     *
     * Priority: --db > --fields > none (no fields defined)
     *
     * @return \Ptah\Support\FieldDefinition[]
     */
    private function resolveFields(string $table): array
    {
        if ($this->option('db')) {
            $fields = $this->inspector->fromDatabase($table);

            if (empty($fields)) {
                $this->components->warn("Table [{$table}] not found or has no columns. No fields will be pre-filled.");
            } else {
                $this->components->info(count($fields) . " field(s) read from table [{$table}].");
            }

            return $fields;
        }

        if ($fieldsOption = $this->option('fields')) {
            return $this->inspector->fromString($fieldsOption);
        }

        return [];
    }

    // ── Output ─────────────────────────────────────────────────────────────

    /**
     * Displays the results summary table.
     *
     * @param GeneratorResult[] $results
     */
    private function printSummary(array $results): void
    {
        $rows = array_map(fn(GeneratorResult $r) => [
            $r->label,
            $r->formattedStatus(),
        ], $results);

        $this->table(['Artifact', 'Status'], $rows);
        $this->newLine();

        $done    = count(array_filter($results, fn($r) => $r->isDone()));
        $skipped = count(array_filter($results, fn($r) => $r->isSkipped()));
        $errors  = count(array_filter($results, fn($r) => $r->isError()));

        $this->line(
            "  <fg=green>{$done} created</> · " .
            "<fg=yellow>{$skipped} skipped</> · " .
            "<fg=red>{$errors} error(s)</>"
        );

        if ($errors > 0) {
            $this->newLine();
            foreach (array_filter($results, fn($r) => $r->isError()) as $r) {
                $this->components->error("{$r->label}: {$r->message}");
            }
        }
    }

    /**
     * Displays suggested next steps after generation.
     */
    private function printNextSteps(EntityContext $context): void
    {
        $ns = rtrim($context->rootNamespace, '\\');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Next steps:</> ');
        $this->newLine();

        $this->line("  <fg=green>✔ Binding automatically registered in AppServiceProvider.</>");
        $this->newLine();

        $this->line("  <fg=yellow>1. Run the migration:</>");
        $this->line("     <fg=gray>php artisan migrate</>");
        $this->newLine();

        if (! empty($context->fields)) {
            $this->line("  <fg=yellow>2. Review the validation rules in the generated Requests.</>");
            $this->newLine();
        }

        if ($context->withViews) {
            $this->line("  <fg=yellow>Access:</> <fg=gray>/{$context->entityLower}</>");
            $this->newLine();
            $this->line("  <fg=blue>→ The screen uses Livewire BaseCrud. Configuration saved in <fg=gray>crud_configs</> and can be adjusted directly in the database.</>");
        } else {
            $this->line("  <fg=yellow>API Endpoint:</> <fg=gray>/api/{$context->entityPlural}</>");
        }

        $this->newLine();
    }

    /**
     * Registers the entity in MenuRegistry.php for automatic menu generation.
     * Called after successful scaffolding if --no-menu flag is not present.
     *
     * @param string $entity Entity name (ex: VaccinationType)
     * @param string $subFolder Module path (ex: Health)
     * @param string $entityLower URL slug (ex: vaccination_type)
     * @return void
     */
    private function registerMenuEntry(string $entity, string $subFolder, string $entityLower): void
    {
        $registryPath = database_path('seeders/MenuRegistry.php');

        if (! file_exists($registryPath)) {
            $this->components->warn('MenuRegistry.php not found — run ptah:install to create it.');
            return;
        }

        $url = '/' . $entityLower;

        try {
            if (empty($subFolder)) {
                // No module prefix → add as flat root link
                $added     = $this->menuWriter->addFlatEntry(
                    entity:       $entity,
                    url:          $url,
                    registryPath: $registryPath
                );
                $linkLabel = \Ptah\Support\MenuIconMapper::translateEntity($entity);

                if ($added) {
                    $this->newLine();
                    $this->components->info("Menu entry added (flat): <fg=cyan>{$linkLabel}</> (<fg=gray>{$url}</>)");
                    $this->line("  <fg=blue>→ Sync menu: <fg=gray>php artisan ptah:menu-sync --fresh</>");
                }
            } else {
                // Has module prefix → add under group
                $added = $this->menuWriter->addEntry(
                    module:       $subFolder,
                    entity:       $entity,
                    url:          $url,
                    registryPath: $registryPath
                );

                if ($added) {
                    $groupLabel = \Ptah\Support\MenuIconMapper::getGroupLabel($subFolder);
                    $linkLabel  = \Ptah\Support\MenuIconMapper::translateEntity($entity);

                    $this->newLine();
                    $this->components->info("Menu entry added: <fg=yellow>{$groupLabel}</> → <fg=cyan>{$linkLabel}</> (<fg=gray>{$url}</>)");
                    $this->line("  <fg=blue>→ Sync menu: <fg=gray>php artisan ptah:menu-sync --fresh</>");
                }
            }
        } catch (\Exception $e) {
            $this->components->warn("Could not register menu entry: {$e->getMessage()}");
        }
    }
}
