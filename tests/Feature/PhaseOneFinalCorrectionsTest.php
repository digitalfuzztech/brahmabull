<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Admin\BrahmaPlays;
use App\Livewire\Admin\Cashouts as AdminCashouts;
use App\Livewire\Admin\Deposits as AdminDeposits;
use App\Livewire\Pages\Games as PlayerGames;
use App\Livewire\Pages\Notifications as PlayerNotifications;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaDepositAdjustment;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneFinalCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_submitted_deposit_amount_is_immutable_and_admin_correction_applies_only_upward_delta_once(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 50])->save();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $deposit = $this->pendingDeposit($player, 20);

        $this->assertFalse(property_exists(BrahmaDeposits::class, 'amount'));

        Livewire::actingAs($agent)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->assertSee('Deposit Amount: $20.00')
            ->assertDontSeeHtml('wire:model="amount"')
            ->set('status', 'verified')
            ->set('load_balance', '20')
            ->call('processDeposit')
            ->assertHasNoErrors();

        $this->assertSame('20.00', $deposit->fresh()->amount);
        $this->assertSame('70.00', $player->fresh()->brahma_balance);

        $component = Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', '200')
            ->call('adminProcessDeposit')
            ->assertHasNoErrors();

        $this->assertSame('20.00', $deposit->fresh()->amount);
        $this->assertSame('200.00', $deposit->fresh()->load_balance);
        $this->assertSame('250.00', $player->fresh()->brahma_balance);
        $this->assertDatabaseHas('brahma_deposit_adjustments', [
            'brahma_deposit_id' => $deposit->id,
            'old_load_balance' => 20,
            'new_load_balance' => 200,
            'delta' => 180,
            'admin_id' => $admin->id,
        ]);

        $adjustment = BrahmaDepositAdjustment::firstOrFail();
        $this->assertDatabaseHas('brahma_balance_transactions', [
            'type' => 'credit',
            'amount' => 180,
            'source_type' => BrahmaDepositAdjustment::class,
            'source_id' => $adjustment->id,
        ]);

        $component
            ->call('openModal', $deposit->id)
            ->set('load_balance', '200')
            ->call('adminProcessDeposit')
            ->assertHasNoErrors();

        $this->assertSame('250.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaDepositAdjustment::count());
        $this->assertSame(2, BrahmaBalanceTransaction::where('type', 'credit')->count());
    }

    public function test_multiple_load_corrections_each_create_a_separate_audited_delta(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $deposit = $this->pendingDeposit($player, 20);
        $this->verifyDeposit($agent, $deposit, 20);

        $this->correctDeposit($admin, $deposit, 200)->assertHasNoErrors();
        $this->correctDeposit($admin, $deposit, 180)->assertHasNoErrors();
        $this->correctDeposit($admin, $deposit, 190)->assertHasNoErrors();

        $this->assertSame('190.00', $player->fresh()->brahma_balance);
        $this->assertSame('190.00', $deposit->fresh()->load_balance);
        $this->assertSame([180.0, -20.0, 10.0], BrahmaDepositAdjustment::orderBy('id')->pluck('delta')->map(fn ($delta) => (float) $delta)->all());
        $this->assertSame(3, BrahmaBalanceTransaction::where('source_type', BrahmaDepositAdjustment::class)->count());
    }

    public function test_upward_correction_preserves_balance_spent_after_original_credit(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $game = $this->game();
        $deposit = $this->pendingDeposit($player, 20);

        $this->verifyDeposit($agent, $deposit, 20);
        $play = $this->pendingPlay($player, $game, 10);
        $this->verifyPlay($agent, $play);
        $this->assertSame('10.00', $player->fresh()->brahma_balance);

        $this->correctDeposit($admin, $deposit, 200)->assertHasNoErrors();

        $this->assertSame('190.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'debit')->count());
        $this->assertSame('verified', $play->fresh()->status);
    }

    public function test_downward_correction_applies_delta_and_blocks_a_negative_balance(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $deposit = $this->pendingDeposit($player, 100);

        $this->verifyDeposit($agent, $deposit, 250);
        $this->correctDeposit($admin, $deposit, 200)->assertHasNoErrors();

        $this->assertSame('200.00', $player->fresh()->brahma_balance);
        $this->assertDatabaseHas('brahma_deposit_adjustments', [
            'brahma_deposit_id' => $deposit->id,
            'delta' => -50,
        ]);
        $this->assertDatabaseHas('brahma_balance_transactions', [
            'type' => 'debit',
            'amount' => 50,
            'source_type' => BrahmaDepositAdjustment::class,
        ]);

        $player->forceFill(['brahma_balance' => 10])->save();
        $this->correctDeposit($admin, $deposit, 100)
            ->assertHasErrors(['load_balance']);

        $this->assertSame('10.00', $player->fresh()->brahma_balance);
        $this->assertSame('200.00', $deposit->fresh()->load_balance);
        $this->assertSame(1, BrahmaDepositAdjustment::count());
    }

    public function test_agent_cannot_use_admin_load_correction_operation(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $deposit = $this->pendingDeposit($player, 20);
        $this->verifyDeposit($admin, $deposit, 20);

        Livewire::actingAs($agent)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', '200')
            ->call('adminProcessDeposit')
            ->assertForbidden();

        $this->assertSame('20.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaDepositAdjustment::count());
    }

    public function test_brahma_player_notification_actions_use_catalog_and_verified_play_action_is_unchanged(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 100])->save();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $deposit = $this->pendingDeposit($player, 20);
        $this->verifyDeposit($agent, $deposit, 20);

        $loaded = Notification::where('user_id', $player->id)->where('type', 'brahma_balance_loaded')->firstOrFail();
        $this->assertSame('Play Now', $loaded->action_text);
        $this->assertSame(route('games'), $loaded->action_url);

        $game = $this->game();
        Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('pointsToLoad', '10')
            ->call('submitBrahmaPlay')
            ->assertHasNoErrors();

        $play = BrahmaPlayRequest::latest('id')->firstOrFail();
        $submitted = Notification::where('user_id', $player->id)->where('type', 'brahma_play_submitted')->firstOrFail();
        $this->assertSame('Play More Games', $submitted->action_text);
        $this->assertSame(route('games'), $submitted->action_url);

        $this->verifyPlay($agent, $play);
        $verified = Notification::where('user_id', $player->id)->where('type', 'brahma_play_verified')->firstOrFail();
        $this->assertSame('Play', $verified->action_text);
        $this->assertSame('https://example.com/play', $verified->action_url);

        Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->assertSee('Play Now')
            ->assertSee('Play More Games');
    }

    public function test_shared_normal_deposit_modal_renders_identity_and_processing_remains_available(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $game = $this->game();
        $deposit = Deposit::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'wallet_type' => 'cashapp',
            'amount' => 25,
            'proof_image' => 'normal-proof.jpg',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminDeposits::class)
            ->call('openModal', $deposit->id)
            ->assertSee('Player Name: ' . $player->name)
            ->assertSee('Player Username: ' . $player->username);

        Livewire::actingAs($agent)
            ->test(AdminDeposits::class)
            ->call('openModal', $deposit->id)
            ->assertSee('Player Name: ' . $player->name)
            ->assertSee('Player Username: ' . $player->username)
            ->set('status', 'verified')
            ->set('game_username', 'normal-user')
            ->set('game_password', 'normal-pass')
            ->set('game_points_loaded', '25')
            ->call('processDeposit')
            ->assertHasNoErrors();

        $this->assertSame('verified', $deposit->fresh()->status);
        $this->assertDatabaseHas('game_accounts', [
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'normal-user',
        ]);
    }

    public function test_shared_cashout_modal_renders_identity_and_player_qr_preview_without_changing_processing(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $game = $this->game();
        $account = GameAccount::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'cashout-user',
            'game_password' => 'cashout-pass',
            'created_by' => $agent->id,
        ]);
        $cashout = Cashout::create([
            'reference' => 'CASH-FINAL1',
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_account_id' => $account->id,
            'amount' => 30,
            'wallet_type' => 'cashapp',
            'wallet_address' => '$player',
            'qr_image' => 'cashout-qr/player.png',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminCashouts::class)
            ->call('openModal', $cashout->id)
            ->assertSee('Player Name:')
            ->assertSee($player->name)
            ->assertSee('Player Username:')
            ->assertSee($player->username)
            ->assertSee('Player QR');

        Livewire::actingAs($agent)
            ->test(AdminCashouts::class)
            ->call('openModal', $cashout->id)
            ->assertSee('Player Name:')
            ->assertSee($player->name)
            ->assertSee('Player Username:')
            ->assertSee($player->username)
            ->assertSee('Player QR')
            ->assertSeeHtml('Player cashout QR code')
            ->call('openProof', $cashout->qr_image)
            ->assertSet('proofPreview', $cashout->qr_image)
            ->assertSeeHtml('max-h-[calc(100vh-4rem)]')
            ->call('closeProof')
            ->assertSet('proofPreview', null)
            ->set('status', 'rejected')
            ->call('processCashout')
            ->assertHasNoErrors();

        $this->assertSame('rejected', $cashout->fresh()->status);
        $this->assertSame('cashout-qr/player.png', $cashout->fresh()->qr_image);
    }

    public function test_admin_and_agent_verification_debit_exact_stored_play_points_and_never_edit_them(): void
    {
        $adminPlayer = $this->userWithRole('player');
        $adminPlayer->forceFill(['brahma_balance' => 150])->save();
        $agentPlayer = $this->userWithRole('player');
        $agentPlayer->forceFill(['brahma_balance' => 150])->save();
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $game = $this->game();
        $adminPlay = $this->pendingPlay($adminPlayer, $game, 100);
        $agentPlay = $this->pendingPlay($agentPlayer, $game, 100);

        $this->assertFalse(property_exists(BrahmaPlays::class, 'points_to_load'));

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $adminPlay->id)
            ->assertSee('Points to Load: 100.00')
            ->assertDontSeeHtml('wire:model="points_to_load"')
            ->set('status', 'verified')
            ->set('game_username', 'admin-game-user')
            ->set('game_password', 'admin-game-pass')
            ->call('adminProcessPlay')
            ->assertHasNoErrors();

        Livewire::actingAs($agent)
            ->test(BrahmaPlays::class)
            ->call('openModal', $agentPlay->id)
            ->assertDontSeeHtml('wire:model="points_to_load"')
            ->set('status', 'verified')
            ->set('game_username', 'agent-game-user')
            ->set('game_password', 'agent-game-pass')
            ->call('processPlay')
            ->assertHasNoErrors();

        $this->assertSame('100.00', $adminPlay->fresh()->points_to_load);
        $this->assertSame('100.00', $agentPlay->fresh()->points_to_load);
        $this->assertSame('50.00', $adminPlayer->fresh()->brahma_balance);
        $this->assertSame('50.00', $agentPlayer->fresh()->brahma_balance);
        $this->assertSame(2, BrahmaBalanceTransaction::where('type', 'debit')->where('amount', 100)->count());

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $adminPlay->id)
            ->set('game_username', 'corrected-user')
            ->set('game_password', 'corrected-pass')
            ->call('adminProcessPlay')
            ->assertHasNoErrors();

        $this->assertSame('50.00', $adminPlayer->fresh()->brahma_balance);
        $this->assertSame(2, BrahmaBalanceTransaction::where('type', 'debit')->where('amount', 100)->count());
    }

    private function verifyDeposit(User $processor, BrahmaDeposit $deposit, float $loadBalance): void
    {
        Livewire::actingAs($processor)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'verified')
            ->set('load_balance', (string) $loadBalance)
            ->call('processDeposit')
            ->assertHasNoErrors();
    }

    private function correctDeposit(User $admin, BrahmaDeposit $deposit, float $loadBalance)
    {
        return Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', (string) $loadBalance)
            ->call('adminProcessDeposit');
    }

    private function verifyPlay(User $processor, BrahmaPlayRequest $play): void
    {
        Livewire::actingAs($processor)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'verified-user')
            ->set('game_password', 'verified-pass')
            ->call('processPlay')
            ->assertHasNoErrors();
    }

    private function pendingDeposit(User $player, float $amount): BrahmaDeposit
    {
        return BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => $amount,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);
    }

    private function pendingPlay(User $player, Game $game, float $points): BrahmaPlayRequest
    {
        return BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => $points,
            'balance_at_submission' => $player->fresh()->brahma_balance ?? 0,
            'status' => 'pending',
        ]);
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
            'name' => 'Final Correction Game',
            'slug' => 'final-correction-' . fake()->unique()->numberBetween(1000, 9999),
            'image' => 'games/test.jpg',
            'game_url' => 'example.com/play',
            'is_active' => true,
        ]);
    }
}
