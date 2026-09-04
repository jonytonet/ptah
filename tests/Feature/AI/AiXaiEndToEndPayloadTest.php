<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Tool;
use Ptah\Support\AI\ToolSchemaNormalizer;
use Ptah\Tests\TestCase;

/**
 * The end-to-end proof: what actually goes on the wire to x.ai.
 *
 * The three separate fixes only matter if they compose, and each was verified in
 * isolation above. This intercepts the real request Prism builds — same
 * provider, same tool, same client — and asserts the two things that made Grok
 * unusable:
 *
 *   1. the URL is `chat/completions`, not the OpenAI Responses API (`responses`),
 *      which x.ai does not implement and rejected with a bare 422;
 *   2. the no-argument tool's schema carries `"properties": {}`, not `[]`, which
 *      x.ai rejects with "Schema validation failed: /properties: [] is not of
 *      type object".
 */
class AiXaiEndToEndPayloadTest extends TestCase
{
    #[Test]
    public function the_request_prism_sends_to_xai_is_accepted_shaped(): void
    {
        ToolSchemaNormalizer::register();

        Http::fake([
            '*' => Http::response([
                'id' => 'x',
                'model' => 'grok-2',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'oi'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);

        // A tool with no arguments — exactly what ptah's own built-ins are.
        $tool = (new Tool)
            ->as('getSystemInfo')
            ->for('Returns system info')
            ->using(fn (): string => 'ok');

        Prism::text()
            ->using(Provider::XAI, 'grok-2')
            ->withPrompt('oi')
            ->withTools([$tool])
            ->asText();

        $recorded = Http::recorded();

        $this->assertNotEmpty($recorded, 'Nenhuma requisicao foi capturada — o fake nao interceptou o cliente do Prism.');

        [$request] = $recorded[0];

        // 1. The endpoint. Provider::OpenAI would have posted to `responses`.
        $this->assertStringContainsString(
            'chat/completions',
            $request->url(),
            'O provider XAI precisa postar em chat/completions; /responses e a Responses API da OpenAI, que a x.ai nao implementa.'
        );
        $this->assertStringNotContainsString('/responses', $request->url());

        // 2. The tool schema, read from the raw body so the JSON type is visible.
        $body = $request->body();

        $this->assertStringContainsString('getSystemInfo', $body);
        $this->assertStringNotContainsString(
            '"properties":[]',
            $body,
            'Uma tool sem argumentos saiu com properties como ARRAY — a x.ai recusa com '.
            '"Schema validation failed: /properties: [] is not of type object".'
        );
        $this->assertStringContainsString('"properties":{}', $body);
    }
}
