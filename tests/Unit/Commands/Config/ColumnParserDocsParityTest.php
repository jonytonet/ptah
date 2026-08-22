<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * Guards against the exact class of drift an audit found in
 * docs/Commands.md: the documented `--column` option/modifier tables silently
 * fell out of sync with `ColumnParser`'s real `$keyMap` and modifier list
 * (invented options like `rendererDecimals`/`width`/`optional` that the
 * parser never reads, missing real ones like `sd_model`/`decimals`).
 *
 * Both sides are parsed straight from source (the markdown table in
 * docs/Commands.md, the PHP array/match literals in ColumnParser.php) rather
 * than hand-duplicated here — a key added to one without the other fails this
 * test instead of drifting silently again.
 */
class ColumnParserDocsParityTest extends TestCase
{
    /**
     * @return array<string, string> option => mapped property, as documented
     */
    private function documentedOptions(): array
    {
        $docs = $this->docsSection(
            '**Column options (`option=value`) — full `$keyMap`:**',
            '> Any `option=value` key'
        );

        preg_match_all('/^\|\s*`([a-z0-9_]+)`\s*\|\s*`([A-Za-z0-9_]+)`/m', $docs, $matches, PREG_SET_ORDER);

        $result = [];
        foreach ($matches as $m) {
            $result[$m[1]] = $m[2];
        }

        return $result;
    }

    /**
     * @return list<string> modifier names, as documented
     */
    private function documentedModifiers(): array
    {
        $docs = $this->docsSection(
            '**Modifiers (bare tokens — never `modifier=true`):**',
            '**Column options'
        );

        preg_match_all('/^\|\s*`([a-z0-9_]+)`\s*\|/m', $docs, $matches);

        return $matches[1];
    }

    private function docsSection(string $startMarker, string $endMarker): string
    {
        $path = dirname(__DIR__, 4).'/docs/Commands.md';
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Could not read docs/Commands.md');
        }

        $start = strpos($content, $startMarker);
        $end = strpos($content, $endMarker, $start !== false ? $start : 0);
        if ($start === false || $end === false) {
            throw new RuntimeException('Could not locate the --column option/modifier section in docs/Commands.md');
        }

        return substr($content, $start, $end - $start);
    }

    /**
     * @return array<string, string> option => mapped property, as implemented
     */
    private function realKeyMap(): array
    {
        $source = $this->columnParserSource();

        if (! preg_match('/\$keyMap\s*=\s*\[(.*?)\n\s*\];/s', $source, $m)) {
            throw new RuntimeException('Could not locate $keyMap in ColumnParser::applyKeyValue()');
        }

        preg_match_all("/'([a-z0-9_]+)'\s*=>\s*'([A-Za-z0-9_]+)'/", $m[1], $pairs, PREG_SET_ORDER);

        $result = [];
        foreach ($pairs as $pair) {
            $result[$pair[1]] = $pair[2];
        }

        // A few keys ('validation', 'options', 'badges', …) bypass $keyMap entirely —
        // applyKeyValue() special-cases them with their own `if ($key === '...')`
        // branch instead. Merge those in too, straight from source, so this test
        // covers every recognised option, not just the ones in the array literal.
        preg_match_all(
            "/\\\$key === '([a-z0-9_]+)'\)\s*\{.*?\\\$config\['([A-Za-z0-9_]+)'\]/s",
            $source,
            $specialCases,
            PREG_SET_ORDER
        );
        foreach ($specialCases as $case) {
            $result[$case[1]] = $case[2];
        }

        return $result;
    }

    /**
     * @return list<string> modifier names, as implemented (the `match ($modifier)` arms)
     */
    private function realModifiers(): array
    {
        $source = $this->columnParserSource();

        if (! preg_match('/protected function applyModifier.*?match\s*\(\$modifier\)\s*\{(.*?)\n\s*\};/s', $source, $m)) {
            throw new RuntimeException('Could not locate the modifier match() in ColumnParser::applyModifier()');
        }

        preg_match_all("/'([a-z0-9_]+)'\s*=>/", $m[1], $matches);

        return $matches[1];
    }

    private function columnParserSource(): string
    {
        $path = dirname(__DIR__, 4).'/src/Commands/Config/Parsers/ColumnParser.php';
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Could not read ColumnParser.php');
        }

        return $content;
    }

    #[Test]
    public function every_documented_column_option_exists_in_the_real_keymap(): void
    {
        $documented = $this->documentedOptions();
        $real = $this->realKeyMap();

        $this->assertNotEmpty($documented, 'Failed to parse any option from docs/Commands.md — the section markers may have moved.');

        foreach ($documented as $option => $mapping) {
            $this->assertArrayHasKey(
                $option,
                $real,
                "docs/Commands.md documents --column option '{$option}', but ColumnParser::\$keyMap has no such key."
            );
        }
    }

    #[Test]
    public function every_real_keymap_option_is_documented(): void
    {
        $documented = $this->documentedOptions();
        $real = $this->realKeyMap();

        $this->assertNotEmpty($real, 'Failed to parse ColumnParser::$keyMap — the source shape may have changed.');

        foreach ($real as $option => $mapping) {
            $this->assertArrayHasKey(
                $option,
                $documented,
                "ColumnParser::\$keyMap has option '{$option}' => '{$mapping}', but it is not documented in docs/Commands.md."
            );
        }
    }

    #[Test]
    public function documented_mappings_match_the_real_keymap(): void
    {
        $documented = $this->documentedOptions();
        $real = $this->realKeyMap();

        foreach ($documented as $option => $mapping) {
            if (! isset($real[$option])) {
                continue; // already reported by the previous test
            }

            $this->assertSame(
                $real[$option],
                $mapping,
                "docs/Commands.md says --column option '{$option}' maps to '{$mapping}', but ColumnParser::\$keyMap maps it to '{$real[$option]}'."
            );
        }
    }

    #[Test]
    public function every_documented_modifier_exists_in_the_real_applymodifier(): void
    {
        $documented = $this->documentedModifiers();
        $real = $this->realModifiers();

        $this->assertNotEmpty($documented, 'Failed to parse any modifier from docs/Commands.md — the section markers may have moved.');

        foreach ($documented as $modifier) {
            $this->assertContains(
                $modifier,
                $real,
                "docs/Commands.md documents modifier '{$modifier}', but ColumnParser::applyModifier() has no such case."
            );
        }
    }

    #[Test]
    public function every_real_modifier_is_documented(): void
    {
        $documented = $this->documentedModifiers();
        $real = $this->realModifiers();

        $this->assertNotEmpty($real, 'Failed to parse ColumnParser::applyModifier() — the source shape may have changed.');

        foreach ($real as $modifier) {
            $this->assertContains(
                $modifier,
                $documented,
                "ColumnParser::applyModifier() has case '{$modifier}', but it is not documented in docs/Commands.md."
            );
        }
    }
}
