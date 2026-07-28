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
    public function web_route_is_gated_behind_auth_when_the_module_is_active(): void
    {
        config(['ptah.modules.auth' => true]);

        (new RouteGenerator($this->files))->generateWebRoute($this->context());

        $content = (string) file_get_contents($this->tmpPath.'/routes/web.php');
        $this->assertStringContainsString("->middleware('auth')", $content);
    }

    #[Test]
    public function web_route_has_no_auth_middleware_when_the_module_is_off(): void
    {
        config(['ptah.modules.auth' => false]);

        (new RouteGenerator($this->files))->generateWebRoute($this->context());

        $content = (string) file_get_contents($this->tmpPath.'/routes/web.php');
        $this->assertStringNotContainsString('middleware', $content);
    }

    #[Test]
    public function it_appends_the_api_resource_route_with_middleware_from_config(): void
    {
        config(['ptah.api.prefix' => 'api', 'ptah.api.middleware' => ['api', 'auth:sanctum']]);

        $result = (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($this->tmpPath.'/routes/api.php');

        $this->assertStringContainsString("Route::prefix('v1')", $content);
        $this->assertStringContainsString("->middleware(['api', 'auth:sanctum'])", $content);
        $this->assertStringContainsString(
            "Route::apiResource('widgets', \\App\\Http\\Controllers\\API\\WidgetApiController::class);",
            $content,
        );
    }

    /**
     * Laravel already mounts routes/api.php under the app's api prefix
     * (withRouting(apiPrefix:)), so the generated group must add ONLY the
     * version segment — prepending ptah.api.prefix produced api/api/v1/…
     */
    #[Test]
    public function the_api_route_group_never_repeats_the_apps_api_prefix(): void
    {
        foreach (['api', 'backend', ''] as $configuredPrefix) {
            config(['ptah.api.prefix' => $configuredPrefix]);
            $this->files->put($this->tmpPath.'/routes/api.php', "<?php\n");

            (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

            $content = (string) file_get_contents($this->tmpPath.'/routes/api.php');
            $this->assertStringContainsString("Route::prefix('v1')", $content, "prefix [{$configuredPrefix}]");
            $this->assertStringNotContainsString("/v1'", str_replace("Route::prefix('v1')", '', $content), "prefix [{$configuredPrefix}]");
        }
    }

    #[Test]
    public function it_errors_and_does_not_fall_back_to_web_php_when_api_routes_file_is_missing(): void
    {
        $this->files->delete($this->tmpPath.'/routes/api.php');
        $webContentBefore = (string) file_get_contents($this->tmpPath.'/routes/web.php');

        $result = (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('routes/api.php not found', (string) $result->message);

        // Never fall back to web.php: no apiResource must have leaked into it,
        // and its content must be byte-for-byte unchanged.
        $webContentAfter = (string) file_get_contents($this->tmpPath.'/routes/web.php');
        $this->assertSame($webContentBefore, $webContentAfter);
        $this->assertStringNotContainsString('apiResource', $webContentAfter);
    }

    #[Test]
    public function it_warns_inline_when_the_sanctum_guard_is_not_configured(): void
    {
        config(['auth.guards.sanctum' => null, 'ptah.api.middleware' => ['api', 'auth:sanctum']]);

        (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

        $content = (string) file_get_contents($this->tmpPath.'/routes/api.php');
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('sanctum', $content);
    }

    #[Test]
    public function it_does_not_warn_when_the_sanctum_guard_is_configured(): void
    {
        config(['auth.guards.sanctum' => ['driver' => 'sanctum'], 'ptah.api.middleware' => ['api', 'auth:sanctum']]);

        (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

        $content = (string) file_get_contents($this->tmpPath.'/routes/api.php');
        $this->assertStringNotContainsString('WARNING', $content);
    }

    #[Test]
    public function it_never_generates_an_open_route_when_middleware_config_is_empty(): void
    {
        config(['ptah.api.middleware' => []]);

        (new RouteGenerator($this->files))->generateApiRoute($this->context(withApi: true, withViews: false));

        $content = (string) file_get_contents($this->tmpPath.'/routes/api.php');
        $this->assertStringContainsString("->middleware(['api', 'auth:sanctum'])", $content);
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
