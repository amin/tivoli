<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\Stamp;
use App\Models\Stamptype;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class VpPenaltyTest extends TestCase
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

    private function makeUser(int $groupId): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId, float $balance = 0): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
            'amusement_balance' => $balance,
        ]);
    }

    private function giveMetalSet(User $u): void
    {
        foreach (['silver', 'gold', 'platinum'] as $m) {
            $animal = 'lion';
            $type = Stamptype::where('animal', $animal)->where('metal', $m)->firstOrFail();
            Stamp::create(['user_id' => $u->id, 'stamptype_id' => $type->id]);
        }
    }

    public function test_leaderboard_returns_0_vp_when_group_has_negative_amusement(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, -0.01);

        $res = $this->actingAs($user)->getJson('/leaderboard');
        $res->assertStatus(200);

        $row = collect($res->json('vp_leaders'))->firstWhere('name', $user->name);
        $this->assertSame(0, $row['total_vp']);
    }

    public function test_leaderboard_returns_normal_vp_when_no_negative_amusement(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, 10.00);

        $res = $this->actingAs($user)->getJson('/leaderboard');
        $res->assertStatus(200);

        $row = collect($res->json('vp_leaders'))->firstWhere('name', $user->name);
        $this->assertSame(46, $row['total_vp']);
    }

    public function test_stamps_index_returns_0_total_vp_under_penalty(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, -1.00);

        $res = $this->actingAs($user)->getJson('/stamps');
        $res->assertStatus(200);
        $this->assertSame(0, $res->json('total_vp'));
    }

    public function test_stamps_index_returns_normal_total_vp_without_penalty(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, 0);

        $res = $this->actingAs($user)->getJson('/stamps');
        $res->assertStatus(200);
        $this->assertSame(46, $res->json('total_vp'));
    }

    public function test_penalty_persists_after_settle(): void
    {
        $admin = Group::forceCreate(['name' => 'admin-' . Str::random(6), 'is_admin' => true]);
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, -1.00);

        $caller = $this->makeUser($admin->id);
        $this->actingAs($caller)->postJson('/settle')->assertStatus(200);

        $res = $this->actingAs($user)->getJson('/stamps');
        $res->assertStatus(200);
        $this->assertSame(0, $res->json('total_vp'));
    }
}
