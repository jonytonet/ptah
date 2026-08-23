<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Generalized guard against the defect class behind the page background, the
 * 4 large chrome surfaces (ThemeSurfaceLightDarkParityTest) and the root
 * text-colour rule: a `.ptah-dark <selector> { ... var(--ptah-*) ... }` rule
 * with NO light-mode counterpart on the exact same selector — so the tone or
 * font-colour axis the user picks in /profile silently never reaches that
 * element in light mode, and nothing else in the suite notices.
 *
 * Every one of those four incidents was found the same way: a user
 * complained, someone grepped for the one selector involved, and the suite
 * was green the whole time because no test enumerated the *set* of dark
 * rules and asked "does light have one too?". This test asks exactly that,
 * for every selector in resources/css/ptah-components.css, not just the
 * handful that already have a dedicated regression test.
 *
 * A "site" here is a single selector (after splitting comma-separated
 * selector lists — see splitSelectors()) whose full string starts with the
 * literal `.ptah-dark ` (dot-ptah-dark-SPACE: a descendant combinator, not
 * the bare `.ptah-dark { ... }` token block and not a
 * `html.ptah-dark[data-ptah-dark="..."]` preset override — neither of those
 * two matches this prefix) and whose declaration body contains at least one
 * `var(--ptah-*)` reference.
 *
 * For each such dark site, this test requires ONE of:
 *   - a rule for the exact same selector remainder (i.e. without the
 *     `.ptah-dark ` prefix) that ALSO declares at least one `var(--ptah-*)`
 *     value — the light side actually reacts to the user's chosen tone; or
 *   - an entry in EXCEPTIONS below, each with a one-line reason. An
 *     exception is not a config toggle: every entry is re-verified as still
 *     genuinely orphaned every run (see exceptions_are_still_necessary()),
 *     so a stale exception (someone added the light rule but forgot to
 *     delete the entry here) fails loudly instead of quietly hiding the fix.
 */
class ThemeChromeOrphanTokenGuardTest extends TestCase
{
    /**
     * selector (light-side remainder, exact string as it appears in the CSS
     * after collapsing whitespace) => one-line reason it is NOT a defect.
     *
     * @var array<string, string>
     */
    private const EXCEPTIONS = [
        // ── (C) legitimately dark-only ──────────────────────────────────
        '.ptah-c-tbody_div > * + *' => 'Light rows already carry a FULL-OPACITY border-bottom via .ptah-c-tr '.
            '(border-color: var(--ptah-line)). The dark .ptah-c-tr border-bottom is only a 40%-opacity '.
            'color-mix(), so this extra top border exists purely to reinforce that faint seam in dark — '.
            'light needs no equivalent because its own row border is already fully visible.',

        // ── (B) already covered — via a different mechanism than a plain
        //        light-scope var(--ptah-*) rule in this file ─────────────
        '.ptah-c-dd_opt' => 'Light color is `inherit`, which resolves up to the `html { color: '.
            'var(--ptah-text-strong) }` root rule (ThemeSurfaceLightDarkParityTest) — already token-driven, '.
            'just not declared locally.',
        '.ptah-btn.ptah-btn-dark' => "forge-button.blade.php's colorMap['dark']['text'] is the Tailwind ".
            'utility `text-dark` (the --color-dark brand/semantic token), baked directly into the component '.
            'for the flat variant — this stylesheet only needs to override it back for the .ptah-dark scope.',
        '.ptah-btn.ptah-btn-dark:hover' => "Same as above: colorMap['dark']['flatHover'] is `hover:bg-dark-light` ".
            '(the --color-dark-light token), already in forge-button.blade.php.',
        '.ptah-c-fp_cancel_btn:hover' => 'Light already has its own rule (color: #4b5563), documented in the '.
            'CSS itself as a Fase-2 literal exception (no token matches that exact gray) — present, just not a '.
            'var(--ptah-*).',
        '.ptah-c-tab_active_success' => 'Light uses var(--color-success) — a brand/semantic token, not a '.
            '--ptah-* neutral, but equally theme-aware.',
        '.ptah-c-tab_active_danger' => 'Light uses var(--color-danger) — same reasoning as tab_active_success.',
        '.ptah-c-tab_active_warn' => 'Light uses var(--color-warn) — same reasoning as tab_active_success.',
        '.ptah-sidebar .ptah-nav-item.ptah-nav-active' => 'Light styling is the accent utility pair '.
            '`bg-primary-light text-primary` directly in forge-sidebar.blade.php — already theme-aware via '.
            '--color-primary, and out of scope for the NEUTRAL text-colour axis this guard protects.',
        '.ptah-navbar .ptah-user-avatar-bg' => 'Light styling is the accent utility `bg-primary-light` directly '.
            'in forge-navbar.blade.php — same reasoning as the sidebar active pill above.',
        '.ptah-navbar .ptah-user-avatar-text' => 'Light styling is the accent utility `text-primary` directly '.
            'in forge-navbar.blade.php — same reasoning as the sidebar active pill above.',
        '.ptah-navbar .ptah-user-dropdown button' => 'The only <button> here is the logout action, which keeps '.
            'its own `text-danger` accent utility in light (forge-navbar.blade.php). A var(--ptah-text) rule on '.
            'this selector would win the cascade over that utility and repaint the logout button neutral gray '.
            'in light mode — a regression, not a fix. `.ptah-navbar .ptah-user-dropdown a` (the profile link) '.
            'IS tokenized; `button` deliberately is not.',
        '.ptah-cfg-content .cfg-ink-warn' => 'Light rule exists (color: #b45309) but is an intentional literal — '.
            'the exact hex of the text-amber-700 utility it replaces, same "keep the original value" idiom as '.
            '.ptah-c-fp_cancel_btn:hover above — not a var(--ptah-*), by design (see '.
            'CrudConfigThemeParityTest::cfg_ink_warn_keeps_the_original_light_literal_and_tokenizes_dark).',

        // ── (C) legitimately dark-only, CRUD Config editor Onda 5 ─────────
        // These four sit inside a literal-tinted callout (bg-sky-50 /
        // bg-indigo-50) that renders the SAME near-white background in both
        // themes. Light mode needs no extra rule here: the base (non-nested)
        // `.ptah-cfg-content .text-slate-400/500/600` light rule already
        // applies correctly (identity — the callout's background never
        // drifted in light to begin with). The dark override exists purely
        // to COUNTERACT that same base rule's DARK value, which assumes the
        // ambient canvas went dark — it never does inside these two boxes.
        '.ptah-cfg-content .bg-sky-50 .text-slate-400' => 'Callout keeps the same near-white bg-sky-50 background '.
            'in light mode too, where the base .text-slate-400 rule already resolves correctly — this override '.
            'only needs to exist in .ptah-dark, to undo that same rule\'s dark value inside this one literal box.',
        '.ptah-cfg-content .bg-sky-50 .text-slate-500' => 'Same reasoning as .bg-sky-50 .text-slate-400 above.',
        '.ptah-cfg-content .bg-sky-50 .text-slate-600' => 'Same reasoning as .bg-sky-50 .text-slate-400 above.',
        '.ptah-cfg-content .bg-indigo-50 .text-slate-500' => 'Same reasoning as .bg-sky-50 .text-slate-400 above, '.
            'for the cascading-dropdown hint box (bg-indigo-50) instead of the SQL-source guide.',

        // ── (C) legitimately dark-only, CRUD Config editor Onda 6 (browser
        //        audit — see CrudConfigDarkContrastBrowserTest) ────────────
        '.ptah-cfg-content .bg-indigo-50 .cfg-label' => 'Same reasoning as .bg-sky-50 .text-slate-400 above: the '.
            'cascading-dropdown box keeps the same near-white background in light mode, where .cfg-label\'s base '.
            'rule already resolves correctly — this override only undoes that rule\'s dark value inside the box.',
        '.ptah-cfg-content .bg-emerald-50 .text-slate-500' => 'Same reasoning as .bg-sky-50 .text-slate-400 above, '.
            'for the SearchDropdown "initial load / result limit" highlight box (bg-emerald-50).',
        '.ptah-cfg-content .bg-emerald-50 .text-slate-700' => 'Same reasoning as .bg-sky-50 .text-slate-400 above, '.
            'for the bg-emerald-50 box (the checkbox label, not just its hint paragraph).',
        '.ptah-cfg-content .bg-emerald-50 .cfg-label' => 'Same reasoning as .bg-sky-50 .text-slate-400 above, for '.
            'the bg-emerald-50 box\'s own "Results Limit" field label.',
        '.ptah-cfg-content .text-primary' => 'Light mode already renders the raw Tailwind `text-primary` utility '.
            '(itself var(--color-primary)) unmodified — already tone-aware via the host\'s own token, just not '.
            'through a --ptah-* alias. The dark override swaps it to --ptah-primary-lite ONLY because the '.
            'ambient card this text sits on (active sub-tab label, "Preview form"/"Edit join" buttons, guide-box '.
            'headers) goes dark — same "-lite on a dark card" idiom as .ptah-c-dd_item_sel and the active-tab '.
            'indicators, just applied to this view\'s own raw utility class instead of a component class.',
        '.ptah-cfg-content .text-red-600' => 'Same reasoning as .ptah-cfg-content .text-primary above, for the '.
            'JOINs tab\'s "Remove" button (raw text-red-600, swapped to --ptah-danger-lite on the dark card).',
        '.ptah-cfg-content .bg-primary-light\/50' => 'Light mode already renders this translucent highlight/guide '.
            'box correctly (a pale tint over the white ambient) — no override needed there. The dark override '.
            'exists purely because, UNLIKE the opaque tint boxes above, this one is translucent: composited over '.
            'the dark ambient it becomes a mid-tone grey-lavender no existing ink token can pair with, so dark '.
            'mode instead drops the tint entirely (same "shed the translucency" fix as the sky-50 box).',
        '.ptah-cfg-content .bg-primary-light .text-slate-700' => 'The validation-rule / column-visibility checkbox cards\' '.
            '"selected" state (border-primary bg-primary-light) keeps the same fixed light accent tint in light '.
            'mode, where the base .text-slate-700 rule already resolves correctly — this override only undoes '.
            'that rule\'s dark value for the ambient (never dark) surface inside this one utility combination.',
        '.ptah-cfg-content .bg-primary-light .text-slate-400' => 'Same reasoning as '.
            '.ptah-cfg-content .bg-primary-light .text-slate-700 above, for the JOINs guide\'s worked-example '.
            'chip and the theme-selector\'s "White background, light gray borders" description.',
        '.ptah-cfg-content .bg-primary-light .text-slate-500' => 'Same reasoning as '.
            '.ptah-cfg-content .bg-primary-light .text-slate-700 above, for the JOINs guide\'s '.
            '"Na aba Colunas:" label.',
        '.ptah-cfg-content .bg-sky-50 .bg-white' => 'Light mode already renders this literal white-on-near-white '.
            'inline-code chip correctly (identity) — no override needed there. The dark override exists because '.
            'the chip\'s ink is intentionally fixed dark (Onda 5\'s .bg-sky-50 .text-slate-500 exception, '.
            'inherited from the surrounding <ul>) for this box\'s permanently near-white background, but its OWN '.
            'background is the separate, unconditionally-dark-in-.ptah-dark `.bg-white` rule — dark ink on a now-'.
            'dark chip. --ptah-surface-on-tint holds the chip\'s background at its light value instead.',
    ];

    #[Test]
    public function every_dark_scoped_component_rule_has_a_light_counterpart_or_a_documented_exception(): void
    {
        $orphans = self::darkOrphans(self::css());

        $undocumented = array_values(array_diff(array_keys($orphans), array_keys(self::EXCEPTIONS)));

        $this->assertSame(
            [],
            $undocumented,
            "ptah-components.css: as regras escuras abaixo declaram var(--ptah-*) mas NAO tem uma regra clara\n".
            "correspondente (mesmo seletor, sem o prefixo \".ptah-dark \") que tambem declare var(--ptah-*).\n".
            "Sem ela, o tom claro / a cor de fonte escolhidos em /profile nao alcancam esse elemento em modo\n".
            "claro — exatamente o defeito que já ocorreu 4 vezes (fundo da pagina, as 4 superficies grandes,\n".
            "a regra raiz de cor de texto). Adicione a regra clara faltante, OU, se for um caso legitimo\n".
            "(B/C — ja coberto por outro mecanismo, ou so faz sentido no escuro), documente uma excecao em\n".
            "ThemeChromeOrphanTokenGuardTest::EXCEPTIONS com o motivo.\n\n".
            "Seletores orfaos sem excecao:\n".implode("\n", array_map(
                static fn (string $s): string => sprintf('  .ptah-dark %s  (propriedade(s): %s)', $s, implode(', ', $orphans[$s])),
                $undocumented
            ))
        );
    }

    /**
     * The other half of the guard: every documented exception must still be
     * a genuine orphan. If a light rule now covers it, the exception is
     * stale — the comment attached to it can no longer be trusted, and the
     * task's own instructions call this "lixo que esconde defeito futuro".
     */
    #[Test]
    public function every_documented_exception_is_still_a_genuine_orphan(): void
    {
        $orphans = self::darkOrphans(self::css());

        $stale = array_values(array_diff(array_keys(self::EXCEPTIONS), array_keys($orphans)));

        $this->assertSame(
            [],
            $stale,
            "ThemeChromeOrphanTokenGuardTest::EXCEPTIONS contem seletor(es) que JA tem uma regra clara com\n".
            "var(--ptah-*) — a excecao esta obsoleta e deve ser REMOVIDA (nao deixada para tras escondendo um\n".
            "defeito futuro caso a regra clara seja revertida):\n".implode("\n", $stale)
        );
    }

    /**
     * @return array<string, list<string>> selector (light-side remainder) => list of dark-scoped
     *                                     properties that reference var(--ptah-*), for every dark
     *                                     selector that has NO light counterpart declaring var(--ptah-*).
     */
    private static function darkOrphans(string $css): array
    {
        $working = self::stripComments($css);
        $working = self::stripAtRuleBlock($working, '@media print');

        if (! preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $working, $rules, PREG_SET_ORDER)) {
            throw new RuntimeException('ThemeChromeOrphanTokenGuardTest: nenhuma regra encontrada em ptah-components.css.');
        }

        // selector (as written, i.e. dark selectors keep their ".ptah-dark " prefix) => true if body has var(--ptah-*)
        $selectorsWithVar = [];
        // selector => list of property names that reference var(--ptah-*) (dark only, for the error message)
        $darkProps = [];

        foreach ($rules as $rule) {
            $body = trim($rule[2]);
            $hasVar = $body !== '' && str_contains($body, 'var(--ptah-');

            foreach (self::splitSelectors($rule[1]) as $selector) {
                if ($hasVar) {
                    $selectorsWithVar[$selector] = true;
                }

                if (str_starts_with($selector, '.ptah-dark ') && $hasVar) {
                    $remainder = substr($selector, strlen('.ptah-dark '));
                    $darkProps[$remainder][] = self::propertiesReferencingVar($body);
                }
            }
        }

        $orphans = [];

        foreach ($darkProps as $remainder => $propGroups) {
            if (! array_key_exists($remainder, $selectorsWithVar)) {
                $orphans[$remainder] = array_values(array_unique(array_merge(...$propGroups)));
            }
        }

        ksort($orphans);

        return $orphans;
    }

    /** @return list<string> */
    private static function propertiesReferencingVar(string $body): array
    {
        $props = [];

        foreach (explode(';', $body) as $declaration) {
            $declaration = trim($declaration);

            if ($declaration === '' || ! str_contains($declaration, 'var(--ptah-')) {
                continue;
            }

            $parts = explode(':', $declaration, 2);
            $props[] = trim($parts[0]);
        }

        return $props;
    }

    /** @return list<string> selectors with collapsed whitespace, trimmed */
    private static function splitSelectors(string $selectorList): array
    {
        $selectors = [];

        foreach (explode(',', $selectorList) as $raw) {
            $normalized = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

            if ($normalized !== '') {
                $selectors[] = $normalized;
            }
        }

        return $selectors;
    }

    private static function stripComments(string $css): string
    {
        return preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
    }

    /** Removes the single (brace-balanced) block introduced by the literal $head, if present. */
    private static function stripAtRuleBlock(string $css, string $head): string
    {
        $start = strpos($css, $head);

        if ($start === false) {
            return $css;
        }

        $bracePos = strpos($css, '{', $start);

        if ($bracePos === false) {
            throw new RuntimeException(sprintf('ThemeChromeOrphanTokenGuardTest: "%s" encontrado sem "{" correspondente.', $head));
        }

        $depth = 0;
        $len = strlen($css);

        for ($i = $bracePos; $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($css, 0, $start).substr($css, $i + 1);
                }
            }
        }

        throw new RuntimeException(sprintf('ThemeChromeOrphanTokenGuardTest: chave de fechamento de "%s" nao encontrada.', $head));
    }

    private static function css(): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');

        if ($content === false) {
            throw new RuntimeException('ThemeChromeOrphanTokenGuardTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}
