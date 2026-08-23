<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Resolves `config('ptah.navbar.notifications')` into one of three navbar
 * bell states. Pure — no framework/facade dependency — so it is trivial to
 * unit test and safe to call from a Blade `@php` block.
 *
 * States:
 *  - MODE_DEFAULT   : keep the static bell button that already lives in
 *                     forge-navbar.blade.php (byte-identical fallback).
 *  - MODE_HIDDEN    : render nothing — the consumer opted the slot out.
 *  - MODE_COMPONENT : render the given Livewire component alias.
 *
 * `resolve()` only NORMALISES the configured value; it does not check
 * whether a MODE_COMPONENT alias is actually registered — that check needs
 * Livewire and belongs to the caller (forge-navbar.blade.php), which falls
 * back to MODE_DEFAULT when Livewire::exists() is false.
 */
final class NavbarSlot
{
    public const MODE_DEFAULT = 'default';

    public const MODE_HIDDEN = 'hidden';

    public const MODE_COMPONENT = 'component';

    /**
     * String values (already lower-cased + trimmed) that opt the slot OUT.
     *
     * @var list<string>
     */
    private const HIDDEN_VALUES = ['none', 'off', 'hidden', 'false', '0'];

    /**
     * @return array{mode: string, component: string|null}
     */
    public static function resolve(mixed $configured): array
    {
        if ($configured === null || $configured === '') {
            return ['mode' => self::MODE_DEFAULT, 'component' => null];
        }

        if ($configured === false) {
            return ['mode' => self::MODE_HIDDEN, 'component' => null];
        }

        if (is_string($configured)) {
            $normalized = strtolower(trim($configured));

            if (in_array($normalized, self::HIDDEN_VALUES, true)) {
                return ['mode' => self::MODE_HIDDEN, 'component' => null];
            }

            $trimmed = trim($configured);

            if ($trimmed === '') {
                return ['mode' => self::MODE_DEFAULT, 'component' => null];
            }

            return ['mode' => self::MODE_COMPONENT, 'component' => $trimmed];
        }

        // Any other scalar (true, int, array, …) is not a valid alias — keep
        // the safe default rather than guessing.
        return ['mode' => self::MODE_DEFAULT, 'component' => null];
    }
}
