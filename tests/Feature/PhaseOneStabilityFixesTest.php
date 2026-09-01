<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Pages\BrahmaBalance;
use App\Livewire\Pages\Notifications as PlayerNotifications;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaDepositAdjustment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneStabilityFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_initial_load_must_equal_or_exceed_the_player_submitted_amount(): void
    {
        $player = $this->userWithRole('player');
        $agent = $this->userWithRole('agent');

        $invalid = $this->pendingDeposit($player, 50);
        $this->processDeposit($agent, $invalid, 49)
            ->assertHasErrors(['load_balance']);

        $this->assertSame('pending', $invalid->fresh()->status);
        $this->assertSame('0.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaBalanceTransaction::count());

        $equal = $this->pendingDeposit($player, 50);
        $this->processDeposit($agent, $equal, 50)->assertHasNoErrors();
        $this->assertSame('50.00', $player->fresh()->brahma_balance);

        $greater = $this->pendingDeposit($player, 50);
        $this->processDeposit($agent, $greater, 60)->assertHasNoErrors();
        $this->assertSame('110.00', $player->fresh()->brahma_balance);
    }

    public function test_admin_correction_cannot_fall_below_deposit_and_invalid_attempt_has_no_effect(): void
    {
        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $deposit = $this->pendingDeposit($player, 50);
        $this->processDeposit($admin, $deposit, 60, true)->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', 49)
            ->call('adminProcessDeposit')
            ->assertHasErrors(['load_balance']);

        $this->assertSame('60.00', $deposit->fresh()->load_balance);
        $this->assertSame('60.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaDepositAdjustment::count());
        $this->assertSame(1, BrahmaBalanceTransaction::count());
    }

    public function test_nonzero_admin_correction_notifies_player_with_delta_and_actual_resulting_balance(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 100])->save();
        $admin = $this->userWithRole('admin');
        $deposit = $this->pendingDeposit($player, 50);
        $this->processDeposit($admin, $deposit, 50, true)->assertHasNoErrors();

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', 110)
            ->call('adminProcessDeposit')
            ->assertHasNoErrors();

        $notification = Notification::where('user_id', $player->id)
            ->where('type', 'brahma_balance_adjusted')
            ->firstOrFail();

        $this->assertSame('210.00', $player->fresh()->brahma_balance);
        $this->assertStringContainsString('additional $60.00', $notification->message);
        $this->assertStringContainsString('current Brahma Balance is $210.00', $notification->message);
        $this->assertSame('Play Now', $notification->action_text);
        $this->assertSame(route('games'), $notification->action_url);

        Livewire::actingAs($admin)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('load_balance', 110)
            ->call('adminProcessDeposit')
            ->assertHasNoErrors();

        $this->assertSame(1, Notification::where('type', 'brahma_balance_adjusted')->count());
        $this->assertSame(1, BrahmaDepositAdjustment::count());
    }

    public function test_notification_action_marks_unread_and_redirects_in_the_same_call_and_read_actions_still_redirect(): void
    {
        $player = $this->userWithRole('player');
        $notification = Notification::create([
            'user_id' => $player->id,
            'type' => 'brahma_balance_loaded',
            'title' => 'Brahma Balance Loaded',
            'message' => 'Ready to play.',
            'action_text' => 'Play Now',
            'action_url' => route('games'),
        ]);

        Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->call('openNotification', $notification->id)
            ->assertRedirect(route('games'));

        $this->assertTrue((bool) $notification->fresh()->is_read);

        Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->call('openNotification', $notification->id)
            ->assertRedirect(route('games'));

        $this->assertNotSame(url('/'), $notification->action_url);

        $rejection = Notification::create([
            'user_id' => $player->id,
            'type' => 'brahma_deposit_rejected',
            'title' => 'Deposit Rejected',
            'message' => 'Please review the notification.',
            'action_text' => 'Got It',
            'action_url' => route('player.notifications'),
            'is_read' => true,
        ]);

        Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->call('openNotification', $rejection->id)
            ->assertRedirect(route('player.notifications'));
    }

    public function test_role_logins_redirect_to_panels_while_player_destination_is_unchanged(): void
    {
        $admin = $this->userWithRole('admin');
        Volt::test('pages.auth.login')
            ->set('form.login', $admin->username)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('admin.dashboard'));

        auth()->logout();
        $agent = $this->userWithRole('agent');
        Volt::test('pages.auth.login')
            ->set('form.login', $agent->username)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('agent.dashboard'));

        auth()->logout();
        $player = $this->userWithRole('player');
        Volt::test('pages.auth.login')
            ->set('form.login', $player->username)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('home', absolute: false));
    }

    public function test_invalid_login_remains_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        Volt::test('pages.auth.login')
            ->set('form.login', $admin->username)
            ->set('form.password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['form.login'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_balance_component_refreshes_the_current_database_value_without_loading_wallets(): void
    {
        $player = $this->userWithRole('player');
        $player->forceFill(['brahma_balance' => 10])->save();

        $component = Livewire::actingAs($player)->test(BrahmaBalance::class)
            ->assertSee('$10.00')
            ->assertSeeHtml('wire:poll.10s.visible="refreshBalance"');

        $player->forceFill(['brahma_balance' => 25])->save();

        $component->call('refreshBalance')->assertSee('$25.00');
        $this->assertFalse($component->get('showModal'));
        $this->assertMatchesRegularExpression('/<template x-teleport="body">\s*<div>/', $component->html());
        $this->assertStringNotContainsString('window.location.reload', $component->html());
    }

    private function processDeposit(User $processor, BrahmaDeposit $deposit, float $loadBalance, bool $admin = false)
    {
        return Livewire::actingAs($processor)
            ->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->set('status', 'verified')
            ->set('load_balance', $loadBalance)
            ->call($admin ? 'adminProcessDeposit' : 'processDeposit');
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . fake()->unique()->numberBetween(1000, 9999),
            'role' => $role,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
