# ptah — guide for AI agents (and busy humans)

You are reading the source of `jonytonet/ptah`, a database-driven low-code
CRUD engine for Laravel (Livewire 4, Tailwind 4). This file exists to keep
your context small: it tells you which single document answers each kind of
question, and which mistakes waste the most tokens and diffs.

## The one rule that saves the most work

**Configure before you code.** A CRUD screen here is a JSON row in
`crud_configs`, not a hand-written component. Search, filters, sorting,
pagination, export, bulk actions, badges, row styles, relations, master-detail,
group breaks, totalizers, column permissions, audit fields, per-event
notifications, a responsive card layout — all of it already exists and is
switched on by configuration (`php artisan ptah:config` or the visual editor).
If you are about to write a Livewire listing, a table Blade view, or an
Eloquent query inside a component: stop — the ready-made path is
`ptah:forge` + `ptah:config` for screens, and `BaseRepository`/`BaseService`
via contracts for data access.

`php artisan ptah:config "App\Models\X" --list` shows what a screen already has.

## The one rule that keeps screens on-theme

Users switch themes at runtime (6 axes: light/dark tone, accent, text weight,
density, font size). **Every color must resolve through a CSS custom property**
— `<x-forge-*>` component props, `bg-primary`-style semantic classes, or
`var(--ptah-*)` tokens. Fixed-palette classes (`bg-white`, `bg-slate-*`,
`text-gray-*`, `dark:` variants) and hex literals produce elements that stay
white when the user picks the *papel* tone. Full contract and conversion
table: `docs/CustomScreens.md`.

## Where to read what (smallest document first)

| Question | Read | Size |
|---|---|---|
| How do I build anything here? (start) | `resources/boost/skills/ptah-development/SKILL.md` | ~1.4k lines, sectioned — read only the section you need |
| Config flag / column type / CLI syntax | `docs/Commands.md` | reference |
| BaseCrud runtime behaviour in depth | `docs/BaseCrud.md` | large — search it, don't read it whole |
| Repository / Service / DTO layer | `resources/boost/skills/ptah-data-layer/SKILL.md`, then `docs/BaseLayer.md` | small |
| Scaffolding a new entity end-to-end | `resources/boost/skills/ptah-scaffold/SKILL.md` | small |
| Custom (non-CRUD) screens, tokens, theming | `docs/CustomScreens.md` | medium |
| Permissions / ACL / column permissions | `docs/Permissions.md` | large |
| Notifications (bell, per-CRUD events, Reverb) | `docs/Notifications.md` | medium |
| Deliberate gaps and sharp edges | `docs/KnownLimitations.md` | medium — check before filing "bugs" |
| Test suite conventions, browser tests | `docs/Testing.md` | small |

## House rules that are enforced by tests

This repo guards its own coherence: docs, enums, schemas, translations and CSS
are pinned to the code by tests that fail on drift. Consequences for you:

- If you change behaviour, grep `tests/` for the doc-guard that pins it
  (`*DocTest`, `*ParityTest`, `*BaselineTest`, `*CoverageTest`) and update the
  doc in the same change — the suite will tell you if you forget.
- Never weaken a guard to make it pass; the guard is usually encoding a
  production incident.
- Source-scanning guards strip comments first — when adding one, copy that
  pattern (`token_get_all` skipping comments, or regex-stripping `/*…*/`),
  because a guard that trips on its own explanatory prose is this repo's most
  recurrent false failure.
- Text shown to users is translated in `resources/lang/{en,pt_BR}/ui.php` —
  both, always; parity is tested.
- On plain HTML tags use `title="{{ __('…') }}"`, never `:title="__('…')"`
  (that is an Alpine expression there, not Blade — also tested).

## Running checks

```bash
php vendor/bin/phpunit          # full suite (~3 min)
php vendor/bin/phpstan analyse  # static analysis — keep at zero errors
php vendor/bin/pint --dirty     # formatting — run before finishing
```

`ptah:*` artisan commands run in a HOST app that installed ptah, never inside
this package directory (there is no `artisan` here).
