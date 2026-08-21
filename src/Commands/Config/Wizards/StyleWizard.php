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
        $this->command->info('=== Style Rule Configuration Wizard ===');
        $this->command->newLine();

        $field = $this->command->ask('Field to check', $existingStyle['field'] ?? null);

        if (! $field) {
            $this->command->warn('Field name is required.');

            return null;
        }

        $condition = $this->command->choice(
            'Comparison operator',
            CrudConfigEnums::STYLE_CONDITIONS,
            $existingStyle['condition'] ?? '=='
        );

        $value = $this->command->ask('Comparison value', $existingStyle['value'] ?? '');

        $this->command->info('Enter CSS styles to apply when condition is met:');

        // No re-run (usuario declinou salvar), o shape canonico guarda o CSS
        // como string unica — nao da para decompor de volta em background/cor/
        // peso, entao o conjunto anterior inteiro vira default do customCss.
        $backgroundColor = $this->command->ask('Background color (e.g., #FFE, red)', '');
        $textColor = $this->command->ask('Text color', '');
        $fontWeight = $this->command->choice(
            'Font weight',
            ['normal', 'bold', 'lighter', 'bolder'],
            'normal'
        );
        $customCss = $this->command->ask('Custom CSS properties (e.g., border:2px solid red)', $existingStyle['style'] ?? '');

        $css = $this->buildCss($backgroundColor, $textColor, $fontWeight, $customCss);

        $style = [
            'field' => $field,
            'condition' => $condition,
            'value' => $value,
            'style' => $css,
        ];

        $this->previewStyle($style);

        if (! $this->command->confirm('Save this style rule?', true)) {
            return $this->run($style);
        }

        return $style;
    }

    /**
     * Assembles the inline CSS declaration list from the wizard's individual
     * prompts, skipping whichever were left blank.
     */
    protected function buildCss(string $backgroundColor, string $textColor, string $fontWeight, string $customCss): string
    {
        $declarations = [];

        if ($backgroundColor !== '') {
            $declarations[] = "background:{$backgroundColor};";
        }

        if ($textColor !== '') {
            $declarations[] = "color:{$textColor};";
        }

        if ($fontWeight !== '') {
            $declarations[] = "font-weight:{$fontWeight};";
        }

        if ($customCss !== '') {
            $declarations[] = rtrim($customCss, ';').';';
        }

        return implode('', $declarations);
    }

    /**
     * Preview style configuration
     */
    protected function previewStyle(array $style): void
    {
        $this->command->newLine();
        $this->command->info('=== Style Rule Preview ===');
        $this->command->line("When {$style['field']} {$style['condition']} {$style['value']}:");
        $this->command->table(
            ['Property', 'Value'],
            [['style', $style['style'] ?: '(not set)']]
        );
        $this->command->newLine();
    }
}
