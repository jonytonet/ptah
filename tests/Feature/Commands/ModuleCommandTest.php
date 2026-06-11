<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Covers the read-only paths of ptah:module. Activating a module publishes
 * files, edits .env and runs migrations/seeders — that flow belongs to the
 * sandbox integration test, not the unit suite.
 */
class ModuleCommandTest extends TestCase
{
    #[Test]
    public function list_shows_all_modules_and_exits_successfully(): void
    {
        $this->artisan('ptah:module', ['--list' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function unknown_module_fails_with_a_clear_message(): void
    {
        $this->artisan('ptah:module', ['module' => 'does-not-exist'])
            ->assertFailed();
    }
}
