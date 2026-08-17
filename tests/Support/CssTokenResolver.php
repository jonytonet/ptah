<?php

declare(strict_types=1);

namespace Ptah\Tests\Support;

use RuntimeException;

/**
 * Small, framework-free helper used ONLY by tests to prove that tokenizing the
 * neutral colors in resources/css/ptah-components.css (Fase 1 of the restyle)
 * produces byte-for-byte identical resolved values to the previous literal hex
 * values — i.e. zero visual change.
 *
 * Not shipped: lives under tests/ (autoload-dev only, see composer.json), and
 * tests/ is not part of any phpunit <testsuite>, so this class is never
 * collected as a test itself.
 */
final class CssTokenResolver
{
    private const MAX_DEPTH = 20;

    /** @var array{light: array<string, string>, dark: array<string, string>} */
    private array $tokens;

    public function __construct(string $css)
    {
        $this->tokens = self::parseTokens($css);
    }

    /**
     * Reads the `:root { ... }` block and the first bare `.ptah-dark { ... }`
     * block (i.e. NOT `.ptah-dark .some-class { ... }`) from $css and returns
     * the `--ptah-*` custom properties declared in each. The "dark" scope
     * inherits from "light" every token it does not redeclare.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function parseTokens(string $css): array
    {
        $css = self::stripComments($css);

        $light = self::parseDeclarationBlock($css, '/:root\s*\{([^}]*)\}/');
        $darkOverrides = self::parseDeclarationBlock($css, '/\.ptah-dark\s*\{([^}]*)\}/');

        return [
            'light' => $light,
            'dark' => array_merge($light, $darkOverrides),
        ];
    }

    /**
     * Substitutes every `var(--ptah-*)` reference found in $value, recursively,
     * against the token map for the given scope ('light' or 'dark'). Any other
     * var() reference (e.g. the host-provided `var(--color-primary, #5b21b6)`)
     * is left untouched, since it is out of this resolver's namespace.
     *
     * Throws on: unknown scope, undefined token, or a reference cycle.
     */
    public function resolve(string $value, string $scope, int $depth = 0, array $trail = []): string
    {
        if ($scope !== 'light' && $scope !== 'dark') {
            throw new RuntimeException(sprintf('CssTokenResolver: escopo desconhecido "%s" (use "light" ou "dark").', $scope));
        }

        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException(sprintf('CssTokenResolver: profundidade maxima excedida ao resolver "%s".', $value));
        }

        $result = preg_replace_callback(
            '/var\(\s*(--ptah-[a-z0-9-]+)\s*\)/i',
            function (array $m) use ($scope, $depth, $trail): string {
                $token = $m[1];

                if (in_array($token, $trail, true)) {
                    throw new RuntimeException(sprintf('CssTokenResolver: ciclo detectado ao resolver o token "%s".', $token));
                }

                if (! array_key_exists($token, $this->tokens[$scope])) {
                    throw new RuntimeException(sprintf('CssTokenResolver: token "%s" nao definido no escopo "%s".', $token, $scope));
                }

                return $this->resolve($this->tokens[$scope][$token], $scope, $depth + 1, [...$trail, $token]);
            },
            $value
        );

        if ($result === null) {
            throw new RuntimeException(sprintf('CssTokenResolver: falha ao processar regex sobre "%s".', $value));
        }

        return $result;
    }

    /**
     * Replaces every `color-mix(in srgb, <hex-literal> <p>%, transparent)`
     * occurrence found ANYWHERE in $value (after resolve()) with the
     * equivalent `rgba()` — e.g. inside a `border: 1px solid color-mix(...)`
     * shorthand, not just when the whole value is the color-mix() call. Per
     * the CSS Color 4 spec, `transparent` is `rgb(0 0 0 / 0)`; interpolating
     * in premultiplied alpha against a fully transparent color means the
     * result keeps the first color's channels unchanged and its alpha
     * becomes exactly the mix percentage (p / 100) — proven in
     * CssTokenResolverTest against hand-computed rgba() values.
     *
     * A `color-mix()` whose first argument is not (yet, at this point) a hex
     * literal — e.g. it is still `var(--color-primary, #5b21b6)` because
     * resolve() only touches --ptah-* vars — is left untouched.
     */
    public static function computeMix(string $value): string
    {
        $result = preg_replace_callback(
            '/color-mix\(\s*in\s+srgb\s*,\s*(#[0-9a-fA-F]{6})\s+(\d+(?:\.\d+)?)%\s*,\s*transparent\s*\)/i',
            function (array $m): string {
                [$r, $g, $b] = self::hexToRgb($m[1]);
                $alpha = ((float) $m[2]) / 100;

                return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, self::formatAlpha($alpha));
            },
            $value
        );

        return $result ?? $value;
    }

    /**
     * Lowercases, expands `#rgb` to `#rrggbb`, converts `#rrggbbaa` to an
     * equivalent `rgba()`, and collapses redundant whitespace (including around
     * commas/parentheses and CSS's leading-dot decimals, e.g. `.04` -> `0.04`).
     */
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $m[1], $m[2], $m[3]);
        }

        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $value, $m)) {
            $r = hexdec($m[1]);
            $g = hexdec($m[2]);
            $b = hexdec($m[3]);
            $a = hexdec($m[4]) / 255;

            return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, self::formatAlpha($a));
        }

        $value = preg_replace('/\s*,\s*/', ', ', $value) ?? $value;
        $value = preg_replace('/\(\s+/', '(', $value) ?? $value;
        $value = preg_replace('/\s+\)/', ')', $value) ?? $value;
        $value = preg_replace('/(?<=[(,]\s)\.(?=\d)/', '0.', $value) ?? $value;
        $value = preg_replace('/(?<=[(,])\.(?=\d)/', '0.', $value) ?? $value;

        return $value;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Formats an alpha in [0, 1] without float noise (e.g. 0.6, not 0.59999999999999998). */
    private static function formatAlpha(float $alpha): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4f', $alpha), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /** @return array<string, string> */
    private static function parseDeclarationBlock(string $css, string $blockPattern): array
    {
        if (! preg_match($blockPattern, $css, $blockMatch)) {
            return [];
        }

        $tokens = [];

        if (preg_match_all('/(--ptah-[a-z0-9-]+)\s*:\s*([^;]+);/i', $blockMatch[1], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $tokens[$m[1]] = trim($m[2]);
            }
        }

        return $tokens;
    }

    private static function stripComments(string $css): string
    {
        return preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
    }
}
