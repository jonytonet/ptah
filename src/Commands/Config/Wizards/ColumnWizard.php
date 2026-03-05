<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Commands\Config\ModelIntrospector;

class ColumnWizard
{
    protected Command $command;
    protected ModelIntrospector $introspector;

    public function __construct(Command $command, ModelIntrospector $introspector)
    {
        $this->command = $command;
        $this->introspector = $introspector;
    }

    /**
     * Run interactive wizard to configure a column
     */
    public function run(string $modelClass, ?array $existingColumn = null): ?array
    {
        $this->command->info("=== Column Configuration Wizard ===");
        $this->command->newLine();

        // Step 1: Basic Information
        $column = $this->askBasicInfo($existingColumn);
        
        if (!$column) {
            return null; // User cancelled
        }

        // Step 2: Renderer Configuration
        if ($this->command->confirm("Configure renderer options?", true)) {
            $column = array_merge($column, $this->askRendererOptions($column));
        }

        // Step 3: Mask Configuration
        if (in_array($column['colsTipo'], ['text', 'number']) && $this->command->confirm("Configure input mask?", false)) {
            $column = array_merge($column, $this->askMaskOptions());
        }

        // Step 4: Validation
        if ($this->command->confirm("Add validation rules?", true)) {
            $column = array_merge($column, $this->askValidationRules($column));
        }

        // Step 5: Relation Configuration (if foreign key or searchdropdown)
        if ($column['colsTipo'] === 'searchdropdown' || $this->introspector->isForeignKey($column['colsNomeFisico'])) {
            if ($this->command->confirm("Configure relation?", true)) {
                $column = array_merge($column, $this->askRelationOptions($column));
            }
        }

        // Step 6: SearchDropdown Configuration
        if ($column['colsTipo'] === 'searchdropdown') {
            $column = array_merge($column, $this->askSearchDropdownOptions());
        }

        // Step 7: Totalizer Configuration
        if (in_array($column['colsTipo'], ['number']) && $this->command->confirm("Add to totalizer?", false)) {
            $column = array_merge($column, $this->askTotalizerOptions());
        }

        // Preview
        $this->previewColumn($column);

        if (!$this->command->confirm("Save this column configuration?", true)) {
            return $this->run($modelClass, $column); // Restart wizard
        }

        return $column;
    }

    /**
     * Ask basic column information
     */
    protected function askBasicInfo(?array $existing = null): ?array
    {
        $field = $this->command->ask("Field name (physical column)", $existing['colsNomeFisico'] ?? null);
        
        if (!$field) {
            $this->command->warn("Field name is required.");
            return null;
        }

        $label = $this->command->ask("Label (display name)", $existing['colsNomeLogico'] ?? $this->generateLabel($field));
        
        $type = $this->command->choice(
            "Column type",
            CrudConfigEnums::COLUMN_TYPES,
            $existing['colsTipo'] ?? 0
        );

        $align = $this->command->choice(
            "Text alignment",
            CrudConfigEnums::ALIGNMENTS,
            $existing['colsAlign'] ?? 'text-start'
        );

        $width = $this->command->ask("Column width (e.g., 120px, 20%, auto)", $existing['colsWidth'] ?? 'auto');
        $placeholder = $this->command->ask("Placeholder text", $existing['colsPlaceholder'] ?? '');
        $helpText = $this->command->ask("Help text", $existing['colsHelpText'] ?? '');
        $defaultValue = $this->command->ask("Default value", $existing['colsDefaultValue'] ?? '');

        return [
            'colsNomeFisico' => $field,
            'colsNomeLogico' => $label,
            'colsTipo' => $type,
            'colsAlign' => $align,
            'colsWidth' => $width,
            'colsPlaceholder' => $placeholder,
            'colsHelpText' => $helpText,
            'colsDefaultValue' => $defaultValue,
            'colsGravar' => $this->command->confirm("Save to database?", $existing['colsGravar'] ?? true),
            'colsRequired' => $this->command->confirm("Required field?", $existing['colsRequired'] ?? false),
            'colsIsFilterable' => $this->command->confirm("Filterable?", $existing['colsIsFilterable'] ?? true),
            'colsVisibleList' => $this->command->confirm("Visible in list?", $existing['colsVisibleList'] ?? true),
            'colsEditableForm' => $this->command->confirm("Editable in form?", $existing['colsEditableForm'] ?? true),
        ];
    }

    /**
     * Ask renderer options
     */
    protected function askRendererOptions(array $column): array
    {
        $renderer = $this->command->choice(
            "Renderer type",
            CrudConfigEnums::RENDERERS,
            $column['colsRenderer'] ?? 'text'
        );

        $config = ['colsRenderer' => $renderer];

        // Renderer-specific options
        match ($renderer) {
            'badge', 'pill' => $config = array_merge($config, $this->askBadgeOptions()),
            'money' => $config = array_merge($config, $this->askMoneyOptions()),
            'number' => $config = array_merge($config, $this->askNumberOptions()),
            'link' => $config = array_merge($config, $this->askLinkOptions()),
            'image' => $config = array_merge($config, $this->askImageOptions()),
            'truncate' => $config['colsRendererMaxChars'] = (int) $this->command->ask("Max characters", 50),
            'date' => $config['colsRendererFormat'] = $this->command->ask("Date format", 'd/m/Y'),
            'datetime' => $config['colsRendererFormat'] = $this->command->ask("Datetime format", 'd/m/Y H:i:s'),
            default => null,
        };

        return $config;
    }

    /**
     * Ask badge/pill color configuration
     */
    protected function askBadgeOptions(): array
    {
        $this->command->info("Configure badge colors for each value:");
        $badges = [];

        while ($this->command->confirm("Add badge color mapping?", true)) {
            $value = $this->command->ask("Value");
            $color = $this->command->choice("Color", CrudConfigEnums::BADGE_COLORS, 0);
            $badges[$value] = $color;
        }

        return ['colsRendererBadges' => $badges];
    }

    /**
     * Ask money renderer options
     */
    protected function askMoneyOptions(): array
    {
        return [
            'colsRendererCurrency' => $this->command->choice("Currency", CrudConfigEnums::CURRENCIES, 'BRL'),
            'colsRendererDecimals' => (int) $this->command->ask("Decimal places", 2),
        ];
    }

    /**
     * Ask number renderer options
     */
    protected function askNumberOptions(): array
    {
        return [
            'colsRendererDecimals' => (int) $this->command->ask("Decimal places", 0),
            'colsRendererPrefix' => $this->command->ask("Prefix", ''),
            'colsRendererSuffix' => $this->command->ask("Suffix", ''),
        ];
    }

    /**
     * Ask link renderer options
     */
    protected function askLinkOptions(): array
    {
        return [
            'colsRendererLink' => $this->command->ask("Link URL pattern (use %field% for field values)"),
            'colsRendererTarget' => $this->command->choice("Target", ['_self', '_blank'], '_self'),
        ];
    }

    /**
     * Ask image renderer options
     */
    protected function askImageOptions(): array
    {
        return [
            'colsRendererImageWidth' => $this->command->ask("Image width", '50px'),
            'colsRendererImageHeight' => $this->command->ask("Image height", 'auto'),
        ];
    }

    /**
     * Ask mask options
     */
    protected function askMaskOptions(): array
    {
        $mask = $this->command->choice("Input mask", array_merge(['none'], CrudConfigEnums::MASKS), 'none');
        
        if ($mask === 'none') {
            return [];
        }

        $config = ['colsMask' => $mask];

        if (str_contains($mask, 'money')) {
            $config['colsMaskDecimalPlaces'] = (int) $this->command->ask("Decimal places", 2);
            $config['colsMaskTransform'] = $this->command->choice(
                "Transform on save",
                CrudConfigEnums::MASK_TRANSFORMS,
                'money_to_float'
            );
        }

        return $config;
    }

    /**
     * Ask validation rules
     */
    protected function askValidationRules(array $column): array
    {
        $rules = [];

        $this->command->info("Select validation rules:");
        
        foreach (CrudConfigEnums::COMMON_VALIDATIONS as $key => $description) {
            if ($this->command->confirm("  {$description}?", false)) {
                $rules[] = $key;
            }
        }

        // Custom rules
        if ($this->command->confirm("Add custom validation rules?", false)) {
            $custom = $this->command->ask("Enter custom Laravel validation rules (e.g., email|unique:users)");
            $rules = array_merge($rules, explode('|', $custom));
        }

        return [
            'colsValidation' => array_unique($rules),
            'colsValidationMessage' => $this->command->ask("Custom validation error message", ''),
        ];
    }

    /**
     * Ask relation options
     */
    protected function askRelationOptions(array $column): array
    {
        $relationName = $this->command->ask(
            "Relation name",
            $this->introspector->suggestRelationName($column['colsNomeFisico'])
        );

        return [
            'colsRelation' => $relationName,
            'colsRelationTable' => $this->command->ask("Related table name"),
            'colsRelationJoinColumn' => $this->command->ask("Join column (foreign key)", $column['colsNomeFisico']),
            'colsRelationDisplayColumn' => $this->command->ask("Display column", 'name'),
        ];
    }

    /**
     * Ask SearchDropdown options
     */
    protected function askSearchDropdownOptions(): array
    {
        return [
            'colsSdTable' => $this->command->ask("Search table"),
            'colsSdSelectColumn' => $this->command->ask("Display column", 'name'),
            'colsSdValueColumn' => $this->command->ask("Value column", 'id'),
            'colsSdFilterWhere' => $this->command->ask("WHERE filter (optional)", ''),
            'colsSdOrderBy' => $this->command->ask("ORDER BY", 'name ASC'),
            'colsSdLimit' => (int) $this->command->ask("Search result limit", 20),
        ];
    }

    /**
     * Ask totalizer options
     */
    protected function askTotalizerOptions(): array
    {
        $type = $this->command->choice("Totalizer function", CrudConfigEnums::TOTALIZER_TYPES, 'sum');
        $format = $this->command->choice("Display format", CrudConfigEnums::TOTALIZER_FORMATS, 'number');

        $config = [
            'colsTotal' => true,
            'totalizadorType' => $type,
            'totalizadorFormat' => $format,
        ];

        if ($format === 'currency') {
            $config['totalizadorCurrency'] = $this->command->choice("Currency", CrudConfigEnums::CURRENCIES, 'BRL');
            $config['totalizadorDecimals'] = (int) $this->command->ask("Decimal places", 2);
        }

        return $config;
    }

    /**
     * Preview column configuration
     */
    protected function previewColumn(array $column): void
    {
        $this->command->newLine();
        $this->command->info("=== Column Preview ===");
        $this->command->table(
            ['Property', 'Value'],
            collect($column)->map(fn($value, $key) => [
                $key,
                is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? 'true' : 'false') : $value)
            ])->toArray()
        );
        $this->command->newLine();
    }

    /**
     * Generate label from field name
     */
    protected function generateLabel(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }
}
