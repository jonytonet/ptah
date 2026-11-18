<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ImpApiProduct extends Model
{
    protected $table = 'imp_api_products';

    protected $fillable = ['sku', 'name', 'status', 'kind', 'secret'];
}

/** A user that answers can() — what the gate of permissions.import asks. */
class ImpApiUser extends User
{
    protected $guarded = [];
}

/**
 * The import wizard through the API: the same engine as the Livewire modal
 * (HasCrudImport), driven step by step by a token instead of a temporary
 * upload kept in the component.
 */
class ImportApiTest extends TestCase
{
    private const BASE = '/ptah/api/crud/products/import';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Schema::create('imp_api_products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->string('status');
            $t->string('kind')->nullable();
            $t->string('secret')->nullable();
            $t->timestamps();
        });

        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));
        CrudScreen::register('products', ImpApiProduct::class)->configRoute('')->lockedFilters(['kind' => 'retail']);
        CrudScreen::register('others', ImpApiProduct::class)->configRoute('');
        $this->configure();
    }

    /** @param  array<string, mixed>  $import */
    private function configure(array $import = ['enabled' => true], array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => ImpApiProduct::class, 'route' => ''], ['config' => [
            'crud' => ImpApiProduct::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'sku', 'colsNomeLogico' => 'SKU', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true, 'colsRequired' => true,
                    'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive']],
            ],
            'permissions' => $permissions,
            'importConfig' => $import,
        ]]);
    }

    private function csv(string $content, string $name = 'produtos.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function upload(UploadedFile $file, string $base = self::BASE): TestResponse
    {
        return $this->post($base, ['file' => $file], ['Accept' => 'application/json']);
    }

    private function goodFile(): UploadedFile
    {
        return $this->csv("SKU,Nome,Situação\nP-001,Parafuso,Ativo\nP-002,Porca,Inativo\n");
    }

    #[Test]
    public function the_wizard_runs_upload_map_preview_import(): void
    {
        $info = $this->getJson(self::BASE)->assertOk()->json();
        $this->assertSame(2000, $info['max_rows']);
        $this->assertSame(['csv', 'txt', 'xlsx', 'xls'], $info['extensions']);
        $this->assertSame(['sku', 'name', 'status'], array_column($info['fields'], 'field'));
        $this->assertSame([true, true, true], array_column($info['fields'], 'required'));

        $up = $this->upload($this->goodFile())->assertOk()->json();
        $this->assertTrue($up['ok']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $up['token']);
        $this->assertSame(['SKU', 'Nome', 'Situação'], $up['headers']);
        // The engine's automatic mapping: headers by field name or label.
        $this->assertSame(['0' => 'sku', '1' => 'name', '2' => 'status'], $up['mapping']);
        Storage::disk('local')->assertExists('ptah-import/'.$up['token'].'.upload');
        // No path, no file name: only the token leaves.
        $this->assertStringNotContainsString('ptah-import', json_encode($up));
        $this->assertStringNotContainsString('produtos.csv', json_encode($up));

        $preview = $this->postJson(self::BASE.'/preview', ['token' => $up['token'], 'mapping' => $up['mapping']])->assertOk()->json('preview');
        $this->assertSame(['total' => 2, 'valid' => 2, 'errors' => [], 'more_errors' => 0, 'unmapped_required' => []], $preview);
        $this->assertSame(0, ImpApiProduct::count(), 'The review writes nothing.');

        $run = $this->postJson(self::BASE.'/run', ['token' => $up['token'], 'mapping' => $up['mapping']])->assertOk()->json();
        $this->assertSame(['created' => 2, 'updated' => 0, 'error' => null], $run['result']);
        $this->assertSame('success', $run['messages'][0]['level']);
        $this->assertSame(2, ImpApiProduct::count());
        // The screen's locked filter lands on every created row.
        $this->assertSame(['retail'], ImpApiProduct::pluck('kind')->unique()->values()->all());

        // Used: the file is gone, and the token with it.
        Storage::disk('local')->assertMissing('ptah-import/'.$up['token'].'.upload');
        $this->postJson(self::BASE.'/run', ['token' => $up['token'], 'mapping' => $up['mapping']])->assertNotFound();
        $this->assertSame(2, ImpApiProduct::count());
    }

    #[Test]
    public function the_review_reports_rows_by_line_and_unmapped_required_fields_and_run_refuses_them(): void
    {
        $token = $this->upload($this->csv("SKU,Nome,Situação\nP-001,Parafuso,Ativo\nP-002,,Suspenso\n"))->json('token');

        $preview = $this->postJson(self::BASE.'/preview', ['token' => $token, 'mapping' => ['sku', 'name', 'status']])->assertOk()->json('preview');
        $this->assertSame(1, $preview['valid']);
        $this->assertSame([3], array_values(array_unique(array_column($preview['errors'], 'line'))));
        $this->assertEqualsCanonicalizing(['name', 'status'], array_column($preview['errors'], 'field'));

        // The run validates again (the preview is never trusted): back to the review.
        $this->postJson(self::BASE.'/run', ['token' => $token, 'mapping' => ['sku', 'name', 'status']])
            ->assertStatus(422)->assertJsonPath('ok', false)->assertJsonPath('preview.valid', 1);
        $this->assertSame(0, ImpApiProduct::count());

        // "Nome" left as Ignore: a required field without a column blocks the import.
        $good = $this->upload($this->goodFile())->json('token');
        $this->postJson(self::BASE.'/preview', ['token' => $good, 'mapping' => ['0' => 'sku', '1' => '', '2' => 'status']])
            ->assertOk()->assertJsonPath('preview.unmapped_required', ['Nome']);
        $this->postJson(self::BASE.'/run', ['token' => $good, 'mapping' => ['0' => 'sku', '1' => '', '2' => 'status']])->assertStatus(422);
        $this->assertSame(0, ImpApiProduct::count());
    }

    #[Test]
    public function a_row_the_database_refuses_rolls_back_and_is_reported(): void
    {
        ImpApiProduct::create(['sku' => 'P-002', 'name' => 'Existente', 'status' => 'active', 'kind' => 'retail']);
        $token = $this->upload($this->goodFile())->json('token');

        $run = $this->postJson(self::BASE.'/run', ['token' => $token, 'mapping' => ['sku', 'name', 'status']])->assertStatus(422)->json();
        $this->assertFalse($run['ok']);
        $this->assertStringContainsString('3', (string) $run['result']['error']);
        $this->assertSame(1, ImpApiProduct::count(), 'All or nothing.');
    }

    #[Test]
    public function a_forged_mapping_cannot_write_outside_the_form(): void
    {
        $token = $this->upload($this->csv("sku,name,status,kind,secret\nP-001,Parafuso,active,wholesale,x\n"))->json('token');

        $this->postJson(self::BASE.'/run', ['token' => $token, 'mapping' => ['sku', 'name', 'status', 'kind', 'secret']])->assertOk();

        $row = ImpApiProduct::firstOrFail();
        $this->assertSame('retail', $row->kind, 'The locked filter, not the spreadsheet.');
        $this->assertNull($row->secret, 'Not a form field.');

        // A mapping that is not a map is a 422, not a crash.
        $other = $this->upload($this->goodFile())->json('token');
        $this->postJson(self::BASE.'/preview', ['token' => $other, 'mapping' => 'sku'])->assertStatus(422)->assertJsonStructure(['errors' => ['mapping']]);
    }

    #[Test]
    public function the_engine_limits_refuse_a_file_as_422(): void
    {
        $this->upload($this->csv("<?php echo 1;\n", 'evil.php'))->assertStatus(422)->assertJsonStructure(['errors' => ['file']]);
        $this->post(self::BASE, [], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonStructure(['errors' => ['file']]);
        $this->upload($this->csv("SKU,Nome\n"))->assertStatus(422)->assertJsonPath('errors.file', __('ptah::ui.import_empty'));

        $this->configure(['enabled' => true, 'maxRows' => 1]);
        $this->upload($this->goodFile())->assertStatus(422)->assertJsonPath('errors.file', __('ptah::ui.import_too_many', ['rows' => 2, 'max' => 1]));

        $this->configure(['enabled' => true, 'maxKb' => 1]);
        $this->upload($this->csv("SKU,Nome,Situação\n".str_repeat("P-001,Parafuso,Ativo\n", 200)))->assertStatus(422);
        Storage::disk('local')->assertDirectoryEmpty('ptah-import');
    }

    #[Test]
    public function a_limit_lowered_after_the_upload_still_applies_on_the_next_step(): void
    {
        $token = $this->upload($this->goodFile())->assertOk()->json('token');

        $this->configure(['enabled' => true, 'maxRows' => 1]);
        $this->postJson(self::BASE.'/run', ['token' => $token, 'mapping' => ['sku', 'name', 'status']])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['file']]);
        $this->assertSame(0, ImpApiProduct::count());
    }

    #[Test]
    public function a_token_works_only_for_its_user_and_its_screen(): void
    {
        $token = $this->upload($this->goodFile())->json('token');
        $body = ['token' => $token, 'mapping' => ['sku', 'name', 'status']];

        // Same screen config, another screen key.
        $this->postJson('/ptah/api/crud/others/import/preview', $body)->assertNotFound();

        // Another user; then the same id 9 under another user class (a second guard).
        $this->actingAs(new GenericUser(['id' => 10, 'name' => 'Bia']));
        $this->postJson(self::BASE.'/preview', $body)->assertNotFound();
        $this->postJson(self::BASE.'/run', $body)->assertNotFound();
        $this->actingAs(new ImpApiUser(['id' => 9]));
        $this->postJson(self::BASE.'/preview', $body)->assertNotFound();
        $this->deleteJson(self::BASE.'/'.$token)->assertOk();
        Storage::disk('local')->assertExists('ptah-import/'.$token.'.upload');

        // Forged tokens: never a path.
        $this->postJson(self::BASE.'/preview', ['token' => '../../../.env', 'mapping' => []])->assertNotFound();
        $this->postJson(self::BASE.'/preview', ['token' => ['x'], 'mapping' => []])->assertNotFound();

        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana']));
        $this->postJson(self::BASE.'/preview', $body)->assertOk();
        $this->deleteJson(self::BASE.'/'.$token)->assertOk();
        Storage::disk('local')->assertMissing('ptah-import/'.$token.'.upload');
        $this->postJson(self::BASE.'/preview', $body)->assertNotFound();
    }

    #[Test]
    public function an_expired_token_is_refused_and_removed_and_a_new_upload_replaces_the_previous(): void
    {
        $first = $this->upload($this->goodFile())->json('token');
        $second = $this->upload($this->goodFile())->json('token');
        Storage::disk('local')->assertMissing('ptah-import/'.$first.'.upload');
        Storage::disk('local')->assertExists('ptah-import/'.$second.'.upload');

        $this->travel(3)->hours();
        $this->postJson(self::BASE.'/preview', ['token' => $second, 'mapping' => []])->assertNotFound();
        Storage::disk('local')->assertMissing('ptah-import/'.$second.'.upload');
    }

    #[Test]
    public function import_off_or_not_allowed_is_403_on_every_step(): void
    {
        $token = $this->upload($this->goodFile())->json('token');
        $body = ['token' => $token, 'mapping' => ['sku', 'name', 'status']];
        $steps = fn () => [
            $this->getJson(self::BASE),
            $this->upload($this->goodFile()),
            $this->postJson(self::BASE.'/preview', $body),
            $this->postJson(self::BASE.'/run', $body),
        ];

        $this->configure(import: []);
        foreach ($steps() as $response) {
            $response->assertForbidden();
        }

        // No "create": the import is a create.
        $this->configure(permissions: ['showCreateButton' => false]);
        foreach ($steps() as $response) {
            $response->assertForbidden();
        }

        // permissions.import names a gate the user does not pass.
        $this->actingAs(new ImpApiUser(['id' => 9]));
        Gate::define('import-products', fn () => false);
        $this->configure(permissions: ['import' => 'import-products']);
        foreach ($steps() as $response) {
            $response->assertForbidden();
        }

        $this->assertSame(0, ImpApiProduct::count());
        Storage::disk('local')->assertExists('ptah-import/'.$token.'.upload');
    }

    #[Test]
    public function an_unknown_screen_is_404(): void
    {
        $this->getJson('/ptah/api/crud/nope/import')->assertNotFound();
    }
}
