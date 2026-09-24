<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Ptah\Models\RecordHistory;

/**
 * Who changed what, and when — on every save of the model.
 *
 * `use RecordsHistory;` on a model, run `php artisan ptah:history:install`
 * and migrate. From then on each create, update, delete and restore writes
 * one row with the fields that changed (`{field: [old, new]}`), the user and
 * the active company. It hooks the MODEL, not the screen, so a change from a
 * spreadsheet import, the API or a queued job is recorded like one from the
 * form. BaseCrud shows it in the edit modal (History).
 *
 * Never recorded: the model's `$hidden` attributes (a password hash has no
 * business in a changelog anyone can read), timestamps, the audit stamps, and
 * anything in the model's own `$historyExcept`. Long values are truncated.
 *
 * History is auxiliary: if its table is missing or the insert fails, the
 * host's save goes on and one warning is logged — losing a changelog line
 * must never lose the change. `ptah:check` reports a model using this trait
 * without the table.
 */
trait RecordsHistory
{
    public static function bootRecordsHistory(): void
    {
        static::created(fn (Model $m) => $m->ptahRecordHistory('created', self::ptahHistoryDiff($m, [], $m->getAttributes())));

        static::updated(function (Model $m): void {
            $changes = [];
            foreach (array_keys($m->getChanges()) as $key) {
                $changes[$key] = [$m->getOriginal($key), $m->getAttribute($key)];
            }

            $diff = self::ptahHistoryFilter($m, $changes);
            if ($diff !== []) {
                $m->ptahRecordHistory('updated', $diff);
            }
        });

        static::deleted(fn (Model $m) => $m->ptahRecordHistory('deleted', []));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $m) => $m->ptahRecordHistory('restored', []));
        }
    }

    /**
     * The newest entries first.
     *
     * @return Collection<int, RecordHistory>
     */
    public function historyEntries(int $limit = 50): Collection
    {
        return RecordHistory::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', (string) $this->getKey())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function historyTableExists(): bool
    {
        return RecordHistory::tableExists();
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     */
    protected function ptahRecordHistory(string $event, array $changes): void
    {
        if (! self::historyTableExists()) {
            return;
        }

        try {
            RecordHistory::query()->create([
                'subject_type' => $this->getMorphClass(),
                'subject_id' => (string) $this->getKey(),
                'event' => $event,
                'changes' => $changes === [] ? null : $changes,
                'user_id' => is_numeric(Auth::id()) ? (int) Auth::id() : null,
                // Dois guards (equipe e portal) repetem ids: o id sozinho nao diz quem.
                'user_guard' => Auth::check() ? Auth::getDefaultDriver() : null,
                'company_id' => function_exists('ptah_company_id') && ptah_company_id() > 0 ? ptah_company_id() : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Ptah RecordsHistory: history row not written', [
                'model' => static::class,
                'id' => $this->getKey(),
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private static function ptahHistoryDiff(Model $m, array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            $changes[$key] = [$before[$key] ?? null, $value];
        }

        return self::ptahHistoryFilter($m, $changes);
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private static function ptahHistoryFilter(Model $m, array $changes): array
    {
        $except = array_merge(
            $m->getHidden(),
            [$m->getKeyName(), $m->getCreatedAtColumn(), $m->getUpdatedAtColumn(), 'deleted_at', 'created_by', 'updated_by', 'deleted_by', 'remember_token'],
            property_exists($m, 'historyExcept') ? (array) $m->historyExcept : [],
        );

        $out = [];
        foreach ($changes as $key => [$old, $new]) {
            if (in_array($key, $except, true) || $old === $new) {
                continue;
            }
            $out[$key] = [self::ptahHistoryValue($old), self::ptahHistoryValue($new)];
        }

        return $out;
    }

    private static function ptahHistoryValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if (is_array($value) || is_object($value)) {
            $value = (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($value) && mb_strlen($value) > 500) {
            return mb_substr($value, 0, 500).'…';
        }

        return $value;
    }
}
