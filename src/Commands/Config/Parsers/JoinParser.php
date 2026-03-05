<?php

namespace Ptah\Commands\Config\Parsers;

class JoinParser
{
    /**
     * Parse join definition string
     * 
     * Format: type:table:first=second:option1=value1
     * Example: left:suppliers:products.supplier_id=suppliers.id:distinct:select=suppliers.name:supplier_name,suppliers.cnpj:supplier_cnpj
     */
    public function parse(string $definition): array
    {
        $parts = explode(':', $definition);

        if (count($parts) < 3) {
            throw new \InvalidArgumentException("JOIN syntax requires at least: type:table:first=second");
        }

        $config = [
            'type' => array_shift($parts),
            'table' => array_shift($parts),
            'distinct' => false,
            'selectRaw' => '',
            'first' => '',
            'second' => '',
        ];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                if (str_starts_with($part, 'select=')) {
                    // Parse select columns
                    $selectPart = str_replace('select=', '', $part);
                    $config['selectRaw'] = $selectPart;
                } else {
                    // ON condition: products.supplier_id=suppliers.id
                    [$first, $second] = explode('=', $part, 2);
                    if (empty($config['first'])) {
                        $config['first'] = $first;
                        $config['second'] = $second;
                    }
                }
            } elseif ($part === 'distinct') {
                $config['distinct'] = true;
            }
        }

        return $config;
    }
}
