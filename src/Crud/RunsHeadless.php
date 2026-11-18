<?php

declare(strict_types=1);

namespace Ptah\Crud;

use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportValidation\BaseValidate;
use ReflectionAttribute;
use ReflectionClass;

use function Livewire\store;

/**
 * What a Livewire component needs to run as a plain object (1.59.0) — for the
 * headless API, where a screen's logic is reused without a Livewire request.
 *
 * Livewire wires three things at mount that a `new Component` does not have:
 * the `#[Rule]`/`#[Validate]` attributes (so `$this->validate()` knows its
 * rules), the redirect target and the dispatched events. This gives them
 * back, and nothing else: the component's own methods run unchanged — the
 * same throttles, the same guards.
 */
trait RunsHeadless
{
    /** @var list<array{name: string, params: array<int|string, mixed>}> */
    private array $headlessEvents = [];

    /** A component of this class, booted the way Livewire boots it. */
    public static function headless(mixed ...$mount): static
    {
        // Through the container: a component may ask for dependencies.
        $component = app()->make(static::class);

        if (method_exists($component, 'boot')) {
            app()->call([$component, 'boot']);
        }
        if (method_exists($component, 'mount')) {
            app()->call([$component, 'mount'], $mount);
        }

        return $component;
    }

    /** Calls a public action with its dependencies injected, like Livewire does. */
    public function run(string $method, array $arguments = []): static
    {
        app()->call([$this, $method], $arguments);

        return $this;
    }

    /**
     * The rules declared with `#[Rule]` / `#[Validate]` on the public
     * properties. A failure throws ValidationException — a 422 with the
     * messages, as any Laravel JSON request.
     */
    public function validate($rules = null, $messages = [], $attributes = [])
    {
        $rules ??= $this->headlessRules();

        // Every public property, as Livewire validates: `confirmed` reads
        // `password_confirmation`, which is not a key of the rules.
        return Validator::make($this->all(), $rules, (array) $messages, (array) $attributes)->validate();
    }

    /** @return array<string, mixed> */
    private function headlessRules(): array
    {
        $rules = method_exists($this, 'rules') ? (array) $this->rules() : [];

        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            foreach ($property->getAttributes(BaseValidate::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                /** @var BaseValidate $rule */
                $rule = $attribute->newInstance();

                if ($rule->rule !== null) {
                    // 'required|email' on the property, or ['items.*' => 'string'].
                    $rules = is_array($rule->rule) && ! array_is_list($rule->rule)
                        ? array_merge($rules, $rule->rule)
                        : array_merge($rules, [$property->getName() => $rule->rule]);
                }
            }
        }

        return $rules;
    }

    public function dispatch($event, ...$params)
    {
        $this->headlessEvents[] = ['name' => (string) $event, 'params' => $params];

        return parent::dispatch($event, ...$params);
    }

    public function dispatched(string $event): bool
    {
        return in_array($event, array_column($this->headlessEvents, 'name'), true);
    }

    /**
     * The toasts the screen would have shown.
     *
     * @return list<array{text: string, level: string}>
     */
    public function toasts(): array
    {
        $out = [];

        foreach ($this->headlessEvents as $event) {
            if ($event['name'] === 'ptah-toast') {
                $out[] = ['text' => (string) ($event['params']['title'] ?? ''), 'level' => (string) ($event['params']['color'] ?? 'info')];
            }
        }

        return $out;
    }

    /** Where the action asked to go (`$this->redirect(...)`), if it did. */
    public function redirectTarget(): ?string
    {
        $target = store($this)->get('redirect');

        return is_string($target) && $target !== '' ? $target : null;
    }
}
