<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Tests\TestCase;

// A hook class living OUTSIDE the default App\CrudHooks namespace — allowed only
// when its namespace is added to ptah.crud.hook_namespaces.
class SpyHook
{
    public static bool $ran = false;

    public function beforeCreate(array &$data, $record, $component): void
    {
        self::$ran = true;
        $data['stamped'] = true;
    }
}

/**
 * Class-based lifecycle hooks (@Class::method in CrudConfig) may only instantiate
 * classes under an allowed namespace (ptah.crud.hook_namespaces) — so a crafted
 * config cannot instantiate an arbitrary class as a gadget.
 */
class CrudHookSecurityTest extends TestCase
{
    /** @param array<string,mixed> $data */
    private function runHook(BaseCrud $crud, string $hookCode, array &$data): void
    {
        $m = new \ReflectionMethod($crud, 'executeClassBasedHook');
        $m->setAccessible(true);

        // Bind $data by reference through invokeArgs.
        $args = [$hookCode, 'beforeCreate'];
        $args[2] = &$data;
        $args[3] = null;
        $m->invokeArgs($crud, $args);
    }

    #[Test]
    public function rejects_a_class_outside_the_allowed_namespaces(): void
    {
        config()->set('ptah.crud.hook_namespaces', ['App\\CrudHooks']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not allowed');

        $data = [];
        // A real, loadable class — but outside the allowlist. Must be refused
        // before instantiation.
        $this->runHook(new BaseCrud, '@Illuminate\\Support\\Str::lower', $data);
    }

    #[Test]
    public function allows_a_class_under_a_configured_namespace(): void
    {
        SpyHook::$ran = false;
        // Whitelist this test's namespace so the guard permits SpyHook.
        config()->set('ptah.crud.hook_namespaces', [__NAMESPACE__]);

        $data = ['a' => 1];
        $this->runHook(new BaseCrud, '@'.SpyHook::class.'::beforeCreate', $data);

        $this->assertTrue(SpyHook::$ran, 'Hook under an allowed namespace must run');
        $this->assertTrue($data['stamped'] ?? false, 'Hook must receive $data by reference');
    }
}
