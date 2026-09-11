<?php

namespace Tests\Feature;

use App\Livewire\Admin\FloatingAlertCenter;
use App\Livewire\Admin\Notifications as AdminNotifications;
use App\Livewire\Pages\Notifications as PlayerNotifications;
use App\Models\Notification;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationNavigationUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_player_search_matches_title_message_and_structured_reference_without_leaking_other_players(): void
    {
        $player = $this->userWithRole('player');
        $other = $this->userWithRole('player');
        $title = $this->notification($player, 'deposit_submitted', 'Special Deposit Notice', 'Ordinary message');
        $message = $this->notification($player, 'deposit_verified', 'Deposit Verified', 'Neon Tiger game is ready.');
        $reference = $this->notification($player, 'cashout_paid', 'Withdrawal Paid', 'Payment completed.', [
            'reference' => 'CASH-REF-7788',
        ]);
        $this->notification($other, 'deposit_verified', 'Special Deposit Notice', 'Neon Tiger CASH-REF-7788');

        $component = Livewire::actingAs($player)->test(PlayerNotifications::class);

        $component->set('search', 'Special Deposit');
        $this->assertSame([$title->id], $component->get('notifications')->getCollection()->pluck('id')->all());

        $component->set('search', 'Neon Tiger');
        $this->assertSame([$message->id], $component->get('notifications')->getCollection()->pluck('id')->all());

        $component->set('search', 'CASH-REF-7788');
        $this->assertSame([$reference->id], $component->get('notifications')->getCollection()->pluck('id')->all());

        $component->set('search', 'does-not-exist');
        $this->assertCount(0, $component->get('notifications')->getCollection());
    }

    public function test_player_deposit_category_and_all_include_the_complete_lifecycle(): void
    {
        $player = $this->userWithRole('player');
        $received = $this->notification($player, 'deposit_submitted', 'Deposit Received', 'Reference DEP-ONE');
        $processed = $this->notification($player, 'deposit_verified', 'Deposit Processed', 'Reference DEP-TWO');
        $rejected = $this->notification($player, 'deposit_rejected', 'Deposit Rejected', 'Reference DEP-THREE');
        $cashout = $this->notification($player, 'cashout_submitted', 'Cashout Received', 'Reference CASH-ONE');

        $component = Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->set('type', 'deposit');

        $this->assertEqualsCanonicalizing(
            [$received->id, $processed->id, $rejected->id],
            $component->get('notifications')->getCollection()->pluck('id')->all()
        );

        $component->set('type', '');
        $this->assertEqualsCanonicalizing(
            [$received->id, $processed->id, $rejected->id, $cashout->id],
            $component->get('notifications')->getCollection()->pluck('id')->all()
        );
    }

    public function test_player_search_category_read_state_and_pagination_compose(): void
    {
        $player = $this->userWithRole('player');

        foreach (range(1, 31) as $index) {
            $this->notification($player, 'cashout_submitted', 'Cashout '.$index, 'Pagination noise '.$index);
        }

        $match = $this->notification($player, 'deposit_rejected', 'Deposit Rejected', 'Unique lifecycle needle', [], true);
        $this->notification($player, 'deposit_verified', 'Deposit Verified', 'Unique lifecycle needle', [], false);

        $component = Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->call('setPage', 2)
            ->set('search', 'Unique lifecycle needle')
            ->set('type', 'deposit')
            ->set('readStatus', '1');

        $this->assertSame([$match->id], $component->get('notifications')->getCollection()->pluck('id')->all());
        $this->assertSame(1, $component->get('notifications')->currentPage());
    }

    public function test_admin_search_keeps_text_search_and_adds_title_and_data_references(): void
    {
        $admin = $this->userWithRole('admin');
        $otherAdmin = $this->userWithRole('admin');
        $titleReference = $this->notification($admin, 'deposit_admin', 'Deposit [DEP-ADMIN-4422] processed', 'Processed for a player.');
        $messageMatch = $this->notification($admin, 'cashout_admin', 'Cashout processed', 'Player River Song was paid.');
        $dataReference = $this->notification($admin, 'cashout_paid', 'Withdrawal Paid', 'Payment complete.', [
            'reference' => 'CASH-ADMIN-9911',
        ]);
        $this->notification($otherAdmin, 'deposit_admin', 'Deposit [DEP-ADMIN-4422] processed', 'Player River Song');

        $component = Livewire::actingAs($admin)->test(AdminNotifications::class);

        $component->set('search', 'River Song');
        $this->assertSame([$messageMatch->id], $component->get('notifications')->getCollection()->pluck('id')->all());

        $component->set('search', 'DEP-ADMIN-4422');
        $this->assertSame([$titleReference->id], $component->get('notifications')->getCollection()->pluck('id')->all());

        $component->set('search', 'CASH-ADMIN-9911');
        $this->assertSame([$dataReference->id], $component->get('notifications')->getCollection()->pluck('id')->all());
    }

    public function test_admin_lifecycle_categories_include_received_processed_and_rejected_types(): void
    {
        $admin = $this->userWithRole('admin');
        $received = $this->notification($admin, 'deposit_created', 'Deposit Received', 'Received');
        $processed = $this->notification($admin, 'deposit_admin', 'Deposit Processed', 'Processed');
        $rejected = $this->notification($admin, 'deposit_rejected', 'Deposit Rejected', 'Rejected');
        $other = $this->notification($admin, 'cashout_created', 'Cashout Received', 'Received');

        $component = Livewire::actingAs($admin)
            ->test(AdminNotifications::class)
            ->set('type', 'deposit');

        $this->assertEqualsCanonicalizing(
            [$received->id, $processed->id, $rejected->id],
            $component->get('notifications')->getCollection()->pluck('id')->all()
        );
        $this->assertNotContains($other->id, $component->get('notifications')->getCollection()->pluck('id')->all());
    }

    public function test_got_it_acknowledges_in_place_without_redirecting_or_changing_filters(): void
    {
        $player = $this->userWithRole('player');
        $notification = $this->notification(
            $player,
            'deposit_rejected',
            'Deposit Rejected',
            'Please review this result.',
            [],
            false,
            route('player.notifications')
        );

        Livewire::actingAs($player)
            ->test(PlayerNotifications::class)
            ->set('search', 'review this result')
            ->set('type', 'deposit')
            ->set('readStatus', '0')
            ->assertSeeHtml('wire:click="acknowledge('.$notification->id.')"')
            ->call('acknowledge', $notification->id)
            ->assertNoRedirect()
            ->assertSet('search', 'review this result')
            ->assertSet('type', 'deposit')
            ->assertSet('readStatus', '0');

        $this->assertTrue((bool) $notification->fresh()->is_read);
    }

    public function test_floating_notification_alert_preserves_internal_and_external_destinations(): void
    {
        $player = $this->userWithRole('player');
        $internal = $this->notification($player, 'deposit_rejected', 'Rejected', 'Review it.', [], false, route('player.notifications'));
        $external = $this->notification($player, 'deposit_verified', 'Game Ready', 'Play now.', [], false, 'https://games.example.test/play');

        $internalKey = 'notification:'.$internal->id;
        Livewire::actingAs($player)
            ->test(FloatingAlertCenter::class)
            ->set('alerts', [$this->floatingAlert($internalKey, $internal->id)])
            ->call('open', $internalKey)
            ->assertRedirect(route('player.notifications'));

        $externalKey = 'notification:'.$external->id;
        Livewire::actingAs($player)
            ->test(FloatingAlertCenter::class)
            ->set('alerts', [$this->floatingAlert($externalKey, $external->id)])
            ->call('open', $externalKey)
            ->assertRedirect('https://games.example.test/play');

        $this->assertTrue((bool) $internal->fresh()->is_read);
        $this->assertTrue((bool) $external->fresh()->is_read);
    }

    public function test_header_and_modal_markup_keep_dynamic_brand_and_viewport_safe_layers(): void
    {
        SiteSetting::query()->findOrFail(1)->update(['site_name' => 'BrahmaBull International Gaming Club']);
        $player = $this->userWithRole('player');

        $this->actingAs($player)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('BrahmaBull International Gaming Club')
            ->assertSeeLivewire('pages.notification-bell');

        $messenger = file_get_contents(resource_path('views/livewire/admin/messenger-bell.blade.php'));
        $support = file_get_contents(resource_path('views/livewire/admin/support-messenger-bell.blade.php'));
        $games = file_get_contents(resource_path('views/livewire/pages/games.blade.php'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('fixed left-1/2 top-20', $messenger);
        $this->assertStringContainsString('md:absolute', $messenger);
        $this->assertStringContainsString('fixed left-1/2 top-20', $support);
        $this->assertStringContainsString('md:absolute', $support);
        $this->assertStringContainsString("\$showModal || \$showWalletPreview ? 'z-[1000]'", $games);
        $this->assertStringContainsString('z-[1001]', $games);
        $this->assertStringContainsString("document.getElementById('preloader')", $javascript);
        $this->assertStringNotContainsString("const preloader = document.getElementById('preloader')", $javascript);
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

    private function notification(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = [],
        bool $isRead = false,
        ?string $actionUrl = null,
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_text' => 'Got It',
            'action_url' => $actionUrl,
            'is_read' => $isRead,
            'data' => $data ?: null,
        ]);
    }

    private function floatingAlert(string $key, int $notificationId): array
    {
        return [
            'key' => $key,
            'kind' => 'notification',
            'title' => 'Notification',
            'subtitle' => 'New notification',
            'preview' => 'Preview',
            'conversation_id' => null,
            'notification_id' => $notificationId,
        ];
    }
}
