<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Company;
use Ptah\Tests\TestCase;

/**
 * In the React/Vue stack the auth module's URLs keep their names and serve
 * the stack page (1.59.0); the dashboard's widgets come through the API.
 */
class StackPagesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.modules.auth', true);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
        $app['config']->set('ptah.stack', 'react');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function the_sign_in_urls_serve_the_stack_page_under_the_same_names(): void
    {
        $this->get(route('ptah.auth.login'))
            ->assertOk()
            ->assertSee('id="ptah-app"', false)
            ->assertSee('data-page="login"', false)
            ->assertDontSee('wire:id', false);

        $this->get(route('ptah.auth.forgot-password'))->assertOk()->assertSee('data-page="forgot-password"', false);

        // The token of the link and its e-mail reach the page as props.
        $html = $this->get(route('ptah.auth.password.reset', ['token' => 'tok-123']).'?email=ana@example.test')
            ->assertOk()
            ->assertSee('data-page="reset-password"', false)
            ->getContent();
        $this->assertStringContainsString('tok-123', html_entity_decode($html));
        $this->assertStringContainsString('ana@example.test', html_entity_decode($html));
    }

    #[Test]
    public function the_protected_pages_still_ask_for_a_session(): void
    {
        $this->get(route('ptah.profile'))->assertRedirect();
        $this->get(route('ptah.dashboard'))->assertRedirect();

        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));

        $this->get(route('ptah.profile'))->assertOk()->assertSee('data-page="profile"', false)->assertSee('data-logout=', false);
        $this->get(route('ptah.dashboard'))->assertOk()->assertSee('data-page="dashboard"', false);
    }

    #[Test]
    public function the_dashboard_widgets_come_computed_and_only_the_visible_ones(): void
    {
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));
        config(['ptah-dashboard.widgets' => [
            ['type' => 'stat', 'label' => 'Empresas', 'model' => Company::class, 'aggregate' => 'count'],
            ['type' => 'stat', 'label' => 'Quebrado', 'model' => 'App\\Models\\DoesNotExist', 'aggregate' => 'count'],
        ]]);

        $widgets = $this->getJson('/ptah/api/dashboard')->assertOk()->json('data');

        $this->assertSame('Empresas', $widgets[0]['label']);
        $this->assertNull($widgets[0]['error']);
        $this->assertArrayHasKey('value', $widgets[0]);
        // A widget that cannot be computed says so instead of breaking the page.
        $this->assertNotNull($widgets[1]['error']);
        // With widgets, no fallback cards.
        $this->assertNull($this->getJson('/ptah/api/dashboard')->json('fallback'));
    }

    #[Test]
    public function without_widgets_the_dashboard_gets_the_four_cards_of_the_livewire_page(): void
    {
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));
        config(['ptah-dashboard.widgets' => [], 'app.name' => 'ERP', 'app.env' => 'local']);

        $this->getJson('/ptah/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('fallback.app', 'ERP')
            ->assertJsonPath('fallback.environment', 'Local')
            ->assertJsonPath('fallback.laravel', app()->version());
    }
}
