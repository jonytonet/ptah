# Changelog

All notable changes to `jonytonet/ptah` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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

[Unreleased]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.5...HEAD
[1.0.0-rc.5]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.4...v1.0.0-rc.5
[1.0.0-rc.4]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.3...v1.0.0-rc.4
[1.0.0-rc.3]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.2...v1.0.0-rc.3
[1.0.0-rc.2]: https://github.com/jonytonet/ptah/compare/v1.0.0-rc.1...v1.0.0-rc.2
[1.0.0-rc.1]: https://github.com/jonytonet/ptah/releases/tag/v1.0.0-rc.1
