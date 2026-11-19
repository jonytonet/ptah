<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\PtahServiceProvider;
use Ptah\Support\SpaNavigation;
use Ptah\Tests\TestCase;

/**
 * The Livewire layout's single-page navigation (`ptah.forge.spa_navigation`).
 *
 * Opt-in, off by default: production hosts have their own pages and scripts
 * that may assume a document load. So the two halves pinned here matter as
 * much as each other — with the mode OFF the layout renders exactly what it
 * rendered before the mode existed; with it ON only same-origin, same-window
 * links get `data-ptah-navigate`, and logout/external/`_blank` never do.
 */
class SpaNavigationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.modules.auth' => true, 'ptah.menu.driver' => 'config']);
        if (! Route::has('ptah.auth.logout')) {
            Route::post('/admin/logout', fn () => '')->name('ptah.auth.logout');
        }
        if (! Route::has('ptah.profile')) {
            Route::get('/admin/profile', fn () => '')->name('ptah.profile');
        }
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'Pessoa']));

        config(['ptah.forge.sidebar_items' => [
            ['label' => 'Inicio', 'url' => '/inicio', 'icon' => 'bx bx-home', 'type' => 'menuLink'],
            ['label' => 'Fora', 'url' => 'https://example.org/fora', 'icon' => 'bx bx-link', 'type' => 'menuLink'],
            ['label' => 'Nova aba', 'url' => '/nova-aba', 'icon' => 'bx bx-link', 'type' => 'menuLink', 'target' => '_blank'],
            ['label' => 'Legado', 'url' => '/legado', 'icon' => 'bx bx-link', 'type' => 'menuLink', 'spa' => false],
            ['label' => 'Grupo', 'icon' => 'bx bx-cube', 'type' => 'menuGroup', 'children' => [
                ['label' => 'Filho', 'url' => '/grupo/filho'],
                ['label' => 'Relatorio', 'url' => '/relatorios/vendas'],
            ]],
        ]]);
    }

    private function layout(): string
    {
        // Livewire prints its styles/scripts once per request; two renders in
        // one test would differ by that alone.
        $assets = app(FrontendAssets::class);
        $assets->hasRenderedStyles = false;
        $assets->hasRenderedScripts = false;

        $html = Blade::render(
            '<x-forge-dashboard-layout title="T">'
            .'<x-forge-page-header title="P" back="/inicio" />'
            .'<x-forge-breadcrumb :items="[[\'url\' => \'/inicio\', \'label\' => \'Inicio\'], [\'label\' => \'Aqui\']]" />'
            .'</x-forge-dashboard-layout>'
        );

        // What changes per render on its own (snapshot ids, checksums, CSRF).
        return (string) preg_replace(
            ['/wire:snapshot="[^"]*"/', '/wire:id="[^"]*"/', '/wire:effects="[^"]*"/', '/content="[A-Za-z0-9]{40}"/', '/name="_token" value="[^"]*"/', '/livewire-[0-9a-f]+/', '/data-csrf="[^"]*"/', '/\?id=[0-9a-f]+/'],
            'X',
            $html
        );
    }

    /** The opening tag of the first `<a>` whose href is exactly $href. */
    private function anchor(string $html, string $href): string
    {
        $pos = strpos($html, 'href="'.$href.'"');
        $this->assertIsInt($pos, "Nenhum link para {$href}.");
        $start = strrpos(substr($html, 0, $pos), '<');
        $end = strpos($html, '>', $pos);

        return substr($html, (int) $start, (int) $end - (int) $start + 1);
    }

    #[Test]
    public function off_by_default_and_the_layout_carries_nothing_of_it(): void
    {
        $this->assertFalse(SpaNavigation::enabled());

        $html = $this->layout();

        foreach (['wire:navigate', 'data-ptah-navigate', 'x-persist', 'data-ptah-spa', 'ptah-build', 'window.ptahSpa', 'data-ptah-group', 'data-ptah-swapping'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "Com o modo desligado o layout nao pode carregar [{$needle}].");
        }
    }

    #[Test]
    public function off_renders_the_same_html_as_a_config_published_before_the_key_existed(): void
    {
        // mergeConfigFrom() is shallow: a host's published `forge` block has no
        // `spa_navigation`. Absent and false must be the same page, byte for byte.
        config(['ptah.forge.spa_navigation' => false]);
        $false = $this->layout();

        $forge = config('ptah.forge');
        unset($forge['spa_navigation'], $forge['spa_navigation_except']);
        config(['ptah.forge' => $forge]);

        $this->assertSame($false, $this->layout());
    }

    #[Test]
    public function on_internal_links_navigate_in_place(): void
    {
        config(['ptah.forge.spa_navigation' => true]);
        $html = $this->layout();

        foreach (['/inicio', '/grupo/filho', route('ptah.profile'), '/'] as $href) {
            $this->assertStringContainsString(SpaNavigation::ATTRIBUTE, $this->anchor($html, $href), "{$href} devia navegar no lugar.");
        }

        // Page header back button and breadcrumb (both point at /inicio).
        $this->assertMatchesRegularExpression('#<a href="/inicio" data-ptah-navigate\s+class="ptah-c-phdr_back#', $html);
        $this->assertMatchesRegularExpression('#<a href="/inicio" data-ptah-navigate class="ptah-c-crumb"#', $html);

        // Never Livewire's own attribute: its prefetch cache races (see
        // SpaNavigation::ATTRIBUTE) — the layout navigates through
        // Livewire.navigate() from one delegated click listener instead.
        $this->assertDoesNotMatchRegularExpression('/\swire:navigate[\s.=>]/', $html);
        $this->assertStringContainsString("closest('a[data-ptah-navigate]')", $html);
    }

    #[Test]
    public function on_external_blank_opted_out_and_excepted_links_stay_full_loads(): void
    {
        config(['ptah.forge.spa_navigation' => true, 'ptah.forge.spa_navigation_except' => ['relatorios/*']]);
        $html = $this->layout();

        foreach (['https://example.org/fora', '/nova-aba', '/legado', '/relatorios/vendas'] as $href) {
            $this->assertStringNotContainsString(SpaNavigation::ATTRIBUTE, $this->anchor($html, $href), "{$href} tem de continuar com carga completa.");
        }

        // Logout is a POST form: never a navigate link.
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*data-ptah-navigate/', $html);
        $this->assertStringContainsString('action="'.route('ptah.auth.logout').'"', $html);
    }

    #[Test]
    public function on_the_frame_is_wired_for_in_place_swaps(): void
    {
        config(['ptah.forge.spa_navigation' => true]);
        $html = $this->layout();

        $this->assertStringContainsString('data-ptah-spa', $html);
        $this->assertStringContainsString('<meta name="ptah-build"', $html);
        $this->assertStringContainsString('x-persist="ptah-toasts"', $html);
        $this->assertStringContainsString('window.ptahSpa', $html);
        $this->assertStringContainsString('data-ptah-group="Grupo"', $html);

        // The frame script is the FIRST thing in <body>: it re-applies the theme
        // before any other body script or Alpine can read a style.
        $body = substr($html, (int) strpos($html, '<body'));
        $this->assertLessThan(strpos($body, 'ptah-sidebar'), strpos($body, 'window.ptahSpa'));

        // The jump box carries the per-link decision for its JS navigation.
        $this->assertStringContainsString('window.Livewire.navigate(it.url)', $html);
        $this->assertStringContainsString('\u0022spa\u0022:true', $html);
        $this->assertStringContainsString('\u0022spa\u0022:false', $html);
    }

    #[Test]
    public function the_ai_chat_is_persisted_only_when_on(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/components/forge-dashboard-layout.blade.php');

        $this->assertMatchesRegularExpression("/@if\\(\\\$ptahSpa\\)@persist\\('ptah-ai-chat'\\)@endif<livewire:ptah-ai-chat-widget \\/>/", $source);
    }

    #[Test]
    public function the_ai_widget_root_tag_has_no_blade_conditional(): void
    {
        // Found in PetPlace: inside a Livewire component @if gets morph markers
        // (<!--[if BLOCK]-->); in the middle of the root tag they swallowed its
        // `class`, and the chat (no longer `fixed`) fell to the page footer.
        $source = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/ai/ai-chat-widget.blade.php');
        $start = strpos($source, "<div\n    x-data=\"{");
        $end = strpos($source, 'class="fixed bottom-6 right-6');
        $this->assertIsInt($start);
        $this->assertIsInt($end);

        $tagTail = substr($source, (int) strrpos(substr($source, 0, $end), '}"'), $end - (int) strrpos(substr($source, 0, $end), '}"'));
        $this->assertStringNotContainsString('@if', $tagTail);
        $this->assertStringContainsString('SpaNavigation::enabled()', $tagTail);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function refused(): array
    {
        return [
            'empty' => ['', null],
            'null' => [null, null],
            'fragment' => ['#secao', null],
            'javascript' => ['javascript:alert(1)', null],
            'javascript with a tab' => ["java\tscript:alert(1)", null],
            'mailto' => ['mailto:a@b.c', null],
            'tel' => ['tel:+5511999999999', null],
            'data' => ['data:text/html,x', null],
            'other host' => ['http://example.org/x', null],
            'protocol-relative other host' => ['//example.org/x', null],
            'other scheme same host' => ['https://localhost/x', null],
            'other port' => ['http://localhost:8081/x', null],
            'target blank' => ['/x', '_blank'],
            'named target' => ['/x', 'janela'],
        ];
    }

    #[Test]
    #[DataProvider('refused')]
    public function the_helper_refuses(?string $url, ?string $target): void
    {
        config(['ptah.forge.spa_navigation' => true]);

        $this->assertSame('', SpaNavigation::attr($url, $target));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function accepted(): array
    {
        return [
            'absolute path' => ['/clientes', null],
            'relative path' => ['clientes', null],
            'query only' => ['?page=2', null],
            'same origin absolute' => ['http://localhost/clientes', null],
            'protocol-relative same host' => ['//localhost/clientes', null],
            'target self' => ['/clientes', '_self'],
            'empty target' => ['/clientes', ''],
        ];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function the_helper_accepts(string $url, ?string $target): void
    {
        config(['ptah.forge.spa_navigation' => true]);

        $this->assertSame(' '.SpaNavigation::ATTRIBUTE, SpaNavigation::attr($url, $target));
    }

    #[Test]
    public function the_helper_says_nothing_when_off_or_opted_out_or_excepted(): void
    {
        $this->assertSame('', SpaNavigation::attr('/clientes'));

        config(['ptah.forge.spa_navigation' => true, 'ptah.forge.spa_navigation_except' => ['legado', 'relatorios/*']]);

        $this->assertSame('', SpaNavigation::attr('/clientes', null, false));
        $this->assertSame('', SpaNavigation::attr('/legado'));
        $this->assertSame('', SpaNavigation::attr('/relatorios/vendas?x=1'));
        $this->assertSame(' '.SpaNavigation::ATTRIBUTE, SpaNavigation::attr('/relatorios'));
        $this->assertFalse(SpaNavigation::allowsMenuItem(['url' => '/x', 'spa' => false]));
        $this->assertFalse(SpaNavigation::allowsMenuItem(['url' => '/x', 'spa' => '0']));
        $this->assertTrue(SpaNavigation::allowsMenuItem(['url' => '/x', 'spa' => true]));
        $this->assertTrue(SpaNavigation::allowsMenuItem(['url' => '/x']));
    }

    #[Test]
    public function the_blade_directive_prints_the_same_rule(): void
    {
        $this->assertSame('<a href="/x">', Blade::render('<a href="/x"@ptahNavigate(\'/x\')>'));

        config(['ptah.forge.spa_navigation' => true]);
        $this->assertSame('<a href="/x" data-ptah-navigate>', Blade::render('<a href="/x"@ptahNavigate(\'/x\')>'));
        $this->assertSame('<a href="https://example.org">', Blade::render('<a href="https://example.org"@ptahNavigate(\'https://example.org\')>'));
    }

    #[Test]
    public function the_env_switch_reaches_a_config_published_before_the_key_existed(): void
    {
        // Found in PetPlace: its config/ptah.php has a `forge` block from an
        // older release, the shallow merge never brought `spa_navigation` in, and
        // PTAH_SPA_NAVIGATION=true did nothing.
        config(['ptah.forge' => ['sidebar_items' => [['label' => 'Do host', 'url' => '/x']]]]);
        putenv('PTAH_SPA_NAVIGATION=true');
        $_ENV['PTAH_SPA_NAVIGATION'] = $_SERVER['PTAH_SPA_NAVIGATION'] = 'true';

        try {
            $provider = new PtahServiceProvider($this->app);
            (fn () => $this->fillMissingNestedConfig('forge', ['spa_navigation', 'spa_navigation_except']))->call($provider);
        } finally {
            putenv('PTAH_SPA_NAVIGATION');
            unset($_ENV['PTAH_SPA_NAVIGATION'], $_SERVER['PTAH_SPA_NAVIGATION']);
        }

        $this->assertTrue(config('ptah.forge.spa_navigation'));
        $this->assertSame([], config('ptah.forge.spa_navigation_except'));
        $this->assertTrue(SpaNavigation::enabled());
        // The host's own keys are untouched.
        $this->assertSame([['label' => 'Do host', 'url' => '/x']], config('ptah.forge.sidebar_items'));
    }

    #[Test]
    public function the_config_key_is_read_with_a_default_and_documented(): void
    {
        $root = dirname(__DIR__, 3);
        $config = (string) file_get_contents($root.'/config/ptah.php');
        $this->assertStringContainsString("'spa_navigation' => (bool) env('PTAH_SPA_NAVIGATION', false)", $config);

        $docs = (string) file_get_contents($root.'/docs/Configuration.md');
        $this->assertStringContainsString('| `forge.spa_navigation` | `PTAH_SPA_NAVIGATION` | `false` |', $docs);
        $this->assertStringContainsString('| `forge.spa_navigation_except` |', $docs);

        $source = (string) file_get_contents($root.'/src/Support/SpaNavigation.php');
        $this->assertStringContainsString("config('ptah.forge.spa_navigation', false)", $source);
    }
}
