<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves `{{column}}` placeholders inside a declarative style string, using
 * values from the row being rendered.
 *
 * The case this exists for: a Tags table where each tag carries its own colour
 * in a column, and the badge has to be that colour. The old model — one
 * `{field, condition, value, style}` rule per known colour — needs a rule per
 * colour and does not scale past a handful. The workaround was
 * `colsMetodoCustom` + `colsMetodoRaw` returning pre-coloured HTML from a
 * presenter, which works and moves presentation out of the declarative config
 * into host code for something the config should be able to say:
 *
 *   "style": "background-color: {{color}}1a; color: {{color}}"
 *
 * ── Why the values are filtered so hard ──────────────────────────────────
 *
 * The result is interpolated into a `style` attribute, and the value comes from
 * a database column — which in a CRUD is, by definition, something a user typed.
 * Blade escapes the attribute so quotes cannot break out of it, but that is not
 * the whole risk: a CSS value is its own little language, and
 * `url(https://tracker/x)` inside a `background-color` declaration is a request
 * to a third party made from an authenticated page.
 *
 * So a placeholder resolves only to a value that CANNOT carry a payload:
 *
 *   - a hex colour, `#rgb` to `#rrggbbaa`;
 *   - or letters only, 2 to 24 of them, which covers the CSS colour keywords
 *     (`teal`, `rebeccapurple`) and cannot contain `:`, `;`, `(`, `)`, `/` or a
 *     space — the characters you would need to say anything else.
 *
 * Anything else and the WHOLE style is dropped, not just the placeholder. A
 * half-substituted declaration would be a rule the author did not write, and
 * silently applying it is worse than applying nothing: `background-color: 1a`
 * is invalid and inert, but `color: red` left over from a partial substitution
 * would look deliberate.
 */
final class StyleTemplate
{
    /** `{{ column }}` — whitespace inside the braces is tolerated. */
    private const PLACEHOLDER = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/';

    /** `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`. */
    private const HEX = '/^#(?:[0-9A-Fa-f]{3,4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

    /** A bare CSS keyword. Letters only, so it cannot say anything but itself. */
    private const KEYWORD = '/^[A-Za-z]{2,24}$/';

    /**
     * Does this style string need a row at all?
     *
     * Callers use it to skip the work entirely for the overwhelmingly common
     * case of a static style, which is every style written before this existed.
     */
    public static function hasPlaceholders(string $style): bool
    {
        return preg_match(self::PLACEHOLDER, $style) === 1;
    }

    /**
     * Substitutes every placeholder from the row.
     *
     * Returns the style unchanged when it has no placeholders, and null when any
     * placeholder cannot be resolved to a safe value — a missing column, a null,
     * or a value that is not a colour.
     *
     * @param  Model|array<string, mixed>  $row
     */
    public static function resolve(string $style, Model|array $row): ?string
    {
        if (! self::hasPlaceholders($style)) {
            return $style;
        }

        $failed = false;

        $resolved = preg_replace_callback(
            self::PLACEHOLDER,
            static function (array $m) use ($row, &$failed): string {
                $safe = self::safeValue(self::read($row, $m[1]));

                if ($safe === null) {
                    $failed = true;

                    return '';
                }

                return $safe;
            },
            $style
        );

        if ($failed || ! is_string($resolved)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param  Model|array<string, mixed>  $row
     */
    private static function read(Model|array $row, string $field): mixed
    {
        if (is_array($row)) {
            return $row[$field] ?? null;
        }

        // getAttribute() would also fire an accessor or hit a relation; for a
        // style placeholder the raw column is what is meant, and a magic call
        // on a name that is not an attribute is a needless surprise.
        return array_key_exists($field, $row->getAttributes())
            ? $row->getAttribute($field)
            : null;
    }

    /**
     * The value, if it is one a `style` attribute can be trusted with.
     */
    private static function safeValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match(self::HEX, $trimmed) === 1) {
            return $trimmed;
        }

        if (preg_match(self::KEYWORD, $trimmed) === 1) {
            return $trimmed;
        }

        return null;
    }
}
