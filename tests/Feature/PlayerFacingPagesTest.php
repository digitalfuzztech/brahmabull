<?php

namespace Tests\Feature;

use App\Livewire\Pages\Cashouts;
use App\Livewire\Pages\Games as PlayerGames;
use App\Livewire\Pages\Notifications as PlayerNotifications;
use App\Livewire\Player\ProfilePage;
use App\Models\Game;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerFacingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('player');
    }

    public function test_catalog_renders_real_games_and_preserves_the_existing_play_modal_actions(): void
    {
        $player = $this->player();
        $game = Game::create([
            'name' => 'Catalog Render Game',
            'slug' => 'catalog-render-game',
            'image' => 'games/catalog-render.jpg',
            'game_url' => 'https://example.com/play',
            'is_active' => true,
        ]);

        $this->actingAs($player)
            ->get(route('games'))
            ->assertOk()
            ->assertSee('Catalog Render Game')
            ->assertSee('Choose your game and start playing.')
            ->assertSeeHtml('wire:click="openPlayModal('.$game->id.')"');

        Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->assertSet('showModal', true)
            ->assertSeeHtml('wire:click="submitDeposit"')
            ->assertSeeHtml('wire:model.live="paymentType"')
            ->assertSeeHtml('wire:model="proofImage"')
            ->set('playModalTab', 'brahma')
            ->assertSeeHtml('wire:click="submitBrahmaPlay"')
            ->assertSeeHtml('wire:model="pointsToLoad"');
    }

    public function test_profile_dashboard_preserves_balance_history_tabs_and_edit_form(): void
    {
        $player = $this->player(['brahma_balance' => '125.50']);

        $this->actingAs($player)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Brahma Balance')
            ->assertSee('$125.50')
            ->assertSee('Deposits')
            ->assertSee('Cashouts')
            ->assertSee('Referrals')
            ->assertSee('Spin Wins');

        Livewire::actingAs($player)
            ->test(ProfilePage::class)
            ->call('openEditModal')
            ->assertSet('showEditModal', true)
            ->assertSeeHtml('wire:submit="saveProfile"')
            ->assertSeeHtml('wire:model="photo"')
            ->assertSeeHtml('wire:model="current_password"');
    }

    public function test_notifications_keep_filters_actions_pagination_and_proof_preview(): void
    {
        $player = $this->player();
        $notification = Notification::create([
            'user_id' => $player->id,
            'type' => 'cashout_paid',
            'title' => 'Withdrawal Paid',
            'message' => 'Your withdrawal was paid.',
            'action_text' => 'View Proof',
            'action_url' => route('player.notifications'),
            'is_read' => false,
            'data' => ['payment_proof' => '/storage/cashout-proofs/example.png'],
        ]);

        Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->assertSee('Withdrawal Paid')
            ->assertSeeHtml('wire:model.live="search"')
            ->assertSeeHtml('wire:model.live="type"')
            ->assertSeeHtml('wire:model.live="readStatus"')
            ->assertSeeHtml('wire:click="viewProof('.$notification->id.')"')
            ->call('viewProof', $notification->id)
            ->assertSet('previewImage', '/storage/cashout-proofs/example.png')
            ->assertSee('Cashout payment proof')
            ->call('closePreview')
            ->assertSet('previewImage', null);
    }

    public function test_withdrawal_page_preserves_all_existing_fields_and_submit_action(): void
    {
        $player = $this->player();

        Livewire::actingAs($player)
            ->test(Cashouts::class)
            ->assertSee('Request Withdrawal')
            ->assertSeeHtml('wire:submit="submit"')
            ->assertSeeHtml('wire:model.live="game_id"')
            ->assertSeeHtml('wire:model="game_account_id"')
            ->assertSeeHtml('wire:model="amount"')
            ->assertSeeHtml('wire:model="wallet_type"')
            ->assertSeeHtml('wire:model="wallet_address"')
            ->assertSeeHtml('wire:model="qr_image"');
    }

    private function player(array $attributes = []): User
    {
        $player = User::factory()->create(array_merge([
            'username' => 'player'.fake()->unique()->numberBetween(1000, 9999),
            'role' => 'player',
        ], $attributes));

        $player->assignRole('player');

        return $player;
    }
}
