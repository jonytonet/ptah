<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Invariant guard: <html> must be the ONLY element carrying `.ptah-dark`/`.dark`
 * in resources/views/components/forge-dashboard-layout.blade.php.
 *
 * Why this matters: the appearance presets added to ptah-components.css select
 * dark tones via `html.ptah-dark[data-ptah-dark="..."]`. Custom properties
 * resolve PER ELEMENT — if `.ptah-dark` were also applied to <body> (as it used
 * to be, to cover @@teleport('body') content), that selector would not match
 * <body>, so <body> would fall back to the bare `.ptah-dark { ... }` token
 * block instead and every dark-tone preset would silently be inert on 100% of
 * visible content. @custom-variant dark (&:where(.ptah-dark, .ptah-dark *)) in
 * forge.css already covers the whole subtree from <html> alone, teleported
 * content included, so there is no functional reason to ever reintroduce the
 * body class — but nothing else stops someone from doing it "for safety".
 *
 * Pure text-matching over the Blade source, no app boot needed.
 */
class ThemeCarrierTest extends TestCase
{
    private static function layoutBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(
            dirname(__DIR__, 3).'/resources/views/components/forge-dashboard-layout.blade.php'
        );
    }

    #[Test]
    public function the_layout_never_toggles_ptah_dark_or_dark_on_document_body(): void
    {
        $blade = self::layoutBlade();

        $this->assertDoesNotMatchRegularExpression(
            '/document\.body\.classList\.(toggle|add)\(\s*[\'"](ptah-dark|dark)[\'"]/',
            $blade,
            'forge-dashboard-layout.blade.php voltou a aplicar .ptah-dark/.dark em document.body. '.
            'Os presets de tom escuro (html.ptah-dark[data-ptah-dark="..."]) so casam em <html> — '.
            'reintroduzir a classe no body faz o body herdar os tokens do bloco `.ptah-dark` NU em '.
            'ptah-components.css, silenciosamente (sem erro, sem warning, so a cor errada).'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/body\.ptah-dark|body\.dark(?![a-z-])/',
            $blade,
            'Nao pode existir nenhuma regra CSS "body.ptah-dark" ou "body.dark" no layout — '.
            '<html> e o unico portador da classe.'
        );
    }

    #[Test]
    public function the_root_x_data_div_never_binds_ptah_dark_or_dark_via_class_directive(): void
    {
        $blade = self::layoutBlade();

        $this->assertDoesNotMatchRegularExpression(
            '/:class\s*=\s*"[^"]*\bptah-dark\b[^"]*"/',
            $blade,
            'A div raiz (x-data) voltou a ter um `:class` que inclui "ptah-dark". Isso reintroduz '.
            'a classe em um segundo elemento (o wrapper de layout, filho de <body>), o que e a '.
            'mesma quebra descrita acima — <html> deve ser o UNICO portador.'
        );
    }

    #[Test]
    public function document_element_still_receives_both_classes_via_apply_theme(): void
    {
        $blade = self::layoutBlade();

        $this->assertMatchesRegularExpression(
            '/document\.documentElement\.classList\.toggle\(\s*[\'"]ptah-dark[\'"]/',
            $blade,
            'applyTheme() deixou de alternar .ptah-dark em document.documentElement — sem isso, '.
            'nenhum preset de tom escuro (nem o dark mode em si) e aplicado.'
        );

        $this->assertMatchesRegularExpression(
            '/document\.documentElement\.classList\.toggle\(\s*[\'"]dark[\'"]/',
            $blade,
            'applyTheme() deixou de alternar .dark em document.documentElement — @custom-variant dark '.
            '(forge.css) depende dela para as utilidades dark: do Tailwind.'
        );
    }
}
