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
        $this->command->info('=== Filter Configuration Wizard ===');
        $this->command->newLine();

        $field = $this->command->ask('Filter field name', $existingFilter['field'] ?? null);

        if (! $field) {
            $this->command->warn('Field name is required.');

            return null;
        }

        $label = $this->command->ask('Filter label', $existingFilter['label'] ?? ucfirst($field));

        $type = $this->command->choice(
            'Filter type',
            CrudConfigEnums::FILTER_TYPES,
            $existingFilter['colsFilterType'] ?? 'text'
        );

        $operator = $this->command->choice(
            'Comparison operator',
            CrudConfigEnums::OPERATORS,
            $existingFilter['operator'] ?? '='
        );

        // O vocabulario do runtime (FilterService / painel de filtros), o
        // mesmo que FilterRule::normalize() produz.
        $filter = [
            'field' => $field,
            'label' => $label,
            'colsFilterType' => $type,
            'operator' => $operator,
        ];

        // Type-specific options
        if (in_array($type, ['select', 'searchdropdown'])) {
            if ($type === 'select') {
                // `colsSelect` (label => value) e o que o painel de filtros
                // le; `colsFilterOptions` era descartado pelo FilterRule.
                $filter['colsSelect'] = $this->askSelectOptions();
            } else {
                $filter = array_merge($filter, $this->askSearchDropdownOptions());
            }
        }

        // Relation filter
        if ($this->command->confirm('Filter through relation (whereHas)?', false)) {
            // Os nomes que FilterRule::normalize() le — os `colsFilter*`
            // antigos sumiam na normalizacao e o filtro ficava sem relacao.
            $filter['whereHas'] = $this->command->ask('Relation name');
            $filter['field_relation'] = $this->command->ask('Field in related table', $field);

            $aggregate = $this->command->choice(
                'Aggregate function (optional)',
                array_merge(['none'], CrudConfigEnums::AGGREGATES),
                'none'
            );

            if ($aggregate !== 'none') {
                $filter['aggregate'] = $aggregate;
            }
        }

        $this->previewFilter($filter);

        if (! $this->command->confirm('Save this filter?', true)) {
            return $this->run($filter);
        }

        return $filter;
    }

    /**
     * Ask select options
     */
    protected function askSelectOptions(): array
    {
        $this->command->info('Enter filter options:');
        $options = [];

        while ($this->command->confirm('Add option?', true)) {
            $value = $this->command->ask('Value');
            $label = $this->command->ask('Label', ucfirst($value));
            $options[$label] = $value;
        }

        return $options;
    }

    /**
     * Ask SearchDropdown options
     */
    protected function askSearchDropdownOptions(): array
    {
        return [
            'colsFilterSdTable' => $this->command->ask('Search table'),
            'colsFilterSdSelectColumn' => $this->command->ask('Display column', 'name'),
            'colsFilterSdValueColumn' => $this->command->ask('Value column', 'id'),
        ];
    }

    /**
     * Preview filter configuration
     */
    protected function previewFilter(array $filter): void
    {
        $this->command->newLine();
        $this->command->info('=== Filter Preview ===');
        $this->command->table(
            ['Property', 'Value'],
            collect($filter)->map(fn ($value, $key) => [
                $key,
                is_array($value) ? json_encode($value) : $value,
            ])->toArray()
        );
        $this->command->newLine();
    }
}
