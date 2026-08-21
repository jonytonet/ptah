<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\UserPreference;
use Ptah\Tests\TestCase;

class SearchPersistStub extends Model
{
    protected $table = 'bulk_action_stubs';

    protected $fillable = ['name', 'status'];
}

/**
 * Relato do usuário: pesquisa, entra no detalhe, dá "voltar" — o texto aparece
 * no campo mas a listagem NÃO está filtrada. O mecanismo de persistência
 * (filters.search em savePreferences/loadPreferences + updatedSearch) existe
 * desde março; estes testes provam o contrato ponta a ponta para cercar onde
 * o sintoma pode nascer.
 */
class CrudSearchPersistenceTest extends TestCase
{
    private function makeConfig(): void
    {
        CrudConfig::create([
            'model' => SearchPersistStub::class,
            'route' => '',
            'config' => [
                'crud' => SearchPersistStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsSearchable' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function actingAsUser(): void
    {
        $user = new class extends User
        {
            protected $table = 'users';

            protected $guarded = [];
        };
        $user->forceFill(['id' => 1, 'name' => 'T', 'email' => 't@t.t', 'password' => 'x'])->save();
        $this->actingAs($user::query()->first());
    }

    #[Test]
    public function a_typed_search_survives_a_full_remount_and_actually_filters(): void
    {
        $this->makeConfig();
        $this->actingAsUser();
        SearchPersistStub::create(['name' => 'Nota cliente Alfa', 'status' => 'a']);
        SearchPersistStub::create(['name' => 'Outra coisa', 'status' => 'a']);

        // Digita (dispara updatedSearch -> savePreferences)
        Livewire::test(BaseCrud::class, ['model' => SearchPersistStub::class])
            ->set('search', 'Alfa');

        $this->assertSame(
            'Alfa',
            data_get(UserPreference::where('key', 'crud.'.SearchPersistStub::class)->first()?->value, 'filters.search'),
            'updatedSearch tem de persistir o texto em filters.search.'
        );

        // "Voltar": remonta a tela do zero (novo request)
        $revisit = Livewire::test(BaseCrud::class, ['model' => SearchPersistStub::class]);
        $revisit->assertSet('search', 'Alfa');
        $revisit->assertSee('Nota cliente Alfa');
        $revisit->assertDontSee('Outra coisa');
    }

    #[Test]
    public function clearing_the_search_also_persists_the_empty_state(): void
    {
        $this->makeConfig();
        $this->actingAsUser();

        Livewire::test(BaseCrud::class, ['model' => SearchPersistStub::class])
            ->set('search', 'algo')
            ->set('search', '');

        Livewire::test(BaseCrud::class, ['model' => SearchPersistStub::class])
            ->assertSet('search', '');
    }

    #[Test]
    public function as_guest_the_search_persists_in_session_and_survives_remount(): void
    {
        $this->makeConfig();
        SearchPersistStub::create(['name' => 'Nota cliente Alfa', 'status' => 'a']);
        SearchPersistStub::create(['name' => 'Outra coisa', 'status' => 'a']);

        Livewire::test(BaseCrud::class, ['model' => SearchPersistStub::class])
            ->set('search', 'Alfa');

        Livewire::test(BaseCrud::class, ['model' => SearchPersistStub::class])
            ->assertSet('search', 'Alfa')
            ->assertDontSee('Outra coisa');
    }
}
