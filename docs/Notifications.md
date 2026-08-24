# Notifications

The navbar bell as a real notification centre: ptah provides the **plumbing**
(storage, the bell UI, read/dismiss, the mount point) and your application
provides the **generators** — what is worth notifying is domain knowledge the
package cannot have.

Two independent layers. **Layer 1** is a ten-line mount point with no database
at all. **Layer 2** is the batteries-included centre (table, service, bell).
You can stop at Layer 1 and build your own UI, or take Layer 2 and only ever
push notifications.

- [The navbar slot (Layer 1)](#the-navbar-slot-layer-1)
- [Hiding the bell](#hiding-the-bell)
- [Enabling the notification centre (Layer 2)](#enabling-the-notification-centre-layer-2)
- [Pushing notifications](#pushing-notifications)
- [Deduplication](#deduplication)
- [Audiences](#audiences)
- [Company scope](#company-scope)
- [Config-driven CRUD notifications](#config-driven-crud-notifications)
- [The bell component](#the-bell-component)
- [Service reference](#service-reference)
- [Security model](#security-model)
- [Realtime (optional)](#realtime-optional)
- [Configuration reference](#configuration-reference)
- [What is not implemented yet](#what-is-not-implemented-yet)

---

## The navbar slot (Layer 1)

`config('ptah.navbar.notifications')` decides what sits in the bell position of
the navbar. It resolves to exactly **three states** through
`Ptah\Support\NavbarSlot`:

| Value | State | Rendered |
|---|---|---|
| unset, `null`, `''` | `default` | The current static bell — **byte-identical** to previous versions |
| `none`, `off`, `hidden`, `false`, `0` | `hidden` | **Nothing.** No element in the DOM |
| any other string | `component` | `@livewire(<that alias>)` |

```dotenv
# .env — pick one
# (absent)                                    → static bell, nothing changes
PTAH_NAVBAR_NOTIFICATIONS=none                # → no bell at all
PTAH_NAVBAR_NOTIFICATIONS=ptah-notification-bell   # → ptah's working bell (needs Layer 2)
PTAH_NAVBAR_NOTIFICATIONS=my-own-bell         # → your own Livewire component
```

The default is deliberately *unset*: upgrading ptah never changes what your
navbar renders and never makes it query a table you have not created.

**A typo cannot break your app.** If the configured alias is not a registered
Livewire component, the slot silently falls back to the static bell instead of
throwing `ComponentNotFoundException` from inside the layout — which would
take down every page that uses it.

### Using your own component

```php
// app/Providers/AppServiceProvider.php
use Livewire\Livewire;

public function boot(): void
{
    Livewire::component('my-own-bell', \App\Livewire\MyOwnBell::class);
}
```

```dotenv
PTAH_NAVBAR_NOTIFICATIONS=my-own-bell
```

That is the whole contract — the same one `ptah-company-switcher` uses. With
Layer 1 alone you own 100% of the UX and storage.

> **If you publish the package views** (`--tag=ptah-views-components`), your
> local copy of `forge-navbar.blade.php` does not receive the slot. Re-apply
> the `{{-- Notifications --}}` block from the package version.

---

## Hiding the bell

If your application has no notifications, the honest state is *no bell* — not
a decorative bell that does nothing:

```dotenv
PTAH_NAVBAR_NOTIFICATIONS=none
```

`false`, `off`, `hidden` and `0` do the same thing. The list exists because
`env()` converts the string `false` into a boolean, and no reasonable spelling
of "hide this" should land in the wrong state.

---

## Enabling the notification centre (Layer 2)

Layer 2 needs a table, so it is **opt-in and never migrates on upgrade**.
Updating ptah does not create tables, does not enable modules and does not add
queries. Two explicit steps, run by a human, per environment:

```bash
php artisan vendor:publish --tag=ptah-notifications
php artisan migrate --path=database/migrations/2026_08_23_000000_create_ptah_notifications_table.php
```

```dotenv
PTAH_MODULE_NOTIFICATIONS=true
PTAH_NAVBAR_NOTIFICATIONS=ptah-notification-bell
```

Then `php artisan config:clear` if you cache config.

**Graceful degradation is guaranteed, not hoped for.** With the module off,
nothing in this subsystem runs — zero queries. With the module on but the table
absent, every read returns a neutral value (`0`, `null`, an empty collection)
and no exception is thrown or logged: the bell renders empty instead of
breaking the page. A test in the suite proves the table does *not* appear from
the package's own migrations even with the module enabled.

### The table

`ptah_notifications` — one row **per user**, because read/dismissed state is
personal:

| Column | Notes |
|---|---|
| `user_id` | no FK, matching the rest of the package |
| `company_id` | nullable — `null` means "all companies" |
| `type` | `info` \| `success` \| `warning` \| `danger` — colour only |
| `category` | free grouping key for your app (`'vaccine'`, `'billing'`, …) |
| `title`, `body` | `body` is optional |
| `icon` | an icon class, e.g. `bx bx-bath` |
| `url` | deep link for "view details" |
| `action_label` | button label; defaults to the translated "View details" |
| `dedupe_key` | see [Deduplication](#deduplication) |
| `read_at`, `dismissed_at` | nullable; `dismissed_at` is the soft hide |

Indexes: `unique(user_id, dedupe_key)`, `index(user_id, read_at)` for the
unread count, `index(created_at)` for retention.

---

## Pushing notifications

Three helpers cover the common cases. All of them are no-ops returning `0`
when the module is off or the table is missing.

```php
// One person
ptah_notify($user, [
    'type' => 'warning',
    'category' => 'vaccine',
    'title' => "Rex's vaccine expires in 7 days",
    'body' => 'Second dose, due 2026-09-01.',
    'icon' => 'bx bx-injection',
    'url' => route('pets.show', $pet),
    'action_label' => 'Open record',
    'dedupe_key' => "vaccine:pet={$pet->id}:dose=2026-09-01",
    'company_id' => $pet->company_id,
]);

// Everyone holding a role
ptah_notify_role('Finance', [...], $companyId);

// Everyone (staff by default)
ptah_notify_all([...], $companyId);
```

`ptah_notify()` accepts a `User` model, an id, or `null` for the current user.
The other two resolve the audience and return **how many rows were written**.

Or inject the service where you prefer explicit dependencies:

```php
use Ptah\Services\Notification\NotificationService;

public function __construct(private readonly NotificationService $notifications) {}
```

**`user_id` is never read from the payload.** Only these keys are accepted:
`type`, `category`, `title`, `body`, `icon`, `url`, `action_label`,
`dedupe_key`, `company_id`. The recipient is always an argument resolved
server-side, so a payload built from request data cannot retarget a
notification at someone else.

---

## Deduplication

A generator that scans every hour must not create "Rex's vaccine expires" a
hundred times. Give it a **stable** `dedupe_key` and `push()` becomes
idempotent — same key, same user updates the existing row instead of adding
one:

```php
'dedupe_key' => "vaccine:pet={$pet->id}:dose={$dose->due_date->toDateString()}",
```

Two things worth knowing:

- **A `null` dedupe_key always creates a new row.** It is not treated as "a
  key that happens to be empty". (Naively upserting on a null key matches
  `dedupe_key IS NULL` and would overwrite the user's previous keyless
  notification — a real trap, deliberately avoided.)
- **Dedupe is per user.** The unique index is `(user_id, dedupe_key)`, so the
  same key for two people is two independent rows. If you want dedupe per
  *branch*, embed the company in the key itself:
  `"vaccine:company={$id}:pet={$petId}"` — `company_id` is intentionally not
  part of the unique index, because a nullable column in a unique index would
  let global notifications duplicate without limit.

---

## Audiences

A notification is one row per person, so targeting is just "resolve the
audience to user ids":

| Method | Audience |
|---|---|
| `toUser($id, $data)` | one person |
| `toRole('Finance', $data, $companyId)` | everyone with that **active** role (in that company, when given) |
| `toAll($data, $companyId, onlyStaff: true)` | everyone with any active role — i.e. the staff |
| `toAll($data, $companyId, onlyStaff: false)` | every user in the app |

`toRole`/`toAll(onlyStaff: true)` build on the permissions module's roles. With
that module **off** there are no roles to resolve, so they return `0` — use
`toUser` or `toAll(onlyStaff: false)` in that case.

Prefer `toRole` over `toAll(onlyStaff: false)`: the latter writes one row per
user in the database.

---

## Company scope

Everything follows ptah's active-company context, like the rest of the
package:

- `company_id = null` → visible in every company.
- `company_id = 7` → visible only while the user is working in company 7.

Reads filter with `company_id IS NULL OR company_id = <active>`, the same
semantics as role bindings.

---

## Config-driven CRUD notifications

Everything above is a manual API — you decide when to call `ptah_notify*()`.
This layer instead turns a model's `created`/`updated`/`deleted` events into
notifications **without writing a generator**: rules are configured entirely
through the CrudConfig editor's **Notifications** tab and stored in the same
`crud_configs.config` JSON your columns and actions already live in.

### Wiring a model

The model must opt in with a trait — without it, rules configured in the
editor are silently never fired (the editor itself warns about this):

```php
use Ptah\Traits\SendsCrudNotifications;

class Product extends Model
{
    use SendsCrudNotifications;
}
```

### Rule shape

Each entry in `notifications.rules` is a plain array with these 11 fields:

| Field | Type | Notes |
|---|---|---|
| `event` | string | `created` \| `updated` \| `deleted` |
| `audience` | string | `user` \| `role` \| `staff` — see [Audiences](#audiences) |
| `audienceValue` | string | user id (`audience: user`) or role name (`audience: role`); ignored for `staff` |
| `title` | string | required; template, truncated to 180 chars (the column width) with no `…` marker — an already-180-char title must not overflow under a strict SQL mode |
| `body` | string | optional; template |
| `url` | string | optional; template |
| `type` | string | `info` \| `success` \| `warning` \| `danger` |
| `category` | string | optional free grouping key, same as the manual API |
| `icon` | string | optional icon class |
| `actionLabel` | string | optional button label |
| `notifySelf` | bool | default `false` — see [Actor exclusion](#actor-exclusion) below |

A rule with an empty `title` (after resolving its template) is skipped —
nothing is queued.

### Placeholders and the allowlist

`title`, `body` and `url` may reference `%column%`. Only **savable** columns
that carry **no** `colsPermission` restriction are resolved — a placeholder
outside that allowlist resolves to an empty string, never to the raw value.
The model's primary key is added to the allowlist automatically (so
`/orders/%id%` works even when `id` is not itself a configured column) —
**unless the config explicitly restricts that exact column**, in which case
the restriction wins and `%id%` resolves empty too.

That last clause is a fixed bypass, not a hypothetical: forcing the primary
key into the allowlist unconditionally meant an admin who tagged `id` with a
`colsPermission` would still see `%id%` resolved for every recipient — the
one placeholder guaranteed to always work was also the one the restriction
could never actually gate.

### Precedence

A model can have more than one `CrudConfig` row (a global one with
`route === ''`, plus route-scoped overrides). The dispatcher picks the
**first** row — ordered by `route` ascending, so the global row always sorts
first — whose `notifications.rules` is non-empty. Rules from other rows for
the same model are not merged in.

### Audiences

Same three audiences as the manual API, routed through the same service
methods — including where each returns zero:

| `audience` | Routes to | Returns 0 when |
|---|---|---|
| `user` | `NotificationService::toUser()` | `audienceValue` is not a positive integer |
| `role` | `NotificationService::toRole()` | the permissions module is off, the role does not exist, or no user holds it as an **active** assignment of an **active** role in the record's company |
| `staff` | `NotificationService::toAll(onlyStaff: true)` | the permissions module is off, or nobody holds an active role in the record's company |

### Delivery: queued, after commit

Each matching rule is dispatched as `Ptah\Jobs\SendCrudNotificationJob` with
`$afterCommit = true` — a bulk delete wrapped in `DB::transaction()` only
inserts notifications once the transaction actually commits (and a rolled
back transaction inserts none). `afterCommit` also works correctly with the
`sync` queue driver, which honours it just like a real queue connection.

For a `deleted` event, every placeholder is resolved from the row's
in-memory attributes **before** queueing — a deleted record notifying "Order
#42 removed" still has `#42` available even though the row is already gone
by the time the job would otherwise run.

### Actor exclusion

By default, the user who triggered the event (`Auth::id()`) is **excluded**
from the audience — you rarely want to tell someone about their own action.
Set `notifySelf: true` on the rule to include them. Without an authenticated
actor (a console command, a queued import), nobody is excluded.

### Why there is no `dedupe_key` here

The manual API's `dedupe_key` (see [Deduplication](#deduplication)) is
deliberately **not** exposed on CRUD rules. An `updated` rule firing twice
would `updateOrCreate()` the same row — which preserves `read_at`. If the
recipient already read the first version, the second `update()` would
silently arrive already marked read, with no badge, no unread count change,
nothing to notice. CRUD rules always `create()` a fresh row per event
instead, so every occurrence is independently visible.

### Reentrancy

A rule's own side effects (a lifecycle hook that saves a related model
which also uses `SendsCrudNotifications`) cannot trigger a **second**,
nested dispatch while the outer one is still running — a process-wide latch
suppresses it. Only the outermost event's rules ever fire for a single
request.

---

## The bell component

Alias `ptah-notification-bell`, registered only when the module is enabled.

- Unread **count badge**, hidden entirely at zero.
- Dropdown with the active notifications: colour and icon by `type`, title,
  body, relative time.
- Clicking an item marks it read and navigates to its `url`. Items are real
  `<a href>` elements, so copy-link and open-in-new-tab work; the primary
  click goes through the server. An item **without** a `url` is not clickable.
- Footer: "Mark all as read" and "View all" (the latter appears once the
  history page exists — see [what is not implemented yet](#what-is-not-implemented-yet)).
- Empty state, `Esc` and click-away close, `aria-expanded` / `aria-haspopup`.
- Dark mode through `--ptah-*` tokens, so it follows the user's chosen theme
  and appearance axes with no extra work.

### Polling cost

The bell polls so the badge updates itself. What that actually costs:

- The **list query only runs while the dropdown is open**. A closed bell
  polling costs one indexed `count()`.
- Livewire already throttles polling in background tabs to roughly one request
  per 20 minutes, so idle tabs are cheap.
- The interval is configurable, and `none` turns polling off completely:

```dotenv
PTAH_NOTIFICATIONS_POLL=60s   # default
PTAH_NOTIFICATIONS_POLL=300s  # calmer
PTAH_NOTIFICATIONS_POLL=none  # no polling; the badge updates on navigation
```

---

## Service reference

`Ptah\Services\Notification\NotificationService` (a singleton; also reachable
as `app(NotificationService::class)`).

```php
push(int $userId, array $data): ?Notification          // idempotent by dedupe_key
pushMany(iterable $userIds, array $data): int
toUser(int $userId, array $data): int
toRole(string $roleName, array $data, ?int $companyId = null): int
toAll(array $data, ?int $companyId = null, bool $onlyStaff = true): int
unreadCount(?int $userId = null, ?int $companyId = null): int
list(?int $userId = null, ?int $companyId = null, int $limit = 20): Collection
paginate(?int $userId, ?int $companyId, array $filters, int $perPage = 20): LengthAwarePaginator
markRead(int $id, ?int $userId = null): bool
markAllRead(?int $userId = null, ?int $companyId = null): int
dismiss(int $id, ?int $userId = null): bool
purgeRead(int $days = 30, int $chunk = 1000, bool $dryRun = false): int
tableExists(): bool
static safeUrl(?string $url): ?string
```

`Ptah\Models\Notification` carries the scopes `unread()`, `active()`,
`forUser($id)` and `forCompany($id)` if you need to query directly.

---

## Security model

A notification belongs to a person, so the gate is **ownership, never a
permission**. There is no page object to register and nothing to grant — a
`ptah_can` check here would only create a way to hide someone's own
notifications from them.

- `markRead`, `dismiss` and `markAllRead` carry the owner **in the SQL WHERE
  clause**. Passing someone else's id changes nothing and returns `false` —
  not an error message, not a silent success.
- `url` is supplied by your application and ends up in an `href` and a
  redirect, so `javascript:`, `data:` and `vbscript:` schemes are stripped at
  **render and redirect time** — which also neutralizes rows a careless
  generator already wrote.
- `title`, `body` and `icon` are always escaped; `type` maps through an
  allowlist, so it can only pick a colour, never inject a class.

---

## Realtime (optional)

**Nothing above requires Reverb, Echo, or any websocket server.** The bell's
`wire:poll` (default `60s`, see [Polling cost](#polling-cost)) is the
delivery mechanism out of the box: push a notification (manually or via a
CRUD rule) and it reaches the recipient on the next poll — no extra setup,
no package to install. This is what every test in the suite runs against.

Enabling `ptah.notifications.broadcast` layers near-instant delivery on top,
without replacing the poll — it stays as the fallback in both configurations.

### What ptah does for you

With the flag on, `NotificationService::push()` (the single funnel both the
manual API and CRUD rules go through) fires
`Ptah\Events\PtahNotificationCreated`, and `PtahServiceProvider::boot()`
registers the private channel authorization for you:

```php
Broadcast::channel('ptah.notifications.{userId}', fn ($user, $userId) =>
    (int) $user->getAuthIdentifier() === (int) $userId
);
```

You do **not** need to touch `routes/channels.php` yourself. The bell
component only registers its Echo listener when the flag is on **and** a
user id can be resolved — a guest never gets one.

The broadcast payload deliberately carries only the notification's `id` —
never `title`/`body` — because it travels through the websocket server. The
bell already re-reads the row (scoped to the authenticated user) once
triggered.

**A broken broadcast connection never blocks anything.** If dispatching the
event throws — a missing driver SDK, a misconfigured connection — it is
caught and logged (`Log::warning`); the notification row was already
written before broadcasting was even attempted, and the poll keeps working
regardless.

### Host setup checklist

1. `composer require laravel/reverb`
2. `php artisan install:broadcasting` (creates `routes/channels.php`,
   `config/broadcasting.php` entries, and wires the Reverb server — ptah's
   own channel registration above coexists with it; you do not need to add
   `ptah.notifications.*` to `channels.php` yourself)
3. `npm install --save-dev laravel-echo pusher-js` then `npm run build`
   (Reverb speaks the Pusher protocol, so the `pusher-js` client is required
   even without a Pusher account)
4. `.env`:
   ```dotenv
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=...
   REVERB_APP_KEY=...
   REVERB_APP_SECRET=...
   REVERB_HOST=...
   REVERB_PORT=443
   REVERB_SCHEME=https
   PTAH_NOTIFICATIONS_BROADCAST=true
   ```
5. Run the Reverb server (`php artisan reverb:start`, or your process
   manager of choice) alongside your queue worker — `PtahNotificationCreated`
   is queued like any other broadcast event.

Any step skipped simply means realtime delivery does not activate; the
database notification and the poll are unaffected either way.

---

## Configuration reference

```php
// config/ptah.php
'navbar' => [
    'notifications' => env('PTAH_NAVBAR_NOTIFICATIONS'),   // 3 states, see above
],

'notifications' => [
    'enabled' => env('PTAH_MODULE_NOTIFICATIONS', false),
    'poll' => env('PTAH_NOTIFICATIONS_POLL', '60s'),       // or 'none'
    'dropdown_limit' => 20,
    'retention_days' => 30,
    'broadcast' => env('PTAH_NOTIFICATIONS_BROADCAST', false), // see Realtime (optional)
],
```

Both blocks are **top level** on purpose. Laravel merges package config
shallowly, so a new *nested* key would never reach an application that already
published `config/ptah.php` — exactly the installations this design protects.

If you cache config, run `php artisan config:clear` after upgrading, or the
new keys stay invisible.

---

## What is not implemented yet

Honest status, so nothing here is a promise:

| Piece | Status |
|---|---|
| Navbar slot with 3 states (Layer 1) | **done** |
| Table, model, service, helpers | **done** |
| Bell component (badge, dropdown, read/dismiss, poll) | **done** |
| Config-driven CRUD notifications (editor tab, rules, placeholders) | **done** |
| Realtime delivery (Reverb/Echo), opt-in via `ptah.notifications.broadcast` | **done** — see [Realtime (optional)](#realtime-optional) |
| "View all" history page + route | **not yet** — the footer link stays hidden until it exists |
| `ptah:notification-prune` retention command | **not yet** — `purgeRead()` exists on the service and can be scheduled from your app in the meantime |
| Email delivery, per-user preferences | **not planned** |

Generating notifications is your application's job: scan your own sources on a
schedule and call `ptah_notify*` with a stable `dedupe_key`. The package
deliberately knows nothing about vaccines, invoices or appointments.
