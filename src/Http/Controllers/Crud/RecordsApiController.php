<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Crud;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;
use Ptah\Models\Attachment;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Record history and record attachments of the BaseCrud over the headless
 * API (1.59.0) — the History and Attachments buttons of the edit modal.
 *
 * The engine does the work: openHistory(), openAttachments(),
 * uploadAttachments(), downloadAttachment(), deleteAttachment() and
 * attachmentCount() of HasCrudHistory / HasCrudAttachments. Each of them
 * re-reads the record through scopedQuery() (company, the screen's locked
 * filters, whereHas pre-filter and host scope), and every attachment is
 * looked up UNDER that record. This controller only turns the silent
 * "nothing happened" of a Livewire action into the status a client needs:
 * 403 when the feature or the permission is off, 404 when the record (or the
 * attachment) is out of this screen's reach.
 */
class RecordsApiController extends Controller
{
    /** At most one page of ids per counts request — never more than the listing can show. */
    private const MAX_COUNT_IDS = 200;

    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    // ── History ─────────────────────────────────────────────────────────────

    public function history(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);
        abort_unless($crud->historyEnabled(), 403, trans('ptah::ui.crud_permission_denied'));

        $crud->openHistory($id);

        // openHistory() opens the modal only for a record the screen reaches.
        abort_unless($crud->showHistoryModal, 404);

        // The trait already reduced each entry to labels and display values of
        // the columns this screen (and user) may see — passed on as built.
        return response()->json(['data' => array_map(static fn (array $item) => [
            'when' => $item['when'],
            'who' => $item['who'],
            'event' => $item['event'],
            'changes' => array_map(static fn (array $change) => [
                'label' => $change['label'],
                'old' => $change['old'],
                'new' => $change['new'],
            ], $item['changes']),
            'hidden' => $item['hidden'],
        ], $crud->historyItems)]);
    }

    // ── Attachments ─────────────────────────────────────────────────────────

    public function attachments(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->openAttachments($request, $screen, $id);

        return response()->json($this->attachmentsPayload($crud, $screen, $id));
    }

    /**
     * Multipart POST, `files[]` — the same rules (count, size, extension and
     * real MIME from ptah.attachments) as the Livewire upload, because it is
     * the same method. A refusal is Laravel's 422 with the errors under
     * `files` / `files.N`.
     */
    public function upload(Request $request, string $screen, int $id): JsonResponse
    {
        $crud = $this->openAttachments($request, $screen, $id, write: true);

        // Files and plain values together: a string or a forged path sent
        // under `files` is not dropped silently, it fails the same `file` rule.
        $files = $request->all()['files'] ?? [];
        $crud->attachmentUploads = is_array($files) ? array_values($files) : [$files];

        try {
            $crud->uploadAttachments();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages($this->renameUploadErrors($e->errors()));
        }

        return response()->json([
            'ok' => true,
            ...$this->attachmentsPayload($crud, $screen, $id),
            // The toast uploadAttachments() dispatches (CrudRuntime keeps its
            // captured events private).
            'messages' => [['text' => trans('ptah::ui.attachments_uploaded'), 'level' => 'success']],
        ], 201);
    }

    /** The file, streamed with its original name — never a public URL. */
    public function download(Request $request, string $screen, int $id, int $attachment): StreamedResponse
    {
        $crud = $this->openAttachments($request, $screen, $id);

        $response = $crud->downloadAttachment($attachment);

        // Not this record's attachment, or no longer in storage.
        abort_if($response === null, 404, trans('ptah::ui.attachments_missing'));

        return $response;
    }

    public function destroy(Request $request, string $screen, int $id, int $attachment): JsonResponse
    {
        $crud = $this->openAttachments($request, $screen, $id, write: true);

        // deleteAttachment() is silent when the id is not under the open
        // record: whether the row went away is the answer.
        $existed = Attachment::query()->whereKey($attachment)->exists();
        $crud->deleteAttachment($attachment);
        abort_unless($existed && ! Attachment::query()->whereKey($attachment)->exists(), 404);

        return response()->json(['ok' => true, ...$this->attachmentsPayload($crud, $screen, $id), 'messages' => []]);
    }

    /**
     * Attachment counts of the rows of a page, in one request: `ids[]=1&ids[]=2`.
     * The ids go through the screen's scope, as attachmentCount() does — an
     * id out of reach counts 0, as on the Livewire screen — in a fixed
     * number of queries, whatever the page size.
     */
    public function counts(Request $request, string $screen): JsonResponse
    {
        $crud = $this->resolver->resolve($request, $screen);
        abort_unless($crud->attachmentsEnabled(), 403, trans('ptah::ui.crud_permission_denied'));

        $ids = $request->input('ids', []);
        $ids = array_slice(array_values(array_unique(array_filter(
            is_array($ids) ? $ids : [],
            static fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))
        ))), 0, self::MAX_COUNT_IDS);

        // The scoping of attachmentCount(), in two queries for the whole page.
        $byId = $crud->attachmentCounts(array_values(array_unique(array_map('intval', $ids))));
        $counts = [];
        foreach ($ids as $rowId) {
            $counts[(string) $rowId] = $byId[(int) $rowId] ?? 0;
        }

        return response()->json(['counts' => (object) $counts]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * The runtime with the record's attachments open, or the refusal: 403 when
     * attachments are off for this user (or, for a change, writing is), 404
     * when the record is out of the screen's reach.
     */
    private function openAttachments(Request $request, string $screen, int $id, bool $write = false): CrudRuntime
    {
        $crud = $this->resolver->resolve($request, $screen);
        abort_unless($crud->attachmentsEnabled(), 403, trans('ptah::ui.crud_permission_denied'));
        abort_if($write && ! $crud->attachmentsCanWrite(), 403, trans('ptah::ui.crud_permission_denied'));

        $crud->openAttachments($id);
        abort_if($crud->attachmentsRecordId === null, 404);

        return $crud;
    }

    /** @return array{data: list<array<string, mixed>>, count: int} */
    private function attachmentsPayload(CrudRuntime $crud, string $screen, int $id): array
    {
        $data = array_map(fn (array $item) => [
            'id' => (int) $item['id'],
            'name' => (string) $item['name'],
            'size' => (string) $item['size'],
            'when' => (string) $item['when'],
            'mime' => $item['mime'] !== null ? (string) $item['mime'] : null,
            // Relative: the client opens it as a link on the same origin.
            'url' => route('ptah.api.crud.records.attachments.download', ['screen' => $screen, 'id' => $id, 'attachment' => $item['id']], false),
        ], $crud->attachmentItems);

        return ['data' => $data, 'count' => $crud->attachmentCount($id)];
    }

    /**
     * The engine's property name (`attachmentUploads.0`) → the request's
     * (`files.0`), so an error lands on what the client sent.
     *
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    private function renameUploadErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $key => $messages) {
            $out[(string) preg_replace('/^attachmentUploads/', 'files', $key)] = $messages;
        }

        return $out;
    }
}
