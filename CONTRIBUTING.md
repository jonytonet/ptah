# Contributing to Ptah

Thanks for considering a contribution! Ptah is a Laravel package combining SOLID scaffolding, Blade components and a dynamic CRUD system — and it gets better with every issue, test and PR from the community.

## Quick links

- [Reporting bugs](#reporting-bugs)
- [Suggesting features](#suggesting-features)
- [Development setup](#development-setup)
- [Running the test suite](#running-the-test-suite)
- [Coding standards](#coding-standards)
- [Submitting a pull request](#submitting-a-pull-request)

---

## Reporting bugs

Open an issue using the **Bug report** template. Please include:

- Ptah, Laravel, Livewire and PHP versions (`composer show jonytonet/ptah laravel/framework livewire/livewire`)
- Steps to reproduce — ideally on a fresh Laravel app
- What you expected vs. what happened
- Relevant stack trace or log excerpt (`storage/logs/laravel.log`)

**Security vulnerabilities:** do **not** open a public issue. E-mail the maintainer directly (see `composer.json` authors) so a fix can be released before disclosure.

## Suggesting features

Open an issue with the **Feature request** template. Explain the problem you're solving, not only the solution — it helps us evaluate alternatives. Features that fit Ptah's niche (database-driven CRUD config, SOLID scaffolding, AI workflows) are the most likely to be accepted.

## Development setup

```bash
git clone https://github.com/jonytonet/ptah.git
cd ptah
composer install
```

The package is self-contained for testing — Orchestra Testbench simulates a full Laravel app with SQLite in memory. You do **not** need a host application to run the suite.

To test against a real app, add a path repository to a Laravel project's `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "../ptah", "options": { "symlink": true } }
    ]
}
```

Then `composer require jonytonet/ptah:@dev` and `php artisan ptah:install`.

## Running the test suite

```bash
vendor/bin/phpunit                       # full suite
vendor/bin/phpunit --filter SomeTest     # single test
vendor/bin/pint --test                   # code style (CI enforces this)
vendor/bin/phpstan analyse               # static analysis (level 5 + baseline)
```

All three must pass before a PR can be merged — CI runs them on PHP 8.2/8.3/8.4 × Laravel 11/12.

### Test conventions

- PHPUnit 11 `#[Test]` attribute (not `@test` docblocks)
- Namespace `Ptah\Tests\…`, `declare(strict_types=1)`
- Extend `Ptah\Tests\TestCase` (registers Livewire, Prism and Ptah providers, SQLite memory, app key)
- Stub tables live in `tests/migrations/` — reuse `items` or create your own
- New code must **not** add PHPStan baseline entries or `@phpstan-ignore` annotations — fix the cause

## Coding standards

- **Style:** Laravel Pint with the `laravel` preset (run `vendor/bin/pint` before committing)
- **Static analysis:** PHPStan level 5 via larastan; the baseline only covers legacy code
- **Architecture:** follow the existing layers — Generators, Services, Repositories, DTOs, Livewire concerns. New filter logic goes through the Strategy pattern (`src/Services/Crud/Filters/`)
- **Security:** never interpolate user input into raw SQL (use `SqlIdentifier::isSafe()` for identifiers); never render user data through unescaped Blade (`{!! !!}`); inline hook logic must stay inside the ExpressionLanguage sandbox
- **i18n:** user-facing strings go through `trans('ptah::ui.…')` with both `en` and `pt_BR` entries
- **Line endings:** LF (enforced by `.gitattributes`)

## Submitting a pull request

1. Fork and create a branch from `main`: `feature/short-description` or `fix/short-description`
2. Make your change with tests — bug fixes need a regression test, features need coverage
3. Update `CHANGELOG.md` under `[Unreleased]`
4. Make sure `phpunit`, `pint --test` and `phpstan analyse` all pass locally
5. Open the PR using the template; link the related issue

Small, focused PRs are reviewed faster than large ones. If your change is big, open an issue first to discuss the approach.

---

By contributing you agree that your contributions are licensed under the [MIT License](LICENSE).
