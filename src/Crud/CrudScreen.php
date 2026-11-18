<?php

declare(strict_types=1);

namespace Ptah\Crud;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * A screen served by the headless CRUD API (1.59.0) — the server-side
 * definition of what `@livewire('ptah-base-crud', [...])` receives in
 * `mount()`.
 *
 *   CrudScreen::register('products', \App\Models\Product::class);
 *
 *   CrudScreen::register('my-appointments', \App\Models\Appointment::class)
 *       ->configRoute('my-appointments')
 *       ->lockedFilters(fn () => ['employee_id' => auth()->user()->employee_id]);
 *
 * Everything here is decided on the server, on every request: the client
 * only names the screen. There is no client-held state to lock — the model,
 * the screen config and the scope never travel to the browser and back.
 */
final class CrudScreen
{
    /** @var array<string, mixed>|Closure(Request): array<string, mixed> */
    private array|Closure $lockedFilters = [];

    private ?string $scope = null;

    /** @var array<string, mixed>|Closure(Request): array<string, mixed> */
    private array|Closure $scopeParams = [];

    private string $whereHasFilter = '';

    /** @var array<int, mixed> */
    private array $whereHasCondition = [];

    private ?string $configRoute = null;

    /** @var (Closure(Request): bool)|null */
    private ?Closure $authorize = null;

    public function __construct(
        public readonly string $key,
        public readonly string $model,
    ) {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $key)) {
            throw new InvalidArgumentException("CrudScreen key '{$key}' may only hold letters, digits, dot, dash and underscore.");
        }
    }

    /** Registers the screen and returns it for the fluent options. */
    public static function register(string $key, string $model): self
    {
        return app(CrudScreenRegistry::class)->add(new self($key, $model));
    }

    /**
     * Which screen config (`crud_configs.route`) to load. Defaults to the key
     * — and falls back to the model's global config, as always.
     */
    public function configRoute(string $route): self
    {
        $this->configRoute = trim($route, '/');

        return $this;
    }

    /**
     * A hard row scope, `[column => value]`. A closure is resolved on every
     * request — compute it from the session, never from the request input.
     *
     * @param  array<string, mixed>|Closure(Request): array<string, mixed>  $filters
     */
    public function lockedFilters(array|Closure $filters): self
    {
        $this->lockedFilters = $filters;

        return $this;
    }

    /**
     * A Ptah\Contracts\CrudQueryScope and its params.
     *
     * @param  array<string, mixed>|Closure(Request): array<string, mixed>  $params
     */
    public function scope(string $scope, array|Closure $params = []): self
    {
        $this->scope = $scope;
        $this->scopeParams = $params;

        return $this;
    }

    /** @param  array<int, mixed>  $condition  `[column, operator, value]` */
    public function whereHas(string $relation, array $condition): self
    {
        $this->whereHasFilter = $relation;
        $this->whereHasCondition = $condition;

        return $this;
    }

    /**
     * An extra gate for the whole screen, on top of the screen's permissions.
     *
     * @param  Closure(Request): bool  $callback
     */
    public function authorize(Closure $callback): self
    {
        $this->authorize = $callback;

        return $this;
    }

    public function allows(Request $request): bool
    {
        return $this->authorize === null || (bool) ($this->authorize)($request);
    }

    /**
     * The arguments of BaseCrud::mount() for this request.
     *
     * @return array<string, mixed>
     */
    public function mountArguments(Request $request): array
    {
        $resolve = static fn (array|Closure $value): array => $value instanceof Closure ? (array) $value($request) : $value;

        return [
            'model' => $this->model,
            'whereHasFilter' => $this->whereHasFilter,
            'whereHasCondition' => $this->whereHasCondition,
            'lockedFilters' => $resolve($this->lockedFilters),
            'configRoute' => $this->configRoute ?? $this->key,
            'scope' => $this->scope,
            'scopeParams' => $resolve($this->scopeParams),
        ];
    }
}
