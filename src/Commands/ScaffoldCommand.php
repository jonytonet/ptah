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
use Ptah\Support\SchemaInspector;

/**
 * Comando principal do Ptah — gera a estrutura completa de uma entidade.
 *
 * Uso:
 *   php artisan ptah:forge Product
 *   php artisan ptah:forge Product --table=products
 *   php artisan ptah:forge Product --fields="name:string,price:decimal(10,2):nullable,status:enum(active|inactive)"
 *   php artisan ptah:forge Product --api
 *   php artisan ptah:forge Product --no-soft-deletes
 *   php artisan ptah:forge Product --force
 *
 * Arquitetura (SOLID):
 *  - Single Responsibility : cada Generator cuida de um único artefato
 *  - Open/Closed           : novos generators podem ser adicionados sem alterar este comando
 *  - Liskov Substitution   : todos os generators implementam GeneratorInterface
 *  - Interface Segregation : GeneratorInterface é mínima (generate + shouldRun)
 *  - Dependency Inversion  : injetamos Filesystem e SchemaInspector via construtor
 */
class ScaffoldCommand extends Command
{
    protected $signature = 'ptah:forge
        {entity                  : Nome da entidade em PascalCase (ex: ProductCategory)}
        {--table=                : Nome da tabela no banco (padrão: plural snake_case da entidade)}
        {--fields=               : Definição dos campos: "name:string,price:decimal(10,2):nullable" }
        {--db                    : Lê os campos diretamente da tabela no banco de dados}
        {--api                   : Gera somente a estrutura de API (sem views)}
        {--no-soft-deletes       : Não adiciona SoftDeletes ao model}
        {--force                 : Sobrescreve arquivos existentes sem confirmação}';

    protected $description = 'Forge — gera a estrutura completa de uma entidade (Model, Migration, DTO, Repository, Service, Controller, Requests, Resource, Views, Routes).';

    public function __construct(
        protected Filesystem     $files,
        protected SchemaInspector $inspector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $entity             = Str::studly($this->argument('entity'));
        $entityLower        = Str::snake($entity);
        $entityPlural       = Str::plural($entityLower);
        $entityPluralStudly = Str::studly($entityPlural);
        $table              = $this->option('table') ?: $entityPlural;
        $withViews          = ! $this->option('api');
        $withSoftDeletes    = ! $this->option('no-soft-deletes');
        $force              = (bool) $this->option('force');

        // ── Resolve fields ──────────────────────────────────────────────
        $fields = $this->resolveFields($table);

        // ── Build context (imutável, passado a todos os generators) ─────
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
        );

        // ── Header ──────────────────────────────────────────────────────
        $this->newLine();
        $this->components->info("Ptah Forge — Gerando: <fg=yellow>{$entity}</>");
        $this->line("  <fg=gray>Tabela: {$table} | Campos: " . count($fields) .
            " | Modo: " . ($withViews ? 'Web' : 'API') . "</>");
        $this->newLine();

        // ── Run generators ───────────────────────────────────────────────
        $results = $this->runGenerators($context);

        // ── Summary table ────────────────────────────────────────────────
        $this->printSummary($results);

        // ── Post-generation hints ────────────────────────────────────────
        $this->printNextSteps($context);

        $hasError = collect($results)->some(fn(GeneratorResult $r) => $r->isError());

        return $hasError ? self::FAILURE : self::SUCCESS;
    }

    // ── Orchestration ──────────────────────────────────────────────────────

    /**
     * Instancia e executa todos os generators na ordem correcta.
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

            // Generators especiais que produzem múltiplos artefatos
            if ($generator instanceof RequestGenerator) {
                $results[] = $generator->generateStore($context);
                $results[] = $generator->generateUpdate($context);
                continue;
            }

            if ($generator instanceof ViewGenerator) {
                foreach (['index', 'create', 'edit', 'show'] as $view) {
                    $results[] = $generator->generateView($context, $view);
                }
                continue;
            }

            $results[] = $generator->generate($context);
        }

        return $results;
    }

    /**
     * Retorna todos os generators disponíveis.
     * Para adicionar um novo gerador, basta incluir aqui (OCP).
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
     * Resolve os campos a partir das opções fornecidas.
     *
     * Prioridade: --db > --fields > nenhum (sem campos definidos)
     *
     * @return \Ptah\Support\FieldDefinition[]
     */
    private function resolveFields(string $table): array
    {
        if ($this->option('db')) {
            $fields = $this->inspector->fromDatabase($table);

            if (empty($fields)) {
                $this->components->warn("Tabela [{$table}] não encontrada ou sem colunas. Nenhum campo será pré-preenchido.");
            } else {
                $this->components->info(count($fields) . " campo(s) lido(s) da tabela [{$table}].");
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
     * Exibe a tabela de resumo dos resultados.
     *
     * @param GeneratorResult[] $results
     */
    private function printSummary(array $results): void
    {
        $rows = array_map(fn(GeneratorResult $r) => [
            $r->label,
            $r->formattedStatus(),
        ], $results);

        $this->table(['Artefato', 'Status'], $rows);
        $this->newLine();

        $done    = count(array_filter($results, fn($r) => $r->isDone()));
        $skipped = count(array_filter($results, fn($r) => $r->isSkipped()));
        $errors  = count(array_filter($results, fn($r) => $r->isError()));

        $this->line(
            "  <fg=green>{$done} criado(s)</> · " .
            "<fg=yellow>{$skipped} ignorado(s)</> · " .
            "<fg=red>{$errors} erro(s)</>"
        );

        if ($errors > 0) {
            $this->newLine();
            foreach (array_filter($results, fn($r) => $r->isError()) as $r) {
                $this->components->error("{$r->label}: {$r->message}");
            }
        }
    }

    /**
     * Exibe as próximas etapas sugeridas após a geração.
     */
    private function printNextSteps(EntityContext $context): void
    {
        $ns = rtrim($context->rootNamespace, '\\');

        $this->newLine();
        $this->line('  <fg=blue;options=bold>Próximos passos:</> ');
        $this->newLine();

        $this->line("  <fg=green>✔ Binding registrado automaticamente no AppServiceProvider.</>");
        $this->newLine();

        $this->line("  <fg=yellow>1. Execute a migration:</>");
        $this->line("     <fg=gray>php artisan migrate</>");
        $this->newLine();

        if (! empty($context->fields)) {
            $this->line("  <fg=yellow>2. Revise as regras de validação nos Requests gerados.</>");
            $this->newLine();
        }

        if ($context->withViews) {
            $this->line("  <fg=yellow>Acesse:</> <fg=gray>/{$context->entityLower}</>");
            $this->newLine();
            $this->line("  <fg=blue>→ A tela usa o Livewire BaseCrud. A configuração foi salva em <fg=gray>crud_configs</> e pode ser ajustada diretamente no banco.</>");
        } else {
            $this->line("  <fg=yellow>Endpoint API:</> <fg=gray>/api/{$context->entityPlural}</>");
        }

        $this->newLine();
    }
}
