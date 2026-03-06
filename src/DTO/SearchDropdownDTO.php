<?php

declare(strict_types=1);

namespace Ptah\DTO;

/**
 * DTO for carrying SearchDropdown query parameters.
 */
readonly class SearchDropdownDTO
{
    public function __construct(
        public ?string $searchTerm,
        public string  $value,
        public string  $label,
        public ?string $labelTwo   = null,
        public ?string $labelThree = null,
        public string  $orderByRaw     = 'id asc',
        public int     $limit          = 10,
        public array   $arraySearch    = [],
        public array   $dataFilter     = [],
    ) {}
}
