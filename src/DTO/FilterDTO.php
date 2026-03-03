<?php

declare(strict_types=1);

namespace Ptah\DTO;

use Illuminate\Http\Request;
use Ptah\Base\BaseDTO;

/**
 * DTO used by FilterService inside BaseCrud.
 *
 * Carries filter data between layers (Livewire → Service → Query Builder).
 * Extends BaseDTO so it satisfies any code that type-hints BaseDTO.
 */
final class FilterDTO extends BaseDTO
{
    /**
     * @param string $field     Field name or relation name
     * @param mixed  $value     Filter value (scalar, array for IN/BETWEEN)
     * @param string $operator  Operator: =, >, <, >=, <=, LIKE, BETWEEN, IN
     * @param string $type      Type: text, number, date, boolean, relation, array
     * @param array  $options   Extra options: ['relation' => 'relName', 'column' => 'col', 'whereHas' => '...']
     */
    public function __construct(
        public readonly string $field,
        public readonly mixed  $value,
        public readonly string $operator = '=',
        public readonly string $type     = 'text',
        public readonly array  $options  = [],
    ) {}

    /**
     * Creates an instance from a Request object (satisfies BaseDTO contract).
     * Delegates to fromArray() for consistency with the existing factory method.
     */
    public static function fromRequest(Request $request): static
    {
        return static::fromArray($request->all());
    }

    /**
     * Creates from array.
     */
    public static function fromArray(array $data): static
    {
        $field    = is_array($data['field'] ?? null) ? '' : (string) ($data['field'] ?? '');
        $operator = is_array($data['operator'] ?? null) ? '=' : (string) ($data['operator'] ?? '=');
        $value    = $data['value'] ?? null;
        $type     = $data['type'] ?? self::inferType($field, $value);

        return new static(
            field:    $field,
            value:    $value,
            operator: $operator,
            type:     $type,
            options:  $data['options'] ?? [],
        );
    }

    /**
     * Infers the type intelligently from the field name and value.
     */
    public static function inferType(string $field, mixed $value): string
    {
        if (is_array($value)) {
            return 'array';
        }

        // Date fields
        if (str_contains($field, '_at') || str_contains($field, '_date') || str_ends_with($field, '_date')) {
            return 'date';
        }

        // Relation fields
        if (str_ends_with($field, '_id')) {
            return 'relation';
        }

        // Boolean
        if (is_bool($value)) {
            return 'boolean';
        }

        // Numeric
        if (is_numeric($value) && !str_contains($field, 'name') && !str_contains($field, 'description')) {
            return 'number';
        }

        return 'text';
    }

    public function toArray(): array
    {
        return [
            'field'    => $this->field,
            'value'    => $this->value,
            'operator' => $this->operator,
            'type'     => $this->type,
            'options'  => $this->options,
        ];
    }

    public function isRelationFilter(): bool
    {
        return $this->type === 'relation' || isset($this->options['whereHas']);
    }

    public function isRangeFilter(): bool
    {
        return $this->operator === 'BETWEEN' && is_array($this->value) && count($this->value) === 2;
    }

    public function isValid(): bool
    {
        if ($this->field === '' || $this->value === null || $this->value === '') {
            return false;
        }

        if (is_array($this->value) && empty($this->value)) {
            return false;
        }

        return true;
    }
}
