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

        foreach ($users as $groupName => $names) {

            // Fetch group from database
            $group = Group::where('name', $groupName)->first();

            // Create each user and assign group ID
            foreach ($names as $studentName) {

                User::create([
                    'name' => $studentName,
                    'group_id' => $group->id,
                    'startcode' => Str::uuid()->toString(),
                    'access_key' => null,
                    'balance' => 25,
                    'github_link' => null,
                    'website_link' => null,
                ]);
            }
        }
    }
}
