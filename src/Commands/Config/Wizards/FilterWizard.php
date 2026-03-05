<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Enums\CrudConfigEnums;

class FilterWizard
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Run interactive wizard to configure a filter
     */
    public function run(?array $existingFilter = null): ?array
    {
        $this->command->info("=== Filter Configuration Wizard ===");
        $this->command->newLine();

        $field = $this->command->ask("Filter field name", $existingFilter['colsFilterField'] ?? null);
        
        if (!$field) {
            $this->command->warn("Field name is required.");
            return null;
        }

        $label = $this->command->ask("Filter label", $existingFilter['colsFilterLabel'] ?? ucfirst($field));
        
        $type = $this->command->choice(
            "Filter type",
            CrudConfigEnums::FILTER_TYPES,
            $existingFilter['colsFilterType'] ?? 'text'
        );

        $operator = $this->command->choice(
            "Comparison operator",
            CrudConfigEnums::OPERATORS,
            $existingFilter['colsFilterOperator'] ?? '='
        );

        $filter = [
            'colsFilterField' => $field,
            'colsFilterLabel' => $label,
            'colsFilterType' => $type,
            'colsFilterOperator' => $operator,
        ];

        // Type-specific options
        if (in_array($type, ['select', 'searchdropdown'])) {
            if ($type === 'select') {
                $filter['colsFilterOptions'] = $this->askSelectOptions();
            } else {
                $filter = array_merge($filter, $this->askSearchDropdownOptions());
            }
        }

        // Relation filter
        if ($this->command->confirm("Filter through relation (whereHas)?", false)) {
            $filter['colsFilterWhereHas'] = $this->command->ask("Relation name");
            $filter['colsFilterRelationField'] = $this->command->ask("Field in related table", $field);
            
            $aggregate = $this->command->choice(
                "Aggregate function (optional)",
                array_merge(['none'], CrudConfigEnums::AGGREGATES),
                'none'
            );
            
            if ($aggregate !== 'none') {
                $filter['colsFilterAggregate'] = $aggregate;
            }
        }

        $this->previewFilter($filter);

        if (!$this->command->confirm("Save this filter?", true)) {
            return $this->run($filter);
        }

        return $filter;
    }

    /**
     * Ask select options
     */
    protected function askSelectOptions(): array
    {
        $this->command->info("Enter filter options:");
        $options = [];

        while ($this->command->confirm("Add option?", true)) {
            $value = $this->command->ask("Value");
            $label = $this->command->ask("Label", ucfirst($value));
            $options[$value] = $label;
        }

        return $options;
    }

    /**
     * Ask SearchDropdown options
     */
    protected function askSearchDropdownOptions(): array
    {
        return [
            'colsFilterSdTable' => $this->command->ask("Search table"),
            'colsFilterSdSelectColumn' => $this->command->ask("Display column", 'name'),
            'colsFilterSdValueColumn' => $this->command->ask("Value column", 'id'),
        ];
    }

    /**
     * Preview filter configuration
     */
    protected function previewFilter(array $filter): void
    {
        $this->command->newLine();
        $this->command->info("=== Filter Preview ===");
        $this->command->table(
            ['Property', 'Value'],
            collect($filter)->map(fn($value, $key) => [
                $key,
                is_array($value) ? json_encode($value) : $value
            ])->toArray()
        );
        $this->command->newLine();
    }
}
