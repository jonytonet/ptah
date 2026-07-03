<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\SearchDropdown;

use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\Tests\TestCase;

/**
 * The SearchDropdown config properties define WHAT is queried and HOW. They are
 * server-set at mount and must never be rewritable from the client payload,
 * otherwise they become SQLi / arbitrary-execution / data-exfiltration vectors.
 * These tests prove they are #[Locked] (client updates rejected) while mount
 * params still configure the component normally.
 */
class SearchDropdownLockedTest extends TestCase
{
    /**
     * @return array<int, array{0: string, 1: mixed}>
     */
    public static function lockedProperties(): array
    {
        return [
            'orderByRaw (SQLi in ORDER BY)' => ['orderByRaw', 'id; DROP TABLE users; --'],
            'modelClass (arbitrary model)' => ['modelClass', 'App\\Models\\User'],
            'serviceClass (arbitrary class)' => ['serviceClass', 'App\\Evil\\Service'],
            'useService (arbitrary method)' => ['useService', 'destroy'],
            'label (column exfiltration)' => ['label', 'password'],
            'value' => ['value', 'password'],
            'dataFilter' => ['dataFilter', [['id', '>', 0]]],
            'limit' => ['limit', 99999],
            'maskOne (Class@method call)' => ['maskOne', 'App\\Evil\\Mask@run'],
            'listens' => ['listens', 'evil'],
        ];
    }

    #[Test]
    #[DataProvider('lockedProperties')]
    public function client_cannot_mutate_locked_config_property(string $property, mixed $value): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(SearchDropdown::class, ['model' => 'Widget'])
            ->set($property, $value);
    }

    #[Test]
    public function mount_params_still_configure_the_component(): void
    {
        // Locking blocks CLIENT updates, not server-side mount configuration.
        Livewire::test(SearchDropdown::class, [
            'model' => 'Product',
            'label' => 'title',
            'value' => 'id',
            'orderByRaw' => 'title asc',
            'limit' => 25,
        ])
            ->assertSet('label', 'title')
            ->assertSet('orderByRaw', 'title asc')
            ->assertSet('limit', 25);
    }
}
