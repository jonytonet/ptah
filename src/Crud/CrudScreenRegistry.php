<?php

declare(strict_types=1);

namespace Ptah\Crud;

use InvalidArgumentException;

/**
 * The screens the headless CRUD API serves (1.59.0). A singleton: screens
 * are registered in a service provider or a routes file, once per boot.
 *
 * A screen that is not registered does not exist for the API — the client
 * can never name a model.
 */
final class CrudScreenRegistry
{
    /** @var array<string, CrudScreen> */
    private array $screens = [];

    public function add(CrudScreen $screen): CrudScreen
    {
        // `..` is reserved for the detail screens the server derives from a
        // registered one (`orders..5.0`, see CrudScreen::DETAIL): a registered
        // key may never look like one, so a child key always means a child.
        if (str_contains($screen->key, '..')) {
            throw new InvalidArgumentException("CrudScreen key '{$screen->key}' may not contain '..' — it is reserved for detail screens.");
        }

        return $this->screens[$screen->key] = $screen;
    }

    public function find(string $key): ?CrudScreen
    {
        return $this->screens[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->screens);
    }
}
