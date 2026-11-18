# Headless CRUD API

The BaseCrud over JSON, for a React, Vue or any other client (since 1.59.0).

It is **the same engine** as the Livewire screen — the same query, form, hooks,
permissions and guards — driven by a plain HTTP request instead of a Livewire
component. A screen configured for Livewire works through the API with the same
`crud_configs` row, and the two can serve the same model side by side.

> **Status.** Listing, form, delete/restore, preferences, searchdropdown
> lookups, image upload, duplicate, bulk and row actions, export and print
> are here. See [What is not here yet](#what-is-not-here-yet).

## Why

| | Livewire screen | API |
|---|---|---|
| A page change, 25 rows of 5,000 | ~70 ms | ~10 ms |
| Response of that interaction | ~440 KB (HTML + state) | ~11 KB (JSON) |
| State kept between requests | the component snapshot, sent back each time | none |

Measured in-process (no network, no gzip) on the package's test database; the
queries are the same in both. What the API skips is Livewire's own work: the
snapshot round trip, hydration and re-rendering the screen's HTML.

## Enable it

```env
PTAH_CRUD_API=true
```

```php
// config/ptah.php
'crud_api' => [
    'enabled'    => env('PTAH_CRUD_API', false),
    'prefix'     => 'ptah/api',
    'middleware' => ['web', 'auth'],   // session auth; ['api', 'auth:sanctum'] for tokens
],
```

Off by default: nothing changes for a project that does not turn it on.

## Register a screen

A screen exists for the API only after it is registered — in a service
provider's `boot()` or a routes file. **The client never names a model.**

```php
use Ptah\Crud\CrudScreen;

CrudScreen::register('products', \App\Models\Product::class);
```

Everything `@livewire('ptah-base-crud', [...])` takes in `mount()` is set here,
on the server:

```php
CrudScreen::register('my-appointments', \App\Models\Appointment::class)
    ->configRoute('my-appointments')                       // which screen config; default: the key
    ->lockedFilters(fn () => ['employee_id' => auth()->user()->employee_id])
    ->scope(\App\CrudScopes\TodayScope::class, fn () => ['tz' => auth()->user()->timezone])
    ->whereHas('customer', ['active', '=', 1])
    ->authorize(fn ($request) => $request->user()->can('see-appointments'));
```

- A closure is resolved **on every request**. Compute it from the session or
  the user — never from the request input.
- There is no client-held state to protect: the model, the config and the
  scope never travel to the browser.

## Endpoints

All under `/{prefix}/crud`, named `ptah.api.crud.*`.

| Method | Path | What |
|---|---|---|
| `GET` | `/{screen}/schema` | Columns, form fields, filters and what this user may do |
| `GET` | `/{screen}` | One page of the listing |
| `GET` | `/{screen}/{id}` | A record's form data |
| `POST` | `/{screen}` | Create — body `{"data": {...}}` |
| `PUT` | `/{screen}/{id}` | Update — body `{"data": {...}}`, only the fields being changed |
| `DELETE` | `/{screen}/{id}` | Delete (soft when the model soft-deletes) |
| `POST` | `/{screen}/{id}/restore` | Restore from the trash |
| `GET` | `/{screen}/create` | The defaults of a new record's form |
| `POST` | `/{screen}/{id}/duplicate` | A record copied into a new form — nothing is saved |
| `GET`/`POST` | `/{screen}/lookup/{field}` | Options of a searchdropdown — `q`, `context` (the form so far, for cascades), `filter=1` for the filter panel |
| `POST` | `/{screen}/bulk` | A bulk action — body `{"action", "ids", "state"}`; `delete`, `restore`, `force-delete` or a configured action |
| `POST` | `/{screen}/export` | Export or print — body `{"format": "excel"\|"pdf"\|"print", "state", "ids"?}`; answers a one-time `url` |
| `GET` | `/{screen}/preferences` | The user's choices for this screen |
| `PUT` | `/{screen}/preferences` | Change them — only the keys sent |
| `POST` | `/{screen}/saved-filters` | Save a set of filters under a name — body `{"name", "filters"}` |
| `DELETE` | `/{screen}/saved-filters` | Forget a named filter — body `{"name"}` |

### Listing parameters

| Parameter | Example |
|---|---|
| `page`, `per_page` | `?page=2&per_page=50` (capped by `ptah.crud.max_per_page`) |
| `sort`, `direction` | `?sort=name&direction=asc` — a column the screen does not sort by falls back to the default |
| `search` | `?search=acme` |
| `filters[field]` | `?filters[status]=active` |
| `operators[field]` | `?operators[amount]=>=` |
| `date_ranges[field_start]`, `date_ranges[field_end]` | `?date_ranges[created_at_start]=2026-11-01` |
| `quick_date` | `?quick_date=last7` |
| `trashed` | `?trashed=1` — only where the screen offers the trash |
| `restore` | `?restore=1` — reopen where the user left (below) |
| `remember` | `?remember=1` — save what this request applied (below) |

### Reopening where the user left

The Livewire screen reopens with the user's last search, filters, sort and
page. The API keeps that, as two explicit switches — so a request without
them still answers only what it was asked:

- **`?restore=1`** — applies what the user left saved and returns it in
  `state`, for the client to mirror in its controls and in the URL. Send it
  alone, when the screen opens.
- **`?remember=1`** — saves the state this request applied. Send it with every
  change the user makes, **with the complete state** (an omitted search or
  filter is saved as cleared; `?search=&filters=` clears explicitly).

```js
// on open
const first = await get(`/ptah/api/crud/products?restore=1`);
setState(first.state);

// on every change
await get(`/ptah/api/crud/products?${qs({ ...state, remember: 1 })}`);
```

It is the same preferences record the Livewire screen reads and writes — per
user, per screen and, with the company module, per company. A user who moves
between the two screens finds the same search, filters, page and columns.

### Preferences

```json
{
  "columns":       { "name": true, "amount": false },
  "column_order":  ["status", "name"],
  "column_widths": { "name": 240 },
  "view_mode":     "auto",
  "view_density":  "compact",
  "saved_filters": { "Arquivados": { "status": "archived" } },
  "search_history": ["acme"]
}
```

`PUT /{screen}/preferences` takes any of `columns`, `column_order`,
`column_widths`, `view_mode`, `view_density` and `clear_search_history: true`.
A key that is not a column of the screen is ignored. The schema and the
listing follow the column choices: a hidden column is in neither. The same
object comes in `schema.preferences`, so one call draws the screen.

### Listing response

```json
{
  "data": [
    {
      "id": 12,
      "cells": {
        "name":   { "value": "Acme",  "html": "Acme" },
        "amount": { "value": 1234.5,  "html": "R$ 1.234,50" }
      },
      "style": null
    }
  ],
  "meta": { "page": 1, "per_page": 25, "total": 312, "last_page": 13,
            "sort": "name", "direction": "ASC", "search": "", "trashed": false },
  "state": { "page": 1, "per_page": 25, "sort": "name", "direction": "ASC", "search": "",
             "filters": {}, "operators": {}, "date_ranges": {}, "date_operators": {},
             "quick_date": "", "trashed": false },
  "totals": [ { "field": "amount", "aggregate": "sum", "value": 98765.4 } ],
  "messages": []
}
```

- `value` is the raw value; `html` is what the Livewire screen shows for that
  cell — the same renderer, already escaped. Draw `html` to get every renderer
  for free, or `value` with a renderer of your own.
- Only the columns this user may see are present.

### Writes

```json
// 201 / 200
{ "ok": true, "id": 12, "errors": {}, "messages": [{ "text": "Salvo!", "level": "success" }] }

// 422
{ "ok": false, "id": null, "errors": { "name": "Nome is required." }, "messages": [] }
```

`messages` are the toasts the Livewire screen would have shown (a hook's
refusal, "cannot delete: has orders"…).

**Files.** An `image` field is uploaded in a multipart request: `data` as a
JSON string and the file under `files[field]`. Send an update with files as
`POST /{screen}/{id}` (PHP only parses a multipart body on POST). Size, real
MIME type and allowed extensions are checked by the same code as a Livewire
upload.

**Rows.** Each row of the listing also carries `actions` — the configured row
actions this user may run, already resolved: a `link` with its href (a
`javascript:` URL never leaves the server), an `action` (a Livewire action of
the config: its name, for your own handler) or `javascript`; with `icon`,
`color` and `confirm`.

**Export.** `state` is the listing state (search, filters, sort): the export
holds the rows the user is looking at. Open the returned `url` in the browser;
a large export that goes to the queue answers a message instead.

## The layout

| Method | Path | What |
|---|---|---|
| `GET` | `/{prefix}/menu` | The menu tree the Livewire sidebar draws |
| `GET` | `/{prefix}/me` | The signed-in user, the active company, the locale and the registered screens |

| Status | Meaning |
|---|---|
| `403` | The screen (or the action) is not allowed for this user |
| `404` | Unknown screen, or a record outside this screen's scope |
| `422` | Validation failed, or the action was refused with a reason |

## What holds, because it is the same code

- Company scope, `lockedFilters`, `CrudQueryScope` and the `whereHas`
  pre-filter on the listing **and** on show/update/delete/restore.
- `showCreateButton` / `showEditButton` / `showDeleteButton`, the Gate names of
  the config, the ptah ACL or the `gate` permission driver.
- Column permissions and `$hidden` attributes: a denied column is in neither
  the schema nor the cells.
- Select and searchdropdown values are checked against their options.
- Lifecycle hooks, mask transforms, audit fields, `GuardsDeletion`.

## What is not here yet

Spreadsheet import, kanban/calendar, record history, attachments, form
drafts, master/detail grids and advanced search.
Each is the same engine away; they arrive in the next increments, and the
React and Vue components are built on this contract.
