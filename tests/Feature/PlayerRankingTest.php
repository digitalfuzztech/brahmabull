<?php

namespace Tests\Feature;

use App\Livewire\Admin\PlayerRankSettings;
use App\Livewire\Public\TopWinners;
use App\Models\Cashout;
use App\Models\Game;
use App\Models\GameAccount;
use App\Models\PlayerRankEntry;
use App\Models\PlayerRankSetting;
use App\Models\User;
use App\Services\PlayerRankingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRankingTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_rank_settings_route_is_admin_only(): void
    {
        $this->actingAs($this->user('admin'))->get(route('admin.player-rank-settings'))->assertOk();
        $this->actingAs($this->user('agent'))->get(route('admin.player-rank-settings'))->assertForbidden();
        $this->actingAs($this->user('player'))->get(route('admin.player-rank-settings'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.player-rank-settings'))->assertRedirect(route('login'));
    }

    public function test_component_rejects_non_admin_access(): void
    {
        Livewire::actingAs($this->user('agent'))
            ->test(PlayerRankSettings::class)
            ->assertForbidden();
    }

    public function test_manual_entries_save_independently_for_every_period(): void
    {
        $component = Livewire::actingAs($this->user('admin'))
            ->test(PlayerRankSettings::class)
            ->assertSet('mode', 'manual')
            ->set('manualRows.last_7_days.0.player_name', 'weekly-player')
            ->set('manualRows.last_7_days.0.wins', '10.25')
            ->set('manualRows.this_month.1.player_name', 'monthly-player')
            ->set('manualRows.this_month.1.wins', '20.50')
            ->set('manualRows.all_time.9.player_name', 'all-time-player')
            ->set('manualRows.all_time.9.wins', '30.75')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Player rank settings saved.');

        $this->assertDatabaseHas('player_rank_entries', ['period' => 'last_7_days', 'rank' => 1, 'player_name' => 'weekly-player', 'wins' => 10.25]);
        $this->assertDatabaseHas('player_rank_entries', ['period' => 'this_month', 'rank' => 2, 'player_name' => 'monthly-player', 'wins' => 20.50]);
        $this->assertDatabaseHas('player_rank_entries', ['period' => 'all_time', 'rank' => 10, 'player_name' => 'all-time-player', 'wins' => 30.75]);
        $this->assertSame(range(1, 10), collect($component->get('manualRows')['last_7_days'])->keys()->map(fn (int $index) => $index + 1)->all());
    }

    public function test_manual_rows_require_complete_nonnegative_decimal_values(): void
    {
        $admin = $this->user('admin');

        Livewire::actingAs($admin)
            ->test(PlayerRankSettings::class)
            ->set('manualRows.last_7_days.0.player_name', 'missing-wins')
            ->call('save')
            ->assertHasErrors(['manualRows.last_7_days.0.wins']);

        Livewire::actingAs($admin)
            ->test(PlayerRankSettings::class)
            ->set('manualRows.last_7_days.0.wins', '12.00')
            ->call('save')
            ->assertHasErrors(['manualRows.last_7_days.0.player_name']);

        Livewire::actingAs($admin)
            ->test(PlayerRankSettings::class)
            ->set('manualRows.last_7_days.0.player_name', 'negative')
            ->set('manualRows.last_7_days.0.wins', '-0.01')
            ->call('save')
            ->assertHasErrors(['manualRows.last_7_days.0.wins']);

        $this->assertDatabaseCount('player_rank_entries', 0);
    }

    public function test_database_prevents_duplicate_period_rank_entries(): void
    {
        PlayerRankEntry::create(['period' => 'last_7_days', 'rank' => 1, 'player_name' => 'first', 'wins' => '10.00']);

        $this->expectException(QueryException::class);

        PlayerRankEntry::create(['period' => 'last_7_days', 'rank' => 1, 'player_name' => 'second', 'wins' => '20.00']);
    }

    public function test_mode_switching_preserves_and_restores_manual_rows(): void
    {
        $admin = $this->user('admin');

        Livewire::actingAs($admin)
            ->test(PlayerRankSettings::class)
            ->set('manualRows.last_7_days.0.player_name', 'preserved-player')
            ->set('manualRows.last_7_days.0.wins', '44.00')
            ->call('save')
            ->set('mode', 'automatic')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('automatic', PlayerRankSetting::findOrFail(1)->mode);
        $this->assertDatabaseHas('player_rank_entries', ['period' => 'last_7_days', 'rank' => 1, 'player_name' => 'preserved-player']);

        Livewire::actingAs($admin)
            ->test(PlayerRankSettings::class)
            ->assertSet('manualRows.last_7_days.0.player_name', 'preserved-player')
            ->set('mode', 'manual')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('manual', PlayerRankSetting::findOrFail(1)->mode);
        $this->assertSame('preserved-player', app(PlayerRankingService::class)->leaderboard('last_7_days')[0]['player_name']);
    }

    public function test_blank_manual_rows_are_hidden_and_saving_is_financially_read_only(): void
    {
        $admin = $this->user('admin');
        PlayerRankEntry::create(['period' => 'last_7_days', 'rank' => 2, 'player_name' => null, 'wins' => null]);
        $tables = ['users', 'cashouts', 'deposits', 'brahma_deposits', 'brahma_play_requests', 'brahma_balance_transactions', 'spin_wheel_spins', 'notifications'];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        Livewire::actingAs($admin)
            ->test(PlayerRankSettings::class)
            ->set('manualRows.last_7_days.0.player_name', 'display-only')
            ->set('manualRows.last_7_days.0.wins', '99.99')
            ->call('save')
            ->assertHasNoErrors();

        $after = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->assertSame($before->all(), $after->all());
        $this->assertSame([1], collect(app(PlayerRankingService::class)->manualLeaderboard('last_7_days'))->pluck('rank')->all());
    }

    public function test_automatic_rankings_ignore_unverified_statuses_sum_paid_cashouts_and_order_players(): void
    {
        $this->automaticMode();
        $now = Carbon::parse('2026-09-10 12:00:00', config('app.timezone'));
        $first = $this->user('player', 'winner-a');
        $second = $this->user('player', 'winner-b');

        foreach (['50.00', '100.00', '25.00'] as $amount) {
            $this->cashout($first, $amount, 'paid', $now->copy()->subDay());
        }

        $this->cashout($second, '120.00', 'paid', $now->copy()->subDay());
        $this->cashout($second, '9999.00', 'pending', null);
        $this->cashout($second, '8888.00', 'rejected', $now->copy()->subDay());
        $this->cashout($second, '7777.00', 'approved', $now->copy()->subDay());

        $rows = app(PlayerRankingService::class)->automaticLeaderboard('last_7_days', $now);

        $this->assertSame([
            ['rank' => 1, 'player_name' => 'winner-a', 'wins' => '175.00'],
            ['rank' => 2, 'player_name' => 'winner-b', 'wins' => '120.00'],
        ], $rows);
    }

    public function test_automatic_rankings_return_only_ten_real_eligible_players(): void
    {
        $this->automaticMode();
        $now = now();

        foreach (range(1, 12) as $index) {
            $this->cashout($this->user('player', 'rank-'.$index), (string) ($index * 10), 'paid', $now);
        }

        $rows = app(PlayerRankingService::class)->automaticLeaderboard('all_time', $now);

        $this->assertCount(10, $rows);
        $this->assertSame('rank-12', $rows[0]['player_name']);
        $this->assertSame(range(1, 10), collect($rows)->pluck('rank')->all());
    }

    public function test_automatic_rankings_do_not_fabricate_missing_players(): void
    {
        $this->automaticMode();
        $this->cashout($this->user('player', 'only-winner'), '12.00', 'paid', now());

        $rows = app(PlayerRankingService::class)->automaticLeaderboard('all_time');

        $this->assertCount(1, $rows);
        $this->assertSame('only-winner', $rows[0]['player_name']);
    }

    public function test_last_seven_days_uses_paid_at_with_an_inclusive_rolling_boundary(): void
    {
        $this->automaticMode();
        $now = Carbon::parse('2026-09-10 15:30:00', config('app.timezone'));
        $included = $this->user('player', 'boundary-winner');
        $excluded = $this->user('player', 'old-winner');

        $this->cashout($included, '40.00', 'paid', $now->copy()->subDays(7));
        $this->cashout($excluded, '500.00', 'paid', $now->copy()->subDays(7)->subSecond());

        $rows = app(PlayerRankingService::class)->automaticLeaderboard('last_7_days', $now);

        $this->assertSame(['boundary-winner'], collect($rows)->pluck('player_name')->all());
    }

    public function test_this_month_excludes_previous_month_while_all_time_includes_it(): void
    {
        $this->automaticMode();
        $now = Carbon::parse('2026-09-10 12:00:00', config('app.timezone'));
        $current = $this->user('player', 'current-month');
        $previous = $this->user('player', 'previous-month');

        $this->cashout($current, '25.00', 'paid', $now->copy()->startOfMonth());
        $this->cashout($previous, '75.00', 'paid', $now->copy()->startOfMonth()->subSecond());

        $service = app(PlayerRankingService::class);
        $monthRows = $service->automaticLeaderboard('this_month', $now);
        $allRows = $service->automaticLeaderboard('all_time', $now);

        $this->assertSame(['current-month'], collect($monthRows)->pluck('player_name')->all());
        $this->assertSame(['previous-month', 'current-month'], collect($allRows)->pluck('player_name')->all());
    }

    public function test_automatic_amount_aggregation_is_decimal_safe(): void
    {
        $this->automaticMode();
        $player = $this->user('player', 'decimal-winner');
        $this->cashout($player, '0.10', 'paid', now());
        $this->cashout($player, '0.20', 'paid', now());

        $this->assertSame('0.30', app(PlayerRankingService::class)->automaticLeaderboard('all_time')[0]['wins']);
    }

    public function test_public_leaderboard_exposes_username_and_no_private_player_fields(): void
    {
        $this->automaticMode();
        $player = $this->user('player', 'public-winner');
        $player->update(['name' => 'Private Full Name', 'phone' => '555-private']);
        $this->cashout($player, '32.10', 'paid', now());

        Livewire::test(TopWinners::class)
            ->assertSet('period', 'last_7_days')
            ->assertSee('public-winner')
            ->assertSee('$32.10')
            ->assertDontSee($player->email)
            ->assertDontSee('555-private')
            ->assertDontSee('Private Full Name');
    }

    private function automaticMode(): void
    {
        PlayerRankSetting::query()->whereKey(1)->update(['mode' => 'automatic']);
    }

    private function user(string $role, ?string $username = null): User
    {
        $this->sequence++;
        $username ??= $role.'-'.$this->sequence;
        $user = User::factory()->create([
            'name' => 'User '.$this->sequence,
            'username' => $username,
            'phone' => '555-'.$this->sequence,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function cashout(User $player, string $amount, string $status, ?Carbon $paidAt): Cashout
    {
        $this->sequence++;
        $game = Game::create([
            'name' => 'Rank Game '.$this->sequence,
            'image' => 'rank-game.png',
            'is_active' => true,
        ]);
        $account = GameAccount::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_username' => 'game-user-'.$this->sequence,
        ]);

        return Cashout::create([
            'reference' => 'RANK-'.$this->sequence,
            'user_id' => $player->id,
            'game_id' => $game->id,
            'game_account_id' => $account->id,
            'amount' => $amount,
            'wallet_type' => 'CashApp',
            'wallet_address' => '$rank-'.$this->sequence,
            'status' => $status,
            'verified_at' => $status === 'paid' ? $paidAt : null,
            'paid_at' => $paidAt,
        ]);
    }
}
