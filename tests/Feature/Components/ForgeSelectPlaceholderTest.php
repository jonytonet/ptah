<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/** The default placeholder was a fixed 'Select...' even with APP_LOCALE=pt_BR (1.43.2). */
class ForgeSelectPlaceholderTest extends TestCase
{
    #[Test]
    public function the_default_placeholder_follows_the_locale(): void
    {
        app()->setLocale('pt_BR');
        $html = Blade::render('<x-forge-select name="s" :options="[]" />');

        $this->assertStringContainsString('Selecione...', $html);
        $this->assertStringNotContainsString('Select...', $html);
    }

    #[Test]
    public function an_explicit_placeholder_still_wins(): void
    {
        $this->assertStringContainsString('Escolha a cor', Blade::render('<x-forge-select name="s" :options="[]" placeholder="Escolha a cor" />'));
    }
}
