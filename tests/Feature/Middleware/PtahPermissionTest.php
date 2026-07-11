<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Middleware;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Http\Middleware\PtahPermission;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Validates the ptah.can middleware: it delegates to PermissionService::check()
 * and translates the result into pass-through or a 403 (HTML or JSON).
 */
class PtahPermissionTest extends TestCase
{
    /** Builds the middleware with a PermissionService stub that records the check() args. */
    private function middleware(bool $allow, array &$captured): PtahPermission
    {
        $stub = new class($allow, $captured) extends PermissionService
        {
            /** @param array<string,mixed> $captured */
            public function __construct(private bool $allow, private array &$captured) {}

            public function check(mixed $user, string $objectKey, string $action, ?int $companyId = null): bool
            {
                $this->captured = compact('objectKey', 'action', 'companyId');

                return $this->allow;
            }
        };

        return new PtahPermission($stub);
    }

    #[Test]
    public function granted_passes_through(): void
    {
        $captured = [];
        $mw = $this->middleware(true, $captured);

        $response = $mw->handle(Request::create('/x'), fn () => 'ok', 'products.index', 'read');

        $this->assertSame('ok', $response);
        $this->assertSame(['objectKey' => 'products.index', 'action' => 'read', 'companyId' => null], $captured);
    }

    #[Test]
    public function denied_aborts_with_403(): void
    {
        $captured = [];
        $mw = $this->middleware(false, $captured);

        try {
            $mw->handle(Request::create('/x'), fn () => 'ok', 'products.index', 'delete');
            $this->fail('Expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    #[Test]
    public function denied_returns_json_403_for_json_requests(): void
    {
        $captured = [];
        $mw = $this->middleware(false, $captured);

        $request = Request::create('/x');
        $request->headers->set('Accept', 'application/json');

        $response = $mw->handle($request, fn () => 'ok', 'products.index', 'delete');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('permission_denied', $response->getData(true)['error']);
    }

    #[Test]
    public function company_argument_is_cast_and_forwarded(): void
    {
        $captured = [];
        $mw = $this->middleware(true, $captured);

        $mw->handle(Request::create('/x'), fn () => 'ok', 'reports', 'read', '5');

        $this->assertSame('reports', $captured['objectKey']);
        $this->assertSame(5, $captured['companyId']); // string '5' → int 5
    }
}
