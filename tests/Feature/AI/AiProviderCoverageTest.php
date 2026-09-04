<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Enums\Provider;
use Prism\Prism\PrismManager;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;
use ReflectionMethod;

/**
 * Which platforms ptah can actually reach, and the two gaps that were left.
 *
 * The roster audit that produced these tests: of Prism's providers, ten had both
 * Text and Stream handlers and were already offered; `z` had Text but NO Stream,
 * so offering it under ptah's `stream => true` default would have broken the
 * chat outright; and every OpenAI-compatible platform WITHOUT a dedicated Prism
 * provider had no working option at all, because Prism's `openai` provider posts
 * to `responses` and none of them implement it.
 */
class AiProviderCoverageTest extends TestCase
{
    private function service(): AiChatService
    {
        return $this->app->make(AiChatService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeConfig(array $attributes = []): AiModelConfig
    {
        return AiModelConfig::create(array_merge([
            'name' => 'cfg-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => true,
        ], $attributes));
    }

    // ── The roster is fully covered ─────────────────────────────────────────

    #[Test]
    public function every_text_capable_prism_provider_is_offered_or_deliberately_absent(): void
    {
        // The audit, as a test. A provider Prism adds with a Text handler shows
        // up here as a failure, which is the prompt to decide about it rather
        // than to discover it years later from a user.
        $offered = array_keys(AiModelConfigList::PROVIDERS);
        $missing = [];

        foreach (Provider::cases() as $case) {
            $dir = __DIR__.'/../../../vendor/prism-php/prism/src/Providers';
            $handler = null;

            foreach (glob($dir.'/*/Handlers/Text.php') ?: [] as $path) {
                if (strtolower(basename(dirname($path, 2))) === strtolower($case->name)) {
                    $handler = $path;
                    break;
                }
            }

            if ($handler === null) {
                continue;   // speech/embedding providers have no chat handler
            }

            if (! in_array($case->value, $offered, true)) {
                $missing[] = $case->value;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Provider(s) do Prism com handler de texto que a UI nao oferece: '.implode(', ', $missing).
            '. Decida: expor (checando se tem Stream) ou documentar a ausencia.'
        );
    }

    #[Test]
    #[DataProvider('offeredProviderProvider')]
    public function every_offered_provider_can_answer_a_text_request(string $slug): void
    {
        // Guards against offering something that only does speech or embeddings:
        // it would appear in the picker and fail on the first message.
        $method = new ReflectionMethod(AiChatService::class, 'resolveProvider');
        $provider = $method->invoke($this->service(), $slug);

        $instance = $this->app->make(PrismManager::class)->resolve($provider);

        $declaring = (new ReflectionMethod($instance, 'text'))->getDeclaringClass()->getName();

        $this->assertNotSame(
            \Prism\Prism\Providers\Provider::class,
            $declaring,
            "'{$slug}' resolve para um provider sem handler de texto proprio — nao serve para chat."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function offeredProviderProvider(): array
    {
        $cases = [];

        foreach (array_keys(AiModelConfigList::PROVIDERS) as $slug) {
            $cases[(string) $slug] = [(string) $slug];
        }

        return $cases;
    }

    // ── Streaming is a capability, not a preference ─────────────────────────

    #[Test]
    public function a_provider_without_a_stream_handler_reports_no_streaming(): void
    {
        // z.ai is the case this exists for: Text and Structured handlers, no
        // Stream. Prism's base provider throws unsupportedProviderAction from
        // stream(), so ptah's `stream => true` default would have broken the
        // chat for it outright.
        $config = $this->makeConfig(['provider' => 'z', 'model' => 'glm-4']);

        $this->assertFalse($this->service()->supportsStreaming($config->id));
    }

    #[Test]
    public function a_provider_with_a_stream_handler_reports_streaming(): void
    {
        $config = $this->makeConfig(['provider' => 'xai', 'model' => 'grok-2']);

        $this->assertTrue($this->service()->supportsStreaming($config->id));
    }

    #[Test]
    public function the_check_is_on_the_class_not_a_hardcoded_list(): void
    {
        // The point of detecting rather than listing: if a provider gains or
        // loses streaming in a Prism release, ptah follows with no change here.
        // So the answer must match what the resolved class actually declares.
        foreach (array_keys(AiModelConfigList::PROVIDERS) as $slug) {
            $config = $this->makeConfig(['provider' => (string) $slug, 'is_default' => false]);

            $method = new ReflectionMethod(AiChatService::class, 'resolveProvider');
            $instance = $this->app->make(PrismManager::class)
                ->resolve($method->invoke($this->service(), (string) $slug));

            $declares = (new ReflectionMethod($instance, 'stream'))->getDeclaringClass()->getName()
                !== \Prism\Prism\Providers\Provider::class;

            $this->assertSame(
                $declares,
                $this->service()->supportsStreaming($config->id),
                "supportsStreaming() divergiu do que a classe de '{$slug}' realmente declara."
            );
        }
    }

    #[Test]
    public function an_unresolvable_config_reports_no_streaming(): void
    {
        // No config at all: the caller must take the path every provider
        // supports rather than gamble on streaming.
        $this->assertFalse($this->service()->supportsStreaming(null));
    }

    // ── The OpenAI-compatible alias ─────────────────────────────────────────

    #[Test]
    public function the_openai_compatible_alias_resolves_to_a_chat_completions_provider(): void
    {
        // The whole reason the alias exists: Prism's `openai` provider posts to
        // `responses`, which Together, Fireworks, Azure, vLLM, LM Studio and the
        // rest do not implement — the same unexplained 422 that made Grok look
        // unsupportable.
        $method = new ReflectionMethod(AiChatService::class, 'resolveProvider');
        $provider = $method->invoke($this->service(), 'openai_compatible');

        $this->assertNotSame(Provider::OpenAI, $provider, 'O alias nao pode cair no provider OpenAI: ele usa a Responses API.');

        $handler = __DIR__.'/../../../vendor/prism-php/prism/src/Providers/'.$provider->name.'/Handlers/Text.php';

        $this->assertFileExists($handler);
        $this->assertStringContainsString(
            'chat/completions',
            (string) file_get_contents($handler),
            'O provider que carrega o alias precisa postar em chat/completions.'
        );
    }

    #[Test]
    public function the_alias_writes_its_credentials_into_the_carrier_provider(): void
    {
        // Without routing the config paths through the alias map there is no
        // `prism.providers.openai_compatible` block, so neither the key nor the
        // endpoint would land anywhere and the request would go to the carrier's
        // own default endpoint with the host's env key.
        $method = new ReflectionMethod(AiChatService::class, 'resolveProvider');
        $carrier = $method->invoke($this->service(), 'openai_compatible')->value;

        $apply = new ReflectionMethod(AiChatService::class, 'applyConfig');
        $apply->invoke($this->service(), new AiModelConfig([
            'name' => 'together',
            'provider' => 'openai_compatible',
            'model' => 'meta-llama/Llama-3-70b',
            'api_key' => 'sk-together',
            'api_endpoint' => 'https://api.together.xyz/v1',
            'max_tokens' => 1024,
            'temperature' => 0.7,
        ]));

        $this->assertSame('https://api.together.xyz/v1', config("prism.providers.{$carrier}.url"));
        $this->assertSame('sk-together', config("prism.providers.{$carrier}.api_key"));
    }

    #[Test]
    public function the_alias_requires_an_endpoint(): void
    {
        // There is no sensible default for somebody else's server, and without
        // one the request would silently go to the carrier provider's endpoint.
        //
        // The rule is asserted directly rather than through save(), which
        // authorizes before it validates — going through the whole action would
        // be testing the permission gate, not this rule.
        $component = new AiModelConfigList;
        $component->provider = 'openai_compatible';

        $rules = (new ReflectionMethod(AiModelConfigList::class, 'rules'))->invoke($component);

        $this->assertStringContainsString(
            'required',
            (string) $rules['api_endpoint'],
            'O alias OpenAI-compatible precisa exigir api_endpoint.'
        );
    }

    #[Test]
    public function a_real_provider_still_does_not_require_an_endpoint(): void
    {
        // The rule is conditional, so it must not start demanding an endpoint
        // from providers that ship a perfectly good default.
        $component = new AiModelConfigList;
        $component->provider = 'openai';

        $rules = (new ReflectionMethod(AiModelConfigList::class, 'rules'))->invoke($component);

        $this->assertStringContainsString('nullable', (string) $rules['api_endpoint']);
        $this->assertStringNotContainsString('required', (string) $rules['api_endpoint']);
    }

    #[Test]
    public function the_widget_takes_the_non_streaming_path_when_the_provider_cannot_stream(): void
    {
        // Asserting the DECISION, not the outcome.
        //
        // My first version of this ran a real turn under Prism::fake() and
        // asserted the assistant message arrived. It passed — and proved
        // nothing: Prism::fake() swaps the manager for a fake provider that DOES
        // implement stream(), so supportsStreaming() reported true and the
        // widget streamed. The test could not distinguish the two paths at all.
        //
        // So the service is stubbed instead, and the stub records which method
        // the widget called. The capability itself is covered above, against the
        // real Prism manager.
        config(['ptah.ai_agent.allow_guests' => true, 'ptah.ai_agent.stream' => true]);

        $this->makeConfig(['provider' => 'z', 'model' => 'glm-4', 'is_default' => true]);

        $stub = new class($this->app->make(AiProviderConfigService::class), $this->app->make(AiToolRegistry::class)) extends AiChatService
        {
            public array $calls = [];

            public function supportsStreaming(?int $configId = null): bool
            {
                $this->calls[] = 'supportsStreaming';

                return false;   // what z.ai really reports
            }

            public function stream(string $message, string $sessionId, ?int $userId = null, ?int $conversationId = null, ?callable $onDelta = null, ?int $configId = null): array
            {
                $this->calls[] = 'stream';

                return ['text' => 'streamed', 'conversationId' => 1];
            }

            public function send(string $message, string $sessionId, ?int $userId = null, ?int $conversationId = null, ?int $configId = null): array
            {
                $this->calls[] = 'send';

                return ['text' => 'sent', 'conversationId' => 1];
            }
        };

        $this->app->instance(AiChatService::class, $stub);

        Livewire::test(AiChatWidget::class)->call('processAiMessage', 'oi');

        $this->assertContains('supportsStreaming', $stub->calls, 'O widget precisa CONSULTAR a capacidade, nao assumir o config.');
        $this->assertContains('send', $stub->calls);
        $this->assertNotContains(
            'stream',
            $stub->calls,
            'Com um provider sem Stream handler, stream() lancaria unsupportedProviderAction e o chat quebraria.'
        );
    }

    #[Test]
    public function the_widget_still_streams_when_the_provider_can(): void
    {
        // The counterpart, so the test above cannot pass merely because
        // streaming is broken for everyone.
        config(['ptah.ai_agent.allow_guests' => true, 'ptah.ai_agent.stream' => true]);

        $this->makeConfig(['provider' => 'xai', 'model' => 'grok-2', 'is_default' => true]);

        $stub = new class($this->app->make(AiProviderConfigService::class), $this->app->make(AiToolRegistry::class)) extends AiChatService
        {
            public array $calls = [];

            public function supportsStreaming(?int $configId = null): bool
            {
                return true;
            }

            public function stream(string $message, string $sessionId, ?int $userId = null, ?int $conversationId = null, ?callable $onDelta = null, ?int $configId = null): array
            {
                $this->calls[] = 'stream';

                return ['text' => 'streamed', 'conversationId' => 1];
            }

            public function send(string $message, string $sessionId, ?int $userId = null, ?int $conversationId = null, ?int $configId = null): array
            {
                $this->calls[] = 'send';

                return ['text' => 'sent', 'conversationId' => 1];
            }
        };

        $this->app->instance(AiChatService::class, $stub);

        Livewire::test(AiChatWidget::class)->call('processAiMessage', 'oi');

        $this->assertContains('stream', $stub->calls);
        $this->assertNotContains('send', $stub->calls);
    }
}
