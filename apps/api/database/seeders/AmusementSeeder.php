<?php

namespace Database\Seeders;

use App\Models\Amusement;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AmusementSeeder extends Seeder
{
    public function run(): void
    {
        $group = Group::where('name', 'Emilie, Amin, Nathalie')->first();

        Amusement::forceCreate([
            'group_id'      => $group->id,
            'api_key'       => (string) Str::uuid(),
            'name'          => 'Preview Attraction',
            'type'          => 'attraction',
            'description'   => 'A test attraction for previewing the card layout.',
            'url'           => 'https://example.com',
            'image_url'     => 'https://picsum.photos/seed/tivoli/400/300',
            'price'         => 5.00,
            'player_payout' => 10.00,
        ]);
    }
}
