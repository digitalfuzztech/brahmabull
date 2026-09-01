<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Admin\BrahmaPlays;
use App\Livewire\Pages\BrahmaBalance;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneStabilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_global_brahma_balance_polling_is_visible_only_and_uses_a_persistent_teleport_root(): void
    {
        $player = $this->userWithRole('player');
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $component = Livewire::actingAs($player)->test(BrahmaBalance::class);

        $component->assertSeeHtml('wire:poll.10s.visible="refreshBalance"');
        $this->assertMatchesRegularExpression('/<template x-teleport="body">\s*<div>/', $component->html());
        $this->assertFalse(collect($queries)->contains(fn ($sql) => str_contains($sql, 'from "wallets"')));
    }

    public function test_valid_authenticated_role_routes_resolve_without_homepage_redirects(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');

        $this->actingAs($player)->get(route('games'))->assertOk();
        $this->actingAs($admin)->get(route('admin.brahma.deposits'))->assertOk();
        $this->actingAs($agent)->get(route('agent.brahma.plays'))->assertOk();
    }

    public function test_admin_can_set_initial_load_without_changing_player_submitted_amount(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $deposit = BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => 40,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'verified')
            ->set('load_balance', '60')
            ->call('adminProcessDeposit')
            ->assertHasNoErrors();

        $this->assertSame('40.00', $deposit->fresh()->amount);
        $this->assertSame('60.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'credit')->count());

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', '70')
            ->set('admin_notes', 'Reporting correction')
            ->call('adminProcessDeposit')
            ->assertHasNoErrors();

        $this->assertSame('40.00', $deposit->fresh()->amount);
        $this->assertSame('70.00', $deposit->fresh()->load_balance);
        $this->assertSame('70.00', $player->fresh()->brahma_balance);
        $this->assertSame(2, BrahmaBalanceTransaction::where('type', 'credit')->count());
    }

    public function test_applied_deposit_status_is_immutable(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $deposit = $this->creditedDeposit($player, $admin, 60);

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'rejected')
            ->call('adminProcessDeposit')
            ->assertHasErrors(['status']);

        $this->assertSame('60.00', $deposit->fresh()->load_balance);
        $this->assertSame('60.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'credit')->count());
    }

    public function test_agent_cannot_call_admin_deposit_edit_operation(): void
    {
        $player = $this->userWithRole('player');
        $agent = $this->userWithRole('agent');
        $deposit = BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => 40,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);

        Livewire::actingAs($agent)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->call('adminProcessDeposit')
            ->assertForbidden();
    }

    public function test_admin_verifies_stored_play_points_and_can_correct_credentials_without_another_debit(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 100])->save();
        $admin = $this->userWithRole('admin');
        $game = $this->game();
        $play = $this->pendingPlay($player, $game, 10);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'first-user')
            ->set('game_password', 'first-pass')
            ->call('adminProcessPlay')
            ->assertHasNoErrors();

        $this->assertSame('10.00', $play->fresh()->points_to_load);
        $this->assertSame('90.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'debit')->count());

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('game_username', 'corrected-user')
            ->set('game_password', 'corrected-pass')
            ->call('adminProcessPlay')
            ->assertHasNoErrors();

        $this->assertSame('90.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'debit')->count());
        $this->assertDatabaseHas('game_accounts', [
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'corrected-user',
            'game_password' => 'corrected-pass',
        ]);
    }

    public function test_stored_play_points_are_immutable_and_balance_is_rechecked_before_debit(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 20])->save();
        $admin = $this->userWithRole('admin');
        $game = $this->game();
        $play = $this->pendingPlay($player, $game, 30);

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'user')
            ->set('game_password', 'pass')
            ->call('adminProcessPlay')
            ->assertHasErrors(['status']);

        $this->assertSame('20.00', $player->fresh()->brahma_balance);
        $this->assertSame('pending', $play->fresh()->status);
        $this->assertSame(0, BrahmaBalanceTransaction::where('type', 'debit')->count());

        $player->forceFill(['brahma_balance' => 100])->save();
        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'verified')
            ->set('game_username', 'user')
            ->set('game_password', 'pass')
            ->call('adminProcessPlay')
            ->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->set('status', 'rejected')
            ->set('rejection_note', 'No reversal mechanism')
            ->call('adminProcessPlay')
            ->assertHasErrors(['status']);

        $this->assertSame('30.00', $play->fresh()->points_to_load);
        $this->assertSame('70.00', $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('type', 'debit')->count());
    }

    public function test_agent_cannot_call_admin_play_edit_operation(): void
    {
        $player = $this->userWithRole('player');
        $agent = $this->userWithRole('agent');
        $play = $this->pendingPlay($player, $this->game(), 5);

        Livewire::actingAs($agent)
            ->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->call('adminProcessPlay')
            ->assertForbidden();
    }

    private function creditedDeposit(User $player, User $admin, float $amount): BrahmaDeposit
    {
        $player->forceFill(['brahma_balance' => $amount])->save();
        $deposit = BrahmaDeposit::create([
            'user_id' => $player->id,
            'amount' => $amount,
            'proof_image' => 'proof.jpg',
            'status' => 'verified',
            'load_balance' => $amount,
            'balance_before_credit' => 0,
            'balance_after_credit' => $amount,
            'processed_by' => $admin->id,
            'verified_by' => $admin->id,
            'processed_at' => now(),
            'verified_at' => now(),
            'credited_at' => now(),
        ]);

        BrahmaBalanceTransaction::create([
            'user_id' => $player->id,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'source_type' => BrahmaDeposit::class,
            'source_id' => $deposit->id,
            'performed_by' => $admin->id,
            'description' => 'Test credit',
        ]);

        return $deposit;
    }

    private function pendingPlay(User $player, Game $game, float $points): BrahmaPlayRequest
    {
        return BrahmaPlayRequest::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'points_to_load' => $points,
            'balance_at_submission' => $player->brahma_balance ?? 0,
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
            'name' => 'Stabilization Game',
            'slug' => 'stabilization-' . fake()->unique()->numberBetween(1000, 9999),
            'image' => 'games/test.jpg',
            'game_url' => 'example.com/play',
            'is_active' => true,
        ]);
    }
}
