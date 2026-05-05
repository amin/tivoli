<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            'Emilie, Amin, Nathalie',
            'Wilma, Benita',
            'Elsa, Laura, John',
            'Anton, Emma',
            'Olivia, Olof',
            'Tim, Robin',
            'Allan, Alex',
            'Marie, Patricia, Malin',
            'Hanna, Maria',
            'Eddie, Daniella'
        ];

        foreach ($groups as $groupNames) {
            Group::create(['name' => $groupNames]);
        }
    }
}
