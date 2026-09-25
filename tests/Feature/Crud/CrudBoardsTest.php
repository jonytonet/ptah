<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class BoardTask extends Model
{
    protected $table = 'board_tasks';

    protected $fillable = ['title', 'status', 'due_date', 'end_date', 'company_id'];
}

/**
 * Kanban and calendar are views of the SAME listing: the table's filters and
 * scope apply, and moving a card is an update held to an update's rules.
 */
class CrudBoardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('board_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('status');
            $t->date('due_date')->nullable();
            $t->date('end_date')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->timestamps();
        });

        session(['ptah_company_id' => 1]);

        BoardTask::create(['title' => 'Orçar cliente A', 'status' => 'todo', 'due_date' => '2026-09-10', 'company_id' => 1]);
        BoardTask::create(['title' => 'Entregar pedido B', 'status' => 'doing', 'due_date' => '2026-09-15', 'end_date' => '2026-09-17', 'company_id' => 1]);
        BoardTask::create(['title' => 'Tarefa de outra empresa', 'status' => 'todo', 'due_date' => '2026-09-10', 'company_id' => 2]);

        $this->configure();
    }

    private function configure(array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => BoardTask::class, 'route' => ''], ['config' => [
            'crud' => BoardTask::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true, 'colsSelect' => ['A fazer' => 'todo', 'Fazendo' => 'doing', 'Feito' => 'done']],
            ],
            'permissions' => $permissions,
            'kanbanConfig' => ['field' => 'status'],
            'calendarConfig' => ['start' => 'due_date', 'end' => 'end_date'],
        ]]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => BoardTask::class]);
    }

    #[Test]
    public function the_board_groups_the_listing_by_status_inside_the_company(): void
    {
        $crud = $this->crud()->call('setViewMode', 'kanban')->assertSet('viewMode', 'kanban');

        $columns = collect($crud->instance()->kanbanColumns())->keyBy('value');

        $this->assertSame(['todo', 'doing', 'done'], $columns->keys()->all());
        $this->assertSame(['Orçar cliente A'], array_column($columns['todo']['cards'], 'title'), 'A tarefa de outra empresa nao pode aparecer.');
        $this->assertSame(1, $columns['doing']['total']);
        $crud->assertSee('Fazendo')->assertSee('Entregar pedido B');
    }

    #[Test]
    public function a_record_whose_value_is_not_an_option_is_not_lost_from_the_board(): void
    {
        // Achado rodando no petplace: status "waiting" sem opcao no select.
        BoardTask::create(['title' => 'Aguardando peça', 'status' => 'waiting', 'company_id' => 1]);

        $crud = $this->crud();
        $columns = collect($crud->instance()->kanbanColumns())->keyBy('value');
        $other = $columns[BaseCrud::KANBAN_OTHER];

        $this->assertSame(1, $other['total']);
        $this->assertSame(['Aguardando peça'], array_column($other['cards'], 'title'));

        // Dali sai para uma coluna valida; para ela ninguem entra.
        $task = BoardTask::where('title', 'Aguardando peça')->first();
        $crud->call('moveCard', $task->id, 'doing');
        $this->assertSame('doing', $task->fresh()->status);

        $crud->call('moveCard', $task->id, BaseCrud::KANBAN_OTHER);
        $this->assertSame('doing', $task->fresh()->status);
    }

    #[Test]
    public function the_other_column_does_not_exist_when_every_value_is_an_option(): void
    {
        $values = array_column($this->crud()->instance()->kanbanColumns(), 'value');

        $this->assertNotContains(BaseCrud::KANBAN_OTHER, $values);
    }

    #[Test]
    public function the_board_follows_the_search_like_the_table(): void
    {
        $crud = $this->crud()->set('search', 'Entregar');
        $columns = collect($crud->instance()->kanbanColumns())->keyBy('value');

        $this->assertSame(0, $columns['todo']['total']);
        $this->assertSame(1, $columns['doing']['total']);
    }

    #[Test]
    public function moving_a_card_updates_the_record(): void
    {
        $task = BoardTask::where('title', 'Orçar cliente A')->first();

        $this->crud()->call('moveCard', $task->id, 'done');

        $this->assertSame('done', $task->fresh()->status);
    }

    #[Test]
    public function a_card_cannot_move_to_a_value_outside_the_options_nor_to_another_company(): void
    {
        $task = BoardTask::where('title', 'Orçar cliente A')->first();
        $other = BoardTask::where('company_id', 2)->first();

        $this->crud()
            ->call('moveCard', $task->id, 'hacked')
            ->call('moveCard', $other->id, 'done');

        $this->assertSame('todo', $task->fresh()->status);
        $this->assertSame('todo', $other->fresh()->status);
    }

    #[Test]
    public function moving_is_refused_without_update_permission(): void
    {
        $this->configure(permissions: ['showEditButton' => false]);
        $task = BoardTask::where('title', 'Orçar cliente A')->first();

        $this->crud()->call('moveCard', $task->id, 'done');

        $this->assertSame('todo', $task->fresh()->status);
    }

    #[Test]
    public function the_calendar_places_records_on_their_days_spanning_to_the_end_date(): void
    {
        $crud = $this->crud()->set('calendarMonth', '2026-09')->call('setViewMode', 'calendar')->assertSet('viewMode', 'calendar');

        $days = [];
        foreach ($crud->instance()->calendarGrid()['weeks'] as $week) {
            foreach ($week as $day) {
                $days[$day['date']] = array_column($day['items'], 'title');
            }
        }

        $this->assertSame(['Orçar cliente A'], $days['2026-09-10']);
        foreach (['2026-09-15', '2026-09-16', '2026-09-17'] as $d) {
            $this->assertSame(['Entregar pedido B'], $days[$d], "Evento de 15 a 17 deveria estar em {$d}.");
        }
        $this->assertSame([], $days['2026-09-18']);
        $crud->assertSee('Orçar cliente A');
    }

    #[Test]
    public function the_calendar_navigates_months(): void
    {
        $this->crud()
            ->set('calendarMonth', '2026-09')
            ->call('calendarShift', 1)->assertSet('calendarMonth', '2026-10')
            ->call('calendarShift', -2)->assertSet('calendarMonth', '2026-08');
    }

    #[Test]
    public function the_modes_do_not_exist_on_a_screen_that_does_not_configure_them(): void
    {
        CrudConfig::where('model', BoardTask::class)->update(['config' => json_encode([
            'crud' => BoardTask::class,
            'cols' => [['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text']],
            'permissions' => [],
        ])]);

        $this->crud()
            ->call('setViewMode', 'kanban')->assertNotSet('viewMode', 'kanban')
            ->call('setViewMode', 'calendar')->assertNotSet('viewMode', 'calendar');
    }
}
