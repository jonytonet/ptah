<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Crud;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Livewire\Features\SupportEvents\Event;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;
use Ptah\Livewire\BaseCrud\BaseCrud;

use function Livewire\store;

/**
 * The alternative views of a BaseCrud screen over JSON (1.59.0): the kanban
 * board, the calendar and the move of a card — HasCrudBoards, run by the
 * runtime.
 *
 * Both views are the SAME listing as the table (the engine builds them on
 * buildBaseQuery()), so each request carries the listing state the client
 * shows and the engine applies it before drawing the board. Moving a card is
 * the engine's moveCard(): permissions, `locked`, `transitions`, the option
 * check, the scope re-read and the update hooks all run there, unchanged.
 *
 * What leaves the server is whitelisted: a card is its id, title and
 * subtitle (already filtered of denied and `$hidden` columns by boardCard());
 * the config itself never travels — only the derived lists the Blade view
 * draws (the options, the allowed targets, which columns are locked).
 */
class BoardsApiController extends Controller
{
    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    /**
     * The board: one column per option of the kanban field (plus "other
     * values" when some record falls outside them), with what the user may
     * do with each card.
     */
    public function kanban(Request $request, string $screen): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);
        abort_unless($crud->kanbanEnabled(), 404);

        $crud->applyState($request->query());

        // The same gate the Blade view reads before drawing a drag handle or a
        // "Move to": crudConfigAllows('update') && the ACL's update.
        $canMove = $crud->permissions()['canUpdate'];
        $config = (array) ($crud->crudConfig['kanbanConfig'] ?? []);
        $locked = array_map('strval', (array) ($config['locked'] ?? []));

        $columns = [];
        foreach ($crud->kanbanColumns() as $column) {
            $value = (string) $column['value'];
            $columns[] = [
                'label' => (string) $column['label'],
                'value' => $value,
                'total' => (int) $column['total'],
                'locked' => in_array($value, $locked, true),
                'other' => $value === BaseCrud::KANBAN_OTHER,
                'cards' => array_map(self::card(...), $column['cards']),
            ];
        }

        $options = [];
        foreach ($crud->kanbanOptions() as $label => $value) {
            $options[] = ['label' => (string) $label, 'value' => (string) $value];
        }

        return response()->json([
            'columns' => $columns,
            'options' => $options,
            // Without the update permission there is nowhere to move a card
            // to — the client draws neither a drag handle nor a "Move to".
            'targets' => (object) ($canMove ? $crud->kanbanTargets() : []),
            'can_move' => $canMove,
            'locked_message' => (string) ($config['lockedMessage'] ?? ''),
        ]);
    }

    /**
     * Moves a card to another column. The engine decides; this only tells
     * the client what it decided — 200 moved, 422 refused (with the
     * engine's own message, so the card goes back with the reason), 404 the
     * record is not on this screen.
     */
    public function move(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen, 'canUpdate');
        abort_unless($crud->kanbanEnabled(), 404);

        $value = $request->input('value');
        $value = is_string($value) || is_int($value) ? (string) $value : null;

        // moveCard() ignores a value outside the options without a word; the
        // client deserves the reason a drag would never have produced.
        if ($value === null || ! in_array($value, $crud->kanbanOptions(), true)) {
            return response()->json([
                'ok' => false,
                'messages' => [['text' => (string) trans('ptah::ui.kanban_move_not_allowed'), 'level' => 'warn']],
            ], 422);
        }

        $before = count(self::toasts($crud));
        $crud->moveCard($id, $value);
        $messages = array_slice(self::toasts($crud), $before);

        // No word at all: the record was not found through scopedQuery() —
        // another company, a locked filter, gone. Same as a show() out of scope.
        abort_if($messages === [], 404);

        $levels = array_column($messages, 'level');
        $ok = in_array('success', $levels, true) && array_intersect($levels, ['danger', 'warn', 'error']) === [];

        return response()->json(['ok' => $ok, 'messages' => $messages], $ok ? 200 : 422);
    }

    /**
     * One month of the calendar (`month` = 'Y-m', empty for the current one;
     * `shift` = months to move from it, as the arrows do).
     */
    public function calendar(Request $request, string $screen): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);
        abort_unless($crud->calendarEnabled(), 404);

        $crud->applyState($request->query());

        // The engine validates the month (anything else is the current one)
        // and clamps the shift — the same as the Livewire arrows.
        $month = $request->query('month');
        $crud->calendarMonth = is_string($month) ? $month : '';

        $shift = $request->query('shift');
        if (is_string($shift) && preg_match('/^-?\d{1,3}$/', $shift) === 1) {
            $crud->calendarShift((int) $shift);
        }

        $grid = $crud->calendarGrid();
        $weeks = [];
        foreach ($grid['weeks'] as $week) {
            $days = [];
            foreach ($week as $day) {
                $days[] = [
                    'date' => (string) $day['date'],
                    'day' => (int) $day['day'],
                    'in_month' => (bool) $day['in_month'],
                    'today' => (bool) $day['today'],
                    'items' => array_map(self::card(...), $day['items']),
                ];
            }
            $weeks[] = $days;
        }

        return response()->json(['month' => $grid['month'], 'weeks' => $weeks, 'overflow' => (bool) $grid['overflow'], 'empty' => (bool) $grid['empty'], 'nearest' => $grid['nearest']]);
    }

    /**
     * A card or a calendar event: only what the Blade view prints.
     *
     * @param  array{id: mixed, title: string, subtitle: string}  $card
     * @return array{id: int|string, title: string, subtitle: string}
     */
    private static function card(array $card): array
    {
        $id = $card['id'];

        return [
            'id' => is_int($id) ? $id : (is_scalar($id) ? (string) $id : ''),
            'title' => (string) $card['title'],
            'subtitle' => (string) $card['subtitle'],
        ];
    }

    /**
     * The toasts the engine raised so far, in order. CrudRuntime keeps its
     * own copy private; the Livewire event store it forwards every dispatch
     * to holds the same events.
     *
     * @return list<array{text: string, level: string}>
     */
    private static function toasts(CrudRuntime $crud): array
    {
        $out = [];

        foreach ((array) store($crud)->get('dispatched', []) as $event) {
            if (! $event instanceof Event) {
                continue;
            }
            $data = $event->serialize();
            if (($data['name'] ?? null) === 'ptah-toast') {
                $params = (array) ($data['params'] ?? []);
                $out[] = ['text' => (string) ($params['title'] ?? ''), 'level' => (string) ($params['color'] ?? 'info')];
            }
        }

        return $out;
    }
}
