<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\GeneralParser;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * No document may teach a `--set` key the runtime does not read.
 *
 * The docs taught `--set="cacheEnabled=true"`, `cacheTime`, `paginationEnabled`
 * and a top-level `itemsPerPage` — none of which BaseCrud ever read. Every
 * example "saved successfully" and changed nothing. `itemsPerPage` and
 * `exportEnabled` are now translated to the keys that ARE read
 * (GeneralParser::ALIASES); the rest are refused by the command, and this
 * keeps them out of the documents. The counterpart of
 * DocumentedFilterDefinitionTest.
 */
class DocumentedSetKeyTest extends TestCase
{
    /**
     * @return list<array{path: string, line: int, key: string}>
     */
    private static function documentedKeys(): array
    {
        $out = [];
        $root = dirname(__DIR__, 3);

        foreach ([$root.'/docs', $root.'/resources/boost', $root.'/README.md'] as $target) {
            $files = is_dir($target)
                ? iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS)))
                : [new \SplFileInfo($target)];

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.md')) {
                    continue;
                }

                foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $i => $line) {
                    if (preg_match_all('/--set[= ]"?([A-Za-z_.]+)=/', $line, $m) > 0) {
                        foreach ($m[1] as $key) {
                            $path = str_replace('\\', '/', $file->getPathname());
                            $out[] = ['path' => ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/'), 'line' => $i + 1, 'key' => $key];
                        }
                    }
                }
            }
        }

        if ($out === []) {
            throw new RuntimeException('DocumentedSetKeyTest: nenhum --set encontrado — a regex quebrou.');
        }

        return $out;
    }

    #[Test]
    public function no_document_teaches_a_set_key_the_runtime_ignores(): void
    {
        $offenders = [];

        foreach (self::documentedKeys() as $case) {
            if (GeneralParser::targetKey($case['key']) === null) {
                $offenders[] = "  {$case['path']}:{$case['line']}  --set {$case['key']}";
            }
        }

        $this->assertSame([], $offenders, "Documento ensina --set que o BaseCrud nao le:\n".implode("\n", $offenders));
    }
}
