<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The Aparência pills preview the option they offer: the accent pills wear their own
 * colour, the font pills render their label in that scale's real ink, and the tone
 * pills are painted with that tone's own surface.
 *
 * The failure mode this guards is specific and quiet. If a preview rule reaches for
 * `var(--ptah-*)` instead of a literal, the token resolves to whatever the user has
 * CURRENTLY chosen — so all twelve pills render identically, every contrast assertion
 * still passes, the golden fixture still matches, and the feature is silently
 * decorative. Nothing else in this suite can see that.
 *
 * The tone pills are additionally NOT scoped by theme, on purpose: "Carvão" has to
 * look dark while the page is light, because that is the question the user is asking
 * when they look at it.
 */
class AppearancePreviewLiteralTest extends TestCase
{
    private static function css(): string
    {
        static $css = null;

        if ($css === null) {
            $read = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

            if ($read === false) {
                throw new RuntimeException('AppearancePreviewLiteralTest: falha ao ler ptah-components.css.');
            }

            $css = $read;
        }

        return $css;
    }

    /** Every `.ptah-swatch[...]` rule in the file, as selector => body. */
    private static function previewRules(): array
    {
        static $rules = null;

        if ($rules !== null) {
            return $rules;
        }

        $css = preg_replace('#/\*.*?\*/#s', '', self::css());

        if ($css === null) {
            throw new RuntimeException('AppearancePreviewLiteralTest: falha ao remover comentarios.');
        }

        $rules = [];

        if (preg_match_all('/([^{}\n]*\.ptah-swatch\[[^{}]*)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER)) {
            foreach ($m as $r) {
                $rules[trim(preg_replace('/\s+/', ' ', $r[1]) ?? $r[1])] = trim($r[2]);
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function groupProvider(): array
    {
        return [
            'cor da fonte' => ['data-ptah-text', ['suave', 'neutra', 'forte']],
            'cor de destaque' => ['data-ptah-accent', ['azul', 'violeta', 'ciano', 'verde', 'teal', 'ambar', 'vermelho', 'rosa', 'cinza']],
            'tom claro' => ['data-ptah-light', ['puro', 'papel', 'nevoa']],
            'tom escuro' => ['data-ptah-dark', ['carvao', 'grafite', 'meianoite']],
            'densidade' => ['data-ptah-density', ['compacta', 'confortavel', 'espacosa']],
            'tamanho de fonte' => ['data-ptah-fontsize', ['pequena', 'normal', 'grande']],
        ];
    }

    /**
     * @param  array<int, string>  $options
     */
    #[Test]
    #[DataProvider('groupProvider')]
    public function every_option_has_a_preview_rule(string $attribute, array $options): void
    {
        $selectors = array_keys(self::previewRules());

        foreach ($options as $option) {
            $needle = sprintf('.ptah-swatch[%s="%s"]', $attribute, $option);
            $found = false;

            foreach ($selectors as $selector) {
                if (str_contains($selector, $needle)) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue(
                $found,
                sprintf(
                    'Falta regra de preview para %s="%s". Sem ela, a pilula nao mostra a '.
                    'opcao que oferece e a pessoa escolhe no escuro.',
                    $attribute,
                    $option
                )
            );
        }
    }

    #[Test]
    public function no_preview_rule_uses_a_theme_token(): void
    {
        $offenders = [];

        foreach (self::previewRules() as $selector => $body) {
            if (str_contains($body, 'var(--ptah-')) {
                $offenders[] = $selector.' { '.$body.' }';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Regra de preview usando var(--ptah-*):\n".implode("\n", $offenders)."\n\n".
            'Um token resolve para a opcao ATUALMENTE escolhida, entao todas as pilulas '.
            'ficariam iguais — o preview vira decoracao. E nada mais na suite percebe: '.
            'contraste continua passando, a fixture continua batendo. Use literal.'
        );
    }

    /**
     * A tone pill scoped under .ptah-dark would only show its colour once the page is
     * already in that mode — useless for choosing. This asserts the tone previews stay
     * unscoped, which is the one place where NOT scoping is the correct answer.
     */
    #[Test]
    public function tone_previews_are_not_scoped_by_theme(): void
    {
        foreach (self::previewRules() as $selector => $body) {
            if (! preg_match('/data-ptah-(light|dark)=/', $selector)) {
                continue;
            }

            $this->assertStringNotContainsString(
                '.ptah-dark ',
                $selector,
                sprintf(
                    'A regra de preview de tom [%s] esta escopada por tema. Assim a pilula '.
                    '"Carvao" so fica escura quando a pagina JA esta escura, que e exatamente '.
                    'quando a pessoa nao precisa mais do preview.',
                    $selector
                )
            );
        }
    }
}
