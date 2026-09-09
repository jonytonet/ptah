<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The Boost guideline may not teach what the build forbids.
 *
 * `resources/boost/guidelines/core.md` is published to Boost, which means it is
 * in an agent's context on every request. `SKILL.md` is loaded on demand. So
 * when the two disagreed, the WRONG advice had context precedence over the
 * right one — and it disagreed on the single thing this package spends the most
 * effort on.
 *
 * What core.md said, for several releases:
 *
 *   "All CSS (including dark overrides) lives in forge-dashboard-layout.blade.php"
 *   "Dark variant pattern: .ptah-dark .my-component { background: #1e293b; }"
 *
 * Both are the opposite of what the repository enforces.
 * `LayoutStyleBaselineTest` is a golden master over that inline block precisely
 * because it retrofits dark mode onto the chrome with no tokens at all, so the
 * user-selectable theme cannot reach any of it — the guideline was pointing
 * consumers at the debt the package is paying off. And the hex in the example
 * contradicted core.md's OWN anti-pattern table, forty-six lines below, which
 * lists a hex in Blade/CSS as a thing not to do.
 *
 * Worse for a consumer: in an app that layout lives in `vendor/`. Following the
 * advice means either editing a vendored file — lost on the next
 * `composer update` — or publishing the view and never receiving an update to
 * it again. core.md did not distinguish the two audiences; `CustomScreens.md`
 * does.
 *
 * ── Why a test and not just a fix ────────────────────────────────────────
 *
 * A written rule on its own has already failed here once.
 * `HardcodedPaletteCeilingTest` exists because `KnownLimitations.md` forbade new
 * fixed-palette utilities from 1.15.0 and the count grew from 999 to 1019
 * anyway. Prose does not hold a line; a failing build does.
 */
class GuidelinesCssParityTest extends TestCase
{
    private const GUIDELINE = __DIR__.'/../../../resources/boost/guidelines/core.md';

    private const SKILL = __DIR__.'/../../../resources/boost/skills/ptah-development/SKILL.md';

    private static function read(string $path): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('GuidelinesCssParityTest: falha ao ler '.$path);
        }

        return str_replace("\r\n", "\n", $raw);
    }

    /**
     * Does a negation or a counter-example marker sit within reach of this line?
     *
     * A three-line window, and the window is the point. The first version of
     * this guard read one line at a time and produced false failures against
     * correct text: SKILL.md's sentence about the legacy block wraps, so
     * "dismantled, not extended" lands on the line above the filename, and
     * core.md's deliberate wrong-way example carries its marker on the comment
     * line above the declaration.
     *
     * Sentences in prose span lines. A line-based check on something whose
     * meaning does not fit on one line is a mistake this suite has made before.
     *
     * @param  list<string>  $lines
     */
    private static function negatedNearby(array $lines, int $i): bool
    {
        for ($j = max(0, $i - 2); $j <= $i; $j++) {
            if (preg_match('/\x{274C}|\b(never|not|nao|avoid|wrong|legacy|dismantl\w*|anti)\b/iu', $lines[$j]) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lines that name the layout as a place CSS goes.
     *
     * Matched on the sentence, not on the filename: core.md legitimately
     * mentions `forge-dashboard-layout` when listing the icon libraries it
     * loads, and both documents now mention it to say NOT to write there. What
     * must never appear is the layout on the receiving end of CSS.
     *
     * @return list<string>
     */
    private static function cssDestinationClaims(string $text): array
    {
        $lines = explode("\n", $text);
        $hits = [];

        foreach ($lines as $i => $line) {
            if (! str_contains($line, 'forge-dashboard-layout')) {
                continue;
            }

            if (self::negatedNearby($lines, $i)) {
                continue;
            }

            if (preg_match('/\b(CSS|style|styles|overrides?)\b/i', $line) === 1) {
                $hits[] = trim($line);
            }
        }

        return $hits;
    }

    #[Test]
    public function the_guideline_does_not_send_css_to_the_layout(): void
    {
        $hits = self::cssDestinationClaims(self::read(self::GUIDELINE));

        $this->assertSame(
            [],
            $hits,
            "core.md aponta o forge-dashboard-layout como destino de CSS. O build proibe:\n".
            "LayoutStyleBaselineTest falha se aquele bloco ganhar uma regra ou um literal de cor.\n".
            "Host: `var(--ptah-*)` no elemento ou em resources/css/app.css.\n".
            "Pacote: classe `.ptah-c-*` em resources/css/ptah-components.css.\n".
            implode("\n", array_map(static fn (string $l): string => '  '.$l, $hits))
        );
    }

    #[Test]
    public function the_skill_does_not_send_css_to_the_layout_either(): void
    {
        // The one that was already right. Pinned so a future edit cannot make
        // the two disagree again in the other direction.
        $this->assertSame([], self::cssDestinationClaims(self::read(self::SKILL)));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function documentProvider(): array
    {
        return [
            'core.md' => [self::GUIDELINE],
            'SKILL.md' => [self::SKILL],
        ];
    }

    #[Test]
    #[DataProvider('documentProvider')]
    public function no_hex_literal_is_taught_as_a_component_colour(string $path): void
    {
        // The contradiction that made this worth a test: core.md's example wrote
        // `background: #1e293b` while core.md's own anti-pattern table forbids a
        // hex in Blade/CSS.
        //
        // Two exemptions, both real, and the second one is a class this guard's
        // first version did not anticipate and immediately reported:
        //
        //   1. A deliberate counter-example, marked (see negatedNearby).
        //
        //   2. A `contitionStyles` value — `--style="is_active:==:0:background:#FEF2F2"`
        //      or `"style": "background:#FEFCE8"`. That feature TAKES a CSS
        //      string; a hex there is the documented contract, not advice about
        //      how to style a component. Those examples would read better with
        //      `var(--ptah-*)`, since a row highlight in a fixed hex ignores the
        //      chosen tone just as much — but that is a separate improvement to
        //      the examples, not a contradiction between two documents, and
        //      conflating the two would make this guard fail for the wrong
        //      reason.
        $lines = explode("\n", self::read($path));
        $offenders = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/(background|color|border|fill|stroke|outline|box-shadow)\s*:\s*[^;\n]*#[0-9A-Fa-f]{3,8}\b/', $line) !== 1) {
                continue;
            }

            if (self::negatedNearby($lines, $i)) {
                continue;
            }

            // The crud_config style vocabulary, not component CSS. The DSL
            // shape `field:operator:value:css` counts too: the guard's first
            // version only knew the `--style=` and `"style":` spellings and
            // reported a third, written as prose — `is_active:==:0:background:#FEF2F2`.
            if (preg_match('/--style=|"style"\s*:|colsCellStyle|contitionStyles|conditionStyles/', $line) === 1) {
                continue;
            }

            if (preg_match('/\w+:(==|!=|<=|>=|<|>):/', $line) === 1) {
                continue;
            }

            $offenders[] = sprintf('  linha %d: %s', $i + 1, trim($line));
        }

        $this->assertSame(
            [],
            $offenders,
            "Literal hex ensinado como cor de componente. Use `var(--ptah-*)`, senao o\n".
            "elemento ignora o tom que o usuario escolheu em /profile:\n".
            implode("\n", $offenders)
        );
    }

    #[Test]
    public function the_guideline_points_at_the_token_vocabulary(): void
    {
        // The counterpart. A guard that only forbids leaves the reader with
        // nothing, and "no hex" is not advice — `var(--ptah-*)` is.
        $text = self::read(self::GUIDELINE);

        $this->assertStringContainsString('var(--ptah-', $text);
        $this->assertStringContainsString('ptah-components.css', $text);
    }

    #[Test]
    public function the_guideline_distinguishes_the_host_from_the_package(): void
    {
        // The scope fault underneath the wrong advice: it was written for
        // someone editing ptah, and core.md's audience is someone consuming it.
        $this->assertStringContainsString('app.css', self::read(self::GUIDELINE));
    }
}
