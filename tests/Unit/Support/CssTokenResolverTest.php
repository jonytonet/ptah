<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Tests\Support\CssTokenResolver;
use RuntimeException;

/**
 * Unit tests for the test-only CssTokenResolver helper (tests/Support), which
 * NeutralTokenBaselineTest relies on to prove the Fase 1 CSS token refactor
 * causes zero visual change. Exercised in isolation here so a bug in the
 * resolver itself cannot masquerade as "the CSS is fine".
 */
final class CssTokenResolverTest extends TestCase
{
    private const SAMPLE_CSS = <<<'CSS'
        :root {
            --ptah-line: #e2e8f0;
            --ptah-surface: #ffffff;
            --ptah-alias-of-line: var(--ptah-line);
        }

        .ptah-dark {
            --ptah-line: #334155;
        }
    CSS;

    #[Test]
    public function parses_light_tokens_from_root(): void
    {
        $tokens = CssTokenResolver::parseTokens(self::SAMPLE_CSS);

        $this->assertSame('#e2e8f0', $tokens['light']['--ptah-line']);
        $this->assertSame('#ffffff', $tokens['light']['--ptah-surface']);
    }

    #[Test]
    public function dark_scope_inherits_undeclared_tokens_from_light(): void
    {
        $tokens = CssTokenResolver::parseTokens(self::SAMPLE_CSS);

        // Redeclared in .ptah-dark:
        $this->assertSame('#334155', $tokens['dark']['--ptah-line']);
        // NOT redeclared in .ptah-dark -> inherited from :root:
        $this->assertSame('#ffffff', $tokens['dark']['--ptah-surface']);
    }

    #[Test]
    public function does_not_confuse_a_bare_ptah_dark_block_with_compound_selectors(): void
    {
        $css = self::SAMPLE_CSS."\n.ptah-dark .ptah-c-toolbar { --ptah-line: #000000; }\n";
        $tokens = CssTokenResolver::parseTokens($css);

        // The compound-selector rule must NOT be read as the token override block.
        $this->assertSame('#334155', $tokens['dark']['--ptah-line']);
    }

    #[Test]
    public function resolve_substitutes_a_ptah_var_with_its_scoped_value(): void
    {
        $resolver = new CssTokenResolver(self::SAMPLE_CSS);

        $this->assertSame('#e2e8f0', $resolver->resolve('var(--ptah-line)', 'light'));
        $this->assertSame('#334155', $resolver->resolve('var(--ptah-line)', 'dark'));
    }

    #[Test]
    public function resolve_recurses_through_a_token_that_points_to_another_token(): void
    {
        $resolver = new CssTokenResolver(self::SAMPLE_CSS);

        $this->assertSame('#e2e8f0', $resolver->resolve('var(--ptah-alias-of-line)', 'light'));
        $this->assertSame('#334155', $resolver->resolve('var(--ptah-alias-of-line)', 'dark'));
    }

    #[Test]
    public function resolve_leaves_non_ptah_var_references_untouched(): void
    {
        $css = ':root { --ptah-primary: var(--color-primary, #5b21b6); }';
        $resolver = new CssTokenResolver($css);

        $this->assertSame('var(--color-primary, #5b21b6)', $resolver->resolve('var(--ptah-primary)', 'light'));
    }

    #[Test]
    public function resolve_leaves_plain_literals_untouched(): void
    {
        $resolver = new CssTokenResolver(self::SAMPLE_CSS);

        $this->assertSame('#123456', $resolver->resolve('#123456', 'light'));
        $this->assertSame('1px solid #94a3b8', $resolver->resolve('1px solid #94a3b8', 'light'));
    }

    #[Test]
    public function resolve_throws_on_an_undefined_token(): void
    {
        $resolver = new CssTokenResolver(self::SAMPLE_CSS);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nao definido');

        $resolver->resolve('var(--ptah-does-not-exist)', 'light');
    }

    #[Test]
    public function resolve_throws_on_an_unknown_scope(): void
    {
        $resolver = new CssTokenResolver(self::SAMPLE_CSS);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('escopo desconhecido');

        $resolver->resolve('var(--ptah-line)', 'sepia');
    }

    #[Test]
    public function resolve_throws_on_a_reference_cycle(): void
    {
        $css = ':root { --ptah-a: var(--ptah-b); --ptah-b: var(--ptah-a); }';
        $resolver = new CssTokenResolver($css);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ciclo detectado');

        $resolver->resolve('var(--ptah-a)', 'light');
    }

    /**
     * Proves the color-mix -> rgba() equivalence by hand-computing a few
     * cases: per CSS Color 4, mixing `in srgb` against `transparent`
     * (= rgb(0 0 0 / 0)) in premultiplied-alpha space keeps the opaque
     * color's channels unchanged and sets the resulting alpha to the mix
     * percentage — because transparent contributes 0 to every premultiplied
     * channel regardless of its own weight.
     */
    #[Test]
    public function compute_mix_converts_color_mix_against_transparent_to_the_equivalent_rgba(): void
    {
        $this->assertSame(
            'rgba(248, 250, 252, 0.6)',
            CssTokenResolver::computeMix('color-mix(in srgb, #f8fafc 60%, transparent)')
        );
        $this->assertSame(
            'rgba(30, 41, 59, 0.25)',
            CssTokenResolver::computeMix('color-mix(in srgb, #1e293b 25%, transparent)')
        );
        $this->assertSame(
            'rgba(51, 65, 85, 0.07)',
            CssTokenResolver::computeMix('color-mix(in srgb, #334155 7%, transparent)')
        );
    }

    #[Test]
    public function compute_mix_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame(
            'rgba(15, 23, 42, 0.5)',
            CssTokenResolver::computeMix('COLOR-MIX( IN SRGB,  #0F172A   50%  ,  TRANSPARENT )')
        );
    }

    #[Test]
    public function compute_mix_returns_the_expression_unchanged_when_the_first_argument_is_not_yet_a_literal(): void
    {
        $expr = 'color-mix(in srgb, var(--color-primary, #5b21b6) 15%, transparent)';

        $this->assertSame($expr, CssTokenResolver::computeMix($expr));
    }

    #[Test]
    public function compute_mix_returns_non_matching_values_unchanged(): void
    {
        $this->assertSame('#ffffff', CssTokenResolver::computeMix('#ffffff'));
        $this->assertSame('1px solid #94a3b8', CssTokenResolver::computeMix('1px solid #94a3b8'));
    }

    #[Test]
    public function normalize_lowercases_and_expands_shorthand_hex(): void
    {
        $this->assertSame('#aabbcc', CssTokenResolver::normalize('#ABC'));
        $this->assertSame('#e2e8f0', CssTokenResolver::normalize('#E2E8F0'));
    }

    #[Test]
    public function normalize_converts_hex_with_alpha_to_rgba(): void
    {
        // #f8fafc66 -> rgb(248,250,252), alpha = 0x66/255 = 0.4
        $this->assertSame('rgba(248, 250, 252, 0.4)', CssTokenResolver::normalize('#f8fafc66'));
    }

    #[Test]
    public function normalize_collapses_whitespace_and_leading_dot_decimals(): void
    {
        $this->assertSame('rgba(15, 23, 42, 0.04)', CssTokenResolver::normalize('rgba(15,23,42,.04)'));
        $this->assertSame('0 1px 2px rgba(15, 23, 42, 0.04)', CssTokenResolver::normalize('0   1px  2px rgba(15,23,42,.04)'));
    }
}
