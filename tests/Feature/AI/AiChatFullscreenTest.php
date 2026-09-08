<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * Full screen: always on a phone, on demand on the desktop.
 *
 * A 320px panel pinned to the corner of a 360px phone left the conversation in a
 * narrow column with the virtual keyboard over it. On a phone there is no
 * "corner" worth keeping, so the panel takes the screen and the toggle that
 * would switch between the two does not render — a control with only one
 * reachable state is worse than no control.
 *
 * The geometry is Tailwind `max-sm:` utilities in the view rather than a media
 * query in ptah-components.css, and that is a deliberate, previously-learned
 * choice: this project's golden-fixture CSS parser is not media-query aware and
 * has already recorded a wrong value because of one.
 *
 * The expanded flag lives in localStorage, not on the component. It is a
 * per-device preference that CSS resolves; putting it in the snapshot would buy
 * a request and a round trip for a class toggle.
 */
class AiChatFullscreenTest extends TestCase
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
            'is_active' => true,
            'is_default' => true,
            'max_tokens' => 1024,
            'temperature' => 0.7,
        ]);
    }

    private function html(): string
    {
        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        if (! str_contains($html, 'ptah-c-chat_panel')) {
            throw new RuntimeException('O painel do chat nao renderizou — nada abaixo teria alvo.');
        }

        return $html;
    }

    /**
     * The panel's own tag, so a class found on some other element cannot pass.
     */
    private function panel(): string
    {
        $html = $this->html();

        if (! preg_match('/<div\b[^>]*ptah-c-chat_panel[^>]*>/s', $html, $m)) {
            throw new RuntimeException('Nao consegui isolar a tag do painel.');
        }

        return $m[0];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function mobileGeometryProvider(): array
    {
        return [
            'ocupa a viewport' => ['max-sm:fixed'],
            'colada nas quatro bordas' => ['max-sm:inset-0'],
            'largura cheia' => ['max-sm:w-full'],
            'sem teto de altura' => ['max-sm:max-h-none'],
            'sem cantos arredondados' => ['max-sm:rounded-none'],
        ];
    }

    #[Test]
    #[DataProvider('mobileGeometryProvider')]
    public function the_phone_always_gets_the_whole_screen(string $utility): void
    {
        $this->assertStringContainsString(
            $utility,
            $this->panel(),
            "Sem `{$utility}` o painel volta a ser uma caixinha no canto do celular."
        );
    }

    #[Test]
    public function the_height_ceiling_can_be_overridden_by_a_utility(): void
    {
        // It used to be an inline `style="max-height: ..."`, which no class can
        // beat — so mobile full-screen was impossible without touching it.
        $panel = $this->panel();

        $this->assertStringNotContainsString('style="max-height', $panel);
        $this->assertStringContainsString('max-h-[min(560px,calc(100vh_-_100px))]', $panel);
    }

    #[Test]
    public function the_desktop_toggle_flips_the_same_geometry(): void
    {
        $panel = $this->panel();

        foreach (['sm:fixed', 'sm:inset-0', 'sm:w-full', 'sm:max-h-none', 'sm:rounded-none'] as $utility) {
            $this->assertStringContainsString($utility, $panel, "Falta `{$utility}` no estado expandido.");
        }

        $this->assertStringContainsString('expanded ?', $panel);
    }

    #[Test]
    public function the_toggle_is_absent_on_a_phone(): void
    {
        // There it has only one reachable state.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*expanded = !expanded[^>]*\bhidden sm:block\b/s',
            $this->html(),
            'O botao de expandir precisa ser hidden sm:block: no celular ja e tela cheia.'
        );
    }

    #[Test]
    public function the_preference_survives_a_reload_without_touching_the_server(): void
    {
        $html = $this->html();

        $this->assertStringContainsString("localStorage.getItem('ptah:ai:expanded')", $html);
        $this->assertStringContainsString("localStorage.setItem('ptah:ai:expanded'", $html);

        // A private window, or a browser set to block site data, throws on the
        // accessor itself — the panel must still open.
        $this->assertMatchesRegularExpression(
            '/localStorage\.getItem\(\'ptah:ai:expanded\'\).*?\}\s*catch/s',
            $html,
            'A leitura do localStorage precisa estar em try/catch.'
        );

        $this->assertFalse(
            property_exists(AiChatWidget::class, 'expanded'),
            'Preferencia de dispositivo nao precisa de propriedade no snapshot.'
        );
    }

    #[Test]
    public function the_floating_button_gets_out_of_the_way(): void
    {
        // Over a full-screen panel it covers content and becomes a second
        // "close" competing with the one in the header.
        $html = $this->html();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*x-show="!\(expanded && open\)"/s',
            $html,
            'O botao flutuante precisa sair quando o painel esta expandido.'
        );
        $this->assertMatchesRegularExpression(
            '/:class="open \? \'max-sm:hidden\' : \'\'"/',
            $html,
            'No celular o painel aberto ja e tela cheia — o flutuante tem de sair tambem.'
        );
    }

    #[Test]
    public function the_panel_is_announced_as_a_dialog_and_escape_closes_it(): void
    {
        $html = $this->html();
        $panel = $this->panel();

        $this->assertStringContainsString('role="dialog"', $panel);
        $this->assertStringContainsString('aria-label=', $panel);
        $this->assertStringContainsString('@keydown.escape.window="if (open) open = false"', $html);
    }

    #[Test]
    public function opening_the_panel_puts_the_caret_in_the_box(): void
    {
        // Full screen with no focus means a phone user taps twice to type.
        // Matched by the watcher's BODY, not its exact text: pinning the whole
        // line is what broke AiChatWidgetScrollTest when this very call was
        // added, and the guarantee here is "focusInput runs when open turns
        // true", not the formatting around it.
        $this->assertMatchesRegularExpression(
            '#\$watch\(.open.,[^\n]*focusInput\(\)#',
            $this->html()
        );
    }

    #[Test]
    public function the_keyboard_hint_is_tokenised(): void
    {
        // It was `text-gray-300 dark:text-slate-600` — about 1.5:1 on white in
        // light mode. A hint that cannot be read is not a hint.
        $html = $this->html();

        $this->assertStringContainsString('ptah-c-chat_hint', $html);
        $this->assertStringNotContainsString('text-gray-300', $html);

        $css = (string) file_get_contents(__DIR__.'/../../../resources/css/ptah-components.css');
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $this->assertMatchesRegularExpression('/\.ptah-c-chat_hint\s*\{[^}]*color:/', $css);
        $this->assertMatchesRegularExpression('/\.ptah-dark \.ptah-c-chat_hint\s*\{[^}]*color:/', $css);
    }
}
