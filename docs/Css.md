# CSS — Tailwind or Bootstrap

ptah's screens are written with Tailwind utility classes. A project chooses at
install time which CSS framework **it** uses; ptah adapts:

| | Tailwind (default) | Bootstrap 5.3 |
|---|---|---|
| Install | `php artisan ptah:install` | `php artisan ptah:install --css=bootstrap` |
| Setting | — (`PTAH_CSS=tailwind`) | `PTAH_CSS=bootstrap` (`ptah.css.framework`) |
| Who compiles ptah's classes | your Tailwind (`app.css` gets `@source` + `ptah-components.css`) | nobody: ptah ships `public/vendor/ptah/ptah.css`, already compiled |
| Tailwind in your project | yes | **no** — `app.css`, `vite.config.js` and `package.json` are not touched |
| Look of ptah's screens | Tailwind | the same (measured: identical pixels in ptah's layout) |

Everything here is about the **Livewire** stack. The React and Vue screens
(`--stack=react|vue`) keep their own stylesheet (`resources/js/ptah-ui.css`).

---

## 1. Installing with Bootstrap

```bash
composer require jonytonet/ptah
php artisan ptah:install --css=bootstrap
```

The installer:

1. writes `PTAH_CSS=bootstrap` to `.env`;
2. publishes the stylesheet: `vendor:publish --tag=ptah-assets --force` →
   `public/vendor/ptah/ptah.css`;
3. clears the compiled views (`view:clear`, see §4);
4. does **not** touch `resources/css/app.css`, `vite.config.js` or `package.json`.

An existing project switches the same way: set `PTAH_CSS=bootstrap`, run
`php artisan vendor:publish --tag=ptah-assets --force` and
`php artisan view:clear`.

**After every `composer update` of ptah**, re-publish the stylesheet:

```bash
php artisan vendor:publish --tag=ptah-assets --force
```

The `<link>` carries the package's build hash (`ptah.css?v=…`), so browsers
never keep an old copy — but the file itself must be the new one.
`php artisan ptah:upgrade-check` warns when the published file is missing or
is not the one of the installed ptah.

---

## 2. Where ptah's screens live

### 2.1 In ptah's own layout

`ptah:forge` screens, the administration screens, login/profile and
`<x-forge-dashboard-layout>` work as in a Tailwind project. In Bootstrap mode
the layout:

- links `ptah.css`, then your own Vite entries that exist in your build
  (`resources/sass/app.scss`, `resources/css/app.css`, `resources/js/app.js` —
  whichever are in `public/build/manifest.json`). ptah first, as
  `ptah-components.css` sits first in a Tailwind project's `app.css`: your
  rule overriding a ptah token (same selector) wins by source order;
- never loads the Tailwind CDN;
- keeps the person's appearance settings (tone, accent, text weight, density,
  font size — `/profile`), exactly as in Tailwind;
- writes `data-bs-theme="dark|light"` on `<html>` when the dark mode is toggled,
  so Bootstrap markup on the page follows.

Your own Bootstrap markup can go in the layout's slot — it keeps Bootstrap's
meaning (a `px-3` there is Bootstrap's 1rem, not Tailwind's .75rem):

```blade
<x-forge-dashboard-layout>
    <x-slot:title>Clientes</x-slot:title>

    <div class="alert alert-info mb-3">Bootstrap markup — Bootstrap values.</div>

    @livewire('ptah-base-crud', ['model' => 'Cliente'])
</x-forge-dashboard-layout>
```

### 2.2 In a layout of your own

```blade
<head>
    <x-ptah-assets />          {{-- or @ptahAssets — before your CSS --}}
    …your Bootstrap (@vite or <link>)…
    @livewireStyles
</head>
<body>
    <div class="container">
        @livewire('ptah-base-crud', ['model' => \App\Models\Cliente::class])
    </div>
    @livewireScripts
</body>
```

`<x-ptah-assets />` brings what the BaseCrud otherwise only gets from ptah's
layout: `ptah.css` (Bootstrap mode), the icon fonts (Boxicons, Font Awesome),
the brand colours you set explicitly, the anti-flash dark/light boot, and the
toast host (where the *undo* of a deletion lives), appended to `<body>`.
Put it **before** your own CSS, so your token overrides win by order; the
isolation from Bootstrap does not depend on the order.

In a Tailwind project the same component works too; it links no stylesheet
(your `app.css` already carries ptah's).

---

## 3. Theming in Bootstrap mode

**Inside a layout of your own**, ptah's tokens follow your Bootstrap theme —
change `$primary`, or switch `data-bs-theme="dark"` on `<html>`, and the
BaseCrud follows without any rebuild:

| ptah token | Bootstrap variable |
|---|---|
| `--color-primary` (and every tint derived from it) | `--bs-primary` |
| `--color-success` / `--color-danger` / `--color-warn` / `--color-dark` | `--bs-success` / `--bs-danger` / `--bs-warning` / `--bs-dark` |
| `--ptah-canvas`, `--ptah-surface`, `--ptah-surface-raised`, `--ptah-field` | `--bs-body-bg` |
| `--ptah-panel`, `--ptah-surface-sunken`, `--ptah-surface-hover`, `--ptah-menu-hover`, `--ptah-field-muted` | `--bs-tertiary-bg` |
| `--ptah-control-ghost(-hover)` | `--bs-secondary-bg` (mixed with `--bs-tertiary-bg`) |
| `--ptah-line-strong` | `--bs-border-color` |
| `--ptah-line` | `--bs-border-color` at 50% over `--bs-body-bg` |
| `--ptah-line-field`, `--ptah-line-control` | `--bs-body-color` at 50% / 25% over `--bs-body-bg` (a field border keeps 3:1) |
| `--ptah-text`, `--ptah-text-field` | `--bs-body-color` |
| `--ptah-text-strong` | `--bs-emphasis-color` |
| `--ptah-text-secondary`, `--ptah-text-muted`, `--ptah-text-faint`, `--ptah-icon-muted` | `--bs-secondary-color` (`--bs-tertiary-color` is under 4.5:1 for text) |
| `--font-sans` | `--bs-body-font-family` |
| `--radius-sm` / `--radius-md` / `--radius-lg` | `--bs-border-radius-sm` / `--bs-border-radius` / `--bs-border-radius-lg` |

What wins over the bridge:

- `PTAH_COLOR_PRIMARY` (and the other `PTAH_COLOR_*`) — or any value of
  `ptah.theme.colors` other than the package default — set explicitly;
- your own `:root { --ptah-…: …; }` — the bridge sits in a cascade layer, and
  any unlayered rule of yours beats it.

**Inside ptah's own layout** the bridge does not apply: those pages carry the
person's appearance settings and look exactly as in a Tailwind project. Your
Bootstrap markup in their slot still uses your Bootstrap theme.

**Dark mode** answers both ptah's `.ptah-dark` and Bootstrap's
`:root[data-bs-theme="dark"]`. In your own layout, a `data-bs-theme` you put
on `<html>` is respected — ptah's boot script does not override it.

---

## 4. How the isolation works (and its rules)

`ptah.css` is Tailwind v4 compiled from ptah's views, components, the stubs
`ptah:forge` writes and the PHP that renders cells — the same preflight,
utilities and `ptah-components.css` a Tailwind project compiles — and then:

- **Scoped.** Preflight and utilities apply only inside a ptah **scope**
  (`data-ptah-scope`, or the class `ptah-scope`) and stop at a **slot**
  (`data-ptah-slot`), through CSS `@scope (…) to (…)`. Nothing in `ptah.css`
  reaches your page outside ptah's markup.
- **Bootstrap neutralised in the scope.** Bootstrap's reboot is unlayered and
  its utilities (`px-3`, `p-4`, `border`, `rounded`, `text-primary`…) are
  `!important`. For each Bootstrap rule that can hit ptah's markup, `ptah.css`
  repeats the selector inside the scope with `revert-layer` — same
  specificity, closer scope, so it wins on ptah's elements and hands the
  property back to ptah's styles. A Bootstrap rule outside the scope is never
  touched.
- **Stacking.** ptah's overlays (modals, menus, toasts, the busy overlay, the
  assistant) are lifted above Bootstrap's fixed navbar (1030) and modal
  (1055): `z-50` becomes 1060. ptah's own sidebar and navbar stay at
  1028–1030, under a Bootstrap modal.

Who marks the scope:

| Element | Marked by |
|---|---|
| `<body>` of ptah's layouts (dashboard, auth, error pages) | the layout |
| Root of every Livewire component of the package (BaseCrud, CrudConfig, admin screens, bell, company switcher, assistant…) and of host classes extending them | a Livewire render hook |
| An `<x-forge-*>` you write in **your** views | a Blade compile step adds it to the tag; its slots are fenced as host markup |
| The page header `ptah:forge` generates | the generator (`data-ptah-scope` on the header) |

Consequences you should know:

- **Your markup in a ptah slot is Bootstrap**: the main slot of
  `<x-forge-dashboard-layout>` and the slots of an `<x-forge-*>` you write.
  To use ptah's Tailwind classes there, put them inside an element with
  `data-ptah-scope`.
- **Screens generated before the switch** have a page header without the
  marker: regenerate them (`ptah:forge … --force`) or add `data-ptah-scope` to
  the header `<div>`.
- **`ptah::layouts.forge-dashboard` / `forge-auth`** (`@extends` or a Livewire
  `#[Layout]`) are ptah's page layouts: what you put in their content is
  treated as ptah markup (Tailwind classes). For pages of your own with
  Bootstrap markup use the `<x-forge-dashboard-layout>` component.
- **Compiled views**: the marker is added when Blade compiles your view. After
  installing/upgrading run `php artisan view:clear` (the installer does).
- **Custom classes in a screen config** (`colsCellClass`, row styles,
  badges) are compiled only if ptah's own sources use them — the same rule a
  Tailwind project has with `@source`. A class nobody uses is missing in both.
- **Browsers**: CSS `@scope` and `revert-layer` — Chrome/Edge 118+, Safari
  17.4+, Firefox 146+.
- **Your own element rules do not reach ptah's markup.** A rule of yours with
  exactly the weight of a Bootstrap selector (`body { … }`, `a { … }`,
  `h2 { … }`) is neutralised inside the scope together with Bootstrap's.
  Theme ptah through its tokens (§3) instead.
- **A custom Bootstrap build** (other spacers, renamed utilities) is
  neutralised only where its selectors match stock Bootstrap 5.3.9.
- Weight: about 310 KiB uncompressed, 35 KiB gzip.

---

## 5. Maintaining the stylesheet (package contributors)

`resources/dist/ptah.css` is generated and committed:

```bash
npm install
npm run build:css
```

`scripts/build-css.mjs` builds it from `scripts/css/ptah.css` (the entry,
mirroring a Tailwind host's `app.css`) and appends `scripts/css/bridge.css`
(the Bootstrap bridge). Its inputs are listed in `scripts/css/sources.json`;
their hash is written in the file's header, and `PtahCssDistTest` fails when a
view, stub, renderer or CSS source changed without a rebuild. Tailwind,
PostCSS and Bootstrap are pinned in `package.json` for the same reason.

The browser test `tests/Browser/CssBootstrapBrowserTest.php` checks, in
Chrome, with Bootstrap on the page: ptah's `px-3` is 12px and the host's
16px, host markup in a slot keeps Bootstrap's values, the ptah modal covers a
fixed Bootstrap navbar, the embedded screen follows `--bs-primary` and
`data-bs-theme="dark"`, and the console stays clean.
