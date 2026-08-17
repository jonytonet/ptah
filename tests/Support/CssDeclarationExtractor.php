<?php

declare(strict_types=1);

namespace Ptah\Tests\Support;

use RuntimeException;

/**
 * Turns a stylesheet into a flat, deterministically-ordered map of
 * "selector|scope|property" => fully resolved color/value, so a golden fixture
 * can prove that a refactor changed nothing about what actually renders.
 *
 * Extracted from NeutralTokenBaselineTest so the same machinery can guard a
 * second stylesheet: the inline <style> block in forge-dashboard-layout, whose
 * ~104 ptah-* rules are being migrated onto the neutral tokens. Both guards
 * must agree on how a site is keyed, otherwise a rule that MOVES from the
 * layout into ptah-components.css would silently vanish from one baseline and
 * appear as a brand-new site in the other, which is precisely the mistake the
 * fixtures exist to catch.
 *
 * Not shipped: lives under tests/ (autoload-dev only) and is not collected as
 * a test itself.
 */
final class CssDeclarationExtractor
{
    /**
     * @param  bool  $stripTokenBlocks  Remove the `:root` and bare `.ptah-dark`
     *                                  declaration blocks before extracting. True for
     *                                  ptah-components.css (those blocks define the
     *                                  tokens; recording them as sites would double-count
     *                                  every value). False for the layout <style>, which
     *                                  has a real, meaningful bare `.ptah-dark { ... }`
     *                                  rule painting the page background.
     */
    public function __construct(
        private readonly CssTokenResolver $resolver,
        private readonly bool $stripTokenBlocks,
    ) {}

    /**
     * @return array<string, string>
     */
    public function extract(string $css): array
    {
        $working = self::stripComments($css);
        $working = self::stripAtRuleBlock($working, '@media print');
        $working = self::stripAtRuleBlocks($working, '/@keyframes\s+[a-z0-9_-]+/i');

        if ($this->stripTokenBlocks) {
            $working = self::stripFirstBlock($working, '/:root\s*\{[^}]*\}/');
            $working = self::stripFirstBlock($working, '/\.ptah-dark\s*\{[^}]*\}/');
        }

        $sites = [];

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $working, $rules, PREG_SET_ORDER)) {
            throw new RuntimeException('CssDeclarationExtractor: nenhuma regra encontrada apos a limpeza — o CSS mudou de formato?');
        }

        foreach ($rules as $rule) {
            $selectorList = trim($rule[1]);
            $body = trim($rule[2]);

            if ($selectorList === '' || $body === '') {
                continue;
            }

            $declarations = self::parseDeclarations($body);

            if ($declarations === []) {
                continue;
            }

            foreach (explode(',', $selectorList) as $selector) {
                $selector = trim(preg_replace('/\s+/', ' ', $selector) ?? $selector);

                if ($selector === '') {
                    continue;
                }

                $scope = str_starts_with($selector, '.ptah-dark') ? 'dark' : 'light';

                // A bare `.ptah-dark { ... }` rule is dark-scoped but has no
                // remainder once the scope prefix is removed. Key it explicitly
                // instead of producing an empty selector.
                $normalizedSelector = $selector;

                if ($scope === 'dark') {
                    $stripped = trim(preg_replace('/^\.ptah-dark\s*/', '', $selector) ?? $selector);
                    $normalizedSelector = $stripped === '' ? ':scope-root' : $stripped;
                }

                foreach ($declarations as $property => $rawValue) {
                    $resolved = $this->resolver->resolve($rawValue, $scope);
                    $mixed = CssTokenResolver::computeMix($resolved);
                    $final = CssTokenResolver::normalize($mixed);

                    $sites[sprintf('%s|%s|%s', $normalizedSelector, $scope, $property)] = $final;
                }
            }
        }

        ksort($sites);

        return $sites;
    }

    /**
     * Pulls the contents of the first `<style> ... </style>` element out of a
     * Blade file. Throws if the file interpolates Blade inside that element:
     * the extractor parses raw CSS, so a `{{ $x }}` would be silently recorded
     * as a literal value and the fixture would then be guarding a string that
     * never reaches the browser.
     */
    public static function styleBlockFromBlade(string $blade): string
    {
        // Blade comments come out first: the comment directly above the block
        // discusses it in prose and contains the literal text "<style>", which
        // otherwise anchors the match inside the comment and silently drops the
        // block's first rule. (Found exactly that way — the fixture reported
        // [x-cloak]|light|display as missing.)
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', $blade) ?? $blade;

        if (! preg_match('#<style[^>]*>(.*?)</style>#s', $blade, $m)) {
            throw new RuntimeException('CssDeclarationExtractor: nenhum bloco <style> encontrado no Blade informado.');
        }

        $css = $m[1];

        if (preg_match('/\{\{|\{!!|@if|@php|@foreach|@unless/', $css)) {
            throw new RuntimeException(
                'CssDeclarationExtractor: o bloco <style> passou a conter Blade interpolado. '.
                'O extrator lê CSS bruto, então a fixture guardaria um literal que nunca chega ao browser. '.
                'Mova o valor dinâmico para uma custom property (ver ptah::partials.theme-colors).'
            );
        }

        return $css;
    }

    /** @return array<string, string> property (lowercase) => raw value */
    private static function parseDeclarations(string $body): array
    {
        $declarations = [];

        foreach (explode(';', $body) as $declaration) {
            $declaration = trim($declaration);

            if ($declaration === '') {
                continue;
            }

            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                throw new RuntimeException(sprintf('CssDeclarationExtractor: declaracao malformada "%s".', $declaration));
            }

            [$property, $value] = $parts;
            $declarations[strtolower(trim($property))] = trim($value);
        }

        return $declarations;
    }

    private static function stripComments(string $css): string
    {
        return preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
    }

    private static function stripFirstBlock(string $css, string $pattern): string
    {
        return preg_replace($pattern, '', $css, 1) ?? $css;
    }

    /** Removes every block matching $headPattern, braces balanced (handles nesting). */
    private static function stripAtRuleBlocks(string $css, string $headPattern): string
    {
        $guard = 0;

        while (preg_match($headPattern, $css, $m, PREG_OFFSET_CAPTURE)) {
            if (++$guard > 200) {
                throw new RuntimeException('CssDeclarationExtractor: laco de remocao de at-rule nao convergiu.');
            }

            $css = self::cutBalancedBlockAt($css, (int) $m[0][1], $m[0][0]);
        }

        return $css;
    }

    /** Removes the single (brace-balanced) block introduced by the literal $head, if present. */
    private static function stripAtRuleBlock(string $css, string $head): string
    {
        $start = strpos($css, $head);

        if ($start === false) {
            return $css;
        }

        return self::cutBalancedBlockAt($css, $start, $head);
    }

    private static function cutBalancedBlockAt(string $css, int $start, string $head): string
    {
        $bracePos = strpos($css, '{', $start);

        if ($bracePos === false) {
            throw new RuntimeException(sprintf('CssDeclarationExtractor: "%s" encontrado sem "{" correspondente.', $head));
        }

        $depth = 0;
        $len = strlen($css);

        for ($i = $bracePos; $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($css, 0, $start).substr($css, $i + 1);
                }
            }
        }

        throw new RuntimeException(sprintf('CssDeclarationExtractor: chave de fechamento de "%s" nao encontrada.', $head));
    }
}
