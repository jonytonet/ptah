<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Errors;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The themed 500 may only claim the exceptions Laravel has no answer for.
 *
 * The second `renderable` is typed `Throwable`, so Laravel offers it EVERY
 * exception, and its only exclusion by type was `HttpException`. But the
 * handler's own `render()` special-cases three more classes, and it does so
 * AFTER the render callbacks run:
 *
 *     $e = $this->prepareException($e);
 *     if ($response = $this->renderViaCallbacks($request, $e)) { return … }
 *     return match (true) {
 *         $e instanceof HttpResponseException    => $e->getResponse(),
 *         $e instanceof AuthenticationException  => $this->unauthenticated(…),
 *         $e instanceof ValidationException      => $this->convertValidation…(…),
 *         default => $this->renderExceptionResponse($request, $e),
 *     };
 *
 * So the package intercepted them first and answered 500:
 *
 *   AuthenticationException → should REDIRECT to the login screen
 *   ValidationException     → should redirect back with the errors in session
 *   HttpResponseException   → should return the response it carries
 *
 * A buyer typing the wrong password got a generic error page instead of
 * "invalid credentials", and a logged-out visitor got a 500 instead of the
 * login screen.
 *
 * ── Why it stayed hidden ─────────────────────────────────────────────────
 *
 * Two guards, each innocent on its own. `$hiddenByDebug` makes the callback
 * step aside while `APP_DEBUG=true`, so the defect exists ONLY in production —
 * turning debug on to investigate made it disappear. And `AuthenticationException`
 * and `ValidationException` are both in Laravel's `dontReport` list, so the 500
 * went out without a single line in the log: the symptom was "clients report
 * errors, nothing to investigate".
 *
 * ── What the report got right, and the one thing it did not ──────────────
 *
 * It also named `AuthorizationException`. That one was already safe:
 * `prepareException()` runs BEFORE the callbacks and turns it into
 * `AccessDeniedHttpException` (or an `HttpException` when it carries a status),
 * both of which the existing `instanceof HttpException` guard excludes. The
 * test below pins that, because "already correct" is a claim like any other.
 */
class ExceptionRenderableScopeTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // A condicao em que o defeito existe, e a unica: com APP_DEBUG ligado o
        // callback sai de cena e tudo funciona.
        $app['config']->set('app.debug', false);
        $app['config']->set('ptah.errors.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // `unauthenticated()` redireciona para `route('login')` quando o host
        // nao diz outra coisa.
        Route::get('/login', fn () => 'login')->name('login');
    }

    #[Test]
    public function an_unauthenticated_visitor_is_sent_to_the_login_screen(): void
    {
        Route::get('/ptah-needs-auth', function () {
            throw new AuthenticationException;
        });

        $response = $this->withExceptionHandling()->get('/ptah-needs-auth');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    #[Test]
    public function a_validation_failure_goes_back_with_its_errors(): void
    {
        Route::get('/ptah-invalid', function () {
            throw ValidationException::withMessages(['email' => 'Credenciais invalidas.']);
        });

        $response = $this->withExceptionHandling()->get('/ptah-invalid');

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function an_http_response_exception_keeps_the_response_it_carries(): void
    {
        Route::get('/ptah-responds', function () {
            throw new HttpResponseException(redirect('/somewhere-else'));
        });

        $response = $this->withExceptionHandling()->get('/ptah-responds');

        $response->assertStatus(302);
        $response->assertRedirect('/somewhere-else');
    }

    #[Test]
    public function an_authorization_denial_still_reaches_the_themed_403(): void
    {
        // O item do laudo que ja estava correto: `prepareException()` converte
        // AuthorizationException em AccessDeniedHttpException ANTES dos
        // callbacks, entao a guarda `instanceof HttpException` ja o excluia.
        config()->set('ptah.modules.permissions', true);

        Route::get('/ptah-denied', function () {
            throw new AuthorizationException('nao pode');
        });

        $this->withExceptionHandling()->get('/ptah-denied')->assertStatus(403);
    }

    #[Test]
    public function a_genuine_unhandled_exception_still_gets_the_themed_500(): void
    {
        // A contrapartida, e o motivo de o callback existir: estreitar demais o
        // escopo apagaria a pagina tematica que este trabalho nao pode perder.
        Route::get('/ptah-boom', function () {
            throw new RuntimeException('estourou de proposito');
        });

        $response = $this->withExceptionHandling()->get('/ptah-boom');

        $response->assertStatus(500);
        $response->assertSee('err-code', false);
    }

    /**
     * The exception classes the framework's own `render()` answers by itself,
     * read out of the installed framework.
     *
     * The report's closing objection was that a hand-written exclusion list
     * "goes stale as Laravel evolves", and it is right — so the list is not
     * trusted here. This reads the arms of the `match (true)` that runs AFTER
     * `renderViaCallbacks()`, resolves each short name through the file's own
     * `use` statements, and hands them back. Add an arm upstream and this test
     * fails with its name.
     *
     * @return list<class-string>
     */
    private static function frameworkRenderedClasses(): array
    {
        $path = __DIR__.'/../../../vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php';
        $source = str_replace("\r\n", "\n", (string) file_get_contents($path));

        $after = strpos($source, 'renderViaCallbacks(');

        if ($after === false) {
            self::fail('Nao achei renderViaCallbacks() no Handler do Laravel — o guard precisa ser reescrito.');
        }

        $match = strpos($source, 'match (true) {', $after);
        $end = strpos($source, '}, $e);', $match === false ? $after : $match);

        if ($match === false || $end === false) {
            self::fail('Nao achei o match(true) que segue os render callbacks.');
        }

        $block = substr($source, $match, $end - $match);

        // `(\S+)` e nao uma classe de caractere: um nome de classe pode trazer
        // contrabarra, e escrever `[A-Za-z_\\]` numa string PHP de aspas
        // simples entrega `[A-Za-z_\]` a regex, que morre com "missing
        // terminating ]". O braco termina em espaco antes do `=>`, entao nao
        // ha ambiguidade.
        preg_match_all('/\$e instanceof (\S+)/', $block, $m);

        $names = array_values(array_unique($m[1]));

        if (count($names) < 3) {
            self::fail('O match(true) rendeu menos braços do que o esperado — extração quebrada, e o teste passaria a vazio.');
        }

        // `use Illuminate\Auth\AuthenticationException;` → nome curto => FQCN.
        // Mesmo motivo do `(\S+)` acima: sem classe de caractere com
        // contrabarra. Um `use` termina em `;` no fim da linha.
        preg_match_all('/^use ([^\s;]+);$/m', $source, $uses);

        $imports = [];

        foreach ($uses[1] as $fqcn) {
            $pos = strrpos($fqcn, '\\');
            $imports[$pos === false ? $fqcn : substr($fqcn, $pos + 1)] = $fqcn;
        }

        return array_map(
            static fn (string $name): string => $imports[ltrim($name, '\\')] ?? ltrim($name, '\\'),
            $names
        );
    }

    #[Test]
    public function the_exclusion_list_still_covers_every_class_the_framework_renders_itself(): void
    {
        $missing = [];

        foreach (self::frameworkRenderedClasses() as $class) {
            if (! class_exists($class) && ! interface_exists($class)) {
                $missing[] = $class.' (nao resolveu para uma classe real — o guard precisa de ajuste)';

                continue;
            }

            foreach (PtahServiceProvider::FRAMEWORK_RENDERED_EXCEPTIONS as $excluded) {
                if (is_a($class, $excluded, true)) {
                    continue 2;
                }
            }

            $missing[] = $class;
        }

        $this->assertSame(
            [],
            $missing,
            "O Laravel renderiza estas excecoes sozinho e o callback do 500 as intercepta:\n  ".
            implode("\n  ", $missing)."\n".
            'Acrescente-as a PtahServiceProvider::FRAMEWORK_RENDERED_EXCEPTIONS.'
        );
    }

    #[Test]
    public function the_exclusion_list_keeps_the_http_exception_it_started_with(): void
    {
        // HttpException nao aparece no match — chega la ja convertido por
        // `prepareException()`, que roda antes dos callbacks. Ele e a exclusao
        // original e nao pode sumir num refactor guiado apenas pelo guard acima.
        $this->assertContains(
            HttpException::class,
            PtahServiceProvider::FRAMEWORK_RENDERED_EXCEPTIONS
        );
    }

    #[Test]
    public function a_json_request_is_untouched_by_any_of_it(): void
    {
        Route::get('/ptah-json-invalid', function () {
            throw ValidationException::withMessages(['email' => 'invalido']);
        });

        $this->withExceptionHandling()
            ->getJson('/ptah-json-invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
