<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\AppearancePresets;
use RuntimeException;

/**
 * Guards the appearance-preset CSS blocks added to the bottom of
 * resources/css/ptah-components.css (aba "Aparência" de /profile —
 * Ptah\Livewire\Auth\ProfilePage / Ptah\Support\AppearancePresets).
 *
 * The golden-master guards (NeutralTokenBaselineTest, ContrastGuardTest) only
 * ever look at `:root` and the FIRST bare `.ptah-dark { ... }` block — they
 * are blind to `html[data-ptah-*="..."]` / `html.ptah-dark[data-ptah-*="..."]`
 * selectors on purpose (those are a different axis entirely). This test is
 * the one guard that actually reads those blocks, so it is the only thing
 * standing between a typo in a hex value and a preset that quietly fails
 * WCAG AA — or a preset offered in the UI with no matching CSS at all, which
 * does not degrade the UI, it DESTROYS it (every var(--ptah-*) that depends
 * on the missing token becomes an invalid declaration).
 *
 * Pure math + file reads, no app boot needed (same idiom as ContrastGuardTest).
 */
class AppearancePresetContrastTest extends TestCase
{
    private const TEXT_TOKEN_ORDER = [
        '--ptah-text-strong',
        '--ptah-text-field',
        '--ptah-text',
        '--ptah-text-secondary',
        '--ptah-text-muted',
        '--ptah-text-faint',
    ];

    private const BACKGROUND_TOKENS = [
        '--ptah-surface',
        '--ptah-canvas',
        '--ptah-surface-sunken',
        '--ptah-surface-hover',
        '--ptah-menu-hover',
        '--ptah-panel',
        '--ptah-field',
        '--ptah-field-muted',
    ];

    // ── Color math (duplicated from ContrastGuardTest on purpose — each guard
    //    test in this suite is intentionally framework-free and self-contained). ──

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function relativeLuminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function contrastRatio(string $hex1, string $hex2): float
    {
        $l1 = self::relativeLuminance(self::hexToRgb($hex1));
        $l2 = self::relativeLuminance(self::hexToRgb($hex2));
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** Maior distância entre canais R/G/B de duas cores — usado nas asserções de separação perceptível abaixo. */
    private static function maxChannelDelta(string $hex1, string $hex2): int
    {
        $a = self::hexToRgb($hex1);
        $b = self::hexToRgb($hex2);

        return max(abs($a[0] - $b[0]), abs($a[1] - $b[1]), abs($a[2] - $b[2]));
    }

    /** Replicates CSS `color-mix(in srgb, $hex $pct%, white)` — see ContrastGuardTest::mixWithWhite(). */
    private static function mixWithWhite(string $hex, float $pct): string
    {
        $c = self::hexToRgb($hex);
        $mixed = [
            (int) round($c[0] * $pct + 255 * (1 - $pct)),
            (int) round($c[1] * $pct + 255 * (1 - $pct)),
            (int) round($c[2] * $pct + 255 * (1 - $pct)),
        ];

        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    // ── CSS parsing ──────────────────────────────────────────────────────────

    private static function css(): string
    {
        static $css = null;

        return $css ??= file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
    }

    /**
     * Extracts the declaration body of the FIRST rule whose selector list
     * matches $selectorPattern (regex, no delimiters/flags needed beyond what
     * you pass), then parses every `--token: value;` pair inside it.
     *
     * @return array<string, string>
     */
    private static function block(string $selectorPattern, string $label): array
    {
        $pattern = '/'.$selectorPattern.'\s*\{([^}]*)\}/';

        if (! preg_match($pattern, self::css(), $m)) {
            throw new RuntimeException("AppearancePresetContrastTest: bloco CSS nao encontrado para [{$label}] (pattern: {$pattern}).");
        }

        return self::parseDeclarations($m[1]);
    }

    /** @return array<string, string> */
    private static function parseDeclarations(string $body): array
    {
        $tokens = [];

        if (preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tokens[$match[1]] = trim($match[2]);
            }
        }

        return $tokens;
    }

    /** @return array<string, array<string, string>> slug => token map */
    private static function lightTonePresets(): array
    {
        return [
            'puro' => self::block('html\[data-ptah-light="puro"\]:not\(\.ptah-dark\)', 'tom claro puro'),
            'papel' => self::block('html\[data-ptah-light="papel"\]:not\(\.ptah-dark\)', 'tom claro papel'),
            'nevoa' => self::block('html\[data-ptah-light="nevoa"\]:not\(\.ptah-dark\)', 'tom claro nevoa'),
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function darkTonePresets(): array
    {
        return [
            'carvao' => self::block('html\.ptah-dark\[data-ptah-dark="carvao"\]', 'tom escuro carvao'),
            'grafite' => self::block('html\.ptah-dark\[data-ptah-dark="grafite"\]', 'tom escuro grafite'),
            'meianoite' => self::block('html\.ptah-dark\[data-ptah-dark="meianoite"\]', 'tom escuro meianoite'),
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function lightTextPresets(): array
    {
        return [
            'suave' => self::block('html\[data-ptah-text="suave"\]:not\(\.ptah-dark\)', 'texto claro suave'),
            'neutra' => self::block('html\[data-ptah-text="neutra"\]:not\(\.ptah-dark\)', 'texto claro neutra'),
            'forte' => self::block('html\[data-ptah-text="forte"\]:not\(\.ptah-dark\)', 'texto claro forte'),
        ];
    }

    /** @return array<string, array<string, string>> */
    private static function darkTextPresets(): array
    {
        return [
            'suave' => self::block('html\.ptah-dark\[data-ptah-text="suave"\]', 'texto escuro suave'),
            'neutra' => self::block('html\.ptah-dark\[data-ptah-text="neutra"\]', 'texto escuro neutra'),
            'forte' => self::block('html\.ptah-dark\[data-ptah-text="forte"\]', 'texto escuro forte'),
        ];
    }

    /** @return array<string, string> accent slug => hex from --color-primary */
    private static function accentPresets(): array
    {
        $presets = [];

        foreach (AppearancePresets::ACCENT as $slug) {
            $tokens = self::block('html\[data-ptah-accent="'.$slug.'"\]', "accent {$slug}");
            $presets[$slug] = strtolower($tokens['--color-primary'] ?? '');
        }

        return $presets;
    }

    /** @return array<string, string> 8 background-role => hex */
    private static function backgroundsOf(array $toneTokens): array
    {
        $backgrounds = [];

        foreach (self::BACKGROUND_TOKENS as $token) {
            $backgrounds[$token] = $toneTokens[$token] ?? '';
        }

        return $backgrounds;
    }

    // ── 1/2. Tom x escala de texto — cada um dos 6 tokens de texto vs cada um
    //    dos 8 fundos do tom, nos dois modos (3x3 combinacoes por modo). ──

    #[Test]
    public function every_tone_x_text_scale_combination_passes_aa_text_contrast_in_light_mode(): void
    {
        $this->assertToneXTextScaleContrast(self::lightTonePresets(), self::lightTextPresets(), 'claro');
    }

    #[Test]
    public function every_tone_x_text_scale_combination_passes_aa_text_contrast_in_dark_mode(): void
    {
        $this->assertToneXTextScaleContrast(self::darkTonePresets(), self::darkTextPresets(), 'escuro');
    }

    /**
     * @param  array<string, array<string, string>>  $tones
     * @param  array<string, array<string, string>>  $textScales
     */
    private function assertToneXTextScaleContrast(array $tones, array $textScales, string $modeLabel): void
    {
        foreach ($tones as $toneName => $toneTokens) {
            $backgrounds = self::backgroundsOf($toneTokens);

            foreach ($textScales as $scaleName => $scaleTokens) {
                foreach (self::TEXT_TOKEN_ORDER as $textToken) {
                    $fg = $scaleTokens[$textToken] ?? null;
                    $this->assertNotNull($fg, "Token de texto {$textToken} ausente na escala '{$scaleName}' ({$modeLabel}).");

                    foreach ($backgrounds as $bgToken => $bg) {
                        $this->assertNotSame('', $bg, "Token de fundo {$bgToken} ausente no tom '{$toneName}' ({$modeLabel}).");

                        $ratio = self::contrastRatio($fg, $bg);

                        $this->assertGreaterThanOrEqual(
                            4.5,
                            $ratio,
                            sprintf(
                                '[%s] tom=%s escala=%s token=%s (%s) vs fundo=%s (%s): %.2f:1, abaixo de 4.5:1.',
                                $modeLabel,
                                $toneName,
                                $scaleName,
                                $textToken,
                                $fg,
                                $bgToken,
                                $bg,
                                $ratio
                            )
                        );
                    }
                }
            }
        }
    }

    // ── 2b. Separação perceptível entre tons: dois tons do mesmo modo não podem
    //    pintar --ptah-canvas e --ptah-surface — as duas maiores áreas de tela —
    //    com a mesma cor (ou quase). Pega exatamente o defeito relatado: três
    //    tons claros com --ptah-surface idêntico e --ptah-canvas quase idêntico
    //    passavam em todo o resto e ainda assim eram visualmente o mesmo tom. ──

    #[Test]
    public function every_pair_of_light_tones_has_perceptible_canvas_and_surface_separation(): void
    {
        $this->assertTonePairsAreSeparated(self::lightTonePresets(), 'claro');
    }

    #[Test]
    public function every_pair_of_dark_tones_has_perceptible_canvas_and_surface_separation(): void
    {
        $this->assertTonePairsAreSeparated(self::darkTonePresets(), 'escuro');
    }

    /**
     * @param  array<string, array<string, string>>  $tones
     */
    private function assertTonePairsAreSeparated(array $tones, string $modeLabel): void
    {
        $names = array_keys($tones);

        for ($i = 0; $i < count($names) - 1; $i++) {
            for ($j = $i + 1; $j < count($names); $j++) {
                $toneA = $tones[$names[$i]];
                $toneB = $tones[$names[$j]];

                $canvasDelta = self::maxChannelDelta($toneA['--ptah-canvas'], $toneB['--ptah-canvas']);
                $surfaceDelta = self::maxChannelDelta($toneA['--ptah-surface'], $toneB['--ptah-surface']);
                $delta = max($canvasDelta, $surfaceDelta);

                $this->assertGreaterThanOrEqual(
                    10,
                    $delta,
                    sprintf(
                        '[%s] tons "%s" (canvas=%s surface=%s) e "%s" (canvas=%s surface=%s): '.
                        'maior delta entre --ptah-canvas e --ptah-surface = %d, abaixo do minimo de 10 — '.
                        'as duas maiores areas de tela nao mudam visivelmente entre esses tons.',
                        $modeLabel,
                        $names[$i],
                        $toneA['--ptah-canvas'],
                        $toneA['--ptah-surface'],
                        $names[$j],
                        $toneB['--ptah-canvas'],
                        $toneB['--ptah-surface'],
                        $delta
                    )
                );
            }
        }
    }

    // ── 3. Accent: branco sobre o accent, e o accent (ou -lite no escuro) como
    //    tinta sobre a surface de cada tom. ──

    #[Test]
    public function every_accent_passes_white_ink_on_solid_background(): void
    {
        foreach (self::accentPresets() as $slug => $hex) {
            $ratio = self::contrastRatio('#ffffff', $hex);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf('accent=%s (%s): branco sobre o accent = %.2f:1, abaixo de 4.5:1.', $slug, $hex, $ratio)
            );
        }
    }

    #[Test]
    public function every_accent_passes_as_ink_on_every_light_tone_surface(): void
    {
        $lightTones = self::lightTonePresets();

        foreach (self::accentPresets() as $slug => $hex) {
            foreach ($lightTones as $toneName => $toneTokens) {
                $surface = $toneTokens['--ptah-surface'];
                $ratio = self::contrastRatio($hex, $surface);

                $this->assertGreaterThanOrEqual(
                    4.5,
                    $ratio,
                    sprintf(
                        'accent=%s (%s) como tinta sobre --ptah-surface do tom claro "%s" (%s) = %.2f:1, abaixo de 4.5:1.',
                        $slug,
                        $hex,
                        $toneName,
                        $surface,
                        $ratio
                    )
                );
            }
        }
    }

    #[Test]
    public function every_accent_lite_passes_as_ink_on_every_dark_tone_surface(): void
    {
        if (! preg_match(
            '/--ptah-primary-lite:\s*color-mix\(in srgb, var\(--ptah-primary\) (\d+)%, #ffffff\);/',
            self::css(),
            $m
        )) {
            throw new RuntimeException('AppearancePresetContrastTest: nao encontrei o color-mix() de --ptah-primary-lite.');
        }

        $litePct = ((int) $m[1]) / 100;
        $darkTones = self::darkTonePresets();

        foreach (self::accentPresets() as $slug => $hex) {
            $lite = self::mixWithWhite($hex, $litePct);

            foreach ($darkTones as $toneName => $toneTokens) {
                $surface = $toneTokens['--ptah-surface'];
                $ratio = self::contrastRatio($lite, $surface);

                $this->assertGreaterThanOrEqual(
                    4.5,
                    $ratio,
                    sprintf(
                        'accent=%s -lite (%s, color-mix %d%%) como tinta sobre --ptah-surface do tom escuro "%s" (%s) = %.2f:1, abaixo de 4.5:1.',
                        $slug,
                        $lite,
                        $m[1],
                        $toneName,
                        $surface,
                        $ratio
                    )
                );
            }
        }
    }

    // ── 4. Os 6 degraus de cada escala de texto sao distintos entre si. ──

    #[Test]
    public function the_six_steps_of_every_text_scale_are_visually_distinct(): void
    {
        $scales = [
            'claro/suave' => self::lightTextPresets()['suave'],
            'claro/neutra' => self::lightTextPresets()['neutra'],
            'claro/forte' => self::lightTextPresets()['forte'],
            'escuro/suave' => self::darkTextPresets()['suave'],
            'escuro/neutra' => self::darkTextPresets()['neutra'],
            'escuro/forte' => self::darkTextPresets()['forte'],
        ];

        foreach ($scales as $scaleLabel => $tokens) {
            $steps = array_map(fn (string $t) => $tokens[$t], self::TEXT_TOKEN_ORDER);

            for ($i = 0; $i < count($steps) - 1; $i++) {
                $a = self::hexToRgb($steps[$i]);
                $b = self::hexToRgb($steps[$i + 1]);
                $maxDelta = max(abs($a[0] - $b[0]), abs($a[1] - $b[1]), abs($a[2] - $b[2]));

                $this->assertGreaterThanOrEqual(
                    3,
                    $maxDelta,
                    sprintf(
                        'escala %s: degraus %s (%s) e %s (%s) sao indistinguiveis (delta maximo de canal = %d).',
                        $scaleLabel,
                        self::TEXT_TOKEN_ORDER[$i],
                        $steps[$i],
                        self::TEXT_TOKEN_ORDER[$i + 1],
                        $steps[$i + 1],
                        $maxDelta
                    )
                );
            }
        }
    }

    // ── 4b. Separação perceptível entre escalas de texto consecutivas: cada um
    //    dos 6 papeis precisa mudar pelo menos 12 em 255 de "suave" para "neutra"
    //    e de "neutra" para "forte" — senao escolher outra escala nao muda nada
    //    visivel no texto (o teto de contraste do modo pode encostar dois alvos
    //    quase no mesmo valor e ainda passar em AA). ──

    #[Test]
    public function consecutive_light_text_scales_are_perceptibly_separated(): void
    {
        $this->assertConsecutiveTextScalesAreSeparated(self::lightTextPresets(), 'claro');
    }

    #[Test]
    public function consecutive_dark_text_scales_are_perceptibly_separated(): void
    {
        $this->assertConsecutiveTextScalesAreSeparated(self::darkTextPresets(), 'escuro');
    }

    /**
     * @param  array<string, array<string, string>>  $scalesBySlug
     */
    private function assertConsecutiveTextScalesAreSeparated(array $scalesBySlug, string $modeLabel): void
    {
        $order = ['suave', 'neutra', 'forte'];

        for ($i = 0; $i < count($order) - 1; $i++) {
            $fromSlug = $order[$i];
            $toSlug = $order[$i + 1];

            foreach (self::TEXT_TOKEN_ORDER as $textToken) {
                $fromValue = $scalesBySlug[$fromSlug][$textToken];
                $toValue = $scalesBySlug[$toSlug][$textToken];
                $delta = self::maxChannelDelta($fromValue, $toValue);

                $this->assertGreaterThanOrEqual(
                    12,
                    $delta,
                    sprintf(
                        '[%s] "%s" -> "%s": %s (%s vs %s) muda apenas %d em 255, abaixo do minimo de 12 — '.
                        'a diferenca fica imperceptivel no texto.',
                        $modeLabel,
                        $fromSlug,
                        $toSlug,
                        $textToken,
                        $fromValue,
                        $toValue,
                        $delta
                    )
                );
            }
        }
    }

    // ── 5. Todo valor da whitelist PHP tem bloco CSS correspondente, e vice-versa. ──

    #[Test]
    public function every_whitelisted_light_tone_has_a_css_block_and_vice_versa(): void
    {
        $cssSlugs = array_keys(self::lightTonePresets());
        sort($cssSlugs);
        $whitelist = AppearancePresets::LIGHT;
        sort($whitelist);

        $this->assertSame($whitelist, $cssSlugs);
    }

    #[Test]
    public function every_whitelisted_dark_tone_has_a_css_block_and_vice_versa(): void
    {
        $cssSlugs = array_keys(self::darkTonePresets());
        sort($cssSlugs);
        $whitelist = AppearancePresets::DARK;
        sort($whitelist);

        $this->assertSame($whitelist, $cssSlugs);
    }

    #[Test]
    public function every_whitelisted_text_scale_has_a_css_block_in_both_modes_and_vice_versa(): void
    {
        $lightSlugs = array_keys(self::lightTextPresets());
        $darkSlugs = array_keys(self::darkTextPresets());
        sort($lightSlugs);
        sort($darkSlugs);
        $whitelist = AppearancePresets::TEXT;
        sort($whitelist);

        $this->assertSame($whitelist, $lightSlugs);
        $this->assertSame($whitelist, $darkSlugs);
    }

    #[Test]
    public function every_whitelisted_accent_has_a_css_block_and_vice_versa(): void
    {
        $cssSlugs = array_keys(self::accentPresets());
        sort($cssSlugs);
        $whitelist = AppearancePresets::ACCENT;
        sort($whitelist);

        $this->assertSame($whitelist, $cssSlugs);
    }

    #[Test]
    public function every_accent_hex_matches_its_css_block(): void
    {
        $this->assertSame(AppearancePresets::ACCENT_HEX, self::accentPresets());
    }

    // ── Nenhum bloco de preset pode conter var() — so literais (Erro 3 do coordenador). ──

    #[Test]
    public function css_presets_never_reference_a_var(): void
    {
        $selectors = [
            'html[data-ptah-light="puro"]:not(.ptah-dark)',
            'html[data-ptah-light="papel"]:not(.ptah-dark)',
            'html[data-ptah-light="nevoa"]:not(.ptah-dark)',
            'html.ptah-dark[data-ptah-dark="carvao"]',
            'html.ptah-dark[data-ptah-dark="grafite"]',
            'html.ptah-dark[data-ptah-dark="meianoite"]',
            'html[data-ptah-text="suave"]:not(.ptah-dark)',
            'html[data-ptah-text="neutra"]:not(.ptah-dark)',
            'html[data-ptah-text="forte"]:not(.ptah-dark)',
            'html.ptah-dark[data-ptah-text="suave"]',
            'html.ptah-dark[data-ptah-text="neutra"]',
            'html.ptah-dark[data-ptah-text="forte"]',
        ];

        foreach (array_merge($selectors, array_map(
            static fn (string $slug) => 'html[data-ptah-accent="'.$slug.'"]',
            AppearancePresets::ACCENT
        )) as $selector) {
            $pattern = '/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/';

            if (! preg_match($pattern, self::css(), $m)) {
                throw new RuntimeException("AppearancePresetContrastTest: bloco nao encontrado para o seletor [{$selector}].");
            }

            $this->assertStringNotContainsStringIgnoringCase(
                'var(',
                $m[1],
                "O bloco do seletor [{$selector}] referencia var(...) — presets de aparencia devem conter so literais, ".
                'senao o CssDeclarationExtractor dos testes golden resolve contra o mapa de escopo errado.'
            );
        }
    }

    // ── Grupo 5 (revisao adversarial) — os pares de token novos desta onda
    //    (batches 1-7 + as correcoes dos grupos 2-4) nunca tinham prova de
    //    contraste nos 6 tons — exatamente a lacuna que deixou as regressoes
    //    dos grupos 2/3 passarem verde. Piso 4.5:1 para pares de texto real
    //    (a cor varia por escala — TEXT_TOKEN_ORDER); piso 3:1 (componente
    //    de UI / grafico nao-texto) para o resto.
    //
    //    Cada lado (fg/bg) e resolvido a partir da REGRA REAL em
    //    ptah-components.css (declaredToken()), nao de um nome de token
    //    hardcoded no teste — senao o teste prova um par hipotetico, e
    //    reverter a correcao de um grupo 2/3/4 nao o derruba. So o fundo
    //    "ambiente" (--ptah-surface / --ptah-canvas, quando o par nao mede
    //    contra outro componente) e passado como nome de token literal,
    //    porque nao existe uma regra de componente que o declare. ──

    /**
     * Extrai o token --ptah-* de fato declarado para uma propriedade numa
     * regra exata (selector no INICIO da linha, formato desta folha de
     * estilo — todas as regras alvo do grupo 5 sao de uma linha so). Falha
     * alto se a regra ou a propriedade não existir, em vez de silenciosamente
     * comparar contra o valor errado.
     */
    private static function declaredToken(string $selector, string $property): string
    {
        $selectorPattern = '/^'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m';

        if (! preg_match($selectorPattern, self::css(), $m)) {
            throw new RuntimeException("AppearancePresetContrastTest (grupo 5): regra exata [{$selector}] nao encontrada em ptah-components.css.");
        }

        $propPattern = '/(?<![\w-])'.preg_quote($property, '/').'\s*:\s*var\((--ptah-[a-z0-9-]+)/i';

        if (! preg_match($propPattern, $m[1], $pm)) {
            throw new RuntimeException("AppearancePresetContrastTest (grupo 5): propriedade [{$property}] com var(--ptah-*) nao encontrada na regra [{$selector}].");
        }

        return $pm[1];
    }

    /**
     * Resolve um nome de token (--ptah-*) contra, em ordem: a escala de
     * texto do modo (se for um dos 6 tokens de TEXT_TOKEN_ORDER), o tom do
     * modo (se o preset de tom declarar esse token), ou :root (tokens
     * invariantes entre escopo, ex. --ptah-line-field-hover,
     * --ptah-text-on-accent — nunca redeclarados em nenhum bloco de preset).
     *
     * @param  array<string, string>  $toneTokens
     * @param  array<string, string>  $textTokens
     */
    private static function resolveAnyToken(string $token, array $toneTokens, array $textTokens): string
    {
        if (isset($textTokens[$token])) {
            return $textTokens[$token];
        }

        if (isset($toneTokens[$token])) {
            return $toneTokens[$token];
        }

        return self::rootToken($token);
    }

    /** Le um valor literal do bloco :root (tokens invariantes entre tom/escuro). */
    private static function rootToken(string $token): string
    {
        $root = self::block(':root', 'root default');

        if (! isset($root[$token])) {
            throw new RuntimeException("AppearancePresetContrastTest: token {$token} nao encontrado em :root (nem em nenhum preset de tom/escala).");
        }

        return $root[$token];
    }

    /**
     * Cruza os 6 tons (3 claros + 3 escuros) com as 3 escalas de texto de
     * cada modo sempre que o token de QUALQUER lado varia por escala (mesma
     * logica de assertToneXTextScaleContrast, só que para um par especifico
     * em vez do produto completo de 6 texto x 8 fundo).
     *
     * $fgSelector/$fgProperty apontam para a regra real que pinta o
     * primeiro plano; o token e extraido de la em cada modo (light:
     * $fgSelector puro; dark: ".ptah-dark " . $fgSelector), entao a prova
     * cai se a regra for revertida para um token pior. $bgToken e o nome
     * literal do token de fundo "ambiente" (--ptah-surface/--ptah-canvas) —
     * nao ha regra de componente para extrair, e o proprio nome ja e o que
     * se quer provar contra.
     */
    private function assertRuleMeetsFloorAgainstAmbient(
        string $pairLabel,
        string $fgSelector,
        string $fgProperty,
        string $bgToken,
        float $floor
    ): void {
        $bgVariesByScale = in_array($bgToken, self::TEXT_TOKEN_ORDER, true);

        $modes = [
            'claro' => [self::lightTonePresets(), self::lightTextPresets(), $fgSelector],
            'escuro' => [self::darkTonePresets(), self::darkTextPresets(), '.ptah-dark '.$fgSelector],
        ];

        foreach ($modes as $modeLabel => [$tones, $texts, $fgRuleSelector]) {
            $fgToken = self::declaredToken($fgRuleSelector, $fgProperty);
            $fgVariesByScale = in_array($fgToken, self::TEXT_TOKEN_ORDER, true);

            foreach ($tones as $toneName => $toneTokens) {
                $scales = ($fgVariesByScale || $bgVariesByScale) ? $texts : ['(invariante)' => []];

                foreach ($scales as $scaleName => $textTokens) {
                    $fg = self::resolveAnyToken($fgToken, $toneTokens, $textTokens);
                    $bg = self::resolveAnyToken($bgToken, $toneTokens, $textTokens);
                    $ratio = self::contrastRatio($fg, $bg);

                    $this->assertGreaterThanOrEqual(
                        $floor,
                        $ratio,
                        sprintf(
                            '[%s] par=%s tom=%s escala=%s: %s::%s -> %s (%s) vs %s (%s) = %.2f:1, abaixo do piso %.1f:1.',
                            $modeLabel,
                            $pairLabel,
                            $toneName,
                            $scaleName,
                            $fgRuleSelector,
                            $fgProperty,
                            $fgToken,
                            $fg,
                            $bgToken,
                            $bg,
                            $ratio,
                            $floor
                        )
                    );
                }
            }
        }
    }

    /**
     * Mesma logica de assertRuleMeetsFloorAgainstAmbient, mas o "fundo" TAMBEM
     * e extraido de uma regra real de componente (ex.: chat_dot contra o
     * proprio chat_bubble), nao de um token ambiente solto — assim, se
     * alguem mudar o fundo do componente-alvo sem saber que outro elemento
     * depende dele, a prova cai tambem.
     */
    private function assertTwoRulesMeetFloor(
        string $pairLabel,
        string $fgSelector,
        string $fgProperty,
        string $bgSelector,
        string $bgProperty,
        float $floor
    ): void {
        $modes = [
            'claro' => [self::lightTonePresets(), self::lightTextPresets(), $fgSelector, $bgSelector],
            'escuro' => [self::darkTonePresets(), self::darkTextPresets(), '.ptah-dark '.$fgSelector, '.ptah-dark '.$bgSelector],
        ];

        foreach ($modes as $modeLabel => [$tones, $texts, $fgRuleSelector, $bgRuleSelector]) {
            $fgToken = self::declaredToken($fgRuleSelector, $fgProperty);
            $bgToken = self::declaredToken($bgRuleSelector, $bgProperty);
            $fgVariesByScale = in_array($fgToken, self::TEXT_TOKEN_ORDER, true);
            $bgVariesByScale = in_array($bgToken, self::TEXT_TOKEN_ORDER, true);

            foreach ($tones as $toneName => $toneTokens) {
                $scales = ($fgVariesByScale || $bgVariesByScale) ? $texts : ['(invariante)' => []];

                foreach ($scales as $scaleName => $textTokens) {
                    $fg = self::resolveAnyToken($fgToken, $toneTokens, $textTokens);
                    $bg = self::resolveAnyToken($bgToken, $toneTokens, $textTokens);
                    $ratio = self::contrastRatio($fg, $bg);

                    $this->assertGreaterThanOrEqual(
                        $floor,
                        $ratio,
                        sprintf(
                            '[%s] par=%s tom=%s escala=%s: %s::%s -> %s (%s) vs %s::%s -> %s (%s) = %.2f:1, abaixo do piso %.1f:1.',
                            $modeLabel,
                            $pairLabel,
                            $toneName,
                            $scaleName,
                            $fgRuleSelector,
                            $fgProperty,
                            $fgToken,
                            $fg,
                            $bgRuleSelector,
                            $bgProperty,
                            $bgToken,
                            $bg,
                            $ratio,
                            $floor
                        )
                    );
                }
            }
        }
    }

    #[Test]
    public function chart_ttl_passes_aa_text_contrast_on_chart_surface(): void
    {
        $this->assertTwoRulesMeetFloor(
            'chart_ttl/chart_surface',
            '.ptah-c-chart_ttl', 'color',
            '.ptah-c-chart_surface', 'background-color',
            4.5
        );
    }

    #[Test]
    public function switch_track_off_passes_ui_component_contrast_on_surface(): void
    {
        $this->assertRuleMeetsFloorAgainstAmbient(
            'switch_track_off/surface',
            '.ptah-c-switch_track_off', 'background-color',
            '--ptah-surface',
            3.0
        );
    }

    /**
     * The thumb (knob) uses a DIFFERENT token per mode
     * (--ptah-surface in light, --ptah-text-on-accent in dark) — proved
     * against its own track (not against surface), extracting both sides
     * from their real rules so a revert of either fails this test.
     */
    #[Test]
    public function switch_thumb_passes_ui_component_contrast_against_its_own_track_in_every_dark_tone(): void
    {
        $this->assertTwoRulesMeetFloor(
            'switch_thumb/switch_track_off (escuro)',
            '.ptah-c-switch_thumb', 'background-color',
            '.ptah-c-switch_track_off', 'background-color',
            3.0
        );
    }

    #[Test]
    public function step_circle_idle_text_passes_aa_text_contrast_on_its_own_background(): void
    {
        $this->assertTwoRulesMeetFloor(
            'step_circle_idle texto/fundo',
            '.ptah-c-step_circle_idle', 'color',
            '.ptah-c-step_circle_idle', 'background-color',
            4.5
        );
    }

    #[Test]
    public function step_line_passes_ui_component_contrast_on_surface(): void
    {
        $this->assertRuleMeetsFloorAgainstAmbient(
            'step_line/surface',
            '.ptah-c-step_line', 'background-color',
            '--ptah-surface',
            3.0
        );
    }

    #[Test]
    public function progress_track_passes_ui_component_contrast_on_surface(): void
    {
        $this->assertRuleMeetsFloorAgainstAmbient(
            'progress_track/surface',
            '.ptah-c-progress_track', 'background-color',
            '--ptah-surface',
            3.0
        );
    }

    #[Test]
    public function pag_btn_text_passes_aa_text_contrast_on_surface(): void
    {
        $this->assertRuleMeetsFloorAgainstAmbient(
            'pag_btn texto/fundo',
            '.ptah-c-pag_btn', 'color',
            '--ptah-surface',
            4.5
        );
    }

    #[Test]
    public function pag_btn_border_passes_ui_component_contrast_on_canvas(): void
    {
        $this->assertRuleMeetsFloorAgainstAmbient(
            'pag_btn borda/canvas',
            '.ptah-c-pag_btn', 'border-color',
            '--ptah-canvas',
            3.0
        );
    }

    #[Test]
    public function chat_dot_passes_non_text_contrast_on_chat_bubble(): void
    {
        $this->assertTwoRulesMeetFloor(
            'chat_dot/chat_bubble',
            '.ptah-c-chat_dot', 'background-color',
            '.ptah-c-chat_bubble', 'background-color',
            3.0
        );
    }

    #[Test]
    public function prof_icon_glyph_passes_non_text_contrast_on_prof_chip(): void
    {
        $this->assertTwoRulesMeetFloor(
            'prof_icon glifo/fundo',
            '.ptah-c-prof_icon', 'color',
            '.ptah-c-prof_chip', 'background-color',
            3.0
        );
    }

    #[Test]
    public function code_text_passes_aa_text_contrast_on_its_own_background(): void
    {
        $this->assertTwoRulesMeetFloor(
            'code texto/fundo',
            '.ptah-c-code', 'color',
            '.ptah-c-code', 'background-color',
            4.5
        );
    }

    #[Test]
    public function code_cap_text_passes_aa_text_contrast_on_its_own_background(): void
    {
        $this->assertTwoRulesMeetFloor(
            'code_cap texto/fundo',
            '.ptah-c-code_cap', 'color',
            '.ptah-c-code_cap', 'background-color',
            4.5
        );
    }
}
