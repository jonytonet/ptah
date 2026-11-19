<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Ptah\Crud\CrudScreenRegistry;
use Ptah\Http\Controllers\Admin\CrudConfigApiController;
use Ptah\Http\Controllers\Crud\FiltersApiController;
use Ptah\Http\Controllers\CrudApiController;
use Ptah\Http\Headless\HeadlessCompanySwitcher;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * What the React/Vue layout needs around the screens (1.59.0), built in one
 * place for two callers: the endpoints `GET {api}/menu` and `GET {api}/me`
 * (ShellApiController), and the shell page ptah::stack.app, which embeds both
 * answers (`data-boot`) so the layout paints complete on the first frame
 * instead of drawing empty and fetching them.
 */
final class StackBoot
{
    /** The pages drawn without the signed-in layout (core/navigate.ts GUEST_PAGES). */
    public const GUEST_PAGES = ['login', 'forgot-password', 'reset-password', 'two-factor'];

    /**
     * Everything the sidebar draws: the tree, the flat list of links the
     * "jump to a screen" search runs over (with the trail to each one, and the
     * text already folded — the same MenuResolver the Livewire sidebar and the
     * AI's find_menu tool use), whether that search is shown, and the brand.
     *
     * @return array<string, mixed>
     */
    public static function menu(): array
    {
        $flat = MenuResolver::flatLinks();
        $nested = false;

        foreach ($flat as $link) {
            if (count($link['breadcrumb']) > 1) {
                $nested = true;
                break;
            }
        }

        // The rule of forge-sidebar.blade.php: never under 3 links; with many
        // links, or as soon as one sits inside a group.
        $min = (int) config('ptah.forge.sidebar_jump_min_items', 8);
        $jump = config('ptah.forge.sidebar_jump', true)
            && count($flat) >= 3
            && (count($flat) >= max(2, $min) || $nested);

        return [
            'data' => MenuResolver::tree(),
            'flat' => $flat,
            'jump' => (bool) $jump,
            'brand' => [
                'name' => (string) config('app.name', 'Ptah'),
                'logo' => self::asset(config('ptah.brand.logo')),
                'logo_dark' => self::asset(config('ptah.brand.logo_dark')),
            ],
            // The top bar: company switcher, bell, administration links.
            'shell' => StackPage::shell(),
        ];
    }

    /**
     * Who is signed in, for the account menu.
     *
     * @return array<string, mixed>
     */
    public static function me(?Authenticatable $user): array
    {
        return [
            'user' => $user === null ? null : [
                'id' => $user->getAuthIdentifier(),
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'photo_url' => ProfilePhoto::url($user),
            ],
            'company_id' => function_exists('ptah_company_id') ? ptah_company_id() : null,
            'locale' => app()->getLocale(),
            'screens' => app(CrudScreenRegistry::class)->keys(),
        ];
    }

    /**
     * The companies this user may switch to and the active one — the answer
     * of `GET {api}/admin/company-switcher`.
     *
     * @return array{companies: list<array{id: int, name: string, label: string, is_default: bool}>, active_id: mixed}
     */
    public static function companySwitcher(?HeadlessCompanySwitcher $switcher = null, ?int $activeId = null): array
    {
        $switcher ??= HeadlessCompanySwitcher::headless();
        $companies = [];

        foreach ($switcher->companies as $company) {
            $companies[] = [
                'id' => (int) $company->id,
                'name' => (string) $company->name,
                // The tab text of the Blade view.
                'label' => $company->getLabelDisplay() ?: mb_strtoupper(mb_substr((string) $company->name, 0, 4)),
                'is_default' => (bool) $company->is_default,
            ];
        }

        // After a switch the component's own activeId still holds the company
        // it was mounted with (the Livewire screen reloads the page instead).
        return ['companies' => $companies, 'active_id' => $activeId ?? $switcher->activeId];
    }

    /**
     * The answers the layout needs for the shell page — the menu, "me" and,
     * when the top bar shows it, the company switcher — or null: the API off,
     * a visitor, or a guest page (login…), none of which draws the layout.
     *
     * @return array{menu: array<string, mixed>, me: array<string, mixed>, switcher: array<string, mixed>|null}|null
     */
    public static function forPage(string $page): ?array
    {
        if (! config('ptah.crud_api.enabled') || in_array($page, self::GUEST_PAGES, true)) {
            return null;
        }

        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        $menu = self::menu();
        $switcher = null;
        if (($menu['shell']['company_switcher'] ?? false) === true) {
            try {
                $switcher = self::companySwitcher();
            } catch (Throwable) {
                // The switcher asks for its own data, as before.
                $switcher = null;
            }
        }

        return ['menu' => $menu, 'me' => self::me($user), 'switcher' => $switcher];
    }

    /**
     * Whether this page may carry API answers (`answers()`): the API is on,
     * someone is signed in, and the page's route went through every
     * middleware the API is behind (`ptah.crud_api.middleware`) — an answer
     * is never embedded in a page that is less guarded than the endpoint it
     * came from. `ptah.crud_api.embed => false` turns it off.
     */
    public static function embeds(?Request $request = null): bool
    {
        if (! config('ptah.crud_api.enabled') || ! config('ptah.crud_api.embed', true) || auth()->user() === null) {
            return false;
        }

        $route = ($request ?? request())->route();
        if (! $route instanceof Route) {
            return false;
        }

        $api = array_filter((array) config('ptah.crud_api.middleware', ['web', 'auth']), 'is_string');

        return array_diff($api, array_filter($route->gatherMiddleware(), 'is_string')) === [];
    }

    /**
     * The first answers a CRUD screen asks the API for, by the path the
     * client asks them with — its schema, its first listing (reopened where
     * the user left), the description of its filters and whether this user
     * gets the config button. The page of the screen embeds them
     * (`data-answers`), so opening a screen, or moving to it, is one request
     * instead of five. Each is the very answer of its endpoint: the
     * controller's action runs here.
     *
     * Null when nothing is embedded: `embeds()` says no, the page URL has a
     * query string (the client then asks, as before), or the screen refuses
     * this user — the client asks and shows the refusal itself.
     *
     * @return array<string, mixed>|null
     */
    public static function answers(string $screen, ?Request $request = null): ?array
    {
        $request ??= request();

        if ($screen === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $screen) !== 1
            || $request->query() !== [] || ! self::embeds($request)) {
            return null;
        }

        $base = '/crud/'.rawurlencode($screen);
        $plain = fn (): Request => $request->duplicate([], []);

        $calls = [
            $base.'/schema' => fn (): JsonResponse => app(CrudApiController::class)->schema($plain(), $screen),
            $base.'?restore=1' => fn (): JsonResponse => app(CrudApiController::class)->index($request->duplicate(['restore' => '1'], []), $screen),
            $base.'/filters/meta' => fn (): JsonResponse => app(FiltersApiController::class)->meta($plain(), $screen),
            $base.'/config/can' => fn (): JsonResponse => app(CrudConfigApiController::class)->can($plain(), $screen),
        ];

        $answers = [];

        foreach ($calls as $path => $call) {
            try {
                $response = $call();
                if ($response->getStatusCode() === 200) {
                    $answers[$path] = $response->getData();
                }
            } catch (Throwable $e) {
                // A refusal (403/404) is the client's to show; anything else
                // is reported and the client asks the endpoint itself.
                if (! $e instanceof HttpExceptionInterface) {
                    report($e);
                }
            }

            // Without a schema there is no screen to draw: nothing is embedded.
            if ($answers === []) {
                return null;
            }
        }

        return $answers;
    }

    /** A URL as it is; a path under public/ as its asset URL. */
    private static function asset(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return preg_match('#^(https?:)?//|^/|^data:#i', $value) === 1 ? $value : asset($value);
    }
}
