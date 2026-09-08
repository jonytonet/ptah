<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Errors;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * The 500, through a real request that really blew up.
 *
 * This was the hole. `ErrorPageRealRequestTest` walks 403, 404, 405, 419 and
 * 429 — every status that arrives as an `HttpException` — and the 500 is not one
 * of them: it is registered on a separate `renderable` because it is not an
 * HttpException, it is whatever broke. So the suite had two tests about the
 * 500's GUARDS (the stack trace survives while APP_DEBUG is on, a host view
 * wins) and none about the page itself. Nobody had ever asserted that a real 500
 * renders the themed shell and follows the user's theme.
 *
 * The report was "a pagina de erro 500 nao esta pegando o tema do ptah". The
 * code turned out to be right, which is exactly why this test has to exist: a
 * claim about the 500 could not be settled either way from the suite.
 *
 * And it uncovered a real design fault next door. With APP_DEBUG on the themed
 * 500 steps aside on purpose — the trace is worth more than the pretty page —
 * but that meant nobody could SEE their own error page in development without
 * turning APP_DEBUG off and a dozen other behaviours with it. A deliberate
 * behaviour that looks exactly like a bug, with nothing saying why. Hence
 * `ptah.errors.themed_500_in_debug`, covered below.
 */
class Error500RealRequestTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Production-shaped: this is the condition the themed page exists for.
        $app['config']->set('app.debug', false);
    }

    private function boom(string $uri = '/ptah-boom'): void
    {
        Route::get($uri, function () {
            throw new RuntimeException('estourou de proposito');
        });
    }

    #[Test]
    public function a_real_500_renders_the_themed_shell(): void
    {
        $this->boom();

        $response = $this->withExceptionHandling()->get('/ptah-boom');

        $response->assertStatus(500);
        $response->assertSee('err-code', false);
        $response->assertSee('>500</p>', false);
    }

    #[Test]
    public function a_real_500_follows_the_theme_from_the_cookie(): void
    {
        // Plain JSON: withCookie encrypts it with the correct prefix, which is
        // what a browser sends.
        $this->boom();

        $response = $this->withExceptionHandling()
            ->withCookie(
                AppearancePresets::COOKIE,
                (string) json_encode(['mode' => 'dark', 'dark' => 'meianoite', 'accent' => 'teal'])
            )
            ->get('/ptah-boom');

        $response->assertStatus(500);
        $response->assertSee('class="ptah-dark"', false);
        $response->assertSee('data-ptah-dark="meianoite"', false);
        $response->assertSee('data-ptah-accent="teal"', false);
    }

    #[Test]
    public function a_real_500_never_shows_what_broke(): void
    {
        // The message can carry a query, a path or a credential, and whoever
        // triggered the error is not necessarily entitled to any of it.
        $this->boom();

        $response = $this->withExceptionHandling()->get('/ptah-boom');

        $response->assertDontSee('estourou de proposito');
        $response->assertDontSee('RuntimeException');
    }

    #[Test]
    public function a_json_client_gets_no_html_page(): void
    {
        $this->boom();

        $response = $this->withExceptionHandling()
            ->getJson('/ptah-boom');

        $response->assertStatus(500);
        $response->assertDontSee('err-code', false);
    }

    #[Test]
    public function the_themed_page_can_be_seen_in_development_on_purpose(): void
    {
        // The new escape hatch. Without it, seeing your own 500 page locally
        // meant turning APP_DEBUG off globally.
        config([
            'app.debug' => true,
            'ptah.errors.themed_500_in_debug' => true,
        ]);

        $this->boom('/ptah-boom-debug');

        $response = $this->withExceptionHandling()->get('/ptah-boom-debug');

        $response->assertStatus(500);
        $response->assertSee('err-code', false);
    }

    #[Test]
    public function debug_still_keeps_the_trace_by_default(): void
    {
        // The counterpart, and the more important of the two: the escape hatch
        // must be opt-in, or a developer loses the stack trace of the thing
        // they are debugging.
        //
        // Asserted STRUCTURALLY, on the opening <html> tag alone, and that is
        // the interesting part of this test.
        //
        // Two earlier versions asserted the absence of a string — first the
        // themed shell's own CSS class, then one of its stamped attributes — and
        // both failed while the code was correct. The debug page renders a
        // source excerpt of the failing frame, so each assertion found its own
        // literal inside the response it was inspecting; the second one found it
        // in the comment explaining the first. Absence of a literal is not a
        // sound assertion whenever the response can echo source, and an error
        // page is precisely a response that can.
        //
        // The tag itself cannot be faked by an excerpt: the themed shell stamps
        // six data-ptah-* attributes on it, the debug page stamps none.
        config(['app.debug' => true]);

        $this->boom('/ptah-boom-trace');

        $html = $this->withExceptionHandling()->get('/ptah-boom-trace')->getContent();

        $this->assertIsString($html);
        $this->assertSame(
            1,
            preg_match('/<html\b[^>]*>/', $html, $tag),
            'Sem a tag <html> nao ha o que afirmar.'
        );
        $this->assertStringNotContainsString(
            'data-ptah',
            $tag[0],
            'Com APP_DEBUG ligado e sem a chave explicita, a 500 tematizada precisa ceder o lugar ao trace.'
        );
    }

    #[Test]
    public function the_other_statuses_never_depended_on_debug(): void
    {
        // They are HttpExceptions — "the request cannot have this" — not "the
        // application broke". There is no trace worth preserving, so a 404 with
        // APP_DEBUG on is still the themed page. Pinned because someone
        // extending the debug gate to the shared renderable would silently
        // unstyle five pages in development.
        config(['app.debug' => true]);

        Route::get('/ptah-nao-existe-mesmo', fn () => abort(404));

        $response = $this->withExceptionHandling()->get('/ptah-nao-existe-mesmo');

        $response->assertStatus(404);
        $response->assertSee('err-code', false);
    }
}
