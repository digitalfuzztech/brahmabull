<?php

namespace Tests\Feature;

use App\Livewire\Admin\PlayersAll;
use App\Livewire\Admin\TopPlayers;
use App\Livewire\Agent\Dashboard as AgentDashboard;
use App\Livewire\Pages\BrahmaBalance;
use App\Livewire\Player\ProfilePage;
use App\Models\BrahmaDeposit;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneRefinementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_brahma_deposit_modal_is_teleported_and_viewport_constrained(): void
    {
        $player = $this->userWithRole('player');

        Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->call('openModal')
            ->assertSeeHtml('x-teleport="body"')
            ->assertSeeHtml('max-h-[calc(100vh-2rem)]')
            ->assertSee('Brahma Balance Deposit');
    }

    public function test_player_profile_displays_balance_and_both_verified_deposit_histories(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 125.50])->save();
        $game = $this->game();
        $normal = $this->normalDeposit($player, $game, 25, 'verified');
        $brahma = $this->brahmaDeposit($player, 50, 'verified', 65);
        $pendingBrahma = $this->brahmaDeposit($player, 75, 'pending');

        Livewire::actingAs($player)
            ->test(ProfilePage::class)
            ->assertSee('$125.50')
            ->assertSee('Game Deposits')
            ->assertSee($normal->reference)
            ->assertSee('Brahma Balance Deposits')
            ->assertSee($brahma->reference)
            ->assertSee('$50.00')
            ->assertSee('$65.00')
            ->assertDontSee($pendingBrahma->reference);
    }

    public function test_agent_dashboard_combines_records_by_existing_verified_by_semantics_without_date_filter(): void
    {
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $player = $this->userWithRole('player');
        $game = $this->game();

        $normal = $this->normalDeposit($player, $game, 10, 'rejected', $agent);
        $normal->forceFill(['created_at' => now()->subYear()])->saveQuietly();
        $brahma = $this->brahmaDeposit($player, 20, 'verified', 200, $agent);
        $this->normalDeposit($player, $game, 30, 'verified', $otherAgent);

        $test = Livewire::actingAs($agent)->test(AgentDashboard::class);
        $deposits = $test->instance()->getDepositsProperty();

        $this->assertCount(2, $deposits);
        $this->assertSame(30.0, (float) $deposits->sum('amount'));

        $test
            ->assertSee('Deposits Verified')
            ->assertSee($normal->reference)
            ->assertSee($brahma->reference)
            ->assertSee('Game Deposit')
            ->assertSee('Brahma Balance Deposit');
    }

    public function test_admin_and_agent_players_views_show_balance_and_all_status_combined_detail_totals(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 125.50])->save();
        $game = $this->game();

        $this->normalDeposit($player, $game, 10, 'verified');
        $this->normalDeposit($player, $game, 25, 'pending');
        $this->brahmaDeposit($player, 50, 'verified', 500);
        $this->brahmaDeposit($player, 100, 'pending');

        foreach ([$admin, $agent] as $viewer) {
            Livewire::actingAs($viewer)
                ->test(PlayersAll::class)
                ->assertSee('Brahma Balance')
                ->assertSee('$125.50')
                ->call('openPlayer', $player->id)
                ->assertSee('Total Deposits: 4')
                ->assertSee('Total Deposit Amount: $185.00');
        }
    }

    public function test_top_players_combines_only_verified_amounts_and_does_not_use_load_balance(): void
    {
        $admin = $this->userWithRole('admin');
        $player = $this->userWithRole('player');
        $game = $this->game();

        $this->normalDeposit($player, $game, 10, 'verified', $admin, 12);
        $this->normalDeposit($player, $game, 25, 'pending');
        $this->brahmaDeposit($player, 50, 'verified', 500, $admin);
        $this->brahmaDeposit($player, 100, 'pending');

        $row = Livewire::actingAs($admin)
            ->test(TopPlayers::class)
            ->instance()
            ->getTopTenProperty()
            ->first(fn ($ranking) => $ranking->player->is($player));

        $this->assertNotNull($row);
        $this->assertSame(2, $row->deposit_count);
        $this->assertSame(60.0, $row->deposit_total);
        $this->assertSame(12.0, $row->points_used);
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
            'name' => 'Refinement Game',
            'slug' => 'refinement-game-' . fake()->unique()->numberBetween(1000, 9999),
            'image' => 'games/test.jpg',
            'game_url' => 'example.com/play',
            'is_active' => true,
        ]);
    }

    private function normalDeposit(
        User $player,
        Game $game,
        float $amount,
        string $status,
        ?User $verifier = null,
        ?float $points = null,
    ): Deposit {
        return Deposit::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'wallet_type' => 'cashapp',
            'amount' => $amount,
            'proof_image' => 'normal-proof.jpg',
            'status' => $status,
            'verified_by' => $verifier?->id,
            'verified_at' => $status === 'verified' ? now() : null,
            'game_points_loaded' => $points,
        ]);
    }

    private function brahmaDeposit(
        User $player,
        float $amount,
        string $status,
        ?float $loadBalance = null,
        ?User $verifier = null,
    ): BrahmaDeposit {
        return BrahmaDeposit::create([
            'user_id' => $player->id,
            'wallet_type' => 'CashApp',
            'wallet_name' => 'Main Wallet',
            'wallet_account_identifier' => '$main',
            'amount' => $amount,
            'proof_image' => 'brahma-proof.jpg',
            'status' => $status,
            'load_balance' => $loadBalance,
            'verified_by' => $verifier?->id,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);
    }
}
