<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Ptah\Models\RecordHistory;
use Ptah\Traits\RecordsHistory;

/**
 * The History button of the edit modal: who changed what, and when.
 *
 * Available when the screen's model uses Ptah\Traits\RecordsHistory and the
 * history table exists (`ptah:history:install`); `history.enabled: false` in
 * the config hides it on one screen.
 *
 * It shows what the screen may show, and no more. The record is loaded
 * through scopedQuery() — the id is client input, and a history is data — so
 * another company's record is not reachable. Only fields configured on this
 * screen are listed, minus the ones the user's column permissions deny; any
 * other changed field is counted, not shown. Otherwise the changelog would
 * print the cost column the screen was configured to hide.
 */
trait HasCrudHistory
{
    public bool $showHistoryModal = false;

    /** @var list<array{when: string, who: string, event: string, changes: list<array{label: string, old: string, new: string}>, hidden: int}> */
    #[Locked]
    public array $historyItems = [];

    public function historyEnabled(): bool
    {
        if (($this->crudConfig['history']['enabled'] ?? true) === false) {
            return false;
        }

        $model = $this->resolveEloquentModel();

        return $model !== null
            && in_array(RecordsHistory::class, class_uses_recursive($model), true)
            && RecordHistory::tableExists()
            && $this->authorizeCrudAction('read');
    }

    public function openHistory(int|string $id): void
    {
        if (! $this->historyEnabled()) {
            return;
        }

        $record = $this->scopedQuery()?->find($id);

        if (! $record) {
            return;
        }

        $cols = [];
        foreach ($this->crudConfig['cols'] ?? [] as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');
            if ($field !== '' && ($col['colsTipo'] ?? '') !== 'action' && ! in_array($field, $this->deniedColumns, true)) {
                $cols[$field] = $col;
            }
        }

        $entries = $record->historyEntries(50);
        $users = $this->historyUserNames($entries);

        $this->historyItems = $entries->map(function (RecordHistory $h) use ($cols, $users): array {
            $changes = [];
            $hidden = 0;

            foreach ((array) $h->changes as $field => $pair) {
                if (! isset($cols[$field])) {
                    $hidden++;

                    continue;
                }

                [$old, $new] = array_pad((array) $pair, 2, null);
                $changes[] = [
                    'label' => (string) ($cols[$field]['colsNomeLogico'] ?? $field),
                    'old' => $this->historyValue($old, $cols[$field]),
                    'new' => $this->historyValue($new, $cols[$field]),
                ];
            }

            return [
                'when' => $h->created_at?->format('d/m/Y H:i') ?? '',
                'who' => $h->user_id ? ($users[($h->user_guard ?? '').':'.$h->user_id] ?? '#'.$h->user_id) : trans('ptah::ui.history_system'),
                'event' => $h->event,
                'changes' => $changes,
                'hidden' => $hidden,
            ];
        })->all();

        $this->showHistoryModal = true;
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyItems = [];
    }

    /**
     * @param  array<string, mixed>  $col
     */
    protected function historyValue(mixed $value, array $col): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (! empty($col['colsSelect']) && is_array($col['colsSelect'])) {
            $label = array_search((string) $value, array_map('strval', $col['colsSelect']), true);
            if ($label !== false) {
                return (string) $label;
            }
        }

        if (($col['colsTipo'] ?? '') === 'boolean') {
            return in_array($value, [1, '1', true, 'true'], true) ? trans('ptah::ui.bool_yes') : trans('ptah::ui.bool_no');
        }

        return is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * "guard:id" => display name, each id resolved through the model of the
     * guard that recorded it (a portal user 5 is not staff user 5).
     *
     * @param  Collection<int, RecordHistory>  $entries
     * @return array<string, string>
     */
    protected function historyUserNames(Collection $entries): array
    {
        $names = [];
        $withUser = $entries->filter(fn (RecordHistory $h) => $h->user_id !== null);

        foreach ($withUser->groupBy(fn (RecordHistory $h) => (string) $h->user_guard) as $guard => $group) {
            $provider = $guard !== '' ? config("auth.guards.{$guard}.provider") : null;
            $model = ($provider ? config("auth.providers.{$provider}.model") : null)
                ?: config('ptah.permissions.user_model')
                ?: config('auth.providers.users.model');

            if (! is_string($model) || ! class_exists($model)) {
                continue;
            }

            try {
                $keyName = (new $model)->getKeyName();
                foreach ($model::query()->whereIn($keyName, $group->pluck('user_id')->unique()->all())->get() as $u) {
                    $names[$guard.':'.$u->getKey()] = (string) ($u->name ?? $u->email ?? '#'.$u->getKey());
                }
            } catch (\Throwable) {
                // Sem nome: a linha mostra o id.
            }
        }

        return $names;
    }
}
