<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use FilesystemIterator;
use Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards the shipped sources against the class of corruption introduced by
 * commit c7f5d08 — a Windows reformat that read UTF-8 files as CP1252 and
 * rewrote them as UTF-8+BOM, double-encoding every non-ASCII byte.
 *
 * These are pure filesystem assertions and do not need the Laravel app.
 *
 * Detection strategy for the "double-encoded" check:
 *   1. Pre-filter: find maximal runs of 2+ consecutive non-ASCII UTF-8
 *      characters (mojibake always shows up as such a run — each original
 *      byte >= 0x80 was individually reinterpreted as a CP1252 character and
 *      re-encoded, producing a string of Latin-1/CP1252-range characters).
 *   2. Confirm: round-trip the run through iconv('UTF-8', 'WINDOWS-1252', ...)
 *      and check whether the resulting bytes are themselves valid UTF-8. Real
 *      text never survives this round-trip: a lone accented character (e.g.
 *      "ç" in "Ação") converts to a single CP1252 byte, and a single byte
 *      >= 0x80 can never be a complete, valid UTF-8 sequence on its own — so
 *      legitimate single characters are never flagged. Only a run whose
 *      CP1252 bytes happen to reassemble into a complete, valid UTF-8
 *      sequence is reported, which is exactly the double-encoding signature.
 */
class SourceEncodingTest extends TestCase
{
    /** @var list<string> Top-level directories that ship with the package. */
    private const SCANNED_DIRECTORIES = ['src', 'resources', 'config', 'routes', 'lang', 'stubs'];

    /** @var list<string> File extensions considered "source" for shipping purposes. */
    private const SCANNED_EXTENSIONS = ['php', 'json', 'neon', 'js', 'css', 'stub', 'yml'];

    #[Test]
    public function no_double_encoded_sequences_in_shipped_sources(): void
    {
        $offenders = [];

        foreach (self::shippedFiles() as $file) {
            $content = file_get_contents($file);

            if ($content === false || ! mb_check_encoding($content, 'UTF-8')) {
                // Covered by all_shipped_sources_are_valid_utf8(); skipped here
                // to avoid preg_match_all('u') on invalid input.
                continue;
            }

            preg_match_all('/[\x{0080}-\x{10FFFF}]{2,}/u', $content, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$run, $byteOffset]) {
                $asCp1252 = @iconv('UTF-8', 'WINDOWS-1252', $run);

                if ($asCp1252 === false || $asCp1252 === '') {
                    continue;
                }

                if (! mb_check_encoding($asCp1252, 'UTF-8')) {
                    continue;
                }

                $line = substr_count($content, "\n", 0, $byteOffset) + 1;

                $offenders[] = sprintf(
                    '%s:%d — "%s" (hex %s) parece duplo-codificado; original provável: "%s"',
                    $file,
                    $line,
                    $run,
                    bin2hex($run),
                    $asCp1252
                );
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Sequências duplo-codificadas encontradas:\n".implode("\n", $offenders)
        );
    }

    #[Test]
    public function no_bom_in_php_and_blade_sources(): void
    {
        $root = self::packageRoot();
        $offenders = [];

        $targets = [
            [$root.'/src', '.php'],
            [$root.'/resources/views', '.blade.php'],
        ];

        foreach ($targets as [$dir, $suffix]) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (! $fileInfo->isFile() || ! str_ends_with($fileInfo->getFilename(), $suffix)) {
                    continue;
                }

                $bytes = file_get_contents($fileInfo->getPathname(), false, null, 0, 3);

                if ($bytes === hex2bin('efbbbf')) {
                    $offenders[] = $fileInfo->getPathname();
                }
            }
        }

        self::assertSame([], $offenders, "Arquivos com BOM UTF-8:\n".implode("\n", $offenders));
    }

    #[Test]
    public function all_shipped_sources_are_valid_utf8(): void
    {
        $offenders = [];

        foreach (self::shippedFiles() as $file) {
            $content = file_get_contents($file);

            if ($content === false || ! mb_check_encoding($content, 'UTF-8')) {
                $offenders[] = $file;
            }
        }

        self::assertSame([], $offenders, "Arquivos com bytes UTF-8 inválidos:\n".implode("\n", $offenders));
    }

    /**
     * @return Generator<int, string>
     */
    private static function shippedFiles(): Generator
    {
        $root = self::packageRoot();

        foreach (self::SCANNED_DIRECTORIES as $dir) {
            $path = $root.'/'.$dir;

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $fileInfo */
            foreach ($iterator as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $extension = strtolower($fileInfo->getExtension());

                if (! in_array($extension, self::SCANNED_EXTENSIONS, true)) {
                    continue;
                }

                yield $fileInfo->getPathname();
            }
        }
    }

    private static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
