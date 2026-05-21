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

    private function makeAmusement(
        int $groupId,
        string $name = 'Fortune Wheel',
        string $type = 'game',
        ?float $price = 5.00,
        ?float $playerPayout = null,
    ): Amusement {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => $name,
            'description' => 'Test amusement',
            'price' => $price,
            'player_payout' => $playerPayout,
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
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id);
        $token = $this->issueToken($player);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['transaction_id', 'stamp']);

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

    public function test_previously_consumed_token_is_still_usable_but_grants_no_stamp(): void
    {
        // Tokens are multi-use during their TTL; consumed_at marks that the
        // token has already had its single stamp-issuing attempt.
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id);
        $token = $this->issueToken($player);
        $token->update(['consumed_at' => now()]);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('stamp'));
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
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id);

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);
        $feeRes->assertStatus(201);
        $feeId = $feeRes->json('transaction_id');

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
        $feeId = $feeRes->json('transaction_id');

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
        $feeId = $feeRes->json('transaction_id');

        $response = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 5.00,
            'api_key' => $attraction->api_key,
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'Attractions cannot pay out']);
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
        $feeId = $feeRes->json('transaction_id');

        $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 7.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $second = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 7.00,
            'api_key' => $amusement->api_key,
        ]);

        $second->assertStatus(409);
        $second->assertJsonFragment(['message' => "Transaction #{$feeId} has already been paid out"]);
    }

    public function test_insufficient_balance_returns_402(): void
    {
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id, 1.00);
        $amusement = $this->makeAmusement($ownerGroup->id);
        $token = $this->issueToken($player);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(402);

        // Token state is unchanged — failure happens before DB::transaction.
        $token->refresh();
        $this->assertNull($token->consumed_at);
        $player->refresh();
        $this->assertEquals(1.00, $player->balance);
    }

    public function test_payout_target_not_found_returns_404(): void
    {
        $group = $this->makeGroup();
        $amusement = $this->makeAmusement($group->id);

        $response = $this->postJson('/transactions/999999/payout', [
            'amount' => 1.00,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(404);
    }

    public function test_payout_missing_api_key_returns_422(): void
    {
        $response = $this->postJson('/transactions/1/payout', [
            'amount' => 1.00,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['api_key']);
    }

    public function test_payout_target_is_payout_row_returns_400(): void
    {
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id);

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);
        $feeId = $feeRes->json('transaction_id');

        $payoutRes = $this->postJson("/transactions/{$feeId}/payout", [
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ]);
        $payoutTxId = $payoutRes->json('transaction_id');

        // Trying to payout the payout row should fail with 400.
        $second = $this->postJson("/transactions/{$payoutTxId}/payout", [
            'amount' => 1.00,
            'api_key' => $amusement->api_key,
        ]);

        $second->assertStatus(400);
        $second->assertJsonFragment(['message' => 'Only fee transactions can be paid out']);
    }

    public function test_amusement_balance_serializes_as_number(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeUser($group->id, 100.0, 'Member');
        $amusement = $this->makeAmusement($group->id);

        $response = $this->actingAs($member)
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

        $response = $this->actingAs($member)
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

        $outsider = $this->makeUser($otherGroup->id, 100.0, 'Outsider');

        $response = $this->actingAs($outsider)
            ->getJson("/amusements/{$amusement->id}/transactions");

        $response->assertStatus(403);
    }

    public function test_store_falls_back_to_amusement_price_when_amount_omitted(): void
    {
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id, price: 7.50);
        $token = $this->issueToken($player);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(7.50, $response->json('amount'));

        $player->refresh();
        $this->assertEquals(92.50, $player->balance);

        $amusement->refresh();
        $this->assertEquals(7.50, $amusement->amusement_balance);
    }

    public function test_store_returns_422_when_amount_omitted_and_amusement_has_no_price(): void
    {
        $group = $this->makeGroup();
        $player = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id, price: null);
        $token = $this->issueToken($player);

        $response = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(422);

        $player->refresh();
        $this->assertEquals(100.00, $player->balance);
    }

    public function test_payout_falls_back_to_amusement_player_payout_when_amount_omitted(): void
    {
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id, playerPayout: 12.00);

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);
        $feeId = $feeRes->json('transaction_id');

        $response = $this->postJson("/transactions/{$feeId}/payout", [
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(12.00, $response->json('amount'));

        $player->refresh();
        $this->assertEquals(107.00, $player->balance);

        $amusement->refresh();
        $this->assertEquals(-7.00, $amusement->amusement_balance);
    }

    public function test_payout_returns_422_when_amount_omitted_and_amusement_has_no_player_payout(): void
    {
        $ownerGroup = $this->makeGroup('Owners');
        $playerGroup = $this->makeGroup('Players');
        $player = $this->makeUser($playerGroup->id);
        $amusement = $this->makeAmusement($ownerGroup->id, playerPayout: null);

        $token = $this->issueToken($player);
        $feeRes = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);
        $feeId = $feeRes->json('transaction_id');

        $response = $this->postJson("/transactions/{$feeId}/payout", [
            'api_key' => $amusement->api_key,
        ]);

        $response->assertStatus(422);

        $player->refresh();
        $this->assertEquals(95.00, $player->balance);
    }

    public function test_stats_returns_correct_totals(): void
    {
        $group = $this->makeGroup();
        $member = $this->makeUser($group->id, 100.0, 'Member');
        $playerA = $this->makeUser($group->id, 100.0, 'Player-A');
        $playerB = $this->makeUser($group->id, 100.0, 'Player-B');
        $amusement = $this->makeAmusement($group->id);

        foreach ([$playerA, $playerB] as $player) {
            $token = $this->issueToken($player);
            $this->postJson('/transactions', [
                'identity_token' => $token->token,
                'amount' => 5.00,
                'api_key' => $amusement->api_key,
            ])->assertStatus(201);
        }

        $firstFeeId = $amusement->transactions()->where('type', 'fee')->orderBy('id')->first()->id;
        $this->postJson("/transactions/{$firstFeeId}/payout", [
            'amount' => 3.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $response = $this->actingAs($member)
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
