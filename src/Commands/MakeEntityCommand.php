<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Comando para gerar a estrutura completa de uma entidade (CRUD).
 *
 * Uso: php artisan ptah:make {Entity}
 */
class MakeEntityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ptah:make
                            {entity : Nome da entidade (ex: Product)}
                            {--force : Sobrescrever arquivos existentes}';

    /**
     * @var string
     */
    protected $description = 'Gera a estrutura completa de uma entidade: Model, Migration, DTO, Repository, Service, Controller, Requests, Resource, Views e Rotas.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Executa o comando.
     */
    public function handle(): int
    {
        $entity = Str::studly($this->argument('entity'));
        $entityLower = Str::snake($entity);
        $entityPlural = Str::plural($entityLower);
        $entityPluralStudly = Str::studly($entityPlural);

        $this->components->info("Gerando estrutura para a entidade: {$entity}");

        $generators = [
            fn () => $this->generateModel($entity),
            fn () => $this->generateMigration($entity, $entityPlural),
            fn () => $this->generateDTO($entity),
            fn () => $this->generateRepositoryInterface($entity),
            fn () => $this->generateRepository($entity),
            fn () => $this->generateService($entity),
            fn () => $this->generateController($entity, $entityLower, $entityPlural),
            fn () => $this->generateRequest($entity, 'Store'),
            fn () => $this->generateRequest($entity, 'Update'),
            fn () => $this->generateResource($entity),
            fn () => $this->generateViews($entity, $entityLower, $entityPlural),
            fn () => $this->appendRoutes($entity, $entityLower),
        ];

        foreach ($generators as $generator) {
            $generator();
        }

        $this->components->info('Estrutura gerada com sucesso!');
        $this->newLine();
        $this->components->bulletList([
            "Lembre-se de registrar o binding do repositório no AppServiceProvider:",
            "  \$this->app->bind({$entity}RepositoryInterface::class, {$entity}Repository::class);",
        ]);

        return self::SUCCESS;
    }

    /**
     * Gera o arquivo do Model.
     */
    protected function generateModel(string $entity): void
    {
        $path = config('ptah.paths.models') . "/{$entity}.php";

        $this->generateFile(
            stub: 'model',
            path: $path,
            replacements: [
                'namespace' => $this->getAppNamespace() . 'Models',
                'entity'    => $entity,
            ],
            label: "Model [{$entity}]"
        );
    }

    /**
     * Gera o arquivo de Migration.
     */
    protected function generateMigration(string $entity, string $table): void
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$table}_table.php";
        $path = database_path("migrations/{$filename}");

        $this->generateFile(
            stub: 'migration',
            path: $path,
            replacements: [
                'table' => $table,
            ],
            label: "Migration [create_{$table}_table]"
        );
    }

    /**
     * Gera o arquivo do DTO.
     */
    protected function generateDTO(string $entity): void
    {
        $path = config('ptah.paths.dtos') . "/{$entity}DTO.php";

        $this->generateFile(
            stub: 'dto',
            path: $path,
            replacements: [
                'namespace' => $this->getAppNamespace() . 'DTOs',
                'entity'    => $entity,
            ],
            label: "DTO [{$entity}DTO]"
        );
    }

    /**
     * Gera a interface do Repositório.
     */
    protected function generateRepositoryInterface(string $entity): void
    {
        $dir = config('ptah.paths.repositories') . '/Contracts';
        $path = "{$dir}/{$entity}RepositoryInterface.php";

        $this->generateFile(
            stub: 'repository.interface',
            path: $path,
            replacements: [
                'namespace' => $this->getAppNamespace() . 'Repositories\\Contracts',
                'entity'    => $entity,
            ],
            label: "Interface [{$entity}RepositoryInterface]"
        );
    }

    /**
     * Gera o Repositório.
     */
    protected function generateRepository(string $entity): void
    {
        $path = config('ptah.paths.repositories') . "/{$entity}Repository.php";

        $this->generateFile(
            stub: 'repository',
            path: $path,
            replacements: [
                'namespace'     => $this->getAppNamespace() . 'Repositories',
                'entity'        => $entity,
                'rootNamespace' => $this->getAppNamespace(),
            ],
            label: "Repository [{$entity}Repository]"
        );
    }

    /**
     * Gera o Service.
     */
    protected function generateService(string $entity): void
    {
        $path = config('ptah.paths.services') . "/{$entity}Service.php";

        $this->generateFile(
            stub: 'service',
            path: $path,
            replacements: [
                'namespace'     => $this->getAppNamespace() . 'Services',
                'entity'        => $entity,
                'rootNamespace' => $this->getAppNamespace(),
            ],
            label: "Service [{$entity}Service]"
        );
    }

    /**
     * Gera o Controller.
     */
    protected function generateController(
        string $entity,
        string $entityLower,
        string $entityPlural
    ): void {
        $path = config('ptah.paths.controllers') . "/{$entity}Controller.php";

        $this->generateFile(
            stub: 'controller',
            path: $path,
            replacements: [
                'namespace'     => $this->getAppNamespace() . 'Http\\Controllers',
                'entity'        => $entity,
                'entity_lower'  => $entityLower,
                'entities'      => $entityPlural,
                'rootNamespace' => $this->getAppNamespace(),
            ],
            label: "Controller [{$entity}Controller]"
        );
    }

    /**
     * Gera um FormRequest (Store ou Update).
     */
    protected function generateRequest(string $entity, string $type): void
    {
        $className = "{$type}{$entity}Request";
        $path = config('ptah.paths.requests') . "/{$className}.php";

        $this->generateFile(
            stub: "request." . Str::lower($type),
            path: $path,
            replacements: [
                'namespace' => $this->getAppNamespace() . 'Http\\Requests',
                'entity'    => $entity,
            ],
            label: "Request [{$className}]"
        );
    }

    /**
     * Gera o API Resource.
     */
    protected function generateResource(string $entity): void
    {
        $path = config('ptah.paths.resources') . "/{$entity}Resource.php";

        $this->generateFile(
            stub: 'resource',
            path: $path,
            replacements: [
                'namespace' => $this->getAppNamespace() . 'Http\\Resources',
                'entity'    => $entity,
            ],
            label: "Resource [{$entity}Resource]"
        );
    }

    /**
     * Gera as views Blade (index, create, edit, show).
     */
    protected function generateViews(
        string $entity,
        string $entityLower,
        string $entityPlural
    ): void {
        $viewDir = config('ptah.paths.views') . "/{$entityLower}";

        foreach (['index', 'create', 'edit', 'show'] as $view) {
            $path = "{$viewDir}/{$view}.blade.php";

            $this->generateFile(
                stub: "view.{$view}",
                path: $path,
                replacements: [
                    'entity'       => $entity,
                    'entity_lower' => $entityLower,
                    'entities'     => $entityPlural,
                ],
                label: "View [{$entityLower}/{$view}.blade.php]"
            );
        }
    }

    /**
     * Adiciona as rotas resource ao arquivo routes/web.php.
     */
    protected function appendRoutes(string $entity, string $entityLower): void
    {
        $routesPath = base_path('routes/web.php');

        if (! $this->files->exists($routesPath)) {
            $this->components->warn("Arquivo routes/web.php não encontrado. Adicione manualmente:");
            $this->line("  Route::resource('{$entityLower}', {$entity}Controller::class);");
            return;
        }

        $routeEntry = "\nRoute::resource('{$entityLower}', \\App\\Http\\Controllers\\{$entity}Controller::class);";
        $content = $this->files->get($routesPath);

        if (str_contains($content, "'{$entityLower}'")) {
            $this->components->twoColumnDetail("Routes [{$entityLower}]", '<fg=yellow;options=bold>SKIPPED</>');
            return;
        }

        $this->files->append($routesPath, $routeEntry);
        $this->components->twoColumnDetail("Routes [{$entityLower}]", '<fg=green;options=bold>DONE</>');
    }

    /**
     * Gera um arquivo a partir de um stub.
     *
     * @param array<string, string> $replacements
     */
    protected function generateFile(
        string $stub,
        string $path,
        array $replacements,
        string $label
    ): void {
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->components->twoColumnDetail($label, '<fg=yellow;options=bold>SKIPPED</>');
            return;
        }

        $directory = dirname($path);
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $stubContent = $this->getStubContent($stub);
        $content = $this->replaceStubVars($stubContent, $replacements);

        $this->files->put($path, $content);
        $this->components->twoColumnDetail($label, '<fg=green;options=bold>DONE</>');
    }

    /**
     * Obtém o conteúdo de um stub.
     * Prioriza stubs publicados em stubs/ptah/ sobre os do pacote.
     */
    protected function getStubContent(string $stub): string
    {
        $publishedPath = base_path("stubs/ptah/{$stub}.stub");

        if ($this->files->exists($publishedPath)) {
            return $this->files->get($publishedPath);
        }

        $packagePath = __DIR__ . "/../Stubs/{$stub}.stub";

        if (! $this->files->exists($packagePath)) {
            throw new \RuntimeException("Stub [{$stub}] não encontrado.");
        }

        return $this->files->get($packagePath);
    }

    /**
     * Substitui as variáveis no conteúdo do stub.
     *
     * @param array<string, string> $replacements
     */
    protected function replaceStubVars(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }

    /**
     * Retorna o namespace raiz da aplicação.
     */
    protected function getAppNamespace(): string
    {
        return $this->laravel->getNamespace();
    }
}
