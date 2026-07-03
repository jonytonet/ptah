<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Middleware;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Http\Middleware\PtahMaster;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The ptah.master middleware guards the ACL-management screens (roles, pages,
 * users-ACL, audit): only master users may pass; everyone else gets 403.
 */
class PtahMasterTest extends TestCase
{
    private function mockMaster(bool $isMaster): void
    {
        $stub = new class($isMaster) extends PermissionService
        {
            public function __construct(private bool $master) {}

            public function isMaster(mixed $user = null): bool
            {
                return $this->master;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }

    #[Test]
    public function non_master_is_forbidden(): void
    {
        $this->mockMaster(false);

        $ran = false;
        try {
            (new PtahMaster)->handle(Request::create('/ptah-roles'), function () use (&$ran) {
                $ran = true;

                return 'ok';
            });
            $this->fail('Expected a 403 HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertFalse($ran, 'The route must not run for a non-master user');
    }

    #[Test]
    public function master_passes_through(): void
    {
        $this->mockMaster(true);

        $response = (new PtahMaster)->handle(Request::create('/ptah-roles'), fn () => 'ok');

        $this->assertSame('ok', $response);
    }
}
