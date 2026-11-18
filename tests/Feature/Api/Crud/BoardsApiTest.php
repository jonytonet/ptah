<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Crud;

use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ApiBoardTask extends Model
{
    protected $table = 'api_board_tasks';

    protected $fillable = ['title', 'status', 'note', 'secret', 'due_date', 'end_date', 'company_id'];

    protected $hidden = ['secret'];
}

/**
 * Kanban, calendar and the move of a card through the API (1.59.0): the
 * same filtered listing as the table, the same scope, and a move held to the
 * engine's rules — refused moves come back as 422 with the engine's message.
 */
class BoardsApiTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9, 'name' => 'Ana', 'email' => 'ana@example.test']));

        Schema::create('api_board_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('status');
            $t->string('note')->nullable();
            $t->string('secret')->nullable();
            $t->date('due_date')->nullable();
            $t->date('end_date')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->timestamps();
        });

        session(['ptah_company_id' => 1]);

        ApiBoardTask::create(['title' => 'Orçar cliente A', 'status' => 'todo', 'note' => 'urgente', 'secret' => 'S3CR3T', 'due_date' => '2026-09-10', 'company_id' => 1]);
        ApiBoardTask::create(['title' => 'Entregar pedido B', 'status' => 'doing', 'due_date' => '2026-09-15', 'end_date' => '2026-09-17', 'company_id' => 1]);
        ApiBoardTask::create(['title' => 'Tarefa de outra empresa', 'status' => 'todo', 'due_date' => '2026-09-10', 'company_id' => 2]);

        CrudScreen::register('tasks', ApiBoardTask::class)->configRoute('');
        $this->configure();
    }

    /**
     * @param  array<string, mixed>  $permissions
     * @param  array<string, mixed>|null  $kanban  null: no kanban
     * @param  array<string, mixed>|null  $calendar  null: no calendar
     */
    private function configure(array $permissions = [], ?array $kanban = [], ?array $calendar = []): void
    {
        $config = [
            'crud' => ApiBoardTask::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'note', 'colsNomeLogico' => 'Nota', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true, 'colsSelect' => ['A fazer' => 'todo', 'Fazendo' => 'doing', 'Feito' => 'done']],
            ],
            'permissions' => $permissions,
        ];
        if ($kanban !== null) {
            $config['kanbanConfig'] = array_merge(['field' => 'status'], $kanban);
        }
        if ($calendar !== null) {
            $config['calendarConfig'] = array_merge(['start' => 'due_date', 'end' => 'end_date'], $calendar);
        }

        CrudConfig::updateOrCreate(['model' => ApiBoardTask::class, 'route' => ''], ['config' => $config]);
    }

    private function task(string $title): ApiBoardTask
    {
        return ApiBoardTask::where('title', $title)->firstOrFail();
    }

    /** @return array<string, array<string, mixed>> */
    private function columns(string $query = ''): array
    {
        $json = $this->getJson('/ptah/api/crud/tasks/kanban'.$query)->assertOk()->json();

        return collect($json['columns'])->keyBy('value')->all();
    }

    // ── Kanban ──────────────────────────────────────────────────────────────

    #[Test]
    public function the_board_is_the_listing_grouped_by_status_inside_the_company(): void
    {
        $json = $this->getJson('/ptah/api/crud/tasks/kanban')->assertOk()->json();
        $columns = collect($json['columns'])->keyBy('value');

        $this->assertSame(['todo', 'doing', 'done'], $columns->keys()->all());
        $this->assertSame('A fazer', $columns['todo']['label']);
        $this->assertSame(1, $columns['todo']['total'], 'A tarefa de outra empresa nao pode aparecer.');
        $this->assertSame(['Orçar cliente A'], array_column($columns['todo']['cards'], 'title'));
        $this->assertSame('urgente', $columns['todo']['cards'][0]['subtitle']);
        $this->assertSame(['id', 'title', 'subtitle'], array_keys($columns['todo']['cards'][0]));
        $this->assertFalse($columns['todo']['locked']);
        $this->assertFalse($columns['todo']['other']);

        $this->assertSame([['label' => 'A fazer', 'value' => 'todo'], ['label' => 'Fazendo', 'value' => 'doing'], ['label' => 'Feito', 'value' => 'done']], $json['options']);
        $this->assertTrue($json['can_move']);
        $this->assertSame(['doing', 'done'], $json['targets']['todo']);
        $this->assertSame(['todo', 'doing', 'done'], $json['targets'][BaseCrud::KANBAN_OTHER]);
        $this->assertSame('', $json['locked_message']);

        // Nothing of the config or of the record beyond the card leaves.
        $raw = $this->getJson('/ptah/api/crud/tasks/kanban')->getContent();
        $this->assertStringNotContainsString('S3CR3T', (string) $raw);
        $this->assertStringNotContainsString('ApiBoardTask', (string) $raw);
        $this->assertStringNotContainsString('kanbanConfig', (string) $raw);
    }

    #[Test]
    public function the_board_follows_the_listing_state_sent(): void
    {
        $columns = $this->columns('?search=Entregar');
        $this->assertSame(0, $columns['todo']['total']);
        $this->assertSame(1, $columns['doing']['total']);

        $columns = $this->columns('?filters[title]=Orçar');
        $this->assertSame(1, $columns['todo']['total']);
        $this->assertSame(0, $columns['doing']['total']);
    }

    #[Test]
    public function a_hidden_attribute_never_becomes_a_card_title(): void
    {
        $this->configure(kanban: ['title' => 'secret']);

        $card = $this->columns()['todo']['cards'][0];

        $this->assertSame('Orçar cliente A', $card['title']);
        $this->assertStringNotContainsString('S3CR3T', (string) json_encode($card));
    }

    #[Test]
    public function locked_columns_and_the_other_values_column_are_flagged(): void
    {
        $this->configure(kanban: ['locked' => ['done'], 'transitions' => ['todo' => ['doing']], 'lockedMessage' => 'Pelo painel.']);
        ApiBoardTask::create(['title' => 'Aguardando peça', 'status' => 'waiting', 'company_id' => 1]);

        $json = $this->getJson('/ptah/api/crud/tasks/kanban')->assertOk()->json();
        $columns = collect($json['columns'])->keyBy('value');

        $this->assertTrue($columns['done']['locked']);
        $this->assertTrue($columns[BaseCrud::KANBAN_OTHER]['other']);
        $this->assertSame(['Aguardando peça'], array_column($columns[BaseCrud::KANBAN_OTHER]['cards'], 'title'));
        $this->assertSame('Pelo painel.', $json['locked_message']);
        $this->assertSame(['todo' => ['doing'], 'doing' => [], 'done' => [], BaseCrud::KANBAN_OTHER => ['todo', 'doing']], $json['targets']);
    }

    #[Test]
    public function without_the_update_permission_the_board_offers_no_move(): void
    {
        $this->configure(permissions: ['showEditButton' => false]);

        $json = $this->getJson('/ptah/api/crud/tasks/kanban')->assertOk()->json();

        $this->assertFalse($json['can_move']);
        $this->assertSame([], $json['targets']);
    }

    // ── Move ────────────────────────────────────────────────────────────────

    #[Test]
    public function moving_a_card_updates_the_record(): void
    {
        $task = $this->task('Orçar cliente A');

        $this->postJson("/ptah/api/crud/tasks/{$task->id}/move", ['value' => 'done'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.level', 'success');

        $this->assertSame('done', $task->fresh()->status);
    }

    #[Test]
    public function a_move_the_config_does_not_allow_is_refused_with_its_message(): void
    {
        $this->configure(kanban: ['locked' => ['done'], 'transitions' => ['todo' => ['doing'], 'doing' => ['todo']], 'lockedMessage' => 'Concluir é feito pelo painel do setor.']);
        $task = $this->task('Orçar cliente A');

        $this->postJson("/ptah/api/crud/tasks/{$task->id}/move", ['value' => 'done'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('messages.0.text', 'Concluir é feito pelo painel do setor.');
        $this->assertSame('todo', $task->fresh()->status);

        $this->postJson("/ptah/api/crud/tasks/{$task->id}/move", ['value' => 'doing'])->assertOk();
        $this->assertSame('doing', $task->fresh()->status);
    }

    #[Test]
    public function a_forged_value_is_refused(): void
    {
        $task = $this->task('Orçar cliente A');

        foreach (['hacked', BaseCrud::KANBAN_OTHER, '', null, ['done']] as $value) {
            $this->postJson("/ptah/api/crud/tasks/{$task->id}/move", ['value' => $value])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);
        }
        $this->postJson("/ptah/api/crud/tasks/{$task->id}/move")->assertStatus(422);

        $this->assertSame('todo', $task->fresh()->status);
    }

    #[Test]
    public function a_card_of_another_company_is_not_found(): void
    {
        $other = $this->task('Tarefa de outra empresa');

        $this->postJson("/ptah/api/crud/tasks/{$other->id}/move", ['value' => 'done'])->assertNotFound();
        $this->postJson('/ptah/api/crud/tasks/99999/move', ['value' => 'done'])->assertNotFound();

        $this->assertSame('todo', $other->fresh()->status);
    }

    #[Test]
    public function moving_is_forbidden_without_the_update_permission(): void
    {
        $this->configure(permissions: ['showEditButton' => false]);
        $task = $this->task('Orçar cliente A');

        $this->postJson("/ptah/api/crud/tasks/{$task->id}/move", ['value' => 'done'])->assertForbidden();

        $this->assertSame('todo', $task->fresh()->status);
    }

    // ── Calendar ────────────────────────────────────────────────────────────

    /** @return array<string, list<string>> date => titles */
    private function days(array $json): array
    {
        $days = [];
        foreach ($json['weeks'] as $week) {
            foreach ($week as $day) {
                $days[$day['date']] = array_column($day['items'], 'title');
            }
        }

        return $days;
    }

    #[Test]
    public function the_calendar_places_the_records_on_their_days(): void
    {
        $json = $this->getJson('/ptah/api/crud/tasks/calendar?month=2026-09')->assertOk()->json();

        $this->assertSame('2026-09', $json['month']);
        $this->assertFalse($json['overflow']);
        $this->assertCount(7, $json['weeks'][0]);
        $this->assertSame(['date', 'day', 'in_month', 'today', 'items'], array_keys($json['weeks'][0][0]));

        $days = $this->days($json);
        $this->assertSame(['Orçar cliente A'], $days['2026-09-10'], 'A tarefa de outra empresa nao pode aparecer.');
        foreach (['2026-09-15', '2026-09-16', '2026-09-17'] as $d) {
            $this->assertSame(['Entregar pedido B'], $days[$d]);
        }
        $this->assertSame([], $days['2026-09-18']);
        $this->assertSame(['id', 'title', 'subtitle'], array_keys($json['weeks'][1][4]['items'][0] ?? ['id' => 0, 'title' => '', 'subtitle' => '']));
    }

    #[Test]
    public function the_calendar_follows_the_listing_state_and_navigates(): void
    {
        $days = $this->days($this->getJson('/ptah/api/crud/tasks/calendar?month=2026-09&search=Entregar')->assertOk()->json());
        $this->assertSame([], $days['2026-09-10']);
        $this->assertSame(['Entregar pedido B'], $days['2026-09-15']);

        $this->getJson('/ptah/api/crud/tasks/calendar?month=2026-09&shift=1')->assertJsonPath('month', '2026-10');
        $this->getJson('/ptah/api/crud/tasks/calendar?month=2026-09&shift=-2')->assertJsonPath('month', '2026-07');
        // The engine clamps a shift to a year, as the arrows never go further.
        $this->getJson('/ptah/api/crud/tasks/calendar?month=2026-09&shift=500')->assertJsonPath('month', '2027-09');

        $today = CarbonImmutable::today()->format('Y-m');
        $this->getJson('/ptah/api/crud/tasks/calendar')->assertJsonPath('month', $today);
        $this->getJson('/ptah/api/crud/tasks/calendar?month=../../etc')->assertJsonPath('month', $today);
        $this->getJson('/ptah/api/crud/tasks/calendar?month[]=2026-09')->assertJsonPath('month', $today);
    }

    // ── Switched off ────────────────────────────────────────────────────────

    #[Test]
    public function the_views_do_not_exist_on_a_screen_that_does_not_configure_them(): void
    {
        $this->configure(kanban: null, calendar: null);
        $task = $this->task('Orçar cliente A');

        $this->getJson('/ptah/api/crud/tasks/kanban')->assertNotFound();
        $this->getJson('/ptah/api/crud/tasks/calendar')->assertNotFound();
        $this->postJson("/ptah/api/crud/tasks/{$task->id}/move", ['value' => 'done'])->assertNotFound();
        $this->assertSame('todo', $task->fresh()->status);

        $this->getJson('/ptah/api/crud/nope/kanban')->assertNotFound();
    }
}
