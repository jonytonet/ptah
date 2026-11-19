<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\SpaAudit;
use Ptah\Support\SpaNavigation;
use Ptah\Support\UpgradeInspector;
use Ptah\Tests\TestCase;

/**
 * What a production host asked for after turning the single-page navigation
 * on (1.59.1): a route that marks itself as a document load, `navigate` on
 * the link button, an audit of the project's own scripts, and a word from
 * `ptah:upgrade-check` when composer.json pins an exact version.
 */
class SpaHostToolsTest extends TestCase
{
    private string $tmp;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.forge.spa_navigation' => true]);
        $this->files = new Filesystem;
        $this->tmp = sys_get_temp_dir().'/ptah-spa-'.uniqid();
        $this->files->ensureDirectoryExists($this->tmp.'/resources/views/help');
        $this->files->ensureDirectoryExists($this->tmp.'/resources/js');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmp);

        parent::tearDown();
    }

    // ── ptah.full-load ──────────────────────────────────────────────────────

    #[Test]
    public function a_route_marked_full_load_is_not_navigated_in_place(): void
    {
        Route::get('pdv', fn () => '')->middleware(['web', 'ptah.full-load']);
        Route::get('pdv/{id}/cupom', fn () => '')->middleware('ptah.full-load');
        Route::get('produtos', fn () => '')->middleware('web');

        $this->assertFalse(SpaNavigation::allows('/pdv'));
        $this->assertFalse(SpaNavigation::allows(url('/pdv').'?caixa=2'));
        $this->assertFalse(SpaNavigation::allows('/pdv/7/cupom'));
        $this->assertSame('', SpaNavigation::attr('/pdv'));

        // An ordinary route, and a path no route answers, keep navigating.
        $this->assertTrue(SpaNavigation::allows('/produtos'));
        $this->assertTrue(SpaNavigation::allows('/nao-existe'));
        $this->assertSame(' '.SpaNavigation::ATTRIBUTE, SpaNavigation::attr('/produtos'));
    }

    #[Test]
    public function the_menu_follows_the_mark_of_the_route(): void
    {
        Route::get('painel-tv', fn () => '')->middleware('ptah.full-load');

        $this->assertFalse(SpaNavigation::allowsMenuItem(['url' => '/painel-tv']));
        $this->assertTrue(SpaNavigation::allowsMenuItem(['url' => '/clientes']));
    }

    // ── <x-forge-button href navigate> ──────────────────────────────────────

    #[Test]
    public function the_link_button_navigates_in_place_when_asked_and_the_link_qualifies(): void
    {
        Route::get('pdv', fn () => '')->middleware('ptah.full-load');
        $render = fn (string $tag): string => Blade::render($tag);
        $attr = SpaNavigation::ATTRIBUTE;

        $this->assertStringContainsString($attr, $render('<x-forge-button href="/clientes" navigate>Ir</x-forge-button>'));
        // Not asked: a plain link, as before.
        $this->assertStringNotContainsString($attr, $render('<x-forge-button href="/clientes">Ir</x-forge-button>'));
        // Asked, but the link does not qualify.
        $this->assertStringNotContainsString($attr, $render('<x-forge-button href="/pdv" navigate>PDV</x-forge-button>'));
        $this->assertStringNotContainsString($attr, $render('<x-forge-button href="/clientes" target="_blank" navigate>Ir</x-forge-button>'));
        $this->assertStringNotContainsString($attr, $render('<x-forge-button href="https://example.org/x" navigate>Fora</x-forge-button>'));
        $this->assertStringNotContainsString($attr, $render('<x-forge-button href="/clientes" navigate disabled>Ir</x-forge-button>'));
        // A button without href has nowhere to go.
        $this->assertStringNotContainsString($attr, $render('<x-forge-button navigate>Salvar</x-forge-button>'));

        config(['ptah.forge.spa_navigation' => false]);
        $this->assertStringNotContainsString($attr, $render('<x-forge-button href="/clientes" navigate>Ir</x-forge-button>'));
    }

    // ── ptah:spa-audit ──────────────────────────────────────────────────────

    private function project(): void
    {
        $this->files->put($this->tmp.'/resources/views/layout.blade.php', implode("\n", [
            '<script>',
            "document.addEventListener('DOMContentLoaded', () => boot());",
            "window.addEventListener('keydown', onKey);",
            "document.addEventListener('livewire:navigated', () => boot());",
            'setInterval(tick, 1000);',
            '</script>',
            '<a href="/x" wire:navigate>X</a>',
        ]));
        $this->files->put($this->tmp.'/resources/views/help/index.blade.php', "<script>mermaid.initialize({ startOnLoad: true });</script>\n");
        $this->files->put($this->tmp.'/resources/js/app.js', "window.onload = () => start();\nwindow.addEventListener('resize', fit);\n");
        // Never read: dependencies and ptah's own published views.
        $this->files->ensureDirectoryExists($this->tmp.'/resources/views/vendor/ptah');
        $this->files->put($this->tmp.'/resources/views/vendor/ptah/x.blade.php', "document.addEventListener('DOMContentLoaded', x);");
        $this->files->ensureDirectoryExists($this->tmp.'/resources/js/node_modules/lib');
        $this->files->put($this->tmp.'/resources/js/node_modules/lib/i.js', "document.addEventListener('DOMContentLoaded', x);");
    }

    #[Test]
    public function the_audit_lists_each_place_that_assumes_a_document_load_with_file_and_line(): void
    {
        $this->project();

        $found = array_map(
            fn (array $f) => "{$f['rule']} {$f['file']}:{$f['line']}",
            SpaAudit::scan([$this->tmp.'/resources/views', $this->tmp.'/resources/js'], $this->tmp)
        );

        $this->assertSame([
            'dom-content-loaded resources/views/layout.blade.php:2',
            'global-listener-in-view resources/views/layout.blade.php:2',
            // Line 4 listens to the navigation itself: the fix, not a finding.
            'global-listener-in-view resources/views/layout.blade.php:3',
            'interval-in-view resources/views/layout.blade.php:5',
            'start-on-load resources/views/help/index.blade.php:1',
            'window-onload resources/js/app.js:1',
            'wire-navigate resources/views/layout.blade.php:7',
        ], $found);
    }

    #[Test]
    public function the_command_prints_them_and_strict_fails_only_when_there_is_something(): void
    {
        $this->project();
        $this->app->setBasePath($this->tmp);

        $this->artisan('ptah:spa-audit')
            ->expectsOutputToContain('resources/views/layout.blade.php:7')
            ->expectsOutputToContain('7 place(s) to look at')
            ->assertSuccessful();
        $this->artisan('ptah:spa-audit', ['--strict' => true])->assertFailed();

        $this->files->deleteDirectory($this->tmp.'/resources');
        $this->artisan('ptah:spa-audit', ['--strict' => true])->assertSuccessful();
    }

    // ── ptah:upgrade-check: an exact version in composer.json ───────────────

    /** @return array<string, array{0: string, 1: bool}> */
    public static function constraints(): array
    {
        return [
            'exact' => ['1.58.6', true],
            'exact with v' => ['v1.58.6', true],
            'exact with =' => ['=1.58.6', true],
            'caret' => ['^1.58', false],
            'tilde' => ['~1.58.0', false],
            'range' => ['>=1.58 <2.0', false],
            'wildcard' => ['1.58.*', false],
            'branch' => ['dev-main', false],
        ];
    }

    #[Test]
    #[DataProvider('constraints')]
    public function upgrade_check_says_when_composer_pins_an_exact_version(string $constraint, bool $pinned): void
    {
        $this->app->setBasePath($this->tmp);
        $this->files->put($this->tmp.'/composer.json', (string) json_encode(['require' => ['jonytonet/ptah' => $constraint]]));

        $found = UpgradeInspector::composerConstraint();

        $this->assertCount($pinned ? 1 : 0, $found);
        if ($pinned) {
            $this->assertSame('composer', $found[0]['check']);
            $this->assertStringContainsString('"'.$constraint.'"', $found[0]['message']);
            $this->assertStringContainsString('composer require jonytonet/ptah:^', $found[0]['action']);
        }
    }

    #[Test]
    public function upgrade_check_is_silent_without_a_composer_file(): void
    {
        $this->app->setBasePath($this->tmp);

        $this->assertSame([], UpgradeInspector::composerConstraint());
    }
}
