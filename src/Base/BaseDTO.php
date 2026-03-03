<?php

declare(strict_types=1);

namespace Ptah\Base;

use Illuminate\Http\Request;

/**
 * Abstract base DTO (Data Transfer Object).
 *
 * All DTO classes must extend this class and implement the
 * array and Request conversion methods.
 */
abstract class BaseDTO
{
    /**
     * Creates a DTO instance from a plain data array.
     *
     * @param array<string, mixed> $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Creates a DTO instance from a Laravel Request object.
     */
    abstract public static function fromRequest(Request $request): static;

    /**
     * Converts the DTO to an associative array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
