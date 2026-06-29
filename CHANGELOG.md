# Changelog

All notable changes to `jonytonet/ptah` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.1] — 2026-06-24

### Fixed
- **Config form preview crashed on `select` fields (HTTP 500).** Any BaseCrud
  screen whose `CrudConfig` had a savable `select` column returned a 500 for
  admins, because the preview iterated `colsSelect` as an array while the edit
  state holds it as a string (`"label;value;;…"`). The `foreach` over a string
  threw `foreach() argument must be of type array|object, string given`, and
  since the preview overlay is rendered (hidden via `x-show`, not `@if`), the
  page failed to load even with the preview closed.
  - `previewFormCols()` now returns `colsSelect` in array form (the edit-state
    string is left untouched so the editable input keeps working).
  - Extracted the string→array parsing into a shared `parseColsSelect()` helper
    reused by both the save path and the preview, so the two can't diverge again.
- 2 regression tests: `previewFormCols()` normalises the select string to an
  array (and leaves the edit state a string); opening the preview renders the
  `<option>`s without error.

## [1.1.0] — 2026-06-24

Dedicated print screen + nested relationship paths + shared query builder.

### Print screen (`/ptah/print`)
- **New: a dedicated print view** opened from the export menu. Unlike the old
  `window.print()` (which only printed the current paginated page), it renders
  **all filtered records** (up to `exportConfig.maxRows`, default 5000) on a clean,
  chrome-free HTML page in a new tab — ready to `Print` or `Copy (Excel)`.
  - **Same data as the listing**: the snapshot is built by the BaseCrud component
    itself (`printView()`), reusing the shared query and `formatCell()`, so badges,
    money, dates, select labels and nested relations render identically and respect
    the active search/filters/company scope. The component caches a ready payload
    under a short-lived, user-scoped token; `CrudPrintController` only displays it
    (no filter logic in the controller → it can never diverge).
  - **Totals footer**: totalizadores are shown per column, computed over the full
    filtered set (SQL aggregate, before the `maxRows` cut).
  - **Copy (Excel)**: copies the table as `text/html` (pastes into Excel / Google
    Sheets as a real table with split columns) plus a `text/plain` TSV fallback;
    uses the Clipboard API with an `execCommand('copy')` fallback.
  - A truncation note is shown when the result exceeds `maxRows`.

### Shared query builder — totals & export now honor every filter
- **Refactored**: extracted `buildBaseQuery()` + `applyGroupingAndSort()` from
  `rows()`; the listing, totals, export and print now all build the query through
  the **same single source of truth**.
- **Fixed (latent bug)**: `totalizadoresData()` previously applied only the form
  filters + date ranges, so the **footer totals could disagree with the visible
  rows** whenever a global search or company filter was active. Totals now reflect
  the exact same filtered query as the listing (search, company, locked, whereHas,
  quick date and custom filters all included).

### Filters/columns — nested relationship paths (`a.b`)
- **Fixed: a column whose `colsRelacao` is a nested path** (e.g.
  `purchaseIncomingInvoices.expeditionReceivingStatus` + `colsRelacaoExibe: name`)
  was broken on three fronts; now fully supported:
  - **Render** (`formatCell`): a dotted `colsRelacao` is resolved with `data_get`
    down the whole chain instead of a single magic-property lookup of the literal
    `"a.b"` key (which rendered empty).
  - **Filter** (`buildActiveFilters`): a selected id no longer filters the root FK
    (which points at the intermediate model, not the final one). Nested paths now
    always go through `whereHas` — numeric id matches the related primary key
    (`colsSDValor`, default `id`), text searches `colsRelacaoExibe` — both via
    Eloquent's native dotted `whereHas`. Single-level columns keep the FK shortcut.
  - **Sort**: nested relation columns are skipped by the relation-JOIN sort
    (`getOrderByRelationInfo` bails on a dotted path) to avoid an invalid table
    name / broken JOIN.
  - Eager loading already handled the dotted path (`with('a.b')`).
- 4 new tests: nested render via `data_get`, nested filter routing (numeric →
  whereHas on the related key, text → display column) and nested-column sort skip.

## [1.0.2] — 2026-06-11

Filter engine fixes + Laravel 13 compatibility.

### Compatibility — Laravel 13 / Livewire 4 / PHP 8.4
- Production constraints already allowed `laravel/framework: ^11|^12|^13` and
  `livewire/livewire: ^4.0`; this leg makes the **toolchain and CI actually cover
  Laravel 13**: `require-dev` widened to `orchestra/testbench: ^9|^10|^11` and
  `phpunit/phpunit: ^11|^12`, and the test matrix gained a Laravel 13 / Testbench 11
  job on PHP 8.4. README badge and requirements updated to 11 · 12 · 13.
- Bumped `guzzlehttp/guzzle` (≥7.12.1) and `guzzlehttp/psr7` (≥2.12.1) in the lock
  to clear three medium CVEs (transitive dev deps; `composer audit` now clean).

### Filters — relationship filtering fix + NULL operators + hardening
- **Fixed: filtering a relationship column by text was broken.** A column with
  `colsRelacao` + `colsRelacaoExibe` was filtered directly on the FK as text
  (`where fk LIKE '%name%'`), which never matches (the FK holds ids). Now
  `buildActiveFilters()` routes by value: a numeric value filters the FK directly
  (`where fk = id`, `!=` supported), and a text value searches the related display
  column via `whereHas(relation, col LIKE …)` through `RelationFilterStrategy`.
- **New: `IS NULL` / `IS NOT NULL` operators** — filter by a column with no value
  (e.g. "orders without an invoice"). Centralised in `FilterService::applyFilter()`
  so they work for every column type and inside AND **and** OR groups
  (`FilterService::NULL_OPERATORS` + `isNullOperator()`), guarded by
  `SqlIdentifier`. `FilterDTO::isValid()` accepts a value-less NULL filter;
  `buildActiveFilters()` keeps a filter that carries only a NULL operator;
  the filter panel exposes the two operators and disables the value input.
- **New: searchdropdown filters support `=` and `!=`** — the filter-panel
  searchdropdown gained an operator select, and `selectFilterDropdownOption()` now
  preserves a user-chosen operator instead of forcing `=` (e.g. "status different
  from finished"). The `!=` path reuses the relation FK-id branch. (Caveat noted in
  code: `fk != id` also excludes rows with a NULL FK.)
- **Hardening: an empty/"select…" operator no longer becomes an invalid clause.**
  `FilterDTO::fromArray()` and `buildActiveFilters()` normalise an empty or
  non-string operator to `=` (Laravel silently discards `where col '' value`).
- The `empty('0')` class of bug was already absent in ptah (strict `=== null/''`
  comparisons throughout); a regression test now locks that in.
- 19 new tests: NULL-operator behaviour (incl. OR groups + SQLi guard), FilterDTO
  operator normalisation / validity / `'0'` regression, and `buildActiveFilters`
  relation routing (numeric FK vs text whereHas) + NULL + empty-operator paths.

## [1.0.1] — 2026-06-11

Developer-experience release: theme your brand colors from config, preview the
form while configuring it, and publish views surgically.

### Theming — config-driven brand colors
- **New `config('ptah.theme.colors')`** (primary/success/danger/warn/dark, each
  with an `.env` override like `PTAH_COLOR_PRIMARY`). Ptah injects them as CSS
  custom properties (`--color-primary`, `--ptah-primary`, …) in the dashboard and
  auth layout `<head>` via a shared `partials/theme-colors` view. Because
  `ptah-components.css` derives every tint/ring/hover from `--color-primary` with
  `color-mix()`, setting one value rebrands the whole UI — no view publishing, and
  it survives `composer update`. The CDN-fallback Tailwind config in both layouts
  now reads the same config values, so colors are consistent with or without a
  Vite build.

### View publishing — granular tags (footgun prevention)
- Added **granular publish tags** so you no longer have to publish all 60+ views
  at once: `ptah-views-components`, `ptah-views-base-crud`, `ptah-views-auth`,
  `ptah-views-ai`. The blanket `ptah-views` remains but is documented as a last
  resort. Publishing a view means Laravel prefers your copy and `composer update`
  never refreshes it — the granular tags + a code comment + a README section make
  that trade-off explicit, steering devs to publish only what they edit (or
  nothing, since most customization is database-driven via CrudConfig).

### Config modal — inert form preview
- **"Preview form" button** in the CrudConfig modal footer opens an inert mirror
  of the create/edit form, built from the columns currently marked as savable
  (unsaved `formEditFields`): section headings (`colsFormBlock`), required marks,
  help text, per-type controls and the cascade gating hint — all disabled, no
  data binding, no validation, no queries, no actions. Lets the dev see the form
  layout while building it.
- **Discoverability fix:** moved `colsFormBlock` and `colsOnChange` from the
  "Mask" sub-tab (where they were easy to miss) to the "Basic" sub-tab of the
  column editor. Cascade fields stay in the "SearchDropdown" sub-tab.
- New `CrudConfig::previewForm()/closePreview()/previewFormCols()`, the
  `_config-form-preview.blade.php` partial, 7 i18n keys (en/pt_BR) and
  `ConfigFormPreviewTest` (3 tests).

## [1.0.0] — 2026-06-11

First public stable release on Packagist. Consolidates everything below: SOLID
scaffolding (`ptah:forge`), the database-driven BaseCrud (filters, master/detail,
group breaks, calculated fields, cascading dropdowns, card view, export, print),
the auth/permissions/menu/company/api/ai_agent modules, a security-reviewed
permission engine, the AI streaming chat, 339 passing tests, CI on PHP 8.2–8.4 ×
Laravel 11/12, and a brand-driven, dark-mode-ready UI.

### AI Agent module — streaming & token accounting
- **Streaming responses** — the chat widget now streams the answer token-by-token
  via a new `AiChatService::stream()` and Livewire `wire:stream`. Toggle with
  `ptah.ai_agent.stream` (default `true`). `send()` (blocking) remains for the
  toggle-off path. Shared guards/persistence were extracted so both paths behave
  identically. Note: the browser-side incremental render is verified manually; the
  PHP emission of stream directives is covered by tests.
- **Fixed token accounting** — `AiChatService` read `usage->inputTokens`/`outputTokens`
  which don't exist on Prism's `Usage` object, so `tokens_used` always recorded 0.
  Now reads `promptTokens`/`completionTokens`.
- Switched the Prism call from the deprecated `generate()` to `asText()`.
- `prism-php/prism` added to `require-dev` so the module is covered by the package
  test suite.
- Added tests: `AiChatServiceTest` (send + stream against `Prism::fake()`, token
  accounting, temperature forwarding, rate-limit/no-provider/guest guards, delta
  extraction for both the real `TextDeltaEvent` and the testing fake),
  `AiToolRegistryTest` (Prism Tool conversion) and `AiChatWidgetTest` (widget wiring
  + guest gating).

### Fixed
- **Base layer UI sentinels were not honored in `searchLike()`/`advancedSearch()`**
  — the methods filtered by the literal default value instead of skipping it, so a
  request with the default `searchLike`/`search`/`relations` returned no rows (or
  threw `RelationNotFoundException` for `relations`). The guards now match the
  documented contract.
- Renamed the base-layer sentinel values to English to match the documentation
  (`BaseLayer.md`) and the rest of the API surface: `search`/`searchLike` default
  sentinel is `Search` (was `Busca`), and the `relations` sentinel is `Relation`
  (was `Relacao`). `Incremental` is unchanged. **Breaking** only for REST clients
  that explicitly sent the old Portuguese magic words as no-op defaults.

### AI Agent module
- **Fixed: `temperature` was ignored** — `AiChatService` now passes the configured
  temperature to Prism (`->usingTemperature()`). Previously the column was stored
  and validated but never applied.
- **Fail-closed authorization on `AiModelConfigList`** — the `ai.config` permission
  is now re-checked on every mutating action (create/edit/save/delete/setDefault),
  not only on `mount()`. These records hold provider API keys.
- **Rate limit keyed by user** when authenticated (was session-only, bypassable by
  dropping the session cookie); the public `processAiMessage` listener re-checks
  provider availability.
- **Octane safety** — provider credentials applied at runtime are now restored after
  each request, so API keys don't bleed across requests on long-lived workers.
- **`getSystemInfo` tool** no longer leaks framework/PHP versions or the environment
  name unless `ptah.ai_agent.expose_system_details` is enabled.
- **Optional per-user daily token budget** via `ptah.ai_agent.daily_token_limit`.
- **`ptah.ai_agent.allow_guests`** (default `false`) — the chat widget and service
  are restricted to authenticated users unless explicitly enabled.
- Removed dead code from `AiToolRegistry` (`execute()`, `hasTools()`); clarified the
  `max_history` config docs (message count, not tokens).
- Added tests: `AiProviderConfigServiceTest` (config service, encrypted-at-rest API
  key, scopes) and `AiModelConfigListAuthTest` (fail-closed authorization).

### Security
- **Removed `eval()` from inline lifecycle hooks** (`HasCrudForm::executeInlineHook`).
  Inline hooks are now sandboxed Symfony ExpressionLanguage expressions — no
  arbitrary PHP execution, eliminating the RCE risk if a `crud_configs` row were
  tampered with. Adds `symfony/expression-language` as a dependency. **Breaking:**
  inline hooks that contained PHP statements must be migrated to a hook class;
  inline now only reshapes `data` (helpers: `merge`, `now`, `upper`, `lower`,
  `slug`, `uuid`). Class-based hooks are unchanged.
- **SQL injection hardening in dynamic filters** — column/identifier names are now
  validated by `Ptah\Support\SqlIdentifier` before being interpolated into raw SQL
  in `TextFilterStrategy`, `RelationFilterStrategy` (whereHas + aggregate/HAVING)
  and `HasCrudSearchDropdown`. Unsafe identifiers are rejected.
- **No insecure default admin password** — removed the hardcoded `admin@123`
  fallback from `config/ptah.php`, `ModuleCommand` and `DefaultAdminSeeder`. When
  `PTAH_ADMIN_PASSWORD` is unset, a strong random password is generated and shown
  once during installation.
- **XSS hardening in table actions** — `javascript:`/`data:`/`vbscript:` schemes are
  now blocked on `link`-type action columns in `_table.blade.php`.
- **Mass-assignment guard** — `save()` now strips `id`, timestamps and audit/`*_by`
  columns from submitted data regardless of the CRUD config.
- **Fail-closed authorization** — create/update/delete/restore now deny anonymous
  users when a `permissionIdentifier` is configured (previously the whole check was
  skipped for unauthenticated requests). Centralised in `authorizeCrudAction()`.
- **Rate limiting added** to password-reset requests (`ForgotPasswordPage`) and to
  the **2FA code challenge** (`TwoFactorChallengePage`), preventing brute-force of
  the verification code.

### Changed
- `ptah:forge --force` now asks for confirmation in interactive sessions before
  overwriting existing files.
- Generated migrations add an index on `deleted_at` when soft deletes are enabled,
  and `--no-soft-deletes` on a fresh migration no longer emits `softDeletes()`
  (the stub now uses a `{{ soft_deletes }}` placeholder controlled by the generator).
- Forge component docs (`forge-input`, `forge-button`) warn that icon props/slots
  render raw HTML and must never receive user-controlled data.

### Docs
- Standardised Livewire version to 4 across `BaseCrud.md` and `Modules.md`.
- Aligned admin-password documentation (no fixed default) across `Commands.md`,
  `Permissions.md`, `Modules.md` and `PetPlace-Prompt-Example.md`.
- Documented the `surname=`/`label=` field modifiers and added a `ptah:menu-sync`
  reference section in `Commands.md`.
- Rewrote the inline lifecycle-hook documentation in `Configuration.md` for the new
  sandboxed expression syntax.

### Permissions — cache invalidation rework (security)
- **Fixed: revoking a permission was not immediate.** Editing a role's object
  bindings (`RoleService::bindPageObject`/`unbindPageObject`/`syncPageBindings`)
  never cleared the permission cache, so a revoked action stayed effective for up
  to `cache_ttl` (default 1h). Replaced the broken tag-based invalidation (keys
  were never stored with tags, so `Cache::tags()->flush()` was a no-op) with
  **generation-based versioning**: every cache key embeds a global counter and a
  per-user counter; invalidation just increments a counter — O(1), works on every
  driver (file/database/redis/memcached), no key enumeration. Revocation now takes
  effect on the next check.
- Invalidation is wired via model observers (`Role`/`RolePermission` → global bump,
  `UserRole` → per-user bump) **and** explicit bumps in `RoleService` (covers
  query-builder mass deletes that don't fire model events).
- **Action whitelist** — `check()` and `getCompaniesForResource()` now reject any
  action outside `create/read/update/delete` before it is interpolated into the
  `can_{action}` column (typo + SQL-injection guard).
- **MASTER permission map is now cached** (per global generation) instead of
  querying `PageObject` on every request for master users.
- `clearCache()` no longer needs the `$companyId` argument — a per-user bump clears
  every company-scoped map at once.
- Docs: documented MASTER being global (not company-scoped), `company_id = null`
  meaning cross-tenant, and the generation-based cache. 10 new tests
  (`PermissionServiceTest`) including the immediate-revocation regression that
  would fail on the old code.

### Power features (ScriptCase-inspired)
- **Master/Detail** — `masterDetail` config adds an expand arrow per row that
  mounts a nested BaseCrud filtered by the parent key via the new
  `lockedFilters` mount parameter: enforced on every query, immune to the
  child's Clear filters, `SqlIdentifier`-guarded. Multiple detail grids per row
  supported; first entry editable in the CrudConfig modal (General tab).
- **Group break ("quebra") with subtotals** — `groupBreak` config keeps rows
  individual but makes the field the primary sort and renders a header per group
  plus per-group subtotal rows (reusing the Totalizer columns), styled with the
  brand tint. Unsafe field names are ignored.
- **Calculated fields** — `colsOnChange` column option runs a sandboxed
  expression (same ExpressionLanguage engine as the lifecycle hooks; variables
  `data`/`value`) whenever the field changes, live (`.live.debounce.600ms` on the
  trigger input). Errors are logged and never break the form.
- **Form sections** — `colsFormBlock` groups adjacent fields under a section
  heading in the create/edit modal.
- **Card (mosaic) view** — toolbar toggle (persisted per user) switches the
  listing between the table and a responsive card grid sharing the same row
  states, selection, actions and pagination.
- **Duplicate record** — copy action per row opens the create modal pre-filled
  with the source row (guarded/audit fields never copied).
- **Print view** — Export menu → Print with a dedicated `@media print`
  stylesheet (chrome, selection and action columns hidden, sticky columns
  flattened).
- Actions column now shrinks to its icons (`width:1%` + nowrap) instead of
  absorbing leftover table width.
- 9 new tests (duplicate, formulas incl. sandbox failure, break sort/render/
  guard, locked filters, detail toggle, view mode); 26 new i18n keys; docs in
  `BaseCrud.md`.

### Cascading (dependent) search dropdowns
- **New `colsSDDependsOn` / `colsSDFilterColumn` column options** — make a
  searchdropdown depend on another form field (Country → State → City, unlimited
  depth). The child is disabled with a "Select {parent} first…" placeholder until
  the parent has a value; its options are filtered by `WHERE {filterColumn} =
  {parent value}` (column guarded by `SqlIdentifier`); changing the parent clears
  the entire descendant chain (value, label, cached results) — including when the
  parent is a plain select, via the `updatedFormData` hook.
- Works in the **filter panel** as well: dependent searchdropdown filters follow
  the same gate/filter/reset rules against the active filters.
- Configurable in the CrudConfig modal (SearchDropdown tab → Cascading section);
  documented in `BaseCrud.md`; covered by 5 new tests (gate, parent filtering,
  recursive reset on both scopes).

### Visual refresh — modern, brand-driven styling
- **Single brand source** — every accent in `ptah-components.css` (focus rings, sort
  arrows, filter chips, active buttons, saved filters, quick-date buttons, bulk bar,
  selected dropdown items, modal icon) now derives from the host's
  `--color-primary` token via `color-mix()` tints. No more hardcoded blue clashing
  with a purple primary: change the token once and the whole CRUD follows.
- **Elevation** — dropdown menus gained a layered shadow + hairline ring and a
  scale/fade open transition; toolbar, filter panel and table wrapper have a subtle
  ambient shadow; modals are `shadow-2xl`; solid forge-buttons get `shadow-sm` and a
  visible `focus-visible` ring.
- **Row states** — hover is now neutral (`slate-50`); brand color is reserved for
  the new selected-row state (soft brand background + 2px brand edge) so bulk
  selection finally has visual feedback.
- **Badges modernised** — badge/pill/boolean renderers moved from the old
  `bg-*-100/text-*-800` look to the current soft + inset-ring idiom
  (`bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20`); the duplicated
  color map was extracted to a shared `badgeColorClasses()` helper.
- **Radius hierarchy** — containers (toolbar, table, filter panel, dropdowns)
  are `rounded-lg`, modals `rounded-xl`; controls keep `rounded-md`.
- **Detailing** — column drag-grip appears only on header hover (cleans 8× visual
  noise); thin styled scrollbar on the table wrapper; circular ringed empty-state
  icon; modal footer separator actually visible.

> After updating, rebuild the host assets (`npm run build`) so the new classes and
> CSS variables are picked up.

### BaseCrud UX overhaul (12 usability improvements)
- **Undo on delete** — the post-delete toast now shows an inline *Undo* button for
  soft-deletable records (calls `restoreRecord`); toasts with Undo stay visible for 6s.
- **Toasts now stack** — multiple notifications no longer overwrite each other; each
  has its own timer and dismiss button (`aria-live=polite`).
- **Native `confirm()` dialogs replaced** with styled, theme-aware dialogs for bulk
  delete / permanent delete (with an "irreversible" warning) and for the
  unsaved-changes check when closing the form modal (Esc first closes the warning).
- **Row links behave like real links** — Ctrl/Cmd-click and middle-click on a row
  with `configLinkLinha` open the record in a new tab (`ptahRowNav`).
- **"Save & add another"** button on the create modal (`saveAndNew()`): persists and
  reopens a blank form — for batch data entry. Covered by 3 new tests.
- **Keyboard shortcuts** — `/` focuses the global search, `n` opens the create modal
  (ignored while typing or with a modal open).
- **Accessibility** — column sort is now a real `<button>` with `aria-sort` on the
  `<th>` (keyboard sortable); `aria-label` on every icon-only button (row actions,
  refresh, clear filters, search clear), on bulk checkboxes and the per-page select.
- **Larger touch targets** — row action buttons gained padded hitboxes and the column
  resize handle is 2× wider without visual change.
- **Sticky actions column** — the default actions column stays visible during
  horizontal scroll (with row-hover background preserved via `group-hover`).
- **Floating bulk bar no longer covers pagination** (spacer when a selection is active).
- **i18n fix** — the "N active" filter badge was hardcoded in Portuguese; now uses
  `trans_choice` with proper pluralisation in both locales (11 new lang keys).

### Tests — BaseCrud save, renderers, search dropdown and export (P1, section 5 complete)
- **`CrudSaveTest`** (6) — end-to-end `save()` through the real component: create with
  mask transform applied, required-field validation blocking the insert, guarded
  fields stripped even when marked savable, inline sandbox hook reshaping data,
  edit-update without duplication, `prepareCreate()` state reset.
- **`CrudRenderersTest`** (14) — XSS safety first: plain values, badge fallbacks and
  config labels are always escaped; badge color maps (named + hex), boolean truthy
  variants and custom labels, money per currency, BR date formatting with
  unparseable passthrough, select label mapping, cell class/icon wrappers,
  conditional row styles (==, >), unknown-renderer fallback.
- **`CrudSearchDropdownExportTest`** (11) — model-backed lookups with value/label
  pairs, `colsSDLimit` cap, empty-query reset, option selection filling
  formData/labels, filter-panel selection + clear flows; export sync dispatch,
  disabled gate and bulk-export selection requirement.

### Tests — BaseCrud concerns and filter pipeline (P1, section 5)
- **`FilterServiceTest`** (15) — AND/OR composition against real rows, plain-array
  to DTO conversion, invalid-filter skipping, date-range form parsing (`_start/_end`,
  explicit operators, legacy `_from/_to`), custom-filter config parsing (whereHas,
  CSV `IN`), global search building (OR LIKE for text/select, whereHas for relations).
- **`FilterStrategiesTest`** (12) — Numeric (array/CSV BETWEEN, partial bounds,
  comparison operators), Date (same-day range covers startOfDay..endOfDay, out-of-window
  exclusion, whereDate equality, null no-op), Array (whereIn, CSV normalisation,
  NOT IN, blank CSV no-op).
- **`CrudMaskTransformsTest`** (13) — `money_to_float` for BR/EN/comma-only formats,
  `digits_only`, `plate_clean`, BR↔ISO dates (invalid input passes through untouched),
  case/trim, unknown transform and skip rules.
- **`FormValidatorServiceTest`** (13) — required (incl. legacy `'S'`), optional-empty
  short-circuit, numeric min/max vs string lengths, digits, in/notIn, regex,
  confirmed cross-field, email, CPF check digits, first-error-only per field.
- **`CrudDeletionTest`** (8) — the real BaseCrud component mounted with a DB config
  row: soft delete + `deleted_by` stamping, restore, trashed count, confirm/cancel
  flow, fail-closed delete for anonymous users.
- **`CrudQueryTest`** (7) — HasCrudQuery pipeline through the real component:
  global search, sort ASC/DESC, form filters, operator filters, per-page pagination,
  quick date filter.

### Fresh-install validation (bugs found and fixed)
Validated the full flow on a brand-new Laravel 12 app (create-project →
path-repository require → `ptah:install` → modules → `ptah:forge` → migrate →
serve). Two real bugs surfaced and were fixed:

- **Generated controller was missing imports** — `controller.stub` referenced
  `Store{Entity}Request`, `Update{Entity}Request` and `RedirectResponse` without
  `use` statements, fataling on store/update calls (subfolder entities included:
  requests now import from the sub-namespace). Covered by the new
  `ControllerGeneratorTest`.
- **Generated web route had no `auth` middleware** — anonymous visitors hit the
  controller and received the permission 403 instead of being redirected to
  `/login`. When `ptah.modules.auth` is active, `ptah:forge` now appends
  `->middleware('auth')` to the generated route.

> **Upgrade note:** stubs are published to `stubs/ptah/` on install and take
> precedence over the package copies. After upgrading, re-publish to get these
> fixes: `php artisan vendor:publish --tag=ptah-stubs --force` (or delete the
> stubs you did not customise).

### Tests — commands (P1)
- **`ScaffoldCommandTest`** (8 tests) — full web artefact set on disk (model, DTO,
  repository + interface, service, requests, resource, view, migration, route,
  binding, crud_configs row), subfolder entities, `--no-soft-deletes`, `--api-only`
  (no views/CrudConfig, API requests + apiResource route), `--force` confirmation
  abort, skip-without-force preserves user edits, acronym table naming
  (`POSSale` → `pos_sales`).
- **`MakeHooksCommandTest`** (4) — generated class implements `CrudHooksInterface`
  with the four hook methods, subfolder namespaces, `--force` semantics.
- **`MenuSyncCommandTest`** (4) — flat links/groups/children synced, idempotent
  re-run, `--fresh` clears stale rows, missing registry fails.
- **`ModuleCommandTest`** (2) — `--list` succeeds, unknown module fails.

### Contribution sandbox
- **`sandbox/docker-compose.yml` + `setup.sh`** — disposable Laravel app with the
  local package symlinked via path repository: `cd sandbox && docker compose up`
  and edit the package with instant feedback. No local PHP/Composer/Node required.
  Documented in `sandbox/README.md` and linked from `CONTRIBUTING.md`.

### Tests — generators (P1)
- **`GeneratorTestCase`** + 9 test files (36 tests) covering Model, DTO, Repository +
  Interface, Service, Requests (web + API), Resource, Routes, Binding, CrudConfig and
  View generators: generated content assertions, `php -l` lint checks, idempotency of
  route/binding injection and the `shouldRun` gates for api-only mode.
- **Fixed: generated DTOs emitted optional constructor parameters before required ones**
  (deprecated in PHP 8). `EntityContext::dtoProperties()` now orders required properties
  first; safe because `fromArray()` uses named arguments.

### Static analysis
- **PHPStan baseline reduced from 208 to 140 errors** via root-cause fixes: typed
  model-event closures in `HasAuditFields` (-40), removed compile-time `App\Models\User`
  references in favour of config-driven strings (-12), `@property` annotations on `Menu`,
  fixed malformed array-shape PHPDoc and declared `#[Computed]` properties in `RoleList`.
  A single documented `ignoreErrors` rule replaces 20 identical baseline entries for the
  package-namespace `view('ptah::…')` false positive.

### Community / DX
- Added `CONTRIBUTING.md`, YAML issue forms (bug / feature), `PULL_REQUEST_TEMPLATE.md`
  and `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1 by reference).
- Added `docs/QuickStart.md` — first CRUD in 5 minutes (SQLite, one entity), linked
  from the README.

### Tests — P0 security regression suite
- **`FilterStrategySecurityTest`** (`tests/Unit/Services/Crud/FilterStrategySecurityTest.php`)
  — 10 tests: `TextFilterStrategy` and `RelationFilterStrategy` discard every class of
  malicious column name (SQL injection, semicolons, leading-digit, unquoted spaces, single
  quotes) and still apply safe identifiers and table-qualified names.
- **`CrudFormSecurityTest`** (`tests/Feature/Crud/CrudFormSecurityTest.php`)
  — 8 tests: `guardedFormFields()` lists all 8 audit/PK columns that must never come from
  form data; inline hooks with `merge(data, {...})` and `upper()` work correctly; arbitrary
  PHP (`file_put_contents`) and invalid syntax are rejected by the ExpressionLanguage sandbox
  without propagating exceptions; `authorizeCrudAction()` is fail-closed (anonymous users
  denied with `permissionIdentifier` set, allowed when module is off or identifier is absent).
- **`AuthRateLimitTest`** (`tests/Feature/Auth/AuthRateLimitTest.php`)
  — 6 tests: `LoginPage` blocks after 5 attempts and allows under the limit;
  `ForgotPasswordPage::sendLink()` throttles after 3 attempts; `TwoFactorChallengePage::verify()`
  throttles after 5 failed codes and keys the counter by `userId|ip` (different users have
  independent counters).
- **`DefaultAdminSeederTest`** (`tests/Feature/Seeders/DefaultAdminSeederTest.php`)
  — 4 tests: seeder never sets `admin@123`; uses `PTAH_ADMIN_PASSWORD` when provided;
  idempotent on second run; generated random password is strong (len > 20, not a dictionary word).

### Added
- **Unit tests — `BaseRepository`** (`tests/Unit/Base/BaseRepositoryTest.php`)
  - 28 test cases: full CRUD contracts (`find` → null, `findOrFail` → exception, `update` →
    fresh record, `delete` → exception), all four `findBy` signatures (string, array, Closure,
    Builder), `findByIn`, `allQuery` skip/limit, `searchLike` operators `}` (≥) and `{` (≤),
    `whereIn` and `additionalQueries` params, `advancedSearch` sentinel guard, `updateBatch`,
    `createQuietly`/`updateQuietly` event-suppression, `replicate`
- **Unit tests — `BaseService`** (`tests/Unit/Base/BaseServiceTest.php`)
  - 9 test cases: `destroy` returns `false` for missing ID (vs `delete` which throws),
    `destroy` removes existing record, `show` returns model or null, `getData` routing
    (search → advancedSearch, searchLike → searchLike, default → findAllFieldsAnd),
    `limit`/`direction` respected, `relations` sentinel `'Relacao'` produces empty array
- **Unit tests — `HasCrud`** (`tests/Unit/Traits/HasCrudTest.php`)
  - 7 test cases: end-to-end delegation chain for every method (`all`, `paginate`, `find`,
    `findOrFail`, `create`, `update`, `delete`), confirming no method-name typos or
    signature drift against `BaseRepositoryInterface`
- **Unit tests — `HasAuditFields`** (`tests/Unit/Traits/HasAuditFieldsTest.php`)
  - 14 test cases (new: `nao_preenche_deleted_by_em_force_delete` regression guard for [M-2])
  - All boot events, `=== null` guard, guest behaviour, `NoFillable` tolerance,
    hard-delete safety, `forceDelete` safety, updating-event semantics and all three
    `createdBy` / `updatedBy` / `deletedBy` relationships
  - Dedicated stub tables in `tests/migrations/` (`has_audit_stubs`, `no_soft_delete_stubs`)
  - Self-contained stub models (`AuditableStub`, `AuditableNoFillableStub`,
    `AuditableHardDeleteStub`) defined inline in the test file
  - Uses `#[Test]` attribute (PHPUnit 11 style) instead of deprecated `@test` docblock
- **`CHANGELOG.md`** — full version history using Keep a Changelog format
- **`tests/migrations/2024_01_10_000002_create_base_crud_stubs_table.php`** — `items` stub
  table (id, name, status, amount, timestamps) shared by BaseRepository, BaseService and
  HasCrud test suites
- **`BaseRepository::findByBuilder()`** — clean replacement for `findBy(Builder)` branch,
  accepts `(Builder $query, string $column, string $operator, mixed $value)` with explicit
  operator as first-class param; old Builder-union in `findBy()` removed [M-3]
- **`BaseRepository::getTableColumns()`** — public memoised helper (static cache per table)
  that returns validated column names for the model's table; replaces the former private
  `tableColumns()` anonymous function; exposed via `BaseService::getTableColumns()` delegation
- **`BaseService::getData()`** — renamed from `getDados()` with English name and
  `orderByRaw("{$col} {$dir}")` replaced by `orderBy($col, $dir)` with column/direction
  whitelisting [C-1]; `getDados()` kept as `@deprecated` alias for backward compatibility
- **`BaseRepositoryInterface`** — 15 previously unspecified methods added: `advancedSearch`,
  `searchLike`, `findAllFieldsAnd`, `autocompleteSearch`, `allQuery`, `findBy` (updated
  signature), `findByBuilder`, `findByIn`, `updateBatch`, `updateQuietly`, `createQuietly`,
  `truncate`, `replicate`, `useIndex`, `buildSelectFields`, `getTableColumns`, `getKeyName` [A-1]
- **`FilterDTO`** — now extends `BaseDTO` and implements `fromRequest(Request): static` [B-2]

### Changed
- **`BaseRepository::mountFieldsToSelect()`** renamed to `buildSelectFields()` — now also
  intersects requested fields against real table columns preventing column enumeration [A-3];
  deprecated alias `mountFieldsToSelect()` retained for backward compat
- **`BaseRepository::getWherehas()`** renamed to `applyWhereHas()` (protected internal method)
- **`BaseRepository::findBy()`** — removed `Builder` union type and `$boolean` parameter;
  use `findByBuilder()` for Builder-based queries [M-3]
- **`BaseRepository::truncate()`** — now multi-DB aware: MySQL/MariaDB uses
  `SET FOREIGN_KEY_CHECKS = 0/1`, PostgreSQL uses `TRUNCATE … RESTART IDENTITY CASCADE`,
  other drivers (SQLite, etc.) use plain `DB::table()->truncate()` [C-4]
- **`BaseRepository::useIndex()`** — returns plain Builder on non-MySQL/MariaDB drivers
  instead of injecting a MySQL-only hint; no behaviour change on MySQL [C-5]
- **`BaseService::destroy()`** — race condition removed: single `findOrFail()` inside
  `try/catch ModelNotFoundException` replaces the separate `find()` + `if(model)` pattern [A-2]
- **`BaseService::resolveRelations()`** — filters requested relations against
  `$allowedRelations` whitelist when non-empty; default `[]` means all allowed (backward
  compat) [A-4]
- **`HasAuditFields` `deleted` event** — guard changed from
  `method_exists($model, 'getDeletedAtColumn')` to `method_exists($model, 'trashed') &&
  $model->trashed()`; `forceDelete()` now correctly skips the raw UPDATE since
  `deleted_at` was never set and `trashed()` returns false [M-2]
- **`PtahServiceProvider`** — `SchemaInspector` singleton registered only when
  `runningInConsole()`; avoids unnecessary reflection overhead in HTTP requests [B-5]
- **`PtahServiceProvider`** — `setLocale()` now opt-in via `ptah.force_locale` config
  (`PTAH_FORCE_LOCALE` env); does not override host app locale by default [A-6]
- **`PtahServiceProvider`** — `loadMigrationsFrom()` only called when at least one module
  is enabled; no migrations auto-loaded on fresh installs with no modules [C-6]
- **`PtahServiceProvider`** — demo route removed from `staging` environment; available only
  in `local` and `development` [B-3]
- **`SchemaInspector::fromDatabase()`** — replaced MySQL-only `SHOW FULL COLUMNS FROM`
  with portable `Schema::getColumns()` (Laravel 10.23+); works on MySQL, PostgreSQL and
  SQLite; `parseDbColumn()` updated to accept `array` instead of `object` [B-1]
- **`config/ptah.php`** — added `force_locale` key; removed `'admin@123'` default from
  `admin_password` (now `null` unless `PTAH_ADMIN_PASSWORD` is set) [M-6]; config section
  comments translated to English

### Fixed
- **`TestCase.php`** — replaced non-functional `loadLaravelMigrations()` (Testbench 10 ships
  an empty `laravel/database/migrations/` directory) with a dedicated test migration
  `tests/migrations/2014_10_12_000000_create_test_users_table.php`; this also unblocked
  `CompanyModelTest` which was silently broken for the same reason
- **`CompanyModelTest`** — migrated from deprecated `@test` docblock to `#[Test]` attribute
- **SQL injection** — user-supplied column names and operators in `searchLike`
  (`whereIn`, `additionalQueries`) and `findAllFieldsAnd` validated against
  `getTableColumns()` whitelist [C-2, C-3, M-4]

---

## [1.0.0-rc.5] — 2026-03-03

### Added
- **`HasAuditFields` trait** (`src/Traits/HasAuditFields.php`)
  — automated audit stamping via Eloquent boot events:
  - `created_by` / `updated_by` on `creating`; `updated_by` always refreshed on `updating`
  - `deleted_by` via raw SQL on the `deleted` event (after soft-delete commits — prevents
    stale stamp if the soft-delete transaction fails)
  - Guard: `=== null` check (not `empty()`), so user ID `0` is never overwritten
  - `Auth::id()` cached once per event callback — no double call
  - Relationships: `createdBy()`, `updatedBy()`, `deletedBy()` resolved from
    `config('auth.providers.users.model')`
  - Tolerant: silently skips columns absent from `$fillable`
- **All package models** now use `HasAuditFields`:
  `Company`, `Department`, `Role`, `Menu`, `CrudConfig`, `PtahPage`, `PageObject`,
  `UserRole`, `RolePermission`
- **`model.stub`** — `use HasAuditFields` added to every scaffolded model
- **`migration.stub` + `MigrationGenerator`** — `created_by`, `updated_by` (always) and
  `deleted_by` (when `--soft-delete`) injected automatically into every generated migration
- **`EntityContext`** — `fillableList()`, `castsList()`, `resourceFields()` include audit fields
- **`BaseCrud`** — belt-and-suspenders: explicit audit injection in `save()` and
  `deleteRecord()`; `bulkDelete()` uses `->each()` so Eloquent events fire per-record
- **ALTER migration** `2024_01_05_000000_add_audit_fields_to_ptah_tables` — idempotent
  `hasColumn()` guards for upgrading existing installations
- **`--api` combined mode** (`ptah:forge … --api`) — generates web + API artefacts in a
  single command; pre-existing Model is preserved and only `@OA\Schema` is injected
- **`--api-only`** flag retains the legacy "API only, no web views" behaviour
- **i18n** — full `en` / `pt_BR` support via `PTAH_LOCALE`; 58 translation keys in
  `ptah::ui`; `ptah:install --locale` option; `ptah-lang` publish tag

### Fixed
- Post-install retrospective: `--boost` crash, immutable migration guard, Horizon docs,
  accent-encoding in publish paths, idempotent `ptah:install`
- `HasAuditFields` — three bugs found in peer review:
  1. `empty($model->created_by)` → `=== null` (falsy-ID-0 false-positive)
  2. `deleting` event → `deleted` (atomicity: stamp only after soft-delete commits)
  3. `Auth::id()` called twice per callback → cached in `$userId`

### Changed
- All Portuguese code comments in `HasAuditFields` translated to English

---

## [1.0.0-rc.4] — 2026-01-15

### Added
- **API module** (`ptah:module api`) — installs `darkaonline/l5-swagger`, publishes
  `BaseApiController`, `BaseResponse`, `SwaggerInfo`; full Swagger annotations generated
  by `ControllerApiGenerator`
- **Subfolders** in `ptah:forge` — `ptah:forge Product/ProductStock` generates all
  artefacts under `Product/` sub-namespace; model key stored as `Product/ProductStock`
- **`surname=` / `label=` modifiers** in `--fields` — override the display label shown in
  the BaseCrud column header without renaming the database column
- **`BaseRepository`** and **`BaseRepositoryInterface`** modernised — type-safe generics,
  `getDados()` intelligent search (OR/AND/searchLike/pagination), three type-error fixes
- **`forge-page-header`** and **`forge-tab` / `forge-tabs`** Blade components (slot +
  Livewire dual-mode)
- **Company Switcher** — horizontal tab bar in navbar, session-aware, hidden when single
  company; `forge-input` eye-toggle for password fields
- **Laravel Boost integration** (`ptah:install --boost`) — installs `laravel/boost`
  automatically; `SKILL.md` and `guidelines/core.blade.php` rewritten with full SOLID
  rules, design tokens, and post-scaffold checklist
- **Livewire 4** support (`^3.0|^4.0`); Livewire component aliases migrated from
  `ptah::` namespace to `ptah-` prefix for compatibility

### Fixed
- `forge-auth` layout `@extends` `ParseError`
- Alpine.js duplicated with Livewire 3 in forge-auth layout
- Route `[login] not defined` accessing protected routes
- Profile page blank — component/view alignment
- `seedDefaultAdmin` and `seedDemoData` check `Schema::hasTable` before running
- `installBoost` now checks `vendor/laravel/boost` path instead of exit code
  (false-positive on Windows)
- `forge-pagination` uses `\/\` instead of custom `@props`
- `relationshipsUse()` generates `// TODO:` placeholder instead of guessing import path

### Changed
- Boxicons + FontAwesome set as the official icon standard; legacy SVG inline map removed
- `ptah:install` now creates the default company and admin user automatically

---

## [1.0.0-rc.3] — 2025-10-20

### Added
- **Módulo auth** — login, logout, forgot + reset password, 2FA (TOTP + e-mail OTP),
  recovery codes, active session management
- **Módulo menu** — `config` (default, zero migrations) and `database` drivers; tree with
  accordion groups; `MenuService` with cache + Observer invalidation; management screen
  `/ptah-menu`; Boxicons/FontAwesome CSS icon support
- **Módulo company** — full company + department CRUD, `CompanyService` with session
  context, multi-company support, `DefaultCompanySeeder` (idempotent)
- **Módulo permissions** — hierarchical RBAC: roles, pages, page objects (button/field/
  section/api/report/tab/link/page), `can_create/read/update/delete` + JSON `extra`;
  `PermissionService` with Redis tag cache; `RoleService` with MASTER bypass and batch sync;
  global helpers `ptah_can()`, `ptah_is_master()`, `ptah_permissions()`; `Permission` Facade;
  `@ptahCan` / `@ptahMaster` Blade directives; `ptah.can` middleware; 5 admin screens;
  `DefaultAdminSeeder` (idempotent)
- **`ptah:module`** command — interactive module activation with migration publish + run
- **Dark mode** — full `ptah-dark` class coverage across all Forge components and module
  views; OS preference detection; `ptah:install --demo` seeds demo data
- **Rule::unique demo** and `CompanyModelTest` (factory-based unit tests)
- `forge-stat-card` component; profile photo upload (`WithFileUploads`)
- Admin dropdown in navbar; `storage:link` in `ptah:install`

### Fixed
- Multiple dark mode inconsistencies in navbar dropdown, search fields, page-list
- `forge-button` light contrast in light mode
- Company list column/wire:model issues
- Menu active-state matching exact path + sub-routes only

---

## [1.0.0-rc.2] — 2025-07-08

### Added
- **Configurable JOINs** — LEFT / INNER JOINs declared in `CrudConfig`; full filter, sort
  and export support without Eloquent relationships
- **Renderer DSL** — `badge`, `pill`, `boolean`, `money`, `link`, `image`, `truncate`
  with `colsRenderer` / `colsRendererBadges`
- **Masks** — `cpf`, `cnpj`, `phone`, `cep`, `currency`, `percent` + `colsMaskTransform`
- **12 new validations** and **8 new renderers** in `FormValidatorService`
- **`colsMetodoCustom`** multi-param support + `colsMetodoRaw`
- **Cell styling** — `colsCellStyle`, `colsCellClass`, `colsCellIcon`, `colsMinWidth`;
  icon shown in both `<th>` header and cell
- **Nested relations** — `colsRelacaoNested` with dot notation (`category.parent.name`)
- **Bulk Actions** — multi-select, bulk delete (with Eloquent event safety), bulk export,
  custom bulk actions
- **Advanced search** — multiple criteria with AND/OR logic
- **Quick date filters** — today / week / month / quarter / year toggle
- **Saved filters** — named filter sets persisted per user
- **Column visibility** — per-user show/hide with drag-and-drop reorder and resize;
  preferences persisted via `UserPreferences`
- **Drag-and-drop column reorder + resize** with persistence
- **CrudConfig modal** — full in-app CRUD configurator (columns, actions, filters, styles,
  general, permissions tabs)
- **SearchDropdown** — select2-like UX in both form modal and filter panel
- **Totalizadores** — sum/count/avg/max/min per column
- **Export** — sync and async (Job-based) export to Excel/CSV
- **Broadcast / real-time** — Echo listener configurable per model
- **Display name** — configurable via `CrudConfig`
- **`forge-tabs`** dual-mode (slot + Livewire)

### Fixed
- Searchdropdown results not showing after Livewire re-render
- Custom filter field name mapping (`colsFilterType`, `defaultOperator`, `field_relation`)
- `getRowStyle` field key mismatch + silent skip for non-existent fields
- Action rendering wrong field names (`actionIcone` / `actionCall`)
- Stale Livewire snapshot in `boot()` — reload `crudConfig` on hydration
- `BaseCrud` layout `ParseError` (form_lbl token corrupted by PowerShell)

### Changed
- S/N string flags replaced with `true`/`false` booleans across all `ColDef` fields
- Scaffold: removed create/edit/show views and full CRUD controller — BaseCrud modal
  manages all mutations; controller is now a single-method index wrapper

---

## [1.0.0-rc.1] — 2025-04-01

### Added
- **Initial package structure** — `composer.json`, `PtahServiceProvider`, `ptah:install`
- **`ptah:forge` scaffolding generator** — single command creates Model, Migration, DTO,
  `RepositoryContract`, `Repository`, `ServiceContract`, `Service`, `Controller`,
  `StoreRequest`, `UpdateRequest`, `Resource`, `CrudConfig` (DB), view index with
  `@livewire`, web route — 14 artefacts total
- **FK auto-detection** — `_id` suffix + `unsignedBigInteger` type generates
  `foreignId()->constrained()->cascadeOnDelete()` in migration and `belongsTo()` in model
- **`BaseCrud` Livewire component** — dynamic table with sort, pagination, global search,
  modal create/edit with validation, soft delete + restore, company multi-tenant filter,
  `whereHas` parent-entity pre-filter, error recovery (clears corrupted preferences),
  cache with per-model invalidation
- **26 Forge Blade components** (`<x-forge-*>`) — layout dashboard, layout auth, sidebar
  with collapse/expand, navbar, button, input, select, modal, alert, badge, table,
  pagination, stat-card, and more — all Tailwind v4 + Alpine.js 3, dark mode ready
- **Design tokens** — `primary` `#5b21b6`, `success` `#10b981`, `danger` `#ef4444`,
  `warn` `#f59e0b` as CSS custom properties
- **`stubs/ptah/`** — publishable stubs for all generated artefacts
- **`config/ptah.php`** — full configuration reference
- **`phpunit.xml`** with Orchestra Testbench + SQLite `:memory:` setup
- **`docs/`** — `BaseCrud.md` (1 500+ lines), `AI_Guide.md`, `Modules.md`,
  `Company.md`, `Permissions.md`
- **`SKILL.md`** for Laravel Boost — SOLID layer rules, design tokens, scaffolding guide,
  BaseCrud JSON reference, Livewire input conventions
- **`ptah:install`** — publishes config + stubs + migrations, runs migrate, optionally
  seeds demo data (`--demo`), installs Boost (`--boost`)

---

[Unreleased]: https://github.com/jonytonet/ptah/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/jonytonet/ptah/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/jonytonet/ptah/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.5...v1.0.0
[1.0.0-rc.5]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.4...v1.0.0-rc.5
[1.0.0-rc.4]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.3...v1.0.0-rc.4
[1.0.0-rc.3]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.2...v1.0.0-rc.3
[1.0.0-rc.2]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.1...v1.0.0-rc.2
[1.0.0-rc.1]: https://github.com/jonytonet/ptah/releases/tag/v1.0.0-rc.1
