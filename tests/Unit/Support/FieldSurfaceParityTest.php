<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The three field surfaces that appear side by side in one BaseCrud modal form
 * must resolve to the same background and border token in each scope:
 *
 *   .ptah-input-wrapper input   forge-input — Title, Description, dates
 *   .ptah-c-form_in             the inline text / searchdropdown input
 *   .ptah-c-form_sel            the select and searchdropdown trigger
 *
 * They did not, and light mode hid it for the life of the package:
 *
 *   resting bg   forge-input          form_in / form_sel
 *   light        --ptah-field         --ptah-field          → identical
 *   dark         --ptah-field-muted   --ptah-field          → visibly apart
 *
 * --ptah-field is #ffffff and --ptah-field-muted #f8fafc, so in light the wrong
 * token was indistinguishable; in dark they are ~12 RGB steps apart and the
 * selects read as a different control. Reported against 1.29.1 from the ERP:
 * "na versão clara está OK, mas na dark ele destoa dos outros inputs".
 *
 * This test exists because a *visual* discrepancy between two rules 700 lines
 * apart is invisible to every other guard in the suite: both sides used valid
 * tokens, contrast passed, the palette ceiling passed, nothing was hardcoded.
 * Only their EQUALITY was wrong, so equality is what gets asserted.
 */
class FieldSurfaceParityTest extends TestCase
{
    private const CSS_PATH = __DIR__.'/../../../resources/css/ptah-components.css';

    /**
     * The selectors that must agree, per scope.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function scopeProvider(): array
    {
        return [
            'light' => ['light', [
                '.ptah-input-wrapper input',
                '.ptah-c-form_in',
                '.ptah-c-form_sel',
            ]],
            'dark' => ['dark', [
                '.ptah-dark .ptah-input-wrapper input',
                '.ptah-dark .ptah-c-form_in',
                '.ptah-dark .ptah-c-form_sel',
            ]],
        ];
    }

    /**
     * @param  list<string>  $selectors
     */
    #[Test]
    #[DataProvider('scopeProvider')]
    public function every_field_surface_shares_one_resting_recipe(string $scope, array $selectors): void
    {
        foreach (['background-color', 'border-color'] as $property) {
            $seen = [];

            foreach ($selectors as $selector) {
                $seen[$selector] = self::tokenFor($selector, $property);
            }

            $distinct = array_values(array_unique(array_filter(
                $seen,
                static fn (?string $token): bool => $token !== null
            )));

            $this->assertCount(
                1,
                $distinct,
                sprintf(
                    'Escopo %s: as tres superficies de campo divergem em %s, entao um select fica visivelmente '.
                    "diferente de um input ao lado dele no mesmo formulario.\n%s\n".
                    'Alinhe todas na receita do forge-input (--ptah-field no claro, --ptah-field-muted no escuro, '.
                    '--ptah-line-field nos dois) — ver o comentario acima de .ptah-c-form_in em ptah-components.css.',
                    $scope,
                    $property,
                    implode("\n", array_map(
                        static fn (string $sel, ?string $tok): string => sprintf('  %-42s %s', $sel, $tok ?? '(nao declarado)'),
                        array_keys($seen),
                        $seen
                    ))
                )
            );
        }
    }

    #[Test]
    public function a_resting_field_uses_the_resting_token_in_dark(): void
    {
        // Not just equal — equal to the RIGHT one. The tokens document their own
        // roles ("active/focused input bg" vs "resting (unfocused) input bg"),
        // so three surfaces agreeing on the focus token would satisfy the test
        // above while still being wrong.
        foreach (['.ptah-dark .ptah-input-wrapper input', '.ptah-dark .ptah-c-form_in', '.ptah-dark .ptah-c-form_sel'] as $selector) {
            $this->assertSame(
                '--ptah-field-muted',
                self::tokenFor($selector, 'background-color'),
                "{$selector}: um campo em repouso usa --ptah-field-muted; --ptah-field e o fundo de campo FOCADO."
            );
        }
    }

    /**
     * Reads the var(--token) a selector declares for one property.
     *
     * Comments are stripped first, and that is not incidental: the block above
     * `.ptah-c-form_in` in the stylesheet names --ptah-field, --ptah-line-control
     * and --ptah-field-muted in prose to explain this very bug. A scanner that
     * reads comments matches its own documentation — a mistake this suite has
     * made more than once.
     */
    private static function tokenFor(string $selector, string $property): ?string
    {
        $css = file_get_contents(self::CSS_PATH);

        if ($css === false) {
            throw new RuntimeException('FieldSurfaceParityTest: falha ao ler '.self::CSS_PATH);
        }

        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        // The exact selector only, never a longer one that merely starts with
        // it: `.ptah-c-form_in:focus` and `.ptah-c-form_in::placeholder` are
        // different states and would otherwise be read as the resting rule.
        $pattern = '/(?:^|\})\s*'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m';

        if (preg_match($pattern, $css, $match) !== 1) {
            return null;
        }

        if (preg_match('/'.preg_quote($property, '/').'\s*:\s*var\(\s*(--[a-z0-9-]+)/i', $match[1], $decl) !== 1) {
            return null;
        }

        return $decl[1];
    }
}
