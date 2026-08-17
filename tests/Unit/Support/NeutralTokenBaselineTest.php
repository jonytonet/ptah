<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Tests\Support\CssTokenResolver;
use RuntimeException;

/**
 * Golden-master guard for the Fase 1 restyle (tokenizing the neutrals in
 * resources/css/ptah-components.css). It reads every declaration in the
 * file, resolves any --ptah-* custom property down to its final rendered
 * value (per scope), and compares the whole map against a byte-for-byte
 * fixture captured BEFORE the tokenization started.
 *
 * If this test ever fails outside of a deliberate, reviewed CSS change, it
 * means a token substitution altered what actually renders — a Fase 1
 * regression, since Fase 1's only mandate is "tokenize with zero visual
 * change".
 *
 * To (re)capture the fixture: run once with PTAH_REGEN_CSS_BASELINE=1 (the
 * test then writes tests/Fixtures/css-neutral-baseline.json and marks
 * itself skipped with an explicit warning — it never regenerates silently).
 */
class NeutralTokenBaselineTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__.'/../../Fixtures/css-neutral-baseline.json';

    #[Test]
    public function resolved_neutral_declarations_match_the_captured_baseline(): void
    {
        $css = self::css();
        $actual = self::extractResolvedDeclarations($css);

        if (getenv('PTAH_REGEN_CSS_BASELINE') === '1') {
            file_put_contents(
                self::FIXTURE_PATH,
                json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
            );

            $this->markTestSkipped(
                'PTAH_REGEN_CSS_BASELINE=1: fixture tests/Fixtures/css-neutral-baseline.json foi REGERADA. '.
                'Rode a suite novamente SEM essa variavel para validar contra a nova fixture.'
            );
        }

        if (! is_file(self::FIXTURE_PATH)) {
            throw new RuntimeException(
                'NeutralTokenBaselineTest: fixture ausente em '.self::FIXTURE_PATH.
                '. Gere-a primeiro com PTAH_REGEN_CSS_BASELINE=1.'
            );
        }

        $expectedJson = file_get_contents(self::FIXTURE_PATH);

        if ($expectedJson === false) {
            throw new RuntimeException('NeutralTokenBaselineTest: falha ao ler a fixture em '.self::FIXTURE_PATH);
        }

        /** @var array<string, string> $expected */
        $expected = json_decode($expectedJson, true, flags: JSON_THROW_ON_ERROR);

        $expectedKeys = array_keys($expected);
        $actualKeys = array_keys($actual);
        sort($expectedKeys);
        sort($actualKeys);

        $missing = array_values(array_diff($expectedKeys, $actualKeys));
        $extra = array_values(array_diff($actualKeys, $expectedKeys));

        $this->assertSame(
            [],
            $missing,
            "Sitios (seletor|escopo|propriedade) presentes na fixture mas ausentes no CSS atual:\n".implode("\n", $missing)
        );
        $this->assertSame(
            [],
            $extra,
            "Sitios (seletor|escopo|propriedade) novos no CSS atual, sem entrada na fixture:\n".implode("\n", $extra)
        );

        foreach ($expected as $site => $expectedValue) {
            $this->assertSame(
                $expectedValue,
                $actual[$site],
                sprintf(
                    "Divergencia no site [%s]:\n  esperado (fixture): %s\n  atual (CSS resolvido): %s",
                    $site,
                    $expectedValue,
                    $actual[$site]
                )
            );
        }
    }

    /**
     * Parses every non-token, non-@media-print rule in $css into a flat,
     * deterministically-ordered map of "selector|scope|property" => the fully
     * resolved (var() substituted, color-mix() computed, normalized) value.
     *
     * @return array<string, string>
     */
    private static function extractResolvedDeclarations(string $css): array
    {
        $resolver = new CssTokenResolver($css);

        $working = self::stripComments($css);
        $working = self::stripMediaPrint($working);
        $working = self::stripFirstBlock($working, '/:root\s*\{[^}]*\}/');
        $working = self::stripFirstBlock($working, '/\.ptah-dark\s*\{[^}]*\}/');

        $sites = [];

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $working, $rules, PREG_SET_ORDER)) {
            throw new RuntimeException('NeutralTokenBaselineTest: nenhuma regra encontrada apos remover blocos de token e @media print — CSS mudou de formato?');
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
                $normalizedSelector = $scope === 'dark'
                    ? trim(preg_replace('/^\.ptah-dark\s*/', '', $selector) ?? $selector)
                    : $selector;

                foreach ($declarations as $property => $rawValue) {
                    $resolved = $resolver->resolve($rawValue, $scope);
                    $mixed = CssTokenResolver::computeMix($resolved);
                    $final = CssTokenResolver::normalize($mixed);

                    $site = sprintf('%s|%s|%s', $normalizedSelector, $scope, $property);
                    $sites[$site] = $final;
                }
            }
        }

        ksort($sites);

        return $sites;
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
                throw new RuntimeException(sprintf('NeutralTokenBaselineTest: declaracao malformada "%s".', $declaration));
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

    /** Removes the (nested-brace) `@media print { ... }` block, if present. */
    private static function stripMediaPrint(string $css): string
    {
        $start = strpos($css, '@media print');

        if ($start === false) {
            return $css;
        }

        $bracePos = strpos($css, '{', $start);

        if ($bracePos === false) {
            throw new RuntimeException('NeutralTokenBaselineTest: "@media print" encontrado sem "{" correspondente.');
        }

        $depth = 0;
        $len = strlen($css);
        $end = null;

        for ($i = $bracePos; $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === null) {
            throw new RuntimeException('NeutralTokenBaselineTest: chave de fechamento de "@media print" nao encontrada.');
        }

        return substr($css, 0, $start).substr($css, $end + 1);
    }

    private static function css(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('NeutralTokenBaselineTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}
