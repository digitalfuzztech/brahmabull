<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\PlayerProfile;

class GeneratePlayerProfiles extends Command
{
    protected $signature = 'generate:player-profiles';

    protected $description = 'Generate player profiles with player IDs';

    public function handle()
    {
        $players = User::role('player')->get();

        $nextPlayerId = PlayerProfile::max('player_id') ?? 10000;

        foreach ($players as $player) {

            if (!$player->playerProfile) {

                $nextPlayerId++;

                PlayerProfile::create([
                    'user_id' => $player->id,
                    'player_id' => $nextPlayerId,
                ]);
            }
        }

        $this->info('Player profiles generated successfully.');

        return 0;
    }
}
