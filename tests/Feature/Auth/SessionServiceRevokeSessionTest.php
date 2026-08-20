<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\Auth\SessionService;
use Ptah\Tests\TestCase;

class SessionRevokeTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * SessionService::revokeSession(sessionId) used to delete by id alone — any
 * authenticated user could revoke ANY OTHER user's session by guessing or
 * observing its id (session ids are plain strings, easy to grab from a
 * shared device / logs) — an IDOR. The fix scopes the delete to the
 * requesting user's own sessions (user_id).
 */
class SessionServiceRevokeSessionTest extends TestCase
{
    private function makeUser(string $email): SessionRevokeTestUser
    {
        return SessionRevokeTestUser::create([
            'name' => 'Tester',
            'email' => $email,
            'password' => bcrypt('secret'),
        ]);
    }

    private function seedSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    #[Test]
    public function a_user_cannot_revoke_another_users_session(): void
    {
        $userA = $this->makeUser('a'.uniqid().'@example.com');
        $userB = $this->makeUser('b'.uniqid().'@example.com');

        $this->seedSession('session-a', $userA->id);
        $this->seedSession('session-b', $userB->id);

        (new SessionService)->revokeSession('session-b', $userA);

        $this->assertDatabaseHas('sessions', ['id' => 'session-b']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-a']);
    }

    #[Test]
    public function a_user_can_revoke_their_own_session(): void
    {
        $userA = $this->makeUser('a'.uniqid().'@example.com');
        $this->seedSession('session-a', $userA->id);
        $this->seedSession('session-a-other', $userA->id);

        (new SessionService)->revokeSession('session-a', $userA);

        $this->assertDatabaseMissing('sessions', ['id' => 'session-a']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-a-other']);
    }
}
