<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Crud;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;
use Ptah\Services\Crud\CrudImportReader;

/**
 * The spreadsheet import wizard of the BaseCrud (HasCrudImport) over JSON.
 *
 * The Livewire screen keeps the uploaded file in a temporary upload between
 * the steps (upload → columns → review → done). A stateless API cannot, so
 * the first step stores the file ONCE on the server and answers an opaque
 * token bound to the user and the screen; every later step re-establishes the
 * component from that token (the engine re-reads, re-checks size/type/row
 * limits — updatedImportFile()) plus the mapping the client sends, and then
 * calls the engine's own previewImport()/runImport(). The client never names
 * a path, and the engine never trusts the preview: runImport() validates
 * every row again, as it does on Livewire.
 */
class ImportApiController extends Controller
{
    /** Where the uploads wait between the steps (the `local` disk is private). */
    private const DIR = 'ptah-import';

    /** A wizard left open longer than this starts over (Livewire's own temp uploads live a day). */
    private const TTL_SECONDS = 7200;

    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    /** What step 1 shows: the row limit of the hint, the accepted types, the size limit. */
    public function info(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen);
        $engine = $this->engine($crud);

        return response()->json([
            'max_rows' => $engine['max_rows'],
            'max_kb' => max(1, (int) ($crud->crudConfig['importConfig']['maxKb'] ?? 10240)),
            'extensions' => CrudImportReader::EXTENSIONS,
            'fields' => $engine['fields'],
        ]);
    }

    /**
     * Step 1 → 2: the file goes through the engine's checks and, accepted, is
     * kept for the next steps. Answers the token, the headers and the
     * automatic mapping the Livewire screen starts from.
     */
    public function upload(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen);
        $file = $request->file('file');

        $crud->importFile = $file instanceof UploadedFile ? $file : null;
        // The engine's `required|file` refuses a missing one; the second test
        // only tells the type of $file below.
        if (($refused = $this->accept($crud)) !== null || ! $file instanceof UploadedFile) {
            return $refused ?? $this->refuse('');
        }

        $disk = $this->disk();
        $owner = $this->owner($request);
        $this->sweep($disk, $owner, $screen);

        $token = Str::random(40);
        $disk->putFileAs(self::DIR, $file, $token.'.upload');
        $disk->put(self::DIR.'/'.$token.'.json', (string) json_encode([
            'owner' => $owner,
            'screen' => $screen,
            // The engine reads the type from the client extension; it passed
            // the `mimes` check against the content above.
            'extension' => strtolower((string) $file->getClientOriginalExtension()),
            'expires' => now()->getTimestamp() + self::TTL_SECONDS,
        ]));

        return response()->json([
            'ok' => true,
            'token' => $token,
            'headers' => $crud->importHeaders,
            'mapping' => (object) $this->mappingOut($crud->importMapping),
            'fields' => $this->engine($crud)['fields'],
        ]);
    }

    /** Step 2 → 3: the review of the file with the client's mapping. */
    public function preview(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen);
        if (($refused = $this->establish($crud, $request, $screen)) !== null) {
            return $refused;
        }

        $crud->previewImport();

        return response()->json(['ok' => true, 'preview' => $this->previewOut($crud->importPreview)]);
    }

    /**
     * Step 3 → 4: the import itself — all or nothing, in one transaction, as
     * on Livewire. A file the engine refuses on the way (a row became invalid,
     * the mapping changed) comes back as the review, with 422.
     */
    public function run(Request $request, string $screen): JsonResponse
    {
        $crud = $this->runtime($request, $screen);
        if (($refused = $this->establish($crud, $request, $screen)) !== null) {
            return $refused;
        }

        $crud->runImport();

        if ($crud->importStep !== 4) {
            return response()->json(['ok' => false, 'preview' => $this->previewOut($crud->importPreview), 'messages' => []], 422);
        }

        // The file was used: a second run would import it twice.
        $this->forget($this->disk(), (string) $request->input('token'));

        $result = $crud->importResult;
        $failed = isset($result['error']);

        return response()->json([
            'ok' => ! $failed,
            'result' => $failed
                ? ['created' => 0, 'updated' => 0, 'error' => (string) $result['error']]
                : ['created' => (int) ($result['created'] ?? 0), 'updated' => (int) ($result['updated'] ?? 0), 'error' => null],
            'messages' => $this->engine($crud)['messages'],
        ], $failed ? 422 : 200);
    }

    /** Closing the wizard: the stored file goes now, not when it expires. */
    public function discard(Request $request, string $screen, string $token): JsonResponse
    {
        $this->runtime($request, $screen);
        $disk = $this->disk();

        if ($this->meta($disk, $token, $this->owner($request), $screen) !== null) {
            $this->forget($disk, $token);
        }

        return response()->json(['ok' => true]);
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /**
     * The screen, its locks and permissions (resolver), and the import switch
     * the Livewire toolbar and every HasCrudImport action read: the screen
     * enables it, the user may create, `permissions.import` allows it.
     */
    private function runtime(Request $request, string $screen): CrudRuntime
    {
        $crud = $this->resolver->resolve($request, $screen);
        abort_unless($crud->importEnabled(), 403, trans('ptah::ui.crud_permission_denied'));
        $crud->openImport();

        return $crud;
    }

    /**
     * Puts the stored file back where the engine reads it and runs the
     * engine's own acceptance again — a limit changed in the meantime still
     * applies — then the client's mapping, which the engine checks on use.
     */
    private function establish(CrudRuntime $crud, Request $request, string $screen): ?JsonResponse
    {
        $token = $request->input('token');
        $disk = $this->disk();

        // Unknown, expired, another user's or another screen's: the same answer,
        // so a token says nothing about anybody else's upload.
        if (! is_string($token) || ($meta = $this->meta($disk, $token, $this->owner($request), $screen)) === null) {
            abort(404);
        }

        $mapping = $request->input('mapping', []);
        if (! is_array($mapping)) {
            return response()->json(['ok' => false, 'errors' => ['mapping' => trans('validation.array', ['attribute' => 'mapping'])]], 422);
        }

        $crud->importFile = self::storedFile($disk->path(self::DIR.'/'.$token.'.upload'), $meta['extension']);
        if (($refused = $this->accept($crud)) !== null) {
            return $refused;
        }

        $crud->importMapping = $this->mappingIn($mapping);

        return null;
    }

    /** The engine's updatedImportFile(): its validation and its limits, as a 422 on `file`. */
    private function accept(CrudRuntime $crud): ?JsonResponse
    {
        try {
            $crud->updatedImportFile();
        } catch (ValidationException $e) {
            return $this->refuse((string) $e->validator->errors()->first('importFile'));
        }

        if (! $crud->importAccepted) {
            return $this->refuse((string) $crud->getErrorBag()->first('importFile'));
        }

        return null;
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'errors' => ['file' => $message !== '' ? $message : trans('ptah::ui.import_empty')]], 422);
    }

    /**
     * What only the engine knows, read in its own scope: CrudRuntime is final
     * and these are protected/private members (the form fields a spreadsheet
     * may fill, the row limit, the toasts it raised). Read here,
     * not reimplemented — a rule changed there changes here.
     *
     * @return array{fields: list<array{field: string, label: string, required: bool}>, max_rows: int, messages: list<array<string, mixed>>}
     */
    private function engine(CrudRuntime $crud): array
    {
        return \Closure::bind(function (): array {
            /** @var CrudRuntime $this */
            return [
                'fields' => array_map(fn (array $c) => [
                    'field' => (string) $c['colsNomeFisico'],
                    'label' => (string) ($c['colsNomeLogico'] ?? $c['colsNomeFisico']),
                    'required' => $this->ptahBool($c['colsRequired'] ?? false),
                ], $this->importFormCols()),
                'max_rows' => $this->importMaxRows(),
                // The toasts the Livewire screen would show — what the runtime
                // captured from dispatch('ptah-toast').
                'messages' => array_values(array_map(fn (array $event) => [
                    'text' => (string) ($event['params']['title'] ?? ''),
                    'level' => (string) ($event['params']['color'] ?? 'info'),
                ], array_filter($this->captured, fn (array $event) => $event['name'] === 'ptah-toast'))),
            ];
        }, $crud, CrudRuntime::class)();
    }

    /**
     * The user the upload belongs to — the class too: a host with two guards
     * has a user 1 in each.
     */
    private function owner(Request $request): string
    {
        $user = $request->user();
        abort_unless($user instanceof Authenticatable, 403);

        return hash('sha256', $user::class.'|'.$user->getAuthIdentifier());
    }

    /**
     * The stored upload's facts, or null when the token is not this user's
     * upload for this screen (or has expired — then it is removed).
     *
     * @return array{owner: string, screen: string, extension: string, expires: int}|null
     */
    private function meta(Filesystem $disk, string $token, string $owner, string $screen): ?array
    {
        // Str::random() only: anything else could walk out of the directory.
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1 || ! $disk->exists(self::DIR.'/'.$token.'.json')) {
            return null;
        }

        $meta = json_decode((string) $disk->get(self::DIR.'/'.$token.'.json'), true);
        if (! is_array($meta) || ! isset($meta['owner'], $meta['screen'], $meta['extension'], $meta['expires'])) {
            return null;
        }

        if ((int) $meta['expires'] < now()->getTimestamp()) {
            $this->forget($disk, $token);

            return null;
        }

        if (! hash_equals((string) $meta['owner'], $owner) || $meta['screen'] !== $screen) {
            return null;
        }

        return ['owner' => (string) $meta['owner'], 'screen' => (string) $meta['screen'], 'extension' => (string) $meta['extension'], 'expires' => (int) $meta['expires']];
    }

    /**
     * Before a new upload: drops the expired ones and this user's previous
     * file for this screen — the Livewire modal holds one file, so does this.
     */
    private function sweep(Filesystem $disk, string $owner, string $screen): void
    {
        foreach ($disk->files(self::DIR) as $path) {
            if (! str_ends_with($path, '.json')) {
                continue;
            }
            $token = basename($path, '.json');
            $meta = json_decode((string) $disk->get($path), true);

            $expired = ! is_array($meta) || (int) ($meta['expires'] ?? 0) < now()->getTimestamp();
            $mine = is_array($meta) && ($meta['owner'] ?? null) === $owner && ($meta['screen'] ?? null) === $screen;

            if ($expired || $mine) {
                $this->forget($disk, $token);
            }
        }
    }

    private function forget(Filesystem $disk, string $token): void
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1) {
            $disk->delete([self::DIR.'/'.$token.'.upload', self::DIR.'/'.$token.'.json']);
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * The stored file as the engine reads an upload: a real file (the
     * validator's `file`, `max` and `mimes` rules) that also answers the
     * client extension readImportFile() asks for.
     */
    private static function storedFile(string $path, string $extension): File
    {
        return new class($path, $extension) extends File
        {
            public function __construct(string $path, private readonly string $clientExtension)
            {
                parent::__construct($path);
            }

            public function getClientOriginalExtension(): string
            {
                return $this->clientExtension;
            }
        };
    }

    /**
     * The client's mapping, header index => field. Only strings survive; the
     * engine keeps only form fields, each once, and only existing headers.
     *
     * @param  array<array-key, mixed>  $mapping
     * @return array<int, string>
     */
    private function mappingIn(array $mapping): array
    {
        $out = [];
        foreach ($mapping as $index => $field) {
            if (is_numeric($index) && is_string($field) && $field !== '') {
                $out[(int) $index] = $field;
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $mapping
     * @return array<string, string>
     */
    private function mappingOut(array $mapping): array
    {
        $out = [];
        foreach ($mapping as $index => $field) {
            if (is_string($field)) {
                $out[(string) $index] = $field;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array{total: int, valid: int, errors: list<array{line: int, field: string, message: string}>, more_errors: int, unmapped_required: list<string>}
     */
    private function previewOut(array $preview): array
    {
        $errors = [];
        foreach ((array) ($preview['errors'] ?? []) as $error) {
            if (is_array($error)) {
                $errors[] = ['line' => (int) ($error['line'] ?? 0), 'field' => (string) ($error['field'] ?? ''), 'message' => (string) ($error['message'] ?? '')];
            }
        }

        return [
            'total' => (int) ($preview['total'] ?? 0),
            'valid' => (int) ($preview['valid'] ?? 0),
            'errors' => $errors,
            'more_errors' => (int) ($preview['more_errors'] ?? 0),
            'unmapped_required' => array_values(array_map('strval', (array) ($preview['unmapped_required'] ?? []))),
        ];
    }
}
