<?php

namespace Ptah\Commands\Config\Parsers;

use Illuminate\Support\Str;

class ActionParser
{
    /**
     * Parse action definition string
     * 
     * Format: name:type:value:option1=value1:option2=value2
     * Example: approve:livewire:approve(%id%):icon=bx-check:color=success:permission=admin
     */
    public function parse(string $definition): array
    {
        $parts = explode(':', $definition);
        
        if (count($parts) < 3) {
            throw new \InvalidArgumentException("Action syntax requires at least: name:type:value");
        }

        $name = array_shift($parts);
        $type = array_shift($parts);
        $value = array_shift($parts);

        $config = [
            'colsNomeLogico' => $name,
            'colsTipo' => 'action',
            'actionType' => $type,
            'actionValue' => $value,
            'actionIcon' => '',
            'actionColor' => 'primary',
            'actionPermission' => '',
        ];

        // Process remaining options
        foreach ($parts as $opt) {
            if (str_contains($opt, '=')) {
                [$k, $v] = explode('=', $opt, 2);
                $config['action' . Str::studly($k)] = $v;
            }
        }

        return $config;
    }
}
