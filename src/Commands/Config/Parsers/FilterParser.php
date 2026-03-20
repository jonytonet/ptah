<?php

namespace Ptah\Commands\Config\Parsers;

use Illuminate\Support\Str;

class FilterParser
{
    /**
     * Parse filter definition string
     * 
     * Format: field:type:option1=value1:option2=value2
     * Example: supplier_name:text:label=Fornecedor:whereHas=supplier:field=name:operator=LIKE
     */
    public function parse(string $definition): array
    {
        $parts = explode(':', $definition);
        $field = array_shift($parts);
        $type = array_shift($parts) ?? 'text';

        $config = [
            'field' => $field,
            'label' => Str::title(str_replace('_', ' ', $field)),
            'colsFilterType' => $type,
            'defaultOperator' => '=',
            'whereHas' => '',
            'field_relation' => '',
            'aggregate' => '',
        ];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                
                // Handle options key
                if ($k === 'options') {
                    $config['options'] = $v;
                } else {
                    $config[$k] = $v;
                }
            }
        }

        return $config;
    }
}
