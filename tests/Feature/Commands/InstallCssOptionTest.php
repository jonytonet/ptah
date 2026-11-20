<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\InstallCommand;
use Ptah\Support\CssFramework;
use Ptah\Tests\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * `ptah:install --css=bootstrap` (docs/Css.md): PTAH_CSS in .env, the
 * stylesheet published, and the host's app.css left alone — a Bootstrap host
 * gets no Tailwind. Without the option, the install is what it always was.
 */
class InstallCssOptionTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    /** Where the provider registered the publish target, at boot (the skeleton's public/). */
    private string $published;

    private const APP_CSS = "/* host */\n";

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->published = public_path(CssFramework::PUBLIC_PATH);
        $this->files->delete($this->published);
        $this->tmpPath = sys_get_temp_dir().'/ptah-css-'.uniqid();
        $this->files->ensureDirectoryExists($this->tmpPath.'/resources/css');
        $this->files->put($this->tmpPath.'/.env', "APP_NAME=Host\n");
        $this->files->put($this->tmpPath.'/resources/css/app.css', "@import 'tailwindcss';\n".self::APP_CSS);
        $this->app->setBasePath($this->tmpPath);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);
        $this->files->delete($this->published);

        parent::tearDown();
    }

    private function configure(?string $option): void
    {
        $command = new class($this->files, $option) extends InstallCommand
        {
            public function __construct(Filesystem $files, private ?string $css)
            {
                parent::__construct($files);
            }

            public function prepare(): void
            {
                $this->components = $this->laravel->make(Factory::class, ['output' => $this->output]);
                $css = $this->resolveCss();
                $this->configureCss((string) $css, 'livewire');
            }

            public function option($key = null)
            {
                return $key === 'css' ? $this->css : false;
            }
        };
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));
        // vendor:publish and view:clear are called through the console application.
        $kernel = $this->app->make(Kernel::class);
        $command->setApplication((new ReflectionMethod($kernel, 'getArtisan'))->invoke($kernel));
        $command->prepare();
    }

    #[Test]
    public function bootstrap_sets_the_env_publishes_the_stylesheet_and_leaves_app_css_alone(): void
    {
        $this->configure('bootstrap');

        $this->assertStringContainsString("PTAH_CSS=bootstrap\n", $this->files->get($this->tmpPath.'/.env'));
        $this->assertSame('bootstrap', CssFramework::current());
        $this->assertFileEquals(CssFramework::distPath(), $this->published);
        $this->assertSame("@import 'tailwindcss';\n".self::APP_CSS, $this->files->get($this->tmpPath.'/resources/css/app.css'));
    }

    #[Test]
    public function the_default_install_wires_tailwind_and_writes_nothing_about_css_to_env(): void
    {
        $this->configure(null);

        $this->assertStringNotContainsString('PTAH_CSS', $this->files->get($this->tmpPath.'/.env'));
        $this->assertFileDoesNotExist($this->published);
        $this->assertStringContainsString('ptah-components.css', $this->files->get($this->tmpPath.'/resources/css/app.css'));
    }

    #[Test]
    public function re_running_without_the_option_keeps_bootstrap(): void
    {
        config(['ptah.css.framework' => 'bootstrap']);

        $this->configure(null);

        $this->assertFileExists($this->published);
        $this->assertStringNotContainsString('ptah-components.css', $this->files->get($this->tmpPath.'/resources/css/app.css'));
    }

    #[Test]
    public function an_unknown_framework_is_refused(): void
    {
        $this->artisan('ptah:install', ['--css' => 'foundation', '--skip-npm' => true])->assertFailed();
    }
}
