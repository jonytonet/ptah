<?php

declare(strict_types=1);

namespace Ptah\Livewire\Exports;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\Export;

/**
 * "Exportações" panel (Fase 3 — "grande volume"): lists the current user's
 * queued/processing/done/failed background exports, with a download link for
 * finished, non-expired files and a way to remove old ones. Meant to be
 * embedded by the host app (@livewire('ptah-exports-panel')).
 */
class ExportsPanel extends Component
{
    use WithPagination;

    #[Computed]
    public function exports(): LengthAwarePaginator
    {
        return Export::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(10);
    }

    /**
     * Whether any row is still queued/processing — drives the conditional
     * wire:poll in the view (no point polling once everything has settled).
     */
    #[Computed]
    public function hasPending(): bool
    {
        return Export::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['queued', 'processing'])
            ->exists();
    }

    /**
     * Deletes a finished/failed export: the stored file (if any) and the row.
     * Scoped to the current user — never trusts the id alone.
     */
    public function remove(int $exportId): void
    {
        $export = Export::query()->where('user_id', Auth::id())->find($exportId);

        if (! $export) {
            return;
        }

        if ($export->file_disk && $export->file_path) {
            Storage::disk($export->file_disk)->delete($export->file_path);
        }

        $export->delete();
    }

    public function render()
    {
        return view('ptah::livewire.exports.exports-panel', [
            'exports' => $this->exports(),
            'hasPending' => $this->hasPending(),
        ]);
    }
}
