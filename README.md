<div align="center">
  <img src="docs/logo.png" alt="Ptah — Enterprise Structure. Startup Speed." width="420" />

  <h3>Enterprise Structure. Startup Speed.</h3>

  <p>
    Generate your system's entire structure in minutes — you focus only on the business logic.<br>
    Built for AI agents: the architecture comes ready, so the agent spends a fraction of the tokens.
  </p>
</div>

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012%2B-red)](https://laravel.com)
[![Livewire Version](https://img.shields.io/badge/Livewire-4-blueviolet.svg)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-06b6d4)](https://tailwindcss.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## What is Ptah?

**Ptah** is a Laravel package that combines SOLID scaffolding, ready-made visual components and a dynamic CRUD system in a single installation. With one command you generate the entire structure of an entity — model, migration, DTO, repository, service, controller, requests, resource, Livewire view and routes — ready to use from the very first `php artisan serve`.

| Pillar | What it delivers |
|---|---|
| **Ptah Forge** | 26 Blade components (`<x-forge-*>`) with Tailwind v4 + Alpine.js — layout, sidebar, navbar, modal, table, forms and much more |
| **ptah:forge** | SOLID scaffolding generator: an entire entity in seconds, with layered architecture and customisable stubs |
| **BaseCrud** | Fully generated Livewire screen — filters, create/edit modal, soft delete, export and per-user preferences, all configurable via the database |

---

> **In a hurry?** Follow the **[Quick Start →](docs/QuickStart.md)** — one entity, SQLite, working CRUD in 5 minutes.

## ⚡ The full structure in minutes — far fewer tokens with AI

> **What Ptah generates in minutes:** the complete, layered structure of every entity (model, migration, DTO, repository, service, controller, requests, resource, Livewire screen and routes), plus auth, RBAC and a dynamic menu. **What stays with you:** the specific business logic and a short post-scaffold review (see [Known Limitations](docs/KnownLimitations.md)).
>
> **Why this saves tokens:** with **ptah + AI** (GitHub Copilot, Claude, Cursor) the agent doesn't waste tokens generating dozens of boilerplate files and re-deciding the architecture on every entity — that's already delivered, consistently. The agent spends its budget only on your differentiator. Fewer tokens, fewer files to review, and an architecture that doesn't drift between entities.

### Example: IT Helpdesk — full CRUD structure in ~3 minutes

#### Step 0 — Check system requirements

Before you start, confirm your environment meets the minimum versions:

```bash
php -v        # Required: PHP 8.2+
composer -V   # Required: Composer 2+
node -v       # Required: Node.js 18+
npm -v        # Required: npm 9+
```

Expected output (example):
```
PHP 8.2.x ...
Composer version 2.x.x ...
v20.x.x
10.x.x
```

> If any version is below the minimum, update it before proceeding. See the [full requirements →](docs/InstallationGuide.md#requirements) for database and extension requirements.

---

#### Step 1 — Create the Laravel project

```bash
composer create-project laravel/laravel ptah-app
cd ptah-app
```

---

#### Step 2 — Install Ptah

Choose **one** of the two options below:

**Option A — From Packagist (stable — recommended):**

```bash
composer require jonytonet/ptah
```

**Option B — From GitHub (latest dev version):**

Add the VCS repository to your `composer.json` before requiring the package:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jonytonet/ptah"
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then run:

```bash
composer require jonytonet/ptah:dev-main
```

**Both options — finish the installation:**

```bash
php artisan ptah:install
```

---

#### Step 3 — Configure your database

Edit `.env` to point to your database of choice before running migrations.

**SQLite** *(quick start)*:
```env
DB_CONNECTION=sqlite
```
```bash
touch database/database.sqlite
```

**MySQL**:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ptah_app
DB_USERNAME=root
DB_PASSWORD=your_password
```

**PostgreSQL**:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ptah_app
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

> See the [full database guide →](docs/InstallationGuide.md#step-3--configure-the-environment) for more options.

---

#### Step 4 — Enable the required modules

```bash
php artisan ptah:module auth
php artisan ptah:module permissions
php artisan ptah:module menu
```

---

#### Step 5 — Generate the 3 system entities

**Category:**
```bash
php artisan ptah:forge Category --fields="name:string,color:string:nullable,description:text:nullable"
```

**Agent:**
```bash
php artisan ptah:forge Agent --fields="name:string,email:string,department_id:unsignedBigInteger:nullable"
```

**Ticket:**
```bash
php artisan ptah:forge Ticket --fields="title:string,description:text,status:string,priority:string,category_id:unsignedBigInteger,agent_id:unsignedBigInteger:nullable,resolved_at:datetime:nullable"
```

---

#### Step 6 — Run migrations, sync menu and serve

```bash
php artisan migrate
php artisan ptah:menu-sync --fresh
php artisan serve
```

**What you get at the end (the structure):**

- ✅ Login with session protection and 2FA
- ✅ Full CRUD for Categories, Agents and Tickets — table, filters, modal, soft delete, export
- ✅ Role-based access control (MASTER + custom roles)
- ✅ Dynamic sidebar menu
- ✅ SOLID architecture: Controller → Service → Repository → DTO
- ✅ Generated validations, Resources and RESTful routes
- ✅ 14 artefacts created per entity, zero manual boilerplate

**What's still yours to do** (the actual application): wire up relationships left as TODOs, review the generated validation rules, and add the business logic — ticket escalation, priority notifications, integrations. Then the usual road to production: tests, security review and deploy. See the [post-scaffold checklist](docs/KnownLimitations.md).

**Why AI loves this:** instead of generating hundreds of files — thousands of tokens, with architectural drift between entities — the agent runs the commands above and spends its token budget only on your differentiator. Ptah handles the structure; AI handles the business logic.

---

## 🚀 Installation

### From Packagist (stable — recommended)

```bash
# 1. Install the package
composer require jonytonet/ptah

# 2. Run the installer
php artisan ptah:install

# 3. (Optional) Install Laravel Boost for AI agent integration
php artisan ptah:install --boost
```

### From GitHub (latest dev version)

Add to your `composer.json` before requiring:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/jonytonet/ptah"
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then:

```bash
composer require jonytonet/ptah:dev-main
php artisan ptah:install
```

> See the **[full installation guide →](docs/InstallationGuide.md)** for database setup, optional modules and troubleshooting.

---

## 🧩 Optional modules

Enable only what you need. Each module updates `.env` and runs its own migrations.

| Module | Command | What it enables |
|---|---|---|
| **auth** | `php artisan ptah:module auth` | Login, logout, password recovery, 2FA (TOTP + email), profile, active sessions |
| **menu** | `php artisan ptah:module menu` | Dynamic sidebar menu via database with cache, accordion groups |
| **company** | `php artisan ptah:module company` | Company and department management, company switcher, multi-tenant support |
| **permissions** | `php artisan ptah:module permissions` | Full RBAC — roles, pages, objects, middleware, Blade directives, audit log |
| **api** | `php artisan ptah:module api` | REST API with Swagger/OpenAPI via `darkaonline/l5-swagger`, standardised `BaseResponse` |
| **ai_agent** | `php artisan ptah:module ai_agent` | Floating AI chat widget (JivoChat-style) + provider config admin — powered by `prism-php/prism` |

```bash
# Check current state of all modules
php artisan ptah:module --list
```

---

## 🤖 Ptah + AI

Ptah is designed to work with AI agents. When installed with `--boost`, the package automatically registers its guidelines in the configured agents (GitHub Copilot, Claude, Cursor, Gemini, etc.), giving the agent deep knowledge of Ptah conventions, commands and architecture.

**Why this matters:**

- **Without Ptah:** AI needs to generate model + migration + repository + service + controller + requests + resource + view + routes for each entity — dozens of files, thousands of tokens, high risk of inconsistency
- **With Ptah:** AI runs `ptah:forge MyEntity --fields="..."`, the structure is ready, and it spends its tokens only on the business logic — fewer tokens, architecture guaranteed by the package

> For prompts, templates and AI workflow, see the **[AI Guide →](docs/AI_Guide.md)**

---

## 📟 Commands

| Command | Description |
|---|---|
| `php artisan ptah:install` | Installs the package (config, stubs, migrations, default data). Flags: `--demo`, `--boost`, `--force`, `--skip-npm` |
| `php artisan ptah:forge {Entity}` | **Generates the complete structure for an entity** ⭐ |
| `php artisan ptah:module {module}` | Enables an optional module |
| `php artisan ptah:module --list` | Lists modules and their states |
| `php artisan ptah:docs {Entity}` | Generates Swagger/OpenAPI annotations |

---

## 🎨 Theming & customizing views

**Brand colors — set once, everything follows.** Define your palette in
`config/ptah.php` (or via `.env`) and the whole UI — BaseCrud, Forge components,
modules — picks it up. No view publishing, survives `composer update`:

```php
// config/ptah.php
'theme' => [
    'colors' => [
        'primary' => env('PTAH_COLOR_PRIMARY', '#5b21b6'),
        'success' => env('PTAH_COLOR_SUCCESS', '#10b981'),
        'danger'  => env('PTAH_COLOR_DANGER',  '#ef4444'),
        'warn'    => env('PTAH_COLOR_WARN',     '#f59e0b'),
        'dark'    => env('PTAH_COLOR_DARK',     '#1e293b'),
    ],
],
```

```dotenv
# .env — rebrand without touching code
PTAH_COLOR_PRIMARY=#0d9488
```

Ptah injects these as CSS variables (`--color-primary`, `--ptah-primary`, …) in the
layout `<head>`; every tint, focus ring and hover is derived from them via
`color-mix()`. Accepts any CSS color (hex, rgb, hsl, oklch).

**Customizing views — publish only what you edit.** Most customization is done
through the CrudConfig modal (database-driven), so you rarely need to touch a Blade
file. If you do, ⚠️ **publishing a view means you own it** — Laravel will prefer your
copy and `composer update` will never refresh it again. Publish the **smallest** slice:

```bash
php artisan vendor:publish --tag=ptah-views-components   # just the <x-forge-*>
php artisan vendor:publish --tag=ptah-views-base-crud    # just the BaseCrud screen
php artisan vendor:publish --tag=ptah-views-auth         # just the auth pages
php artisan vendor:publish --tag=ptah-views-ai           # just the AI widget
# (ptah-views still publishes ALL 60+ views — avoid it unless you really mean to)
```

To keep the UI always up to date, keep `resources/views/vendor/ptah/` empty.

---

## 📚 Documentation

| Document | Contents |
|---|---|
| **[Quick Start](docs/QuickStart.md)** | Your first CRUD in 5 minutes — one entity, SQLite, zero decisions |
| **[Installation Guide](docs/InstallationGuide.md)** | Step-by-step guide with real terminal output — Laravel 11/12, all modules and Boost |
| **[BaseCrud](docs/BaseCrud.md)** | Complete reference — column schema, types, filters, renderers, export, preferences and UI configuration |
| **[Modules](docs/Modules.md)** | Detailed documentation for Auth, Menu, Company, Permissions and API modules |
| **[Company](docs/Company.md)** | Company module — companies, departments, company switcher and multi-company |
| **[Permissions](docs/Permissions.md)** | Permissions module — RBAC, roles, middleware, helpers, Blade directives and audit log |
| **[Base Layer](docs/BaseLayer.md)** | BaseDTO, BaseRepository, BaseService — all methods, signatures, examples and REST API query parameters |
| **[AI Guide](docs/AI_Guide.md)** | AI agent integration — prompts, templates and workflow with Copilot, Claude and Cursor |
| **[Known Limitations](docs/KnownLimitations.md)** | Developer checklist — decimal precision, FK constraints, composite indexes, post-forge responsibilities |

---

## 📋 Requirements

| Requirement | Minimum version |
|---|---|
| PHP | 8.2 |
| Laravel | 11 or 12 |
| Node.js + npm | 18+ |
| Livewire | v4 (included as dependency) |

---

## 📄 License

Open source under the [MIT License](LICENSE).

---

<div align="center">
  <p>Made by <a href="https://github.com/jonytonet">jonytonet</a></p>
  <p><em>Ptah — Enterprise Structure. Startup Speed.</em></p>
</div>
