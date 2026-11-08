<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\MenuResolver;
use Ptah\Tests\TestCase;

/**
 * Without `ptah.auth.dashboard` (the default) nothing changes: the package
 * registers its /dashboard and the menu points at it.
 */
class DefaultDashboardTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
    }

    #[Test]
    public function the_package_dashboard_stays_the_default(): void
    {
        $this->assertTrue(Route::has('ptah.dashboard'));
        $this->assertSame(route('ptah.dashboard'), MenuResolver::dashboardUrl());
    }
}
