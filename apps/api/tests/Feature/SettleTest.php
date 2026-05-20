<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettleTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(string $name = 'G', bool $isAdmin = false): Group
    {
        return Group::forceCreate(['name' => $name . '-' . Str::random(6), 'is_admin' => $isAdmin]);
    }

    private function makeUser(?int $groupId, float $balance = 0.0): User
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

    private function makeAmusement(int $groupId, float $balance): Amusement
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

    public function test_unauthenticated_caller_returns_401(): void
    {
        $owners = $this->makeGroup('owners', false);
        $member = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, -10.00);

        $res = $this->postJson('/settle');

        $res->assertStatus(401);

        $a->refresh();
        $member->refresh();
        $this->assertEquals(-10.00, $a->amusement_balance);
        $this->assertNull($a->settled_at);
        $this->assertEquals(100.00, $member->balance);
    }

    public function test_non_admin_caller_returns_403_and_no_side_effects(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($owners->id, 100.00);
        $member = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, -10.00);

        $res = $this->actingAs($caller)->postJson('/settle');

        $res->assertStatus(403);
        $a->refresh();
        $member->refresh();
        $caller->refresh();
        $this->assertEquals(-10.00, $a->amusement_balance);
        $this->assertNull($a->settled_at);
        $this->assertEquals(100.00, $member->balance);
        $this->assertEquals(100.00, $caller->balance);
    }

    public function test_admin_settles_negative_amusement_by_splitting_debt(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 1000.00);
        $o1 = $this->makeUser($owners->id, 50.00);
        $o2 = $this->makeUser($owners->id, 50.00);
        $a = $this->makeAmusement($owners->id, -20.00);

        $res = $this->actingAs($caller)->postJson('/settle');

        $res->assertStatus(200);
        $o1->refresh();
        $o2->refresh();
        $this->assertEquals(40.00, $o1->balance);
        $this->assertEquals(40.00, $o2->balance);
        $a->refresh();
        $this->assertEqualsWithDelta(-20.00, $a->amusement_balance, 0.0001);
        $this->assertNotNull($a->settled_at);
    }

    public function test_admin_does_not_alter_non_negative_amusements(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $o1 = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, 5.00);

        $res = $this->actingAs($caller)->postJson('/settle');
        $res->assertStatus(200);

        $o1->refresh();
        $this->assertEquals(100.00, $o1->balance);
        $a->refresh();
        $this->assertEquals(5.00, $a->amusement_balance);
        $this->assertNotNull($a->settled_at);
    }

    public function test_second_settle_is_a_noop(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $o1 = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, -10.00);

        $this->actingAs($caller)->postJson('/settle')->assertStatus(200);

        $o1->refresh();
        $balanceAfterFirst = $o1->balance;

        $second = $this->actingAs($caller)->postJson('/settle');
        $second->assertStatus(200);

        $o1->refresh();
        $a->refresh();
        $this->assertEquals($balanceAfterFirst, $o1->balance);
        $this->assertEquals(-10.00, $a->amusement_balance);
    }

    public function test_member_balance_can_go_negative(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $o1 = $this->makeUser($owners->id, 5.00);
        $a = $this->makeAmusement($owners->id, -20.00);

        $this->actingAs($caller)->postJson('/settle')->assertStatus(200);

        $o1->refresh();
        $this->assertEquals(-15.00, $o1->balance);
    }

    public function test_response_includes_per_amusement_details(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $this->makeUser($owners->id, 0);
        $this->makeUser($owners->id, 0);
        $negative = $this->makeAmusement($owners->id, -10.00);
        $positive = $this->makeAmusement($owners->id, 5.00);

        $res = $this->actingAs($caller)->postJson('/settle');
        $res->assertStatus(200);

        $details = collect($res->json('details'))->keyBy('amusement_id');
        $this->assertEquals(5.00, $details[$negative->id]['deducted_per_member']);
        $this->assertEquals(0, $details[$positive->id]['deducted_per_member']);
        $this->assertSame(2, $details[$negative->id]['member_count']);
    }
}
