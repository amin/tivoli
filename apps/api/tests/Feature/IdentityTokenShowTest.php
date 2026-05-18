<?php

namespace Tests\Feature;

use App\Models\IdentityToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityTokenShowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Player'): User
    {
        return User::forceCreate([
            'name' => $name,
            'group_id' => null,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 100.0,
            'is_active' => true,
        ]);
    }

    public function test_show_returns_user_for_valid_token(): void
    {
        $user = $this->makeUser('Alice');
        $token = IdentityToken::issueFor($user);

        $response = $this->getJson("/identity-tokens/{$token->token}");

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $user->id);
        $response->assertJsonPath('user.name', 'Alice');
        $response->assertJsonStructure(['user' => ['id', 'name'], 'expires_at']);
    }

    public function test_show_returns_401_for_unknown_token(): void
    {
        $response = $this->getJson('/identity-tokens/' . Str::uuid());

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Invalid or expired identity token']);
    }

    public function test_show_returns_401_for_expired_token(): void
    {
        $user = $this->makeUser();
        $token = IdentityToken::issueFor($user);
        $token->update(['expires_at' => now()->subMinute()]);

        $response = $this->getJson("/identity-tokens/{$token->token}");

        $response->assertStatus(401);
    }

    public function test_show_returns_401_for_consumed_token(): void
    {
        $user = $this->makeUser();
        $token = IdentityToken::issueFor($user);
        $token->update(['consumed_at' => now()]);

        $response = $this->getJson("/identity-tokens/{$token->token}");

        $response->assertStatus(401);
    }
}
