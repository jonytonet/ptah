<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Enums\Provider;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Tests\TestCase;
use ReflectionMethod;

/**
 * Getting ptah's AI agent to talk to an OpenAI-compatible provider that is not
 * OpenAI. Reported from a real integration with Grok (x.ai), which could not be
 * made to work at all: four defects stacked, and each one masked the next.
 *
 * They are covered together because that stacking is the story — fixing any one
 * in isolation still leaves the integration dead, and the last of them is why it
 * took hours instead of minutes to find the first three.
 */
class AiProviderCompatibilityTest extends TestCase
{
    private function service(): AiChatService
    {
        return $this->app->make(AiChatService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function config(array $attributes = []): AiModelConfig
    {
        return new AiModelConfig(array_merge([
            'name' => 'test',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test-key',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyConfig(array $attributes): array
    {
        $method = new ReflectionMethod(AiChatService::class, 'applyConfig');

        return $method->invoke($this->service(), $this->config($attributes));
    }

    // ── 1. the endpoint was written to a key nothing reads ──────────────────

    #[Test]
    public function every_prism_provider_reads_its_endpoint_from_the_url_key(): void
    {
        // The premise the fix rests on, asserted against Prism's own shipped
        // config rather than trusted: if a provider ever used `base_url`, the
        // generalisation in applyConfig() would be wrong for it.
        $providers = config('prism.providers');

        $this->assertIsArray($providers);

        foreach ($providers as $name => $settings) {
            if (! is_array($settings) || ! array_key_exists('url', $settings)) {
                continue;
            }

            $this->assertArrayNotHasKey(
                'base_url',
                $settings,
                "prism.providers.{$name} declara base_url — applyConfig() escreve em .url e precisaria ser revisto."
            );
        }
    }

    #[Test]
    #[DataProvider('endpointProvider')]
    public function a_saved_endpoint_reaches_the_key_prism_actually_reads(string $provider): void
    {
        // THE bug. applyConfig() used to set `prism.providers.openai.base_url`
        // while Prism reads `.url`, so the endpoint typed into /ptah-ai/models
        // was silently discarded and the request still went to api.openai.com —
        // the 401 even mentioned platform.openai.com, which sent the
        // investigation the wrong way entirely.
        // A host no provider ships as its default, deliberately: using
        // https://api.x.ai/v1 here made the `xai` case pass even with the bug,
        // because that IS Prism's default for xai — a test that could not fail.
        $endpoint = 'https://llm.internal.example/v1';

        $this->applyConfig(['provider' => $provider, 'api_endpoint' => $endpoint]);

        $this->assertSame(
            $endpoint,
            config("prism.providers.{$provider}.url"),
            "O api_endpoint salvo precisa chegar em prism.providers.{$provider}.url."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function endpointProvider(): array
    {
        return [
            'openai' => ['openai'],
            'anthropic' => ['anthropic'],
            'groq' => ['groq'],
            'xai' => ['xai'],
        ];
    }

    #[Test]
    public function the_api_key_is_applied_and_the_previous_value_is_returned_for_restore(): void
    {
        config(['prism.providers.openai.api_key' => 'env-key']);

        $restore = $this->applyConfig(['provider' => 'openai', 'api_key' => 'sk-from-db']);

        $this->assertSame('sk-from-db', config('prism.providers.openai.api_key'));

        // The restore map is what stops a key leaking between requests on a
        // long-lived worker (Octane), so the ORIGINAL value has to come back.
        $this->assertSame('env-key', $restore['prism.providers.openai.api_key']);
    }

    #[Test]
    public function an_empty_key_does_not_blank_out_the_hosts_env_credential(): void
    {
        // Ollama configs legitimately save no key. Writing null over the env
        // value would break a host that relies on it.
        config(['prism.providers.ollama.api_key' => 'env-key']);

        $this->applyConfig(['provider' => 'ollama', 'api_key' => null]);

        $this->assertSame('env-key', config('prism.providers.ollama.api_key'));
    }

    #[Test]
    public function ollama_falls_back_to_a_base_url_without_the_api_suffix(): void
    {
        // Found while generalising the maps, not in the original report.
        // Prism's Ollama handlers post to the RELATIVE path `api/chat`, and its
        // own default base url has no `/api`. ptah used to force
        // `http://localhost:11434/api`, producing `…/api/api/chat`, so a default
        // Ollama install failed until the host set OLLAMA_URL by hand.
        $this->applyConfig(['provider' => 'ollama', 'api_endpoint' => null]);

        $url = (string) config('prism.providers.ollama.url');

        $this->assertStringEndsNotWith('/api', $url, "A base url do Ollama nao deve terminar em /api: o Prism ja pede 'api/chat'.");
        $this->assertSame('http://localhost:11434', $url);
    }

    // ── 2. xai was routed through the OpenAI provider ───────────────────────

    #[Test]
    #[DataProvider('providerSlugProvider')]
    public function every_slug_offered_in_the_ui_resolves_to_its_own_prism_provider(string $slug, Provider $expected): void
    {
        $method = new ReflectionMethod(AiChatService::class, 'resolveProvider');

        $this->assertSame($expected, $method->invoke($this->service(), $slug));
    }

    /**
     * @return array<string, array{0: string, 1: Provider}>
     */
    public static function providerSlugProvider(): array
    {
        return [
            'openai' => ['openai', Provider::OpenAI],
            'anthropic' => ['anthropic', Provider::Anthropic],
            'gemini' => ['gemini', Provider::Gemini],
            // The one the report is about: routed to Provider::OpenAI before,
            // which posts to `responses` — an endpoint x.ai does not implement,
            // answered with a bare 422.
            'xai' => ['xai', Provider::XAI],
            'deepseek' => ['deepseek', Provider::DeepSeek],
            'groq' => ['groq', Provider::Groq],
            'mistral' => ['mistral', Provider::Mistral],
            'openrouter' => ['openrouter', Provider::OpenRouter],
            'perplexity' => ['perplexity', Provider::Perplexity],
            'ollama' => ['ollama', Provider::Ollama],
            // Case is not the caller's problem.
            'uppercase' => ['XAI', Provider::XAI],
        ];
    }

    #[Test]
    public function an_unknown_slug_still_falls_back_to_openai(): void
    {
        $method = new ReflectionMethod(AiChatService::class, 'resolveProvider');

        $this->assertSame(Provider::OpenAI, $method->invoke($this->service(), 'something-else'));
    }

    #[Test]
    public function the_ui_only_offers_slugs_prism_can_resolve(): void
    {
        // The provider list is the gate on what reaches resolveProvider(), so a
        // label added there without a matching Prism enum value would silently
        // fall back to OpenAI — reintroducing exactly the xai bug for a new
        // provider.
        foreach (array_keys(AiModelConfigList::PROVIDERS) as $slug) {
            $this->assertNotNull(
                Provider::tryFrom((string) $slug),
                "'{$slug}' esta na lista da UI mas nao e um valor de Prism\\Prism\\Enums\\Provider — ".
                'resolveProvider() cairia no fallback OpenAI e o endpoint seria o errado.'
            );
        }
    }
}
