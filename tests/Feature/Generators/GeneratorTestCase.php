<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use Illuminate\Filesystem\Filesystem;
use Ptah\Support\EntityContext;
use Ptah\Support\FieldDefinition;
use Ptah\Tests\TestCase;

/**
 * Shared base for generator tests.
 *
 * Redirects every ptah.paths.* config entry to a per-test temporary directory
 * so generators never write inside the Testbench skeleton, and provides a
 * standard EntityContext with a string, a decimal and an FK field.
 */
abstract class GeneratorTestCase extends TestCase
{
    protected string $tmpPath;

    protected Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-gen-'.uniqid();
        $this->files->ensureDirectoryExists($this->tmpPath);

        config([
            'ptah.paths.models' => $this->tmpPath.'/app/Models',
            'ptah.paths.services' => $this->tmpPath.'/app/Services',
            'ptah.paths.repositories' => $this->tmpPath.'/app/Repositories',
            'ptah.paths.dtos' => $this->tmpPath.'/app/DTOs',
            'ptah.paths.requests' => $this->tmpPath.'/app/Http/Requests',
            'ptah.paths.resources' => $this->tmpPath.'/app/Http/Resources',
            'ptah.paths.controllers' => $this->tmpPath.'/app/Http/Controllers',
            'ptah.paths.views' => $this->tmpPath.'/resources/views',
        ]);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    /**
     * Standard context: Widget with name (string), price (decimal 10,2 nullable)
     * and category_id (unsignedBigInteger FK).
     *
     * @param  FieldDefinition[]|null  $fields  Override the default field set.
     */
    protected function context(
        bool $withSoftDeletes = true,
        bool $withApi = false,
        bool $withViews = true,
        ?array $fields = null,
    ): EntityContext {
        return new EntityContext(
            entity: 'Widget',
            entityLower: 'widget',
            entityPlural: 'widgets',
            entityPluralStudly: 'Widgets',
            table: 'widgets',
            rootNamespace: 'App\\',
            timestamp: date('Y_m_d_His'),
            withViews: $withViews,
            withSoftDeletes: $withSoftDeletes,
            force: false,
            fields: $fields ?? $this->defaultFields(),
            withApi: $withApi,
        );
    }

    /** @return FieldDefinition[] */
    protected function defaultFields(): array
    {
        return [
            $this->field('name', 'string'),
            $this->field('price', 'decimal', nullable: true, precision: 10, scale: 2),
            $this->field('category_id', 'unsignedBigInteger'),
        ];
    }

    protected function field(
        string $name,
        string $type,
        bool $nullable = false,
        bool $unique = false,
        int $precision = 8,
        int $scale = 2,
        array $enumValues = [],
    ): FieldDefinition {
        return new FieldDefinition(
            name: $name,
            type: $type,
            nullable: $nullable,
            unique: $unique,
            precision: $precision,
            scale: $scale,
            enumValues: $enumValues,
        );
    }
}
