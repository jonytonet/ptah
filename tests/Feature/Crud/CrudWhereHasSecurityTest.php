<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\RelationPath;
use Ptah\Tests\TestCase;

class WhParent extends Model
{
    protected $table = 'wh_parents';

    protected $fillable = ['name'];
}

class WhChild extends Model
{
    use SoftDeletes;

    protected $table = 'wh_children';

    protected $fillable = ['name', 'wh_parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WhParent::class, 'wh_parent_id');
    }

    // Relacao escrita sem tipo de retorno, como era comum antes: tem de valer.
    public function parentUntyped()
    {
        return $this->belongsTo(WhParent::class, 'wh_parent_id');
    }

    // Metodo do host que NAO e relacao, com tipo declarado.
    public function label(): string
    {
        return 'x';
    }
}

/**
 * One forged Livewire request emptied the CRUD's table.
 *
 * `whereHasFilter` and `whereHasCondition` were public and NOT `#[Locked]`, and
 * `buildBaseQuery()` handed the name straight to `whereHas()`. Laravel resolves
 * a relation by calling it — `$this->getModel()->{$relation}()` in
 * `QueriesRelationships::getRelationWithoutConstraints()` — with no validation.
 * A name that is not a real method falls through `Model::__call` to the query
 * builder, so:
 *
 *     whereHasFilter    = 'truncate'
 *     whereHasCondition = ['id', '=', 1]
 *
 * ran `TRUNCATE` on the table. The render then failed with a
 * BadMethodCallException — a 500 — but the rows were already gone. Reproduced
 * before the fix: three rows, one request, zero rows.
 *
 * Anyone with READ access to ANY BaseCrud screen could do it. An empty
 * `whereHasFilter` also silently dropped the pre-filter the host had set, and
 * `scopedQuery()` — which edit, save and delete run on — never applied it at
 * all, so a detail screen filtered by its parent let you edit a record of
 * another parent by id.
 */
class CrudWhereHasSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('wh_parents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('wh_children', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('wh_parent_id');
            $table->softDeletes();
            $table->timestamps();
        });

        CrudConfig::create([
            'model' => WhChild::class,
            'route' => '',
            'config' => [
                'crud' => WhChild::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    /**
     * @return array{0: WhParent, 1: WhParent}
     */
    private function seedFamilies(): array
    {
        $one = WhParent::create(['name' => 'Pedido 1']);
        $two = WhParent::create(['name' => 'Pedido 2']);

        WhChild::create(['name' => 'item-a', 'wh_parent_id' => $one->id]);
        WhChild::create(['name' => 'item-b', 'wh_parent_id' => $one->id]);
        WhChild::create(['name' => 'item-c', 'wh_parent_id' => $two->id]);

        return [$one, $two];
    }

    // ── The attack ─────────────────────────────────────────────────────────

    #[Test]
    public function the_client_cannot_set_the_relation_name_and_the_rows_survive(): void
    {
        $this->seedFamilies();
        $this->assertSame(3, WhChild::count());

        $component = Livewire::test(BaseCrud::class, ['model' => WhChild::class]);

        try {
            $component->set('whereHasCondition', ['id', '=', 1])->set('whereHasFilter', 'truncate');
            $this->fail('whereHasFilter aceitou escrita do cliente.');
        } catch (CannotUpdateLockedPropertyException) {
            // esperado
        }

        $this->assertSame(3, WhChild::count(), 'O TRUNCATE rodou: a tabela foi esvaziada por um request forjado.');
    }

    #[Test]
    public function the_condition_is_locked_too(): void
    {
        $this->seedFamilies();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, ['model' => WhChild::class])
            ->set('whereHasCondition', ['id', '>', 0]);
    }

    #[Test]
    public function the_client_cannot_drop_the_hosts_pre_filter(): void
    {
        [$one] = $this->seedFamilies();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, [
            'model' => WhChild::class,
            'whereHasFilter' => 'parent',
            'whereHasCondition' => ['id', '=', $one->id],
        ])->set('whereHasFilter', '');
    }

    // ── What the host may pass to mount() ──────────────────────────────────

    /**
     * @return array<string, array{0: string}>
     */
    public static function notARelationProvider(): array
    {
        return [
            // Chega ao query builder por __call.
            'truncate (via __call)' => ['truncate'],
            // Existe no proprio Eloquent: um method_exists puro aprovaria.
            'delete (Eloquent)' => ['delete'],
            'save (Eloquent)' => ['save'],
            // Vem de trait do framework: a classe declarante e o model do host.
            'forceDelete (SoftDeletes)' => ['forceDelete'],
            'restore (SoftDeletes)' => ['restore'],
            // Metodo do host com retorno declarado que nao e Relation.
            'label (not a relation)' => ['label'],
            'nonsense' => ['doesNotExist'],
            'injection' => ['parent; drop table x'],
        ];
    }

    #[Test]
    #[DataProvider('notARelationProvider')]
    public function a_name_that_is_not_a_relation_is_refused_at_mount(string $name): void
    {
        [$one] = $this->seedFamilies();

        try {
            Livewire::test(BaseCrud::class, [
                'model' => WhChild::class,
                'whereHasFilter' => $name,
                'whereHasCondition' => ['id', '=', $one->id],
            ]);
            $this->fail("'{$name}' foi aceito como relacao.");
        } catch (\Throwable $e) {
            $this->assertInstanceOf(
                InvalidArgumentException::class,
                $e->getPrevious() ?? $e,
                'Esperava recusa com InvalidArgumentException, veio: '.$e->getMessage()
            );
        }

        $this->assertSame(3, WhChild::withTrashed()->count(), "'{$name}' alterou dados antes de ser recusado.");
    }

    #[Test]
    public function a_real_relation_still_pre_filters_the_listing(): void
    {
        // A contrapartida: travar e validar nao pode ter quebrado o recurso.
        [$one] = $this->seedFamilies();

        $html = Livewire::test(BaseCrud::class, [
            'model' => WhChild::class,
            'whereHasFilter' => 'parent',
            'whereHasCondition' => ['id', '=', $one->id],
        ])->html();

        $this->assertStringContainsString('item-a', $html);
        $this->assertStringContainsString('item-b', $html);
        $this->assertStringNotContainsString('item-c', $html);
    }

    #[Test]
    public function an_untyped_relation_is_still_accepted(): void
    {
        // Relacoes escritas antes de tipo de retorno ser comum sao legitimas.
        [$one] = $this->seedFamilies();

        $html = Livewire::test(BaseCrud::class, [
            'model' => WhChild::class,
            'whereHasFilter' => 'parentUntyped',
            'whereHasCondition' => ['id', '=', $one->id],
        ])->html();

        $this->assertStringContainsString('item-a', $html);
        $this->assertStringNotContainsString('item-c', $html);
    }

    // ── Single-record actions honour the pre-filter ────────────────────────

    #[Test]
    public function editing_a_record_of_another_parent_by_id_is_refused(): void
    {
        // O scopedQuery nao aplicava o pre-filtro, entao a tela de detalhe do
        // pedido 1 abria para edicao, por id, um item do pedido 2.
        [$one] = $this->seedFamilies();
        $foreign = WhChild::where('name', 'item-c')->first();

        Livewire::test(BaseCrud::class, [
            'model' => WhChild::class,
            'whereHasFilter' => 'parent',
            'whereHasCondition' => ['id', '=', $one->id],
        ])
            ->call('openEdit', $foreign->id)
            ->assertNotSet('editingId', $foreign->id);
    }

    #[Test]
    public function deleting_a_record_of_another_parent_by_id_is_refused(): void
    {
        [$one] = $this->seedFamilies();
        $foreign = WhChild::where('name', 'item-c')->first();

        Livewire::test(BaseCrud::class, [
            'model' => WhChild::class,
            'whereHasFilter' => 'parent',
            'whereHasCondition' => ['id', '=', $one->id],
        ])
            ->call('confirmDelete', $foreign->id)
            ->call('deleteRecord');

        $this->assertNotSoftDeleted('wh_children', ['id' => $foreign->id]);
    }

    // ── RelationPath on its own, no Livewire ───────────────────────────────

    #[Test]
    public function a_dotted_path_is_walked_segment_by_segment(): void
    {
        $this->assertTrue(RelationPath::isValid(new WhChild, 'parent'));

        // O segundo segmento e resolvido no model RELACIONADO, onde `truncate`
        // chegaria pelo mesmo caminho sem checagem.
        $this->assertFalse(RelationPath::isValid(new WhChild, 'parent.truncate'));
    }

    #[Test]
    public function an_empty_path_is_no_filter_at_all(): void
    {
        RelationPath::assertValid(new WhChild, '');

        $this->addToAssertionCount(1);
    }
}
