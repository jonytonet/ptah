# Quick Start — your first CRUD in 5 minutes

The shortest path from zero to a working, authenticated CRUD screen. One entity, SQLite, no decisions to make. For the full options see the [Installation Guide](InstallationGuide.md).

**Requirements:** PHP 8.2+, Composer 2, Node 18+. Check with `php -v`, `composer -V`, `node -v`.

---

## 1. Create the project and install Ptah (~2 min)

```bash
composer create-project laravel/laravel quickstart
cd quickstart
composer require jonytonet/ptah
php artisan ptah:install
```

## 2. Use SQLite (zero config)

Laravel 11+ already defaults to SQLite. Confirm your `.env` has:

```env
DB_CONNECTION=sqlite
```

If `database/database.sqlite` doesn't exist yet:

```bash
# macOS / Linux
touch database/database.sqlite
# Windows (PowerShell)
New-Item database/database.sqlite -ItemType File
```

## 3. Enable auth + permissions

```bash
php artisan ptah:module auth
php artisan ptah:module permissions
php artisan ptah:module menu
```

The permissions module creates the admin user. **Copy the generated password shown in the terminal — it is displayed only once.** (Or set `PTAH_ADMIN_PASSWORD` in `.env` before running the command.)

## 4. Generate your entity (~30 s)

```bash
php artisan ptah:forge Task --fields="title:string,done:boolean,due_at:datetime:nullable"
php artisan migrate
php artisan ptah:menu-sync --fresh
```

One command created: model, migration, DTO, repository (+ interface), service, controller, requests, resource, Livewire view, route and the CRUD configuration in the database.

## 5. Run it

```bash
php artisan serve
```

Open <http://localhost:8000/login>, sign in as `admin@admin.com` with the password from step 3, and click **Tasks** in the sidebar. You have a full CRUD: searchable table, filters, create/edit modal, soft delete, export — all configurable at runtime via the gear icon (no redeploy).

---

## What just happened?

| You ran | Ptah generated |
|---|---|
| `ptah:install` | Config, stubs, base migrations, Tailwind/Alpine assets |
| `ptah:module …` | Login + 2FA, RBAC with MASTER role, dynamic sidebar menu |
| `ptah:forge Task` | 14 artefacts in a Controller → Service → Repository → DTO architecture |

## Next steps

- **Add fields or relations:** see the `--fields` syntax in [Commands.md](Commands.md) (FK auto-detection with `*_id`, `surname=` labels, `--no-soft-deletes`, `--api`)
- **Tune the CRUD without code:** column renderers, filters, hooks and permissions in [BaseCrud.md](BaseCrud.md) and [Configuration.md](Configuration.md)
- **Post-scaffold checklist:** relationships left as TODOs and validation review in [KnownLimitations.md](KnownLimitations.md)
- **Working with AI agents:** [AI_Guide.md](AI_Guide.md) — the structure is already generated, so your agent spends tokens only on business logic
