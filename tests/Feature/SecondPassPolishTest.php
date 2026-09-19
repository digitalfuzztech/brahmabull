<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\FloatingAlertCenter;
use App\Livewire\Player\PlayerSupportChat;
use App\Livewire\Player\SpecialOffers;
use App\Models\BrahmaDeposit;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\Notification;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecondPassPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_admin_pending_deposits_combines_normal_and_brahma_pending_records(): void
    {
        $admin = $this->user('admin');
        $player = $this->user('player');
        $game = Game::create(['name' => 'Dashboard Count Game', 'image' => 'game.png', 'is_active' => true]);

        Deposit::create([
            'user_id' => $player->id,
            'game_id' => $game->id,
            'wallet_type' => 'test',
            'amount' => 25,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);
        BrahmaDeposit::create([
            'user_id' => $player->id,
            'wallet_type' => 'test',
            'wallet_name' => 'Test Wallet',
            'wallet_account_identifier' => 'wallet-1',
            'amount' => 50,
            'proof_image' => 'proof.jpg',
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertViewHas('pendingDeposits', 2)
            ->assertSee('Pending Deposits');
    }

    public function test_player_notification_floating_alert_ignores_external_action_url(): void
    {
        $player = $this->user('player');
        $component = Livewire::actingAs($player)->test(FloatingAlertCenter::class);

        Notification::create([
            'user_id' => $player->id,
            'type' => 'deposit_verified',
            'title' => 'Deposit Verified',
            'message' => 'Your deposit was verified.',
            'action_text' => 'Play Game',
            'action_url' => 'https://external-game.example/play',
            'is_read' => false,
        ]);

        $component->call('pollAlerts')->assertCount('alerts', 1);
        $key = $component->get('alerts')[0]['key'];

        $component->call('open', $key)
            ->assertRedirect(route('player.notifications'));
    }

    public function test_offers_and_support_components_dispatch_and_handle_exclusive_open_events(): void
    {
        $player = $this->user('player');
        SpecialOffer::create([
            'name' => 'Exclusive Offer',
            'description' => 'Event coordination test offer.',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addDay(),
            'is_active' => true,
        ]);

        Livewire::actingAs($player)->test(SpecialOffers::class)
            ->call('toggleList')
            ->assertSet('listOpen', true)
            ->assertDispatched('bb:offers-opened')
            ->dispatch('bb:support-opened')
            ->assertSet('listOpen', false)
            ->assertSet('selectedOfferId', null);

        Livewire::actingAs($player)->test(PlayerSupportChat::class)
            ->set('isOpen', true)
            ->dispatch('bb:offers-opened')
            ->assertSet('isOpen', false);
    }

    public function test_parallax_invite_dropdown_and_celebration_hooks_are_present(): void
    {
        $home = file_get_contents(resource_path('views/livewire/public/home-page.blade.php'));
        $script = file_get_contents(resource_path('js/home-parallax.js'));
        $styles = file_get_contents(resource_path('css/app.css'));
        $invite = file_get_contents(resource_path('views/livewire/public/hero-carousel.blade.php'));
        $header = file_get_contents(resource_path('views/components/public-header.blade.php'));
        $bell = file_get_contents(resource_path('views/livewire/pages/notification-bell.blade.php'));
        $offers = file_get_contents(resource_path('views/livewire/player/special-offers.blade.php'));
        $teamBell = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $supportBell = file_get_contents(resource_path('views/livewire/admin/support-messenger-bell.blade.php'));

        $this->assertStringContainsString('data-bb-rules-parallax', $home);
        $this->assertStringContainsString("querySelector('[data-bb-rules-parallax]')", $script);
        $this->assertStringContainsString("--bb-rules-parallax-y", $script);
        $this->assertStringContainsString('extraRate = desktop ? 1', $script);
        $this->assertStringNotContainsString('data-bb-about-parallax', $home);
        $this->assertStringNotContainsString('data-bb-about-stage', $home);
        $this->assertStringNotContainsString('data-bb-about', $script);
        $this->assertStringNotContainsString('aboutTargetY', $script);
        $this->assertStringNotContainsString('--bb-about-parallax-y', $styles);
        $this->assertStringNotContainsString('.bb-about-parallax-layer', $styles);
        $this->assertStringContainsString("@teleport('body')", $invite);
        $this->assertStringContainsString('bb-invite-overlay-root', $invite);
        $this->assertStringContainsString('bb-invite-backdrop fixed inset-0 z-0', $invite);
        $this->assertStringContainsString('background: transparent;', $styles);
        $this->assertStringContainsString('blur(16px) brightness(0.58) saturate(0.78)', $styles);
        $this->assertStringContainsString("document.body.classList.toggle('overflow-hidden', value)", $invite);
        $this->assertStringContainsString("copy('{{ auth()->user()->referral_code }}')", $invite);
        $this->assertStringContainsString('bb:player-notifications-opened', $header);
        $this->assertStringContainsString('bb:player-profile-opened', $bell);
        $this->assertStringContainsString('bb-offer-celebration', $offers);
        $this->assertStringContainsString('aria-label="Close Messenger"', $teamBell);
        $this->assertStringContainsString('aria-label="Close Support Messages"', $supportBell);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role' => $role,
            'username' => $role.fake()->unique()->numberBetween(1000, 9999),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
