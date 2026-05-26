<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeGuest(bool $isActive): User
    {
        return User::forceCreate([
            'name' => 'Guest',
            'group_id' => null,
            'startcode' => (string) Str::uuid(),
            'access_key' => null,
            'balance' => 90000,
            'is_active' => $isActive,
        ]);
    }

    private function makeUser(?int $groupId): User
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

    private function adminUser(): User
    {
        $group = Group::forceCreate(['name' => 'admin-' . Str::random(6), 'is_admin' => true]);
        return $this->makeUser($group->id);
    }

    public function test_status_is_true_when_guest_active(): void
    {
        $this->makeGuest(true);

        $this->getJson('/guest')
            ->assertStatus(200)
            ->assertJson(['active' => true]);
    }

    public function test_status_is_false_when_guest_inactive(): void
    {
        $this->makeGuest(false);

        $this->getJson('/guest')
            ->assertStatus(200)
            ->assertJson(['active' => false]);
    }

    public function test_status_is_false_when_no_guest_exists(): void
    {
        $this->getJson('/guest')
            ->assertStatus(200)
            ->assertJson(['active' => false]);
    }

    public function test_admin_can_disable_guest(): void
    {
        $this->makeGuest(true);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', ['is_active' => false])
            ->assertStatus(200)
            ->assertJson(['active' => false]);

        $this->assertFalse((bool) User::whereRaw('LOWER(name) = ?', ['guest'])->first()->is_active);
    }

    public function test_admin_can_enable_guest(): void
    {
        $this->makeGuest(false);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', ['is_active' => true])
            ->assertStatus(200)
            ->assertJson(['active' => true]);

        $this->assertTrue((bool) User::whereRaw('LOWER(name) = ?', ['guest'])->first()->is_active);
    }

    public function test_non_admin_cannot_toggle_guest(): void
    {
        $this->makeGuest(true);
        $nonAdmin = $this->makeUser(null);

        $this->actingAs($nonAdmin)
            ->patchJson('/guest', ['is_active' => false])
            ->assertStatus(403);

        $this->assertTrue((bool) User::whereRaw('LOWER(name) = ?', ['guest'])->first()->is_active);
    }

    public function test_unauthenticated_cannot_toggle_guest(): void
    {
        $this->makeGuest(true);

        $this->patchJson('/guest', ['is_active' => false])
            ->assertStatus(401);
    }

    public function test_toggle_returns_404_when_no_guest_exists(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', ['is_active' => true])
            ->assertStatus(404);
    }

    public function test_toggle_validates_is_active(): void
    {
        $this->makeGuest(true);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', [])
            ->assertStatus(422);
    }
}
