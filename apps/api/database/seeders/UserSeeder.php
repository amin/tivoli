<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Group;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Group name and user names
        $users = [
            'Emilie, Amin, Nathalie' => [
                'Emilie',
                'Amin',
                'Nathalie',
            ],
            'Wilma, Benita' => [
                'Wilma',
                'Benita',
            ],
            'Elsa, Laura, John' => [
                'Elsa',
                'Laura',
                'John',
            ],
            'Anton, Emma' => [
                'Anton',
                'Emma',
            ],
            'Olivia, Olof' => [
                'Olivia',
                'Olof',
            ],
            'Tim, Robin' => [
                'Tim',
                'Robin',
            ],
            'Allan, Alex' => [
                'Allan',
                'Alex',
            ],
            'Marie, Patricia, Malin' => [
                'Marie',
                'Patricia',
                'Malin',
            ],
            'Hanna, Maria' => [
                'Hanna',
                'Maria',
            ],
            'Eddie, Daniella' => [
                'Eddie',
                'Daniella',
            ],
        ];

        // Startcodes for deterministic seeding
        $startcodes = [
            'Emilie' => 'c81e18e2-5525-4c9a-aa15-cf9ffcc8d72c',
            'Amin' => 'afc2b0f0-7c1e-484a-834f-c1adc25a819d',
            'Nathalie' => '9740f873-e137-414b-a6b6-7bd53a035c0b',
            'Wilma' => '967d6aff-e60c-4afe-9475-46cb0c8b4b6f',
            'Benita' => 'c65332ae-923c-4817-a54a-135c1a748347',
            'Elsa' => 'ba5e48d5-b169-4b8c-abfd-14b2a52aaa5b',
            'Laura' => '8fe89580-1536-4179-b5b4-bb07a55ab9d9',
            'John' => 'eba8b307-77b8-4809-ab4e-d7c457b43ee3',
            'Anton' => 'd5e5e9bc-8949-4cb4-875c-f0d621faa44a',
            'Emma' => 'db273090-155a-4088-bb02-c429b656eb45',
            'Olivia' => '5cb056f9-cc64-45ac-a6e1-6b61f4068959',
            'Olof' => '8bebd2d8-c562-49b9-b676-d9570eb4c6c2',
            'Tim' => '554f7db5-97fd-423a-b8bf-0db23f817116',
            'Robin' => '3412cca3-b526-4eb8-9962-b51bd8e52058',
            'Allan' => 'da51ea1b-ab32-4834-920a-e03ee0048a60',
            'Alex' => '38a50345-913c-4155-8361-6b27436fca3d',
            'Marie' => '73891e9c-799d-45c4-8165-9138ef13701f',
            'Patricia' => '7f8f0766-8eba-46fd-9901-af9a94899ef5',
            'Malin' => 'd44df589-222e-4369-81d3-b30f09a67695',
            'Hanna' => '023e0e1f-0f49-4def-a2f6-79c18eb86246',
            'Maria' => '78e44e33-5c43-4176-bdf4-0795821c3d5a',
            'Eddie' => '7fe1100b-fd28-444e-b6c5-54121aef51ae',
            'Daniella' => '6cb4cc5a-db71-43bc-9e86-0851d10f8782'
        ];

        foreach ($users as $groupName => $names) {

            // Fetch group from database
            $group = Group::where('name', $groupName)->first();

            // Create each user and assign group ID
            foreach ($names as $studentName) {

                User::create([
                    'name' => $studentName,
                    'group_id' => $group->id,
                    'startcode' => $startcodes[$studentName] ?? Str::uuid()->toString(),
                    'access_key' => null,
                    'balance' => 25,
                    'github_link' => null,
                    'website_link' => null,
                ]);
            }
        }
    }
}
