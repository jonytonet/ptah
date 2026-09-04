<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as ClientResponse;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Exceptions\PrismException;
use Ptah\Services\AI\AiChatService;
use Ptah\Support\AI\ProviderFailure;
use Ptah\Tests\TestCase;
use ReflectionMethod;

/**
 * The multiplier on the other three fixes: the provider's real complaint was
 * being thrown away.
 *
 * Prism formats its message as `"%s Error [%d]: %s"` and fills the last part
 * from `error.message` in the JSON body (PrismException::
 * providerRequestErrorWithDetails). A provider that reports the problem
 * anywhere else in the body therefore yields the literal "Unknown error", and
 * the explanation is gone. x.ai does exactly that for a schema rejection:
 *
 *     logged:   OpenAI Error [422]: Unknown error
 *     actual:   Schema validation failed: /properties: [] is not of type "object"
 *
 * That second line is the entire diagnosis of the tool-schema bug, and finding
 * it required tapping the HTTP client by hand.
 *
 * Prism does pass the original RequestException as `previous`, so the body was
 * always reachable — nothing needed to be intercepted, only read.
 */
class AiProviderErrorDetailTest extends TestCase
{
    private function providerResponseBody(\Throwable $e): ?string
    {
        // Moved out of AiChatService into ProviderFailure when the failure
        // started being classified rather than just logged.
        return ProviderFailure::from($e)->body;
    }

    /**
     * Builds the exception chain Prism actually produces for a rejected call.
     */
    private function prismFailure(int $status, string $body): PrismException
    {
        $response = new ClientResponse(new Response(
            $status,
            ['Content-Type' => 'application/json'],
            $body
        ));

        $requestException = new RequestException($response);

        return PrismException::providerRequestErrorWithDetails(
            provider: 'OpenAI',
            statusCode: $status,
            errorType: null,
            errorMessage: null,   // exactly the case that produced "Unknown error"
            previous: $requestException
        );
    }

    #[Test]
    public function the_real_provider_complaint_is_recovered_from_the_chain(): void
    {
        $body = '{"code":"Client specified an invalid argument","error":"Schema validation failed: /properties: [] is not of type \\"object\\""}';

        $e = $this->prismFailure(422, $body);

        // The message Prism produced on its own says nothing.
        $this->assertStringContainsString('Unknown error', $e->getMessage());

        // The body was there the whole time.
        $recovered = $this->providerResponseBody($e);

        $this->assertNotNull($recovered);
        $this->assertStringContainsString('Schema validation failed', $recovered);
        $this->assertStringContainsString('is not of type', $recovered);
    }

    #[Test]
    public function the_body_is_found_however_deep_the_chain_goes(): void
    {
        // The wrapping depth varies by provider and by where the failure is
        // caught, so the walk cannot assume one level.
        $inner = $this->prismFailure(400, '{"error":"bad tool schema"}');
        $outer = new \RuntimeException('wrapped once', 0, new \RuntimeException('wrapped twice', 0, $inner));

        $this->assertStringContainsString('bad tool schema', (string) $this->providerResponseBody($outer));
    }

    #[Test]
    public function an_exception_with_no_response_yields_null(): void
    {
        // A local failure — a bad config, a DNS error — has no body, and the
        // caller must get null rather than an empty string it then logs.
        $this->assertNull($this->providerResponseBody(new \RuntimeException('boom')));
    }

    #[Test]
    public function an_empty_body_yields_null_rather_than_whitespace(): void
    {
        $this->assertNull($this->providerResponseBody($this->prismFailure(500, '   ')));
    }

    #[Test]
    public function a_long_body_is_truncated(): void
    {
        // Some providers echo the whole request back. The log line has to stay
        // readable, and this value ends up in a log driver that may have limits.
        $recovered = (string) $this->providerResponseBody(
            $this->prismFailure(400, str_repeat('x', 8000))
        );

        $this->assertLessThan(8000, strlen($recovered));
        $this->assertStringEndsWith('...', $recovered);
    }

    #[Test]
    public function the_user_facing_message_carries_the_body_only_in_debug(): void
    {
        // This message reaches the chat widget. A provider body can carry
        // internal detail an end user has no business seeing, so it is appended
        // for a developer and withheld from everyone else — while the log gets
        // it either way.
        $method = new ReflectionMethod(AiChatService::class, 'providerException');
        $service = $this->app->make(AiChatService::class);
        $failure = $this->prismFailure(422, '{"error":"internal detail here"}');

        config(['app.debug' => false]);
        $this->assertStringNotContainsString(
            'internal detail here',
            $method->invoke($service, $failure)->getMessage()
        );

        config(['app.debug' => true]);
        $this->assertStringContainsString(
            'internal detail here',
            $method->invoke($service, $failure)->getMessage()
        );
    }
}
