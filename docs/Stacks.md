# Stacks: Livewire, React or Vue

Since 1.59.0 ptah draws its screens with the stack you choose. The back end is
the same in all three — models, services, repositories, screen configs
(`crud_configs`), permissions, hooks, `ptah:config`, `ptah:check`, the MCP
server. What changes is what draws the screen.

| | Livewire (default) | React | Vue |
|---|---|---|---|
| Screen | `@livewire('ptah-base-crud')` | `<PtahCrud screen="…" />` | `<PtahCrud screen="…" />` |
| Talks to the server through | the Livewire component | the [headless API](HeadlessApi.md) | the [headless API](HeadlessApi.md) |
| Front end | Blade + Alpine | React 19 | Vue 3 |

React and Vue use the same engine as Livewire (see
[HeadlessApi.md](HeadlessApi.md)): a screen configured for one works in the
others, and a user's preferences — columns, filters, the page they left — are
shared.

## Install

```bash
php artisan ptah:install                 # Livewire, as always
php artisan ptah:install --stack=react
php artisan ptah:install --stack=vue
npm install && npm run build             # or npm run dev
```

For React or Vue the installer also:

- sets `PTAH_STACK` and `PTAH_CRUD_API=true` in `.env`;
- writes the entry file — `resources/js/ptah-app.jsx` (React) or
  `resources/js/ptah-app.js` (Vue) — and `routes/ptah-screens.php`;
- prepares `vite.config.js`: the framework plugin, the entry in the Laravel
  plugin's `input`, the `@ptah` alias and `dedupe`. Only what is missing; a
  config it cannot read is left alone and the lines to add are printed;
- adds the packages to `package.json` (a version you already pin is kept).

Running it again is safe. Changing stack later is running it with the other
`--stack`.

The front-end sources live in the Composer package
(`vendor/jonytonet/ptah/resources/js`) and are compiled by your Vite through
the `@ptah` alias: there is one version, and `composer update` updates both
sides.

## Generating screens

`ptah:forge` follows `ptah.stack`:

```bash
php artisan ptah:forge Catalog/Product --fields="name:string,price:decimal(10,2)"
```

Model, migration, service, repository, DTO, requests, controller, route, menu
entry and screen config are the same in every stack. In React/Vue, instead of
the Livewire view:

- `resources/views/product/index.blade.php` is a page that mounts the front
  end on the screen (`@include('ptah::stack.app', ['screen' => 'product'])`);
- `routes/ptah-screens.php` gets `CrudScreen::register('product', 'Catalog/Product');`.

The screen key is the page's path, so the screen config of that path is the
one loaded — exactly how the Livewire screen picks it.

## Using the components yourself

The generated page is only a starting point. The components work in any page
of yours — an Inertia page, a router view, a tab:

```jsx
// React
import { PtahCrud } from '@ptah/react';
import '@ptah/ptah-ui.css';

<PtahCrud screen="product" onAction={(name, row) => …} />
```

```vue
<!-- Vue -->
<script setup>
import { PtahCrud } from '@ptah/vue';
import '@ptah/ptah-ui.css';
</script>

<template>
    <PtahCrud screen="product" :on-action="(name, row) => …" />
</template>
```

| Prop | |
|---|---|
| `screen` | The key of `CrudScreen::register()`. Required |
| `locale` | `pt_BR`, `en`… Defaults to `<html lang>` |
| `title` | Replaces the screen's title |
| `api` | A `PtahApi` of your own: another base URL, a token, a custom `fetch` |
| `remember` | `false` stops reopening where the user left (default `true`) |
| `onAction` | Runs a row action of type `action` (a Livewire action in the config) |
| `controller` | A `CrudController` of your own, to drive the screen from outside |

Everything the screen does lives in `CrudController`
(`@ptah/core`), which has no framework in it — build a screen of your own
shape over it and keep the behaviour:

```js
import { CrudController } from '@ptah/core';

const crud = new CrudController('product');
await crud.init();
crud.subscribe(() => render(crud.getState()));
await crud.setSearch('acme');
```

`<PtahLayout>` draws the menu of the server (the same tree and permissions as
the Livewire sidebar) and the signed-in user; swap it for your own shell.

## Navigation

`mountPtah` makes the stack pages behave like one app: moving between them
does not reload the document. (The Livewire layout has the same feeling as an
opt-in, through Livewire's own `wire:navigate` — see
[Configuration.md → Single-page navigation](Configuration.md#single-page-navigation-of-the-livewire-layout-forgespa_navigation).) A click on a link inside `#ptah-app` (the
sidebar, the administration gear, the breadcrumb, a dashboard widget, a link
in a cell), a row link, the "jump to a screen" search, a notification's
"open" and the redirect of a save all go through the navigator
(`PtahNavigator`, `@ptah/core`):

1. it fetches the URL as HTML (header `X-Ptah-Navigate: 1`, never cached) and
   reads the `#ptah-app` of the answer — every stack page ends in it, ptah's,
   `ptah:forge`'s and yours, so there is no server contract to keep in step;
2. the current page stays on screen, with a thin bar at the top of the
   content, until the next one has its first data — for a CRUD screen its
   schema and first listing (at most 8 s, then its skeleton);
3. it swaps only the content: the layout stays mounted (sidebar scroll and
   open groups, collapsed state, bell, assistant, company switcher). The
   page mounts fresh, the URL, `document.title`, the active menu item and
   the CSRF token follow, focus goes to the page's heading and its title is
   announced to screen readers.

Back and forward reuse the page kept in the history entry (no request) and
restore the scroll. Hovering, focusing or touching a menu link fetches its
page ahead (one at a time, not on a data-saving connection), so the click is
instant; `[data-ptah-prefetch]` adds a link of yours.

**A full document load happens** for: a link with `data-ptah-reload` (or
inside an element with it), `target` other than `_self`, `download`,
`rel="external"`, a modifier key or another mouse button; another origin; a
file (`.pdf`, `.csv`…); and whenever the answer is not a stack page of the same
kind — an error, a 401/419 (the session ended: the server sends to the
login), a file, a redirect elsewhere, a host's own Blade or Livewire page,
or a sign-in page while signed in (and the reverse). Signing in, logging out
and switching company always reload everything.

**A router of your own** (Inertia, React Router, Vue Router):
`mountPtah(el, { onNavigate: (url) => router.visit(url) })` — ptah then
intercepts nothing and every ptah link goes through your function. `mountPtah`
returns the function that unmounts it and gives the links back;
`mountPtah(el, {}, { navigate: false })` keeps every link a document load.

**The first paint.** The shell page embeds what the layout needs
(`data-boot`: the answers of `GET {api}/menu`, `{api}/me` and, when shown, the
company switcher — built by `Ptah\Support\StackBoot`), so after F5 the
sidebar, the gear, the bell, the switcher and the account are there in the
first frame, with no request. Not for a visitor or a sign-in page, and not
in the answers fetched by the navigator (the layout is already on screen).

**Kept listings (`instant`).** `<PtahCrud instant>` (`ControllerOptions.instant`,
on in the pages of `PtahApp`, off by default) draws the last listing this tab
saw of the screen at once — busy — and replaces it with the server's answer,
so a screen you come back to never shows a loading state. Kept in
`sessionStorage` (this tab, gone when it closes; never `localStorage`) under
the signed-in user and company; a listing over ~200 KB is not kept; logging
out and switching company forget them all; a screen the server now refuses
(403/404) drops its copy and shows the refusal, never the old rows. The
first visit of a screen draws a skeleton of its shape (`PtahCrudSkeleton`).

### One request per screen

The page of a screen carries what the screen would ask the API first — its
schema, its first listing, the description of its filters and whether the
user gets the config button — in `data-answers` of `#ptah-app`. The front end
takes each answer from there once, so opening a screen (a document load or a
move in place) is the single request of its page.

This happens only when the page is as guarded as the API: the route of the
page must have every middleware listed in `ptah.crud_api.middleware`
(`['web', 'auth']` by default — the routes `ptah:forge` writes have both). A
page that lacks one, or whose URL has a query string, embeds nothing and the
front end asks the API as before (the screen works, with more requests).
`PTAH_CRUD_API_EMBED=false` turns the embedding off.

### Editing a record

A click on *Edit* shows a loading over the screen at once and the modal opens
with the record in it. The record is asked for ahead when the pointer rests on
the edit button of a row (or the button takes the focus), so the click that
follows usually opens the form without waiting — `crud.intendEdit(id)` and
`crud.leaveEdit()` do the same for a button of your own.

## Styling

`ptah-ui.css` is self-contained (no Tailwind needed). It follows `--ptah-primary`
/ `--color-primary` when your page defines them, and switches to dark under an
ancestor with `.dark`, `.ptah-dark` or `[data-theme="dark"]`.

Cells arrive rendered by the server (badges, pills, progress bars…) with the
classes of the Livewire screen, so the generated page also loads your
`resources/css/app.css`.

## Sign-in

The API uses your session (`ptah.crud_api.middleware`, `['web', 'auth']` by
default). ptah's own auth module (`ptah:module auth`) keeps working in the
React and Vue stacks — its login, 2FA and profile pages are served as before —
and so does any auth of your own, including a Laravel starter kit's.

## Administration screens

With the modules on, ptah's own screens are served in your stack at the same
URLs and route names as in Livewire — nothing to register:

| URL | Route name | Component |
|---|---|---|
| `/ptah-companies` | `ptah.company.index` | `PtahCompanies` |
| `/ptah-departments` | `ptah.acl.departments` | `PtahDepartments` |
| `/ptah-roles` | `ptah.acl.roles` | `PtahRoles` |
| `/ptah-pages` | `ptah.acl.pages` | `PtahPages` |
| `/ptah-users-acl` | `ptah.acl.users` | `PtahUserPermissions` |
| `/ptah-audit` | `ptah.acl.audit` | `PtahAudit` |
| `/ptah-permission-guide` | `ptah.acl.guide` | `PtahPermissionGuide` |
| `/ptah-menu` | `ptah.menu.manage` | `PtahMenuAdmin` |
| `/ptah-settings` | `ptah.settings` | `PtahSettings` |

The locks are the ones of the Livewire screens (`ptah.master` for the access
control screens, `ptah_can_manage_structure()` for companies, menu and
settings), applied to the page and again to every call of `ptah/api/admin/*`.

`PtahLayout` reads from the server what the top bar offers this user: the
company switcher (company module), the notification bell
(`ptah.notifications.enabled`) and, in the account menu, the administration
links the user may open. `PtahCompanySwitcher`, `PtahNotificationBell` and
`PtahExportsPanel` are exported for a layout of your own.

## Realtime (websocket)

A screen whose config has `broadcast` on, and the notification bell with
`ptah.notifications.broadcast`, update by themselves — as on Livewire, and on
the same channel and event (`Ptah\Support\BroadcastListener`). The screen
reloads its listing quietly (a burst of events is one reload); the bell
refreshes its count.

ptah does not ship a websocket client. Set up Laravel Echo in your app
(Reverb, Pusher, Ably…) and leave it on `window.Echo` **before** mounting:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({ broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY, /* … */ });

mountPtah(document.getElementById('ptah-app'));
```

Without `window.Echo` nothing is subscribed and the screens work as before.
A `private` or `presence` channel is authorised by your
`routes/channels.php`, exactly as for the Livewire screen. `listenBroadcast()`
is exported for screens of your own.

## What differs from the Livewire stack

Every feature of the Livewire screens is in the React and Vue stacks. Known
differences:

- Background export (`asyncExport`) and its "Limit" badge are not in the
  export menu.
- An image field uploads with the save, not when the file is picked.
- The global toast host of the Livewire layout has no single equivalent: each
  screen draws its own toasts.
- The inside of the AI chat panel and a few fields of the administration
  screens (required mark, show-password eye) are plainer.
