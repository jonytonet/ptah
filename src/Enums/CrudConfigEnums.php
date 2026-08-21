<?php

namespace Ptah\Enums;

use Ptah\Support\StyleRule;

class CrudConfigEnums
{
    /**
     * Available column input types for colsTipo
     */
    public const COLUMN_TYPES = [
        'text',
        'textarea',
        'number',
        'date',
        'datetime',
        'select',
        'searchdropdown',
        'boolean',
        'file',
        'image',
    ];

    /**
     * Available renderers for colsRenderer
     */
    public const RENDERERS = [
        'text',
        'badge',
        'pill',
        'boolean',
        'money',
        'date',
        'datetime',
        'link',
        'image',
        'truncate',
        'number',
        'filesize',
        'duration',
        'code',
        'color',
        'progress',
        'rating',
        'qrcode',
    ];

    /**
     * Available input masks for colsMask
     */
    public const MASKS = [
        'money_brl',
        'money_usd',
        'percent',
        'cpf',
        'cnpj',
        'rg',
        'pis',
        'ncm',
        'ean13',
        'phone',
        'cep',
        'plate',
        'credit_card',
        'date',
        'datetime',
        'time',
        'integer',
        'uppercase',
        'custom_regex',
    ];

    /**
     * Mask transformations for colsMaskTransform
     */
    public const MASK_TRANSFORMS = [
        'money_to_float',
        'digits_only',
        'plate_clean',
        'date_br_to_iso',
        'date_iso_to_br',
        'uppercase',
        'lowercase',
        'trim',
    ];

    /**
     * Filter types for colsFilterType
     */
    public const FILTER_TYPES = [
        'text',
        'number',
        'date',
        'select',
        'searchdropdown',
    ];

    /**
     * Comparison operators for filters (SQL-style — consumed by
     * FilterService/TextFilterStrategy/RelationFilterStrategy, which build a
     * WHERE clause with '=' and 'LIKE' literally).
     */
    public const OPERATORS = [
        '=',
        '!=',
        '>',
        '<',
        '>=',
        '<=',
        'LIKE',
    ];

    /**
     * Comparison conditions for conditionStyles (PHP-style — consumed by
     * HasCrudRenderers::getRowStyle()'s match() expression, which recognises
     * '==' and has no 'LIKE' arm at all). Deliberately NOT the same list as
     * OPERATORS above: a style condition of '=' or 'LIKE' would pass the old,
     * shared validation but never match anything at render time — the match()
     * default arm silently returns false, so the configured style just never
     * applies, with no error anywhere. See ConfigValidator::validateStyle().
     */
    // Fonte unica: StyleRule e o normalizador canonico de row styles; esta
    // constante existe para consumidores antigos e para o wizard, e referencia
    // a mesma lista para nunca driftar (achado de revisao, Fase 2.5 Onda II).
    public const STYLE_CONDITIONS = StyleRule::CONDITIONS;

    /**
     * SQL aggregation functions
     */
    public const AGGREGATES = [
        'SUM',
        'COUNT',
        'AVG',
        'MAX',
        'MIN',
    ];

    /**
     * Totalizer functions for totalizadorType
     */
    public const TOTALIZER_TYPES = [
        'sum',
        'avg',
        'count',
        'min',
        'max',
    ];

    /**
     * Totalizer display formats for totalizadorFormat
     */
    public const TOTALIZER_FORMATS = [
        'currency',
        'number',
        'integer',
    ];

    /**
     * Action types for actionType
     */
    public const ACTION_TYPES = [
        'link',
        'livewire',
        'javascript',
    ];

    /**
     * Action/Button colors for actionColor
     */
    public const ACTION_COLORS = [
        'primary',
        'success',
        'danger',
        'warning',
        'info',
        'secondary',
    ];

    /**
     * SQL JOIN types
     */
    public const JOIN_TYPES = [
        'left',
        'inner',
    ];

    /**
     * Badge/Pill colors for colsRendererBadges
     */
    public const BADGE_COLORS = [
        'green',
        'yellow',
        'red',
        'blue',
        'indigo',
        'purple',
        'pink',
        'gray',
    ];

    /**
     * Supported currency codes for colsRendererCurrency
     */
    public const CURRENCIES = [
        'BRL',
        'USD',
        'EUR',
    ];

    /**
     * Export orientations
     */
    public const ORIENTATIONS = [
        'landscape',
        'portrait',
    ];

    /**
     * UI themes
     */
    public const THEMES = [
        'light',
        'dark',
    ];

    /**
     * Text alignment options for colsAlign
     */
    public const ALIGNMENTS = [
        'text-start',
        'text-center',
        'text-end',
    ];

    /**
     * Common Laravel validation rules (for interactive mode)
     */
    public const COMMON_VALIDATIONS = [
        'email' => 'Valid email',
        'url' => 'Valid URL',
        'integer' => 'Integer number',
        'numeric' => 'Numeric',
        'cpf' => 'Valid CPF',
        'cnpj' => 'Valid CNPJ',
        'phone' => 'Valid phone',
        'alpha' => 'Letters only',
        'alphanum' => 'Letters and numbers',
    ];

    /**
     * Check if a column type is valid
     */
    public static function isValidColumnType(string $type): bool
    {
        return in_array($type, self::COLUMN_TYPES);
    }

    /**
     * Check if a renderer is valid
     */
    public static function isValidRenderer(string $renderer): bool
    {
        return in_array($renderer, self::RENDERERS);
    }

    /**
     * Check if a mask is valid
     */
    public static function isValidMask(string $mask): bool
    {
        return in_array($mask, self::MASKS);
    }

    /**
     * Check if an operator is valid
     */
    public static function isValidOperator(string $operator): bool
    {
        return in_array($operator, self::OPERATORS);
    }
}
