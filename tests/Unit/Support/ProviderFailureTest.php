<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as ClientResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Exceptions\PrismException;
use Ptah\Support\AI\ProviderFailure;
use Ptah\Tests\TestCase;

/**
 * Classifying a provider failure into something a person can act on.
 *
 * Every failure used to surface the same way: the translated sentence plus
 * `OpenAI Error [422]: Unknown error`. That leaves both audiences stuck — a user
 * cannot tell whether to retry, and an administrator cannot tell whether to fix
 * a key, an endpoint, a model name, or nothing at all.
 *
 * The classification is the fix, so these tests are the classification: each
 * cause a real integration hits, mapped to the one thing worth telling someone.
 */
class ProviderFailureTest extends TestCase
{
    private function prismFailure(int $status, string $body): PrismException
    {
        $response = new ClientResponse(new PsrResponse($status, ['Content-Type' => 'application/json'], $body));

        return PrismException::providerRequestErrorWithDetails(
            provider: 'OpenAI',
            statusCode: $status,
            errorType: null,
            errorMessage: null,
            previous: new RequestException($response)
        );
    }

    /**
     * @return array<string, array{0: int, 1: string, 2: string}>
     */
    public static function causeProvider(): array
    {
        return [
            'rejected credential' => [401, '{"error":{"message":"Incorrect API key provided"}}', 'auth'],
            'forbidden' => [403, '{"error":"forbidden"}', 'auth'],
            'wrong endpoint' => [404, '{"error":"not found"}', 'endpoint'],
            'throttled' => [429, '{"error":"slow down"}', 'rate_limit'],
            'provider down' => [503, '{"error":"upstream unavailable"}', 'overloaded'],
            // The one that cost hours: a schema rejection reads as the operator's
            // fault unless it is named as a request-format problem.
            'tool schema' => [422, '{"error":"Schema validation failed: /properties: [] is not of type \\"object\\""}', 'schema'],
            'unknown model' => [400, '{"error":{"message":"The model `grok-9` does not exist"}}', 'model'],
            'other 4xx' => [418, '{"error":"teapot"}', 'rejected'],
        ];
    }

    #[Test]
    #[DataProvider('causeProvider')]
    public function each_cause_gets_its_own_classification(int $status, string $body, string $expected): void
    {
        $failure = ProviderFailure::from($this->prismFailure($status, $body));

        $this->assertSame($expected, $failure->reason);
        $this->assertSame($status, $failure->status);
    }

    #[Test]
    public function a_connection_failure_is_recognised_without_a_status(): void
    {
        // DNS, refused, timeout: never reaches a status code, and the fix is the
        // endpoint or the server — not the credential or the request.
        $failure = ProviderFailure::from(
            new \RuntimeException('wrapped', 0, new ConnectionException('cURL error 7: Failed to connect to localhost port 11434'))
        );

        $this->assertSame('unreachable', $failure->reason);
        $this->assertNull($failure->status);
    }

    #[Test]
    #[DataProvider('causeProvider')]
    public function every_classification_has_a_translated_sentence_in_both_languages(int $status, string $body, string $expected): void
    {
        // A missing key would surface the raw `ptah::ui.ai_error_*` string in the
        // chat widget, which is worse than the message it replaced.
        foreach (['en', 'pt_BR'] as $locale) {
            $strings = require dirname(__DIR__, 2)."/../resources/lang/{$locale}/ui.php";

            $this->assertArrayHasKey("ai_error_{$expected}", $strings, "Falta ai_error_{$expected} em {$locale}.");
            $this->assertNotSame('', trim((string) $strings["ai_error_{$expected}"]));
        }
    }

    #[Test]
    public function the_sentence_never_leaks_the_raw_body(): void
    {
        // The classified sentence goes to the chat widget. The body belongs in
        // the log, where the person who can act on it looks.
        $failure = ProviderFailure::from(
            $this->prismFailure(401, '{"error":"Incorrect API key sk-abc123 for org-internal"}')
        );

        $this->assertStringNotContainsString('sk-abc123', $failure->message());
        $this->assertStringNotContainsString('org-internal', $failure->message());
    }

    #[Test]
    public function the_log_context_carries_the_reason_status_and_body(): void
    {
        $failure = ProviderFailure::from($this->prismFailure(422, '{"error":"Schema validation failed"}'));

        $context = $failure->logContext();

        $this->assertSame('schema', $context['reason']);
        $this->assertSame(422, $context['status']);
        $this->assertStringContainsString('Schema validation failed', $context['response_body']);
    }

    #[Test]
    public function a_failure_with_nothing_to_go_on_is_generic_rather_than_guessed(): void
    {
        $failure = ProviderFailure::from(new \RuntimeException('something local broke'));

        $this->assertSame('generic', $failure->reason);
        $this->assertNull($failure->status);
        $this->assertNull($failure->body);
    }

    #[Test]
    public function the_log_context_omits_what_it_does_not_know(): void
    {
        // Logging `status => null` on every local failure is noise in the very
        // place someone is scanning for the real cause.
        $context = ProviderFailure::from(new \RuntimeException('local'))->logContext();

        $this->assertArrayNotHasKey('status', $context);
        $this->assertArrayNotHasKey('response_body', $context);
        $this->assertSame('generic', $context['reason']);
    }
}
