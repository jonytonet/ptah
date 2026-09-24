<?php

declare(strict_types=1);

namespace Ptah\Tests\Support;

use Ptah\Services\Permission\PermissionService;

/**
 * Deterministic answers from `ptah_is_master()` and `ptah_can()` in a test.
 *
 * Binds a PermissionService stub in the container, which is where both helpers
 * resolve it from. It existed privately in CrudConfigAuthorizationTest; it
 * moved here when the menu and company screens gained a master gate in 1.34.8
 * and three more test files needed exactly the same thing — and a stub copied
 * into each would drift the moment one of them grew a method.
 *
 * A screen's BEHAVIOUR test should act as a user who is allowed in, and its
 * AUTHORIZATION test should state who is and who is not. Before the gate, the
 * behaviour tests of those screens ran as an ordinary user — which was the
 * vulnerability, recorded as a passing test.
 */
trait ActsAsPtahUser
{
    protected function actAsMaster(bool $master = true): void
    {
        $this->bindPermissionStub(master: $master, can: $master);
    }

    protected function actAsUserWhoCan(bool $can): void
    {
        $this->bindPermissionStub(master: false, can: $can);
    }

    private function bindPermissionStub(bool $master, bool $can): void
    {
        $stub = new class($master, $can) extends PermissionService
        {
            public function __construct(private bool $master, private bool $can) {}

            public function isMaster(mixed $user = null): bool
            {
                return $this->master;
            }

            public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
            {
                return $this->can;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }
}
