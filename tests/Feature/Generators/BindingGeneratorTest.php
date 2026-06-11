<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\BindingGenerator;

/**
 * Covers the AppServiceProvider binding injection: use imports, bind() inside
 * register(), idempotency and the php -l safety net (restore on broken output).
 */
class BindingGeneratorTest extends GeneratorTestCase
{
    private string $providerPath;

    protected function setUp(): void
    {
        parent::setUp();

        // app_path() must point at the temp dir so the Testbench skeleton's
        // real AppServiceProvider is never touched.
        $this->app->useAppPath($this->tmpPath.'/app');
        $this->providerPath = $this->tmpPath.'/app/Providers/AppServiceProvider.php';

        $this->files->ensureDirectoryExists(dirname($this->providerPath));
        $this->files->put($this->providerPath, <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
PHP);
    }

    #[Test]
    public function it_injects_the_interface_binding_into_register(): void
    {
        $result = (new BindingGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($this->providerPath);

        $this->assertStringContainsString('use App\Repositories\Contracts\WidgetRepositoryInterface;', $content);
        $this->assertStringContainsString('use App\Repositories\WidgetRepository;', $content);
        $this->assertStringContainsString(
            '$this->app->bind(WidgetRepositoryInterface::class, WidgetRepository::class);',
            $content,
        );
    }

    #[Test]
    public function modified_provider_remains_valid_php(): void
    {
        (new BindingGenerator($this->files))->generate($this->context());

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($this->providerPath), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    #[Test]
    public function it_is_idempotent_when_the_binding_already_exists(): void
    {
        $generator = new BindingGenerator($this->files);

        $first = $generator->generate($this->context());
        $second = $generator->generate($this->context());

        $this->assertTrue($first->isDone());
        $this->assertTrue($second->isSkipped());

        $content = (string) file_get_contents($this->providerPath);
        $this->assertSame(1, substr_count($content, 'WidgetRepositoryInterface::class'));
    }

    #[Test]
    public function it_errors_when_the_provider_file_is_missing(): void
    {
        $this->files->delete($this->providerPath);

        $result = (new BindingGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isError());
    }
}
