<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for class-based lifecycle hooks used by BaseCrud.
 *
 * Implement this interface in your hook classes to get IDE autocompletion,
 * static analysis support (PHPStan/Psalm), and guaranteed method signatures.
 *
 * Usage in CrudConfig modal:
 *   @ProductHooks
 *   @ProductHooks::beforeCreate
 *   @App\CrudHooks\ProductHooks
 *
 * Generate a hook class automatically:
 *   php artisan ptah:hooks ProductHooks
 */
interface CrudHooksInterface
{
    /**
     * Runs before a new record is inserted.
     *
     * @param array<string,mixed>  &$data      Form data (modifiable by reference)
     * @param Model|null            $record     Always null before creation
     * @param object                $component  The Livewire BaseCrud component instance
     */
    public function beforeCreate(array &$data, ?Model $record, object $component): void;

    /**
     * Runs after a new record is successfully inserted.
     *
     * @param array<string,mixed>  &$data      Form data (read-only in practice)
     * @param Model                 $record     The freshly created Eloquent record
     * @param object                $component  The Livewire BaseCrud component instance
     */
    public function afterCreate(array &$data, Model $record, object $component): void;

    /**
     * Runs before an existing record is updated.
     *
     * @param array<string,mixed>  &$data      Form data (modifiable by reference)
     * @param Model                 $record     The record BEFORE the update
     * @param object                $component  The Livewire BaseCrud component instance
     */
    public function beforeUpdate(array &$data, Model $record, object $component): void;

    /**
     * Runs after an existing record is successfully updated.
     *
     * @param array<string,mixed>  &$data      Form data (read-only in practice)
     * @param Model                 $record     The record AFTER the update
     * @param object                $component  The Livewire BaseCrud component instance
     */
    public function afterUpdate(array &$data, Model $record, object $component): void;
}
