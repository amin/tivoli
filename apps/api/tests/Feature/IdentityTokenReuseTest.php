<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\IdentityToken;
use App\Models\Stamptype;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityTokenReuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'] as $a) {
            Stamptype::forceCreate(['animal' => $a, 'metal' => null]);
            foreach (['silver', 'gold', 'platinum'] as $m) {
                Stamptype::forceCreate(['animal' => $a, 'metal' => $m]);
            }
        }
    }

    private function makeGroup(string $prefix = 'G'): Group
    {
        return Group::forceCreate(['name' => $prefix . '-' . Str::random(6)]);
    }

    private function makeUser(?int $groupId, float $balance = 100.0): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'W-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    private function postTx(string $token, Amusement $amusement, float $amount = 1.00)
    {
        return $this->postJson('/transactions', [
            'identity_token' => $token,
            'amount' => $amount,
            'api_key' => $amusement->api_key,
        ]);
    }

    public function test_first_use_awards_stamp_and_marks_token_consumed(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);
        $token = IdentityToken::issueFor($player);

        $res = $this->postTx($token->token, $amusement, 1.00);

        $res->assertStatus(201);
        $this->assertNotNull($res->json('stamp'));

        $token->refresh();
        $this->assertNotNull($token->consumed_at);
    }

    public function test_second_use_of_same_token_succeeds_but_awards_no_stamp(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);
        $token = IdentityToken::issueFor($player);

        $first = $this->postTx($token->token, $amusement, 1.00);
        $first->assertStatus(201);
        $this->assertNotNull($first->json('stamp'));

        $second = $this->postTx($token->token, $amusement, 1.00);
        $second->assertStatus(201);
        $this->assertNull($second->json('stamp'));
    }

    public function test_token_used_when_rate_limited_burns_its_chance(): void
    {
        // Player has a fresh stamped tx within the 3-min window (from any
        // amusement/token); a different identity_token issued for the same
        // (user, amusement) inside the window cannot stamp — and that token's
        // chance is gone even though the rate-limit will pass later.
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $tokenA = IdentityToken::issueFor($player);
        $this->postTx($tokenA->token, $amusement, 1.00)->assertStatus(201);

        // New token issued seconds later for same (user, amusement).
        $tokenB = IdentityToken::issueFor($player);
        $blocked = $this->postTx($tokenB->token, $amusement, 1.00);
        $blocked->assertStatus(201);
        $this->assertNull($blocked->json('stamp'));

        $tokenB->refresh();
        $this->assertNotNull($tokenB->consumed_at);

        // Even after the 3-min rate-limit window expires, tokenB cannot mint
        // a stamp (option B: chance was burned on first use).
        \App\Models\Transaction::where('user_id', $player->id)
            ->where('amusement_id', $amusement->id)
            ->update(['created_at' => now()->subMinutes(4)]);

        $later = $this->postTx($tokenB->token, $amusement, 1.00);
        $later->assertStatus(201);
        $this->assertNull($later->json('stamp'));
    }

    public function test_fresh_token_after_rate_limit_window_awards_stamp(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $tokenA = IdentityToken::issueFor($player);
        $first = $this->postTx($tokenA->token, $amusement, 1.00);
        $first->assertStatus(201);
        $this->assertNotNull($first->json('stamp'));

        // Push the prior stamped tx outside the rate-limit window.
        \App\Models\Transaction::where('user_id', $player->id)
            ->where('amusement_id', $amusement->id)
            ->update(['created_at' => now()->subMinutes(4)]);

        $tokenB = IdentityToken::issueFor($player);
        $second = $this->postTx($tokenB->token, $amusement, 1.00);
        $second->assertStatus(201);
        $this->assertNotNull($second->json('stamp'));
    }

    public function test_expired_token_returns_401(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);
        $token = IdentityToken::issueFor($player);
        $token->update(['expires_at' => now()->subMinute()]);

        $this->postTx($token->token, $amusement)->assertStatus(401);
    }

    public function test_token_default_ttl_is_30_minutes(): void
    {
        $player = $this->makeUser(null);
        $before = now();
        $token = IdentityToken::issueFor($player);

        $expected = $before->copy()->addMinutes(IdentityToken::DEFAULT_TTL_MINUTES);
        $this->assertTrue($token->expires_at->greaterThanOrEqualTo($expected->subSecond()));
        $this->assertTrue($token->expires_at->lessThanOrEqualTo($expected->addSeconds(2)));
    }

    public function test_missing_identity_token_returns_422(): void
    {
        $owners = $this->makeGroup('owners');
        $amusement = $this->makeAmusement($owners->id);

        $res = $this->postJson('/transactions', [
            'amount' => 1.00,
            'api_key' => $amusement->api_key,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['identity_token']);
    }
}
