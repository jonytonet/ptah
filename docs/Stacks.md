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

## What the React and Vue screens do not have yet

The visual config editor, spreadsheet import, kanban/calendar, record history,
attachments, form drafts, master/detail grids, advanced search and the AI
chat. They remain available in the Livewire stack; configure a screen with
`ptah:config`, the JSON files or the MCP server meanwhile.
