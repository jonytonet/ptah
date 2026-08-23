<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Auth\User as AuthUser;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Tests\Browser\Fixtures\DuskCrudStub;
use RuntimeException;

/**
 * Full-sweep contrast auditor for the "Configurar CRUD" editor modal — the
 * defence that two prior waves did not have.
 *
 * WHY A NEW APPROACH: both previous passes measured HAND-PICKED pairs found
 * by grepping the view for known utility-class families (bg-white, text-
 * slate-*, border-slate-*...) and computing their `.ptah-dark` contrast in a
 * plain PHPUnit test (see CrudConfigDarkContrastTest, Unit suite). That
 * catches exactly the pairs someone thought to grep for — and missed whole
 * categories: the emerald-tinted SearchDropdown highlight box (introduced in
 * v1.21.0, never added to the sky-50/indigo-50 tint-box exception list), the
 * sidebar's own "SearchDropdown" nav-tab label, the footer's "Pré-visualizar
 * formulário" button (raw `text-primary` on a dark card — no `-lite` swap),
 * and the entire inert form-preview overlay (a sibling of `.ptah-cfg-content`,
 * so NONE of that scope's tokenisation rules ever reached it).
 *
 * This test instead drives a REAL Chrome, opens the REAL modal, walks every
 * sidebar section and every column-form sub-tab (switching Alpine's `tab`/
 * `editTab` state directly — see setTab()/setEditTab()), and for every
 * VISIBLE element with its own text node computes the actual WCAG contrast
 * ratio against the actual composited background — not a value read from the
 * CSS source, the value the browser's own layout/paint engine produces. Same
 * for <input>/<select>/<textarea> borders (3:1 floor) and `::placeholder`
 * colours. No selector is chosen ahead of time: the sweep is exhaustive over
 * `.ptah-cfg *`, so a future component added to any tab is covered
 * automatically, the same way ThemeChromeOrphanTokenGuardTest enumerates
 * every dark CSS rule instead of re-checking a fixed list.
 *
 * The full ordered-worst-first report of every pair measured (not just the
 * failures) is written to tests/Browser/source/contrast-report.txt on every
 * run — useful for the next wave even when this test is green.
 */
class CrudConfigDarkContrastBrowserTest extends DuskTestCase
{
    private const ROUTE = 'dusk-test/contrast-audit';

    private const MASTER_EMAIL = 'dusk-contrast-master@example.test';

    /** WCAG AA floor for normal text (SC 1.4.3) — applied to text AND placeholders. */
    private const TEXT_MIN_RATIO = 4.5;

    /** WCAG AA floor for UI-component boundaries (SC 1.4.11) — input/select/textarea borders. */
    private const BORDER_MIN_RATIO = 3.0;

    /** Column indices in richConfig()['cols'] — kept in sync with that method on purpose (see its docblock). */
    private const COL_NOTES = 6;

    private const COL_SUPPLIER_SD = 7;

    private const COL_CARRIER_SD = 8;

    private const REPORT_PATH = __DIR__.'/source/contrast-report.txt';

    /**
     * Every element with its own text node, plus <input>/<select>/<textarea>
     * borders and placeholders, scoped to `.ptah-cfg` (the config modal —
     * scanning the whole page would also pull in the navbar/sidebar, out of
     * scope for this task and owned by a different in-flight change).
     *
     * Kept as one big self-contained IIFE (no helper globals leaked onto
     * `window`) so it can be called once per tab/sub-tab state with zero
     * setup, via Browser::script().
     */
    private const AUDIT_JS = <<<'JS'
        return (function () {
            function parseColor(str) {
                if (!str) return null;
                var m = str.match(/rgba?\(([^)]+)\)/);
                if (!m) return null;
                var parts = m[1].split(',').map(function (s) { return parseFloat(s.trim()); });
                return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
            }
            function relLum(c) {
                function lin(v) { v = v / 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
                return 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
            }
            function contrastRatio(c1, c2) {
                var L1 = relLum(c1) + 0.05, L2 = relLum(c2) + 0.05;
                return L1 > L2 ? L1 / L2 : L2 / L1;
            }
            function over(fg, bg) {
                return {
                    r: fg.r * fg.a + bg.r * (1 - fg.a),
                    g: fg.g * fg.a + bg.g * (1 - fg.a),
                    b: fg.b * fg.a + bg.b * (1 - fg.a),
                };
            }
            function effectiveBg(el) {
                var layers = [];
                var node = el;
                while (node) {
                    var cs = getComputedStyle(node);
                    var c = parseColor(cs.backgroundColor);
                    if (c && c.a > 0) {
                        layers.push(c);
                        if (c.a >= 0.999) break;
                    }
                    node = node.parentElement;
                }
                layers.reverse();
                var result = { r: 255, g: 255, b: 255 };
                layers.forEach(function (layer) { result = over(layer, result); });
                return result;
            }
            function isVisible(el) {
                if (!(el.offsetWidth || el.offsetHeight || el.getClientRects().length)) return false;
                var cs = getComputedStyle(el);
                return cs.visibility !== 'hidden' && cs.display !== 'none';
            }
            function hasOwnText(el) {
                for (var i = 0; i < el.childNodes.length; i++) {
                    var n = el.childNodes[i];
                    if (n.nodeType === 3 && n.textContent.trim().length > 0) return true;
                }
                return false;
            }
            function rgbRound(c) {
                return 'rgb(' + Math.round(c.r) + ',' + Math.round(c.g) + ',' + Math.round(c.b) + ')';
            }
            function describe(el) {
                var cls = (el.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).join('.');
                var id = el.id ? '#' + el.id : '';
                return el.tagName.toLowerCase() + id + (cls ? '.' + cls : '');
            }

            var root = document.querySelector('.ptah-cfg');
            if (!root) return [];

            var out = [];
            var nodes = root.querySelectorAll('*');

            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                if (!isVisible(el)) continue;

                // WCAG SC 1.4.3/1.4.11 both exempt text/borders that belong to an
                // INACTIVE user interface component. The inert form-preview overlay's
                // two mockup footer buttons ("Cancel"/"Create" — see
                // _config-form-preview.blade.php's own "Visual only" docblock) are
                // exactly that: `cursor-not-allowed select-none` together mark a
                // purely illustrative, non-interactive label — never a real disabled
                // FORM CONTROL (those use `cursor-not-allowed` too, but not
                // `select-none`, and are exempted separately below).
                var isInertMockup = el.classList.contains('cursor-not-allowed') && el.classList.contains('select-none');

                if (!isInertMockup && hasOwnText(el)) {
                    var cs = getComputedStyle(el);
                    var fg = parseColor(cs.color);
                    if (fg) {
                        var bg = effectiveBg(el);
                        var fgc = over(fg, bg);
                        out.push({
                            kind: 'text',
                            selector: describe(el),
                            text: el.textContent.trim().slice(0, 60),
                            color: cs.color,
                            background: rgbRound(bg),
                            ratio: Math.round(contrastRatio(fgc, bg) * 100) / 100,
                        });
                    }
                }

                var tagName = el.tagName.toLowerCase();
                // Same WCAG exemption as isInertMockup above, for the actual DISABLED
                // form controls in that same preview overlay — their border (1.4.11)
                // and placeholder (1.4.3, the "text" this inactive control displays)
                // are both exempt, unlike a real, editable field's.
                if (!el.disabled && (tagName === 'input' || tagName === 'select' || tagName === 'textarea')) {
                    var csx = getComputedStyle(el);
                    var borderColor = parseColor(csx.borderTopColor);
                    var borderWidth = parseFloat(csx.borderTopWidth) || 0;
                    if (borderColor && borderWidth > 0) {
                        var bgOutside = effectiveBg(el.parentElement || el);
                        out.push({
                            kind: 'border',
                            selector: describe(el),
                            text: '',
                            color: csx.borderTopColor,
                            background: rgbRound(bgOutside),
                            ratio: Math.round(contrastRatio(borderColor, bgOutside) * 100) / 100,
                        });
                    }
                    if (el.placeholder) {
                        var ph = getComputedStyle(el, '::placeholder');
                        var phColor = parseColor(ph.color);
                        if (phColor) {
                            var bgOwn = effectiveBg(el);
                            var phc = over(phColor, bgOwn);
                            out.push({
                                kind: 'placeholder',
                                selector: describe(el),
                                text: el.placeholder,
                                color: ph.color,
                                background: rgbRound(bgOwn),
                                ratio: Math.round(contrastRatio(phc, bgOwn) * 100) / 100,
                            });
                        }
                    }
                }
            }

            return out;
        })();
        JS;

    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->get('/'.self::ROUTE, function () {
            return view('dusk-crud', ['model' => DuskCrudStub::class]);
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->booted(function (Application $app) {
            $this->ensureContrastFixtures();
        });
    }

    #[Test]
    public function dark_mode_meets_the_contrast_floor_in_every_tab_and_subtab(): void
    {
        $this->browse(function (Browser $browser) {
            $records = $this->auditModal($browser, dark: true, themeLabel: 'dark');

            $this->writeReportAndAssert($records, 'dark');
        });
    }

    #[Test]
    public function light_mode_meets_the_contrast_floor_in_every_tab_and_subtab(): void
    {
        $this->browse(function (Browser $browser) {
            $records = $this->auditModal($browser, dark: false, themeLabel: 'light');

            $this->writeReportAndAssert($records, 'light');
        });
    }

    /**
     * Opens the config modal and sweeps every sidebar section, every
     * column-form sub-tab (for a plain column, a searchdropdown in "model"
     * mode and one in "service" mode) and the inert form-preview overlay.
     *
     * @return list<array<string, mixed>>
     */
    private function auditModal(Browser $browser, bool $dark, string $themeLabel): array
    {
        $browser->loginAs(self::MASTER_EMAIL)
            ->visit('/'.self::ROUTE)
            ->waitFor('.ptah-c-search');

        if ($dark) {
            $browser->script("document.documentElement.classList.add('ptah-dark');");
        }
        $browser->pause(50);

        $wireId = $this->resolveConfigWireId($browser);

        $browser->click('button[wire\\:click="openModal"]')->waitFor('.ptah-cfg-content');

        $records = [];

        // The stored CrudConfig is deliberately free of real JOINs/relations
        // (see richConfig()'s own docblock: BaseCrud's listing query applies
        // both UNCONDITIONALLY, so a fake "supplier"/"suppliers" reference
        // there 500s the very first page load). The JOINs tab's "registered
        // joins" list and the Relação tab's nested-relation preview are
        // instead populated by writing straight into this EDITOR component's
        // own reactive state below — never persisted (save() is never
        // called), so the real listing query never sees them.
        $this->callWire($browser, $wireId, "\$set('joins', ".json_encode([[
            'type' => 'left', 'table' => 'suppliers', 'first' => 'bulk_action_stubs.id', 'second' => 'suppliers.stub_id',
            // distinct: true — also exercises the "DISTINCT" badge (bg-primary-light text-primary),
            // which the CrudConfigDarkContrastBrowserTest audit caught as `bg-primary text-primary`
            // (identical fg/bg, 1:1 in EITHER theme) before that view fix.
            'distinct' => true, 'select' => [['column' => 'suppliers.name', 'alias' => 'supplier_name']],
        ]]).')');

        // ── Sidebar nav tabs (Colunas, Ações, Filtros Custom, Estilos, JOINs,
        //    Geral, Permissões, Lifecycle Hooks) ──────────────────────────
        foreach (['cols', 'actions', 'filters', 'styles', 'joins', 'general', 'permissions', 'hooks'] as $tabId) {
            $this->setTab($browser, $tabId);
            $this->collect($records, $browser, "{$themeLabel} / nav:{$tabId}");
        }

        // ── Column sub-form: a plain column (badge renderer, mask,
        //    validation with active rules, relation empty state, totalizer
        //    enabled) ──────────────────────────────────────────────────────
        $this->setTab($browser, 'cols');
        $this->callWire($browser, $wireId, 'editField('.self::COL_NOTES.')');
        foreach (['basic', 'renderer', 'mask', 'validation', 'relation', 'total'] as $subTab) {
            $this->setEditTab($browser, $subTab);
            $this->collect($records, $browser, "{$themeLabel} / cols:notes / {$subTab}");
        }

        // ── Column sub-form: SearchDropdown in "model" mode — the exact tab
        //    the user's screenshot showed with the illegible emerald box —
        //    plus the Relação tab, populated with a nested relation (scratch
        //    state only — see the joins comment above for why). ───────────
        $this->callWire($browser, $wireId, 'editField('.self::COL_SUPPLIER_SD.')');
        $this->callWire($browser, $wireId, "\$set('formDataField.colsRelacao', 'supplier')");
        $this->callWire($browser, $wireId, "\$set('formDataField.colsRelacaoNested', 'supplier.contact.email')");
        foreach (['basic', 'relation', 'sd'] as $subTab) {
            $this->setEditTab($browser, $subTab);
            $this->collect($records, $browser, "{$themeLabel} / cols:supplier_id(model) / {$subTab}");
        }

        // ── Column sub-form: SearchDropdown in "service" mode — the ELSE
        //    branch of the search-mode radio, never reached by the pass
        //    above. ────────────────────────────────────────────────────────
        $this->callWire($browser, $wireId, 'editField('.self::COL_CARRIER_SD.')');
        $this->setEditTab($browser, 'sd');
        $this->collect($records, $browser, "{$themeLabel} / cols:carrier_id(service) / sd");

        // ── Inert form-preview overlay (footer button symptom #4). ─────────
        $this->callWire($browser, $wireId, 'previewForm()');
        $browser->waitFor('.ptah-cfg .z-\\[70\\]');
        $this->collect($records, $browser, "{$themeLabel} / preview-overlay");
        $this->callWire($browser, $wireId, 'closePreview()');

        return $records;
    }

    /** @param  list<array<string, mixed>>  $records */
    private function collect(array &$records, Browser $browser, string $tag): void
    {
        $batch = $browser->script(self::AUDIT_JS)[0] ?? [];

        foreach ($batch as $record) {
            $record['tag'] = $tag;
            $records[] = $record;
        }
    }

    private function setTab(Browser $browser, string $tabId): void
    {
        $browser->script(sprintf(
            "window.Alpine.\$data(document.querySelector('.ptah-cfg')).tab = %s;",
            json_encode($tabId)
        ));
        $browser->pause(30);
    }

    private function setEditTab(Browser $browser, string $subTabId): void
    {
        $browser->script(sprintf(
            "window.Alpine.\$data(document.querySelector('[x-data*=\"editTab\"]')).editTab = %s;",
            json_encode($subTabId)
        ));
        $browser->pause(30);
    }

    /**
     * Invokes a method on the ptah-crud-config Livewire component directly,
     * bypassing click() entirely — required for every action that lives
     * inside the @teleport('body')'d modal panel: a synthetic WebDriver click
     * on a wire:click-bound element inside a teleported subtree delivers
     * every DOM event but the Livewire binding never fires under Chrome
     * 151.0.7922.173 (documented in docs/Testing.md's "Known quirk" section
     * and in CrudModalBrowserTest).
     *
     * Uses executeAsyncScript (not a fire-and-forget executeScript + a fixed
     * pause): a bare `component.method(...)` call kicks off Livewire's
     * request but returns before the round-trip settles, and measured
     * empirically here, a 350ms pause was NOT reliably enough for the
     * server's response to land and morph the DOM — editingFieldIndex was
     * still -1 immediately after. Awaiting the Promise the call itself
     * returns is the only deterministic signal that the state (and the DOM
     * this test scans right after) has actually updated.
     *
     * Livewire.find() already returns the component's $wire proxy (see
     * livewire.js's own `find(id) { return component && component.$wire; }`)
     * — no extra `.$wire.` hop (CrudModalBrowserTest's comment suggesting
     * otherwise documents a call path that test never actually reaches: it
     * is skipped before getting there).
     */
    private function callWire(Browser $browser, string $wireId, string $expression): void
    {
        $browser->driver->executeAsyncScript(sprintf(
            'var callback = arguments[arguments.length - 1];'.
            'Promise.resolve(window.Livewire.find(%s).%s)'.
            '  .then(function () { callback(true); })'.
            '  .catch(function () { callback(false); });',
            json_encode($wireId),
            $expression
        ));
    }

    /**
     * The config trigger button (`wire:click="openModal"`) lives OUTSIDE the
     *
     * @teleport('body') block, so its nearest `[wire:id]` ancestor is the
     * ptah-crud-config component's own root — the same anchor-by-DOM idiom
     * CrudModalBrowserTest uses, needed because the page also renders the
     * BaseCrud component itself (and any host-added Livewire widgets), so
     * `Livewire.all()[0]` is not guaranteed to resolve to this component.
     */
    private function resolveConfigWireId(Browser $browser): string
    {
        $id = $browser->script(
            'return document.querySelector(\'button[wire\\\\:click="openModal"]\').closest("[wire\\\\:id]").getAttribute("wire:id");'
        )[0] ?? null;

        if (! is_string($id) || $id === '') {
            throw new RuntimeException(
                'CrudConfigDarkContrastBrowserTest: could not resolve the ptah-crud-config wire:id.'
            );
        }

        return $id;
    }

    /**
     * Writes the full ordered-worst-first report (every pair measured, not
     * just failures) to tests/Browser/source/contrast-report.txt, then
     * fails with a short, actionable table if anything is under the floor.
     *
     * @param  list<array<string, mixed>>  $records
     */
    private function writeReportAndAssert(array $records, string $themeLabel): void
    {
        // De-duplicate: the footer and the sidebar rail are visible across
        // every nav tab, so the same element/colour/background triple is
        // measured repeatedly — keep the first tag it was seen under.
        $seen = [];
        $unique = [];
        foreach ($records as $record) {
            $key = $record['kind'].'|'.$record['selector'].'|'.$record['color'].'|'.$record['background'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $record;
        }

        usort($unique, fn (array $a, array $b) => $a['ratio'] <=> $b['ratio']);

        $lines = [];
        $lines[] = "Ptah CRUD Config editor — contrast audit ({$themeLabel} mode)";
        $lines[] = 'Floors: text/placeholder >= '.self::TEXT_MIN_RATIO.':1, input/select/textarea border >= '.self::BORDER_MIN_RATIO.':1';
        $lines[] = 'Ordered worst-first. '.count($unique).' unique pairs measured.';
        $lines[] = str_repeat('=', 100);

        $failures = [];

        foreach ($unique as $record) {
            $floor = $record['kind'] === 'border' ? self::BORDER_MIN_RATIO : self::TEXT_MIN_RATIO;
            $pass = $record['ratio'] >= $floor;

            $lines[] = sprintf(
                '[%s] %-6s ratio=%5.2f %s | fg=%s bg=%s | %s | text="%s"',
                $pass ? 'OK' : 'FAIL',
                $record['kind'],
                $record['ratio'],
                $pass ? '' : '(floor '.$floor.')',
                $record['color'],
                $record['background'],
                $record['tag'],
                $record['text']
            );
            $lines[] = '        '.$record['selector'];

            if (! $pass) {
                $failures[] = $record;
            }
        }

        @mkdir(dirname(self::REPORT_PATH), recursive: true);
        file_put_contents(self::REPORT_PATH.'.'.$themeLabel.'.txt', implode("\n", $lines)."\n");

        if ($failures === []) {
            $this->assertTrue(true);

            return;
        }

        $summary = array_map(
            fn (array $r) => sprintf(
                '  ratio=%.2f (floor %.1f) [%s] %s | fg=%s bg=%s | %s | text="%s"',
                $r['ratio'],
                $r['kind'] === 'border' ? self::BORDER_MIN_RATIO : self::TEXT_MIN_RATIO,
                $r['kind'],
                $r['selector'],
                $r['color'],
                $r['background'],
                $r['tag'],
                $r['text']
            ),
            array_slice($failures, 0, 40)
        );

        $this->fail(sprintf(
            "%d par(es) abaixo do piso de contraste no modal 'Configurar CRUD' (%s mode) — ".
            "veja o relatorio completo em %s\n\n%s%s",
            count($failures),
            $themeLabel,
            self::REPORT_PATH.'.'.$themeLabel.'.txt',
            implode("\n", $summary),
            count($failures) > 40 ? "\n  ... e mais ".(count($failures) - 40).' par(es), ver o arquivo do relatorio.' : ''
        ));
    }

    /**
     * Re-seeded on every request the Dusk server handles (each one reboots
     * the app from a fresh, empty `:memory:` sqlite — see DuskTestCase's own
     * class docblock), guarded the same way ColumnPermissionBrowserTest's
     * fixtures are.
     */
    private function ensureContrastFixtures(): void
    {
        if (CrudConfig::query()->where('model', DuskCrudStub::class)->where('route', self::ROUTE)->exists()) {
            return;
        }

        CrudConfig::create([
            'model' => DuskCrudStub::class,
            'route' => self::ROUTE,
            'config' => $this->richConfig(),
        ]);

        $role = Role::create(['name' => 'dusk-contrast-master', 'is_master' => true, 'is_active' => true]);

        $user = new AuthUser;
        $user->name = 'Dusk Contrast Master';
        $user->email = self::MASTER_EMAIL;
        $user->password = bcrypt('secret');
        $user->save();

        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
    }

    /**
     * One CrudConfig covering every tab/sub-tab this test visits, with a mix
     * of FILLED fields (real-value text contrast) and DELIBERATELY EMPTY
     * ones (placeholder contrast) in every section. Column indices are
     * mirrored in the COL_* constants above — keep both in sync.
     *
     * Two fields are intentionally left blank on `notes`
     * (colsCellStyle/colsCellClass) and several on the two SearchDropdown
     * columns: real per-CRUD content is exactly this uneven mix in
     * practice, and an all-filled or all-empty fixture would each hide a
     * different failure mode (a token that only breaks on real text, or one
     * that only breaks on the placeholder).
     *
     * @return array<string, mixed>
     */
    private function richConfig(): array
    {
        return [
            'crud' => DuskCrudStub::class,
            'displayName' => 'Contrast Audit Stub',
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false, 'colsIsFilterable' => false],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true, 'colsIsFilterable' => true],
                [
                    'colsNomeFisico' => 'price', 'colsNomeLogico' => 'Preço', 'colsTipo' => 'number', 'colsGravar' => true,
                    'colsRenderer' => 'money', 'colsRendererCurrency' => 'BRL', 'colsRendererDecimals' => 2,
                    'totalizadorEnabled' => true, 'totalizadorType' => 'sum', 'totalizadorFormat' => 'currency', 'totalizadorLabel' => 'Total',
                ],
                ['colsNomeFisico' => 'order_date', 'colsNomeLogico' => 'Data do Pedido', 'colsTipo' => 'date', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'select', 'colsGravar' => true, 'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive']],
                ['colsNomeFisico' => 'active', 'colsNomeLogico' => 'Ativo', 'colsTipo' => 'boolean', 'colsGravar' => true, 'colsRenderer' => 'boolean', 'colsRendererBoolTrue' => 'Sim', 'colsRendererBoolFalse' => 'Não'],
                [
                    // Index 6 — self::COL_NOTES.
                    'colsNomeFisico' => 'notes', 'colsNomeLogico' => 'Notas', 'colsTipo' => 'textarea', 'colsGravar' => true,
                    'colsFormBlock' => 'Detalhes', 'colsValidations' => ['alpha', 'max:255'],
                    'colsRenderer' => 'badge',
                    'colsRendererBadges' => [
                        ['value' => 'yes', 'label' => 'Sim', 'color' => 'green', 'icon' => 'bx bx-check'],
                        ['value' => 'no', 'label' => 'Não', 'color' => 'red', 'icon' => 'bx bx-x'],
                    ],
                ],
                [
                    // Index 7 — self::COL_SUPPLIER_SD. colsSDModel/colsSDPlaceholder/
                    // colsSDFilters/colsSDDependsOn/colsSDFilterColumn left blank on
                    // purpose — audits their placeholders. colsRelacao/colsRelacaoNested
                    // are DELIBERATELY NOT persisted here — BaseCrud's listing query
                    // eager-loads any configured relation unconditionally
                    // (getVisibleRelations()), which would 500 on a fake "supplier"
                    // relation this stub model doesn't define. auditModal() injects
                    // both into the editor's in-memory formDataField instead (never
                    // saved), which is all the Relação tab needs to render.
                    'colsNomeFisico' => 'supplier_id', 'colsNomeLogico' => 'Fornecedor', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsPermission' => 'items.secret_amount',
                    'colsSDTipo' => 'model', 'colsSDValor' => 'id', 'colsSDLabel' => 'name', 'colsSDLabelTwo' => 'cnpj',
                    'colsSDOrder' => 'id asc', 'colsSDStartList' => 'bottom', 'colsSDArraySearch' => 'cnpj,email',
                    'colsSDInitWithData' => true, 'colsSDLimit' => 10,
                    'colsSDMaskOne' => 'cnpj', 'colsSDMaskTwo' => 'defaultMask', 'colsSDMaskThree' => 'defaultMask',
                ],
                [
                    // Index 8 — self::COL_CARRIER_SD. "service" search mode — the
                    // ELSE branch of the radio, with its own two fields left blank.
                    'colsNomeFisico' => 'carrier_id', 'colsNomeLogico' => 'Transportadora', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsSDTipo' => 'service', 'colsSDValor' => 'id', 'colsSDLabel' => 'name',
                ],
                [
                    'colsNomeFisico' => 'id', 'colsNomeLogico' => 'Ver detalhe', 'colsTipo' => 'action', 'colsGravar' => false, 'colsRequired' => false, 'colsIsFilterable' => false,
                    'actionType' => 'link', 'actionValue' => '/x/%id%', 'actionIcon' => 'bx bx-link', 'actionColor' => 'primary',
                    // addAction() always defaults this to '' (see CrudConfig::addAction()) —
                    // the view reads it unguarded ($col['actionPermission']), so a
                    // hand-written fixture that omits it 500s the Actions tab.
                    'actionPermission' => '',
                ],
            ],
            'customFilters' => [
                [
                    'field' => 'total_qty', 'label' => 'Quantidade total', 'whereHas' => 'items',
                    'field_relation' => 'quantity', 'aggregate' => 'sum', 'defaultOperator' => '>=', 'colsFilterType' => 'number',
                ],
            ],
            // Note: contitionStyles' own key spelling (missing the first "d") is
            // a pre-existing typo baked into the config schema — see CrudConfig::
            // buildConfigArray()/loadFromDb(), not something this fixture invents.
            'contitionStyles' => [
                ['field' => 'status', 'condition' => '==', 'value' => 'active', 'style' => ''],
            ],
            // Empty on purpose — see the supplier_id column's own comment above:
            // BaseCrud's listing query applies every configured JOIN
            // unconditionally, so a fake table here 500s the page. auditModal()
            // injects one straight into the editor's $this->joins property
            // instead (never saved).
            'joins' => [],
            'permissions' => [],
        ];
    }
}
