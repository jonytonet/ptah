<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\AI\ChatMarkdown;
use Ptah\Tests\TestCase;

/**
 * Rendering an assistant answer.
 *
 * The bubble was `nl2br(e($content))`, so a model answering with a table or a
 * code block put the syntax on screen as literal characters. Models answer in
 * Markdown whether or not the client renders it.
 *
 * The interesting half of this class is what the renderer must REFUSE. The
 * input is text a remote model produced, steered by whatever the user typed and
 * by whatever a tool returned — it is untrusted, and it is about to be printed
 * with `{!! !!}`. So: HTML escaped rather than executed, and dangerous URL
 * schemes dropped, which HTML escaping does NOT do because the scheme sits
 * inside an attribute value that escaping leaves intact. Same hole the row
 * actions had.
 */
class ChatMarkdownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ChatMarkdown::flush();
    }

    #[Test]
    public function it_renders_the_structures_a_model_actually_uses(): void
    {
        $html = ChatMarkdown::render(
            "Pedidos abertos:\n\n".
            "| Cliente | Total |\n".
            "| --- | --- |\n".
            "| Acme | 1.200 |\n\n".
            "1. Conferir\n".
            "2. Faturar\n\n".
            'Use `php artisan ptah:config` e veja **isto**.'
        );

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<td>Acme</td>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<code>php artisan ptah:config</code>', $html);
        $this->assertStringContainsString('<strong>isto</strong>', $html);

        // The point of the whole change: none of the syntax survives as text.
        $this->assertStringNotContainsString('**isto**', $html);
        $this->assertStringNotContainsString('| --- |', $html);
    }

    #[Test]
    public function a_fenced_code_block_keeps_its_content_verbatim(): void
    {
        $html = ChatMarkdown::render("```php\n\$a = 1 < 2 && 3 > 2;\n```");

        $this->assertStringContainsString('<pre>', $html);
        // Escaped inside the block, not interpreted as markup.
        $this->assertStringContainsString('1 &lt; 2 &amp;&amp; 3 &gt; 2', $html);
    }

    #[Test]
    public function embedded_html_is_shown_rather_than_executed(): void
    {
        // Escaped, not stripped: stripping silently rewrites what the model
        // said, escaping shows it.
        $html = ChatMarkdown::render('Olha isso: <script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function an_html_img_with_an_onerror_handler_does_not_reach_the_dom(): void
    {
        $html = ChatMarkdown::render('<img src=x onerror="alert(1)">');

        // A tag inteira sai escapada, entao nada disso e um no do DOM: o
        // `onerror=` continua no texto porque e texto, e e assim que tem de
        // ser. Uma versao anterior desta asserticao exigia a AUSENCIA da
        // string, o que confundia "inerte" com "censurado".
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=', $html);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dangerousSchemeProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(document.cookie)'],
            'javascript com maiuscula' => ['JaVaScRiPt:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:msgbox(1)'],
        ];
    }

    #[Test]
    #[DataProvider('dangerousSchemeProvider')]
    public function a_dangerous_link_scheme_is_refused(string $href): void
    {
        // HTML escaping does not close this: the scheme lives inside an
        // attribute value, and the parser decodes entities before following it.
        $html = ChatMarkdown::render("[clique aqui]({$href})");

        $this->assertStringNotContainsString('href="'.$href.'"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/href="\s*(?:javascript|data|vbscript)\s*:/i',
            $html,
            "O esquema perigoso sobreviveu no href: {$href}"
        );
    }

    #[Test]
    public function an_ordinary_link_still_works(): void
    {
        // The counterpart: the guard above must not make every link inert.
        $html = ChatMarkdown::render('[o guia](https://exemplo.test/guia)');

        $this->assertStringContainsString('href="https://exemplo.test/guia"', $html);
    }

    #[Test]
    public function plain_text_keeps_its_line_breaks(): void
    {
        // GitHub-flavoured: a single newline inside a paragraph is a <br>, which
        // is what the old nl2br did and what people expect from a chat.
        $html = ChatMarkdown::render("linha um\nlinha dois");

        $this->assertStringContainsString('<br />', $html);
    }

    #[Test]
    public function empty_input_renders_nothing(): void
    {
        $this->assertSame('', ChatMarkdown::render(''));
        $this->assertSame('', ChatMarkdown::render("   \n  "));
    }

    #[Test]
    public function an_unfinished_fence_still_renders(): void
    {
        // Streaming calls this on every delta, so most calls receive a
        // half-written document.
        $html = ChatMarkdown::render("segue o codigo:\n```php\n\$a = 1;");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('$a = 1;', $html);
    }

    #[Test]
    public function the_same_text_is_rendered_once(): void
    {
        // Streaming re-renders the same growing prefix dozens of times.
        $text = '**um** dois tres';

        $first = ChatMarkdown::render($text);

        $this->assertSame($first, ChatMarkdown::render($text));
    }

    #[Test]
    public function the_cache_does_not_grow_without_bound(): void
    {
        // A long stream produces one entry per delta.
        for ($i = 0; $i < 200; $i++) {
            ChatMarkdown::render("texto numero {$i}");
        }

        $reflected = new \ReflectionClass(ChatMarkdown::class);
        $cache = $reflected->getStaticPropertyValue('cache');

        $this->assertIsArray($cache);
        $this->assertLessThanOrEqual(
            (int) $reflected->getConstant('CACHE_LIMIT'),
            count($cache),
            'O cache do renderizador cresceria sem teto ao longo de um streaming longo.'
        );
    }

    #[Test]
    public function the_user_bubble_is_not_rendered_as_markdown(): void
    {
        // What a person typed must not become HTML just because it looked like
        // markdown — and their message is the one input a real attacker
        // controls directly.
        $view = (string) file_get_contents(
            __DIR__.'/../../../resources/views/livewire/ai/ai-chat-widget.blade.php'
        );

        // Casado pela CHAMADA, nao pelo bloco em volta: os chips de anexo
        // entraram entre a abertura da bolha e o conteudo, e o ancoramento
        // anterior no atributo class quebrou por isso. O que importa e que o
        // conteudo do usuario passe por nl2br(e(...)) e nunca pelo renderizador
        // de markdown.
        $this->assertMatchesRegularExpression(
            '/\{!! nl2br\(e\(\$msg\[.content.\]\)\) !!\}/',
            $view,
            'A bolha do usuario precisa continuar em texto escapado.'
        );

        // E que a unica bolha renderizada como markdown seja a do assistente.
        $this->assertSame(
            1,
            preg_match_all('/ChatMarkdown::render\(\$msg\[.content.\]\)/', $view),
            'Markdown deve ser aplicado em exatamente uma bolha: a do assistente.'
        );
    }

    #[Test]
    public function the_streamed_text_uses_the_same_renderer_as_the_final_bubble(): void
    {
        // Otherwise the answer changes appearance the instant streaming ends.
        $widget = (string) file_get_contents(__DIR__.'/../../../src/Livewire/AI/AiChatWidget.php');

        $this->assertStringContainsString('ChatMarkdown::render($accumulated)', $widget);
        $this->assertStringNotContainsString('nl2br(e($accumulated))', $widget);
    }
}
