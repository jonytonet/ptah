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
            throw new \InvalidArgumentException('Action syntax requires at least: name:type:value');
        }

        $name = array_shift($parts);
        $type = array_shift($parts);

        // Collect value parts: everything before the first key=value option.
        // This lets URL values like 'https://...' be preserved intact.
        $valueParts = [];
        while (! empty($parts) && ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*=/', $parts[0])) {
            $valueParts[] = array_shift($parts);
        }
        $value = implode(':', $valueParts);

        $config = [
            'colsNomeLogico' => $name,
            'colsTipo' => 'action',
            'actionType' => $type,
            'actionValue' => $value,
            'actionIcon' => '',
            'actionColor' => 'primary',
            'actionPermission' => '',
        ];

        // Process remaining key=value options
        foreach ($parts as $opt) {
            if (str_contains($opt, '=')) {
                [$k, $v] = explode('=', $opt, 2);
                $config['action'.Str::studly($k)] = $v;
            }
        }

        return $config;
    }

    /**
     * An action in the shape the runtime reads: a column of type `action`
     * inside `cols` — the same one the visual editor writes
     * (CrudConfig::addAction). Accepts parse() output and ActionWizard output
     * (`actionName`/`actionLabel`).
     *
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public static function asColumn(array $action): array
    {
        $label = (string) ($action['colsNomeLogico'] ?? $action['actionLabel'] ?? $action['actionName'] ?? '');
        unset($action['actionName'], $action['actionLabel']);

        return array_merge([
            'actionType' => 'link',
            'actionValue' => '',
            'actionIcon' => 'bx bx-link',
            'actionColor' => 'primary',
            'actionPermission' => '',
        ], array_filter($action, fn ($v) => $v !== '' && $v !== null), [
            'colsNomeLogico' => $label,
            'colsNomeFisico' => 'id',
            'colsTipo' => 'action',
            'colsGravar' => false,
            'colsRequired' => false,
            'colsIsFilterable' => false,
        ]);
    }
}
