<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\DTO;

use PHPUnit\Framework\Attributes\Test;
use Ptah\DTO\FilterDTO;
use Ptah\Tests\TestCase;

/**
 * Unit tests for FilterDTO factory + validation, covering the operator
 * normalisation (item 3) and NULL-operator validity (item 2).
 */
class FilterDTOTest extends TestCase
{
    // ── fromArray: empty operator falls back to '=' (item 3) ────────────────────

    #[Test]
    public function empty_operator_falls_back_to_equals(): void
    {
        $dto = FilterDTO::fromArray(['field' => 'c', 'operator' => '', 'value' => '7']);

        $this->assertSame('=', $dto->operator);
        $this->assertTrue($dto->isValid());
    }

    #[Test]
    public function whitespace_operator_falls_back_to_equals(): void
    {
        $dto = FilterDTO::fromArray(['field' => 'c', 'operator' => '   ', 'value' => '7']);
        $this->assertSame('=', $dto->operator);
    }

    #[Test]
    public function array_operator_falls_back_to_equals(): void
    {
        $dto = FilterDTO::fromArray(['field' => 'c', 'operator' => ['x'], 'value' => '7']);
        $this->assertSame('=', $dto->operator);
    }

    #[Test]
    public function missing_operator_defaults_to_equals(): void
    {
        $dto = FilterDTO::fromArray(['field' => 'c', 'value' => '7']);
        $this->assertSame('=', $dto->operator);
    }

    // ── isValid ──────────────────────────────────────────────────────────────

    #[Test]
    public function null_operator_filter_is_valid_without_a_value(): void
    {
        $this->assertTrue((new FilterDTO(field: 'b_nnf', value: null, operator: 'IS NULL'))->isValid());
        $this->assertTrue((new FilterDTO(field: 'b_nnf', value: null, operator: 'IS NOT NULL'))->isValid());
    }

    #[Test]
    public function a_non_null_filter_still_requires_a_value(): void
    {
        $this->assertFalse((new FilterDTO(field: 'c', value: null, operator: '='))->isValid());
        $this->assertFalse((new FilterDTO(field: 'c', value: '', operator: '='))->isValid());
        $this->assertFalse((new FilterDTO(field: '', value: '7', operator: '='))->isValid());
    }

    #[Test]
    public function zero_is_a_valid_value(): void
    {
        // Regression for the empty('0') bug — '0' must survive validation.
        $this->assertTrue((new FilterDTO(field: 'amount', value: '0', operator: '>'))->isValid());
    }
}
