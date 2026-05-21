<?php

namespace Tests\Unit;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserVpPenaltyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?int $groupId, float $balance = 0): User
    {
        return User::forceCreate([
            'name' => 'Player-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
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

    public function test_returns_false_when_user_has_no_group(): void
    {
        $user = $this->makeUser(null);
        $this->assertFalse($user->hasNegativeGroupAmusement());
    }

    public function test_returns_false_when_group_has_no_amusements(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $this->assertFalse($user->hasNegativeGroupAmusement());
    }

    public function test_returns_false_when_all_amusements_non_negative(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $this->makeAmusement($group->id, 10.0);
        $this->makeAmusement($group->id, 0.0);
        $this->assertFalse($user->hasNegativeGroupAmusement());
    }

    public function test_returns_true_when_any_amusement_negative(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $this->makeAmusement($group->id, 10.0);
        $this->makeAmusement($group->id, -0.01);
        $this->assertTrue($user->hasNegativeGroupAmusement());
    }
}
