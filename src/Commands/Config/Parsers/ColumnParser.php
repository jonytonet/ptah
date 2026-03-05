<?php

namespace Ptah\Commands\Config\Parsers;

use Illuminate\Support\Str;

class ColumnParser
{
    /**
     * Parse column definition string
     * 
     * Format: field:type:modifier1:modifier2:option1=value1:option2=value2
     * Example: name:text:required:label=Product Name:placeholder=Enter name
     */
    public function parse(string $definition): array
    {
        $parts = explode(':', $definition);
        $field = array_shift($parts);
        $type = array_shift($parts) ?? 'text';

        $config = [
            'colsNomeFisico' => $field,
            'colsNomeLogico' => Str::title(str_replace('_', ' ', $field)),
            'colsTipo' => $type,
            'colsAlign' => 'text-start',
            'colsGravar' => true,
            'colsRequired' => false,
            'colsIsFilterable' => true,
            'colsVisibleList' => true,
            'colsEditableForm' => true,
        ];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $config = $this->applyKeyValue($config, $key, $value);
            } else {
                $config = $this->applyModifier($config, $part);
            }
        }

        return $config;
    }

    /**
     * Apply boolean modifiers
     */
    protected function applyModifier(array $config, string $modifier): array
    {
        return match ($modifier) {
            'required' => array_merge($config, ['colsRequired' => true]),
            'nullable' => array_merge($config, ['colsRequired' => false]),
            'readonly' => array_merge($config, ['colsGravar' => false]),
            'hidden' => array_merge($config, ['colsVisibleList' => false]),
            'sortable' => array_merge($config, ['colsOrderBy' => $config['colsNomeFisico']]),
            'filterable' => array_merge($config, ['colsIsFilterable' => true]),
            'not_filterable' => array_merge($config, ['colsIsFilterable' => false]),
            default => $config,
        };
    }

    /**
     * Apply key=value options
     */
    protected function applyKeyValue(array $config, string $key, string $value): array
    {
        // Mapping shortcuts to full property names
        $keyMap = [
            'label' => 'colsNomeLogico',
            'placeholder' => 'colsPlaceholder',
            'align' => 'colsAlign',
            'renderer' => 'colsRenderer',
            'mask' => 'colsMask',
            'relation' => 'colsRelacao',
            'relation_display' => 'colsRelacaoExibe',
            'relation_nested' => 'colsRelacaoNested',
            'min_width' => 'colsMinWidth',
            'cell_style' => 'colsCellStyle',
            'cell_class' => 'colsCellClass',
            'cell_icon' => 'colsCellIcon',
            'source' => 'colsSource',
            'method' => 'colsMetodoCustom',
            'method_raw' => 'colsMetodoRaw',
            'order_by' => 'colsOrderBy',

            // SearchDropdown
            'sd_mode' => 'colsSDMode',
            'sd_model' => 'colsSDModel',
            'sd_service' => 'colsSDService',
            'sd_service_method' => 'colsSDServiceMethod',
            'sd_value' => 'colsSDValueField',
            'sd_label' => 'colsSDLabelField',
            'sd_label_secondary' => 'colsSDLabelSecondary',
            'sd_order_by' => 'colsSDOrderBy',
            'sd_limit' => 'colsSDLimit',
            'sd_placeholder' => 'colsSDPlaceholder',
            'sd_filters' => 'colsSDFilters',

            // Renderer specific
            'currency' => 'colsRendererCurrency',
            'decimals' => 'colsRendererDecimals',
            'bool_true' => 'colsRendererBoolTrue',
            'bool_false' => 'colsRendererBoolFalse',
            'link_template' => 'colsRendererLinkTemplate',
            'link_label' => 'colsRendererLinkLabel',
            'link_new_tab' => 'colsRendererLinkNewTab',
            'image_width' => 'colsRendererImageWidth',
            'image_height' => 'colsRendererImageHeight',
            'max_chars' => 'colsRendererMaxChars',
            'locale' => 'colsRendererLocale',
            'progress_max' => 'colsRendererMax',
            'progress_color' => 'colsRendererColor',
            'rating_max' => 'colsRendererMax',
            'duration_unit' => 'colsRendererDurationUnit',
            'qr_size' => 'colsRendererQrSize',

            // Mask
            'mask_regex' => 'colsMaskRegex',
            'mask_transform' => 'colsMaskTransform',

            // Totalizer
            'totalizer' => 'totalizadorType',
            'totalizer_format' => 'totalizadorFormat',
            'totalizer_label' => 'totalizadorLabel',
            'totalizer_enabled' => 'totalizadorEnabled',
        ];

        $mappedKey = $keyMap[$key] ?? $key;

        // Special parsing for complex fields
        if ($key === 'validation') {
            $config['colsValidations'] = $this->parseValidations($value);
        } elseif ($key === 'options') {
            $config['colsSelect'] = $this->parseOptions($value);
        } elseif ($key === 'badges') {
            $config['colsRendererBadges'] = $this->parseBadges($value);
        } elseif ($mappedKey === 'totalizadorType') {
            $config['totalizadorEnabled'] = true;
            $config['totalizadorType'] = $value;
        } else {
            $config[$mappedKey] = $this->castValue($value);
        }

        return $config;
    }

    /**
     * Parse validation rules
     * Format: email|unique:products,email|maxLength:255|min:0
     */
    protected function parseValidations(string $value): array
    {
        return array_map('trim', explode('|', $value));
    }

    /**
     * Parse select options
     * Format: active:Active,inactive:Inactive,pending:Pending
     */
    protected function parseOptions(string $value): string
    {
        return $value; // Keep original format
    }

    /**
     * Parse badge configurations
     * Format: active:green:Ativo,inactive:gray:Inativo,pending:yellow:Pendente
     */
    protected function parseBadges(string $value): array
    {
        $badges = [];
        foreach (explode(',', $value) as $badge) {
            $parts = explode(':', $badge, 3);
            if (count($parts) >= 2) {
                $badges[] = [
                    'value' => $parts[0],
                    'color' => $parts[1],
                    'label' => $parts[2] ?? Str::title($parts[0]),
                ];
            }
        }
        return $badges;
    }

    /**
     * Cast string value to appropriate type
     */
    protected function castValue(string $value): mixed
    {
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if (is_numeric($value)) return is_float($value + 0) ? (float)$value : (int)$value;
        return $value;
    }
}
