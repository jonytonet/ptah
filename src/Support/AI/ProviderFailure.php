<?php

declare(strict_types=1);

namespace Ptah\Support\AI;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a provider failure into something a person can act on.
 *
 * Before this, every failure surfaced as the translated sentence plus whatever
 * Prism had produced — typically `OpenAI Error [422]: Unknown error`. That tells
 * a user nothing, and tells an administrator nothing either: the two things they
 * need to know are *whose* fault it is (a wrong key, a wrong endpoint, a
 * provider outage, a request the provider rejected) and *what to change*.
 *
 * So the failure is classified once, here, and each class carries:
 *   - a translation key with an actionable sentence for the chat widget;
 *   - the HTTP status and raw response body, for the log.
 *
 * The body is deliberately NOT part of the user-facing sentence (the caller
 * appends it only under APP_DEBUG): it can carry internal detail, and it is
 * already in the log where the person who can act on it will look.
 */
class ProviderFailure
{
    private function __construct(
        public readonly string $translationKey,
        public readonly ?int $status,
        public readonly ?string $body,
        public readonly string $reason,
    ) {}

    public static function from(Throwable $e): self
    {
        $status = self::statusFor($e);
        $body = self::bodyFor($e);
        $haystack = Str::lower(($e->getMessage() ?? '').' '.($body ?? ''));

        // Order matters: the checks that identify a specific, fixable cause come
        // before the generic status buckets, because a 400 that says "model does
        // not exist" needs a different answer than a 400 that says anything else.
        $reason = match (true) {
            self::isConnectionFailure($e, $haystack) => 'unreachable',
            $status === 401 || $status === 403 => 'auth',
            $status === 404 => 'endpoint',
            $status === 429 => 'rate_limit',
            self::mentionsModel($haystack) => 'model',
            self::mentionsSchema($haystack) => 'schema',
            $status !== null && $status >= 500 => 'overloaded',
            $status !== null && $status >= 400 => 'rejected',
            default => 'generic',
        };

        return new self("ptah::ui.ai_error_{$reason}", $status, $body, $reason);
    }

    /** The sentence shown in the chat widget. */
    public function message(): string
    {
        return (string) trans($this->translationKey);
    }

    /**
     * Everything worth logging, minus anything null.
     *
     * @return array<string, mixed>
     */
    public function logContext(): array
    {
        return array_filter([
            'reason' => $this->reason,
            'status' => $this->status,
            'response_body' => $this->body,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * A DNS/refused/timeout failure never reaches a status code, so it has to be
     * recognised from the exception type or its text — and it is worth its own
     * class because the fix is "the endpoint or the server is wrong", not
     * anything about credentials or the request.
     */
    private static function isConnectionFailure(Throwable $e, string $haystack): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ConnectionException
                || $current instanceof ConnectException) {
                return true;
            }
        }

        return Str::contains($haystack, [
            'could not resolve host',
            'connection refused',
            'connection timed out',
            'failed to connect',
            'operation timed out',
            'curl error 6',
            'curl error 7',
            'curl error 28',
        ]);
    }

    /** A model name the provider does not serve — the commonest copy/paste slip. */
    private static function mentionsModel(string $haystack): bool
    {
        return Str::contains($haystack, [
            'model not found',
            'does not exist',
            'unknown model',
            'invalid model',
            'model_not_found',
            'no such model',
        ]);
    }

    /**
     * A schema/tool rejection. Kept separate because it is not the operator's
     * fault at all: it means the payload ptah built is not acceptable to this
     * provider, which is what the `"properties": []` defect looked like from the
     * outside for hours.
     */
    private static function mentionsSchema(string $haystack): bool
    {
        return Str::contains($haystack, [
            'schema validation',
            'is not of type',
            'invalid_request_error',
            'tools[',
            'invalid schema',
        ]);
    }

    private static function statusFor(Throwable $e): ?int
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            // Prism puts the provider status in the exception code
            // (providerRequestErrorWithDetails passes it as $code).
            $code = $current->getCode();

            if (is_int($code) && $code >= 100 && $code < 600) {
                return $code;
            }

            if (property_exists($current, 'response') && $current->response !== null) {
                $response = $current->response;

                if (method_exists($response, 'status')) {
                    return (int) $response->status();
                }
            }

            if (method_exists($current, 'getResponse')) {
                $response = $current->getResponse();

                if ($response !== null) {
                    return $response->getStatusCode();
                }
            }
        }

        return null;
    }

    /**
     * The provider's raw response body, from wherever in the chain it sits.
     *
     * Prism formats its message from `error.message` only, so a provider that
     * reports the problem anywhere else yields "Unknown error" and the real
     * explanation is discarded — yet it passes the original RequestException as
     * `previous`, so the body was always one hop away. This is the RESPONSE, so
     * it cannot contain the API key that was sent.
     */
    private static function bodyFor(Throwable $e): ?string
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $body = null;

            if (property_exists($current, 'response') && $current->response !== null) {
                $response = $current->response;
                $body = method_exists($response, 'body') ? $response->body() : null;
            } elseif (method_exists($current, 'getResponse')) {
                $response = $current->getResponse();
                $body = $response !== null ? (string) $response->getBody() : null;
            }

            if (is_string($body) && trim($body) !== '') {
                return Str::limit($body, 2000);
            }
        }

        return null;
    }
}
