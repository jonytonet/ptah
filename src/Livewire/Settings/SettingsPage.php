<?php

declare(strict_types=1);

namespace Ptah\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Ptah\Livewire\Concerns\RequiresStructureAccess;
use Ptah\Models\Setting;
use Ptah\Services\SettingsService;

/**
 * /ptah-settings — the settings declared in config/ptah-settings.php, edited.
 *
 * Same access rule as the menu and company screens (ptah_can_manage_structure:
 * master with the permissions module, PTAH_STRUCTURE_EDITOR without it),
 * re-checked on every request in boot(). With `per_company` on, values are
 * saved for the active company; a master can switch to the global values.
 * Only declared keys are accepted — the form is client state.
 */
#[Layout('ptah::layouts.forge-dashboard')]
class SettingsPage extends Component
{
    use RequiresStructureAccess;

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, string> */
    public array $errorsBag = [];

    #[Locked]
    public bool $editingGlobal = false;

    public function boot(): void
    {
        $this->assertStructureAccess();
    }

    public function mount(SettingsService $settings): void
    {
        $this->editingGlobal = $settings->companyFor(null) === 0;
        $this->load($settings);
    }

    public function editGlobal(SettingsService $settings, bool $global = true): void
    {
        // O global vale para todas as empresas: so master com modulo de permissoes.
        if ($global && config('ptah.modules.permissions') && ! ptah_is_master()) {
            return;
        }

        $this->editingGlobal = $global || $settings->companyFor(null) === 0;
        $this->load($settings);
    }

    public function save(SettingsService $settings): void
    {
        $defs = $settings->definitions();
        $rules = [];
        foreach ($defs as $key => $def) {
            $rules[$key] = array_merge(['nullable'], self::typeRules($def), array_filter(explode('|', (string) ($def['rules'] ?? ''))));
        }

        $input = array_intersect_key($this->values, $defs);
        $validator = Validator::make($input, $rules, [], array_map(fn (array $d) => (string) $d['label'], $defs));

        if ($validator->fails()) {
            $this->errorsBag = array_map(fn (array $m) => $m[0], $validator->errors()->toArray());

            return;
        }

        $company = $this->editingGlobal ? 0 : null;
        foreach ($input as $key => $value) {
            $settings->set($key, $value, $company ?? $settings->companyFor(null), is_numeric(Auth::id()) ? (int) Auth::id() : null);
        }

        $this->errorsBag = [];
        $this->load($settings);
        $this->dispatch('ptah-toast', title: __('ptah::ui.settings_saved'), color: 'success');
    }

    public function resetToGlobal(SettingsService $settings, string $key): void
    {
        if ($this->editingGlobal || ! isset($settings->definitions()[$key])) {
            return;
        }

        $settings->reset($key);
        $this->load($settings);
    }

    public function render(SettingsService $settings)
    {
        $groups = [];
        foreach ($settings->definitions() as $key => $def) {
            $groups[(string) ($def['group'] ?: __('ptah::ui.settings_group_general'))][$key] = $def;
        }

        return view('ptah::livewire.settings.settings-page', [
            'groups' => $groups,
            'installed' => Setting::tableExists(),
            'sources' => $settings->all($this->editingGlobal ? 0 : null),
            'perCompany' => (bool) config('ptah-settings.per_company', true) && $settings->companyFor(null) > 0,
            'canEditGlobal' => ! config('ptah.modules.permissions') || ptah_is_master(),
        ]);
    }

    private function load(SettingsService $settings): void
    {
        $this->values = array_map(fn (array $v) => $v['value'], $settings->all($this->editingGlobal ? 0 : null));
    }

    /**
     * @param  array<string, mixed>  $def
     * @return list<mixed>
     */
    private static function typeRules(array $def): array
    {
        return match ($def['type'] ?? 'text') {
            'integer' => ['integer'],
            'decimal' => ['numeric'],
            'boolean' => ['boolean'],
            'email' => ['email'],
            'url' => ['url'],
            'date' => ['date'],
            'select' => ['in:'.implode(',', array_map('strval', array_keys((array) ($def['options'] ?? []))))],
            'textarea' => ['string', 'max:10000'],
            default => ['string', 'max:1000'],
        };
    }
}
