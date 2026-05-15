<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmusementCreationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?int $groupId = null, string $name = 'Tester'): User
    {
        return User::forceCreate([
            'name' => $name,
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 100,
            'is_active' => true,
        ]);
    }

    public function test_group_member_can_register_an_amusement(): void
    {
        $group = Group::forceCreate(['name' => 'Test Group']);
        $user = $this->makeUser($group->id);

        $response = $this->actingAs($user)->postJson('/amusements', [
            'name' => 'Fortune Wheel',
            'description' => 'Spin to win',
            'url' => 'https://fortune.example.com',
            'price' => 5,
            'type' => 'game',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('amusement.name', 'Fortune Wheel');
        $response->assertJsonPath('amusement.group_id', $group->id);
        $response->assertJsonStructure(['amusement' => ['api_key']]);

        $this->assertDatabaseHas('amusements', [
            'name' => 'Fortune Wheel',
            'group_id' => $group->id,
            'type' => 'game',
        ]);
    }

    public function test_amusement_can_be_created_without_price(): void
    {
        $group = Group::forceCreate(['name' => 'No-Price Group']);
        $user = $this->makeUser($group->id);

        $response = $this->actingAs($user)->postJson('/amusements', [
            'name' => 'Variable Stake Bingo',
            'url' => 'https://bingo.example.com',
            'type' => 'game',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('amusements', [
            'name' => 'Variable Stake Bingo',
            'price' => null,
        ]);
    }

    public function test_user_without_group_cannot_register_an_amusement(): void
    {
        $user = $this->makeUser(null);

        $response = $this->actingAs($user)->postJson('/amusements', [
            'name' => 'Orphan Game',
            'url' => 'https://orphan.example.com',
            'type' => 'game',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('amusements', 0);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/amusements', [
            'name' => 'No Auth',
            'url' => 'https://example.com',
            'type' => 'game',
        ]);

        $response->assertStatus(401);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $group = Group::forceCreate(['name' => 'Dup Group']);
        $user = $this->makeUser($group->id);

        $this->actingAs($user)
            ->postJson('/amusements', [
                'name' => 'Bingo',
                'url' => 'https://bingo.example.com',
                'type' => 'game',
            ])->assertStatus(201);

        $second = $this->actingAs($user)
            ->postJson('/amusements', [
                'name' => 'Bingo',
                'url' => 'https://bingo2.example.com',
                'type' => 'game',
            ]);

        $second->assertStatus(422);
        $second->assertJsonValidationErrors(['name']);
    }

    public function test_validation_rejects_invalid_type(): void
    {
        $group = Group::forceCreate(['name' => 'Validation Group']);
        $user = $this->makeUser($group->id);

        $response = $this->actingAs($user)->postJson('/amusements', [
            'name' => 'Bad Type',
            'url' => 'https://example.com',
            'type' => 'roller-coaster',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_response_includes_api_key_only_for_group_members(): void
    {
        $group = Group::forceCreate(['name' => 'Visibility Group']);
        $user = $this->makeUser($group->id);

        $createRes = $this->actingAs($user)->postJson('/amusements', [
            'name' => 'Test',
            'url' => 'https://example.com',
            'type' => 'game',
        ]);

        $createdId = $createRes->json('amusement.id');
        $this->assertNotEmpty($createRes->json('amusement.api_key'));

        // Subsequent GET as a group member should include api_key
        $showRes = $this->actingAs($user)->getJson("/amusements/{$createdId}");

        $showRes->assertStatus(200);
        $this->assertNotEmpty($showRes->json('api_key'));

        // Index endpoint should never include api_key
        $indexRes = $this->actingAs($user)->getJson('/amusements');

        $indexRes->assertStatus(200);
        $indexJson = $indexRes->json('data');
        foreach ($indexJson as $row) {
            $this->assertArrayNotHasKey('api_key', $row);
        }
    }
}
