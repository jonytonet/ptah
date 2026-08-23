<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\NavbarSlot;

/**
 * Unit coverage for Ptah\Support\NavbarSlot::resolve() — pure normalisation
 * of `config('ptah.navbar.notifications')` into one of the 3 navbar states.
 * See FASE 0 of the notification hook plan.
 */
class NavbarSlotTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: string, 2: string|null}>
     */
    public static function valuesProvider(): array
    {
        return [
            'null keeps the static bell' => [null, NavbarSlot::MODE_DEFAULT, null],
            'empty string keeps the static bell' => ['', NavbarSlot::MODE_DEFAULT, null],
            'whitespace-only string keeps the static bell' => ['   ', NavbarSlot::MODE_DEFAULT, null],
            'boolean false hides the slot' => [false, NavbarSlot::MODE_HIDDEN, null],
            "'false' hides the slot" => ['false', NavbarSlot::MODE_HIDDEN, null],
            "'none' hides the slot" => ['none', NavbarSlot::MODE_HIDDEN, null],
            "'off' hides the slot" => ['off', NavbarSlot::MODE_HIDDEN, null],
            "'hidden' hides the slot" => ['hidden', NavbarSlot::MODE_HIDDEN, null],
            "'0' hides the slot" => ['0', NavbarSlot::MODE_HIDDEN, null],
            'uppercase / spaced off-value still hides the slot' => [' NONE ', NavbarSlot::MODE_HIDDEN, null],
            'a trimmed alias resolves to a component' => [' Ptah-Bell ', NavbarSlot::MODE_COMPONENT, 'Ptah-Bell'],
            'a plain alias resolves to a component' => ['meu-sino', NavbarSlot::MODE_COMPONENT, 'meu-sino'],
        ];
    }

    #[Test]
    #[DataProvider('valuesProvider')]
    public function resolves_every_configured_value_to_the_expected_state(mixed $configured, string $expectedMode, ?string $expectedComponent): void
    {
        $result = NavbarSlot::resolve($configured);

        $this->assertSame($expectedMode, $result['mode']);
        $this->assertSame($expectedComponent, $result['component']);
    }

    #[Test]
    public function an_unexpected_scalar_falls_back_to_the_default_mode(): void
    {
        $result = NavbarSlot::resolve(true);

        $this->assertSame(NavbarSlot::MODE_DEFAULT, $result['mode']);
        $this->assertNull($result['component']);
    }
}
