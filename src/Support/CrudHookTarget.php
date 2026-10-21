<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Where a class-based lifecycle hook (`@Class::method`) points, and whether
 * it is allowed to run (1.50.0) — one rule for the runtime and the doctor.
 *
 * The class must live under `ptah.crud.hook_namespaces` (default
 * `App\CrudHooks`). A hook outside it was refused at save time with an
 * exception that is logged and swallowed — the save went on WITHOUT the hook,
 * and the docs showed `@App\Services\...` examples. The doctor now reports it.
 */
final class CrudHookTarget
{
    /**
     * @return array{class: string, method: string, permitted: bool, allowed: list<string>}
     */
    public static function resolve(string $hookCode, string $hookName): array
    {
        $hookCode = str_replace('@', '::', ltrim(trim($hookCode), '@'));

        if (str_contains($hookCode, '::')) {
            [$className, $methodName] = explode('::', $hookCode, 2);
        } else {
            $className = $hookCode;
            $methodName = $hookName; // beforeCreate, afterCreate…
        }

        $allowed = array_values(array_filter(array_map(
            fn ($ns) => trim((string) $ns, '\\'),
            (array) config('ptah.crud.hook_namespaces', ['App\\CrudHooks'])
        )));

        // Not fully qualified → the first allowed namespace.
        if (! str_starts_with($className, '\\') && ! str_contains($className, '\\')) {
            $className = ($allowed[0] ?? 'App\\CrudHooks').'\\'.$className;
        }

        $normalized = ltrim($className, '\\');
        $permitted = false;
        foreach ($allowed as $ns) {
            if (str_starts_with($normalized, $ns.'\\')) {
                $permitted = true;
                break;
            }
        }

        return ['class' => $className, 'method' => $methodName, 'permitted' => $permitted, 'allowed' => $allowed];
    }
}
