<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\FilterParser;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Tests\TestCase;
use RuntimeException;
use Throwable;

/**
 * Every `--filter="…"` a document teaches must mean what it says.
 *
 * The filter parser reads `field:type` and then `key=value` only. A POSITIONAL
 * operator — `name:text:LIKE:label=…`, `score:number:>=:…`,
 * `status:select:=:…` — is silently dropped, and the filter falls back to
 * equality: a "text with LIKE" example became an exact-match filter, with no
 * error anywhere. `docs/Configuration.md` even documented the format as
 * `field:type:operator:label=…`, while the package skill correctly said "no
 * positional operator" — two documents, two answers.
 *
 * And `boolean` is not a filter type the runtime handles: `_filter-panel`
 * renders searchdropdown, date, number and select, and falls back to a plain
 * text box for anything else. The scaffold skill — the one an agent reads
 * first — taught `is_active:boolean:eq:Ativos`: a type the panel does not know,
 * then two positional tokens the parser discards.
 *
 * Found by `ptah:docs filter`, whose type list is read from the same constant
 * the runtime branches on. The counterpart of DocumentedColumnDefinitionTest.
 */
class DocumentedFilterDefinitionTest extends TestCase
{
    private const NEGATIONS = ['wrong', '❌', 'do not', 'don\'t', 'never', 'errado', 'incorrect', 'no positional operator', 'stores nothing'];

    /**
     * @return list<array{path: string, line: int, definition: string}>
     */
    private static function definitions(): array
    {
        $out = [];

        foreach ([__DIR__.'/../../../docs', __DIR__.'/../../../resources/boost'] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.md')) {
                    continue;
                }

                $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname())));

                foreach ($lines as $i => $line) {
                    if (preg_match_all('/--filter="([^"]+)"/', $line, $m) === 0) {
                        continue;
                    }

                    if (self::negatedNearby($lines, $i)) {
                        continue;
                    }

                    foreach ($m[1] as $definition) {
                        if (str_contains($definition, '...') || str_contains($definition, '…')) {
                            continue;
                        }

                        $out[] = [
                            'path' => str_replace(dirname(__DIR__, 3).DIRECTORY_SEPARATOR, '', $file->getPathname()),
                            'line' => $i + 1,
                            'definition' => $definition,
                        ];
                    }
                }
            }
        }

        if ($out === []) {
            throw new RuntimeException('DocumentedFilterDefinitionTest: nenhum --filter encontrado — a regex quebrou.');
        }

        return $out;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function negatedNearby(array $lines, int $index): bool
    {
        for ($i = max(0, $index - 3); $i <= $index + 1; $i++) {
            $haystack = mb_strtolower($lines[$i] ?? '');

            foreach (self::NEGATIONS as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Tokens after `field:type` that the parser would silently discard.
     *
     * Mirrors FilterParser::tokenize(): a `key=value` token opens a value that
     * may continue across `:` (`options=1:Ativo,0:Inativo`); a token without
     * `=` is only legal as that continuation. A bare token with no value open,
     * or a "key" that is not an identifier (`=`, `>=`), is a positional operator
     * the parser drops.
     *
     * @return list<string>
     */
    private static function discardedTokens(string $definition): array
    {
        $parts = explode(':', $definition);
        array_shift($parts); // field
        array_shift($parts); // type

        $discarded = [];
        $open = false;

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                $key = explode('=', $part, 2)[0];

                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1) {
                    $open = true;

                    continue;
                }

                $discarded[] = $part;
                $open = false;

                continue;
            }

            if (! $open) {
                $discarded[] = $part;
            }
        }

        return $discarded;
    }

    #[Test]
    public function the_documents_do_teach_filters(): void
    {
        // Ancora: sem ela, os dois testes abaixo passariam a vazio.
        $this->assertGreaterThan(5, count(self::definitions()));
    }

    #[Test]
    public function every_documented_filter_uses_a_type_the_panel_renders(): void
    {
        $offenders = [];

        foreach (self::definitions() as $case) {
            $type = explode(':', $case['definition'])[1] ?? 'text';

            if (! in_array($type, CrudConfigEnums::FILTER_TYPES, true)) {
                $offenders[] = "  {$case['path']}:{$case['line']}  tipo \"{$type}\" — em `{$case['definition']}`";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Filtro documentado com tipo que o painel nao trata (cai num campo de texto):\n".implode("\n", $offenders)
        );
    }

    #[Test]
    public function no_documented_filter_relies_on_a_token_the_parser_discards(): void
    {
        $offenders = [];

        foreach (self::definitions() as $case) {
            $discarded = self::discardedTokens($case['definition']);

            if ($discarded !== []) {
                $offenders[] = "  {$case['path']}:{$case['line']}  descartado: ".implode(', ', $discarded)." — em `{$case['definition']}`";
            }

            try {
                (new FilterParser)->parse($case['definition']);
            } catch (Throwable $e) {
                $offenders[] = "  {$case['path']}:{$case['line']}  {$e->getMessage()}";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Filtro documentado com operador posicional — o parser o descarta em silencio:\n".implode("\n", $offenders)
        );
    }
}
