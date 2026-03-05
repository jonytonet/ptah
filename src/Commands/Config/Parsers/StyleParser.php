<?php

namespace Ptah\Commands\Config\Parsers;

class StyleParser
{
    /**
     * Parse style definition string
     * 
     * Format: field:condition:value:style
     * Example: status:==:cancelled:background:#FEE2E2;color:#991B1B;
     */
    public function parse(string $definition): array
    {
        $parts = explode(':', $definition, 4);
        
        if (count($parts) < 4) {
            throw new \InvalidArgumentException("Style syntax requires: field:condition:value:style");
        }

        [$field, $condition, $value, $style] = $parts;

        return [
            'field' => $field,
            'condition' => $condition,
            'value' => $value,
            'style' => $style,
        ];
    }
}
