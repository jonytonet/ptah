<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\BulkActionHandler;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ActItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class ActBulkHandler implements BulkActionHandler
{
    /** @var list<mixed> */
    public static array $seen = [];

    public function handleBulkAction(array $ids, string $model, string $key): void
    {
        self::$seen = [$key, $ids];
        ActItem::whereIn('id', $ids)->update(['status' => 'archived']);
    }
}

/**
 * The rest of the BaseCrud through the API (1.59.0): searchdropdown lookups,
 * image upload, duplicate, bulk and row actions, export, and the layout's
 * menu. Same engine — these pin the contract and the scope.
 */
class CrudApiActionsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana', 'email' => 'ana@example.test']));
        ActBulkHandler::$seen = [];

        CrudScreen::register('items', ActItem::class)->configRoute('');
        CrudConfig::create(['model' => ActItem::class, 'route' => '', 'config' => [
            'crud' => ActItem::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Foto', 'colsTipo' => 'image', 'colsGravar' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Parecido com', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsSDModel' => ActItem::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id', 'colsSDTipo' => 'model'],
                ['colsTipo' => 'action', 'colsNomeLogico' => 'Abrir', 'actionType' => 'link', 'actionValue' => '/items/%id%?n=%name%', 'actionIcon' => 'bx-link'],
                ['colsTipo' => 'action', 'colsNomeLogico' => 'Perigo', 'actionType' => 'link', 'actionValue' => 'javascript:alert(%id%)'],
                ['colsTipo' => 'action', 'colsNomeLogico' => 'Aprovar', 'actionType' => 'livewire', 'actionValue' => 'approve(%id%)', 'actionConfirm' => true],
            ],
            'permissions' => [],
            'exportConfig' => ['enabled' => true, 'maxRows' => 5000],
            'bulkActions' => [
                ['label' => 'Arquivar', 'action' => 'archive', 'method' => ActBulkHandler::class.'@archive', 'confirm' => 'Arquivar?'],
            ],
        ]]);

        foreach (['Alfa', 'Beta', 'Gama'] as $name) {
            ActItem::create(['name' => $name, 'status' => 'active', 'amount' => 0]);
        }
    }

    #[Test]
    public function a_searchdropdown_is_looked_up(): void
    {
        $found = $this->getJson('/ptah/api/crud/items/lookup/amount?q=bet')->assertOk()->json('data');
        $this->assertCount(1, $found);
        $this->assertStringContainsString('Beta', json_encode($found));

        // Empty term: the first options, as on focus.
        $this->assertCount(3, $this->getJson('/ptah/api/crud/items/lookup/amount')->json('data'));

        // Not a searchdropdown of this screen: nothing, not an error.
        $this->assertSame([], $this->getJson('/ptah/api/crud/items/lookup/name?q=a')->assertOk()->json('data'));
    }

    #[Test]
    public function an_image_is_uploaded_with_the_same_checks(): void
    {
        Storage::fake('public');

        $id = $this->post('/ptah/api/crud/items', [
            'data' => json_encode(['name' => 'Com foto']),
            'files' => ['status' => UploadedFile::fake()->image('foto.png', 20, 20)],
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $path = ActItem::find($id)->status;
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);

        // Not an image: refused by the engine's real-MIME check.
        $this->post("/ptah/api/crud/items/{$id}", [
            'data' => json_encode(['name' => 'Com foto']),
            'files' => ['status' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['status']]);
    }

    #[Test]
    public function a_record_is_duplicated_into_a_new_form_and_a_blank_one_has_defaults(): void
    {
        $id = ActItem::where('name', 'Alfa')->value('id');

        $json = $this->postJson("/ptah/api/crud/items/{$id}/duplicate")->assertOk()->json();
        $this->assertSame('Alfa', $json['data']['name']);
        $this->assertArrayNotHasKey('id', $json['data']);
        $this->assertSame(3, ActItem::count());

        $this->getJson('/ptah/api/crud/items/create')->assertOk()->assertJsonStructure(['data', 'labels']);
    }

    #[Test]
    public function bulk_actions_run_over_the_scoped_ids(): void
    {
        $ids = ActItem::pluck('id')->all();

        $schema = $this->getJson('/ptah/api/crud/items/schema')->json();
        $this->assertSame('archive', $schema['bulkActions'][0]['action']);
        $this->assertSame('Arquivar?', $schema['bulkActions'][0]['confirm']);

        $this->postJson('/ptah/api/crud/items/bulk', ['action' => 'archive', 'ids' => [$ids[0], $ids[1], 99999]])
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(['archive', [$ids[0], $ids[1]]], ActBulkHandler::$seen);

        $this->postJson('/ptah/api/crud/items/bulk', ['action' => 'delete', 'ids' => [$ids[2]]])->assertOk();
        $this->assertDatabaseMissing('items', ['id' => $ids[2]]);

        // An action the screen does not have runs nothing.
        $this->postJson('/ptah/api/crud/items/bulk', ['action' => 'drop-everything', 'ids' => [$ids[0]]])
            ->assertStatus(422)->assertJsonPath('remaining', [(string) $ids[0]]);
    }

    #[Test]
    public function a_bulk_action_cannot_leave_the_screens_scope(): void
    {
        ActItem::where('name', 'Gama')->update(['status' => 'other']);
        CrudScreen::register('active', ActItem::class)->configRoute('')->lockedFilters(['status' => 'active']);
        $outside = ActItem::where('name', 'Gama')->value('id');

        $this->postJson('/ptah/api/crud/active/bulk', ['action' => 'delete', 'ids' => [$outside]]);

        $this->assertDatabaseHas('items', ['id' => $outside]);
    }

    #[Test]
    public function export_answers_a_one_time_url_for_the_filtered_listing(): void
    {
        $json = $this->postJson('/ptah/api/crud/items/export', ['format' => 'excel', 'state' => ['search' => 'alf']])
            ->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertStringContainsString('/ptah/export/download/', $json['url']);

        $token = basename(parse_url($json['url'], PHP_URL_PATH));
        $payload = cache('ptah:export:'.$token);
        $this->assertSame([ActItem::where('name', 'Alfa')->value('id')], $payload['ids']);

        $print = $this->postJson('/ptah/api/crud/items/export', ['format' => 'print'])->assertOk()->json();
        $this->assertStringContainsString('/ptah/print/', $print['url']);
    }

    #[Test]
    public function row_actions_come_resolved_and_safe(): void
    {
        $row = $this->getJson('/ptah/api/crud/items?search=alf')->json('data.0');
        $id = $row['id'];
        $actions = array_column($row['actions'], null, 'label');

        $this->assertSame("/items/{$id}?n=Alfa", $actions['Abrir']['value']);
        $this->assertSame('link', $actions['Abrir']['type']);
        // A javascript: href never reaches the client.
        $this->assertSame('#', $actions['Perigo']['value']);
        // A Livewire action is a named action for the client's own handler.
        $this->assertSame('action', $actions['Aprovar']['type']);
        $this->assertSame("approve({$id})", $actions['Aprovar']['value']);
        $this->assertNotNull($actions['Aprovar']['confirm']);
    }

    #[Test]
    public function the_layout_gets_the_menu_and_the_user(): void
    {
        config(['ptah.forge.sidebar_items' => [
            ['label' => 'Itens', 'url' => '/items', 'icon' => 'bx bx-cube', 'type' => 'menuLink', 'match' => 'items'],
        ]]);

        $menu = $this->getJson('/ptah/api/menu')->assertOk()->json('data');
        $this->assertSame('Itens', $menu[0]['label']);

        $me = $this->getJson('/ptah/api/me')->assertOk()->json();
        $this->assertSame('Ana', $me['user']['name']);
        $this->assertContains('items', $me['screens']);
    }
}
