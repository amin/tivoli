<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmusementDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(string $name = 'G'): Group
    {
        return Group::forceCreate(['name' => $name . '-' . Str::random(6)]);
    }

    private function makeUser(?int $groupId): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 0.0,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId, ?string $settledAt = null): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
            'amusement_balance' => 0.0,
            'settled_at' => $settledAt,
        ]);
    }

    private function seedTransaction(int $amusementId, int $userId): void
    {
        Transaction::create([
            'user_id' => $userId,
            'amusement_id' => $amusementId,
            'amount' => 5.00,
            'type' => 'fee',
        ]);
    }

    public function test_unsettled_amusement_with_transactions_cannot_be_deleted(): void
    {
        $group = $this->makeGroup();
        $owner = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $this->seedTransaction($amusement->id, $owner->id);

        $res = $this->actingAs($owner)->deleteJson("/amusements/{$amusement->id}");

        $res->assertStatus(409);
        $this->assertDatabaseHas('amusements', ['id' => $amusement->id]);
    }

    public function test_amusement_without_transactions_can_be_deleted(): void
    {
        $group = $this->makeGroup();
        $owner = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        $res = $this->actingAs($owner)->deleteJson("/amusements/{$amusement->id}");

        $res->assertStatus(204);
        $this->assertDatabaseMissing('amusements', ['id' => $amusement->id]);
    }
}
