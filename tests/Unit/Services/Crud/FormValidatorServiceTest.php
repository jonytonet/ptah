<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Tests\TestCase;

/**
 * Covers FormValidatorService — the rich per-column validation that runs on
 * every BaseCrud save (required, numeric bounds, lengths, sets, regex,
 * cross-field confirmation and document rules).
 */
class FormValidatorServiceTest extends TestCase
{
    private FormValidatorService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FormValidatorService;
    }

    /** Validates a single field with the given column definition. */
    private function errorsFor(mixed $value, array $col, array $extraData = []): array
    {
        $col += ['colsNomeFisico' => 'field', 'colsNomeLogico' => 'Field'];

        return $this->validator->validate(['field' => $value] + $extraData, [$col]);
    }

    // ── required ──────────────────────────────────────────────────────────────

    #[Test]
    public function required_fails_on_empty_and_passes_on_filled(): void
    {
        $this->assertArrayHasKey('field', $this->errorsFor('', ['colsRequired' => true]));
        $this->assertArrayHasKey('field', $this->errorsFor(null, ['colsRequired' => true]));
        $this->assertSame([], $this->errorsFor('ok', ['colsRequired' => true]));

        // Legacy 'S' flag also counts as required.
        $this->assertArrayHasKey('field', $this->errorsFor('', ['colsRequired' => 'S']));
    }

    #[Test]
    public function optional_empty_field_skips_all_other_rules(): void
    {
        $errors = $this->errorsFor('', ['colsValidations' => ['email', 'minLength:5']]);

        $this->assertSame([], $errors, 'Optional empty fields must not run additional rules');
    }

    // ── numeric bounds and lengths ────────────────────────────────────────────

    #[Test]
    public function min_and_max_compare_numerically(): void
    {
        $this->assertArrayHasKey('field', $this->errorsFor('3', ['colsValidations' => ['min:5']]));
        $this->assertSame([], $this->errorsFor('7', ['colsValidations' => ['min:5']]));

        $this->assertArrayHasKey('field', $this->errorsFor('10', ['colsValidations' => ['max:5']]));
        $this->assertSame([], $this->errorsFor('2', ['colsValidations' => ['max:5']]));
    }

    #[Test]
    public function min_length_and_max_length_compare_string_length(): void
    {
        $this->assertArrayHasKey('field', $this->errorsFor('ab', ['colsValidations' => ['minLength:3']]));
        $this->assertSame([], $this->errorsFor('abc', ['colsValidations' => ['minLength:3']]));

        $this->assertArrayHasKey('field', $this->errorsFor('abcdef', ['colsValidations' => ['maxLength:5']]));
        $this->assertSame([], $this->errorsFor('abcde', ['colsValidations' => ['maxLength:5']]));
    }

    #[Test]
    public function digits_requires_the_exact_count(): void
    {
        $this->assertArrayHasKey('field', $this->errorsFor('123', ['colsValidations' => ['digits:4']]));
        $this->assertArrayHasKey('field', $this->errorsFor('12a4', ['colsValidations' => ['digits:4']]));
        $this->assertSame([], $this->errorsFor('1234', ['colsValidations' => ['digits:4']]));
    }

    // ── sets / regex ──────────────────────────────────────────────────────────

    #[Test]
    public function in_and_not_in_validate_against_the_option_list(): void
    {
        $this->assertArrayHasKey('field', $this->errorsFor('x', ['colsValidations' => ['in:a,b,c']]));
        $this->assertSame([], $this->errorsFor('b', ['colsValidations' => ['in:a,b,c']]));

        $this->assertArrayHasKey('field', $this->errorsFor('b', ['colsValidations' => ['notIn:a,b,c']]));
        $this->assertSame([], $this->errorsFor('x', ['colsValidations' => ['notIn:a,b,c']]));
    }

    #[Test]
    public function regex_rule_validates_the_pattern(): void
    {
        $col = ['colsValidations' => ['regex:/^[A-Z]{3}\d{4}$/']];

        $this->assertSame([], $this->errorsFor('ABC1234', $col));
        $this->assertArrayHasKey('field', $this->errorsFor('abc1234', $col));
    }

    // ── cross-field / formats ─────────────────────────────────────────────────

    #[Test]
    public function confirmed_requires_the_other_field_to_match(): void
    {
        $col = ['colsValidations' => ['confirmed:password_confirm']];

        $this->assertSame(
            [],
            $this->errorsFor('secret', $col, ['password_confirm' => 'secret']),
        );
        $this->assertArrayHasKey(
            'field',
            $this->errorsFor('secret', $col, ['password_confirm' => 'different']),
        );
    }

    #[Test]
    public function email_rule_validates_format(): void
    {
        $this->assertSame([], $this->errorsFor('a@b.com', ['colsValidations' => ['email']]));
        $this->assertArrayHasKey('field', $this->errorsFor('not-an-email', ['colsValidations' => ['email']]));
    }

    #[Test]
    public function cpf_rule_validates_check_digits(): void
    {
        // Valid CPF (generated, passes the mod-11 algorithm).
        $this->assertSame([], $this->errorsFor('529.982.247-25', ['colsValidations' => ['cpf']]));
        // Same digits repeated → invalid.
        $this->assertArrayHasKey('field', $this->errorsFor('111.111.111-11', ['colsValidations' => ['cpf']]));
        $this->assertArrayHasKey('field', $this->errorsFor('123.456.789-00', ['colsValidations' => ['cpf']]));
    }

    #[Test]
    public function only_the_first_failing_rule_is_reported_per_field(): void
    {
        $errors = $this->errorsFor('x', ['colsValidations' => ['minLength:5', 'email']]);

        $this->assertCount(1, $errors);
    }

    #[Test]
    public function multiple_fields_are_validated_independently(): void
    {
        $errors = $this->validator->validate(
            ['a' => '', 'b' => 'ok'],
            [
                ['colsNomeFisico' => 'a', 'colsNomeLogico' => 'A', 'colsRequired' => true],
                ['colsNomeFisico' => 'b', 'colsNomeLogico' => 'B', 'colsRequired' => true],
            ],
        );

        $this->assertArrayHasKey('a', $errors);
        $this->assertArrayNotHasKey('b', $errors);
    }
}
