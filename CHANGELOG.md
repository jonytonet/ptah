# Changelog

All notable changes to `jonytonet/ptah` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- **Unit tests — `HasAuditFields`** (`tests/Unit/Traits/HasAuditFieldsTest.php`)
  - 13 test cases covering all boot events, the `=== null` guard, guest behaviour,
    `NoFillable` tolerance, hard-delete safety, updating-event semantics and all three
    `createdBy` / `updatedBy` / `deletedBy` relationships
  - Dedicated stub tables in `tests/migrations/` (`has_audit_stubs`, `no_soft_delete_stubs`)
  - Self-contained stub models (`AuditableStub`, `AuditableNoFillableStub`,
    `AuditableHardDeleteStub`) defined inline in the test file
  - Uses `#[Test]` attribute (PHPUnit 11 style) instead of deprecated `@test` docblock
- **`CHANGELOG.md`** — full version history using Keep a Changelog format

### Fixed
- **`TestCase.php`** — replaced non-functional `loadLaravelMigrations()` (Testbench 10 ships
  an empty `laravel/database/migrations/` directory) with a dedicated test migration
  `tests/migrations/2014_10_12_000000_create_test_users_table.php`; this also unblocked
  `CompanyModelTest` which was silently broken for the same reason
- **`CompanyModelTest`** — migrated from deprecated `@test` docblock to `#[Test]` attribute

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
