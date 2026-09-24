<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_never_creates_or_promotes_an_admin(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
        $user = User::factory()->create(['email' => 'lee@wisener.net']);
        $password = $user->password;
        $this->seed();
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_explicit_admin_setup_preserves_credentials_and_activation(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        $this->artisan('blogshed:make-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertNull($user->fresh()->activated_at);
        $this->artisan('blogshed:make-admin', ['email' => 'missing@example.com'])->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}
