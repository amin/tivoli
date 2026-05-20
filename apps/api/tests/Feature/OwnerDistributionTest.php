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

class OwnerDistributionTest extends TestCase
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

    private function makeUser(?int $groupId, float $balance = 0.0, string $name = 'P'): User
    {
        return User::forceCreate([
            'name' => $name . '-' . Str::random(6),
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
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    public function test_each_owner_gets_equal_share_on_entry(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 10.00, 'O1');
        $o2 = $this->makeUser($owners->id, 10.00, 'O2');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 4.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh();
        $o2->refresh();
        $this->assertEquals(12.00, $o1->balance);
        $this->assertEquals(12.00, $o2->balance);
    }

    public function test_each_owner_gets_share_when_token_is_reused(): void
    {
        // Reusing the same identity_token for mid-game payments still
        // distributes income to the group's members.
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 0, 'O1');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);

        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 1.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 2.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh();
        $this->assertEquals(3.00, $o1->balance);
    }

    public function test_rounding_drops_fractional_cent(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 0, 'O1');
        $o2 = $this->makeUser($owners->id, 0, 'O2');
        $o3 = $this->makeUser($owners->id, 0, 'O3');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh(); $o2->refresh(); $o3->refresh();
        $this->assertEquals(1.67, $o1->balance);
        $this->assertEquals(1.67, $o2->balance);
        $this->assertEquals(1.67, $o3->balance);
    }

    public function test_payout_does_not_touch_owner_balances(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 0, 'O1');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $entry = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 2.00,
            'api_key' => $amusement->api_key,
        ])->json();

        $o1->refresh();
        $balanceAfterEntry = $o1->balance;
        $this->assertEquals(2.00, $balanceAfterEntry);

        $this->postJson("/transactions/{$entry['id']}/payout", [
            'amount' => 4.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh();
        $this->assertEquals($balanceAfterEntry, $o1->balance);
    }

    public function test_amusement_balance_tracks_full_amount_despite_distribution(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $this->makeUser($owners->id, 0);
        $this->makeUser($owners->id, 0);
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 3.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $amusement->refresh();
        $this->assertEquals(3.00, $amusement->amusement_balance);
    }
}
