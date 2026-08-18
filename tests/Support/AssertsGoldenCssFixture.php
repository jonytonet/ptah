<?php

declare(strict_types=1);

namespace Ptah\Tests\Support;

use RuntimeException;

/**
 * Compares an extracted "selector|scope|property" => value map against a
 * committed JSON fixture, reporting missing sites, new sites and per-site value
 * divergences separately — the three failure modes mean different things:
 *
 *   missing  a rule was deleted or its selector changed
 *   extra    a rule was added, or a selector was renamed (shows up as both)
 *   diverged the rule is still there but now renders a different color
 *
 * Regeneration is deliberately explicit and noisy (env var + skipped test with
 * a warning), never silent, so a reviewer can never mistake "I rewrote the
 * baseline" for "the baseline passed".
 */
trait AssertsGoldenCssFixture
{
    private const REGEN_ENV = 'PTAH_REGEN_CSS_BASELINE';

    /**
     * @param  array<string, string>  $actual
     */
    private function assertMatchesGoldenFixture(string $fixturePath, array $actual, string $label): void
    {
        if (getenv(self::REGEN_ENV) === '1') {
            file_put_contents(
                $fixturePath,
                json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
            );

            $this->markTestSkipped(sprintf(
                '%s=1: fixture %s foi REGERADA (%d sitios). Rode a suite novamente SEM essa variavel para validar.',
                self::REGEN_ENV,
                basename($fixturePath),
                count($actual)
            ));
        }

        if (! is_file($fixturePath)) {
            throw new RuntimeException(sprintf(
                '%s: fixture ausente em %s. Gere-a primeiro com %s=1.',
                $label,
                $fixturePath,
                self::REGEN_ENV
            ));
        }

        $expectedJson = file_get_contents($fixturePath);

        if ($expectedJson === false) {
            throw new RuntimeException(sprintf('%s: falha ao ler a fixture em %s', $label, $fixturePath));
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
}
