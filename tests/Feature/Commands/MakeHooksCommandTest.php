<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Covers ptah:hooks — the class-based hook scaffolder users migrate to
 * when an inline sandbox expression isn't enough.
 */
class MakeHooksCommandTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-hooks-'.uniqid();
        $this->app->useAppPath($this->tmpPath.'/app');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    #[Test]
    public function it_creates_a_hook_class_implementing_the_interface(): void
    {
        $this->artisan('ptah:hooks', ['name' => 'ProductHooks'])->assertSuccessful();

        $path = $this->tmpPath.'/app/CrudHooks/ProductHooks.php';
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('namespace App\CrudHooks;', $content);
        $this->assertStringContainsString('class ProductHooks implements CrudHooksInterface', $content);

        foreach (['beforeCreate', 'afterCreate', 'beforeUpdate', 'afterUpdate'] as $method) {
            $this->assertStringContainsString("public function {$method}(", $content);
        }

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path), $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    #[Test]
    public function it_supports_subfolders_with_the_right_namespace(): void
    {
        $this->artisan('ptah:hooks', ['name' => 'Inventory/StockHooks'])->assertSuccessful();

        $path = $this->tmpPath.'/app/CrudHooks/Inventory/StockHooks.php';
        $this->assertFileExists($path);
        $this->assertStringContainsString(
            'namespace App\CrudHooks\Inventory;',
            (string) file_get_contents($path),
        );
    }

    #[Test]
    public function it_refuses_to_overwrite_without_force(): void
    {
        $this->artisan('ptah:hooks', ['name' => 'KeepHooks'])->assertSuccessful();

        $path = $this->tmpPath.'/app/CrudHooks/KeepHooks.php';
        $this->files->append($path, "\n// custom marker\n");

        $this->artisan('ptah:hooks', ['name' => 'KeepHooks'])->assertFailed();

        $this->assertStringContainsString('// custom marker', (string) file_get_contents($path));
    }

    #[Test]
    public function force_overwrites_the_existing_file(): void
    {
        $this->artisan('ptah:hooks', ['name' => 'ReplaceHooks'])->assertSuccessful();

        $path = $this->tmpPath.'/app/CrudHooks/ReplaceHooks.php';
        $this->files->append($path, "\n// custom marker\n");

        $this->artisan('ptah:hooks', ['name' => 'ReplaceHooks', '--force' => true])->assertSuccessful();

        $this->assertStringNotContainsString('// custom marker', (string) file_get_contents($path));
    }
}
