<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Tests\TestCase;

/**
 * Choosing which configured provider a chat turn uses.
 *
 * The picker's value is client-writable — that is the whole feature — so the
 * validation has to live on the read side. These tests are mostly about what the
 * picker must NOT let through: an id naming a provider an administrator switched
 * off, or one that no longer exists.
 */
class AiChatProviderSelectionTest extends TestCase
{
    private function service(): AiProviderConfigService
    {
        return $this->app->make(AiProviderConfigService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeConfig(array $attributes = []): AiModelConfig
    {
        return AiModelConfig::create(array_merge([
            'name' => 'Config '.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => false,
        ], $attributes));
    }

    #[Test]
    public function the_picker_lists_every_active_provider_with_the_default_first(): void
    {
        $this->makeConfig(['name' => 'Zeta', 'is_default' => false]);
        $default = $this->makeConfig(['name' => 'Mid', 'is_default' => true]);
        $this->makeConfig(['name' => 'Alpha', 'is_default' => false]);

        $options = $this->service()->listActive();

        $this->assertCount(3, $options);

        // Default first, then alphabetical: the list is a menu, so the entry a
        // user gets by doing nothing has to be the one at the top.
        $this->assertSame($default->id, $options[0]['id']);
        $this->assertTrue($options[0]['is_default']);
        $this->assertSame(['Mid', 'Alpha', 'Zeta'], array_column($options, 'name'));
    }

    #[Test]
    public function the_picker_never_carries_the_api_key(): void
    {
        // The list goes into a Livewire payload, which reaches the browser.
        // `api_key` is an encrypted attribute and has no business being
        // decrypted for a dropdown label.
        $this->makeConfig(['api_key' => 'sk-super-secret', 'is_default' => true]);

        $options = $this->service()->listActive();

        $this->assertArrayNotHasKey('api_key', $options[0]);
        $this->assertStringNotContainsString('sk-super-secret', (string) json_encode($options));
    }

    #[Test]
    public function an_inactive_provider_is_not_offered(): void
    {
        $this->makeConfig(['name' => 'On', 'is_default' => true]);
        $this->makeConfig(['name' => 'Off', 'is_active' => false]);

        $this->assertSame(['On'], array_column($this->service()->listActive(), 'name'));
    }

    #[Test]
    public function an_explicit_choice_is_honoured(): void
    {
        $this->makeConfig(['name' => 'Default one', 'is_default' => true]);
        $chosen = $this->makeConfig(['name' => 'Chosen', 'provider' => 'xai', 'model' => 'grok-2']);

        $resolved = $this->service()->resolveForTurn($chosen->id);

        $this->assertSame($chosen->id, $resolved?->id);
        $this->assertSame('xai', $resolved?->provider);
    }

    #[Test]
    public function no_choice_falls_back_to_the_default(): void
    {
        $default = $this->makeConfig(['name' => 'Default one', 'is_default' => true]);
        $this->makeConfig(['name' => 'Other']);

        $this->assertSame($default->id, $this->service()->resolveForTurn(null)?->id);
    }

    #[Test]
    public function an_id_naming_a_disabled_provider_falls_back_instead_of_using_it(): void
    {
        // The security case. An administrator switching a provider off is a
        // deliberate act — perhaps its key was rotated or its billing stopped —
        // and a client that still holds the id must not be able to spend against
        // it. `active()` in resolveForTurn() is what enforces that.
        $default = $this->makeConfig(['name' => 'Live', 'is_default' => true]);
        $disabled = $this->makeConfig(['name' => 'Disabled', 'is_active' => false]);

        $resolved = $this->service()->resolveForTurn($disabled->id);

        $this->assertSame(
            $default->id,
            $resolved?->id,
            'Um id apontando para provedor inativo precisa cair no default, nunca ser usado.'
        );
    }

    #[Test]
    public function an_id_that_does_not_exist_falls_back_instead_of_failing(): void
    {
        // A tab left open across a config deletion. Degrading beats an error
        // message the user can do nothing about.
        $default = $this->makeConfig(['name' => 'Live', 'is_default' => true]);

        $this->assertSame($default->id, $this->service()->resolveForTurn(999999)?->id);
    }

    #[Test]
    public function the_list_cache_is_invalidated_when_a_config_changes(): void
    {
        // listActive() is cached, and the picker would otherwise keep offering a
        // provider that was just switched off — or hide one just added.
        $this->makeConfig(['name' => 'First', 'is_default' => true]);

        $this->assertCount(1, $this->service()->listActive());

        $this->service()->create([
            'name' => 'Second',
            'provider' => 'xai',
            'model' => 'grok-2',
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
        ]);

        $this->assertCount(2, $this->service()->listActive());
    }

    // ── The widget itself ───────────────────────────────────────────────────

    #[Test]
    public function the_widget_starts_on_the_default_provider(): void
    {
        config(['ptah.ai_agent.allow_guests' => true]);
        $this->makeConfig(['name' => 'Alpha']);
        $default = $this->makeConfig(['name' => 'Preferred', 'is_default' => true]);

        Livewire::test(AiChatWidget::class)
            ->assertSet('selectedConfigId', $default->id)
            ->assertCount('providerOptions', 2);
    }

    #[Test]
    public function the_picker_is_hidden_when_there_is_nothing_to_choose(): void
    {
        // One provider needs no control, and the widget panel is narrow enough
        // that an inert dropdown would just be noise.
        config(['ptah.ai_agent.allow_guests' => true]);
        $this->makeConfig(['name' => 'Only one', 'is_default' => true]);

        Livewire::test(AiChatWidget::class)
            ->assertCount('providerOptions', 1)
            ->assertDontSee('ptah-ai-provider');
    }

    #[Test]
    public function the_picker_is_shown_when_there_is_a_choice(): void
    {
        config(['ptah.ai_agent.allow_guests' => true]);
        $this->makeConfig(['name' => 'Grok', 'provider' => 'xai', 'model' => 'grok-2']);
        $this->makeConfig(['name' => 'GPT', 'is_default' => true]);

        Livewire::test(AiChatWidget::class)
            ->assertSee('ptah-ai-provider')
            ->assertSee('grok-2');
    }
}
