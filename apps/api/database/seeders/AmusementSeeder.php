<?php

namespace Database\Seeders;

use App\Models\Amusement;
use App\Models\Group;
use Illuminate\Database\Seeder;

class AmusementSeeder extends Seeder
{
    public function run(): void
    {
        $group = Group::where("is_admin", true)->first();

        // Settled attraction so the admin UI can demonstrate the post-settle state.
        Amusement::forceCreate([
            "group_id" => $group->id,
            "api_key" => "d6fb94db-359f-4f29-86a2-3b9e6dd1d698",
            "name" => "Animal Parade",
            "type" => "attraction",
            "description" => null,
            "url" => "https://animal-parade-dev.vercel.app",
            "image_url" => null,
            "price" => 2.0,
            "player_payout" => null,
            "amusement_balance" => 4.0,
            "buffer_required" => 0.0,
            "buffer_locked" => 0.0,
            "settled_at" => "2026-05-20 07:20:21",
        ]);

        // Game so we can exercise payouts and the settle-deduction flow.
        Amusement::forceCreate([
            "group_id" => $group->id,
            "api_key" => "8b7c9d3e-2a4f-4e1b-9c5d-7f8a6b9c0d1e",
            "name" => "Dice Roll",
            "type" => "game",
            "description" => "Roll a dice. Pay €2, win €10 on a six.",
            "url" => "https://dice-roll-dev.example.com",
            "image_url" => null,
            "price" => 2.0,
            "player_payout" => 10.0,
        ]);
    }
}
