<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\InstallCommand;
use Ptah\Tests\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * `updateAppCss()` is the only place `ptah:install` wires the host's Tailwind
 * entrypoint to Ptah's compiled component styles. Before this fix, it injected
 * `@source`, `@custom-variant dark` and the brand `@theme` tokens but never the
 * `@import '.../ptah-components.css'` line — so a fresh install shipped a host
 * app with zero `.ptah-c-*` classes and none of the 24 neutral `--ptah-*`
 * tokens, even though the command reported every step as successful.
 *
 * `ptah-components.css` is imported (not `forge.css`): `forge.css` itself
 * re-declares `@import "tailwindcss"`, which would double-import Tailwind in
 * the host.
 *
 * base_path() is redirected to a temp dir so the Testbench skeleton's real
 * resources/css/app.css is never touched.
 */
class InstallCommandUpdateAppCssTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-install-'.uniqid();
        $this->files->ensureDirectoryExists($this->tmpPath.'/resources/css');
        $this->app->setBasePath($this->tmpPath);
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);

        parent::tearDown();
    }

    #[Test]
    public function it_injects_the_components_import_after_tailwindcss_and_before_the_theme_block(): void
    {
        $this->files->put($this->appCssPath(), "@import 'tailwindcss';\n");

        $this->runUpdateAppCss();

        $content = (string) file_get_contents($this->appCssPath());

        $this->assertStringContainsString(
            "@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';",
            $content,
        );

        $tailwindPos = strpos($content, "@import 'tailwindcss';");
        $importPos = strpos($content, 'ptah-components.css');
        $themePos = strpos($content, '@theme {');
        $this->assertNotFalse($tailwindPos);
        $this->assertNotFalse($importPos);
        $this->assertNotFalse($themePos);
        $this->assertLessThan($importPos, $tailwindPos);
        $this->assertLessThan($themePos, $importPos);
    }

    /**
     * Reproduces the real-world layout of a freshly scaffolded host app.css
     * (Laravel already ships a few @source lines of its own, e.g. for
     * pagination views): here the components import must land immediately
     * after the tailwindcss import, ahead of every other directive the
     * command injects.
     */
    #[Test]
    public function it_injects_the_components_import_right_after_tailwindcss_when_source_lines_already_exist(): void
    {
        $this->files->put($this->appCssPath(), <<<'CSS'
        @import 'tailwindcss';

        @source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
        @source '../../storage/framework/views/*.php';

        CSS);

        $this->runUpdateAppCss();

        $content = (string) file_get_contents($this->appCssPath());

        $this->assertStringContainsString(
            "@import 'tailwindcss';\n@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';",
            $content,
        );
    }

    /**
     * Regression guard for a collision the components import introduced.
     *
     * The @source guard used to be `str_contains($content, 'vendor/jonytonet/ptah')`.
     * The import line added just above it contains that exact substring, so from the
     * moment the import existed the guard was always true and the @source for the
     * package's views was never written again — meaning Tailwind would not scan Ptah's
     * Blade files and every utility class used ONLY inside the package would be
     * tree-shaken out of the host's bundle. A fresh install would look broken in a way
     * that has nothing to do with the import it just gained.
     *
     * Both directives must be present after one run. Widening the guard back to the
     * bare prefix fails here.
     */
    #[Test]
    public function it_injects_both_the_components_import_and_the_views_source(): void
    {
        $this->files->put($this->appCssPath(), "@import 'tailwindcss';\n");

        $this->runUpdateAppCss();

        $content = (string) file_get_contents($this->appCssPath());

        $this->assertStringContainsString(
            "@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';",
            $content,
            'O import dos estilos do pacote nao foi injetado.'
        );
        $this->assertStringContainsString(
            "@source '../../vendor/jonytonet/ptah/resources/views/**/*.blade.php';",
            $content,
            'O @source das views do pacote nao foi injetado. Se a guarda desse @source voltar a '.
            'testar apenas "vendor/jonytonet/ptah", o import de CSS acima ja satisfaz a condicao '.
            'e esta linha nunca mais e escrita.'
        );
    }

    #[Test]
    public function running_it_twice_does_not_duplicate_the_import(): void
    {
        $this->files->put($this->appCssPath(), "@import 'tailwindcss';\n");

        $this->runUpdateAppCss();
        $this->runUpdateAppCss();

        $content = (string) file_get_contents($this->appCssPath());

        $this->assertSame(1, substr_count($content, 'ptah-components.css'));
    }

    #[Test]
    public function an_app_css_that_already_has_the_import_is_left_untouched(): void
    {
        $original = <<<'CSS'
        @import 'tailwindcss';
        @import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';
        @source '../../vendor/jonytonet/ptah/resources/views/**/*.blade.php';
        @custom-variant dark (&:where(.dark, .dark *));

        :root {
            --ptah-surface: #fafafa;
        }

        @theme {
            --color-primary: #1e40af;
        }
        CSS;

        $this->files->put($this->appCssPath(), $original);

        $this->runUpdateAppCss();

        $content = (string) file_get_contents($this->appCssPath());

        $this->assertSame($original, $content);
    }

    /**
     * The upgrade path for every host installed before this fix, and therefore the
     * scenario that decides whether the fix reaches anyone: the app.css is already
     * fully configured (brand tokens present) and only the components import is
     * missing. updateAppCss() takes an early return on "already configured", so
     * without an explicit write at that point the import is computed in memory and
     * discarded — re-running `ptah:install` would report success and change nothing.
     */
    #[Test]
    public function a_host_configured_before_this_fix_gains_the_import_on_re_run(): void
    {
        $this->files->put($this->appCssPath(), <<<'CSS'
        @import 'tailwindcss';
        @source '../../vendor/jonytonet/ptah/resources/views/**/*.blade.php';
        @custom-variant dark (&:where(.dark, .dark *));

        @theme {
            --color-primary: #1e40af;
        }
        CSS);

        $this->runUpdateAppCss();

        $content = (string) file_get_contents($this->appCssPath());

        $this->assertStringContainsString(
            "@import '../../vendor/jonytonet/ptah/resources/css/ptah-components.css';",
            $content,
            'Host ja configurado (com --color-primary) nao recebeu o import ao reinstalar. '.
            'O early-return de "already configured" precisa gravar o conteudo antes de retornar, '.
            'senao a correcao nunca alcanca nenhuma instalacao existente.'
        );
    }

    #[Test]
    public function it_warns_and_does_nothing_when_app_css_is_missing(): void
    {
        $this->runUpdateAppCss();

        $this->assertFileDoesNotExist($this->appCssPath());
    }

    private function appCssPath(): string
    {
        return $this->tmpPath.'/resources/css/app.css';
    }

    /**
     * Invokes the protected updateAppCss() method directly — InstallCommand
     * runs migrations, seeders and npm during handle(), which is out of scope
     * for this filesystem-only concern. components/output are wired the same
     * way Illuminate\Console\Command::run() wires them, minus the full command
     * pipeline.
     */
    private function runUpdateAppCss(): void
    {
        $command = $this->app->make(InstallCommand::class);
        $command->setLaravel($this->app);

        $components = new ReflectionProperty($command, 'components');
        $components->setValue($command, new Factory(new OutputStyle(new ArrayInput([]), new BufferedOutput)));

        (new ReflectionMethod($command, 'updateAppCss'))->invoke($command);
    }
}
