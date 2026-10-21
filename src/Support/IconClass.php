<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * An icon class as configured → the class the browser needs (1.50.0).
 *
 * Boxicons needs its base class: `bx bx-check`, `bx bxs-star`, `bx bxl-github`.
 * The docs and the CLI examples wrote `icon=bx-check`, and the class was
 * printed as given — the icon never showed. A Boxicons glyph without the base
 * gets it; anything else (FontAwesome, a full Boxicons class) is untouched.
 */
final class IconClass
{
    public static function normalize(string $class): string
    {
        $class = trim($class);

        if ($class === '' || preg_match('/(^|\s)bx(\s|$)/', $class) === 1) {
            return $class;
        }

        return preg_match('/(^|\s)bx[sl]?-[\w-]+/', $class) === 1 ? 'bx '.$class : $class;
    }
}
