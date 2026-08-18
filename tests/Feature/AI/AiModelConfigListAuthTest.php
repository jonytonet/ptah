<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

/** Minimal Authenticatable backed by the shared `users` table (see tests/migrations). */
class AiConfigTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

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

    /**
     * `ai.config` is a capability-as-object (see the comment in
     * AiModelConfigList::authorizeAiConfig()): there is no dedicated verb, so a
     * `read` grant on the `ai.config` object is what authorizes managing AI
     * provider credentials. Regression: before this pattern, the call site used
     * a `manage` verb that was never in PermissionService::ACTIONS, so the
     * whitelist silently rejected it and the grant was impossible for anyone
     * but MASTER.
     */
    #[Test]
    public function it_allows_a_non_master_user_with_a_read_grant_on_ai_config(): void
    {
        $page = PtahPage::create(['slug' => 'ai-config', 'name' => 'AI Config', 'is_active' => true]);
        $object = PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'ai.config', 'obj_label' => 'AI Config',
            'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true,
        ]);

        $role = Role::create(['name' => 'AiConfigEditor', 'is_active' => true]);
        RolePermission::create([
            'role_id' => $role->id, 'page_object_id' => $object->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);

        $user = AiConfigTestUser::create([
            'name' => 'Tester', 'email' => 'ai-config-tester'.uniqid().'@example.com', 'password' => bcrypt('secret'),
        ]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'company_id' => null, 'is_active' => true]);
        $this->actingAs($user);

        Livewire::test(AiModelConfigList::class)->assertStatus(200);
    }
}
