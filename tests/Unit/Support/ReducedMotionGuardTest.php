<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * FIX 5 of the Onda C ux-acl-tree audit: `@media (prefers-reduced-motion:
 * reduce)` in ptah-components.css silences every transition/animation in the
 * package (sidebar collapse, chevrons, toast enter/leave, skeleton pulse) via
 * a universal `*, *::before, *::after` rule — but a frozen spinner reads as
 * "the screen is stuck", not as "no unnecessary motion", so
 * animate-spin/-bounce/-wave are explicitly exempted and keep animating.
 *
 * `.001ms`, not `0ms`, on the universal rule: an Alpine `x-transition`
 * (forge-toast-host, the sidebar overlay, every forge-modal) waits for the
 * `transitionend` event to finish its enter/leave sequence, and `0ms` never
 * fires that event.
 */
class ReducedMotionGuardTest extends TestCase
{
    #[Test]
    public function the_universal_rule_uses_a_near_zero_duration_not_literal_zero(): void
    {
        $css = self::css();

        $this->assertMatchesRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{\s*\*, \*::before, \*::after \{[^}]*animation-duration: \.001ms !important;/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/@media \(prefers-reduced-motion: reduce\) \{\s*\*, \*::before, \*::after \{[^}]*animation-duration: 0ms/',
            $css
        );
    }

    #[Test]
    public function spinners_are_exempted_and_keep_a_real_animation_duration(): void
    {
        $css = self::css();
        $block = self::reducedMotionBlock($css);

        $this->assertMatchesRegularExpression(
            '/\.animate-spin, \.animate-bounce, \.animate-wave \{\s*animation-duration: 1s !important;\s*animation-iteration-count: infinite !important;\s*\}/',
            $block
        );
    }

    #[Test]
    public function only_one_reduced_motion_media_query_exists(): void
    {
        $css = self::css();

        $this->assertSame(
            1,
            preg_match_all('/@media \(prefers-reduced-motion: reduce\)/', $css),
            'Deveria existir exatamente um bloco @media (prefers-reduced-motion: reduce) — '.
            'um segundo bloco duplicaria a regra universal sem motivo.'
        );
    }

    private static function reducedMotionBlock(string $css): string
    {
        $start = strpos($css, '@media (prefers-reduced-motion: reduce)');

        if ($start === false) {
            throw new RuntimeException('ReducedMotionGuardTest: bloco @media (prefers-reduced-motion: reduce) nao encontrado.');
        }

        $bracePos = strpos($css, '{', $start);
        $depth = 0;
        $len = strlen($css);

        for ($i = $bracePos; $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($css, $start, $i + 1 - $start);
                }
            }
        }

        throw new RuntimeException('ReducedMotionGuardTest: chave de fechamento nao encontrada.');
    }

    private static function css(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('ReducedMotionGuardTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}
