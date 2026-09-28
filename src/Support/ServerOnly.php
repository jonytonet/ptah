<?php

declare(strict_types=1);

namespace Ptah\Support;

use Attribute;
use ReflectionMethod;

/**
 * Marks a PUBLIC Livewire method that the browser must never call.
 *
 * Livewire lets the client call every public method a component declares,
 * with arguments it chooses, and hands the return value back. Some methods
 * are public only because views and host code call them — and two of those
 * turned out to execute code the caller picks (found by the 28/09/2026
 * surface audit, fixed in 1.41.6):
 *
 *   SearchDropdown::formatValue($value, $mask)  — a mask of `Class@method`
 *       resolved and CALLED any class: `Filesystem@get` read `.env`,
 *       `DatabaseManager@unprepared` ran SQL.
 *   BaseCrud::formatCell($col, $row)            — a forged `colsMetodoCustom`
 *       called any method of any `App\Services` class with the caller's
 *       arguments (`…Service\delete(5)`).
 *
 * Making them protected would break every view and host that calls them, so
 * they stay public for PHP and are refused to the client: PtahServiceProvider
 * hooks Livewire's `call` event and answers a marked method as if it did not
 * exist. `ServerOnlyMethodTest` fails when a public method that returns data
 * is left unmarked.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ServerOnly
{
    /** @var array<string, bool> class::method => marked */
    private static array $memo = [];

    public static function marks(object $component, string $method): bool
    {
        $key = $component::class.'::'.$method;

        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = method_exists($component, $method)
                && (new ReflectionMethod($component, $method))->getAttributes(self::class) !== [];
        }

        return self::$memo[$key];
    }
}
