<?php

declare(strict_types=1);

namespace Ptah\Support\AI;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

/**
 * Rewrites `"properties": []` to `"properties": {}` in outgoing tool payloads.
 *
 * ── Why this exists ────────────────────────────────────────────────────────
 * A tool with no arguments has an empty parameter list, and Prism serialises it
 * straight from PHP: `Tool::parametersAsArray()` returns `[]`, which json_encode
 * writes as a JSON *array*. JSON Schema requires `properties` to be an *object*,
 * so the payload is invalid — and providers that validate it refuse the request:
 *
 *     Schema validation failed: /properties: [] is not of type "object"
 *
 * x.ai rejects it, and so does OpenAI's own strict/structured mode. Ptah's two
 * built-in tools (getSystemInfo, getCurrentDateTime) both take no arguments, so
 * every install talking to a strict provider failed on the package's own tools,
 * with the real message swallowed into "Unknown error".
 *
 * Prism has already fixed this for two providers — Anthropic and OpenRouter both
 * emit `$properties === [] ? new stdClass : $properties` — but OpenAI, XAI, Groq,
 * Mistral and DeepSeek still pass the raw array. The durable fix is upstream;
 * this keeps ptah's users working in the meantime.
 *
 * ── Why it does not sniff the host ─────────────────────────────────────────
 * The obvious implementation checks whether the request is going to x.ai. That
 * is the wrong shape twice over: it would miss every other strict endpoint
 * (OpenAI strict mode, a self-hosted vLLM, Together, DeepSeek) and it would need
 * a list maintained forever.
 *
 * `"properties": []` is invalid JSON Schema for ANY provider, so rewriting it to
 * `{}` is a correctness fix, never a behavioural change: a lenient provider
 * treats both as "no properties". So the trigger is the malformed shape itself,
 * and the payload is left byte-identical when that shape is absent.
 *
 * ── Scope ─────────────────────────────────────────────────────────────────
 * Registered from PtahServiceProvider only when the AI agent module is enabled,
 * and disableable with `ptah.ai_agent.normalize_tool_schema => false`. It is a
 * global Http middleware because that is the only hook Prism's client exposes
 * (Prism builds its client from the Http facade with a pass-through middleware),
 * so it does see the host's other outbound requests — which is why the shape
 * test is this narrow and why a payload without the defect is returned
 * untouched.
 */
class ToolSchemaNormalizer
{
    /**
     * Installs the middleware. Idempotent per process is NOT guaranteed by the
     * Http facade, so the caller gates on the module being enabled.
     */
    public static function register(): void
    {
        Http::globalRequestMiddleware(
            static fn (RequestInterface $request): RequestInterface => self::handle($request)
        );
    }

    public static function handle(RequestInterface $request): RequestInterface
    {
        $body = (string) $request->getBody();

        // Cheap rejection first: the vast majority of requests never get parsed.
        // `properties` must be present as an empty array for anything below to
        // apply, and a body without "tools" cannot be a tool payload.
        if ($body === '' || ! str_contains($body, '"tools"') || ! str_contains($body, '"properties"')) {
            return $request;
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ! is_array($data['tools'] ?? null)) {
            return $request;
        }

        $changed = false;

        foreach ($data['tools'] as $i => $tool) {
            if (! is_array($tool)) {
                continue;
            }

            // Three shapes across the providers Prism supports:
            //   nested   {type, function: {name, parameters: {properties}}}   Groq, Mistral, DeepSeek, XAI
            //   flat     {type, name, parameters: {properties}}               OpenAI Responses API
            //   anthropic{name, input_schema: {properties}}                   already fixed upstream, harmless here
            foreach ([['function', 'parameters'], ['parameters'], ['input_schema']] as $path) {
                $cursor = &$data['tools'][$i];
                $found = true;

                foreach ($path as $segment) {
                    if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                        $found = false;
                        break;
                    }

                    $cursor = &$cursor[$segment];
                }

                if ($found && is_array($cursor) && ($cursor['properties'] ?? null) === []) {
                    // An empty stdClass is what makes json_encode emit `{}`.
                    $cursor['properties'] = new \stdClass;
                    $changed = true;
                }

                unset($cursor);
            }
        }

        if (! $changed) {
            return $request;
        }

        $encoded = json_encode($data);

        if ($encoded === false) {
            // Re-encoding failed: send the original rather than a broken body.
            return $request;
        }

        return $request->withBody(Utils::streamFor($encoded));
    }
}
