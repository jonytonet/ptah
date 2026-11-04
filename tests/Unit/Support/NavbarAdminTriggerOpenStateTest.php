<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 1.56.1: the admin gear's open state was `bg-gray-100` — a near-white chip
 * in the dark navbar. It is a themed class with a dark counterpart now.
 */
class NavbarAdminTriggerOpenStateTest extends TestCase
{
    #[Test]
    public function the_open_gear_uses_the_themed_class_with_a_dark_counterpart(): void
    {
        $root = dirname(__DIR__, 3);
        $navbar = (string) file_get_contents($root.'/resources/views/components/forge-navbar.blade.php');
        $css = (string) file_get_contents($root.'/resources/css/ptah-components.css');

        $this->assertStringContainsString("openAdmin ? 'ptah-navbar-icon-btn--open'", $navbar);
        $this->assertStringNotContainsString("openAdmin ? 'bg-gray-100", $navbar);
        $this->assertStringContainsString('.ptah-dark .ptah-navbar .ptah-navbar-icon-btn.ptah-navbar-icon-btn--open', $css);
    }
}
