<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\RouteGenerator;

/**
 * Covers route appending: web route entry, API resource group and the
 * idempotency guard (same entity never appended twice).
 *
 * base_path() is redirected to the temp dir so the Testbench skeleton's
 * real route files are never touched.
 */
class RouteGeneratorTest extends GeneratorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setBasePath($this->tmpPath);
        $this->files->ensureDirectoryExists($this->tmpPath.'/routes');
        $this->files->put($this->tmpPath.'/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($this->tmpPath.'/routes/api.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
    }

    #[Test]
    public function it_appends_the_web_index_route(): void
    {
        $result = (new RouteGenerator($this->files))->generateWebRoute($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($this->tmpPath.'/routes/web.php');

        $this->assertStringContainsString(
            "Route::get('widget', [\\App\\Http\\Controllers\\WidgetController::class, 'index'])->name('widget.index');",
            $content,
        );
    }

    #[Test]
    public function it_appends_the_api_resource_route_in_a_v1_prefix(): void
    {
        $result = (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($this->tmpPath.'/routes/api.php');

        $this->assertStringContainsString("Route::prefix('v1')", $content);
        $this->assertStringContainsString(
            "Route::apiResource('widgets', \\App\\Http\\Controllers\\API\\WidgetApiController::class);",
            $content,
        );
    }

    #[Test]
    public function it_is_idempotent_and_skips_when_the_route_already_exists(): void
    {
        $generator = new RouteGenerator($this->files);

        $first = $generator->generateWebRoute($this->context());
        $second = $generator->generateWebRoute($this->context());

        $this->assertTrue($first->isDone());
        $this->assertTrue($second->isSkipped());

        // The route appears exactly once.
        $content = (string) file_get_contents($this->tmpPath.'/routes/web.php');
        $this->assertSame(1, substr_count($content, "Route::get('widget'"));
    }

    #[Test]
    public function it_errors_when_the_routes_file_is_missing(): void
    {
        $this->files->delete($this->tmpPath.'/routes/web.php');

        $result = (new RouteGenerator($this->files))->generateWebRoute($this->context());

        $this->assertTrue($result->isError());
    }
}
