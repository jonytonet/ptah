<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Enums\CrudConfigEnums;

class StyleWizard
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Run interactive wizard to configure a style rule
     */
    public function run(?array $existingStyle = null): ?array
    {
        $this->command->info("=== Style Rule Configuration Wizard ===");
        $this->command->newLine();

        $field = $this->command->ask("Field to check", $existingStyle['styleField'] ?? null);
        
        if (!$field) {
            $this->command->warn("Field name is required.");
            return null;
        }

        $operator = $this->command->choice(
            "Comparison operator",
            CrudConfigEnums::OPERATORS,
            $existingStyle['styleOperator'] ?? '='
        );

        $value = $this->command->ask("Comparison value", $existingStyle['styleValue'] ?? '');

        $this->command->info("Enter CSS styles to apply when condition is met:");
        
        $backgroundColor = $this->command->ask("Background color (e.g., #FFE, red)", $existingStyle['styleBackgroundColor'] ?? '');
        $textColor = $this->command->ask("Text color", $existingStyle['styleColor'] ?? '');
        $fontWeight = $this->command->choice(
            "Font weight",
            ['normal', 'bold', 'lighter', 'bolder'],
            $existingStyle['styleFontWeight'] ?? 'normal'
        );
        $customCss = $this->command->ask("Custom CSS properties (e.g., border:2px solid red)", $existingStyle['styleCustom'] ?? '');

        $style = [
            'styleField' => $field,
            'styleOperator' => $operator,
            'styleValue' => $value,
            'styleBackgroundColor' => $backgroundColor,
            'styleColor' => $textColor,
            'styleFontWeight' => $fontWeight,
            'styleCustom' => $customCss,
        ];

        $this->previewStyle($style);

        if (!$this->command->confirm("Save this style rule?", true)) {
            return $this->run($style);
        }

        return $style;
    }

    /**
     * Preview style configuration
     */
    protected function previewStyle(array $style): void
    {
        $this->command->newLine();
        $this->command->info("=== Style Rule Preview ===");
        $this->command->line("When {$style['styleField']} {$style['styleOperator']} {$style['styleValue']}:");
        $this->command->table(
            ['Property', 'Value'],
            collect($style)->except(['styleField', 'styleOperator', 'styleValue'])->map(fn($value, $key) => [
                $key,
                $value ?: '(not set)'
            ])->toArray()
        );
        $this->command->newLine();
    }
}
