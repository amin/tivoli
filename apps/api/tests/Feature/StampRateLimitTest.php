<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\IdentityToken;
use App\Models\Stamptype;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StampRateLimitTest extends TestCase
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

    private function makeGroup(): Group
    {
        return Group::forceCreate(['name' => 'G-' . Str::random(6)]);
    }

    private function makeUser(?int $groupId): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 100,
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

    private function entry(User $u, Amusement $a, float $amount = 1.00): array
    {
        $token = IdentityToken::issueFor($u);
        $res = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => $amount,
            'api_key' => $a->api_key,
        ]);
        $res->assertStatus(201);
        return $res->json();
    }

    public function test_second_entry_within_3_min_is_fee_with_no_stamp(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $first = $this->entry($player, $amusement);
        $this->assertNotNull($first['stamp']);
        $firstStampedTxId = $first['transaction_id'];

        $second = $this->entry($player, $amusement);
        $this->assertNull($second['stamp']);

        $this->assertDatabaseHas('transactions', [
            'id' => $second['transaction_id'],
            'type' => 'fee',
            'stamp_id' => null,
        ]);

        $this->assertDatabaseMissing('transactions', [
            'id' => $firstStampedTxId,
            'stamp_id' => null,
        ]);
    }

    public function test_entry_more_than_3_min_after_last_stamp_awards_stamp_again(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $first = $this->entry($player, $amusement);
        $this->assertNotNull($first['stamp']);

        Transaction::where('id', $first['transaction_id'])->update([
            'created_at' => now()->subMinutes(4),
        ]);

        $second = $this->entry($player, $amusement);
        $this->assertNotNull($second['stamp']);
        $this->assertSame(2, \App\Models\Stamp::where('user_id', $player->id)->count());
    }

    public function test_rate_limit_is_per_user_amusement_pair(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $other = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $playersFirst = $this->entry($player, $amusement);
        $this->assertNotNull($playersFirst['stamp']);

        $otherFirst = $this->entry($other, $amusement);
        $this->assertNotNull($otherFirst['stamp']);
    }

    public function test_rate_limit_is_per_amusement(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $a = $this->makeAmusement($owners->id);
        $b = $this->makeAmusement($owners->id);

        $atA = $this->entry($player, $a);
        $atB = $this->entry($player, $b);

        $this->assertNotNull($atA['stamp']);
        $this->assertNotNull($atB['stamp']);
    }
}
