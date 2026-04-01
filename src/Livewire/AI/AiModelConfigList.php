<?php

declare(strict_types=1);

namespace Ptah\Livewire\AI;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiProviderConfigService;

#[Layout('ptah::layouts.forge-dashboard')]
class AiModelConfigList extends Component
{
    use WithPagination;

    protected AiProviderConfigService $configService;

    public function boot(AiProviderConfigService $configService): void
    {
        $this->configService = $configService;
    }

    public function mount(): void
    {
        abort_unless(
            ptah_can('ai.config', 'manage') || ptah_is_master(),
            403,
            trans('ptah::ui.permission_denied')
        );
    }

    // ── List ───────────────────────────────────────────────────────────
    public string $search    = '';
    public string $sort      = 'name';
    public string $direction = 'asc';

    // ── Modal ──────────────────────────────────────────────────────────
    public bool  $showModal = false;
    public bool  $isEditing = false;
    public ?int  $editingId = null;

    // ── Form fields ────────────────────────────────────────────────────
    public string  $name          = '';
    public string  $provider      = 'openai';
    public string  $model         = 'gpt-4o-mini';
    public string  $api_key       = '';
    public string  $api_endpoint  = '';
    public int     $max_tokens    = 1024;
    public string  $temperature   = '0.70';
    public string  $system_prompt = '';
    public string  $notes         = '';
    public bool    $is_active     = true;
    public bool    $is_default    = false;

    // ── Delete ─────────────────────────────────────────────────────────
    public ?int  $deleteId        = null;
    public bool  $showDeleteModal = false;

    // ── Feedback ───────────────────────────────────────────────────────
    public string $successMsg = '';
    public string $errorMsg   = '';

    /** Supported providers (value => label) */
    public const PROVIDERS = [
        'openai'    => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini'    => 'Google Gemini',
        'ollama'    => 'Ollama (Local)',
        'groq'      => 'Groq',
        'mistral'   => 'Mistral',
    ];

    // ── Rules ──────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'provider'      => 'required|in:' . implode(',', array_keys(self::PROVIDERS)),
            'model'         => 'required|string|max:100',
            'api_key'       => ($this->isEditing || $this->provider === 'ollama') ? 'nullable|string' : 'required|string',
            'api_endpoint'  => 'nullable|url|max:500',
            'max_tokens'    => 'required|integer|min:1|max:128000',
            'temperature'   => 'required|numeric|min:0|max:2',
            'system_prompt' => 'nullable|string|max:5000',
            'notes'         => 'nullable|string|max:1000',
            'is_active'     => 'boolean',
            'is_default'    => 'boolean',
        ];
    }

    // ── Lifecycle ──────────────────────────────────────────────────────

    public function updatingSearch(): void { $this->resetPage(); }

    public function sort(string $column): void
    {
        $this->direction = ($this->sort === $column && $this->direction === 'asc') ? 'desc' : 'asc';
        $this->sort      = $column;
    }

    // ── CRUD ───────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $config = AiModelConfig::findOrFail($id);

        $this->editingId     = $id;
        $this->name          = $config->name;
        $this->provider      = $config->provider;
        $this->model         = $config->model;
        $this->api_key       = '';   // never pre-fill the encrypted key
        $this->api_endpoint  = $config->api_endpoint ?? '';
        $this->max_tokens    = $config->max_tokens;
        $this->temperature   = number_format((float) $config->temperature, 2);
        $this->system_prompt = $config->system_prompt ?? '';
        $this->notes         = $config->notes ?? '';
        $this->is_active     = $config->is_active;
        $this->is_default    = $config->is_default;

        $this->isEditing = true;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate();

        try {
            $data['temperature'] = (float) $data['temperature'];

            if ($this->isEditing) {
                $config = AiModelConfig::findOrFail($this->editingId);

                // Keep existing API key when the field is left blank on edit
                if (empty($data['api_key'])) {
                    unset($data['api_key']);
                }

                $this->configService->update($config, $data);
                $this->successMsg = __('ptah::ui.ai_config_updated');
            } else {
                $this->configService->create($data);
                $this->successMsg = __('ptah::ui.ai_config_created');
            }

            $this->showModal = false;
            $this->resetForm();
        } catch (\Throwable $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId        = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (!$this->deleteId) {
            return;
        }

        try {
            $config = AiModelConfig::findOrFail($this->deleteId);
            $this->configService->delete($config);
            $this->successMsg     = __('ptah::ui.ai_config_deleted');
            $this->showDeleteModal = false;
            $this->deleteId       = null;
        } catch (\Throwable $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function setDefault(int $id): void
    {
        try {
            $this->configService->setDefault($id);
            $this->successMsg = __('ptah::ui.ai_config_set_default_ok');
        } catch (\Throwable $e) {
            $this->errorMsg = $e->getMessage();
        }
    }

    public function closeModal(): void
    {
        $this->showModal  = false;
        $this->resetForm();
        $this->resetValidation();
    }

    // ── Query ──────────────────────────────────────────────────────────

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return AiModelConfig::query()
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('provider', 'like', "%{$this->search}%")
                   ->orWhere('model', 'like', "%{$this->search}%");
            }))
            ->orderBy($this->sort, $this->direction)
            ->paginate(20);
    }

    // ── Render ─────────────────────────────────────────────────────────

    public function render()
    {
        return view('ptah::livewire.ai.ai-model-config-list', [
            'rows'      => $this->rows,
            'providers' => self::PROVIDERS,
        ])->title(__('ptah::ui.ai_config_title'));
    }

    // ── Private ────────────────────────────────────────────────────────

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'provider', 'model', 'api_key', 'api_endpoint',
            'max_tokens', 'temperature', 'system_prompt', 'notes',
            'is_active', 'is_default', 'errorMsg',
        ]);
        $this->provider    = 'openai';
        $this->model       = 'gpt-4o-mini';
        $this->max_tokens  = 1024;
        $this->temperature = '0.70';
        $this->is_active   = true;
        $this->is_default  = false;
    }
}
