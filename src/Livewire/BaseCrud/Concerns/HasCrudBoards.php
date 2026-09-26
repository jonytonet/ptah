<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Ptah\Support\RelationPath;

/**
 * Kanban and calendar: two more ways to look at the SAME listing.
 *
 *   "kanbanConfig":   { "field": "status", "title": "name", "limit": 50 }
 *   "calendarConfig": { "start": "due_date", "end": "end_date", "title": "name" }
 *
 * Both are built on buildBaseQuery() — the table's own query — so search,
 * filters, the company scope, locked filters and the whereHas pre-filter
 * apply exactly as they do to the table; neither can show a row the table
 * would not.
 *
 * Moving a card is an update, and goes through what an update from the form
 * goes through: the same RBAC and config gates, the value checked against the
 * field's options, the record re-read through scopedQuery() (the id comes from
 * the client), the lifecycle hooks and the audit stamp.
 */
trait HasCrudBoards
{
    /** 'Y-m' of the month the calendar shows */
    public string $calendarMonth = '';

    private const KANBAN_LIMIT = 50;

    /** The column of records whose value is not one of the options. Never a drop target. */
    public const KANBAN_OTHER = '__ptah_other__';

    private const CALENDAR_LIMIT = 500;

    public function kanbanEnabled(): bool
    {
        $field = (string) ($this->crudConfig['kanbanConfig']['field'] ?? '');

        return $field !== '' && $this->kanbanOptions() !== [] && ! in_array($field, $this->deniedColumns, true);
    }

    public function calendarEnabled(): bool
    {
        $start = (string) ($this->crudConfig['calendarConfig']['start'] ?? '');
        $model = $this->resolveEloquentModel();

        return $start !== '' && $model !== null
            && ! in_array($start, $this->deniedColumns, true)
            && Schema::hasColumn($model->getTable(), $start);
    }

    /**
     * label => value, from the field's select options.
     *
     * @return array<string, string>
     */
    public function kanbanOptions(): array
    {
        $field = (string) ($this->crudConfig['kanbanConfig']['field'] ?? '');

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            if (($col['colsNomeFisico'] ?? '') === $field && ! empty($col['colsSelect']) && is_array($col['colsSelect'])) {
                return array_map('strval', $col['colsSelect']);
            }
        }

        return [];
    }

    /**
     * Whether a card may move from one column to another under the config's
     * `locked` columns and `transitions` map.
     *
     * A status that STARTS a workflow (concluding an appointment creates the
     * service order, the pet's record and the charge) must not be set by a
     * drag: `locked` columns accept no drop and let no card out, and when
     * `transitions` is given only the listed moves exist — an origin that is
     * not listed moves nowhere (achado #3 do PetPlace).
     */
    public function kanbanAllowed(string $from, string $to): bool
    {
        $cfg = (array) ($this->crudConfig['kanbanConfig'] ?? []);
        $locked = array_map('strval', (array) ($cfg['locked'] ?? []));

        if ($from === $to || in_array($to, $locked, true) || in_array($from, $locked, true) || ! in_array($to, $this->kanbanOptions(), true)) {
            return false;
        }

        if (! array_key_exists('transitions', $cfg) || ! is_array($cfg['transitions'])) {
            return true;
        }

        return in_array($to, array_map('strval', (array) ($cfg['transitions'][$from] ?? [])), true);
    }

    /**
     * from => allowed destinations, for the board's drag and "Move to".
     *
     * @return array<string, list<string>>
     */
    public function kanbanTargets(): array
    {
        $values = array_values($this->kanbanOptions());
        $map = [];

        foreach (array_merge($values, [self::KANBAN_OTHER]) as $from) {
            $map[$from] = array_values(array_filter($values, fn (string $to) => $from === self::KANBAN_OTHER
                ? ! in_array($to, array_map('strval', (array) ($this->crudConfig['kanbanConfig']['locked'] ?? [])), true)
                : $this->kanbanAllowed($from, $to)));
        }

        return $map;
    }

    /**
     * Relations to eager-load for card titles built by an accessor
     * (`kanbanConfig.with` / `calendarConfig.with`), each checked to be a real
     * relationship — one query per card otherwise.
     *
     * @return list<string>
     */
    private function boardWith(string $section): array
    {
        $model = $this->resolveEloquentModel();

        return $model === null ? [] : array_values(array_filter(
            array_map('strval', (array) ($this->crudConfig[$section]['with'] ?? [])),
            fn (string $rel) => RelationPath::isValid($model, $rel)
        ));
    }

    /**
     * @return list<array{label: string, value: string, total: int, cards: list<array{id: mixed, title: string, subtitle: string}>}>
     */
    public function kanbanColumns(): array
    {
        $model = $this->resolveEloquentModel();

        if (! $model || ! $this->kanbanEnabled()) {
            return [];
        }

        $field = $model->getTable().'.'.$this->crudConfig['kanbanConfig']['field'];
        $limit = max(1, (int) ($this->crudConfig['kanbanConfig']['limit'] ?? self::KANBAN_LIMIT));
        $columns = [];

        foreach ($this->kanbanOptions() as $label => $value) {
            [$query] = $this->buildBaseQuery($model);
            $query->where($field, $value)->with($this->boardWith('kanbanConfig'));
            $total = (clone $query)->count();
            // Sem applyGroupingAndSort: um groupBy da tela viraria GROUP BY aqui.
            $query->orderByDesc($model->getTable().'.'.$model->getKeyName());

            $columns[] = [
                'label' => (string) $label,
                'value' => $value,
                'total' => $total,
                'cards' => $query->limit($limit)->get()->map(fn (Model $r) => $this->boardCard($r, 'kanbanConfig'))->all(),
            ];
        }

        // Valor fora das opcoes (ou vazio): sem esta coluna o registro sumiria
        // do quadro sem aviso — e quem olha acha que o ticket foi apagado.
        [$query] = $this->buildBaseQuery($model);
        $values = array_values($this->kanbanOptions());
        $query->where(fn (Builder $q) => $q->whereNotIn($field, $values)->orWhereNull($field))->with($this->boardWith('kanbanConfig'));
        $total = (clone $query)->count();

        if ($total > 0) {
            $query->orderByDesc($model->getTable().'.'.$model->getKeyName());
            $columns[] = [
                'label' => (string) trans('ptah::ui.kanban_other'),
                'value' => self::KANBAN_OTHER,
                'total' => $total,
                'cards' => $query->limit($limit)->get()->map(fn (Model $r) => $this->boardCard($r, 'kanbanConfig'))->all(),
            ];
        }

        return $columns;
    }

    /**
     * Move a card to another column: an update of one field, gated and hooked
     * like a save from the form.
     */
    public function moveCard(int|string $id, string $value): void
    {
        if (! $this->kanbanEnabled()
            || ! $this->authorizeCrudAction('update') || ! $this->crudConfigAllows('update')) {
            $this->dispatch('ptah-toast', title: trans('ptah::ui.crud_permission_denied'), color: 'danger');

            return;
        }

        if (! in_array($value, $this->kanbanOptions(), true)) {
            return;
        }

        $record = $this->scopedQuery()?->find($id);

        if (! $record) {
            return;
        }

        $field = (string) $this->crudConfig['kanbanConfig']['field'];
        $from = (string) $record->getAttribute($field);

        // O destino vem do cliente: a regra de transicao vale aqui tambem.
        $fromIsOption = in_array($from, $this->kanbanOptions(), true);
        if (! in_array($value, $this->kanbanTargets()[$fromIsOption ? $from : self::KANBAN_OTHER] ?? [], true)) {
            $this->dispatch('ptah-toast', title: (string) ($this->crudConfig['kanbanConfig']['lockedMessage'] ?? trans('ptah::ui.kanban_move_not_allowed')), color: 'warn');

            return;
        }

        $data = [$field => $value];

        try {
            $this->beforeUpdate($data, $record);
            $this->executeDynamicHook('beforeUpdate', $data, $record);

            $model = $this->resolveEloquentModel();
            if (($userId = auth()->id()) && in_array('updated_by', $model?->getFillable() ?? [], true)) {
                $data['updated_by'] = $userId;
            }

            $record->update($data);
            $this->afterUpdate($record);
            $this->executeDynamicHook('afterUpdate', $data, $record);
        } catch (\Throwable $e) {
            $this->dispatch('ptah-toast', title: trans('ptah::ui.crud_save_error', ['message' => $e->getMessage()]), color: 'danger');

            return;
        }

        $this->cacheService->invalidateModel($this->model);
        $this->dispatch('ptah-toast', title: trans('ptah::ui.toast_saved'), color: 'success');
    }

    public function calendarShift(int $months): void
    {
        $this->calendarMonth = $this->calendarStart()->addMonths(max(-12, min(12, $months)))->format('Y-m');
    }

    /**
     * Weeks (Sunday first) of the month, each day with its records.
     *
     * @return array{month: string, weeks: list<list<array{date: string, day: int, in_month: bool, today: bool, items: list<array{id: mixed, title: string, subtitle: string}>}>>, overflow: bool}
     */
    public function calendarGrid(): array
    {
        $model = $this->resolveEloquentModel();
        $monthStart = $this->calendarStart();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);
        $byDate = [];
        $overflow = false;

        if ($model && $this->calendarEnabled()) {
            $cfg = $this->crudConfig['calendarConfig'];
            $table = $model->getTable();
            $start = $table.'.'.$cfg['start'];
            $end = ! empty($cfg['end']) && Schema::hasColumn($table, (string) $cfg['end']) ? $table.'.'.$cfg['end'] : null;

            [$query] = $this->buildBaseQuery($model);
            $query->where(function (Builder $q) use ($start, $end, $gridStart, $gridEnd): void {
                $q->whereBetween($start, [$gridStart->startOfDay(), $gridEnd->endOfDay()]);
                if ($end !== null) {
                    // Um evento que comeca antes do mes e termina nele tambem aparece.
                    $q->orWhere(fn (Builder $o) => $o->where($start, '<', $gridStart->startOfDay())->where($end, '>=', $gridStart->startOfDay()));
                }
            })->orderBy($start)->with($this->boardWith('calendarConfig'));

            $records = $query->limit(self::CALENDAR_LIMIT + 1)->get();
            $overflow = $records->count() > self::CALENDAR_LIMIT;

            foreach ($records->take(self::CALENDAR_LIMIT) as $record) {
                $from = $this->boardDate($record->getAttribute($cfg['start']));
                if ($from === null) {
                    continue;
                }
                $to = $end !== null ? ($this->boardDate($record->getAttribute($cfg['end'])) ?? $from) : $from;
                $card = $this->boardCard($record, 'calendarConfig');

                for ($d = max($from, $gridStart->startOfDay()); $d <= min($to, $gridEnd->startOfDay()); $d = $d->addDay()) {
                    $byDate[$d->format('Y-m-d')][] = $card;
                }
            }
        }

        $weeks = [];
        $today = CarbonImmutable::today()->format('Y-m-d');
        for ($d = $gridStart->startOfDay(); $d <= $gridEnd; $d = $d->addDay()) {
            $key = $d->format('Y-m-d');
            $weeks[intdiv((int) $gridStart->startOfDay()->diffInDays($d), 7)][] = [
                'date' => $key,
                'day' => (int) $d->format('j'),
                'in_month' => $d->format('Y-m') === $monthStart->format('Y-m'),
                'today' => $key === $today,
                'items' => $byDate[$key] ?? [],
            ];
        }

        return ['month' => $monthStart->format('Y-m'), 'weeks' => array_values($weeks), 'overflow' => $overflow];
    }

    protected function calendarStart(): CarbonImmutable
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $this->calendarMonth) === 1 ? $this->calendarMonth : CarbonImmutable::today()->format('Y-m');

        try {
            return CarbonImmutable::createFromFormat('!Y-m', $month) ?: CarbonImmutable::today()->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::today()->startOfMonth();
        }
    }

    private function boardDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A card's title and subtitle: the configured `title` field, else the
     * first visible text column; the subtitle is the next one. Denied and
     * `$hidden` columns never appear.
     *
     * @return array{id: mixed, title: string, subtitle: string}
     */
    private function boardCard(Model $record, string $section): array
    {
        $hidden = $record->getHidden();
        $fields = [];

        $titleField = (string) ($this->crudConfig[$section]['title'] ?? '');
        if ($titleField !== '' && ! in_array($titleField, $this->deniedColumns, true) && ! in_array($titleField, $hidden, true)) {
            $fields[] = $titleField;
        }

        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $f = (string) ($col['colsNomeFisico'] ?? '');
            if ($f === '' || $f === 'id' || str_contains($f, '.') || ($col['colsTipo'] ?? 'text') === 'action'
                || in_array($f, $this->deniedColumns, true) || in_array($f, $hidden, true) || in_array($f, $fields, true)
                || ! in_array($col['colsTipo'] ?? 'text', ['text', 'textarea', 'email', ''], true)) {
                continue;
            }
            $fields[] = $f;
            if (count($fields) >= 2) {
                break;
            }
        }

        $value = fn (?string $f) => $f === null ? '' : trim((string) (is_scalar($record->getAttribute($f)) ? $record->getAttribute($f) : ''));

        return [
            'id' => $record->getKey(),
            'title' => $value($fields[0] ?? null) !== '' ? $value($fields[0] ?? null) : '#'.$record->getKey(),
            'subtitle' => $value($fields[1] ?? null),
        ];
    }
}
