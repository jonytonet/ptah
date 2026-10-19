<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * A model attribute as a plain value (1.46.4). A column cast to an enum
 * (`'status' => StatusEnum::class`) comes back as an object, and every
 * `(string) $value` in the listing — badge, select label, row style, export,
 * kanban move — died with "could not be converted to string": a 500, but only
 * once the screen had a row, so an empty screen hid it.
 */
final class Scalar
{
    /** BackedEnum → its value; pure UnitEnum → its name; anything else as is. */
    public static function of(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return $value;
    }
}
