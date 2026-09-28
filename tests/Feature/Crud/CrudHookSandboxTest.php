<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class SandboxItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * ALTO, auditoria de 28/09/2026 (1.41.7): a expressao inline recebia `record`
 * e `user` como OBJETOS, e o ExpressionLanguage chama metodo de objeto — o
 * `__call` do Eloquent levava ao query builder. `record.newQuery().update(...)`
 * alterava a tabela inteira a partir de um hook de config.
 */
class CrudHookSandboxTest extends TestCase
{
    private function screen(string $beforeUpdate): SandboxItem
    {
        $target = SandboxItem::create(['name' => 'alvo', 'status' => 'open']);
        SandboxItem::create(['name' => 'outro', 'status' => 'open']);

        CrudConfig::updateOrCreate(['model' => SandboxItem::class, 'route' => ''], ['config' => [
            'crud' => SandboxItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'text', 'colsGravar' => true],
            ],
            'permissions' => [],
            'lifecycleHooks' => ['beforeUpdate' => $beforeUpdate],
        ]]);

        $this->actingAs(new GenericUser(['id' => 4, 'name' => 'Ana', 'email' => 'ana@x.test']));

        return $target;
    }

    private function saveEdit(SandboxItem $item): void
    {
        Livewire::test(BaseCrud::class, ['model' => SandboxItem::class])
            ->call('openEdit', $item->id)
            ->set('formData.status', 'done')
            ->call('save');
    }

    #[Test]
    public function a_hook_cannot_reach_the_query_builder_through_record(): void
    {
        $item = $this->screen("record.newQuery().update({'name': 'pwned'})");

        $this->saveEdit($item);

        $this->assertSame(0, SandboxItem::where('name', 'pwned')->count(), 'A expressao alterou a tabela pelo query builder.');
    }

    #[Test]
    public function a_hook_cannot_call_methods_on_the_user(): void
    {
        $item = $this->screen("user.getAuthIdentifier() ~ ''");

        $this->saveEdit($item);

        // O save segue (hook nao critico) — o que importa e que o metodo nao existe.
        $this->assertSame('done', $item->fresh()->status);
    }

    #[Test]
    public function reading_fields_still_works(): void
    {
        $item = $this->screen("merge(data, {'name': upper(record.name) ~ ' por ' ~ user.name})");

        $this->saveEdit($item);

        $this->assertSame('ALVO por Ana', $item->fresh()->name);
    }
}
