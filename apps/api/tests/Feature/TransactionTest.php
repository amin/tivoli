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

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStamptypes();
    }

    private function seedStamptypes(): void
    {
        $animals = ['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'];
        foreach ($animals as $a) {
            Stamptype::forceCreate(['animal' => $a, 'metal' => null]);
            foreach (['silver', 'gold', 'platinum'] as $m) {
                Stamptype::forceCreate(['animal' => $a, 'metal' => $m]);
            }
        }
    }

    private function makeGroup(string $name = 'Test Group'): Group
    {
        return Group::forceCreate(['name' => $name]);
    }

    private function makeUser(?int $groupId = null, float $balance = 100.0, string $name = 'Player'): User
    {
        return User::forceCreate([
            'name' => $name,
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId, string $name = 'Fortune Wheel'): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => $name,
            'description' => 'Test amusement',
            'price' => 5.00,
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    private function issueToken(User $user): IdentityToken
    {
        return IdentityToken::issueFor($user);
    }

    public function test_happy_path_creates_transaction_and_consumes_token(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);

        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => $amusement->uuid,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'stamp']);

        $token->refresh();
        $this->assertNotNull($token->consumed_at);

        $player->refresh();
        $this->assertEquals(95.00, $player->balance);

        $amusement->refresh();
        $this->assertEquals(5.00, $amusement->amusement_balance);
    }

    public function test_uuid_mismatch_rejects_with_403_and_does_not_consume_token(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id, 'Authed Amusement');
        $otherAmusement = $this->makeAmusement($group->id, 'Other Amusement');
        $token = $this->issueToken($player);

        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => $otherAmusement->uuid,
            ]);

        $response->assertStatus(403);

        $token->refresh();
        $this->assertNull($token->consumed_at, 'Token must not be consumed on mismatch');

        $player->refresh();
        $this->assertEquals(100.00, $player->balance, 'Balance must be unchanged');
    }

    public function test_missing_amusement_uuid_returns_422(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);

        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amusement_uuid']);
    }

    public function test_invalid_uuid_format_returns_422(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);

        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => 'not-a-uuid',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amusement_uuid']);
    }

    public function test_already_consumed_token_returns_401(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);
        $token->update(['consumed_at' => now()]);

        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => $amusement->uuid,
            ]);

        $response->assertStatus(401);
    }

    public function test_payout_succeeds_into_debt(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        // Seed a fee transaction to satisfy the payout ownership check.
        $token = $this->issueToken($player);
        $feeRes = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => $amusement->uuid,
            ]);
        $feeRes->assertStatus(201);
        $feeId = $feeRes->json('id');

        // Amusement now has €5. Pay out €20 → balance must go to -€15.
        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson("/transactions/{$feeId}/payout", [
                'amount' => 20.00,
            ]);

        $response->assertStatus(201);

        $amusement->refresh();
        $this->assertEquals(-15.00, $amusement->amusement_balance);

        $player->refresh();
        // Started 100, paid 5, won 20 → 115
        $this->assertEquals(115.00, $player->balance);
    }

    public function test_expired_token_returns_401(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);
        $token->update(['expires_at' => now()->subMinute()]);

        $response = $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => $amusement->uuid,
            ]);

        $response->assertStatus(401);
    }

    public function test_group_member_can_list_amusement_transactions(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeUser($group->id, 100.0, 'Member');
        $player = $this->makeUser($group->id, 100.0, 'Player');
        $amusement = $this->makeAmusement($group->id);

        $token = $this->issueToken($player);
        $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'amusement_uuid' => $amusement->uuid,
            ])->assertStatus(201);

        $memberKey = (string) Str::uuid();
        $member->update(['access_key' => Hash::make($memberKey)]);

        $response = $this->withHeaders(['X-Access-Key' => $memberKey])
            ->getJson("/amusements/{$amusement->id}/transactions");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('fee', $response->json('data.0.type'));
    }

    public function test_non_group_member_cannot_list_amusement_transactions(): void
    {
        $ownerGroup = $this->makeGroup('Owners');
        $otherGroup = $this->makeGroup('Outsiders');
        $amusement = $this->makeAmusement($ownerGroup->id);

        $outsiderKey = (string) Str::uuid();
        $outsider = $this->makeUser($otherGroup->id, 100.0, 'Outsider');
        $outsider->update(['access_key' => Hash::make($outsiderKey)]);

        $response = $this->withHeaders(['X-Access-Key' => $outsiderKey])
            ->getJson("/amusements/{$amusement->id}/transactions");

        $response->assertStatus(403);
    }

    public function test_stats_returns_correct_totals(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeUser($group->id, 100.0, 'Member');
        $player = $this->makeUser($group->id, 100.0, 'Player');
        $amusement = $this->makeAmusement($group->id);

        // Two fees of €5 each, then a payout of €3.
        foreach ([5.0, 5.0] as $amount) {
            $token = $this->issueToken($player);
            $this->withHeaders(['X-Api-Key' => $amusement->api_key])
                ->postJson('/transactions', [
                    'identity_token' => $token->token,
                    'amount' => $amount,
                    'amusement_uuid' => $amusement->uuid,
                ])->assertStatus(201);
        }
        $firstFeeId = $amusement->transactions()->where('type', 'fee')->orderBy('id')->first()->id;
        $this->withHeaders(['X-Api-Key' => $amusement->api_key])
            ->postJson("/transactions/{$firstFeeId}/payout", ['amount' => 3.00])
            ->assertStatus(201);

        $memberKey = (string) Str::uuid();
        $member->update(['access_key' => Hash::make($memberKey)]);

        $response = $this->withHeaders(['X-Access-Key' => $memberKey])
            ->getJson("/amusements/{$amusement->id}/stats");

        $response->assertStatus(200);
        $response->assertJson([
            'fees_total' => 10.0,
            'fees_count' => 2,
            'payouts_total' => 3.0,
            'payouts_count' => 1,
            'net' => 7.0,
            'amusement_balance' => 7.0,
        ]);
    }
}
