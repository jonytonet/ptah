<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class DraftContact extends Model
{
    protected $table = 'draft_contacts';

    protected $fillable = ['name', 'phone', 'document', 'secret', 'status'];

    protected $hidden = ['secret'];
}

/**
 * Form draft (1.42.0): what is typed in the modal survives closing it, kept in
 * the browser. The server's part is deciding WHICH draft a given opening may
 * see and what may go into it — these tests pin that; the browser half is in
 * FormDraftBrowserTest.
 */
class CrudFormDraftTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('draft_contacts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('document')->nullable();
            $t->string('secret')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        $this->screen();
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    private function screen(?array $draft = ['enabled' => true, 'exclude' => ['document']]): void
    {
        CrudConfig::updateOrCreate(['model' => DraftContact::class, 'route' => ''], ['config' => array_filter([
            'crud' => DraftContact::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'phone', 'colsNomeLogico' => 'Telefone', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'document', 'colsNomeLogico' => 'CPF', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'secret', 'colsNomeLogico' => 'Segredo', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'text', 'colsGravar' => true, 'colsDefaultValue' => 'ativo'],
            ],
            'permissions' => [],
            'formDraft' => $draft,
        ], fn ($v) => $v !== null)]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => DraftContact::class]);
    }

    #[Test]
    public function it_is_off_unless_the_screen_turns_it_on(): void
    {
        $this->screen(null);

        $this->crud()->call('openCreate')
            ->assertNotDispatched('ptah:form-draft')
            ->assertDontSee(__('ptah::ui.btn_form_draft_clear'));
    }

    #[Test]
    public function new_announces_the_create_draft_with_only_safe_fields(): void
    {
        $this->crud()->call('openCreate')->assertDispatched('ptah:form-draft', function ($name, $p) {
            return str_ends_with($p['key'], ':new')
                && $p['fields'] === ['name', 'phone', 'status']        // sem CPF (exclude) e sem `secret` ($hidden)
                && $p['original'] === ['name' => null, 'phone' => null, 'status' => 'ativo']
                && $p['version'] === '';
        });
    }

    #[Test]
    public function an_edit_draft_is_bound_to_the_record_id_and_its_fingerprint(): void
    {
        $a = DraftContact::create(['name' => 'Ana', 'phone' => '1']);
        $b = DraftContact::create(['name' => 'Bia', 'phone' => '2']);

        $crud = $this->crud();
        $crud->call('openEdit', $a->id)->assertDispatched('ptah:form-draft', fn ($n, $p) => str_ends_with($p['key'], ':edit:'.$a->id)
            && $p['version'] === md5((string) json_encode(['name' => 'Ana', 'phone' => '1', 'status' => null])));

        $crud->call('openEdit', $b->id)->assertDispatched('ptah:form-draft', fn ($n, $p) => str_ends_with($p['key'], ':edit:'.$b->id));
    }

    #[Test]
    public function two_users_never_share_a_draft_key(): void
    {
        $keyOf = function (int $user): string {
            $this->actingAs(new GenericUser(['id' => $user]));
            $key = '';
            $this->crud()->call('openCreate')->assertDispatched('ptah:form-draft', function ($n, $p) use (&$key) {
                $key = $p['key'];

                return true;
            });

            return $key;
        };

        $this->assertNotSame($keyOf(1), $keyOf(2));
    }

    #[Test]
    public function a_duplicate_opens_without_a_draft(): void
    {
        $a = DraftContact::create(['name' => 'Ana']);

        $crud = $this->crud()->call('duplicateRecord', $a->id)->assertSet('formDraftMode', '');

        // UM anuncio so, o de "sem rascunho": com o do "Novo" antes, o
        // navegador restaurava o rascunho de inclusao POR CIMA da copia.
        $announces = array_values(array_filter($crud->effects['dispatches'] ?? [], fn ($d) => $d['name'] === 'ptah:form-draft'));
        $this->assertCount(1, $announces);
        $this->assertSame('', $announces[0]['params']['key']);
    }

    #[Test]
    public function restoring_takes_only_draftable_fields(): void
    {
        $crud = $this->crud()->call('openCreate');
        $key = $crud->get('formInstanceKey');

        $crud->call('restoreFormDraft', ['name' => 'Rascunho', 'document' => '123', 'secret' => 'x', 'id' => 99]);

        $this->assertSame('Rascunho', $crud->get('formData.name'));
        $this->assertArrayNotHasKey('document', $crud->get('formData'));
        $this->assertArrayNotHasKey('secret', $crud->get('formData'));
        $this->assertArrayNotHasKey('id', $crud->get('formData'));
        $this->assertNotSame($key, $crud->get('formInstanceKey'), 'O formulario precisa remontar para as mascaras lerem o valor.');
    }

    #[Test]
    public function revert_reloads_the_record_and_clear_resets_the_create_form(): void
    {
        $a = DraftContact::create(['name' => 'Ana']);

        $crud = $this->crud()->call('openEdit', $a->id)->set('formData.name', 'Mudado')->call('revertFormToOriginal');
        $this->assertSame('Ana', $crud->get('formData.name'));

        $crud = $this->crud()->call('openCreate')->set('formData.name', 'Algo')->call('clearFormDraft');
        $this->assertNull($crud->get('formData.name') ?? null);
        $this->assertSame('ativo', $crud->get('formData.status'), 'Limpar volta aos defaults, nao a vazio.');
    }

    #[Test]
    public function a_successful_save_tells_the_browser_to_drop_the_draft(): void
    {
        $crud = $this->crud()->call('openCreate');
        $key = '';
        $crud->assertDispatched('ptah:form-draft', function ($n, $p) use (&$key) {
            $key = $p['key'];

            return true;
        });

        $crud->set('formData.name', 'Nova')->call('save')
            ->assertDispatched('ptah:form-draft-saved', key: $key);
        $this->assertSame(1, DraftContact::count());
    }

    #[Test]
    public function the_original_and_the_mode_cannot_be_forged(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->crud()->call('openCreate')->set('formDraftOriginal', ['name' => 'x']);
    }
}
