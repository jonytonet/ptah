<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class KindsStaff extends Model
{
    protected $table = 'kinds_staff';

    protected $guarded = [];
}

class KindsStaffHashed extends Model
{
    protected $table = 'kinds_staff';

    protected $guarded = [];

    protected $casts = ['password' => 'hashed'];
}

/**
 * 1.48.0 (Engepecas): no `password` type (the edit form carried the HASH into
 * a text input and the Livewire payload), `boolean` was the same Yes/No
 * select, and there was no way to SHOW a field without letting it be edited.
 */
class CrudFormFieldKindsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('kinds_staff', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('password');
            $t->boolean('active')->default(true);
            $t->boolean('verified')->nullable();
            $t->text('buyer_note')->nullable();
            $t->timestamps();
        });

        foreach ([KindsStaff::class, KindsStaffHashed::class] as $model) {
            CrudConfig::create(['model' => $model, 'route' => '', 'config' => [
                'crud' => $model,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                    ['colsNomeFisico' => 'password', 'colsNomeLogico' => 'Senha', 'colsTipo' => 'password', 'colsGravar' => true, 'colsRequired' => true],
                    ['colsNomeFisico' => 'active', 'colsNomeLogico' => 'Ativo', 'colsTipo' => 'boolean', 'colsGravar' => true],
                    ['colsNomeFisico' => 'verified', 'colsNomeLogico' => 'Verificado', 'colsTipo' => 'boolean', 'colsGravar' => true],
                    ['colsNomeFisico' => 'buyer_note', 'colsNomeLogico' => 'Relato', 'colsTipo' => 'textarea', 'colsGravar' => true, 'colsReadonly' => true],
                ],
                'permissions' => [],
            ]]);
        }
    }

    private function staff(): int
    {
        return (int) DB::table('kinds_staff')->insertGetId([
            'name' => 'Ana', 'password' => Hash::make('antiga'), 'active' => true, 'buyer_note' => "Peca X\nurgente",
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function the_hash_never_reaches_the_form_and_empty_keeps_the_password(): void
    {
        $id = $this->staff();
        $hash = DB::table('kinds_staff')->where('id', $id)->value('password');

        $crud = Livewire::test(BaseCrud::class, ['model' => KindsStaff::class])->call('openEdit', $id);
        $crud->assertSet('formData.password', '');
        $this->assertStringNotContainsString($hash, $crud->html());

        $crud->set('formData.name', 'Ana Maria')->call('save')->assertSet('formErrors', []);

        $this->assertSame($hash, DB::table('kinds_staff')->where('id', $id)->value('password'), 'Vazio ao editar = manter.');
    }

    #[Test]
    public function a_new_password_is_hashed_once(): void
    {
        $id = $this->staff();

        Livewire::test(BaseCrud::class, ['model' => KindsStaff::class])->call('openEdit', $id)
            ->set('formData.password', 'nova-senha')->call('save')->assertSet('formErrors', []);
        $stored = (string) DB::table('kinds_staff')->where('id', $id)->value('password');
        $this->assertNotSame('nova-senha', $stored);
        $this->assertTrue(Hash::check('nova-senha', $stored));

        Livewire::test(BaseCrud::class, ['model' => KindsStaffHashed::class])->call('openEdit', $id)
            ->set('formData.password', 'outra')->call('save')->assertSet('formErrors', []);
        $this->assertTrue(Hash::check('outra', (string) DB::table('kinds_staff')->where('id', $id)->value('password')), 'Cast hashed: hash uma vez so.');
    }

    #[Test]
    public function the_listing_never_shows_the_hash(): void
    {
        $id = $this->staff();
        $hash = DB::table('kinds_staff')->where('id', $id)->value('password');

        $html = Livewire::test(BaseCrud::class, ['model' => KindsStaff::class])->call('clearFilters')->html();
        $this->assertStringNotContainsString($hash, $html);
    }

    #[Test]
    public function a_not_null_boolean_is_a_switch_and_a_nullable_one_keeps_the_select(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => KindsStaff::class])->call('prepareCreate')->html();

        $this->assertStringContainsString('data-ptah-toggle="active"', $html);
        $this->assertStringNotContainsString('data-ptah-toggle="verified"', $html);
        $this->assertStringContainsString('wire:key="ptah-select-verified-new"', $html);
    }

    #[Test]
    public function an_untouched_switch_saves_off(): void
    {
        Livewire::test(BaseCrud::class, ['model' => KindsStaff::class])->call('prepareCreate')
            ->set('formData.name', 'Bia')->set('formData.password', 'x1')
            ->call('save')->assertSet('formErrors', []);

        $this->assertSame(0, (int) DB::table('kinds_staff')->where('name', 'Bia')->value('active'));
    }

    #[Test]
    public function a_read_only_field_is_shown_and_never_saved(): void
    {
        $id = $this->staff();

        $crud = Livewire::test(BaseCrud::class, ['model' => KindsStaff::class])->call('openEdit', $id);
        $this->assertStringContainsString('data-ptah-readonly="buyer_note"', $crud->html());
        $this->assertStringContainsString('urgente', $crud->html());

        $crud->set('formData.buyer_note', 'forjado')->call('save')->assertSet('formErrors', []);
        $this->assertSame("Peca X\nurgente", DB::table('kinds_staff')->where('id', $id)->value('buyer_note'));
    }
}
