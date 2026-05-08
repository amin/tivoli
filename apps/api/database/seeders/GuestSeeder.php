<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GuestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create guest account
        // Used by students during development for testing their applications
        // Will then be used by guests (will not trigger any transactions)


        // Delete old guest if exists
        User::where('name', 'Guest')->delete();

        User::create([
            'name' => 'Guest',
            'group_id' => null,
            'startcode' => '4755efcb-6a7d-4013-ac21-fed62bebb265',
            'access_key' => null, // Generates when activated
            'balance' => 90000,
            'github_link' => null,
            'website_link' => null,
            'is_active' => true, // Toggle to false to inactive account
        ]);
    }
}
