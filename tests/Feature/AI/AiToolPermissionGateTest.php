<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Tool;
use Ptah\Contracts\AiToolAuthorizable;
use Ptah\Contracts\AiToolContextAware;
use Ptah\Contracts\AiToolInterface;
use Ptah\Contracts\AiToolSchemaInterface;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Services\Permission\PermissionService;
use Ptah\Support\AI\AiToolContext;
use Ptah\Tests\TestCase;

final class GatedReceivablesTool implements AiToolAuthorizable, AiToolContextAware, AiToolInterface, AiToolSchemaInterface
{
    public static int $built = 0;

    private ?AiToolContext $context = null;

    public function __construct()
    {
        self::$built++;
    }

    public static function permission(): ?array
    {
        return ['financial.receivables'];
    }

    public static function toolSchema(): array
    {
        return ['name' => 'list_receivables', 'description' => 'Who owes money.', 'parameters' => ['type' => 'object', 'properties' => []]];
    }

    public function withContext(AiToolContext $context): void
    {
        $this->context = $context;
    }

    public function name(): string
    {
        return 'list_receivables';
    }

    public function description(): string
    {
        return 'Who owes money.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return ['user' => $this->context?->userId(), 'company' => $this->context?->companyId];
    }
}

final class OpenClockTool implements AiToolAuthorizable, AiToolInterface
{
    public static function permission(): ?array
    {
        return null;
    }

    public function name(): string
    {
        return 'clock';
    }

    public function description(): string
    {
        return 'Time.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return ['ok' => true];
    }
}

/**
 * Achado #14 do PetPlace: o registry entregava toda tool ao modelo para
 * qualquer usuario — um Atendente perguntava "quem esta devendo?" e recebia
 * o financeiro inteiro, somando todas as filiais.
 */
class AiToolPermissionGateTest extends TestCase
{
    /** @var list<int> */
    private array $granted = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.modules.permissions' => true]);
        GatedReceivablesTool::$built = 0;

        $this->mock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('check')->andReturnUsing(
                fn ($user, string $key, string $action) => $key === 'financial.receivables' && $action === 'read'
                    && in_array((int) ($user?->getAuthIdentifier()), $this->granted, true)
            );
        });
    }

    private function registry(): AiToolRegistry
    {
        $registry = new AiToolRegistry;
        $registry->registerClass(GatedReceivablesTool::class);
        $registry->registerClass(OpenClockTool::class);

        return $registry;
    }

    /** @param Tool[] $tools */
    private static function names(array $tools): array
    {
        return array_map(fn (Tool $t) => $t->name(), $tools);
    }

    #[Test]
    public function a_gated_tool_is_not_even_offered_to_a_user_without_the_permission(): void
    {
        $this->granted = [1];
        $registry = $this->registry();

        $this->actingAs(new GenericUser(['id' => 2]));
        $this->assertSame(['clock'], self::names($registry->getPrismTools()));
        $this->assertSame(0, GatedReceivablesTool::$built, 'Decidido antes de construir a tool.');

        // O memo depende do usuario: outro usuario, outra lista.
        $this->actingAs(new GenericUser(['id' => 1]));
        $this->assertSame(['list_receivables', 'clock'], self::names($registry->getPrismTools()));
    }

    #[Test]
    public function the_permission_is_checked_again_when_the_model_calls_the_tool(): void
    {
        $this->granted = [1];
        $this->actingAs(new GenericUser(['id' => 1]));
        $tool = $this->registry()->getPrismTools()[0];

        $this->granted = [];
        $result = json_decode((string) $tool->handle(), true);

        $this->assertSame('forbidden', $result['code'] ?? null);
        $this->assertSame(0, GatedReceivablesTool::$built, 'Negada na chamada: a tool nao roda.');
    }

    #[Test]
    public function the_tool_receives_the_user_and_the_active_company(): void
    {
        $this->granted = [1];
        $this->actingAs(new GenericUser(['id' => 1]));
        session([config('ptah.permissions.company_session_key', 'ptah_company_id') => 7]);

        $result = json_decode((string) $this->registry()->getPrismTools()[0]->handle(), true);

        $this->assertSame(['user' => 1, 'company' => 7], $result);
    }

    #[Test]
    public function a_guest_gets_only_the_open_tools(): void
    {
        $this->assertSame(['clock'], self::names($this->registry()->getPrismTools()));
    }

    #[Test]
    public function ptah_check_reports_host_tools_without_a_gate(): void
    {
        config(['ptah.modules.ai_agent' => true, 'ptah.ai_agent.tools' => [GatedReceivablesTool::class, OpenClockTool::class, UngatedLedgerTool::class]]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('AI tools with no permission gate')
            ->expectsOutputToContain(UngatedLedgerTool::class)
            ->doesntExpectOutputToContain('    '.OpenClockTool::class)
            ->assertExitCode(0);
    }
}

final class UngatedLedgerTool implements AiToolInterface
{
    public function name(): string
    {
        return 'ledger';
    }

    public function description(): string
    {
        return 'Everything.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return [];
    }
}
