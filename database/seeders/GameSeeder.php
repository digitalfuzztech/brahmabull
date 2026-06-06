<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Game;
use Illuminate\Support\Str;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            ['name' => 'Juwa', 'image' => 'images/games/juwa.png'],
            ['name' => 'Orion Stars', 'image' => 'images/games/os.jpg'],
            ['name' => 'Game Vault', 'image' => 'images/games/gv.png'],
            ['name' => 'Firekirin', 'image' => 'images/games/fk.jpg'],
            ['name' => 'Ultrapanda', 'image' => 'images/games/up.png'],
            ['name' => 'Vblink', 'image' => 'images/games/vb.webp'],
            ['name' => 'Milkyway', 'image' => 'images/games/mw.jpg'],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(
                ['name' => $game['name']],
                [
                    'slug' => Str::slug($game['name']),
                    'image' => $game['image'],
                    'is_active' => true,
                ]
            );
        }
    }
}
