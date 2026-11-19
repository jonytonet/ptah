<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class AnswersItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * The page of a screen carries the screen's first API answers (1.59.0), so
 * opening it — or moving to it in place — is one request instead of five.
 * The answers are the endpoints' own, and never reach a page that is less
 * guarded than the API.
 */
class StackPageAnswersTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web', 'auth']);
        $app['config']->set('ptah.stack', 'react');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['ptah.modules.permissions' => false]);

        CrudScreen::register('things', AnswersItem::class)->configRoute('');
        CrudConfig::create(['model' => AnswersItem::class, 'route' => '', 'config' => [
            'crud' => AnswersItem::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
            ],
            'permissions' => [],
        ]]);
        AnswersItem::create(['name' => 'Alfa']);
        AnswersItem::create(['name' => 'Beta']);

        $page = fn () => view('ptah::stack.app', ['screen' => 'things', 'title' => 'Things']);
        Route::get('things', $page)->middleware(['web', 'auth']);
        Route::get('things-open', $page)->middleware(['web']);
        Route::get('dash', fn () => view('ptah::stack.app', ['page' => 'dashboard']))->middleware(['web', 'auth']);
    }

    private function signIn(): void
    {
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));
    }

    /** @return array<string, mixed>|null */
    private function answers(string $html): ?array
    {
        if (preg_match('/data-answers="([^"]*)"/', $html, $found) !== 1) {
            return null;
        }

        return json_decode(html_entity_decode($found[1], ENT_QUOTES), true);
    }

    #[Test]
    public function the_page_of_a_screen_carries_the_very_answers_of_the_api(): void
    {
        $this->signIn();

        $html = $this->get('/things')->assertOk()->getContent();
        $answers = $this->answers($html);

        $this->assertNotNull($answers);
        $this->assertStringContainsString('data-embed="1"', $html);

        // Keyed by the path the client asks with; equal to what the endpoint answers.
        foreach (['/crud/things/schema', '/crud/things?restore=1', '/crud/things/filters/meta', '/crud/things/config/can'] as $path) {
            $this->assertArrayHasKey($path, $answers);
            $this->assertSame($this->getJson('/ptah/api'.$path)->assertOk()->json(), $answers[$path], $path);
        }

        $this->assertCount(2, $answers['/crud/things?restore=1']['data']);
    }

    #[Test]
    public function a_navigation_in_place_gets_the_answers_too_without_the_layout_data(): void
    {
        $this->signIn();

        $html = $this->get('/things', ['X-Ptah-Navigate' => '1'])->assertOk()->getContent();

        $this->assertNotNull($this->answers($html));
        $this->assertStringNotContainsString('data-boot=', $html);
    }

    #[Test]
    public function a_page_less_guarded_than_the_api_carries_nothing(): void
    {
        $this->signIn();

        // Its route lacks the `auth` the API is behind.
        $html = $this->get('/things-open')->assertOk()->getContent();

        $this->assertNull($this->answers($html));
        $this->assertStringNotContainsString('data-embed', $html);
    }

    #[Test]
    public function a_page_with_a_query_string_or_with_the_switch_off_carries_nothing(): void
    {
        $this->signIn();

        $this->assertNull($this->answers($this->get('/things?f=abc')->assertOk()->getContent()));

        config(['ptah.crud_api.embed' => false]);
        $html = $this->get('/things')->assertOk()->getContent();
        $this->assertNull($this->answers($html));
        $this->assertStringNotContainsString('data-embed', $html);
    }

    #[Test]
    public function a_page_that_is_not_a_screen_says_it_embeds_but_carries_no_answers(): void
    {
        $this->signIn();

        $html = $this->get('/dash')->assertOk()->getContent();

        $this->assertStringContainsString('data-embed="1"', $html);
        $this->assertNull($this->answers($html));
    }

    #[Test]
    public function a_screen_that_refuses_the_user_carries_nothing(): void
    {
        $this->signIn();
        Route::get('ghost', fn () => view('ptah::stack.app', ['screen' => 'ghost']))->middleware(['web', 'auth']);

        // Not registered: the API answers 404, and the page embeds no answer.
        $this->assertNull($this->answers($this->get('/ghost')->assertOk()->getContent()));
    }
}
