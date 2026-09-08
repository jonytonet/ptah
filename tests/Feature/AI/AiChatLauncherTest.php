<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * Getting the floating button out of the way.
 *
 * A 56px circle in the bottom-right corner sits exactly over a listing's
 * pagination and its last column — "para não atrapalhar a visualização de
 * tabelas". The package already reserved space at the end of the listing for it
 * (`.ptah-has-ai-launcher .ptah-crud-list-end`), which helps and does not
 * answer the case where someone simply does not want the chat nearby.
 *
 * Hiding it does NOT remove it. It becomes a thin handle on the right edge: one
 * element in two states rather than two elements, because a launcher that
 * vanishes entirely leaves the chat with no way back — and the way back has to
 * be discoverable by someone who does not remember hiding it.
 *
 * The reserved space goes away with it, or it would be a gap with no reason.
 */
class AiChatLauncherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.ai_agent.allow_guests' => true]);

        AiModelConfig::create([
            'name' => 'Padrao',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function html(): string
    {
        $html = Livewire::test(AiChatWidget::class)->html();

        if (! str_contains($html, 'bx-bot')) {
            throw new RuntimeException('O lancador nao renderizou — nada abaixo teria alvo.');
        }

        return $html;
    }

    #[Test]
    public function the_launcher_can_be_dismissed(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*@click\.stop="launcherHidden = true"/s',
            $html,
            'Falta o controle de esconder.'
        );
        // .stop, or the click reaches the launcher underneath and opens the chat
        // on the way out.
        $this->assertStringContainsString('@click.stop="launcherHidden = true"', $html);
    }

    #[Test]
    public function the_dismiss_control_is_reachable_without_a_mouse(): void
    {
        // It is opacity-0 at rest. Without focus-within it would be a control
        // that only exists for people who hover.
        $html = $this->html();

        $this->assertStringContainsString('group-focus-within:opacity-100', $html);
        $this->assertMatchesRegularExpression(
            '/<div class="group relative"/',
            $html,
            'O `group` precisa estar no envelope, senao group-hover no dispensar nunca dispara.'
        );
    }

    #[Test]
    public function the_dismiss_control_is_absent_while_the_panel_is_open(): void
    {
        // With the panel open the launcher is already the "close" button, and
        // hiding it from under an open panel would leave the person wondering
        // where it went.
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/x-show="!open"\s*\n\s*@click\.stop="launcherHidden = true"/s',
            $html
        );
    }

    #[Test]
    public function hiding_it_leaves_a_handle_that_brings_it_back(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('ptah-c-chat_handle', $html);
        $this->assertStringContainsString('x-show="launcherHidden"', $html);
        // And it opens the chat, not merely restores the button: someone
        // reaching for the handle wants the assistant.
        $this->assertStringContainsString('launcherHidden = false; open = true', $html);
    }

    #[Test]
    public function the_handle_is_announced(): void
    {
        // Two and a half pixels wide at 35% opacity is invisible without a
        // label.
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/ptah-c-chat_handle[^>]*/s',
            $html
        );
        $this->assertStringContainsString(__('ptah::ui.ai_widget_show_launcher'), $html);
    }

    #[Test]
    public function the_handle_is_fixed_to_the_window_not_nested_in_the_corner(): void
    {
        // Inside the `fixed bottom-6 right-6` wrapper it would inherit the 24px
        // offset and land back over the content it exists to clear.
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/ptah-c-chat_handle fixed right-0 top-1\/2/',
            $html
        );
    }

    #[Test]
    public function the_reserved_space_goes_away_with_the_launcher(): void
    {
        // `.ptah-has-ai-launcher` is stamped on <body> by the layout for the
        // button that is no longer there; left behind it is a gap at the end of
        // every listing with nothing in it.
        $html = $this->html();

        $this->assertStringContainsString(
            "document.body.classList.toggle('ptah-has-ai-launcher', !value)",
            $html
        );
    }

    #[Test]
    public function the_choice_survives_a_reload_without_touching_the_server(): void
    {
        $html = $this->html();

        $this->assertStringContainsString("localStorage.getItem('ptah:ai:launcher-hidden')", $html);
        $this->assertStringContainsString("localStorage.setItem('ptah:ai:launcher-hidden'", $html);

        $this->assertFalse(
            property_exists(AiChatWidget::class, 'launcherHidden'),
            'Preferencia de dispositivo nao precisa de propriedade no snapshot.'
        );
    }

    #[Test]
    public function the_handle_and_the_launcher_are_never_both_on_screen(): void
    {
        // They are the same affordance in two states.
        $html = $this->html();

        $this->assertStringContainsString('x-show="!launcherHidden"', $html);
        $this->assertStringContainsString('x-show="launcherHidden"', $html);
    }

    #[Test]
    public function double_clicking_the_header_toggles_full_screen(): void
    {
        // The button in the header is 20px of white at 70% opacity on purple.
        // Double-clicking a title bar is the gesture people try first, and it
        // costs one attribute.
        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        $this->assertStringContainsString(
            '@dblclick="if (window.innerWidth >= 640) expanded = !expanded"',
            $html,
            'O duplo clique precisa ser guardado por largura: no celular ja e tela cheia.'
        );
    }
}
