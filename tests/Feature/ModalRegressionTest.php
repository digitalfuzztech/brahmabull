<?php

namespace Tests\Feature;

use App\Livewire\Pages\BrahmaBalance;
use App\Livewire\Pages\Games as PlayerGames;
use App\Models\Game;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletAgent;
use App\Models\WalletType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModalRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_header_brahma_deposit_modal_has_stable_teleport_roots_and_opens_and_closes(): void
    {
        $player = $this->userWithRole('player');
        $component = Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->assertSet('showModal', false)
            ->assertDontSee('Brahma Balance Deposit');

        $this->assertMatchesRegularExpression(
            '/<template x-teleport="body">\s*<div>/',
            $component->html()
        );

        $component
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertSee('Brahma Balance Deposit')
            ->assertSeeHtml('z-[10000]')
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertDontSee('Brahma Balance Deposit');
    }

    public function test_header_brahma_wallet_preview_opens_and_closes(): void
    {
        $player = $this->userWithRole('player');
        $wallet = $this->wallet();

        Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->set('paymentType', $wallet->type)
            ->call('selectWallet', $wallet->id)
            ->set('showWalletPreview', true)
            ->assertSet('showWalletPreview', true)
            ->assertSee('Wallet Details')
            ->assertSeeHtml('z-[10001]')
            ->set('showWalletPreview', false)
            ->assertSet('showWalletPreview', false)
            ->assertDontSee('Wallet Details');
    }

    public function test_game_play_modal_opens_on_existing_payment_and_switches_to_brahma_balance(): void
    {
        $player = $this->userWithRole('player');
        $game = $this->game();
        $component = Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->assertSet('showModal', false)
            ->call('openPlayModal', $game->id)
            ->assertSet('showModal', true)
            ->assertSet('playModalTab', 'payment')
            ->assertSee('Deposit Amount')
            ->assertSee('Payment Screenshot');

        $this->assertTrue($component->get('selectedGame')->is($game));

        $component
            ->set('playModalTab', 'brahma')
            ->assertSet('playModalTab', 'brahma')
            ->assertSee('Current Brahma Balance')
            ->assertSee('Points to Load')
            ->call('closeModal')
            ->assertSet('showModal', false);
    }

    public function test_existing_game_payment_qr_thumbnail_opens_and_closes_existing_preview(): void
    {
        $player = $this->userWithRole('player');
        $game = $this->game();
        $wallet = $this->wallet();

        Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('paymentType', $wallet->type)
            ->call('selectWallet', $wallet->id)
            ->assertSet('showWalletPreview', false)
            ->assertSeeHtml('aria-label="Preview selected wallet QR code"')
            ->set('showWalletPreview', true)
            ->assertSet('showWalletPreview', true)
            ->assertSee('Wallet Details')
            ->set('showWalletPreview', false)
            ->assertSet('showWalletPreview', false)
            ->assertDontSee('Wallet Details');
    }

    public function test_player_wallet_actions_render_in_normal_and_brahma_main_and_preview_displays(): void
    {
        $player = $this->userWithRole('player');
        $game = $this->game();
        $wallet = $this->wallet();

        $games = Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('paymentType', $wallet->type)
            ->assertSeeHtml('wire:click="selectWallet('.$wallet->id.')"')
            ->call('selectWallet', $wallet->id)
            ->assertSee('$modal')
            ->assertSee('Copy')
            ->assertSee('Download QR')
            ->assertSeeHtml('download="brahmabull-wallet-qr.png"')
            ->set('showWalletPreview', true);

        $this->assertSame(2, substr_count($games->html(), 'data-wallet-actions'));
        $this->assertSame(2, substr_count($games->html(), 'data-wallet-qr-download'));

        $brahma = Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->call('openModal')
            ->set('paymentType', $wallet->type)
            ->assertSeeHtml('wire:click="selectWallet('.$wallet->id.')"')
            ->call('selectWallet', $wallet->id)
            ->assertSee('$modal')
            ->assertSee('Copy')
            ->assertSee('Download QR')
            ->set('showWalletPreview', true);

        $this->assertSame(2, substr_count($brahma->html(), 'data-wallet-actions'));
        $this->assertSame(2, substr_count($brahma->html(), 'data-wallet-qr-download'));
    }

    public function test_missing_wallet_values_do_not_render_dead_copy_or_download_actions(): void
    {
        $player = $this->userWithRole('player');
        $game = $this->game();
        $wallet = $this->wallet();
        $wallet->update(['account_identifier' => '', 'qr_image' => null]);

        $games = Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('paymentType', $wallet->type)
            ->call('selectWallet', $wallet->id);

        $this->assertStringNotContainsString('data-wallet-actions', $games->html());
        $this->assertStringNotContainsString('data-wallet-qr-download', $games->html());

        $brahma = Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->call('openModal')
            ->set('paymentType', $wallet->type)
            ->call('selectWallet', $wallet->id);

        $this->assertStringNotContainsString('data-wallet-actions', $brahma->html());
        $this->assertStringNotContainsString('data-wallet-qr-download', $brahma->html());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.fake()->unique()->numberBetween(1000, 9999),
            'role' => $role,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function game(): Game
    {
        return Game::create([
            'name' => 'Modal Test Game',
            'slug' => 'modal-test-'.fake()->unique()->numberBetween(1000, 9999),
            'image' => 'games/test.jpg',
            'game_url' => 'example.com/play',
            'is_active' => true,
        ]);
    }

    private function wallet(): Wallet
    {
        $owner = $this->userWithRole('admin');
        $agent = WalletAgent::create([
            'name' => 'Modal Wallet Agent',
            'slug' => 'modal-agent-'.fake()->unique()->numberBetween(1000, 9999),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $type = WalletType::create([
            'wallet_agent_id' => $agent->id,
            'name' => 'CashApp',
            'slug' => 'modal-cashapp-'.fake()->unique()->numberBetween(1000, 9999),
        ]);

        return Wallet::create([
            'wallet_type_id' => $type->id,
            'wallet_agent_id' => $agent->id,
            'type' => 'cashapp',
            'name' => 'Modal Wallet',
            'account_identifier' => '$modal',
            'qr_image' => 'wallets/modal-qr.png',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
    }
}
