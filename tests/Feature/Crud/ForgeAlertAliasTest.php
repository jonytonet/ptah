<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the back-compat aliases on <x-forge-alert>: ERP screens call it with
 * type="warning|info|error" and :dismissible instead of color=/closable=.
 * A regression here silently drops styling or the close button.
 */
class ForgeAlertAliasTest extends TestCase
{
    #[Test]
    public function type_alias_maps_to_the_matching_color_styling(): void
    {
        $html = Blade::render('<x-forge-alert type="warning">Atenção</x-forge-alert>');

        $this->assertStringContainsString('ptah-alert-warn', $html);
        $this->assertStringContainsString('bg-warn-light', $html);
        $this->assertStringContainsString('Atenção', $html);
    }

    #[Test]
    #[DataProvider('aliasColorProvider')]
    public function type_alias_normalises_each_keyword(string $type, string $expectedColor): void
    {
        $html = Blade::render('<x-forge-alert type="'.$type.'">x</x-forge-alert>');

        $this->assertStringContainsString('ptah-alert-'.$expectedColor, $html);
    }

    public static function aliasColorProvider(): array
    {
        return [
            'warning => warn' => ['warning', 'warn'],
            'info => primary' => ['info', 'primary'],
            'error => danger' => ['error', 'danger'],
            'passthrough danger' => ['danger', 'danger'],
        ];
    }

    #[Test]
    public function dismissible_alias_renders_the_close_button(): void
    {
        $html = Blade::render('<x-forge-alert type="success" dismissible="true">ok</x-forge-alert>');

        $this->assertStringContainsString('show = false', $html);
        $this->assertStringContainsString('aria-label', $html);
    }

    #[Test]
    public function without_dismissible_there_is_no_close_button(): void
    {
        $html = Blade::render('<x-forge-alert type="success">ok</x-forge-alert>');

        $this->assertStringNotContainsString('show = false', $html);
    }
}
