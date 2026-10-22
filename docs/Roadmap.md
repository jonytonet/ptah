# Roadmap — the road to 2.0, and plugins after it

> Status: **planned, not started.** Nothing on this page exists yet. It records
> a decision taken in October 2026 (after 1.50.0) so that the steps before it
> are taken with it in mind.

## The decision

Ptah will open a **plugin ecosystem** — third-party (or per-client) Composer
packages that add field types, renderers, masks, modules and integrations —
**only after 2.0**, and 2.0 is defined as the release in which the core is
stable and reliable enough to promise a public API.

Why not now: a plugin depends on the core's internals staying put. Today the
BaseCrud traits and the form change from release to release, and that freedom
is what made fixes like 1.47–1.50 cheap. Opening plugins first would turn every
such fix into a breaking change for someone else's package. The promise of a
stable API is only worth its cost once the core can keep it.

Until then the rule stays: **a need of one client lives in that client's
project, through a contract** (`SearchDropdownSource`, `BulkActionHandler`,
lifecycle hooks, masks) — not in the core.

## Before 2.0 — work that helps either way

These pay off on their own and close no doors:

1. **Split the form partial** (`_modal-form.blade.php`, 1,000+ lines) into one
   partial per field type. A refactor-only release, no behaviour change, with
   browser tests over every field type before and after. Prerequisite for
   everything below: a new field type needs a place to plug in.
2. **Internal registries** for field types, cell renderers and filter
   operators, replacing the `match` blocks in the views and renderers. Used
   only by the core at first — the built-in types register themselves the way
   a plugin would.
3. **Mark the public API.** Decide, class by class, what is public (stable
   under semver) and what is internal, and say so in the code and here.
4. **Keep the docs honest** — `DocsMatchRuntimeTest` and `ColumnKeysTest` hold
   names to the code; extend them as the registries appear.

## 2.0 — what "stable and reliable" means

2.0 ships when:

- the public API above is marked and covered by contract tests;
- the registries exist and the core's own types go through them;
- the form is split and every field type has a browser test;
- deprecations accumulated in 1.x are removed in one go (e.g. the legacy
  config dialects the doctor already reports), with an upgrade guide and an
  `ptah:upgrade-check` that knows each of them.

## After 2.0 — the plugin ecosystem

- **Public registries:** `Ptah::fieldType()`, `Ptah::renderer()`,
  `Ptah::mask()`, `Ptah::filterOperator()`, called from a plugin's own
  ServiceProvider.
- **Third-party modules** in `ptah:module` (migrations, routes, menu entry).
- **A plugin skeleton command** that generates the package, its contract tests
  and its agent skill.
- **Security rule, non-negotiable:** a plugin extends Ptah by a contract
  registered in code. Configuration never names a class or method to run (the
  lesson of 1.49.0).

### Where plugins fit best

- **A Brazil kit (`ptah-br`)** — the first candidate: CEP field that fills the
  address, CNPJ lookup, Pix renderer, CPF/CNPJ/IE validation. The core's `br`
  mask preset would move there, kept in the core with a deprecation notice
  until the next major.
- **Private, per-client integrations** — e.g. an ERP plugin with a
  `SearchDropdownSource` for branches, an ERP-status renderer and domain AI
  tools, so the core never needs to know a specific ERP exists.
- **Channels and outputs** — WhatsApp/Telegram for CRUD notifications, extra
  export layouts, dashboard widgets.

**Not plugins:** permissions, auth, BaseCrud itself and the data layer stay in
the core — they are the security base and the contract the plugins build on.

## Impact on running systems

None while 1.x lasts: the registries are additive, the core's types keep their
names, and existing configs keep working unchanged. The form split is the one
step with real risk, which is why it ships alone. Moving the `br` masks out
happens only at a major version, with a transition period.
