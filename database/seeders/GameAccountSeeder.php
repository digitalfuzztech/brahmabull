<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Game;
use App\Models\GameAccount;
use Illuminate\Database\Seeder;

class GameAccountSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::pluck('id');
        $games = Game::all();

        $usernames = [
            'ShadowKing',
            'RapidFire',
            'LuckyDragon',
            'DarkKnight',
            'StormBreaker',
            'SilentWolf',
            'AlphaPro',
            'MoneyMaker',
            'FastHands',
            'RoyalPlayer',
            'GoldenTiger',
            'NightHunter',
            'LegendX',
            'BrahmaBoss',
            'TopWinner',
            'NoMercy',
            'FireStorm',
            'KingSlayer',
            'CashMachine',
            'RedTiger',
        ];

        foreach ($userIds as $userId) {

            foreach ($games as $game) {

                GameAccount::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'game_id' => $game->id,
                    ],
                    [
                        'game_username' =>
                            $usernames[array_rand($usernames)]
                            . rand(1000, 9999),

                        'created_by' => 1,
                    ]
                );
            }
        }
    }
}
