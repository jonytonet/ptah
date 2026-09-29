<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\ExportOwner;
use Ptah\Tests\TestCase;

class AuditMediumDoc extends Model
{
    use SoftDeletes;

    protected $table = 'audit_medium_docs';

    protected $fillable = ['title', 'secret', 'cost'];

    protected $hidden = ['secret'];
}

/**
 * The BaseCrud MEDIUM findings of the 28/09/2026 surface audit, fixed in
 * 1.41.10. Each case was reproducible on 1.41.9.
 */
class AuditMediumFindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_medium_docs', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('secret')->nullable();
            $t->decimal('cost', 10, 2)->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        $this->screen();
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    private function screen(array $permissions = [], array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => AuditMediumDoc::class, 'route' => ''], ['config' => array_merge([
            'crud' => AuditMediumDoc::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                // So no formulario — como um `password` na tela de usuarios.
                ['colsNomeFisico' => 'secret', 'colsNomeLogico' => 'Segredo', 'colsTipo' => 'text', 'colsGravar' => true, 'colsVisibleList' => false],
            ],
            'permissions' => $permissions,
        ], $extra)]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => AuditMediumDoc::class]);
    }

    #[Test]
    public function show_all_columns_never_lists_a_hidden_attribute(): void
    {
        AuditMediumDoc::create(['title' => 'Doc', 'secret' => 'hash-que-nao-pode-sair']);

        $html = $this->crud()->call('showAllColumns')->html();

        $this->assertStringContainsString('Doc', $html);
        $this->assertStringNotContainsString('hash-que-nao-pode-sair', $html);
    }

    #[Test]
    public function open_edit_sends_only_the_configured_fields(): void
    {
        $doc = AuditMediumDoc::create(['title' => 'Doc', 'cost' => 99.9]);

        $formData = $this->crud()->call('openEdit', $doc->id)->get('formData');

        $this->assertSame('Doc', $formData['title'] ?? null);
        $this->assertArrayNotHasKey('cost', $formData, 'Um atributo que a tela nao configurou chegou ao navegador.');
    }

    #[Test]
    public function bulk_force_delete_only_empties_the_trash(): void
    {
        $alive = AuditMediumDoc::create(['title' => 'Viva']);
        $trashed = AuditMediumDoc::create(['title' => 'Lixeira']);
        $trashed->delete();

        $this->crud()->set('selectedRows', [$alive->id, $trashed->id])->call('bulkForceDelete');

        $this->assertNotNull(AuditMediumDoc::find($alive->id), 'Apagou para sempre uma linha ativa.');
        $this->assertNull(AuditMediumDoc::withTrashed()->find($trashed->id));
    }

    #[Test]
    public function a_custom_bulk_action_answers_to_the_config_gate(): void
    {
        $doc = AuditMediumDoc::create(['title' => 'Doc']);
        $this->screen(['showEditButton' => false], ['bulkActions' => [['action' => 'approve', 'label' => 'Aprovar']]]);

        $this->crud()->set('selectedRows', [$doc->id])->call('executeBulkAction', 'approve')
            ->assertNotDispatched('crud-bulk-action');
    }

    #[Test]
    public function the_bulk_action_event_carries_only_ids_in_scope(): void
    {
        $doc = AuditMediumDoc::create(['title' => 'Doc']);
        $this->screen([], ['bulkActions' => [['action' => 'approve', 'label' => 'Aprovar']]]);

        $this->crud()->set('selectedRows', [$doc->id, 999999])->call('executeBulkAction', 'approve')
            ->assertDispatched('crud-bulk-action', fn ($name, $p) => array_map('intval', $p['ids'] ?? []) === [$doc->id]);
    }

    #[Test]
    public function an_export_belongs_to_a_user_on_its_own_guard(): void
    {
        config(['auth.guards.customers' => ['driver' => 'session', 'provider' => 'users']]);

        // O usuario 1 do guard padrao nao e o dono do export do usuario 1 do
        // outro guard — era, com user_id sozinho.
        $this->assertTrue(ExportOwner::owns(1, 'web'));
        $this->assertFalse(ExportOwner::owns(1, 'customers'));

        $this->assertFalse(ExportOwner::owns(null, null), 'Visitante nao e dono de nada.');
    }
}
