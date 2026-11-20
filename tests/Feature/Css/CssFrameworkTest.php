<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Css;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Livewire\Livewire;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ViewGenerator;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\PtahServiceProvider;
use Ptah\Support\CssFramework;
use Ptah\Support\UpgradeInspector;
use Ptah\Tests\TestCase;
use ReflectionProperty;

/**
 * ptah.css.framework (docs/Css.md): tailwind renders what it always did;
 * bootstrap links the pre-compiled stylesheet and marks the scope.
 */
class CssFrameworkTest extends TestCase
{
    /**
     * The compile step only acts in bootstrap mode, so a string view is never
     * reused across modes here (a host runs view:clear when switching).
     */
    private function render(string $template): string
    {
        // The engine remembers, per request, a path it already compiled: the
        // mode is part of the template so each mode compiles its own.
        return Blade::render($template.'{{-- '.CssFramework::current().' --}}', [], true);
    }

    private function bootstrap(): void
    {
        config(['ptah.css.framework' => 'bootstrap']);
    }

    private function layout(string $inner = '<p class="px-3">host</p>'): string
    {
        $assets = app(FrontendAssets::class);
        $assets->hasRenderedStyles = false;
        $assets->hasRenderedScripts = false;

        return $this->render('<x-forge-dashboard-layout title="T">'.$inner.'</x-forge-dashboard-layout>');
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

        // Not published in this app: no version (see the versioning test).
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*/vendor/ptah/ptah\.css" data-ptah-css>#', $html);
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
        $auth = fn () => $this->render('@extends(\'ptah::layouts.forge-auth\') @section(\'content\') x @endsection');

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
        $html = $this->render('<x-ptah-assets />');

        $this->assertStringContainsString('vendor/ptah/ptah.css', $html);
        $this->assertStringContainsString('boxicons', $html);
        $this->assertStringContainsString('font-awesome', $html);
        $this->assertStringContainsString("classList.add('ptah-dark', 'dark')", $html, 'appearance boot');
        $this->assertStringContainsString("hasAttribute('data-bs-theme')", $html, 'the host theme wins');
        $this->assertStringContainsString('<template id="ptah-assets-body"><div data-ptah-scope x-data="{', $html, 'toast host, scoped');
        $this->assertStringContainsString('@ptah-toast.window', $html);

        $this->assertSame($html, $this->render('@ptahAssets'));
    }

    #[Test]
    public function ptah_assets_prints_only_the_colours_the_host_chose_in_bootstrap_mode(): void
    {
        $this->bootstrap();
        $this->assertStringNotContainsString('--color-primary:', $this->render('<x-ptah-assets />'), 'Stock colours yield to the Bootstrap theme.');

        config(['ptah.theme.colors.primary' => '#0d9488']);
        $html = $this->render('<x-ptah-assets />');
        $this->assertStringContainsString('--color-primary: #0d9488;', $html);
        $this->assertStringNotContainsString('--color-success:', $html);

        // The package's own layout keeps the full palette (its look is the
        // Tailwind one).
        $this->assertStringContainsString('--color-success: #10b981;', $this->layout());
    }

    #[Test]
    public function ptah_assets_links_no_stylesheet_in_tailwind_mode(): void
    {
        $html = $this->render('<x-ptah-assets />');

        $this->assertStringNotContainsString('vendor/ptah/ptah.css', $html);
        $this->assertStringNotContainsString('data-ptah-scope', $html);
        $this->assertStringContainsString('--color-primary: #5b21b6;', $html);
    }

    // ── scope markers ──────────────────────────────────────────────────────

    #[Test]
    public function a_forge_component_in_a_host_view_opens_a_scope_and_fences_its_slots(): void
    {
        $tag = '<x-forge-card class="mb-3"><x-slot:footer><b class="p-4">f</b></x-slot:footer><span class="px-3">s</span></x-forge-card>';

        $tailwind = $this->render($tag);
        $this->assertStringNotContainsString('data-ptah-', $tailwind);
        $this->assertStringContainsString('<div class="p-5"><span class="px-3">s</span></div>', $tailwind);

        $this->bootstrap();
        $html = $this->render($tag);
        $this->assertMatchesRegularExpression('#<div class="[^"]*mb-3[^"]*" data-ptah-scope="data-ptah-scope">#', $html);
        $this->assertStringContainsString('<div class="p-5"><span data-ptah-slot><span class="px-3">s</span></span></div>', $html);
        $this->assertStringContainsString('<span data-ptah-slot><b class="p-4">f</b></span>', $html);
    }

    #[Test]
    public function a_text_slot_needs_no_fence_and_a_component_whose_root_skips_attributes_still_gets_the_scope(): void
    {
        $this->bootstrap();

        $this->assertStringContainsString('<span>Save</span>', $this->render('<x-forge-button>Save</x-forge-button>'));
        $this->assertMatchesRegularExpression('#<div data-ptah-scope class="ptah-input-wrapper#', $this->render('<x-forge-input name="q" />'));
    }

    #[Test]
    public function the_compile_step_marks_only_package_components_in_host_views_in_bootstrap_mode(): void
    {
        $value = '<x-forge-button>Ok</x-forge-button>';
        $host = base_path('resources/views/x.blade.php');
        $marker = ':data-ptah-scope="\\Ptah\\Support\\CssFramework::isBootstrap()"';

        $this->assertSame($value, CssFramework::markHostComponents($value, $host), 'Tailwind mode compiles host views untouched.');

        $this->bootstrap();
        $this->assertSame($value, CssFramework::markHostComponents($value, dirname(__DIR__, 3).'/resources/views/livewire/base-crud/base-crud.blade.php'));
        $this->assertStringContainsString($marker, CssFramework::markHostComponents($value, $host));
        $this->assertSame('<x-forge-buttonx', substr(CssFramework::markHostComponents('<x-forge-buttonx', null), 0, 16));

        // A host component of its own named forge-* is not the package's.
        $this->assertSame('<x-forge-kpi value="1" />', CssFramework::markHostComponents('<x-forge-kpi value="1" />', $host));

        // @verbatim and Blade comments are copied as written.
        $verbatim = '@verbatim <x-forge-button>raw</x-forge-button> @endverbatim {{-- <x-forge-card> --}} <x-forge-card>c</x-forge-card>';
        $marked = CssFramework::markHostComponents($verbatim, $host);
        $this->assertStringContainsString('@verbatim <x-forge-button>raw</x-forge-button> @endverbatim {{-- <x-forge-card> --}}', $marked);
        $this->assertSame(1, substr_count($marked, $marker));
    }

    #[Test]
    public function verbatim_output_is_the_same_in_both_modes(): void
    {
        $template = '@verbatim<x-forge-button>raw</x-forge-button>@endverbatim';

        $this->assertSame('<x-forge-button>raw</x-forge-button>', $this->render($template));
        $this->bootstrap();
        $this->assertSame('<x-forge-button>raw</x-forge-button>', $this->render($template));
    }

    #[Test]
    public function the_pagination_view_opens_a_scope_when_a_host_view_renders_it(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 30, 10, 2, ['path' => '/x']);

        $this->assertStringNotContainsString('data-ptah-scope', (string) $paginator->links('ptah::components.forge-pagination'));

        $this->bootstrap();
        $this->assertMatchesRegularExpression('#<nav data-ptah-scope aria-label=#', (string) $paginator->links('ptah::components.forge-pagination'));
    }

    #[Test]
    public function the_dashboard_widgets_open_a_scope_and_use_the_ink_tone(): void
    {
        $widgets = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/dashboard/widgets.blade.php');

        // Included by hosts anywhere — also inside a fenced slot.
        $this->assertStringContainsString('<div{{ \Ptah\Support\CssFramework::scopeAttribute() }} class="grid', $widgets);
        // --ptah-primary as text measured 2.72:1 on the dark surface.
        $this->assertStringContainsString("'primary' => 'var(--ptah-primary-strong)'", $widgets);
        $this->assertStringNotContainsString("'var(--ptah-primary)'", $widgets);
    }

    #[Test]
    public function a_tab_shows_a_focus_ring_to_the_keyboard(): void
    {
        $this->assertStringContainsString('focus-visible:ring-2', $this->render('<x-forge-tab>A</x-forge-tab>'));
    }

    #[Test]
    public function the_link_is_versioned_by_the_published_file_and_a_stale_copy_is_logged(): void
    {
        $published = public_path(CssFramework::PUBLIC_PATH);
        @mkdir(dirname($published), 0755, true);
        (new ReflectionProperty(CssFramework::class, 'staleLogged'))->setValue(null, false);

        try {
            file_put_contents($published, "/*! ptah.css\n * inputs-hash: ".str_repeat('a', 64)."\n */");
            Log::shouldReceive('warning')->once()->withArgs(fn (string $m): bool => str_contains($m, 'vendor:publish --tag=ptah-assets'));

            $this->assertStringEndsWith('ptah.css?v=aaaaaaaaaaaa', CssFramework::stylesheetUrl(), 'The version of the content served, not of the package.');
            CssFramework::stylesheetUrl();

            copy(CssFramework::distPath(), $published);
            $this->assertStringEndsWith('?v='.substr(CssFramework::distHash(), 0, 12), CssFramework::stylesheetUrl());
        } finally {
            @unlink($published);
            (new ReflectionProperty(CssFramework::class, 'staleLogged'))->setValue(null, false);
        }
    }

    #[Test]
    public function the_documented_boundaries_stay_documented(): void
    {
        $doc = (string) file_get_contents(dirname(__DIR__, 3).'/docs/Css.md');

        // Bootstrap in a cascade layer breaks the isolation; there is no fallback without @scope.
        $this->assertStringContainsString('**Bootstrap imported into a cascade layer**', $doc);
        $this->assertStringContainsString("'CSSScopeRule' in window", $doc);
        $this->assertStringContainsString('## 5. Migrating a Tailwind host\'s theme', $doc);
        $this->assertStringContainsString('## 6. Troubleshooting', $doc);
    }

    #[Test]
    public function the_stylesheet_tag_warns_in_browsers_without_css_scope(): void
    {
        $this->bootstrap();

        $this->assertStringContainsString("if (!('CSSScopeRule' in window)) { console.warn(", (string) CssFramework::stylesheetTag());
    }

    #[Test]
    public function a_host_subclass_rendering_a_view_of_its_own_gets_no_scope(): void
    {
        $dir = sys_get_temp_dir().'/ptah-host-views-'.uniqid();
        @mkdir($dir);
        file_put_contents($dir.'/host-dropdown.blade.php', '<div class="host">own</div>');
        $this->app['view']->addLocation($dir);
        $this->bootstrap();

        $component = new class extends SearchDropdown
        {
            public function render(): View
            {
                return view('host-dropdown');
            }
        };

        try {
            $html = Livewire::test($component::class, ['model' => 'Department', 'name' => 'x'])->html();
            $this->assertStringContainsString('own', $html);
            $this->assertStringNotContainsString('data-ptah-scope', $html);
        } finally {
            @unlink($dir.'/host-dropdown.blade.php');
            @rmdir($dir);
        }
    }

    #[Test]
    public function upgrade_check_lists_host_views_that_predate_bootstrap_mode(): void
    {
        $views = sys_get_temp_dir().'/ptah-views-'.uniqid();
        @mkdir($views.'/cliente', 0755, true);
        file_put_contents($views.'/cliente/index.blade.php', '<div class="flex"><h1 class="ptah-page-title">C</h1></div>');
        file_put_contents($views.'/cliente/ok.blade.php', '<div data-ptah-scope><h1 class="ptah-page-title">C</h1></div>');
        $components = resource_path('views/vendor/ptah/components');
        @mkdir($components, 0755, true);
        file_put_contents($components.'/forge-card.blade.php', '<div {{ $attributes }}>{{ $slot }}</div>');
        $lang = lang_path('vendor/ptah/pt_BR');
        @mkdir($lang, 0755, true);
        file_put_contents($lang.'/ui.php', "<?php return ['x' => '<span class=\"bg-indigo-100 px-3\">x</span>'];");
        config(['ptah.paths.views' => $views]);

        try {
            $this->assertSame([], UpgradeInspector::bootstrapViews(), 'Tailwind mode: nothing to say.');

            $this->bootstrap();
            $messages = implode("\n", array_column(UpgradeInspector::bootstrapViews(), 'message'));

            $this->assertStringContainsString('forge-card.blade.php', $messages);
            $this->assertStringContainsString('cliente/index.blade.php', $messages);
            $this->assertStringNotContainsString('cliente/ok.blade.php', $messages);
            $this->assertStringContainsString('bg-indigo-100', $messages);
            $this->assertStringNotContainsString('px-3,', $messages);
        } finally {
            (new Filesystem)->deleteDirectory($views);
            @unlink($components.'/forge-card.blade.php');
            @unlink($lang.'/ui.php');
        }
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
