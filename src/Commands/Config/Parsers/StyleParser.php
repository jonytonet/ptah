<?php

namespace Ptah\Commands\Config\Parsers;

use Ptah\Support\StyleRule;

class StyleParser
{
    /**
     * Parse style definition string
     *
     * Format: field:condition:value:style
     * Example: status:==:cancelled:background:#FEE2E2;color:#991B1B;
     *
     * `condition` accepts any of StyleRule::CONDITIONS or its aliases
     * (eq/ne/lt/gt/lte/gte/=) — anything else (e.g. LIKE) is rejected here,
     * because HasCrudRenderers::getRowStyle()'s match() expression has no arm
     * for it and would silently never apply the style.
     */
    public function parse(string $definition): array
    {
        $parts = explode(':', $definition, 4);

        if (count($parts) < 4) {
            throw new \InvalidArgumentException('Style syntax requires: field:condition:value:style');
        }

        [$field, $condition, $value, $style] = $parts;

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
}
