<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\InstallCommand;
use Ptah\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * The React/Vue stack (1.59.0): what `ptah:install --stack` prepares and
 * what `ptah:forge` generates for it — the same back end, another page.
 */
class StackTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-stack-'.uniqid();

        $this->app->setBasePath($this->tmpPath);
        $this->app->useAppPath($this->tmpPath.'/app');
        $this->app->useDatabasePath($this->tmpPath.'/database');

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

        foreach (['routes', 'database/migrations', 'app/Providers', 'resources/js'] as $dir) {
            $this->files->ensureDirectoryExists($this->tmpPath.'/'.$dir);
        }

        $this->files->put($this->tmpPath.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
        $this->files->put($this->tmpPath.'/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        $this->files->put($this->tmpPath.'/app/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass AppServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n        //\n    }\n\n    public function boot(): void\n    {\n        //\n    }\n}\n");
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    private function forge(string $entity): void
    {
        $this->artisan('ptah:forge', ['entity' => $entity, '--fields' => 'name:string', '--no-menu' => true])->assertSuccessful();
    }

    // ── ptah:forge follows the stack ────────────────────────────────────────

    #[Test]
    public function the_livewire_stack_generates_the_livewire_screen_as_before(): void
    {
        $this->forge('Gadget');

        $view = $this->files->get($this->tmpPath.'/resources/views/gadget/index.blade.php');
        $this->assertStringContainsString("@livewire('ptah-base-crud', ['model' => 'Gadget'])", $view);
        $this->assertFileDoesNotExist($this->tmpPath.'/routes/ptah-screens.php');
    }

    #[Test]
    public function the_react_and_vue_stacks_generate_a_page_and_register_the_screen(): void
    {
        foreach (['react' => 'Gadget', 'vue' => 'Catalog/Widget'] as $stack => $entity) {
            config(['ptah.stack' => $stack]);
            $this->forge($entity);
        }

        $view = $this->files->get($this->tmpPath.'/resources/views/gadget/index.blade.php');
        $this->assertStringContainsString("@include('ptah::stack.app', ['screen' => 'gadget', 'title' => 'Gadget'])", $view);
        $this->assertStringNotContainsString('@livewire', $view);

        $screens = $this->files->get($this->tmpPath.'/routes/ptah-screens.php');
        $this->assertStringContainsString("CrudScreen::register('gadget', 'Gadget');", $screens);
        $this->assertStringContainsString("CrudScreen::register('widget', 'Catalog/Widget');", $screens);
        $this->assertSame(0, substr_count($screens, 'Route::'));

        // The back end is the same as the Livewire stack's.
        $this->assertFileExists($this->tmpPath.'/app/Models/Gadget.php');
        $this->assertFileExists($this->tmpPath.'/app/Services/GadgetService.php');
        $this->assertStringContainsString("Route::get('gadget'", $this->files->get($this->tmpPath.'/routes/web.php'));

        // Forging again does not register the screen twice.
        config(['ptah.stack' => 'react']);
        $this->artisan('ptah:forge', ['entity' => 'Gadget', '--fields' => 'name:string', '--no-menu' => true, '--force' => true])
            ->expectsConfirmation('Continue?', 'yes')
            ->assertSuccessful();
        $this->assertSame(1, substr_count($this->files->get($this->tmpPath.'/routes/ptah-screens.php'), "CrudScreen::register('gadget'"));
    }

    // ── The page of a screen ────────────────────────────────────────────────

    #[Test]
    public function the_page_carries_the_screen_key_and_never_a_model(): void
    {
        config(['ptah.crud_api.entry' => 'resources/js/ptah-app.jsx']);
        $this->withoutVite();

        $html = view('ptah::stack.app', ['screen' => 'gadget', 'title' => 'Gadgets'])->render();

        $this->assertStringContainsString('id="ptah-app"', $html);
        $this->assertStringContainsString('data-screen="gadget"', $html);
        $this->assertStringContainsString('data-title="Gadgets"', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('/ptah/api"', $html);
        $this->assertStringNotContainsString('App\\Models', $html);
    }

    // ── ptah:install --stack ────────────────────────────────────────────────

    private function installer(string $stack): void
    {
        $command = new class($this->files) extends InstallCommand
        {
            public function prepare(string $stack): void
            {
                // What Command::run() sets up before handle().
                $this->components = $this->laravel->make(Factory::class, ['output' => $this->output]);
                $this->installStack($stack);
            }

            public function option($key = null)
            {
                return $key === null ? [] : false;
            }
        };
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));
        $command->prepare($stack);
    }

    #[Test]
    public function installing_a_stack_prepares_the_app_and_is_safe_to_repeat(): void
    {
        $this->files->put($this->tmpPath.'/.env', "APP_NAME=Test\nPTAH_STACK=livewire\n");
        $this->files->put($this->tmpPath.'/package.json', json_encode(['private' => true, 'devDependencies' => ['vite' => '^7.0.0']]));
        $this->files->put($this->tmpPath.'/vite.config.js', "import { defineConfig } from 'vite';\nimport laravel from 'laravel-vite-plugin';\n\nexport default defineConfig({\n    plugins: [\n        laravel({\n            input: ['resources/js/app.js'],\n        }),\n    ],\n});\n");

        $this->installer('react');
        $this->installer('react');

        $env = $this->files->get($this->tmpPath.'/.env');
        $this->assertSame(1, substr_count($env, 'PTAH_STACK=react'));
        $this->assertStringNotContainsString('PTAH_STACK=livewire', $env);
        $this->assertStringContainsString('PTAH_CRUD_API=true', $env);

        $this->assertStringContainsString("from '@ptah/react'", $this->files->get($this->tmpPath.'/resources/js/ptah-app.jsx'));
        $this->assertFileExists($this->tmpPath.'/routes/ptah-screens.php');

        $vite = $this->files->get($this->tmpPath.'/vite.config.js');
        $this->assertSame(1, substr_count($vite, 'react(),'));
        $this->assertSame(1, substr_count($vite, "'@ptah'"));

        $package = json_decode($this->files->get($this->tmpPath.'/package.json'), true);
        $this->assertArrayHasKey('react', $package['devDependencies']);
        $this->assertArrayHasKey('@vitejs/plugin-react', $package['devDependencies']);
        $this->assertSame('^7.0.0', $package['devDependencies']['vite']);
    }

    #[Test]
    public function the_vue_stack_writes_its_own_entry(): void
    {
        $this->files->put($this->tmpPath.'/.env', "APP_NAME=Test\n");
        $this->files->put($this->tmpPath.'/package.json', json_encode(['private' => true, 'dependencies' => ['vue' => '^3.4.0']]));

        $this->installer('vue');

        $this->assertStringContainsString("from '@ptah/vue'", $this->files->get($this->tmpPath.'/resources/js/ptah-app.js'));

        // A version the app already pins is kept.
        $package = json_decode($this->files->get($this->tmpPath.'/package.json'), true);
        $this->assertSame('^3.4.0', $package['dependencies']['vue']);
        $this->assertArrayNotHasKey('vue', $package['devDependencies']);
        $this->assertArrayHasKey('@vitejs/plugin-vue', $package['devDependencies']);
    }

    #[Test]
    public function an_unknown_stack_is_refused(): void
    {
        $this->artisan('ptah:install', ['--stack' => 'svelte', '--skip-npm' => true])->assertFailed();
    }
}
