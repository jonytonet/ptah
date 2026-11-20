<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Env;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Throwable;

/**
 * Which CSS framework the host uses, and everything that changes with it.
 *
 * `tailwind` (default): the host compiles ptah's classes with its own
 * Tailwind; nothing here prints anything, and every view renders the HTML it
 * rendered before this class existed.
 *
 * `bootstrap`: the host has no Tailwind. ptah links its pre-compiled
 * stylesheet (resources/dist/ptah.css, built by `npm run build:css`, published
 * by `vendor:publish --tag=ptah-assets`). That stylesheet only applies inside
 * a ptah SCOPE — an element carrying `data-ptah-scope` (or the class
 * `ptah-scope`) — and stops at a SLOT (`data-ptah-slot`): host markup placed in
 * a ptah layout or component keeps its Bootstrap meaning. This class decides
 * where those two markers go:
 *
 *  - the layouts put the scope on <body>;
 *  - every Livewire component of the package gets it on its root
 *    (PtahServiceProvider, Livewire render hook);
 *  - an `<x-forge-*>` written in a HOST view gets it through an attribute the
 *    Blade precompiler adds ({@see markHostComponents()}), and that same
 *    attribute tells the component to fence its slots ({@see slot()}). Inside
 *    the package's own views the precompiler adds nothing: their slot content
 *    is ptah markup and must stay in the scope.
 *
 * Read with a default: a config/ptah.php published before `css` existed has
 * no such key.
 */
final class CssFramework
{
    public const TAILWIND = 'tailwind';

    public const BOOTSTRAP = 'bootstrap';

    /** @var list<string> */
    public const FRAMEWORKS = [self::TAILWIND, self::BOOTSTRAP];

    /** Marks a ptah-owned root: the stylesheet applies from here down. */
    public const SCOPE_ATTRIBUTE = 'data-ptah-scope';

    /** Marks host content inside a ptah element: the stylesheet stops here. */
    public const SLOT_ATTRIBUTE = 'data-ptah-slot';

    /** Where `vendor:publish --tag=ptah-assets` puts the stylesheet, under public/. */
    public const PUBLIC_PATH = 'vendor/ptah/ptah.css';

    /**
     * Vite entries a Bootstrap host may have, in the order they are linked.
     * Only those in the host's build are asked for (see viteEntries()).
     *
     * @var list<string>
     */
    public const VITE_ENTRIES = ['resources/sass/app.scss', 'resources/css/app.css', 'resources/js/app.js'];

    private static ?string $distHash = null;

    public static function current(): string
    {
        $framework = strtolower(trim((string) config('ptah.css.framework', self::TAILWIND)));

        return in_array($framework, self::FRAMEWORKS, true) ? $framework : self::TAILWIND;
    }

    public static function isBootstrap(): bool
    {
        return self::current() === self::BOOTSTRAP;
    }

    /** The pre-compiled stylesheet shipped with the package. */
    public static function distPath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/ptah.css';
    }

    /**
     * The inputs hash recorded in the header of the package's stylesheet ('' when
     * it cannot be read) — the cache-busting version of the link.
     */
    public static function distHash(): string
    {
        if (self::$distHash !== null) {
            return self::$distHash;
        }

        $hash = '';

        try {
            $handle = @fopen(self::distPath(), 'rb');
            if ($handle !== false) {
                $head = (string) fread($handle, 1024);
                fclose($handle);
                if (preg_match('/inputs-hash: ([0-9a-f]{64})/', $head, $m) === 1) {
                    $hash = $m[1];
                }
            }
        } catch (Throwable) {
            $hash = '';
        }

        return self::$distHash = $hash;
    }

    /** URL of the published stylesheet, versioned by the package's build. */
    public static function stylesheetUrl(): string
    {
        $version = substr(self::distHash(), 0, 12);

        return asset(self::PUBLIC_PATH).($version !== '' ? '?v='.$version : '');
    }

    /**
     * ` data-ptah-scope` in bootstrap mode, nothing in tailwind mode — for the
     * roots the package renders outside any other scope (<body> of its
     * layouts, the toast host). Written glued to the tag name
     * (`<div{{ … }} x-data`) so tailwind mode stays byte-identical.
     */
    public static function scopeAttribute(): HtmlString
    {
        return new HtmlString(self::isBootstrap() ? ' '.self::SCOPE_ATTRIBUTE : '');
    }

    /**
     * The same marker for an `<x-forge-*>` whose root does not print
     * `$attributes` (they go on an inner <input>, or only `class` is read):
     * ` data-ptah-scope` when a host view wrote the component in bootstrap
     * mode ({@see markHostComponents()}), nothing otherwise.
     */
    public static function hostScope(?ComponentAttributeBag $attributes): HtmlString
    {
        return new HtmlString(self::isBootstrap() && $attributes?->get(self::SCOPE_ATTRIBUTE) ? ' '.self::SCOPE_ATTRIBUTE : '');
    }

    /** The <link> of the stylesheet in bootstrap mode; nothing in tailwind mode. */
    public static function stylesheetTag(): HtmlString
    {
        if (! self::isBootstrap()) {
            return new HtmlString('');
        }

        return new HtmlString('<link rel="stylesheet" href="'.e(self::stylesheetUrl()).'" data-ptah-css>');
    }

    /**
     * The host's own Vite entries a ptah layout links in bootstrap mode: the
     * ones its build actually has. The Tailwind layout asks for
     * resources/css/app.css unconditionally, and a Bootstrap host (laravel/ui
     * compiles resources/sass/app.scss) would get a ViteException.
     *
     * @return list<string>
     */
    public static function viteEntries(): array
    {
        try {
            if (is_file(public_path('hot'))) {
                return array_values(array_filter(self::VITE_ENTRIES, fn (string $entry): bool => is_file(base_path($entry))));
            }

            $manifest = public_path('build/manifest.json');
            if (! is_file($manifest)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($manifest), true);

            return is_array($decoded)
                ? array_values(array_filter(self::VITE_ENTRIES, fn (string $entry): bool => array_key_exists($entry, $decoded)))
                : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The brand colours partials.theme-colors prints: all of
     * config('ptah.theme.colors'), as always — except in a host layout
     * (`<x-ptah-assets />`, `$embedded`) in bootstrap mode. There only the ones
     * the host chose are printed — PTAH_COLOR_* present in the environment, or
     * a value other than the package default — and the rest follow the host's
     * Bootstrap theme through the bridge in ptah.css. (With `config:cache` the
     * .env is not read at runtime; there only a value different from the
     * default counts.)
     *
     * @param  array<string, mixed>  $colors
     * @return array<string, mixed>
     */
    public static function themeColors(array $colors, bool $embedded = false): array
    {
        if (! $embedded || ! self::isBootstrap()) {
            return $colors;
        }

        $defaults = ['primary' => '#5b21b6', 'success' => '#10b981', 'danger' => '#ef4444', 'warn' => '#f59e0b', 'dark' => '#1e293b'];

        return array_filter($colors, function (mixed $value, string $key) use ($defaults): bool {
            if (Env::get('PTAH_COLOR_'.strtoupper($key)) !== null) {
                return true;
            }

            return ! isset($defaults[$key]) || strtolower(trim((string) $value)) !== $defaults[$key];
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * A component's slot, fenced off from the ptah scope when the component was
     * written in a host view in bootstrap mode — so `px-3` in that slot keeps
     * Bootstrap's meaning. Anything else comes back untouched (the same object),
     * which keeps tailwind mode byte-identical.
     *
     * A <span> because it may sit in a <p> or a <button>, where a <div> would
     * close the paragraph; it is `display: contents`, so it never takes part in
     * the layout. Plain text needs no fence.
     */
    public static function slot(mixed $slot, ?ComponentAttributeBag $attributes = null): mixed
    {
        if (! $slot instanceof Htmlable || ! self::isBootstrap() || ! $attributes?->get(self::SCOPE_ATTRIBUTE)) {
            return $slot;
        }

        $html = $slot->toHtml();

        if (! str_contains($html, '<')) {
            return $slot;
        }

        return new HtmlString('<span '.self::SLOT_ATTRIBUTE.'>'.$html.'</span>');
    }

    /**
     * Blade precompiler: in a view that is NOT one of the package's, every
     * `<x-forge-*>` gets `:data-ptah-scope="…isBootstrap()"`. In bootstrap mode
     * that renders `data-ptah-scope` on the component's root (it rides on
     * `$attributes`) and fences its slots; in tailwind mode the value is false
     * and Blade prints nothing. The compiled view is the same in both modes, so
     * switching PTAH_CSS needs no view:clear.
     */
    public static function markHostComponents(string $value, ?string $path): string
    {
        if (! str_contains($value, '<x-forge-') || self::isPackageView($path)) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/<x-forge-([\w\-.]+)(?=[\s\/>])/',
            fn (array $m): string => $m[0].' :'.self::SCOPE_ATTRIBUTE.'="\\'.self::class.'::isBootstrap()"',
            $value
        );
    }

    /** Whether a Livewire component is the package's, or extends one of them. */
    public static function isPackageComponent(object $component): bool
    {
        foreach ([$component::class, ...array_values(class_parents($component) ?: [])] as $class) {
            if (str_starts_with($class, 'Ptah\\')) {
                return true;
            }
        }

        return false;
    }

    /** Whether a compiled view is one of the package's (shipped or published). */
    public static function isPackageView(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $normalize = fn (string $p): string => rtrim(str_replace('\\', '/', (string) (realpath($p) ?: $p)), '/');
        $file = $normalize($path);

        foreach ([dirname(__DIR__, 2).'/resources/views', resource_path('views/vendor/ptah')] as $root) {
            if (str_starts_with($file, $normalize($root).'/')) {
                return true;
            }
        }

        return false;
    }
}
