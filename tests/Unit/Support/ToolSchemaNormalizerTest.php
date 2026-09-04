<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prism\Prism\Tool;
use Ptah\Support\AI\ToolSchemaNormalizer;

/**
 * A tool with no arguments serialises its empty parameter list as
 * `"properties": []` — a JSON *array* where JSON Schema requires an *object*.
 * Strict providers reject the whole request for it:
 *
 *     Schema validation failed: /properties: [] is not of type "object"
 *
 * x.ai does, and so does OpenAI's structured mode. Both of ptah's built-in tools
 * (getSystemInfo, getCurrentDateTime) take no arguments, so every install
 * talking to a strict provider failed on the package's own tools — and Prism
 * reported it as "Unknown error".
 *
 * The normaliser rewrites the shape rather than sniffing the destination host,
 * and these tests pin both halves of that decision: it fixes the malformed
 * payload wherever it is going, and it leaves a well-formed one byte-identical.
 */
class ToolSchemaNormalizerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $request = new Request(
            'POST',
            'https://api.x.ai/v1/chat/completions',
            ['Content-Type' => 'application/json'],
            Utils::streamFor((string) json_encode($payload))
        );

        $result = ToolSchemaNormalizer::handle($request);

        return (array) json_decode((string) $result->getBody(), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rawBody(array $payload): string
    {
        $encoded = (string) json_encode($payload);

        $request = new Request(
            'POST',
            'https://api.openai.com/v1/responses',
            ['Content-Type' => 'application/json'],
            Utils::streamFor($encoded)
        );

        return (string) ToolSchemaNormalizer::handle($request)->getBody();
    }

    #[Test]
    public function the_defect_is_real_in_prism_itself(): void
    {
        // Not a synthetic fixture: this is what Prism produces for a no-argument
        // tool, and it is the reason this class exists. If Prism ever fixes
        // parametersAsArray() upstream this test starts failing, which is the
        // signal to delete the normaliser rather than keep it forever.
        $tool = (new Tool)->as('getSystemInfo')->for('info')->using(fn (): string => 'x');

        $this->assertSame(
            '{"properties":[]}',
            json_encode(['properties' => $tool->parametersAsArray()]),
            'O Prism passou a serializar properties como objeto — reavalie a necessidade do ToolSchemaNormalizer.'
        );
    }

    /**
     * Each provider family nests the schema differently.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function shapeProvider(): array
    {
        return [
            // Groq, Mistral, DeepSeek, XAI
            'nested function' => [
                ['tools' => [[
                    'type' => 'function',
                    'function' => ['name' => 'getSystemInfo', 'parameters' => ['type' => 'object', 'properties' => [], 'required' => []]],
                ]]],
                '"properties":{}',
            ],
            // OpenAI Responses API
            'flat' => [
                ['tools' => [[
                    'type' => 'function',
                    'name' => 'getSystemInfo',
                    'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
                ]]],
                '"properties":{}',
            ],
            // Anthropic (already fixed upstream; normalising anyway is harmless)
            'input_schema' => [
                ['tools' => [[
                    'name' => 'getSystemInfo',
                    'input_schema' => ['type' => 'object', 'properties' => [], 'required' => []],
                ]]],
                '"properties":{}',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[Test]
    #[DataProvider('shapeProvider')]
    public function an_empty_properties_array_becomes_an_object(array $payload, string $expected): void
    {
        $this->assertStringContainsString($expected, $this->rawBody($payload));
        $this->assertStringNotContainsString('"properties":[]', $this->rawBody($payload));
    }

    #[Test]
    public function a_tool_that_has_parameters_is_left_alone(): void
    {
        $payload = ['tools' => [[
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['q' => ['type' => 'string']],
                    'required' => ['q'],
                ],
            ],
        ]]];

        $result = $this->normalize($payload);

        $this->assertSame(
            ['q' => ['type' => 'string']],
            $result['tools'][0]['function']['parameters']['properties']
        );
    }

    #[Test]
    public function a_well_formed_payload_comes_back_byte_identical(): void
    {
        // The guarantee that makes a global middleware acceptable: a request
        // without the defect is not re-encoded, so key order, spacing and
        // escaping are untouched. Re-serialising every outbound body would be a
        // real risk for a payload the host built deliberately.
        $payload = ['model' => 'grok-2', 'messages' => [['role' => 'user', 'content' => 'oi']]];
        $encoded = (string) json_encode($payload);

        $request = new Request('POST', 'https://api.x.ai/v1/chat/completions', [], Utils::streamFor($encoded));

        $this->assertSame($encoded, (string) ToolSchemaNormalizer::handle($request)->getBody());
    }

    #[Test]
    public function a_body_that_is_not_json_is_untouched(): void
    {
        $request = new Request('POST', 'https://example.test/upload', [], Utils::streamFor('not json at all "tools" "properties"'));

        $this->assertSame(
            'not json at all "tools" "properties"',
            (string) ToolSchemaNormalizer::handle($request)->getBody()
        );
    }

    #[Test]
    public function an_empty_body_is_untouched(): void
    {
        $request = new Request('GET', 'https://example.test/');

        $this->assertSame('', (string) ToolSchemaNormalizer::handle($request)->getBody());
    }

    #[Test]
    public function a_tools_value_that_is_not_a_list_is_ignored(): void
    {
        // Defensive: some hosts send `tools` as a string or an object. The
        // middleware must not throw on a shape it does not understand — it runs
        // on every outbound request.
        $encoded = (string) json_encode(['tools' => 'auto', 'properties' => []]);

        $request = new Request('POST', 'https://example.test/', [], Utils::streamFor($encoded));

        $this->assertSame($encoded, (string) ToolSchemaNormalizer::handle($request)->getBody());
    }

    #[Test]
    public function several_tools_are_all_normalised(): void
    {
        $payload = ['tools' => [
            ['type' => 'function', 'function' => ['name' => 'getSystemInfo', 'parameters' => ['type' => 'object', 'properties' => []]]],
            ['type' => 'function', 'function' => ['name' => 'search', 'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]]],
            ['type' => 'function', 'function' => ['name' => 'getCurrentDateTime', 'parameters' => ['type' => 'object', 'properties' => []]]],
        ]];

        $body = $this->rawBody($payload);

        $this->assertSame(2, substr_count($body, '"properties":{}'));
        $this->assertStringContainsString('"q":{"type":"string"}', $body);
    }
}
