<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Per-file ratchet on fixed-palette Tailwind utilities (`bg-*` / `text-*` from
 * the gray/slate/zinc/neutral/stone families, plus `bg-white` / `text-white` /
 * `text-black`) in package Blade views.
 *
 * Why this exists: `docs/KnownLimitations.md` has forbidden new fixed-palette
 * utilities in package views since 1.15.0, but the count grew anyway (999 to
 * 1019 `text-*` alone by 1.25.0) because a doc rule with nothing enforcing it
 * does not hold a line. This test is the enforcement: a per-file ceiling that
 * may only ever go down, with the fixture tightened in the same commit that
 * reduces a count (see `ceilings_are_tight`), and a sweep that fails the
 * moment a new view ships hardcoded utilities without an entry at all (see
 * `every_view_with_a_hardcoded_utility_is_in_the_fixture`).
 *
 * A hardcoded utility is not automatically a bug: 78 of them are repainted
 * through a token from a distance by an unlayered selector in
 * ptah-components.css (`.ptah-cfg .bg-white`, `.ptah-module-table
 * .text-slate-800`, …) and ~70 more are ink painted on top of an accent
 * background, where `--ptah-text-on-accent` is #ffffff invariant in every
 * scope. The ceiling does not distinguish those from real debt — it only
 * guarantees the total recorded per file never grows silently. See
 * `docs/KnownLimitations.md` section 6 for the qualitative picture.
 */
class HardcodedPaletteCeilingTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__.'/../../Fixtures/hardcoded-palette-ceiling.json';

    private const BG_PATTERN = '/bg-(white|slate|gray|zinc|neutral|stone)(-\d+(\/\d+)?)?/';

    private const TEXT_PATTERN = '/text-(gray|slate|zinc|neutral|stone|white|black)(-\d+(\/\d+)?)?/';

    #[Test]
    public function no_view_gained_a_hardcoded_palette_utility(): void
    {
        foreach (self::fixture() as $view => $ceiling) {
            $actual = self::countInFile($view);

            $this->assertLessThanOrEqual(
                $ceiling,
                $actual,
                sprintf(
                    '%s: ganhou %d utilitario(s) de paleta fixa (teto %d, atual %d). Cor de superficie/texto '.
                    'vai numa classe .ptah-c-* em resources/css/ptah-components.css usando var(--ptah-*) — '.
                    'senao o tom escolhido em /profile nunca alcanca esse elemento. Receita: '.
                    'docs/CustomScreens.md secao 6.',
                    $view,
                    max(0, $actual - $ceiling),
                    $ceiling,
                    $actual
                )
            );
        }
    }

    #[Test]
    public function ceilings_are_tight(): void
    {
        foreach (self::fixture() as $view => $ceiling) {
            $actual = self::countInFile($view);

            $this->assertSame(
                $ceiling,
                $actual,
                $actual < $ceiling
                    ? sprintf(
                        '%s: progresso: baixe o teto de %d para %d na MESMA commit — o teto e catraca, nao folga.',
                        $view,
                        $ceiling,
                        $actual
                    )
                    : sprintf(
                        '%s: teto %d, atual %d — o guard ja deveria ter barrado isso em '.
                        'no_view_gained_a_hardcoded_palette_utility.',
                        $view,
                        $ceiling,
                        $actual
                    )
            );
        }
    }

    #[Test]
    public function every_view_with_a_hardcoded_utility_is_in_the_fixture(): void
    {
        $fixture = self::fixture();
        $offenders = [];

        foreach (self::allBladeViews() as $view) {
            $actual = self::countInFile($view);

            if ($actual > 0 && ! array_key_exists($view, $fixture)) {
                $offenders[$view] = $actual;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "View(s) com utilitario de paleta fixa sem entrada na fixture tests/Fixtures/hardcoded-palette-ceiling.json:\n".
            implode("\n", array_map(
                static fn (string $view, int $count): string => sprintf('  %s: %d', $view, $count),
                array_keys($offenders),
                $offenders
            ))
        );
    }

    /**
     * @return array<string, int>
     */
    private static function fixture(): array
    {
        $json = file_get_contents(self::FIXTURE_PATH);

        if ($json === false) {
            throw new RuntimeException('HardcodedPaletteCeilingTest: falha ao ler '.self::FIXTURE_PATH);
        }

        /** @var array<string, int> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private static function allBladeViews(): array
    {
        $root = self::viewsRoot();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        $views = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $views[] = self::relativeToViewsRoot($file->getPathname());
            }
        }

        sort($views);

        return $views;
    }

    private static function countInFile(string $relativeViewPath): int
    {
        $path = self::viewsRoot().'/'.$relativeViewPath;

        if (! is_file($path)) {
            return 0;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('HardcodedPaletteCeilingTest: falha ao ler '.$path);
        }

        return self::countInContent($content);
    }

    private static function countInContent(string $content): int
    {
        $normalized = self::normalize($content);

        preg_match_all(self::BG_PATTERN, $normalized, $bgMatches);
        preg_match_all(self::TEXT_PATTERN, $normalized, $textMatches);

        return count($bgMatches[0]) + count($textMatches[0]);
    }

    /**
     * Normalization order matters: comments and <style> blocks must go before
     * the palette regexes run, or a class name mentioned in prose (a footgun
     * seen in crud-config.blade.php and forge-dashboard-layout.blade.php)
     * counts as markup debt.
     */
    private static function normalize(string $content): string
    {
        // 1. Blade comment.
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content) ?? $content;

        // 2. HTML comment.
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? $content;

        // 3. <style> block — delegated to LayoutStyleBaselineTest; also exempts
        //    crud-print.blade.php, a standalone <!DOCTYPE html> document with its
        //    own <style> and no ptah-components.css.
        $content = preg_replace('#<style[^>]*>.*?</style>#s', '', $content) ?? $content;

        // 4. PHP block comment inside @php.
        $content = preg_replace('#/\*.*?\*/#s', '', $content) ?? $content;

        // 5. PHP whole-line comment (only a whole line — a loose `//` would
        //    break URLs inside markup/strings).
        $content = preg_replace('#^\s*//.*$#m', '', $content) ?? $content;

        return $content;
    }

    private static function viewsRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3).'/resources/views');
    }

    private static function relativeToViewsRoot(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return substr($normalized, strlen(self::viewsRoot()) + 1);
    }
}
