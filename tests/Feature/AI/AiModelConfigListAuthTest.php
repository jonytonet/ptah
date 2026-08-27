<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Models\AiModelConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;
use Ptah\Services\Permission\PermissionService;
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

    /**
     * Renders the table with two rows exercising both branches of every
     * status/default badge (active+default vs inactive+non-default) — a
     * regression net for the module-screen-consistency pass that swapped
     * several raw gray/slate text utilities on those badges for the
     * ptah-c-ai_ token classes: a typo'd class name wouldn't break the
     * Blade compile, but a broken if/else branch would.
     */
    #[Test]
    public function it_renders_the_table_with_active_and_inactive_rows(): void
    {
        $stub = new class extends PermissionService
        {
            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        };
        $this->app->instance(PermissionService::class, $stub);

        AiModelConfig::create([
            'name' => 'Prod OpenAI', 'provider' => 'openai', 'model' => 'gpt-4o-mini',
            'api_key' => 'sk-secret', 'max_tokens' => 1024, 'temperature' => 0.7,
            'is_active' => true, 'is_default' => true,
        ]);
        AiModelConfig::create([
            'name' => 'Backup Groq', 'provider' => 'groq', 'model' => 'llama-3.1-70b',
            'api_key' => 'sk-secret-2', 'max_tokens' => 1024, 'temperature' => 0.7,
            'is_active' => false, 'is_default' => false,
        ]);

        Livewire::test(AiModelConfigList::class)
            ->assertStatus(200)
            ->assertSee('Prod OpenAI')
            ->assertSee('Backup Groq')
            ->assertSee(__('ptah::ui.ai_config_active'))
            ->assertSee(__('ptah::ui.ai_config_inactive'))
            ->assertSee(__('ptah::ui.ai_config_is_default'))
            ->assertSee(__('ptah::ui.ai_config_set_default'));
    }
}
