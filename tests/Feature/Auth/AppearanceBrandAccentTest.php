<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\AppearancePresets;
use Ptah\Tests\TestCase;

/**
 * The default accent ("azul") always won over `ptah.theme.colors.primary`, so
 * a host's brand color never showed for a user who had not picked an accent
 * (1.43.2).
 */
class AppearanceBrandAccentTest extends TestCase
{
    #[Test]
    public function a_host_with_its_own_primary_gets_it_by_default(): void
    {
        config(['ptah.theme.colors.primary' => '#c2410c']);

        $this->assertSame('marca', AppearancePresets::sanitize([])['accent']);
        $this->assertSame('azul', AppearancePresets::sanitize(['accent' => 'azul'])['accent'], 'Quem escolheu azul continua azul.');
    }

    #[Test]
    public function the_brand_accent_has_no_css_rule_so_the_config_color_wins(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../../../resources/css/ptah-components.css');

        $this->assertStringNotContainsString('data-ptah-accent="marca"', $css);
    }

    #[Test]
    public function a_host_that_never_set_a_color_keeps_the_blue_it_had(): void
    {
        config(['ptah.theme.colors.primary' => '#5b21b6']);

        $this->assertSame(AppearancePresets::DEFAULT_ACCENT, AppearancePresets::sanitize([])['accent']);
        $this->assertNotContains('marca', AppearancePresets::whitelistFor('accent'));
    }
}
