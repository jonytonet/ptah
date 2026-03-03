<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Generates the API structure for an entity (no views).
 *
 * Usage: php artisan ptah:make {Entity} --api
 *
 * Note: this command is automatically invoked when passing --api to ptah:make.
 * It can also be used directly.
 */
class MakeApiCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ptah:make-api
                            {entity : Entity name (e.g. Product)}
                            {--force : Overwrite existing files}';

    /**
     * @var string
     */
    protected $description = 'Generates the complete API structure for an entity: Model, Migration, DTO, Repository, Service, API Controller, Requests and Resource.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Runs the command.
     */
    public function handle(): int
    {
        $entity = Str::studly($this->argument('entity'));
        $entityLower = Str::snake($entity);
        $entityPlural = Str::plural($entityLower);

        $this->components->info("Generating API structure for entity: {$entity}");

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
            fn () => $this->appendApiRoutes($entity, $entityLower),
        ];

        foreach ($generators as $generator) {
            $generator();
        }

        $this->components->info('API structure generated successfully!');
        $this->newLine();
        $this->components->bulletList([
            "Remember to register the repository binding in AppServiceProvider:",
            "  \$this->app->bind({$entity}RepositoryInterface::class, {$entity}Repository::class);",
        ]);

        return self::SUCCESS;
    }

    /**
     * Generates the Model file.
     */
    protected function generateModel(string $entity): void
    {
        $path = config('ptah.paths.models') . "/{$entity}.php";
        $this->generateFile('model', $path, [
            'namespace' => $this->getAppNamespace() . 'Models',
            'entity'    => $entity,
        ], "Model [{$entity}]");
    }

    /**
     * Generates the Migration file.
     */
    protected function generateMigration(string $entity, string $table): void
    {
        $timestamp = date('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$table}_table.php");
        $this->generateFile('migration', $path, [
            'table' => $table,
        ], "Migration [create_{$table}_table]");
    }

    /**
     * Generates the DTO file.
     */
    protected function generateDTO(string $entity): void
    {
        $path = config('ptah.paths.dtos') . "/{$entity}DTO.php";
        $this->generateFile('dto', $path, [
            'namespace' => $this->getAppNamespace() . 'DTOs',
            'entity'    => $entity,
        ], "DTO [{$entity}DTO]");
    }

    /**
     * Generates the Repository interface.
     */
    protected function generateRepositoryInterface(string $entity): void
    {
        $path = config('ptah.paths.repositories') . "/Contracts/{$entity}RepositoryInterface.php";
        $this->generateFile('repository.interface', $path, [
            'namespace' => $this->getAppNamespace() . 'Repositories\\Contracts',
            'entity'    => $entity,
        ], "Interface [{$entity}RepositoryInterface]");
    }

    /**
     * Generates the Repository.
     */
    protected function generateRepository(string $entity): void
    {
        $path = config('ptah.paths.repositories') . "/{$entity}Repository.php";
        $this->generateFile('repository', $path, [
            'namespace'     => $this->getAppNamespace() . 'Repositories',
            'entity'        => $entity,
            'rootNamespace' => $this->getAppNamespace(),
        ], "Repository [{$entity}Repository]");
    }

    /**
     * Generates the Service.
     */
    protected function generateService(string $entity): void
    {
        $path = config('ptah.paths.services') . "/{$entity}Service.php";
        $this->generateFile('service', $path, [
            'namespace'     => $this->getAppNamespace() . 'Services',
            'entity'        => $entity,
            'rootNamespace' => $this->getAppNamespace(),
        ], "Service [{$entity}Service]");
    }

    /**
     * Generates the API Controller.
     */
    protected function generateController(
        string $entity,
        string $entityLower,
        string $entityPlural
    ): void {
        $path = config('ptah.paths.controllers') . "/Api/{$entity}Controller.php";
        $this->generateFile('controller.api', $path, [
            'namespace'     => $this->getAppNamespace() . 'Http\\Controllers\\Api',
            'entity'        => $entity,
            'entity_lower'  => $entityLower,
            'entities'      => $entityPlural,
            'rootNamespace' => $this->getAppNamespace(),
        ], "Controller API [{$entity}Controller]");
    }

    /**
     * Generates a FormRequest (Store or Update).
     */
    protected function generateRequest(string $entity, string $type): void
    {
        $className = "{$type}{$entity}Request";
        $path = config('ptah.paths.requests') . "/{$className}.php";
        $this->generateFile("request." . Str::lower($type), $path, [
            'namespace' => $this->getAppNamespace() . 'Http\\Requests',
            'entity'    => $entity,
        ], "Request [{$className}]");
    }

    /**
     * Generates the API Resource.
     */
    protected function generateResource(string $entity): void
    {
        $path = config('ptah.paths.resources') . "/{$entity}Resource.php";
        $this->generateFile('resource', $path, [
            'namespace' => $this->getAppNamespace() . 'Http\\Resources',
            'entity'    => $entity,
        ], "Resource [{$entity}Resource]");
    }

    /**
     * Appends API resource routes to routes/api.php.
     */
    protected function appendApiRoutes(string $entity, string $entityLower): void
    {
        $routesPath = base_path('routes/api.php');

        if (! $this->files->exists($routesPath)) {
            $this->components->warn("File routes/api.php not found. Add manually:");
            $this->line("  Route::apiResource('{$entityLower}', Api\\{$entity}Controller::class);");
            return;
        }

        $routeEntry = "\nRoute::apiResource('{$entityLower}', \\App\\Http\\Controllers\\Api\\{$entity}Controller::class);";
        $content = $this->files->get($routesPath);

        if (str_contains($content, "'{$entityLower}'")) {
            $this->components->twoColumnDetail("Routes [{$entityLower}]", '<fg=yellow;options=bold>SKIPPED</>');
            return;
        }

        $this->files->append($routesPath, $routeEntry);
        $this->components->twoColumnDetail("Routes [{$entityLower}]", '<fg=green;options=bold>DONE</>');
    }

    /**
     * Generates a file from a stub.
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
     * Retrieves the contents of a stub file.
     */
    protected function getStubContent(string $stub): string
    {
        $publishedPath = base_path("stubs/ptah/{$stub}.stub");

        if ($this->files->exists($publishedPath)) {
            return $this->files->get($publishedPath);
        }

        $packagePath = __DIR__ . "/../Stubs/{$stub}.stub";

        if (! $this->files->exists($packagePath)) {
            throw new \RuntimeException("Stub [{$stub}] not found.");
        }

        return $this->files->get($packagePath);
    }

    /**
     * Replaces stub placeholder variables with actual values.
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
     * Returns the root namespace of the application.
     */
    protected function getAppNamespace(): string
    {
        return $this->laravel->getNamespace();
    }
}
