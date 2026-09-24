<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Is this string a real Eloquent relation path on this model?
 *
 * `BaseCrud` pre-filters a listing with `whereHas($this->whereHasFilter, …)`,
 * and Laravel resolves the "relation" by calling it:
 *
 *     // QueriesRelationships::getRelationWithoutConstraints()
 *     return $this->getModel()->{$relation}();
 *
 * with no validation of the name whatsoever. So any string reaches the model as
 * a method call — and a name that is not a real method falls through
 * `Model::__call` to the query builder. `whereHasFilter = 'truncate'` ran
 * `TRUNCATE` on the CRUD's table. The property was public and not `#[Locked]`,
 * so one forged Livewire request from any user with read access wiped it.
 *
 * `#[Locked]` closes the client vector. This closes the other one: a host that
 * passes request input into `mount()` — `['whereHasFilter' => request('rel')]` —
 * is one step from the same outcome, and a relation name is a value the package
 * can check instead of trusting.
 *
 * ── What counts as a relation ────────────────────────────────────────────
 *
 * A method that REALLY exists on the model (a name only reachable through
 * `__call` — `truncate`, `delete` on the builder — is rejected outright), that
 * is not part of Eloquent's own surface (`delete`, `save`, `forceDelete`,
 * `restore`… would all pass a bare `method_exists`), takes no required
 * argument, and returns a `Relation`. A method with no declared return type is
 * accepted only if calling it does return one — relations written before
 * return types were common are legitimate and must keep working.
 *
 * Dotted paths (`order.customer`) are walked segment by segment against each
 * related model, because Laravel resolves every segment the same unchecked way.
 */
final class RelationPath
{
    /**
     * @throws InvalidArgumentException when any segment is not a relation
     */
    public static function assertValid(Model $model, string $path): void
    {
        if ($path === '') {
            return;
        }

        $current = $model;

        foreach (explode('.', $path) as $segment) {
            $relation = self::resolveSegment($current, $segment, $path);
            $current = $relation->getRelated();
        }
    }

    public static function isValid(Model $model, string $path): bool
    {
        try {
            self::assertValid($model, $path);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function resolveSegment(Model $model, string $segment, string $path): Relation
    {
        $reject = static fn (string $why): InvalidArgumentException => new InvalidArgumentException(
            "[Ptah] '{$path}' nao e uma relacao valida de ".$model::class.": o segmento '{$segment}' {$why}."
        );

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
            throw $reject('nao e um nome de metodo');
        }

        // Um nome que so existe via __call — `truncate`, e tudo o mais que o
        // model repassa ao query builder — nao e metodo do model.
        if (! method_exists($model, $segment)) {
            throw $reject('nao e um metodo declarado no model');
        }

        // A superficie do proprio Eloquent: `delete`, `save`, `push`, `touch`,
        // `replicate`… um `method_exists` puro aprovaria todos.
        if (method_exists(Model::class, $segment)) {
            throw $reject('pertence ao Eloquent, nao e uma relacao');
        }

        // Metodos que chegam por trait do framework — `forceDelete` e
        // `restore` do SoftDeletes, por exemplo. A classe declarante de um
        // metodo de trait e a classe que o usa, entao isto tem de ser checado
        // pelo trait, nao pela reflexao do metodo.
        foreach (class_uses_recursive($model) as $trait) {
            if (str_starts_with($trait, 'Illuminate\\') && method_exists($trait, $segment)) {
                throw $reject('vem de um trait do framework');
            }
        }

        $method = new ReflectionMethod($model, $segment);

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
            throw $reject('nao tem a forma de uma relacao (publico, de instancia, sem argumento obrigatorio)');
        }

        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin() || ! is_a($type->getName(), Relation::class, true)) {
                throw $reject('declara um retorno que nao e Relation');
            }
        }

        $relation = $model->{$segment}();

        if (! $relation instanceof Relation) {
            throw $reject('nao devolve uma Relation');
        }

        return $relation;
    }
}
