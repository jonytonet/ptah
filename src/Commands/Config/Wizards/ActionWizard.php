<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Enums\CrudConfigEnums;

class ActionWizard
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Run interactive wizard to configure an action
     */
    public function run(?array $existingAction = null): ?array
    {
        $this->command->info("=== Action Configuration Wizard ===");
        $this->command->newLine();

        $name = $this->command->ask("Action name (e.g., approve, reject)", $existingAction['actionName'] ?? null);
        
        if (!$name) {
            $this->command->warn("Action name is required.");
            return null;
        }

        $label = $this->command->ask("Action label", $existingAction['actionLabel'] ?? ucfirst($name));
        
        $type = $this->command->choice(
            "Action type",
            CrudConfigEnums::ACTION_TYPES,
            $existingAction['actionType'] ?? 'livewire'
        );

        $value = $this->command->ask(
            "Action value (method name, URL, or JavaScript)",
            $existingAction['actionValue'] ?? $name
        );

        $icon = $this->command->ask("Icon class (e.g., bx-check, fa-check)", $existingAction['actionIcon'] ?? '');
        
        $color = $this->command->choice(
            "Button color",
            CrudConfigEnums::ACTION_COLORS,
            $existingAction['actionColor'] ?? 'primary'
        );

        $position = $this->command->choice(
            "Action position",
            ['row', 'bulk', 'both'],
            $existingAction['actionPosition'] ?? 'row'
        );

        $confirm = $this->command->confirm("Require confirmation?", $existingAction['actionConfirm'] ?? false);
        $confirmMessage = '';
        
        if ($confirm) {
            $confirmMessage = $this->command->ask(
                "Confirmation message",
                $existingAction['actionConfirmMessage'] ?? "Are you sure you want to {$name}?"
            );
        }

        $permission = $this->command->ask("Required permission (optional)", $existingAction['actionPermission'] ?? '');

        $action = [
            'actionName' => $name,
            'actionLabel' => $label,
            'actionType' => $type,
            'actionValue' => $value,
            'actionIcon' => $icon,
            'actionColor' => $color,
            'actionPosition' => $position,
            'actionConfirm' => $confirm,
            'actionConfirmMessage' => $confirmMessage,
            'actionPermission' => $permission,
        ];

        $this->previewAction($action);

        if (!$this->command->confirm("Save this action?", true)) {
            return $this->run($action);
        }

        return $action;
    }

    /**
     * Preview action configuration
     */
    protected function previewAction(array $action): void
    {
        $this->command->newLine();
        $this->command->info("=== Action Preview ===");
        $this->command->table(
            ['Property', 'Value'],
            collect($action)->map(fn($value, $key) => [
                $key,
                is_bool($value) ? ($value ? 'true' : 'false') : $value
            ])->toArray()
        );
        $this->command->newLine();
    }
}
