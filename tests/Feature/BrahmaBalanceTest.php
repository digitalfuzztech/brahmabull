<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Admin\BrahmaPlays;
use App\Livewire\Pages\BrahmaBalance;
use App\Livewire\Pages\Games as PlayerGames;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletAgent;
use App\Models\WalletType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrahmaBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_existing_players_start_with_zero_brahma_balance(): void
    {
        $player = $this->userWithRole('player');

        $this->assertSame('0.00', $player->fresh()->brahma_balance);
    }

    public function test_player_can_submit_brahma_deposit_without_changing_balance_and_notifications_are_created(): void
    {
        Storage::fake('public');
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $wallet = $this->wallet();

        Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->set('amount', '100')
            ->set('paymentType', $wallet->type)
            ->set('selectedWallet', $wallet->id)
            ->set('proofImage', UploadedFile::fake()->image('proof.jpg'))
            ->call('submitDeposit')
            ->assertHasNoErrors();

        $deposit = BrahmaDeposit::first();

        $this->assertNotNull($deposit);
        $this->assertSame('pending', $deposit->status);
        $this->assertSame('0.00', $player->fresh()->brahma_balance);
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_deposit_submitted']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'brahma_deposit_created']);
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'brahma_deposit_created']);
        Storage::disk('public')->assertExists($deposit->proof_image);
    }

    public function test_admin_can_verify_brahma_deposit_once(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $this->userWithRole('agent');
        $deposit = BrahmaDeposit::create([
            'user_id' => $player->id,
            'wallet_id' => $this->wallet()->id,
            'wallet_type' => 'CashApp',
            'wallet_name' => 'Main',
            'wallet_account_identifier' => '$main',
            'amount' => 100,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'verified')
            ->set('load_balance', '105')
            ->call('processDeposit')
            ->assertHasNoErrors();

        $this->assertSame('105.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'credit')->count());

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'verified')
            ->set('load_balance', '105')
            ->call('processDeposit')
            ->assertHasNoErrors();

        $this->assertSame('105.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'credit')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_balance_loaded']);
        $this->assertDatabaseHas('notifications', ['type' => 'brahma_deposit_verified']);
    }

    public function test_rejected_brahma_deposit_does_not_change_balance(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $deposit = BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => 50,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'rejected')
            ->call('processDeposit')
            ->assertHasNoErrors();

        $this->assertSame('0.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaBalanceTransaction::where('type', 'credit')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_deposit_rejected']);
    }

    public function test_player_can_submit_brahma_play_only_when_balance_is_sufficient(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 10])->save();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $game = $this->game();

        Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('pointsToLoad', '11')
            ->call('submitBrahmaPlay')
            ->assertHasErrors(['pointsToLoad']);

        Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('pointsToLoad', '5')
            ->call('submitBrahmaPlay')
            ->assertHasNoErrors();

        $play = BrahmaPlayRequest::first();

        $this->assertSame('pending', $play->status);
        $this->assertSame('10.00', $player->fresh()->brahma_balance);
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_play_submitted']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'brahma_play_created']);
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'brahma_play_created']);
    }

    public function test_admin_can_verify_brahma_play_once_and_game_account_is_created(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 10])->save();
        $admin = $this->userWithRole('admin');
        $this->userWithRole('agent');
        $game = $this->game();
        $play = BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => 5,
            'balance_at_submission' => 10,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'player-game')
            ->set('game_password', 'secret')
            ->call('processPlay')
            ->assertHasNoErrors();

        $this->assertSame('5.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'debit')->count());
        $this->assertDatabaseHas('game_accounts', [
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'player-game',
            'game_password' => 'secret',
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_play_verified']);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'player-game')
            ->set('game_password', 'secret')
            ->call('processPlay')
            ->assertHasNoErrors();

        $this->assertSame('5.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'debit')->count());
    }

    public function test_brahma_play_verification_cannot_make_balance_negative(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 3])->save();
        $admin = $this->userWithRole('admin');
        $game = $this->game();
        $play = BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => 5,
            'balance_at_submission' => 10,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'player-game')
            ->set('game_password', 'secret')
            ->call('processPlay')
            ->assertHasErrors(['status']);

        $this->assertSame('3.00', $player->fresh()->brahma_balance);
        $this->assertSame('pending', $play->fresh()->status);
        $this->assertSame(0, BrahmaBalanceTransaction::where('type', 'debit')->count());
    }

    public function test_rejected_brahma_play_requires_reason_and_leaves_balance_untouched(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 10])->save();
        $admin = $this->userWithRole('admin');
        $this->userWithRole('agent');
        $game = $this->game();
        $play = BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => 5,
            'balance_at_submission' => 10,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'rejected')
            ->call('processPlay')
            ->assertHasErrors(['rejection_note']);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'rejected')
            ->set('rejection_note', 'Invalid request')
            ->call('processPlay')
            ->assertHasNoErrors();

        $this->assertSame('10.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaBalanceTransaction::where('type', 'debit')->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_play_rejected']);
        $this->assertDatabaseHas('notifications', ['type' => 'brahma_play_rejected_admin']);
    }

    public function test_existing_normal_deposit_workflow_is_still_available(): void
    {
        Storage::fake('public');
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $wallet = $this->wallet();
        $game = $this->game();

        Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('amount', '25')
            ->set('paymentType', $wallet->type)
            ->set('selectedWallet', $wallet->id)
            ->set('proofImage', UploadedFile::fake()->image('normal.jpg'))
            ->call('submitDeposit')
            ->assertHasNoErrors();

        $this->assertSame(1, Deposit::count());
        $this->assertSame(0, BrahmaDeposit::count());
        $this->actingAs($admin)->get(route('admin.deposits'))->assertOk();
    }

    public function test_existing_cashout_and_admin_agent_routes_remain_available_and_brahma_pages_are_protected(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');

        $this->actingAs($player)->get(route('cashouts'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($agent)->get(route('agent.dashboard'))->assertOk();

        $this->actingAs($player)->get(route('admin.brahma.deposits'))->assertForbidden();
        $this->actingAs($player)->get(route('agent.brahma.plays'))->assertForbidden();
        $this->actingAs($agent)->get(route('admin.brahma.deposits'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . fake()->unique()->numberBetween(1000, 9999),
            'role' => $role,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function game(): Game
    {
        return Game::create([
            'name' => 'Test Game',
            'slug' => 'test-game-' . fake()->unique()->numberBetween(1000, 9999),
            'image' => 'games/test.jpg',
            'game_url' => 'example.com/play',
            'is_active' => true,
        ]);
    }

    private function wallet(): Wallet
    {
        $owner = $this->userWithRole('admin');
        $agent = WalletAgent::create([
            'name' => 'Main Agent',
            'slug' => 'main-agent-' . fake()->unique()->numberBetween(1000, 9999),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $type = WalletType::create([
            'wallet_agent_id' => $agent->id,
            'name' => 'CashApp',
            'slug' => 'cashapp-' . fake()->unique()->numberBetween(1000, 9999),
        ]);

        return Wallet::create([
            'wallet_type_id' => $type->id,
            'wallet_agent_id' => $agent->id,
            'type' => 'cashapp',
            'name' => 'Main Wallet',
            'account_identifier' => '$main',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
    }
}
