<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\Attachment;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use Ptah\Traits\HasAttachments;

class AttOrder extends Model
{
    use HasAttachments;

    protected $table = 'att_orders';

    protected $fillable = ['code', 'company_id'];
}

/**
 * Attachments — files stream through the screen, never a public URL, and
 * every lookup goes through the record the screen may reach.
 */
class CrudAttachmentsTest extends TestCase
{
    private string $migrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->migrations = sys_get_temp_dir().'/ptah-att-'.uniqid();
        $this->app->useDatabasePath($this->migrations);
        $this->artisan('ptah:attachments:install')->assertExitCode(0);
        foreach (glob($this->migrations.'/migrations/*.php') as $file) {
            (require $file)->up();
        }
        Attachment::flushTableCache();

        Schema::create('att_orders', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedBigInteger('company_id')->nullable();
            $t->timestamps();
        });

        $this->configure();
        session(['ptah_company_id' => 1]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->migrations);
        Attachment::flushTableCache();

        parent::tearDown();
    }

    private function configure(array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => AttOrder::class, 'route' => ''], ['config' => [
            'crud' => AttOrder::class,
            'cols' => [['colsNomeFisico' => 'code', 'colsNomeLogico' => 'Código', 'colsTipo' => 'text', 'colsGravar' => true]],
            'permissions' => $permissions,
        ]]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => AttOrder::class]);
    }

    private function uploadTo(AttOrder $order, string $name = 'nota.pdf')
    {
        return $this->crud()
            ->call('openAttachments', $order->id)
            ->set('attachmentUploads', [UploadedFile::fake()->create($name, 120, 'application/pdf')])
            ->call('uploadAttachments');
    }

    #[Test]
    public function a_file_is_stored_privately_with_a_random_name_and_downloads_by_its_own(): void
    {
        $order = AttOrder::create(['code' => 'PED-1', 'company_id' => 1]);

        $crud = $this->uploadTo($order)->assertHasNoErrors();

        $att = Attachment::first();
        $this->assertSame('nota.pdf', $att->original_name);
        $this->assertStringNotContainsString('nota', $att->path, 'O nome gravado deveria ser aleatorio.');
        Storage::disk('local')->assertExists($att->path);

        $crud->call('downloadAttachment', $att->id)->assertFileDownloaded('nota.pdf');
    }

    #[Test]
    public function an_executable_is_refused(): void
    {
        $order = AttOrder::create(['code' => 'PED-1', 'company_id' => 1]);

        $this->crud()
            ->call('openAttachments', $order->id)
            ->set('attachmentUploads', [UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])
            ->call('uploadAttachments')
            ->assertHasErrors('attachmentUploads.0');

        $this->assertSame(0, Attachment::count());
    }

    #[Test]
    public function another_companys_record_and_another_records_file_are_unreachable(): void
    {
        $mine = AttOrder::create(['code' => 'MEU', 'company_id' => 1]);
        $theirs = AttOrder::create(['code' => 'DELES', 'company_id' => 2]);

        session(['ptah_company_id' => 2]);
        $this->uploadTo($theirs, 'contrato.pdf');
        $foreign = Attachment::first();
        session(['ptah_company_id' => 1]);

        // Registro de outra empresa: o modal nao abre.
        $this->crud()->call('openAttachments', $theirs->id)->assertSet('showAttachmentsModal', false);

        // Id de anexo de outro registro, com o meu registro aberto: nao baixa.
        $this->crud()
            ->call('openAttachments', $mine->id)
            ->call('downloadAttachment', $foreign->id)
            ->assertNoFileDownloaded();
    }

    #[Test]
    public function delete_is_soft_and_keeps_the_file(): void
    {
        $order = AttOrder::create(['code' => 'PED-1', 'company_id' => 1]);
        $this->uploadTo($order);
        $att = Attachment::first();

        $this->crud()->call('openAttachments', $order->id)->call('deleteAttachment', $att->id)->assertSet('attachmentItems', []);

        $this->assertSoftDeleted(Attachment::TABLE, ['id' => $att->id]);
        Storage::disk('local')->assertExists($att->path);
    }

    #[Test]
    public function uploading_needs_update_permission(): void
    {
        $this->configure(permissions: ['showEditButton' => false]);
        $order = AttOrder::create(['code' => 'PED-1', 'company_id' => 1]);

        $this->uploadTo($order);

        $this->assertSame(0, Attachment::count());
    }

    #[Test]
    public function the_attachments_gate_hides_the_feature(): void
    {
        $this->configure(permissions: ['attachments' => 'orders.files']);
        $order = AttOrder::create(['code' => 'PED-1', 'company_id' => 1]);

        $this->crud()->call('openAttachments', $order->id)->assertSet('showAttachmentsModal', false);
    }
}
