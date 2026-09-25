<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
            $query->where($field, $value);
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
            })->orderBy($start);

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
