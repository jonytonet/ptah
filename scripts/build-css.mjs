// Builds resources/dist/ptah.css — the stylesheet a host WITHOUT Tailwind
// links when ptah.css.framework = bootstrap (docs/Css.md).
//
//   npm run build:css           rebuild
//   npm run build:css -- --hash print the inputs hash and exit
//
// Steps:
//   1. Tailwind v4 compiles scripts/css/ptah.css — the same
//      preflight + utilities + ptah-components.css a Tailwind host compiles
//      from its app.css — from the package's own views, stubs and renderers.
//   2. ISOLATION. Preflight (layer base) and utilities (layer utilities) are
//      wrapped in  @scope (.ptah-scope, [data-ptah-scope]) to (.ptah-slot,
//      [data-ptah-slot]): they reach ptah-owned markup and nothing else —
//      not the host's page, not host markup placed in a ptah slot.
//      Each selector also gets :where(:scope, :scope *) on its subject, so the
//      scoping root itself (a BaseCrud root that also carries `p-4`) is
//      matched; :where keeps the specificity Tailwind gave it.
//   3. BOOTSTRAP NEUTRALISED IN THE SCOPE. Bootstrap's reboot is unlayered
//      and beats any layered rule; its utilities (`px-3`, `p-4`, `border`,
//      `text-primary`…) are !important. For every Bootstrap rule that can
//      hit ptah markup, the build emits the same selector, inside the same
//      @scope, with each property set to `revert-layer` (keeping !important
//      where Bootstrap had it). Same specificity, closer scope proximity:
//      this rule wins over Bootstrap's on ptah elements, and revert-layer
//      hands the property back to the layered ptah styles — exactly the
//      value a Tailwind host computes. Outside the scope nothing changes.
//   4. ptah-components.css sits in layer `ptah` (above utilities, as being
//      unlayered puts it in a Tailwind host) — except the appearance presets,
//      which stay unlayered, as there — `.ptah-dark` also answers
//      `:root[data-bs-theme=dark]`, and z-index utilities from 40 up are
//      lifted above Bootstrap's 1030/1055 (scripts/css/bridge.css).
//   5. The bridge is appended; the header records a hash of every input
//      (scripts/css/sources.json) that PtahCssDistTest recomputes.

import { createRequire } from 'node:module';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(path.join(root, 'package.json'));

const SCOPE = '.ptah-scope, [data-ptah-scope]';
const LIMIT = '.ptah-slot, [data-ptah-slot]';
const SUBJECT_IN_SCOPE = ':where(:scope, :scope *)';
const DARK_ALIAS = ':is(.ptah-dark, :where(:root[data-bs-theme=dark]))';
const Z_LIFT = 1000;
const PRESET = /^\s*html(\.ptah-dark)?\[data-ptah-(light|dark|accent|text|density|fontsize)=/;

const OUT = path.join(root, 'resources/dist/ptah.css');
const sources = JSON.parse(fs.readFileSync(path.join(root, 'scripts/css/sources.json'), 'utf8'));

// ── inputs hash (mirrored by Ptah\Support\CssDist::inputsHash()) ──────────

function walk(dir, suffix, out) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            walk(full, suffix, out);
        } else if (entry.name.endsWith(suffix)) {
            out.push(full);
        }
    }
}

function inputFiles() {
    const files = [];
    for (const [dir, suffix] of sources.dirs) {
        walk(path.join(root, dir), suffix, files);
    }
    for (const file of sources.files) {
        files.push(path.join(root, file));
    }

    return [...new Set(files.map((f) => path.relative(root, f).split(path.sep).join('/')))].sort();
}

function inputsHash() {
    const hash = crypto.createHash('sha256');
    const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'));
    for (const name of sources.packages) {
        hash.update(`package:${name}@${pkg.devDependencies?.[name] ?? ''}\n`);
    }
    for (const rel of inputFiles()) {
        const content = fs.readFileSync(path.join(root, rel), 'utf8').replace(/\r\n/g, '\n');
        hash.update(`${rel}\n${content}\n`);
    }

    return hash.digest('hex');
}

if (process.argv.includes('--hash')) {
    console.log(inputsHash());
    process.exit(0);
}

const postcss = require('postcss');
const tailwind = require('@tailwindcss/postcss');
const selectorParser = require('postcss-selector-parser');

// ── selector helpers ───────────────────────────────────────────────────────

const LEGACY_PSEUDO_ELEMENTS = new Set([':before', ':after', ':first-line', ':first-letter']);

function isPseudoElement(node) {
    return node.type === 'pseudo' && (node.value.startsWith('::') || LEGACY_PSEUDO_ELEMENTS.has(node.value));
}

/** The nodes of the last compound selector (the subject). */
function subjectNodes(selector) {
    const nodes = selector.nodes;
    let start = 0;
    nodes.forEach((node, i) => {
        if (node.type === 'combinator') {
            start = i + 1;
        }
    });

    return nodes.slice(start);
}

/** Adds :where(:scope, :scope *) to the subject; `html` / `:host` become :scope. */
function scopeSelector(selectorText, { rootAliases = false } = {}) {
    return selectorParser((selectors) => {
        selectors.each((selector) => {
            const subject = subjectNodes(selector);
            const single = selector.nodes.length === 1 ? selector.nodes[0] : null;

            if (rootAliases && single && (
                (single.type === 'tag' && single.value === 'html') ||
                (single.type === 'pseudo' && single.value === ':host')
            )) {
                single.replaceWith(selectorParser.pseudo({ value: ':scope' }));

                return;
            }

            const where = selectorParser.pseudo({ value: ':where' });
            where.append(selectorParser.selector({ nodes: [selectorParser.pseudo({ value: ':scope' })] }));
            const descendant = selectorParser.selector({ nodes: [] });
            descendant.append(selectorParser.pseudo({ value: ':scope' }));
            descendant.append(selectorParser.combinator({ value: ' ' }));
            descendant.append(selectorParser.universal());
            where.append(descendant);

            // The leading whitespace of a selector in a list belongs to its
            // first node: moved first, or `:where(…) :after` becomes a
            // descendant selector.
            const lead = selector.first.spaces.before;
            selector.first.spaces.before = '';

            const pseudoElement = subject.find(isPseudoElement);
            if (pseudoElement) {
                selector.insertBefore(pseudoElement, where);
            } else {
                selector.append(where);
            }
            selector.first.spaces.before = lead;
        });
    }).processSync(selectorText);
}

/** `.ptah-dark` also answers Bootstrap's page-level dark theme. */
function aliasDark(selectorText) {
    if (!selectorText.includes('ptah-dark')) {
        return selectorText;
    }

    return selectorParser((selectors) => {
        selectors.walkClasses((node) => {
            if (node.value === 'ptah-dark') {
                node.replaceWith(selectorParser.string({ value: DARK_ALIAS }));
            }
        });
    }).processSync(selectorText);
}

const inKeyframes = (rule) => rule.parent?.type === 'atrule' && /keyframes$/.test(rule.parent.name);
const isCustom = (decl) => decl.prop.startsWith('--');

/**
 * `.ptah-dark` also answers :root[data-bs-theme=dark] — for TOKENS. A rule that
 * paints the dark element itself (`.ptah-dark { background-color }`, the
 * <html> of ptah's layouts) keeps `.ptah-dark` only: aliased, it would paint the
 * <html> of a host page that is merely in Bootstrap's dark theme.
 */
function aliasDarkTokens(container) {
    container.walkRules((rule) => {
        if (inKeyframes(rule) || !rule.selector.includes('ptah-dark')) {
            return;
        }
        const paintsDarkRoot = rule.selectors.some((s) => selectorParser().astSync(s).nodes.some(
            (sel) => subjectNodes(sel).some((n) => n.type === 'class' && n.value === 'ptah-dark'),
        ));
        const props = rule.nodes.filter((n) => n.type === 'decl');
        if (!paintsDarkRoot || props.every(isCustom)) {
            rule.selector = aliasDark(rule.selector);

            return;
        }
        if (props.some(isCustom)) {
            const tokens = rule.clone();
            tokens.each((n) => {
                if (n.type === 'decl' && !isCustom(n)) {
                    n.remove();
                }
            });
            tokens.selector = aliasDark(tokens.selector);
            rule.each((n) => {
                if (n.type === 'decl' && isCustom(n)) {
                    n.remove();
                }
            });
            rule.before(tokens);
        }
    });
}

/**
 * Nothing in ptah.css may style a HOST element. Rules of ptah-components.css
 * and the bridge whose selector names no ptah class or attribute (`*`, `html`,
 * `main`, `.scrollbar-none`, the reduced-motion block…) are confined: the
 * root ones (`html`, `:root`) to pages whose <body> is a ptah scope (ptah's
 * own layouts), the rest to the @scope of the utilities. Custom properties
 * and `[x-cloak]` (Alpine's own contract) stay global.
 */
const ROOT_SELECTOR = /^\s*(html|:root)\s*$/;
const BODY_IS_SCOPE = ':has(> body:is(.ptah-scope, [data-ptah-scope]))';

function isolate(container) {
    const rules = [];
    container.walkRules((rule) => {
        if (!inKeyframes(rule)) {
            rules.push(rule);
        }
    });
    for (const rule of rules) {
        if (rule.nodes.filter((n) => n.type === 'decl').every(isCustom)) {
            continue;
        }
        const generic = rule.selectors.filter((s) => !/ptah/.test(s) && s.trim() !== '[x-cloak]');
        if (generic.length === 0) {
            continue;
        }
        const keep = rule.selectors.filter((s) => !generic.includes(s));
        const roots = generic.filter((s) => ROOT_SELECTOR.test(s)).map((s) => `${s.trim()}${BODY_IS_SCOPE}`);
        const inner = generic.filter((s) => !ROOT_SELECTOR.test(s));

        if (inner.length > 0) {
            const scoped = rule.clone({ selector: scopeSelector(inner.join(', ')) });
            const scope = postcss.atRule({ name: 'scope', params: `(${SCOPE}) to (${LIMIT})`, nodes: [] });
            scope.append(scoped);
            rule.after(scope);
        }
        if (keep.length + roots.length > 0) {
            rule.selectors = [...keep, ...roots];
        } else {
            rule.remove();
        }
    }
}

/** Classes the subject compound REQUIRES (outside :not()/:is()/…). */
function requiredSubjectClasses(selector) {
    return subjectNodes(selector).filter((n) => n.type === 'class').map((n) => n.value);
}

// ── candidate classes of the ptah markup (a superset is fine) ──────────────

function ptahTokens() {
    const tokens = new Set();
    for (const rel of inputFiles()) {
        if (!/\.(php|stub)$/.test(rel)) {
            continue;
        }
        const content = fs.readFileSync(path.join(root, rel), 'utf8');
        // Twice: plain words, and words that keep a decimal point (`px-2.5`).
        for (const token of [...content.split(/[^A-Za-z0-9_-]+/), ...content.split(/[^A-Za-z0-9_.-]+/)]) {
            const trimmed = token.replace(/^\.+|\.+$/g, '');
            if (trimmed !== '') {
                tokens.add(trimmed);
            }
        }
    }

    return tokens;
}

// ── 1. Tailwind ────────────────────────────────────────────────────────────

const entry = path.join(root, 'scripts/css/ptah.css');
const compiled = await postcss([tailwind({ base: root, optimize: { minify: false } })])
    .process(fs.readFileSync(entry, 'utf8'), { from: entry });

const css = postcss.parse(compiled.css);

function scopeLayer(layer, { rootAliases = false, liftZ = false } = {}) {
    const hoisted = [];
    layer.walkAtRules('keyframes', (rule) => {
        hoisted.push(rule.remove());
    });
    layer.walkRules((rule) => {
        if (rule.parent?.type === 'atrule' && /keyframes$/.test(rule.parent.name)) {
            return;
        }
        rule.selector = scopeSelector(rule.selector, { rootAliases });
        if (liftZ) {
            rule.walkDecls('z-index', (decl) => {
                const n = Number(decl.value);
                if (Number.isInteger(n) && n >= 40 && n < 9999) {
                    decl.value = String(n + Z_LIFT);
                }
            });
        }
    });
    const scope = postcss.atRule({ name: 'scope', params: `(${SCOPE}) to (${LIMIT})` });
    scope.append(layer.nodes);
    layer.append(scope);

    return hoisted;
}

const hoisted = [];
css.each((node) => {
    if (node.type !== 'atrule' || node.name !== 'layer' || !node.nodes) {
        return;
    }
    if (node.params === 'base') {
        hoisted.push(...scopeLayer(node, { rootAliases: true }));
    } else if (node.params === 'utilities') {
        hoisted.push(...scopeLayer(node, { liftZ: true }));
    } else if (node.params === 'ptah') {
        // The appearance presets (html[data-ptah-accent=…] and the other five
        // axes) leave the layer: in a Tailwind host they beat the brand
        // colours partials.theme-colors prints by SPECIFICITY, both unlayered —
        // layered, the unlayered :root of that partial would win instead.
        const presets = [];
        node.each((child) => {
            if (child.type === 'rule' && child.selectors.every((s) => PRESET.test(s))) {
                presets.push(child);
            }
        });
        aliasDarkTokens(node);
        isolate(node);
        presets.reverse().forEach((rule) => node.after(rule.remove()));
    }
});
hoisted.forEach((rule) => css.append(rule));

// ── 3. Bootstrap, reverted inside the scope ────────────────────────────────

const bootstrapPath = require.resolve('bootstrap/dist/css/bootstrap.css');
const bootstrap = postcss.parse(fs.readFileSync(bootstrapPath, 'utf8'));
const tokens = ptahTokens();
const revert = postcss.atRule({ name: 'scope', params: `(${SCOPE}) to (${LIMIT})` });
let revertedRules = 0;

function canHitPtahMarkup(selector) {
    return requiredSubjectClasses(selector).every((cls) => tokens.has(cls));
}

function revertContainer(source, target) {
    source.each((node) => {
        if (node.type === 'atrule' && ['media', 'supports', 'container'].includes(node.name)) {
            const copy = postcss.atRule({ name: node.name, params: node.params, nodes: [] });
            revertContainer(node, copy);
            if (copy.nodes.length > 0) {
                target.append(copy);
            }

            return;
        }
        if (node.type !== 'rule') {
            return;
        }

        const decls = [];
        node.each((decl) => {
            if (decl.type === 'decl' && !decl.prop.startsWith('--')) {
                decls.push(postcss.decl({ prop: decl.prop, value: 'revert-layer', important: decl.important }));
            }
        });
        if (decls.length === 0) {
            return;
        }

        const kept = [];
        selectorParser((selectors) => {
            selectors.each((selector) => {
                if (canHitPtahMarkup(selector)) {
                    kept.push(selector.toString().trim());
                }
            });
        }).processSync(node.selector);
        if (kept.length === 0) {
            return;
        }

        const rule = postcss.rule({ selector: scopeSelector(kept.join(', ')) });
        decls.forEach((decl) => rule.append(decl));
        target.append(rule);
        revertedRules++;
    });
}

revertContainer(bootstrap, revert);

// A project's own Sass build extends Bootstrap's utility families ($spacers 6
// → `.p-6 { padding: 3.5rem !important }`, $utilities maps…). Such a class is
// not in stock Bootstrap but can carry the name of a ptah utility. So every
// ptah class named like a member of a stock !important family is reverted
// too, with that family's properties. Only spacing, gap, radius, shadow,
// opacity and z-index: a colour or display family would also override the
// layout's own repaint rules and Alpine's inline `display: none`, and a
// size family the widths Alpine and the column resize set inline (the
// sidebar is `w-64` + :style width).
const FAMILY_PROPS = /^(margin|padding|gap|row-gap|column-gap|z-index|opacity|box-shadow|border(-(top|bottom)-(left|right))?-radius|border-(top|bottom|left|right|start|end)-(start|end|left|right)?-?radius)(-.*)?$/;
const familyKey = (cls) => cls.replace(/-[^-]+$/, '');
const families = new Map();
const stockClasses = new Set();
bootstrap.each((node) => {
    if (node.type !== 'rule') {
        return;
    }
    for (const sel of node.selectors) {
        const m = /^\.([a-z][\w-]*)$/.exec(sel.trim());
        if (!m) {
            continue;
        }
        stockClasses.add(m[1]);
        const props = [];
        let important = true;
        node.walkDecls((d) => {
            if (!isCustom(d)) {
                props.push(d.prop);
                important &&= d.important;
            }
        });
        if (props.length === 0 || !important || !m[1].includes('-')) {
            continue;
        }
        const key = familyKey(m[1]);
        const set = families.get(key) ?? new Set();
        props.forEach((p) => set.add(p));
        families.set(key, set);
    }
});
for (const [key, props] of families) {
    if (![...props].every((p) => FAMILY_PROPS.test(p))) {
        families.delete(key);
    }
}
let extendedRules = 0;
for (const token of [...tokens].sort()) {
    if (token.includes(':') || stockClasses.has(token) || !token.includes('-') || !families.has(familyKey(token))) {
        continue;
    }
    const selector = selectorParser.className({ value: token }).toString();
    const rule = postcss.rule({ selector: scopeSelector(selector) });
    families.get(familyKey(token)).forEach((prop) => rule.append(postcss.decl({ prop, value: 'revert-layer', important: true })));
    revert.append(rule);
    extendedRules++;
}

css.append(revert);

// ── 5. bridge + header ─────────────────────────────────────────────────────

const bridge = postcss.parse(fs.readFileSync(path.join(root, 'scripts/css/bridge.css'), 'utf8'));
aliasDarkTokens(bridge);
isolate(bridge);
css.append(bridge);

// ── guard: nothing outside a scope styles an element that is not ptah's ────

const leaks = [];
css.walkRules((rule) => {
    if (inKeyframes(rule) || rule.nodes.filter((n) => n.type === 'decl').every(isCustom)) {
        return;
    }
    for (let p = rule.parent; p && p.type !== 'root'; p = p.parent) {
        if (p.type === 'atrule' && (p.name === 'scope' || (p.name === 'layer' && ['theme', 'properties'].includes(p.params)))) {
            return;
        }
    }
    for (const sel of rule.selectors) {
        if (!/ptah/.test(sel) && sel.trim() !== '[x-cloak]') {
            leaks.push(sel);
        }
        if (/data-bs-theme=dark/.test(sel) && !/:where\(:root\[data-bs-theme=dark\]\)|:root\[data-bs-theme=dark\]/.test(sel)) {
            leaks.push(sel);
        }
    }
});
if (leaks.length > 0) {
    throw new Error(`ptah.css would style host elements outside a ptah scope:\n  ${leaks.join('\n  ')}`);
}

const bootstrapVersion = JSON.parse(fs.readFileSync(path.join(path.dirname(bootstrapPath), '../../package.json'), 'utf8')).version;
const tailwindVersion = JSON.parse(fs.readFileSync(require.resolve('tailwindcss/package.json'), 'utf8')).version;
const header = [
    '/*! ptah.css — ptah for hosts without Tailwind (ptah.css.framework = bootstrap).',
    ` * GENERATED by scripts/build-css.mjs (npm run build:css) — do not edit.`,
    ` * tailwindcss ${tailwindVersion}, bootstrap ${bootstrapVersion} reverted in ${revertedRules} rules, + ${extendedRules} same-named ptah utilities.`,
    ` * inputs-hash: ${inputsHash()}`,
    ' */',
].join('\n');

const body = css.toString().replace(/\r\n/g, '\n').replace(/^\/\*! tailwindcss[^*]*\*\/\n?/, '');
fs.mkdirSync(path.dirname(OUT), { recursive: true });
fs.writeFileSync(OUT, `${header}\n${body.trim()}\n`);
console.log(`resources/dist/ptah.css: ${(fs.statSync(OUT).size / 1024).toFixed(1)} KiB, ${revertedRules} Bootstrap rules reverted, ${extendedRules} extended-family utilities`);
