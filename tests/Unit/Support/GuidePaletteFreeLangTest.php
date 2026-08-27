<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ratchet for fixed-palette Tailwind utilities embedded in the `guide_*`
 * lang keys (`resources/lang/{pt_BR,en}/ui.php`) that back
 * `/ptah-permission-guide`.
 *
 * Why this exists as a SEPARATE guard from `HardcodedPaletteCeilingTest`:
 * that test only ever reads Blade VIEW files — never lang files — so 23
 * `bg-slate-100`/`bg-indigo-100`/`text-indigo-600`/`text-purple-700`/
 * `text-blue-700`/`text-slate-400` utilities survived for releases inside
 * `<code>`/`<span>`/`<strong>` markup baked directly into the translated
 * strings themselves (`guide_ov_body`, `guide_con_scope_body`,
 * `guide_s4_ex1`, `guide_faq_a8`, …), invisible to that ratchet no matter how
 * clean the view got. Unlike the view-level guard (scoped to the
 * gray/slate/zinc/neutral/stone/white/black family, because that is the
 * historical debt it tracks), this one flags ANY raw Tailwind palette
 * utility — including blue/indigo/purple/green/amber/red/… — since a
 * lang-embedded color is exactly as immune to the appearance preset either
 * way. It does NOT flag the package's own semantic aliases (`text-primary`,
 * `bg-success`, `border-warn`, …), which already resolve through
 * `--color-*`/`--ptah-*` tokens — those are the correct, intended way to
 * color a lang string, not the debt this test guards against.
 *
 * A per-key ratchet, not a per-file ceiling like `HardcodedPaletteCeilingTest`:
 * `guide_*` keys are prose, not markup a ceiling would meaningfully bound —
 * the bar here is zero, always, in both locales.
 */
class GuidePaletteFreeLangTest extends TestCase
{
    private const PALETTE_PATTERN = '/\b(?:bg|text|border)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)(-\d+)?(\/\d+)?\b/';

    /** @return array<string, array{0: string}> */
    public static function localeProvider(): array
    {
        return [
            'pt_BR' => ['pt_BR'],
            'en' => ['en'],
        ];
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function no_guide_key_carries_a_hardcoded_palette_utility(string $locale): void
    {
        $lang = self::lang($locale);
        $offenders = [];

        foreach ($lang as $key => $value) {
            if (! str_starts_with($key, 'guide_') || ! is_string($value)) {
                continue;
            }

            if (preg_match_all(self::PALETTE_PATTERN, $value, $matches)) {
                $offenders[$key] = implode(', ', $matches[0]);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            sprintf(
                "Chave(s) guide_* em resources/lang/%s/ui.php com utilitario de paleta fixa:\n%s\n".
                'A cor de superficie/texto de uma tela do pacote vai numa classe .ptah-c-* '.
                'em resources/css/ptah-components.css usando var(--ptah-*), ou no alias semantico '.
                'ja existente (text-primary/bg-success/etc) — nunca um utilitario Tailwind de paleta fixa, '.
                'nem mesmo dentro de uma string de idioma.',
                $locale,
                implode("\n", array_map(
                    static fn (string $key, string $found): string => "  {$key}: {$found}",
                    array_keys($offenders),
                    $offenders
                ))
            )
        );
    }

    /** @return array<string, mixed> */
    private static function lang(string $locale): array
    {
        return require dirname(__DIR__, 3)."/resources/lang/{$locale}/ui.php";
    }
}
