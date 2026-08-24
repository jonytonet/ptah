<?php

namespace Ptah\Commands\Config\Parsers;

use Ptah\Support\StyleRule;

class StyleParser
{
    /**
     * The explicit end-of-value marker. Its presence is what lets a VALUE
     * contain ':' — see splitSegments().
     */
    private const STYLE_MARKER = ':style=';

    /**
     * Parse style definition string
     *
     * Format: field:condition:value:style
     * Example: status:==:cancelled:background:#FEE2E2;color:#991B1B;
     *
     * Long form, for a value that itself contains ':':
     *   field:condition:value:style=<css>
     * Example: start_at:==:12:30:style=background:#eee;
     *
     * `condition` accepts any of StyleRule::CONDITIONS or its aliases
     * (eq/ne/lt/gt/lte/gte/=) — anything else (e.g. LIKE) is rejected here,
     * because HasCrudRenderers::getRowStyle()'s match() expression has no arm
     * for it and would silently never apply the style.
     */
    public function parse(string $definition): array
    {
        [$field, $condition, $value, $style] = $this->splitSegments($definition);

        $rule = StyleRule::normalize([
            'field' => $field,
            'condition' => $condition,
            'value' => $value,
            'style' => $style,
        ]);

        if ($rule === null) {
            throw new \InvalidArgumentException(
                "Invalid style condition: {$condition}. Valid conditions: ".implode(', ', StyleRule::CONDITIONS)
            );
        }

        return $rule;
    }

    /**
     * Splits the definition into exactly [field, condition, value, style].
     *
     * The positional form is inherently ambiguous: the STYLE is colon-rich by
     * nature (`background:#eee;color:#111`), so `explode(':', $d, 4)` gives the
     * style everything after the third colon — which silently truncates any
     * VALUE containing a colon and leaks the remainder into the CSS.
     * `start_at:==:12:30:background:#eee;` parsed as value "12" and style
     * "30:background:#eee;": a rule that never matches and emits garbage, with
     * no error. FilterParser and ColumnParser already fixed this same class of
     * bug for their `options=` lists; this is the equivalent for --style=.
     *
     * The cure is an explicit end-of-value marker rather than guesswork: when
     * `:style=` appears, the value is everything between the condition and the
     * marker and the style is everything after it. Without the marker the old
     * positional behaviour is kept verbatim, so every existing --style= call
     * parses exactly as before.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function splitSegments(string $definition): array
    {
        $markerAt = strpos($definition, self::STYLE_MARKER);

        if ($markerAt !== false) {
            $head = substr($definition, 0, $markerAt);
            $style = substr($definition, $markerAt + strlen(self::STYLE_MARKER));

            // field:condition:value — the value keeps every colon it contains,
            // because the marker, not a count, ended it.
            $headParts = explode(':', $head, 3);

            if (count($headParts) < 3) {
                throw new \InvalidArgumentException('Style syntax requires: field:condition:value:style=<css>');
            }

            return [$headParts[0], $headParts[1], $headParts[2], $style];
        }

        $parts = explode(':', $definition, 4);

        if (count($parts) < 4) {
            throw new \InvalidArgumentException('Style syntax requires: field:condition:value:style');
        }

        return [$parts[0], $parts[1], $parts[2], $parts[3]];
    }
}
