<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigEditor;
use Ptah\Models\CrudConfig;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

class HardenedRow extends Model
{
    protected $table = 'hardened_rows';

    protected $fillable = ['name', 'secret', 'note'];
}

/**
 * Three smaller ways client state reached further than it should.
 *
 * `configRoute` chose which screen's config `boot()` loaded, and was writable.
 * `sortBy()` wrote `$sort` directly, bypassing the allowlist that
 * `updatedSort()` applies — the hook only runs when the PROPERTY is updated by
 * the client, not when a value comes in through a method. And the date helpers
 * returned the raw value, unescaped, into `{!! formatCell() !!}` whenever the
 * value did not parse as a date.
 */
class CrudClientStateHardeningTest extends TestCase
{
    use ActsAsPtahUser;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('hardened_rows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Fora da config: nao pode servir de ordenacao.
            $table->string('secret')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        CrudConfig::create([
            'model' => HardenedRow::class,
            'route' => '',
            'config' => [
                'crud' => HardenedRow::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true, 'colsOrderBy' => 'name'],
                    // Coluna de TEXTO renderizada como data: o parse falha.
                    ['colsNomeFisico' => 'note', 'colsNomeLogico' => 'Nota', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true, 'colsRenderer' => 'date'],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => HardenedRow::class]);
    }

    // ── configRoute ────────────────────────────────────────────────────────

    #[Test]
    public function the_client_cannot_point_the_crud_at_another_screens_config(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->crud()->set('configRoute', 'admin/pedidos');
    }

    #[Test]
    public function the_config_editor_cannot_be_pointed_at_another_route_either(): void
    {
        // No editor ela decide PARA QUAL rota a config e gravada.
        $this->actAsMaster();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(CrudConfigEditor::class, ['model' => HardenedRow::class])
            ->set('configRoute', 'admin/outra-tela');
    }

    // ── sortBy ─────────────────────────────────────────────────────────────

    #[Test]
    public function sort_by_refuses_a_column_outside_the_config(): void
    {
        // O oraculo: ordenar por uma coluna que o usuario nao pode ler revela o
        // valor dela pela ordem das linhas.
        $this->crud()
            ->call('sortBy', 'secret')
            ->assertNotSet('sort', 'secret');
    }

    #[Test]
    public function sort_by_still_sorts_by_a_configured_column(): void
    {
        $this->crud()
            ->call('sortBy', 'name')
            ->assertSet('sort', 'name')
            ->assertSet('direction', 'ASC');
    }

    #[Test]
    public function the_property_entry_point_keeps_its_own_guard(): void
    {
        // A outra porta, que ja estava fechada, continua fechada.
        $this->crud()
            ->set('sort', 'secret')
            ->assertSet('sort', 'id');
    }

    // ── Date helpers ───────────────────────────────────────────────────────

    #[Test]
    public function an_unparseable_date_is_escaped_not_rendered_as_html(): void
    {
        HardenedRow::create(['name' => 'linha', 'note' => '<img src=x onerror=alert(1)>']);

        $html = $this->crud()->html();

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html, 'O valor cru virou HTML na celula.');
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html, 'O valor tem de aparecer, escapado — senao o teste passaria a vazio.');
    }

    #[Test]
    public function a_real_date_is_still_formatted(): void
    {
        HardenedRow::create(['name' => 'linha', 'note' => '2026-09-23']);

        $this->assertStringContainsString('23/09/2026', $this->crud()->html());
    }
}
