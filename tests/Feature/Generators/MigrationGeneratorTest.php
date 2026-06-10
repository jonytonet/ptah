<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\MigrationGenerator;
use Ptah\Support\EntityContext;
use Ptah\Tests\TestCase;

/**
 * Covers the migration generator output, in particular the soft-deletes handling
 * (deleted_at index + the --no-soft-deletes behaviour on a fresh migration).
 */
class MigrationGeneratorTest extends TestCase
{
    private string $tmpDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDatabasePath = sys_get_temp_dir().'/ptah-mig-'.uniqid();
        $this->app->useDatabasePath($this->tmpDatabasePath);
        (new Filesystem)->ensureDirectoryExists($this->tmpDatabasePath.'/migrations');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmpDatabasePath);
        parent::tearDown();
    }

    #[Test]
    public function it_adds_soft_deletes_and_a_deleted_at_index_when_enabled(): void
    {
        $result = (new MigrationGenerator(new Filesystem))->generate($this->context(withSoftDeletes: true));
        $content = file_get_contents($result->path);

        $this->assertStringContainsString('$table->softDeletes();', $content);
        $this->assertStringContainsString("\$table->index('deleted_at');", $content);
    }

    #[Test]
    public function it_omits_soft_deletes_and_the_index_with_no_soft_deletes(): void
    {
        $result = (new MigrationGenerator(new Filesystem))->generate($this->context(withSoftDeletes: false));
        $content = file_get_contents($result->path);

        $this->assertStringNotContainsString('softDeletes', $content);
        $this->assertStringNotContainsString("index('deleted_at')", $content);
        $this->assertStringNotContainsString("'deleted_by'", $content);
    }

    private function context(bool $withSoftDeletes): EntityContext
    {
        return new EntityContext(
            entity: 'Widget',
            entityLower: 'widget',
            entityPlural: 'widgets',
            entityPluralStudly: 'Widgets',
            table: 'widgets_'.uniqid(),
            rootNamespace: 'App\\',
            timestamp: date('Y_m_d_His'),
            withViews: true,
            withSoftDeletes: $withSoftDeletes,
            force: false,
            fields: [],
        );
    }
}
