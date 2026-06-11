# Ptah Sandbox

A disposable Laravel app for **contributing to Ptah without an existing project**. One command gives you a running app with the local package source symlinked in — edit the package on your host machine and refresh the browser.

## Requirements

- Docker + Docker Compose (nothing else — no local PHP/Composer/Node needed)

## Usage

```bash
cd sandbox
docker compose up
```

First run takes a few minutes (creates a Laravel app, requires the local `jonytonet/ptah` via path repository, enables `auth` + `menu` + `permissions` and forges a sample `Task` entity). Subsequent runs reuse the app stored in the `sandbox-app` volume and boot in seconds.

Then open <http://localhost:8000/login>. The admin password is **printed once** in the `docker compose up` output (random — scroll up and copy it), or set `PTAH_ADMIN_PASSWORD` before the first run.

## How it works

| Mount | Purpose |
|---|---|
| `..` → `/ptah` | Package source, symlinked via Composer path repository — **host edits apply instantly** |
| `sandbox-app` volume → `/app` | The generated Laravel app — survives restarts, never pollutes the repo |

## Common tasks

```bash
# Shell inside the container
docker compose exec app sh

# Forge another entity
docker compose exec app php artisan ptah:forge Product --fields="name:string,price:decimal(10,2)"
docker compose exec app php artisan migrate
docker compose exec app php artisan ptah:menu-sync --fresh

# Run the package test suite (uses the package's own vendor/)
cd .. && composer install && vendor/bin/phpunit
```

## Resetting

```bash
docker compose down -v   # -v removes the sandbox-app volume → next up starts fresh
```

## Limitations

- `ptah:install` runs with `--skip-npm` (the image has no Node): Tailwind assets are not compiled, so styling may be incomplete. For UI work, install Node in the container or run `npm install && npm run build` from a host with Node against the volume.
- The sample app is for development only — it is not a deployment reference.
