<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\DTO\FilterDTO;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Services\Crud\CrudImportReader;
use Ptah\Services\Crud\Filters\TextFilterStrategy;
use Ptah\Support\PtahMask;
use Ptah\Tests\TestCase;

class PetPlace1586Flag extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];

    protected $casts = ['amount' => 'boolean'];
}

class PetPlace1586Campaign extends Model
{
    protected $table = 'pp_campaigns';

    protected $fillable = ['name', 'trigger', 'whatsapp'];
}

/**
 * PetPlace requests on 1.58.5 (1.58.6): a Sim/Não select over a boolean cast,
 * reserved-word columns in raw SQL, the link renderer's text through the mask,
 * and confirmDelete on a screen without delete.
 */
class LaudoPetPlace1586Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9]));

        Schema::create('pp_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('trigger')->nullable();
            $t->string('whatsapp')->nullable();
            $t->timestamps();
        });
    }

    private function flagScreen(array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => PetPlace1586Flag::class, 'route' => ''], ['config' => [
            'crud' => PetPlace1586Flag::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Destaque', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Sim' => '1', 'Não' => '0'], 'colsHelper' => 'yesOrNot'],
            ],
            'permissions' => $permissions,
        ]]);
    }

    // ── 1. Sim/Não over a boolean cast ──────────────────────────────────────

    #[Test]
    public function editing_a_record_with_nao_saves_without_touching_the_field(): void
    {
        $this->flagScreen();
        $off = PetPlace1586Flag::create(['name' => 'PIX', 'amount' => false]);
        $on = PetPlace1586Flag::create(['name' => 'Cartão', 'amount' => true]);

        foreach ([[$off, false], [$on, true]] as [$record, $expected]) {
            Livewire::test(BaseCrud::class, ['model' => PetPlace1586Flag::class])
                ->call('openEdit', $record->id)
                ->assertSet('formData.amount', $expected ? '1' : '0')
                ->set('formData.name', $record->name.' editado')
                ->call('save')
                ->assertSet('formErrors', []);

            $this->assertSame($expected, $record->fresh()->amount);
            $this->assertSame($record->name.' editado', $record->fresh()->name);
        }
    }

    #[Test]
    public function a_forged_option_is_still_refused(): void
    {
        $this->flagScreen();
        $row = PetPlace1586Flag::create(['name' => 'PIX', 'amount' => false]);

        Livewire::test(BaseCrud::class, ['model' => PetPlace1586Flag::class])
            ->call('openEdit', $row->id)
            ->set('formData.amount', '2')
            ->call('save')
            ->assertSet('formErrors.amount', trans('ptah::ui.sd_value_not_allowed'));
    }

    #[Test]
    public function an_imported_false_cell_matches_the_nao_option(): void
    {
        $col = ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Destaque', 'colsTipo' => 'select', 'colsSelect' => ['Sim' => '1', 'Não' => '0']];

        $this->assertSame(['0', null], CrudImportReader::coerce(false, $col));
        $this->assertSame(['1', null], CrudImportReader::coerce(true, $col));
    }

    // ── 2. Reserved-word column ─────────────────────────────────────────────

    private function campaignScreen(array $extraCol = []): void
    {
        CrudConfig::updateOrCreate(['model' => PetPlace1586Campaign::class, 'route' => ''], ['config' => [
            'crud' => PetPlace1586Campaign::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                array_merge(['colsNomeFisico' => 'trigger', 'colsNomeLogico' => 'Gatilho', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true, 'colsSearchable' => true], $extraCol),
            ],
            'permissions' => [],
        ]]);
        PetPlace1586Campaign::create(['name' => 'Aniversário', 'trigger' => 'birthday']);
        PetPlace1586Campaign::create(['name' => 'Retorno', 'trigger' => 'comeback']);
    }

    #[Test]
    public function search_and_filter_work_on_a_column_named_trigger(): void
    {
        $this->campaignScreen();

        $rows = Livewire::test(BaseCrud::class, ['model' => PetPlace1586Campaign::class])
            ->set('search', 'birth')->instance()->rows;
        $this->assertSame(['Aniversário'], collect($rows->items())->pluck('name')->all());

        $rows = Livewire::test(BaseCrud::class, ['model' => PetPlace1586Campaign::class])
            ->set('search', '')   // the search above persists in the preferences
            ->set('filters.trigger', 'come')->instance()->rows;
        $this->assertSame(['Retorno'], collect($rows->items())->pluck('name')->all());

        // SQLite tolerates a bare `trigger`; MySQL does not. What matters is
        // that the identifier goes through the grammar.
        $sql = (new TextFilterStrategy)->apply(PetPlace1586Campaign::query(), new FilterDTO(field: 'trigger', value: 'x', operator: 'LIKE', type: 'text'))->toSql();
        $this->assertStringContainsString('LOWER("trigger")', $sql);
    }

    // ── 3. Link text through the mask ───────────────────────────────────────

    #[Test]
    public function a_link_shows_the_masked_value_and_points_at_the_raw_one(): void
    {
        PtahMask::define('pp_phone', ['pattern' => ['(00) 0000-0000', '(00) 00000-0000'], 'store' => 'digits']);
        $this->campaignScreen();
        $row = PetPlace1586Campaign::create(['name' => 'Zap', 'whatsapp' => '41988887777']);

        $crud = Livewire::test(BaseCrud::class, ['model' => PetPlace1586Campaign::class])->instance();
        $col = ['colsNomeFisico' => 'whatsapp', 'colsMask' => 'pp_phone', 'colsRenderer' => 'link',
            'colsRendererLinkTemplate' => 'https://wa.me/55%whatsapp%', 'colsRendererLinkNewTab' => true];

        $html = $crud->formatCell($col, $row);
        $this->assertStringContainsString('href="https://wa.me/5541988887777"', $html);
        $this->assertStringContainsString('>(41) 98888-7777</a>', $html);

        // An explicit label wins, and takes %field% too.
        $html = $crud->formatCell($col + ['colsRendererLinkLabel' => 'Falar com %name%'], $row);
        $this->assertStringContainsString('>Falar com Zap</a>', $html);
    }

    // ── 4. confirmDelete on a screen without delete ─────────────────────────

    #[Test]
    public function confirm_delete_does_not_open_where_delete_is_off(): void
    {
        $this->flagScreen(['showDeleteButton' => false]);
        $row = PetPlace1586Flag::create(['name' => 'PIX', 'amount' => false]);

        Livewire::test(BaseCrud::class, ['model' => PetPlace1586Flag::class])
            ->call('confirmDelete', $row->id)
            ->assertSet('showDeleteConfirm', false)
            ->assertDispatched('ptah-toast', title: trans('ptah::ui.crud_permission_denied'));

        // A forged deletingId: refused, now with the reason.
        Livewire::test(BaseCrud::class, ['model' => PetPlace1586Flag::class])
            ->set('deletingId', $row->id)
            ->call('deleteRecord')
            ->assertDispatched('ptah-toast', title: trans('ptah::ui.crud_permission_denied'));

        $this->assertNotNull($row->fresh());
    }
}
