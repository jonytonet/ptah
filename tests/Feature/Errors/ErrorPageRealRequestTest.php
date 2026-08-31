<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Errors;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Walks every themed status through a REAL request instead of rendering the
 * view, because the two are not equivalent and the difference shipped a bug.
 *
 * `View::make('ptah::errors.404')->render()` runs inside a fully booted
 * request: the session has started and `EncryptCookies` has already decrypted
 * every cookie in place. A real 404 has neither — an unmatched URI never
 * enters the `web` middleware group. So the appearance tests that rendered the
 * view all passed while the actual 404 in the browser ignored the user's dark
 * theme, which is how 1.29.1 shipped with the 403 themed and the 404 not.
 *
 * The lesson is the shape of this file: for anything that depends on where in
 * the middleware stack a page renders, only a request through the real path is
 * evidence. Each status below is triggered the way the framework itself would.
 */
class ErrorPageRealRequestTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.errors.enabled', true);
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('app.debug', false);
    }

    /**
     * Each case aborts with one status from a route registered OUTSIDE any
     * group, which is the harsher of the two environments: no session, and a
     * cookie still encrypted. A status that works here works inside the group
     * too.
     *
     * @return array<string, array{0: int}>
     */
    public static function statusProvider(): array
    {
        return [
            '403' => [403],
            '404' => [404],
            '405' => [405],
            '419' => [419],
            '429' => [429],
            // 503 is absent on purpose — see
            // `a_host_view_wins_over_the_package_page` below. Testbench's
            // skeleton ships resources/views/errors/503.blade.php, so the
            // package correctly steps aside and there is nothing to assert
            // about the themed shell here.
        ];
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function every_status_renders_the_themed_shell_and_honours_the_theme(int $status): void
    {
        Route::get("/ptah-abort-{$status}", function () use ($status) {
            throw new HttpException($status);
        });

        $response = $this
            // Plain JSON: withCookie encrypts it with the correct prefix, which
            // is exactly what a browser sends.
            ->withCookie(
                AppearancePresets::COOKIE,
                (string) json_encode(['mode' => 'dark', 'dark' => 'meianoite', 'accent' => 'teal'])
            )
            ->get("/ptah-abort-{$status}");

        $response->assertStatus($status);

        // The themed shell, not Laravel's default page.
        $response->assertSee('err-code', false);
        $response->assertSee(">{$status}</p>", false);

        // And the user's chosen appearance, resolved with no session and an
        // encrypted cookie — the condition the 404 failed under.
        $response->assertSee('class="ptah-dark"', false);
        $response->assertSee('data-ptah-dark="meianoite"', false);
        $response->assertSee('data-ptah-accent="teal"', false);
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function no_status_answers_a_json_request_with_html(int $status): void
    {
        // An API client asking for JSON must never receive a themed page, at any
        // status. This is checked per status because the gate lives in the
        // renderable, and a new status added to the list without it would be
        // silently wrong.
        Route::get("/ptah-abort-json-{$status}", function () use ($status) {
            throw new HttpException($status);
        });

        $response = $this->getJson("/ptah-abort-json-{$status}");

        $response->assertStatus($status);
        $this->assertStringNotContainsString('err-code', (string) $response->getContent());
    }

    #[Test]
    public function a_500_keeps_the_stack_trace_while_app_debug_is_on(): void
    {
        // The one page that must step aside for a developer. Registering it and
        // then hiding the trace behind a friendly screen would be worse than
        // having no page at all.
        config(['app.debug' => true]);

        Route::get('/ptah-boom-debug', function () {
            throw new \RuntimeException('kaboom-marker');
        });

        $response = $this->get('/ptah-boom-debug');

        // Asserting on the MESSAGE, not on the absence of the shell's markup:
        // the debug page renders source snippets and can legitimately contain
        // any string from the codebase, `err-code` included (my first version of
        // this assertion failed for exactly that reason). What actually matters
        // is the property the guard exists for — the developer still sees what
        // blew up, which the themed 500 deliberately never shows.
        $this->assertStringContainsString('kaboom-marker', (string) $response->getContent());
    }

    #[Test]
    public function a_host_view_wins_over_the_package_page(): void
    {
        // Laravel's convention is that resources/views/errors/{code}.blade.php
        // is the last word, so the package must never displace one. Testbench's
        // own skeleton ships a 503 there, which makes this a real fixture rather
        // than a simulated one: if the guard regressed, the assertions below
        // would find the themed shell instead of the host page.
        $this->assertFileExists(
            resource_path('views/errors/503.blade.php'),
            'Este teste depende do 503 que o skeleton do Testbench publica; se ele sumiu, '.
            'troque a fixture em vez de remover a cobertura do override do host.'
        );

        Route::get('/ptah-abort-host-503', function () {
            throw new HttpException(503);
        });

        $response = $this->get('/ptah-abort-host-503');

        $response->assertStatus(503);
        $this->assertStringNotContainsString('err-code', (string) $response->getContent());
    }
}
