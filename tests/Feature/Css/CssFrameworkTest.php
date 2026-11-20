<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Css;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ViewGenerator;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\PtahServiceProvider;
use Ptah\Support\CssFramework;
use Ptah\Support\UpgradeInspector;
use Ptah\Tests\TestCase;

/**
 * ptah.css.framework (docs/Css.md): tailwind renders what it always did;
 * bootstrap links the pre-compiled stylesheet and marks the scope.
 */
class CssFrameworkTest extends TestCase
{
    private function bootstrap(): void
    {
        config(['ptah.css.framework' => 'bootstrap']);
    }

    private function layout(string $inner = '<p class="px-3">host</p>'): string
    {
        $assets = app(FrontendAssets::class);
        $assets->hasRenderedStyles = false;
        $assets->hasRenderedScripts = false;

        return Blade::render('<x-forge-dashboard-layout title="T">'.$inner.'</x-forge-dashboard-layout>');
    }

    // ── config ─────────────────────────────────────────────────────────────

    #[Test]
    public function the_default_is_tailwind_even_for_a_config_published_before_the_key(): void
    {
        $this->assertSame('tailwind', CssFramework::current());

        $ptah = config('ptah');
        unset($ptah['css']);
        config(['ptah' => $ptah]);

        $this->assertSame('tailwind', CssFramework::current());
        $this->assertFalse(CssFramework::isBootstrap());
    }

    #[Test]
    public function bootstrap_is_read_case_insensitively_and_an_unknown_value_falls_back_to_tailwind(): void
    {
        config(['ptah.css.framework' => ' Bootstrap ']);
        $this->assertTrue(CssFramework::isBootstrap());

        config(['ptah.css.framework' => 'foundation']);
        $this->assertSame('tailwind', CssFramework::current());
    }

    #[Test]
    public function the_assets_tag_publishes_the_dist_file_to_public_vendor_ptah(): void
    {
        $paths = ServiceProvider::pathsToPublish(PtahServiceProvider::class, 'ptah-assets');

        $targets = [];
        foreach ($paths as $from => $to) {
            $targets[(string) realpath($from)] = $to;
        }

        // Only the stylesheet: the CSS sources moved to `ptah-css-sources`.
        $this->assertSame([(string) realpath(CssFramework::distPath()) => public_path('vendor/ptah/ptah.css')], $targets);
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(PtahServiceProvider::class, 'ptah-css-sources'));
    }

    // ── layouts ────────────────────────────────────────────────────────────

    #[Test]
    public function the_tailwind_layout_links_no_ptah_stylesheet_and_marks_no_scope(): void
    {
        $html = $this->layout();

        $this->assertStringNotContainsString('vendor/ptah/ptah.css', $html);
        $this->assertStringNotContainsString('data-ptah-scope', $html);
        $this->assertStringNotContainsString('data-ptah-slot', $html);
        $this->assertStringNotContainsString('data-bs-theme', $html);
        $this->assertStringContainsString('cdn.tailwindcss.com', $html, 'Sem build, o modo tailwind continua no CDN.');
    }

    #[Test]
    public function the_bootstrap_layout_links_the_stylesheet_opens_the_scope_and_fences_the_host_slot(): void
    {
        $this->bootstrap();
        $html = $this->layout();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*/vendor/ptah/ptah\.css\?v=[0-9a-f]{12}" data-ptah-css>#', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html, 'Bootstrap mode must never load Tailwind.');
        $this->assertMatchesRegularExpression('#<body data-ptah-scope class="font-sans#', $html);
        $this->assertStringContainsString('<span data-ptah-slot><p class="px-3">host</p></span>', $html);
        $this->assertStringContainsString("setAttribute('data-bs-theme', isDark ? 'dark' : 'light')", $html);
    }

    #[Test]
    public function the_bootstrap_layout_asks_vite_only_for_the_entries_the_host_build_has(): void
    {
        $this->bootstrap();
        $manifest = public_path('build/manifest.json');
        @mkdir(dirname($manifest), 0755, true);
        file_put_contents($manifest, json_encode([
            'resources/sass/app.scss' => ['file' => 'assets/app-1.css', 'src' => 'resources/sass/app.scss', 'isEntry' => true],
            'resources/js/app.js' => ['file' => 'assets/app-2.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
        ]));

        try {
            $this->assertSame(['resources/sass/app.scss', 'resources/js/app.js'], CssFramework::viteEntries());

            $html = $this->layout();
            $this->assertStringContainsString('build/assets/app-1.css', $html);
            $this->assertLessThan(strpos($html, 'build/assets/app-1.css'), strpos($html, 'vendor/ptah/ptah.css'), 'ptah.css comes before the host CSS: a host token override wins by order, as in a Tailwind host.');
        } finally {
            @unlink($manifest);
        }
    }

    #[Test]
    public function the_auth_and_error_layouts_follow_the_same_switch(): void
    {
        $auth = fn () => Blade::render('@extends(\'ptah::layouts.forge-auth\') @section(\'content\') x @endsection');

        $this->assertStringNotContainsString('vendor/ptah/ptah.css', $auth());
        $this->bootstrap();
        $html = $auth();
        $this->assertStringContainsString('vendor/ptah/ptah.css', $html);
        $this->assertStringContainsString('<body data-ptah-scope class="min-h-screen', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);

        $error = view('ptah::errors.404')->render();
        $this->assertStringContainsString('vendor/ptah/ptah.css', $error);
        $this->assertStringContainsString('<body data-ptah-scope>', $error);
    }

    // ── <x-ptah-assets /> ──────────────────────────────────────────────────

    #[Test]
    public function ptah_assets_brings_what_a_host_layout_lacks(): void
    {
        $this->bootstrap();
        $html = Blade::render('<x-ptah-assets />');

        $this->assertStringContainsString('vendor/ptah/ptah.css', $html);
        $this->assertStringContainsString('boxicons', $html);
        $this->assertStringContainsString('font-awesome', $html);
        $this->assertStringContainsString("classList.add('ptah-dark', 'dark')", $html, 'appearance boot');
        $this->assertStringContainsString("hasAttribute('data-bs-theme')", $html, 'the host theme wins');
        $this->assertStringContainsString('<template id="ptah-assets-body"><div data-ptah-scope x-data="{', $html, 'toast host, scoped');
        $this->assertStringContainsString('@ptah-toast.window', $html);

        $this->assertSame($html, Blade::render('@ptahAssets'));
    }

    #[Test]
    public function ptah_assets_prints_only_the_colours_the_host_chose_in_bootstrap_mode(): void
    {
        $this->bootstrap();
        $this->assertStringNotContainsString('--color-primary:', Blade::render('<x-ptah-assets />'), 'Stock colours yield to the Bootstrap theme.');

        config(['ptah.theme.colors.primary' => '#0d9488']);
        $html = Blade::render('<x-ptah-assets />');
        $this->assertStringContainsString('--color-primary: #0d9488;', $html);
        $this->assertStringNotContainsString('--color-success:', $html);

        // The package's own layout keeps the full palette (its look is the
        // Tailwind one).
        $this->assertStringContainsString('--color-success: #10b981;', $this->layout());
    }

    #[Test]
    public function ptah_assets_links_no_stylesheet_in_tailwind_mode(): void
    {
        $html = Blade::render('<x-ptah-assets />');

        $this->assertStringNotContainsString('vendor/ptah/ptah.css', $html);
        $this->assertStringNotContainsString('data-ptah-scope', $html);
        $this->assertStringContainsString('--color-primary: #5b21b6;', $html);
    }

    // ── scope markers ──────────────────────────────────────────────────────

    #[Test]
    public function a_forge_component_in_a_host_view_opens_a_scope_and_fences_its_slots(): void
    {
        $tag = '<x-forge-card class="mb-3"><x-slot:footer><b class="p-4">f</b></x-slot:footer><span class="px-3">s</span></x-forge-card>';

        $tailwind = Blade::render($tag);
        $this->assertStringNotContainsString('data-ptah-', $tailwind);
        $this->assertStringContainsString('<div class="p-5"><span class="px-3">s</span></div>', $tailwind);

        $this->bootstrap();
        $html = Blade::render($tag);
        $this->assertMatchesRegularExpression('#<div class="[^"]*mb-3[^"]*" data-ptah-scope="data-ptah-scope">#', $html);
        $this->assertStringContainsString('<div class="p-5"><span data-ptah-slot><span class="px-3">s</span></span></div>', $html);
        $this->assertStringContainsString('<span data-ptah-slot><b class="p-4">f</b></span>', $html);
    }

    #[Test]
    public function a_text_slot_needs_no_fence_and_a_component_whose_root_skips_attributes_still_gets_the_scope(): void
    {
        $this->bootstrap();

        $this->assertStringContainsString('<span>Save</span>', Blade::render('<x-forge-button>Save</x-forge-button>'));
        $this->assertMatchesRegularExpression('#<div data-ptah-scope class="ptah-input-wrapper#', Blade::render('<x-forge-input name="q" />'));
    }

    #[Test]
    public function the_precompiler_leaves_the_package_views_alone(): void
    {
        $value = '<x-forge-button>Ok</x-forge-button>';

        $this->assertSame($value, CssFramework::markHostComponents($value, dirname(__DIR__, 3).'/resources/views/livewire/base-crud/base-crud.blade.php'));
        $this->assertStringContainsString(':data-ptah-scope="\\Ptah\\Support\\CssFramework::isBootstrap()"', CssFramework::markHostComponents($value, base_path('resources/views/x.blade.php')));
        $this->assertSame('<x-forge-buttonx', substr(CssFramework::markHostComponents('<x-forge-buttonx', null), 0, 16));
    }

    #[Test]
    public function a_package_livewire_component_root_opens_a_scope_in_bootstrap_mode_only(): void
    {
        $params = ['model' => 'Department', 'name' => 'x'];

        $this->assertStringNotContainsString('data-ptah-scope', Livewire::test(SearchDropdown::class, $params)->html());

        $this->bootstrap();
        $this->assertMatchesRegularExpression('#^\s*<div [^>]*data-ptah-scope=""#', Livewire::test(SearchDropdown::class, $params)->html());
    }

    #[Test]
    public function upgrade_check_warns_when_the_published_stylesheet_is_missing_or_stale(): void
    {
        $published = public_path(CssFramework::PUBLIC_PATH);
        @unlink($published);

        $this->assertSame([], UpgradeInspector::stylesheet(), 'Tailwind mode has nothing to publish.');

        $this->bootstrap();

        try {
            $this->assertStringContainsString('does not exist', UpgradeInspector::stylesheet()[0]['message']);

            @mkdir(dirname($published), 0755, true);
            file_put_contents($published, "/*! ptah.css\n * inputs-hash: ".str_repeat('0', 64)."\n */");
            $this->assertStringContainsString('is not the stylesheet of the installed ptah', UpgradeInspector::stylesheet()[0]['message']);
            $this->assertSame('php artisan vendor:publish --tag=ptah-assets --force', UpgradeInspector::stylesheet()[0]['action']);

            copy(CssFramework::distPath(), $published);
            $this->assertSame([], UpgradeInspector::stylesheet());
        } finally {
            @unlink($published);
        }
    }

    #[Test]
    public function forge_scopes_the_generated_page_header_in_bootstrap_mode(): void
    {
        $stub = (string) file_get_contents(dirname(__DIR__, 3).'/src/Stubs/view.index.stub');

        $this->assertStringContainsString('<div data-ptah-scope class="flex items-center justify-between mb-6">', ViewGenerator::scopePageHeader($stub));
        $this->assertSame('<div>custom</div>', ViewGenerator::scopePageHeader('<div>custom</div>'));
    }
}
