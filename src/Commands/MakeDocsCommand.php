<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Generates Swagger/OpenAPI documentation for an entity.
 *
 * Usage: php artisan ptah:docs {Entity}
 *
 * Requires the darkaonline/l5-swagger package for full generation.
 */
class MakeDocsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ptah:docs
                            {entity? : Entity name to document (optional; documents all if omitted)}
                            {--force : Overwrite existing files}';

    /**
     * @var string
     */
    protected $description = 'Generates Swagger/OpenAPI annotations for an entity.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Runs the command.
     */
    public function handle(): int
    {
        $entity = $this->argument('entity');

        if ($entity) {
            $entity = Str::studly($entity);
            $this->generateDocs($entity);
        } else {
            $this->components->warn('No entity specified. Provide one with: ptah:docs {Entity}');
        }

        return self::SUCCESS;
    }

    /**
     * Generates Swagger annotations for the entity.
     */
    protected function generateDocs(string $entity): void
    {
        $entityLower = Str::snake($entity);
        $entityPlural = Str::plural($entityLower);

        $controllerPath = config('ptah.paths.controllers') . "/{$entity}Controller.php";

        if (! $this->files->exists($controllerPath)) {
            $this->components->error("Controller [{$entity}Controller] not found. Run ptah:make {$entity} first.");
            return;
        }

        $annotations = $this->buildSwaggerAnnotations($entity, $entityLower, $entityPlural);

        // Insert annotations at the beginning of the controller, after declare(strict_types=1)
        $controllerContent = $this->files->get($controllerPath);

        if (str_contains($controllerContent, '@OA\\')) {
            if (! $this->option('force')) {
                $this->components->twoColumnDetail("Docs [{$entity}Controller]", '<fg=yellow;options=bold>SKIPPED</>');
                return;
            }
        }

        $this->components->twoColumnDetail("Docs [{$entity}Controller]", '<fg=green;options=bold>DONE</>');
        $this->newLine();
        $this->components->info('Swagger annotations generated:');
        $this->line($annotations);
        $this->newLine();
        $this->components->warn('Add the annotations above to the controller class manually.');

        if (! class_exists('L5Swagger\\L5SwaggerServiceProvider')) {
            $this->newLine();
            $this->components->warn('For full docs generation, install: composer require darkaonline/l5-swagger');
        }
    }

    /**
     * Builds the Swagger annotations for the entity.
     */
    protected function buildSwaggerAnnotations(
        string $entity,
        string $entityLower,
        string $entityPlural
    ): string {
        return <<<ANNOTATIONS
/**
 * @OA\\Tag(name="{$entity}", description="{$entity} operations")
 *
 * @OA\\Get(
 *     path="/api/{$entityPlural}",
 *     tags={"{$entity}"},
 *     summary="List all {$entityLower}s",
 *     @OA\\Response(response=200, description="Paginated list of {$entityLower}s")
 * )
 *
 * @OA\\Post(
 *     path="/api/{$entityPlural}",
 *     tags={"{$entity}"},
 *     summary="Create a new {$entityLower}",
 *     @OA\\Response(response=201, description="{$entity} created"),
 *     @OA\\Response(response=422, description="Validation error")
 * )
 *
 * @OA\\Get(
 *     path="/api/{$entityPlural}/{id}",
 *     tags={"{$entity}"},
 *     summary="Retrieve a specific {$entityLower}",
 *     @OA\\Parameter(name="id", in="path", required=true, @OA\\Schema(type="integer")),
 *     @OA\\Response(response=200, description="{$entity} found"),
 *     @OA\\Response(response=404, description="{$entity} not found")
 * )
 *
 * @OA\\Put(
 *     path="/api/{$entityPlural}/{id}",
 *     tags={"{$entity}"},
 *     summary="Update a {$entityLower}",
 *     @OA\\Parameter(name="id", in="path", required=true, @OA\\Schema(type="integer")),
 *     @OA\\Response(response=200, description="{$entity} updated"),
 *     @OA\\Response(response=404, description="{$entity} not found")
 * )
 *
 * @OA\\Delete(
 *     path="/api/{$entityPlural}/{id}",
 *     tags={"{$entity}"},
 *     summary="Delete a {$entityLower}",
 *     @OA\\Parameter(name="id", in="path", required=true, @OA\\Schema(type="integer")),
 *     @OA\\Response(response=204, description="{$entity} deleted"),
 *     @OA\\Response(response=404, description="{$entity} not found")
 * )
 */
ANNOTATIONS;
    }
}
