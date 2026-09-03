<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Errors;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * The themed error pages (403, 404, 419, 429, 500, 503).
 *
 * The old 403 was a standalone document with its own Tailwind CDN fallback and
 * a dozen hardcoded hex values, so it ignored the user's chosen theme — the
 * complaint that started this work. All six now share one shell whose colours
 * chain `var(--ptah-token, literal-fallback)`: themed when the package
 * stylesheet is loaded, readable when it is not, because an error page has to
 * survive the failure that produced it.
 *
 * The tests that matter most here are not about looks. They are about the
 * three ways a well-meaning error page does damage: stealing a host's own
 * view, answering an API with HTML, and hiding a stack trace from a developer.
 */
class ErrorPagesTest extends TestCase
{
    /**
     * Renders one error page in isolation.
     *
     * `flushState()` is not optional here: Blade keeps `@section` content on
     * the view factory for the life of the request, so a page rendered earlier
     * in the same PHP process leaks its sections into the next one. In a
     * browser each error is its own request and the problem cannot arise; in a
     * test that renders six pages in a row it silently makes assertions pass
     * or fail for the wrong reason.
     *
     * @param  array<string, mixed>  $data
     */
    private function renderError(string $code, array $data = []): string
    {
        app('view')->flushState();

        return View::make("ptah::errors.{$code}", $data)->render();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function statusProvider(): array
    {
        return [
            '403' => ['403'],
            '404' => ['404'],
            '405' => ['405'],
            '419' => ['419'],
            '429' => ['429'],
            '500' => ['500'],
            '503' => ['503'],
        ];
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function every_page_renders_with_its_code_and_a_human_sentence(string $code): void
    {
        $html = $this->renderError($code, ['errorId' => 'abc123']);

        $this->assertStringContainsString(">{$code}</p>", $html, "A pagina {$code} precisa exibir o proprio codigo.");

        // A human sentence, not the HTTP reason phrase. "Forbidden" tells a
        // person nothing they can act on.
        $this->assertStringContainsString('err-title', $html);
        $this->assertStringNotContainsString('ptah::ui.', $html, "Chave de traducao nao resolvida na pagina {$code}.");
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function every_colour_falls_back_when_the_package_stylesheet_is_absent(string $code): void
    {
        $html = $this->renderError($code, ['errorId' => null]);

        // The whole design of the shell: a token alone would render an
        // unstyled page exactly when the asset pipeline is what broke.
        preg_match_all('/var\(--ptah-[a-z-]+(?:,\s*[^)]+)?\)/', $html, $matches);

        $this->assertNotEmpty($matches[0], "A pagina {$code} nao usa nenhum token --ptah-* — nao seguiria o tema.");

        $withoutFallback = array_values(array_filter(
            $matches[0],
            static fn (string $usage): bool => ! str_contains($usage, ',')
        ));

        $this->assertSame(
            [],
            $withoutFallback,
            "Token sem fallback na pagina {$code}: se o ptah-components.css nao carregar (build ausente, pipeline quebrado — ".
            "justamente o cenario de um erro 500), essas cores ficam indefinidas.\n".
            implode("\n", $withoutFallback)
        );
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function no_page_depends_on_a_webfont_or_external_script(string $code): void
    {
        $html = $this->renderError($code, ['errorId' => null]);

        // An error page that fetches from a CDN adds one more thing that can
        // fail while the site is already failing.
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('<script', $html, "A pagina {$code} nao deve depender de JS.");
    }

    #[Test]
    public function the_500_page_prints_a_reference_only_when_there_is_one(): void
    {
        // The reference is the single actionable thing on a server-error page:
        // an id the user can hand to whoever reads the logs.
        $withId = $this->renderError('500', ['errorId' => 'deadbeef1234']);
        $this->assertStringContainsString('deadbeef1234', $withId);

        // And an empty "Reference:" line is noise, so it must disappear.
        //
        // Asserting on `class="err-ref"` and not on the bare `err-ref`: the
        // shell's inline stylesheet declares `.err-ref`, so the loose string is
        // present on every page whether the block renders or not. My first
        // version of this test asserted the loose string and failed for that
        // reason — a test can be wrong about the thing it is testing.
        $withoutId = $this->renderError('500', ['errorId' => null]);
        $this->assertStringNotContainsString('class="err-ref"', $withoutId);
        $this->assertStringContainsString('class="err-ref"', $withId);
    }

    #[Test]
    public function the_500_page_never_shows_the_exception_message(): void
    {
        // A message can carry a query, a filesystem path or a credential, and
        // whoever triggered the error is not necessarily entitled to any of it.
        $html = $this->renderError('500', [
            'errorId' => 'abc',
            'exception' => new \RuntimeException('SQLSTATE[28000] password authentication failed for user "root"'),
        ]);

        $this->assertStringNotContainsString('SQLSTATE', $html);
        $this->assertStringNotContainsString('password', $html);
    }

    #[Test]
    public function the_shell_is_marked_noindex(): void
    {
        // An error page in a search index is a permanent bad first impression.
        $html = $this->renderError('404', ['errorId' => null]);

        $this->assertStringContainsString('noindex', $html);
    }

    #[Test]
    public function every_page_translates_in_both_languages(): void
    {
        $keys = [
            'error_btn_back', 'error_btn_home', 'error_btn_reload',
            'error_404_title', 'error_404_body',
            'error_405_title', 'error_405_body',
            'error_419_title', 'error_419_body',
            'error_429_title', 'error_429_body',
            'error_500_title', 'error_500_body', 'error_500_reference',
            'error_503_title', 'error_503_body',
        ];

        foreach (['en', 'pt_BR'] as $locale) {
            $file = dirname(__DIR__, 3)."/resources/lang/{$locale}/ui.php";
            $strings = require $file;

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $strings, "Falta a chave '{$key}' em {$locale}/ui.php.");
                $this->assertNotSame('', trim((string) $strings[$key]));
            }
        }
    }

    #[Test]
    public function the_reference_shown_to_the_user_is_the_one_written_to_the_log(): void
    {
        // The property the whole reference line depends on, and the one the
        // first version of this feature got wrong: the id used to be minted in
        // the renderable, which Laravel runs AFTER report(). The user was
        // handed a code that appeared in no log line anywhere — a reference
        // that correlates with nothing is worse than no reference, because it
        // promises support a lookup that cannot succeed.
        //
        // Registering it through `buildContextUsing` moves the minting inside
        // report(), so this test can assert the two are the same string.
        $handler = $this->app->make(ExceptionHandler::class);

        if (! method_exists($handler, 'buildContextUsing')) {
            $this->markTestSkipped('Handler sem buildContextUsing nesta versao do Laravel.');
        }

        $logged = null;

        Log::shouldReceive('error')
            ->once()
            ->andReturnUsing(function (string $message, array $context) use (&$logged): void {
                $logged = $context['ptahErrorId'] ?? null;
            });

        $handler->report(new \RuntimeException('kaboom'));

        $this->assertNotNull($logged, 'O handler precisa gravar ptahErrorId no contexto do log.');

        // And the page shows exactly that id — not a fresh one.
        $html = $this->renderError('500', ['errorId' => Context::get('ptahErrorId')]);

        $this->assertSame($logged, Context::get('ptahErrorId'));
        $this->assertStringContainsString($logged, $html);
    }

    #[Test]
    public function an_unreported_exception_shows_no_reference_at_all(): void
    {
        // When nothing was logged there is no id to read back, and the page
        // must stay silent rather than invent one.
        Context::flush();

        $html = $this->renderError('500', ['errorId' => Context::get('ptahErrorId')]);

        $this->assertStringNotContainsString('class="err-ref"', $html);
    }

    #[Test]
    public function a_manifest_without_the_expected_entry_does_not_take_the_page_down(): void
    {
        // A host is free to name its stylesheet something other than
        // resources/css/app.css — renamed, split per area, a different bundler
        // layout. Its manifest is then perfectly valid and simply lacks that
        // key, and @vite throws ViteException for a missing entry.
        //
        // The first version of this shell only checked that manifest.json
        // EXISTED, so on such a host the throw happened while rendering the page
        // that exists to explain a failure — turning a tidy 500 into Laravel's
        // bare handler, the one outcome this shell must never produce.
        $manifest = public_path('build/manifest.json');
        $created = [];

        if (! is_dir(dirname($manifest))) {
            mkdir(dirname($manifest), 0777, true);
            $created[] = dirname($manifest);
        }

        $previous = is_file($manifest) ? file_get_contents($manifest) : null;

        // Valid manifest, real entries, just not the one the shell asks for.
        file_put_contents($manifest, (string) json_encode([
            'resources/css/painel.css' => ['file' => 'assets/painel-abc123.css', 'isEntry' => true],
        ]));

        try {
            $html = $this->renderError('500', ['errorId' => 'abc123']);

            $this->assertStringContainsString('>500</p>', $html);
            $this->assertStringContainsString('err-title', $html);

            // Degraded exactly as intended: no stylesheet link, and the shell's
            // own literal fallbacks carry the colours.
            $this->assertStringNotContainsString('painel-abc123.css', $html);
            $this->assertStringNotContainsString('app.css', $html);
            $this->assertStringContainsString('--err-canvas', $html);
        } finally {
            if ($previous !== null) {
                file_put_contents($manifest, $previous);
            } else {
                @unlink($manifest);
            }

            foreach (array_reverse($created) as $dir) {
                @rmdir($dir);
            }
        }
    }
}
