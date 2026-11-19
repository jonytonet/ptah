<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ptah\Crud\CrudRuntime;
use Ptah\Crud\CrudRuntimeResolver;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Http\Headless\HeadlessCrudConfig;
use Ptah\Support\BroadcastListener;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The BaseCrud's visual config editor (the gear of a BaseCrud screen,
 * `ptah-crud-config`) for the React and Vue stacks.
 *
 * The Livewire component CrudConfig is the engine, run as a plain object
 * (HeadlessCrudConfig): its gate (ptah_can_manage_config(), checked by
 * openModal() and save()), the defaults and JOIN rules of addField() /
 * updateField(), the duplicate-table guard and the `table.col:alias` parsing
 * of addJoin(), the trimming of addNotificationRule(), the merge with the
 * stored config, the schema validation and the cache invalidation of save()
 * are all its own code.
 *
 * The editor is addressed by the registered screen key — never by a model
 * class from the client. The screen is resolved through CrudRuntimeResolver
 * (the screen's own gate and read permission come first), and the model and
 * config route the editor works on are the screen's.
 *
 * Stateless: the Livewire editor keeps the config being edited in its
 * properties between clicks. Here the client holds that document and sends
 * it with every request; each request builds the component (which loads the
 * stored config), lays the document over it, runs ONE of the component's
 * actions and answers the resulting document. Every button of the Blade
 * editor is one of those actions — none of their rules is re-implemented.
 */
class CrudConfigApiController extends Controller
{
    /** Document key → the component's list property. */
    private const LISTS = [
        'columns' => 'formEditFields',
        'customFilters' => 'customFilters',
        'conditionStyles' => 'conditionStyles',
        'joins' => 'joins',
        'notificationRules' => 'notificationRules',
    ];

    /**
     * The scalar settings of the editor (General, Permissions and Hooks tabs),
     * by their property names and types. `groupBy` has no field in the Blade
     * editor; it travels so it round-trips.
     */
    private const SETTINGS = [
        'displayName' => 'string',
        'displayNameSingular' => 'string',
        'displayNameGender' => 'string',
        'configLinkLinha' => 'string',
        'tableClass' => 'string',
        'theadClass' => 'string',
        'uiCompactMode' => 'bool',
        'uiStickyHeader' => 'bool',
        'uiCopyCell' => 'bool',
        'groupBy' => 'string',
        'groupBreak' => 'string',
        'groupBreakPeriod' => 'string',
        'detailModel' => 'string',
        'detailForeignKey' => 'string',
        'detailTitle' => 'string',
        'cacheEnabled' => 'bool',
        'cacheTtl' => 'int',
        'exportAsyncThreshold' => 'int',
        'exportMaxRows' => 'int',
        'exportOrientation' => 'string',
        'broadcastEnabled' => 'bool',
        'broadcastChannel' => 'string',
        'broadcastEvent' => 'string',
        'broadcastType' => 'string',
        'broadcastPerCompany' => 'bool',
        'featureImport' => 'bool',
        'featureImportMode' => 'string',
        'featureImportKey' => 'string',
        'featureFormDraft' => 'bool',
        'featureHistory' => 'bool',
        'featureAttachments' => 'bool',
        'kanbanField' => 'string',
        'calendarStart' => 'string',
        'calendarEnd' => 'string',
        'permissionCreate' => 'string',
        'permissionEdit' => 'string',
        'permissionDelete' => 'string',
        'permissionExport' => 'string',
        'permissionRestore' => 'string',
        'permissionIdentifier' => 'string',
        'showCreateButton' => 'bool',
        'showEditButton' => 'bool',
        'showDeleteButton' => 'bool',
        'showTrashButton' => 'bool',
        'hookBeforeCreate' => 'string',
        'hookAfterCreate' => 'string',
        'hookBeforeUpdate' => 'string',
        'hookAfterUpdate' => 'string',
        'hookBeforeDelete' => 'string',
        'hookAfterDelete' => 'string',
    ];

    /**
     * The sub-forms of the editor: the component's form property and its
     * "index being edited" property (none for filters and styles, which the
     * Blade editor only adds and removes).
     */
    private const SECTIONS = [
        'field' => ['formDataField', 'editingFieldIndex'],
        'action' => ['formDataAction', 'editingActionIndex'],
        'join' => ['formDataJoin', 'editingJoinIndex'],
        'notification' => ['formDataNotification', 'editingNotificationIndex'],
        'filter' => ['formDataFilter', null],
        'style' => ['formDataStyle', null],
    ];

    /**
     * The component's actions this API runs — every button of the Blade
     * editor — with their section and argument (`index`, `order` or none).
     * The route accepts only these names.
     */
    public const ACTIONS = [
        'addField' => ['field', null],
        'editField' => ['field', 'index'],
        'updateField' => ['field', null],
        'cancelEditField' => ['field', null],
        'removeField' => ['field', 'index'],
        'moveFieldUp' => ['field', 'index'],
        'moveFieldDown' => ['field', 'index'],
        'reorderFields' => ['field', 'order'],
        'addAction' => ['action', null],
        'editAction' => ['action', 'index'],
        'cancelEditAction' => ['action', null],
        'removeAction' => ['action', 'index'],
        'addJoin' => ['join', null],
        'editJoin' => ['join', 'index'],
        'cancelEditJoin' => ['join', null],
        'removeJoin' => ['join', 'index'],
        'addNotificationRule' => ['notification', null],
        'editNotificationRule' => ['notification', 'index'],
        'cancelEditNotificationRule' => ['notification', null],
        'removeNotificationRule' => ['notification', 'index'],
        'addCustomFilter' => ['filter', null],
        'removeCustomFilter' => ['filter', 'index'],
        'addConditionStyle' => ['style', null],
        'removeConditionStyle' => ['style', 'index'],
    ];

    /**
     * The values of the editor's selects, as the Blade view lists them. The
     * labels are the client's words; the values are decided here.
     */
    private const CHOICES = [
        'fieldTypes' => ['text', 'number', 'date', 'datetime', 'select', 'searchdropdown', 'boolean', 'textarea'],
        'aligns' => ['text-start', 'text-center', 'text-end'],
        'renderers' => ['', 'badge', 'pill', 'boolean', 'money', 'date', 'datetime', 'link', 'image', 'truncate', 'number', 'filesize', 'duration', 'code', 'color', 'progress', 'rating', 'qrcode'],
        'badgeColors' => ['green', 'yellow', 'red', 'blue', 'indigo', 'purple', 'pink', 'gray'],
        'currencies' => ['BRL', 'USD', 'EUR'],
        'progressColors' => ['indigo', 'green', 'blue', 'yellow', 'red', 'purple'],
        'durationUnits' => ['minutes', 'seconds'],
        'masks' => ['', 'money_brl', 'money_usd', 'percent', 'cpf', 'cnpj', 'rg', 'pis', 'ncm', 'ean13', 'phone', 'cep', 'plate', 'credit_card', 'date', 'datetime', 'time', 'integer', 'uppercase'],
        'maskTransforms' => ['', 'money_to_float', 'digits_only', 'plate_clean', 'date_br_to_iso', 'date_iso_to_br', 'uppercase', 'lowercase', 'trim'],
        'validationRules' => ['email', 'url', 'integer', 'numeric', 'cpf', 'cnpj', 'phone', 'alpha', 'alphanum', 'ncm'],
        'validationParams' => ['min', 'max', 'minLength', 'maxLength', 'regex', 'digits', 'digitsBetween', 'after', 'before', 'dateFormat', 'confirmed', 'unique', 'in', 'notIn'],
        'sdModes' => ['model', 'service'],
        'sdStartList' => ['bottom', 'top'],
        'sdMasks' => ['defaultMask', 'cnpj', 'cpf', 'money', 'phone', 'date'],
        'totalizerFunctions' => ['sum', 'avg', 'count', 'min', 'max'],
        'actionTypes' => ['link', 'livewire', 'javascript'],
        'actionColors' => ['primary', 'success', 'danger', 'warning', 'info', 'secondary'],
        'filterTypes' => ['text', 'number', 'date', 'select'],
        'filterOperators' => ['=', 'LIKE', '>', '<', '>=', '<='],
        'aggregates' => ['', 'SUM', 'COUNT', 'AVG', 'MAX', 'MIN'],
        'styleOperators' => ['==', '!=', '>', '<', '>=', '<='],
        'joinTypes' => ['left', 'inner'],
        'genders' => ['m', 'f'],
        'groupBreakPeriods' => ['', 'day', 'month', 'year'],
        'exportOrientations' => ['landscape', 'portrait'],
        'broadcastTypes' => ['private', 'presence', 'public'],
        'importModes' => ['create', 'upsert'],
        'notificationEvents' => ['created', 'updated', 'deleted'],
        'notificationTypes' => ['info', 'success', 'warning', 'danger'],
        'notificationAudiences' => ['user', 'role', 'staff'],
    ];

    public function __construct(private readonly CrudRuntimeResolver $resolver) {}

    /**
     * Whether the gear button is drawn for this user: the editor's own gate,
     * ptah_can_manage_config(), as the Blade button.
     */
    public function can(Request $request, string $screen): JsonResponse
    {
        try {
            $this->resolver->resolve($request, $screen);
        } catch (HttpExceptionInterface) {
            // A screen this user may not even read has no config button.
            return response()->json(['can' => false]);
        }

        return response()->json(['can' => ptah_can_manage_config()]);
    }

    /** The editable document of the screen's config, and what the editor needs to draw it. */
    public function show(Request $request, string $screen): JsonResponse
    {
        [$crud, $editor] = $this->open($request, $screen);

        return response()->json([
            'can' => true,
            'screen' => $screen,
            // Shown in the editor's header, as the Livewire sidebar shows it.
            'model' => $crud->model,
            'route' => $editor->configRoute,
            'document' => $this->document($editor),
            'choices' => self::CHOICES,
            'options' => [
                'permissionsModule' => (bool) config('ptah.modules.permissions'),
                'permissionKeys' => $editor->availablePermissionKeys(),
                'notificationsEnabled' => (bool) config('ptah.notifications.enabled'),
                'broadcast' => $this->broadcastDefaults($crud->model),
            ],
            'hints' => $this->hints($editor),
        ]);
    }

    /**
     * One button of the editor: the document and the sub-form come in, the
     * component's action runs, the new document and sub-form go out.
     */
    public function action(Request $request, string $screen, string $action): JsonResponse
    {
        abort_unless(array_key_exists($action, self::ACTIONS), 404);
        [$section, $argument] = self::ACTIONS[$action];
        [$formProperty, $editingProperty] = self::SECTIONS[$section];

        Validator::make($request->all(), array_filter([
            'form' => 'sometimes|nullable|array',
            'editing' => 'sometimes|nullable|integer|min:-1',
            'index' => $argument === 'index' ? 'required|integer|min:0' : null,
            'order' => $argument === 'order' ? 'required|array|list' : null,
            'order.*' => $argument === 'order' ? 'integer|min:0' : null,
        ]))->validate();

        [, $editor] = $this->open($request, $screen);
        $this->applyDocument($editor, $request);

        $editor->{$formProperty} = (array) $request->input('form', []);
        if ($editingProperty !== null) {
            $editor->{$editingProperty} = (int) $request->input('editing', -1);
        }

        $before = $this->fingerprint($editor, $formProperty, $editingProperty);

        $arguments = match ($argument) {
            'index' => ['index' => (int) $request->input('index')],
            'order' => ['order' => array_map('intval', (array) $request->input('order'))],
            default => [],
        };
        $editor->run($action, $arguments);

        // addJoin() refuses a second JOIN on the same table through a flash
        // message the Livewire tab shows — a 422 on the table field here. The
        // flash is consumed so it does not surface on the next page.
        $joinError = session()->pull('joinError');
        if (is_string($joinError) && $joinError !== '') {
            throw ValidationException::withMessages(['table' => $joinError]);
        }

        return response()->json([
            'document' => $this->document($editor),
            'form' => $this->object($editor->{$formProperty}),
            'editing' => $editingProperty !== null ? $editor->{$editingProperty} : -1,
            // Most actions do nothing on an incomplete form (addField without a
            // physical name…), silently in the Livewire editor; the client can say so.
            'applied' => $before !== $this->fingerprint($editor, $formProperty, $editingProperty),
            'hints' => $this->hints($editor),
        ]);
    }

    /**
     * The inert preview of the create/edit form, from the document being
     * edited (previewForm() + previewFormCols()). Whitelisted per field.
     */
    public function preview(Request $request, string $screen): JsonResponse
    {
        [, $editor] = $this->open($request, $screen);
        $this->applyDocument($editor, $request);

        $editor->run('previewForm');
        abort_unless($editor->showPreview, 403, trans('ptah::ui.permission_denied'));

        $labels = [];
        foreach ($editor->formEditFields as $column) {
            if (is_array($column) && isset($column['colsNomeFisico']) && is_scalar($column['colsNomeFisico'])) {
                $labels[(string) $column['colsNomeFisico']] ??= (string) ($column['colsNomeLogico'] ?? $column['colsNomeFisico']);
            }
        }

        $fields = [];
        foreach ($editor->previewFormCols() as $column) {
            $field = (string) ($column['colsNomeFisico'] ?? '');
            $depends = isset($column['colsSDDependsOn']) && is_scalar($column['colsSDDependsOn']) && $column['colsSDDependsOn'] !== '' ? (string) $column['colsSDDependsOn'] : null;
            $options = [];
            if (is_array($column['colsSelect'] ?? null)) {
                foreach ($column['colsSelect'] as $label => $value) {
                    $options[] = ['label' => (string) (is_string($label) ? $label : $value), 'value' => is_scalar($value) ? (string) $value : ''];
                }
            }

            $fields[] = [
                'field' => $field,
                'label' => (string) ($column['colsNomeLogico'] ?? $field),
                'type' => (string) ($column['colsTipo'] ?? 'text'),
                'required' => in_array($column['colsRequired'] ?? false, [true, 'S', 1, '1'], true),
                'help' => is_scalar($column['colsHelpText'] ?? null) ? (string) $column['colsHelpText'] : null,
                'block' => trim(is_scalar($column['colsFormBlock'] ?? null) ? (string) $column['colsFormBlock'] : ''),
                'computed' => ! empty($column['colsOnChange']),
                'mask' => is_scalar($column['colsMask'] ?? null) ? (string) $column['colsMask'] : '',
                'options' => $options,
                'dependsOn' => $depends,
                'dependsOnLabel' => $depends !== null ? ($labels[$depends] ?? $depends) : null,
                'placeholder' => is_scalar($column['colsSDPlaceholder'] ?? null) && $column['colsSDPlaceholder'] !== '' ? (string) $column['colsSDPlaceholder'] : null,
                'lazy' => ($column['colsSDInitWithData'] ?? true) === false,
            ];
        }

        return response()->json(['title' => $editor->displayName, 'fields' => $fields]);
    }

    /** The estimated recipients of the notification rule being edited (notificationAudienceCount()). */
    public function audienceCount(Request $request, string $screen): JsonResponse
    {
        Validator::make($request->all(), ['form' => 'sometimes|nullable|array'])->validate();

        [, $editor] = $this->open($request, $screen);
        $editor->formDataNotification = (array) $request->input('form', []);

        return response()->json(['count' => $editor->notificationAudienceCount()]);
    }

    /**
     * Saves: the document is laid over the stored config and the component's
     * save() merges, validates (ConfigSchemaValidator), writes and forgets the
     * cache. A schema refusal is a 422 with its message.
     */
    public function update(Request $request, string $screen): JsonResponse
    {
        Validator::make($request->all(), ['document' => 'required|array'])->validate();

        [$crud, $editor] = $this->open($request, $screen);
        $this->applyDocument($editor, $request);

        try {
            $editor->run('save');
        } catch (ConfigValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['config' => [$e->getMessage()]],
                'path' => $e->getJsonPath(),
            ], 422);
        }

        // save() is a silent no-op for whoever may not configure — the gate
        // already answered 403 in open(), so this is the second net.
        abort_unless($editor->dispatched('ptah:crud-config-updated'), 403, trans('ptah::ui.permission_denied'));

        // The Livewire screen flashes this for the page; here it is the answer.
        $message = session()->pull('crud-success');

        // Read back what was stored, as the next opening of the editor would.
        $fresh = HeadlessCrudConfig::headless(model: $crud->model, configRoute: $crud->configRoute);

        return response()->json([
            'ok' => true,
            'message' => is_string($message) ? $message : null,
            // The BaseCrud listens to this to reload its config
            // (reloadCrudConfig); a client re-reads the screen's schema.
            'event' => 'ptah:crud-config-updated',
            'document' => $this->document($fresh),
            'hints' => $this->hints($fresh),
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * The screen (its gate and read permission first) and its config editor,
     * opened. openModal() is the editor's own gate: a no-op that leaves the
     * modal closed for whoever ptah_can_manage_config() refuses.
     *
     * @return array{0: CrudRuntime, 1: HeadlessCrudConfig}
     */
    private function open(Request $request, string $screen): array
    {
        $crud = $this->resolver->resolve($request, $screen);

        // The model and the config route are the screen's, never the client's.
        $editor = HeadlessCrudConfig::headless(model: $crud->model, configRoute: $crud->configRoute);
        $editor->run('openModal');
        abort_unless($editor->showModal, 403, trans('ptah::ui.permission_denied'));

        return [$crud, $editor];
    }

    /**
     * Lays the client's document over the stored config the component loaded.
     * Only the whitelisted properties, with their types; a key left out keeps
     * the stored value. The values themselves are what the Livewire editor
     * accepts from its own fields — save() validates the result.
     */
    private function applyDocument(HeadlessCrudConfig $editor, Request $request): void
    {
        if ($request->input('document') === null) {
            return;
        }

        $rules = ['document' => 'array', 'document.settings' => 'sometimes|array'];
        foreach (array_keys(self::LISTS) as $key) {
            $rules["document.{$key}"] = 'sometimes|array|list';
            $rules["document.{$key}.*"] = 'array';
        }
        foreach (self::SETTINGS as $name => $type) {
            $rules["document.settings.{$name}"] = match ($type) {
                'bool' => 'sometimes|boolean',
                'int' => 'sometimes|integer',
                default => 'sometimes|nullable|string',
            };
        }
        Validator::make($request->all(), $rules)->validate();

        /** @var array<string, mixed> $document */
        $document = (array) $request->input('document');

        foreach (self::LISTS as $key => $property) {
            if (array_key_exists($key, $document)) {
                $editor->{$property} = array_values((array) $document[$key]);
            }
        }

        $settings = (array) ($document['settings'] ?? []);
        foreach (self::SETTINGS as $name => $type) {
            if (! array_key_exists($name, $settings)) {
                continue;
            }
            $value = $settings[$name];
            $editor->{$name} = match ($type) {
                'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'int' => (int) $value,
                default => (string) ($value ?? ''),
            };
        }
    }

    /**
     * The editor's state the client edits — its lists and settings, nothing
     * else of the component (the open/preview flags, the sub-forms, the
     * service are not part of it).
     *
     * @return array<string, mixed>
     */
    private function document(HeadlessCrudConfig $editor): array
    {
        $document = [];
        foreach (self::LISTS as $key => $property) {
            $document[$key] = array_map(fn ($entry) => $this->object($entry), array_values($editor->{$property}));
        }

        $settings = [];
        foreach (array_keys(self::SETTINGS) as $name) {
            $settings[$name] = $editor->{$name};
        }
        $document['settings'] = $settings;

        return $document;
    }

    /**
     * Values the Notifications tab derives from the columns.
     *
     * @return array{placeholders: list<string>, traitMissing: bool}
     */
    private function hints(HeadlessCrudConfig $editor): array
    {
        return [
            'placeholders' => array_values($editor->notificationPlaceholderOptions()),
            'traitMissing' => $editor->notificationTraitMissing(),
        ];
    }

    /**
     * What the General tab shows for an empty channel/event, and the company
     * its listener preview uses (the Blade's `max(1, ptah_company_id())`).
     *
     * @return array{base: string, defaultChannel: string, defaultEvent: string, companyId: int, prefixes: array<string, string>}
     */
    private function broadcastDefaults(string $model): array
    {
        return [
            'base' => class_basename(str_replace('/', '\\', $model)),
            'defaultChannel' => BroadcastListener::channel([], $model),
            'defaultEvent' => BroadcastListener::event([], $model),
            'companyId' => max(1, ptah_company_id()),
            'prefixes' => BroadcastListener::TYPES,
        ];
    }

    /** The lists, the sub-form and its index, to tell whether an action changed anything. */
    private function fingerprint(HeadlessCrudConfig $editor, string $formProperty, ?string $editingProperty): string
    {
        $state = [$editor->{$formProperty}, $editingProperty !== null ? $editor->{$editingProperty} : null];
        foreach (self::LISTS as $property) {
            $state[] = $editor->{$property};
        }

        return (string) json_encode($state);
    }

    /** An associative array as a JSON object even when empty (a form, a column). */
    private function object(mixed $value): mixed
    {
        return is_array($value) && $value === [] ? new stdClass : $value;
    }
}
