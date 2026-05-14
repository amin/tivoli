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

    private function makeAmusement(int $groupId, string $name = 'Fortune Wheel', string $type = 'game'): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => $name,
            'description' => 'Test amusement',
            'price' => 5.00,
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => $type,
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

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
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

    public function test_invalid_api_key_returns_401(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $token = $this->issueToken($player);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => (string) Str::uuid(), // random, not an amusement's
        ]);

        $response->assertStatus(401);

        $token->refresh();
        $this->assertNull($token->consumed_at);
    }

    public function test_missing_api_key_returns_422(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $token = $this->issueToken($player);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_already_consumed_token_returns_401(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);
        $token->update(['consumed_at' => now()]);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(401);
    }

    public function test_expired_token_returns_401(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = $this->issueToken($player);
        $token->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(401);
    }

    public function test_payout_succeeds_into_debt(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);
        $feeRes->assertStatus(201);
        $feeId = $feeRes->json('id');

        $response = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 20.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(201);

        $amusement->refresh();
        $this->assertEquals(-15.00, $amusement->amusement_balance);

        $player->refresh();
        $this->assertEquals(115.00, $player->balance);
    }

    public function test_payout_with_wrong_api_key_returns_403(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id, 'Owner');
        $other = $this->makeAmusement($group->id, 'Stranger');

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);
        $feeId = $feeRes->json('id');

        // Different amusement tries to claim the payout.
        $response = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 10.00,
            'api_key' => $other->api_key,
        ]);

        $response->assertStatus(403);
    }

    public function test_attraction_cannot_payout(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $attraction = $this->makeAmusement($group->id, 'Ferris Wheel', 'attraction');

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 3.00,
            'api_key' => $attraction->api_key,
        ]);
        $feeRes->assertStatus(201);
        $feeId = $feeRes->json('id');

        $response = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 5.00,
            'api_key' => $attraction->api_key,
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['error' => 'Attractions cannot pay out']);
    }

    public function test_double_payout_is_rejected(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);
        $feeId = $feeRes->json('id');

        $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 7.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $second = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 7.00,
            'api_key' => $amusement->api_key,
        ]);

        $second->assertStatus(409);
        $second->assertJsonFragment(['error' => "Transaction #{$feeId} has already been paid out"]);
    }

    public function test_amusement_balance_serializes_as_number(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeUser($group->id, 100.0, 'Member');
        $amusement = $this->makeAmusement($group->id);

        $memberKey = (string) Str::uuid();
        $member->update(['access_key' => Hash::make($memberKey)]);

        $response = $this->withHeaders(['X-Access-Key' => $memberKey])
            ->getJson("/amusements/{$amusement->id}");

        $response->assertStatus(200);
        $this->assertIsNumeric($response->json('amusement_balance'));
        $this->assertIsNumeric($response->json('price'));
        $this->assertIsNotString($response->json('amusement_balance'));
        $this->assertIsNotString($response->json('price'));
    }

    public function test_group_member_can_list_amusement_transactions(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeUser($group->id, 100.0, 'Member');
        $player = $this->makeUser($group->id, 100.0, 'Player');
        $amusement = $this->makeAmusement($group->id);

        $token = $this->issueToken($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
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

        foreach ([5.0, 5.0] as $amount) {
            $token = $this->issueToken($player);
            $this->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => $amount,
                'api_key' => $amusement->api_key,
            ])->assertStatus(201);
        }
        $firstFeeId = $amusement->transactions()->where('type', 'fee')->orderBy('id')->first()->id;
        $this->postJson("/transactions/{$firstFeeId}/payout", [
            'amount' => 3.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

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
