<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Tests\TestCase;

/**
 * Verifies the AI provider-config screen is fail-closed: a user without the
 * 'ai.config' permission (and not MASTER) cannot reach it. Because the same
 * guard runs on every mutating action, blocking mount covers the guard path.
 */
class AiModelConfigListAuthTest extends TestCase
{
    #[Test]
    public function it_forbids_users_without_the_ai_config_permission(): void
    {
        Livewire::test(AiModelConfigList::class)->assertStatus(403);
    }
}
