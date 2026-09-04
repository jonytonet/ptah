<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * `<x-forge-select>` had no visible error state at all.
 *
 * The component set `$borderNormal = 'border-red-400'` when validation failed,
 * and that utility never applied: `.ptah-select-trigger` declares
 * `border-color` unlayered, and an unlayered rule beats a layered Tailwind
 * utility whatever the specificity. Measured in the built stylesheet, an errored
 * trigger and a valid one rendered the SAME border — rgb(143,149,160) in both
 * cases. The control also set no `aria-invalid`, so assistive technology had
 * nothing either.
 *
 * forge-input had the identical defect and it was fixed there, with a comment
 * explaining the specificity trap; the fix was never mirrored onto the select.
 * This pins both halves so it cannot go quiet again.
 */
class SelectErrorBorderTest extends TestCase
{
    private const CSS = __DIR__.'/../../../resources/css/ptah-components.css';

    private const VIEW = __DIR__.'/../../../resources/views/components/forge-select.blade.php';

    private static function css(): string
    {
        $css = file_get_contents(self::CSS);

        if ($css === false) {
            throw new RuntimeException('SelectErrorBorderTest: falha ao ler ptah-components.css');
        }

        // Comments first: this file explains the defect in prose, and a scanner
        // that reads its own documentation matches nothing useful.
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    private static function view(): string
    {
        $view = file_get_contents(self::VIEW);

        if ($view === false) {
            throw new RuntimeException('SelectErrorBorderTest: falha ao ler forge-select.blade.php');
        }

        return (string) preg_replace('#\{\{--.*?--\}\}#s', '', $view);
    }

    #[Test]
    public function the_trigger_marks_itself_invalid_when_there_is_a_validation_message(): void
    {
        // The attribute is what the CSS rule below hooks onto, and it is the
        // accessibility signal in its own right.
        $view = self::view();

        $this->assertStringContainsString(
            'aria-invalid="true"',
            $view,
            'O gatilho do forge-select precisa marcar aria-invalid — e o gancho da regra CSS e o sinal para leitor de tela.'
        );

        $this->assertStringContainsString(
            'aria-describedby',
            $view,
            'A mensagem de erro precisa estar associada ao controle.'
        );
    }

    #[Test]
    public function the_error_border_exists_in_both_scopes(): void
    {
        $css = self::css();

        $this->assertMatchesRegularExpression(
            '/\.ptah-select-trigger\[aria-invalid="true"\]\s*\{[^}]*border-color:\s*var\(--ptah-danger-strong\)/',
            $css,
            'Falta a borda de erro do forge-select no claro.'
        );

        // Dark deliberately uses raw --color-danger, not --ptah-danger-strong:
        // the latter reads fine on a light field (6.11:1) but fails the 3:1
        // component floor against a dark one. Same split the aria-invalid block
        // for the inputs already documents.
        $this->assertMatchesRegularExpression(
            '/\.ptah-dark \.ptah-select-trigger\[aria-invalid="true"\]\s*\{[^}]*border-color:\s*var\(--color-danger\)/',
            $css,
            'Falta a borda de erro do forge-select no escuro (ou usa o token errado para esse fundo).'
        );
    }

    #[Test]
    public function no_fixed_palette_border_utility_came_back_into_the_component(): void
    {
        // The inert utilities are gone; if one returns it would once again look
        // like the error state was handled while changing nothing on screen.
        $view = self::view();

        preg_match_all(
            '/border-(?:[xylrtbse]-)?(white|black|slate|gray|zinc|neutral|stone|red|orange|amber|yellow|green|blue|indigo)(-\d+)?/',
            $view,
            $matches
        );

        $this->assertSame(
            [],
            $matches[0],
            'Utilitario(s) de borda de paleta fixa de volta no forge-select: '.implode(', ', $matches[0]).
            ' — eles nao aplicam (regra sem layer vence) e mascaram o estado real.'
        );
    }

    #[Test]
    public function the_resting_border_still_comes_from_the_shared_field_token(): void
    {
        // The counterpart: removing the utilities must not have left the trigger
        // with no border at all, and it must use the same token as every other
        // field surface (see FieldSurfaceParityTest).
        $this->assertMatchesRegularExpression(
            '/\.ptah-select-trigger\s*\{[^}]*border-color:\s*var\(--ptah-line-field\)/',
            self::css()
        );
    }
}
