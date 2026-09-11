<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrahmaPlays;
use App\Livewire\Admin\Deposits as AdminDeposits;
use App\Livewire\Admin\FloatingAlertCenter;
use App\Livewire\Admin\SpinningWheel;
use App\Livewire\Player\ProfilePage;
use App\Livewire\Player\SpinWheel;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\BrahmaPlayRequest;
use App\Models\Cashout;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\Notification;
use App\Models\SpinAttemptGrant;
use App\Models\SpinPromotionalPointLedger;
use App\Models\SpinRewardEntitlement;
use App\Models\SpinWheelAssignment;
use App\Models\SpinWheelOffer;
use App\Models\SpinWheelOfferType;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpin;
use App\Models\User;
use App\Services\Spin\SpinBonusService;
use App\Services\Spin\SpinCategoryProbabilityService;
use App\Services\Spin\SpinEligibilityService;
use App\Services\Spin\SpinOfferService;
use App\Services\Spin\SpinRewardService;
use App\Services\Spin\SpinStatisticsService;
use App\Services\Spin\SpinWheelService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpinWheelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_launcher_is_player_only(): void
    {
        $player = $this->user('player');
        $this->actingAs($player)->get(route('home'))->assertOk()->assertSee('data-spin-attempt-badge', false);
        $this->actingAs($this->user('admin'))->get(route('home'))->assertDontSee('data-spin-attempt-badge', false);
        $this->actingAs($this->user('agent'))->get(route('home'))->assertDontSee('data-spin-attempt-badge', false);
        auth()->logout();
        $this->get(route('home'))->assertDontSee('data-spin-attempt-badge', false);
    }

    public function test_verified_transition_replenishes_three_and_is_idempotent(): void
    {
        $player = $this->user('player');
        $event = $this->deposit($player, 'pending');
        $this->assertSame(0, app(SpinEligibilityService::class)->available($player));
        $event->update(['status' => 'rejected']);
        $this->assertDatabaseCount('spin_attempt_grants', 0);
        $event->update(['status' => 'verified']);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($player));
        $this->assertDatabaseHas('spin_attempt_grants', [
            'source_type' => BrahmaDeposit::class, 'source_id' => $event->id,
            'attempts_granted' => 3, 'attempts_remaining' => 3,
        ]);
        $event->update(['admin_notes' => 'safe re-save']);
        $this->assertDatabaseCount('spin_attempt_grants', 1);
        Livewire::actingAs($player)->test(SpinWheel::class)->assertSet('availableSpins', 3);
    }

    public function test_normal_deposits_only_replenish_spins_at_the_ten_dollar_threshold(): void
    {
        $game = Game::create(['name' => 'Deposit Threshold', 'image' => 'test.png', 'is_active' => true]);

        $below = $this->user('player');
        $belowDeposit = $this->normalDeposit($below, $game, '9.99', 'pending');
        $belowDeposit->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(0, app(SpinEligibilityService::class)->available($below));
        $this->assertDatabaseMissing('spin_attempt_grants', ['source_type' => Deposit::class, 'source_id' => $belowDeposit->id]);

        $exact = $this->user('player');
        $exactDeposit = $this->normalDeposit($exact, $game, '10.00', 'pending');
        $exactDeposit->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($exact));
        $this->assertDatabaseHas('spin_attempt_grants', [
            'source_type' => Deposit::class, 'source_id' => $exactDeposit->id,
            'attempts_granted' => 3, 'attempts_remaining' => 3,
        ]);

        $above = $this->user('player');
        $this->grant($above, 2);
        $aboveDeposit = $this->normalDeposit($above, $game, '10.01', 'pending');
        $aboveDeposit->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($above));
        $this->assertDatabaseHas('spin_attempt_grants', [
            'source_type' => Deposit::class, 'source_id' => $aboveDeposit->id,
            'attempts_granted' => 1, 'attempts_remaining' => 1,
        ]);

        $rejected = $this->user('player');
        $rejectedDeposit = $this->normalDeposit($rejected, $game, '100.00', 'pending');
        $this->assertSame(0, app(SpinEligibilityService::class)->available($rejected));
        $rejectedDeposit->update(['status' => 'rejected']);
        $this->assertSame(0, app(SpinEligibilityService::class)->available($rejected));

        $exactDeposit->update(['admin_notes' => 'idempotent reprocessing']);
        app(SpinEligibilityService::class)->grantForVerifiedEvent($exactDeposit->fresh(), $exact);
        $this->assertSame(1, SpinAttemptGrant::where('source_type', Deposit::class)->where('source_id', $exactDeposit->id)->count());
    }

    public function test_brahma_deposits_use_deposited_amount_instead_of_load_balance_for_spin_eligibility(): void
    {
        $below = $this->user('player');
        $belowDeposit = BrahmaDeposit::create([
            'user_id' => $below->id, 'amount' => '9.99', 'load_balance' => '20.00',
            'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        $belowDeposit->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(0, app(SpinEligibilityService::class)->available($below));
        $this->assertDatabaseMissing('spin_attempt_grants', ['source_type' => BrahmaDeposit::class, 'source_id' => $belowDeposit->id]);

        $exact = $this->user('player');
        $exactDeposit = BrahmaDeposit::create([
            'user_id' => $exact->id, 'amount' => '10.00', 'load_balance' => '10.00',
            'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        $exactDeposit->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($exact));

        $corrected = $this->user('player');
        $correctedDeposit = BrahmaDeposit::create([
            'user_id' => $corrected->id, 'amount' => '20.00', 'load_balance' => '5.00',
            'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        $correctedDeposit->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($corrected));

        $rejected = $this->user('player');
        $rejectedDeposit = BrahmaDeposit::create([
            'user_id' => $rejected->id, 'amount' => '50.00', 'load_balance' => '50.00',
            'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        $rejectedDeposit->update(['status' => 'rejected']);
        $this->assertSame(0, app(SpinEligibilityService::class)->available($rejected));

        app(SpinEligibilityService::class)->grantForVerifiedEvent($exactDeposit->fresh(), $exact);
        $this->assertSame(1, SpinAttemptGrant::where('source_type', BrahmaDeposit::class)->where('source_id', $exactDeposit->id)->count());
    }

    public function test_second_event_replenishes_to_three_and_refresh_and_login_preserve_usage(): void
    {
        $player = $this->user('player');
        $this->deposit($player, 'pending')->update(['status' => 'verified']);
        SpinAttemptGrant::where('user_id', $player->id)->update(['attempts_remaining' => 1]);
        $this->deposit($player, 'pending')->update(['status' => 'verified']);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($player));
        $this->deposit($player, 'pending')->update(['status' => 'verified']);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($player));
        SpinAttemptGrant::where('user_id', $player->id)->orderBy('id')->firstOrFail()->decrement('attempts_remaining');
        Livewire::actingAs($player)->test(SpinWheel::class)->call('refreshAttempts')->assertSet('availableSpins', 2);
        auth()->logout();
        Livewire::actingAs($player->fresh())->test(SpinWheel::class)->assertSet('availableSpins', 2);
    }

    public function test_verified_brahma_play_requests_each_add_one_spin_and_remain_idempotent(): void
    {
        $game = Game::create(['name' => 'Spin Test', 'image' => 'test.png', 'is_active' => true]);

        $player = $this->user('player');
        $play = $this->brahmaPlay($player, $game, 'pending');
        $play->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(1, app(SpinEligibilityService::class)->available($player));
        $this->assertDatabaseHas('spin_attempt_grants', [
            'source_type' => BrahmaPlayRequest::class, 'source_id' => $play->id,
            'attempts_granted' => 1, 'attempts_remaining' => 1,
        ]);
        $play->update(['game_username' => 'unchanged-reward-state']);
        app(SpinEligibilityService::class)->grantForVerifiedEvent($play->fresh(), $player);
        $this->assertSame(1, SpinAttemptGrant::where('source_type', BrahmaPlayRequest::class)->where('source_id', $play->id)->count());

        $playerWithTwo = $this->user('player');
        $this->grant($playerWithTwo, 2);
        $this->brahmaPlay($playerWithTwo, $game, 'pending')->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(3, app(SpinEligibilityService::class)->available($playerWithTwo));

        $playerAboveMaximum = $this->user('player');
        $this->grant($playerAboveMaximum, 5);
        $this->brahmaPlay($playerAboveMaximum, $game, 'pending')->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(6, app(SpinEligibilityService::class)->available($playerAboveMaximum));

        $multiple = $this->user('player');
        $this->brahmaPlay($multiple, $game, 'pending')->update(['status' => 'verified', 'verified_at' => now()]);
        $this->brahmaPlay($multiple, $game, 'pending')->update(['status' => 'verified', 'verified_at' => now()]);
        $this->assertSame(2, app(SpinEligibilityService::class)->available($multiple));

        $rejected = $this->user('player');
        $this->brahmaPlay($rejected, $game, 'pending')->update(['status' => 'rejected']);
        $this->assertSame(0, app(SpinEligibilityService::class)->available($rejected));
    }

    public function test_free_spin_rewards_are_additive_above_the_verified_event_limit(): void
    {
        [$player] = $this->configuredReward('free_spin', '2');
        $this->grant($player, 1);
        $spin = app(SpinWheelService::class)->spin($player, '11111111-1111-4111-8111-111111111111');
        $this->assertSame(2, app(SpinEligibilityService::class)->available($player));
        $this->assertDatabaseHas('spin_reward_entitlements', ['spin_id' => $spin->id, 'entitlement_type' => 'free_spin', 'numeric_value' => 2]);

        SpinWheelAssignment::query()->delete();
        [, $offer] = $this->configuredReward('free_spin', '3', $player);
        $this->assignment($offer, 'featured', 2);
        $this->grant($player, 2);
        app(SpinWheelService::class)->spin($player, '22222222-2222-4222-8222-222222222222');
        $this->assertSame(6, app(SpinEligibilityService::class)->available($player));
    }

    public function test_new_registration_gets_exactly_one_onboarding_spin(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Onboarding Player')
            ->set('email', 'onboarding@example.test')
            ->set('username', 'onboarding-player')
            ->set('password', 'Secure1!Pass')
            ->set('password_confirmation', 'Secure1!Pass')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('registered', true);

        $player = User::where('email', 'onboarding@example.test')->firstOrFail();
        $eligibility = app(SpinEligibilityService::class);
        $this->assertSame(1, $eligibility->available($player));
        $this->assertDatabaseHas('spin_attempt_grants', [
            'user_id' => $player->id,
            'source_type' => SpinEligibilityService::ONBOARDING_SOURCE,
            'source_id' => $player->id,
            'attempts_granted' => 1,
            'attempts_remaining' => 1,
        ]);

        $eligibility->grantOnboarding($player);
        $this->actingAs($player)->get(route('home'))->assertOk();
        $this->actingAs($player)->get(route('home'))->assertOk();
        $this->assertSame(1, $eligibility->available($player));
        SpinAttemptGrant::where('user_id', $player->id)->decrement('attempts_remaining');
        $this->assertSame(0, $eligibility->available($player));
        $this->deposit($player, 'pending')->update(['status' => 'verified']);
        $this->assertSame(3, $eligibility->available($player));
        $this->assertSame(1, SpinAttemptGrant::where('source_type', SpinEligibilityService::ONBOARDING_SOURCE)->where('source_id', $player->id)->count());
    }

    public function test_verified_events_top_up_to_three_without_reducing_reward_spins(): void
    {
        foreach ([0 => 3, 1 => 3, 2 => 3, 3 => 3, 5 => 5, 7 => 7] as $current => $expected) {
            $player = $this->user('player');
            if ($current > 0) {
                $this->grant($player, $current);
            }
            $event = $this->deposit($player, 'pending');
            $event->update(['status' => 'verified']);
            $this->assertSame($expected, app(SpinEligibilityService::class)->available($player));
            $event->update(['admin_notes' => 'duplicate save']);
            $this->assertSame($expected, app(SpinEligibilityService::class)->available($player));
        }
    }

    public function test_free_spin_sequence_stacks_three_to_seven_and_has_no_global_cap(): void
    {
        [$player, $offer] = $this->configuredReward('free_spin', '3');
        $this->grant($player, 3);
        app(SpinWheelService::class)->spin($player, '21212121-2121-4212-8212-212121212121');
        $this->assertSame(5, app(SpinEligibilityService::class)->available($player));

        $offer->update(['display_value' => '1']);
        app(SpinWheelService::class)->spin($player, '23232323-2323-4232-8232-232323232323');
        $this->assertSame(5, app(SpinEligibilityService::class)->available($player));

        $offer->update(['display_value' => '3']);
        app(SpinWheelService::class)->spin($player, '24242424-2424-4242-8242-242424242424');
        $this->assertSame(7, app(SpinEligibilityService::class)->available($player));

        $anotherPlayer = $this->user('player');
        $this->grant($anotherPlayer, 10);
        $source = SpinWheelSpin::create([
            'request_token' => '25252525-2525-4252-8252-252525252525', 'user_id' => $anotherPlayer->id,
            'spin_number' => 1, 'wheel_number' => 101, 'wheel_slot' => 1, 'offer_id' => $offer->id,
            'offer_snapshot_name' => '+3 Free Spins', 'offer_snapshot_value' => '3',
            'offer_snapshot_type' => 'free_spin', 'offer_snapshot_category' => 'free_spin',
            'offer_snapshot_score' => 0, 'status' => 'awarded', 'spun_at' => now(),
        ]);
        app(SpinEligibilityService::class)->grantFreeSpins($source, $anotherPlayer, 3);
        $this->assertSame(13, app(SpinEligibilityService::class)->available($anotherPlayer));
    }

    public function test_offer_types_are_limited_slugged_and_agent_denied(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $service = app(SpinOfferService::class);
        SpinWheelAssignment::query()->delete();
        SpinWheelOffer::query()->delete();
        SpinWheelOfferType::query()->delete();
        $first = $service->saveType($admin, null, ['name' => 'VIP Badge', 'action_type' => 'badge', 'is_active' => true]);
        $duplicate = $service->saveType($admin, null, ['name' => 'VIP Badge', 'action_type' => 'badge', 'is_active' => true]);
        $this->assertSame('vip-badge', $first->slug);
        $this->assertSame('vip-badge-2', $duplicate->slug);
        foreach (range(3, 10) as $number) {
            $service->saveType($admin, null, ['name' => 'Type '.$number, 'action_type' => 'try_again', 'is_active' => true]);
        }
        $this->assertThrows(fn () => $service->saveType($admin, null, ['name' => 'Eleventh', 'action_type' => 'badge', 'is_active' => true]), ValidationException::class);
        $this->assertThrows(fn () => $service->saveType($agent, null, ['name' => 'Denied', 'action_type' => 'badge', 'is_active' => true]), AuthorizationException::class);
        Livewire::actingAs($agent)->test(SpinningWheel::class)->assertForbidden();
    }

    public function test_badge_value_is_optional_and_free_spin_value_is_one_to_three(): void
    {
        $admin = $this->user('admin');
        $service = app(SpinOfferService::class);
        $badge = $service->saveType($admin, null, ['name' => 'Badge', 'action_type' => 'badge', 'is_active' => true]);
        $offer = $service->saveOffer($admin, null, ['offer_type_id' => $badge->id, 'name' => 'VIP Badge', 'display_value' => null, 'is_active' => true]);
        $this->assertNull($offer->display_value);
        $free = $service->saveType($admin, null, ['name' => 'Free Spin', 'action_type' => 'free_spin', 'is_active' => true]);
        foreach (['0', '4', 'many'] as $invalid) {
            $this->assertThrows(fn () => $service->saveOffer($admin, null, ['offer_type_id' => $free->id, 'name' => 'Invalid', 'display_value' => $invalid, 'is_active' => true]), ValidationException::class);
        }
        foreach (['1', '2', '3'] as $valid) {
            $this->assertSame($valid, $service->saveOffer($admin, null, ['offer_type_id' => $free->id, 'name' => '+'.$valid.' Spin', 'display_value' => $valid, 'is_active' => true])->display_value);
        }
    }

    public function test_wheel_supports_exactly_ten_numeric_and_six_featured_slots(): void
    {
        [$player, $offer] = $this->configuredReward();
        SpinWheelAssignment::query()->delete();
        $service = app(SpinOfferService::class);
        $admin = User::role('admin')->firstOrFail();
        foreach (range(1, 10) as $position) {
            $service->saveAssignment($admin, null, ['slot_type' => 'numeric', 'slot_position' => $position, 'offer_id' => $offer->id, 'weight' => 1, 'is_active' => true]);
        }
        foreach (range(1, 6) as $position) {
            $service->saveAssignment($admin, null, ['slot_type' => 'featured', 'slot_position' => $position, 'offer_id' => $offer->id, 'display_label' => 'Featured '.$position, 'weight' => 1, 'is_active' => true]);
        }
        $this->assertSame(10, SpinWheelAssignment::where('slot_type', 'numeric')->count());
        $this->assertSame(6, SpinWheelAssignment::where('slot_type', 'featured')->count());
        $this->assertThrows(fn () => $service->saveAssignment($admin, null, ['slot_type' => 'featured', 'slot_position' => 7, 'offer_id' => $offer->id]), ValidationException::class);
        Livewire::actingAs($player)->test(SpinWheel::class)
            ->assertViewHas('visualSegments', fn ($segments) => $segments->count() === 16
                && $segments->where('type', 'numeric')->count() === 10
                && $segments->where('type', 'featured')->count() === 6);
    }

    public function test_server_selects_active_current_assignment_and_client_cannot_choose(): void
    {
        [$player, $offer] = $this->configuredReward();
        $this->grant($player);
        $component = Livewire::actingAs($player)->test(SpinWheel::class);
        $this->assertFalse(array_key_exists('outcome', get_object_vars($component->instance())));
        $component->call('spin')->assertDispatched('spin-wheel-result')
            ->assertSee('spin-result-card', false)
            ->assertSee('blur-md brightness-[.28]', false)
            ->assertSee('lg:h-[calc(100dvh-1.5rem)]', false)
            ->assertSee('Continue');
        $spin = SpinWheelSpin::firstOrFail();
        $this->assertSame($offer->id, $spin->offer_id);
        $this->assertSame(1, $spin->wheel_slot);

        SpinWheelSpin::query()->delete();
        $this->grant($player);
        $offer->update(['is_active' => false]);
        $this->assertThrows(fn () => app(SpinWheelService::class)->spin($player, '33333333-3333-4333-8333-333333333333'), ValidationException::class);
        $offer->update(['is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
        $this->assertThrows(fn () => app(SpinWheelService::class)->spin($player, '44444444-4444-4444-8444-444444444444'), ValidationException::class);
    }

    public function test_badge_entitlement_displays_and_expires(): void
    {
        [$player] = $this->configuredReward('badge', null, null, ['valid_days' => 3]);
        $this->grant($player);
        $spin = app(SpinWheelService::class)->spin($player, '55555555-5555-4555-8555-555555555555');
        $badge = SpinRewardEntitlement::where('spin_id', $spin->id)->firstOrFail();
        $this->assertSame('badge', $badge->entitlement_type);
        $this->assertTrue($badge->expires_at->between(now()->addDays(2), now()->addDays(4)));
        $this->actingAs($player)->get(route('home'))->assertSee('data-spin-player-badge', false)->assertSee('Test Reward');
        Livewire::actingAs($player)->test(ProfilePage::class)->set('activeTab', 'spin_wins')
            ->assertSee('data-spin-player-badge', false)->assertSee('Test Reward')->assertSee('Awarded');
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'spin_wheel_win', 'entity_id' => $spin->id]);
        $this->assertDatabaseMissing('notifications', ['type' => 'spin_wheel_staff', 'entity_id' => $spin->id]);
        $badge->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($player)->get(route('home'))->assertDontSee('data-spin-player-badge', false);
        Livewire::actingAs($player)->test(ProfilePage::class)->set('activeTab', 'spin_wins')
            ->assertDontSee('data-spin-player-badge', false)->assertSee('Badge')->assertSee('Awarded');
    }

    public function test_active_vip_badge_converts_a_badge_draw_to_a_try_again_landing_and_result(): void
    {
        $admin = $this->user('admin');
        [$player] = $this->configuredReward('badge', null, null, ['valid_days' => 5], $admin);
        $this->grant($player);
        $firstSpin = app(SpinWheelService::class)->spin($player, '56565656-5656-4565-8565-565656565656');
        $activeBadge = SpinRewardEntitlement::where('spin_id', $firstSpin->id)->firstOrFail();
        $originalExpiry = $activeBadge->expires_at->copy();

        $tryAgainType = SpinWheelOfferType::create([
            'name' => 'Try Again', 'slug' => 'try-again-active-vip', 'action_type' => 'try_again',
            'is_active' => true, 'created_by' => $admin->id,
        ]);
        $tryAgainOffer = SpinWheelOffer::create([
            'offer_type_id' => $tryAgainType->id, 'name' => 'Try Again', 'category' => 'try_again',
            'rarity_weight' => 1, 'ranking_score' => 0, 'is_active' => true, 'created_by' => $admin->id,
        ]);
        $tryAgainAssignment = $this->assignment($tryAgainOffer, 'numeric', 2);
        $this->grant($player);

        $notificationsBefore = Notification::where('user_id', $player->id)->where('type', 'spin_wheel_win')->count();
        $entitlementsBefore = SpinRewardEntitlement::where('user_id', $player->id)->count();
        $balanceBefore = (float) $player->fresh()->brahma_balance;

        Livewire::actingAs($player)->test(SpinWheel::class)
            ->call('spin')
            ->assertSet('result.name', 'Try Again')
            ->assertSet('result.type', 'try_again')
            ->assertSet('result.description', 'You already have a VIP badge valid until '.$originalExpiry->format('F j, Y g:i A').'.')
            ->assertDispatched('spin-wheel-result');

        $effectiveSpin = SpinWheelSpin::where('user_id', $player->id)->latest('id')->firstOrFail();
        $this->assertSame('try_again', $effectiveSpin->offer_snapshot_type);
        $this->assertSame('try_again', $effectiveSpin->offer_snapshot_category);
        $this->assertSame('Try Again', $effectiveSpin->offer_snapshot_name);
        $this->assertSame('Try Again', $effectiveSpin->offer_snapshot_metadata['display_label']);
        $this->assertSame(app(SpinOfferService::class)->visualSlot($tryAgainAssignment->slot_type, $tryAgainAssignment->slot_position), $effectiveSpin->wheel_slot);
        $this->assertSame($entitlementsBefore, SpinRewardEntitlement::where('user_id', $player->id)->count());
        $this->assertTrue($originalExpiry->equalTo($activeBadge->fresh()->expires_at));
        $this->assertSame($notificationsBefore, Notification::where('user_id', $player->id)->where('type', 'spin_wheel_win')->count());
        $this->assertFalse(app(SpinStatisticsService::class)->topWins()->whereKey($effectiveSpin->id)->exists());
        $this->assertSame($balanceBefore, (float) $player->fresh()->brahma_balance);
    }

    public function test_expired_vip_badge_allows_a_new_badge_award(): void
    {
        [$player] = $this->configuredReward('badge', null, null, ['valid_days' => 3]);
        $this->grant($player);
        $firstSpin = app(SpinWheelService::class)->spin($player, '57575757-5757-4575-8575-575757575757');
        $expiredBadge = SpinRewardEntitlement::where('spin_id', $firstSpin->id)->firstOrFail();
        $expiredBadge->update(['expires_at' => now()->subSecond()]);
        $this->grant($player);

        $secondSpin = app(SpinWheelService::class)->spin($player, '58585858-5858-4585-8585-585858585858');

        $this->assertSame('badge', $secondSpin->offer_snapshot_type);
        $this->assertDatabaseHas('spin_reward_entitlements', [
            'spin_id' => $secondSpin->id, 'user_id' => $player->id, 'entitlement_type' => 'badge',
        ]);
        $this->assertSame(2, SpinRewardEntitlement::where('user_id', $player->id)->where('entitlement_type', 'badge')->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $player->id, 'type' => 'spin_wheel_win', 'entity_id' => $secondSpin->id,
        ]);
        $this->assertTrue(app(SpinStatisticsService::class)->topWins()->whereKey($secondSpin->id)->exists());
    }

    public function test_sajilo_and_bonus_points_use_separate_idempotent_promotional_ledgers(): void
    {
        $this->user('agent');
        [$player] = $this->configuredReward('sajilo_points', '10');
        $player->forceFill(['brahma_balance' => 77])->save();
        $before = [Deposit::count(), Cashout::count(), $player->brahmaPlayRequests()->count()];
        $this->grant($player);
        $spin = app(SpinWheelService::class)->spin($player, '66666666-6666-4666-8666-666666666666');
        app(SpinRewardService::class)->award($spin, $spin->offer);
        $this->assertDatabaseHas('spin_promotional_point_ledgers', ['spin_id' => $spin->id, 'point_type' => 'sajilo_points', 'amount' => 10, 'balance_after' => 10]);
        $this->assertSame(1, SpinPromotionalPointLedger::where('spin_id', $spin->id)->count());
        $this->assertSame(87.0, (float) $player->fresh()->brahma_balance);
        $this->assertSame(1, BrahmaBalanceTransaction::where('source_type', SpinWheelSpin::class)->where('source_id', $spin->id)->where('type', 'credit')->count());
        $this->assertSame(1, Notification::where('entity_id', $spin->id)->count());

        SpinWheelAssignment::query()->delete();
        [, $bonus] = $this->configuredReward('bonus_points', '5', $player);
        $this->assignment($bonus, 'featured', 2);
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, '99999999-9999-4999-8999-999999999999');
        $this->assertSame(10, (int) SpinPromotionalPointLedger::where('user_id', $player->id)->where('point_type', 'sajilo_points')->sum('remaining_amount'));
        $this->assertSame(5, (int) SpinPromotionalPointLedger::where('user_id', $player->id)->where('point_type', 'bonus_points')->sum('remaining_amount'));
        $this->assertSame($before, [Deposit::count(), Cashout::count(), $player->brahmaPlayRequests()->count()]);
    }

    public function test_try_again_and_badge_do_not_create_staff_spin_notifications(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        [$player] = $this->configuredReward('try_again', admin: $admin);
        $this->grant($player);
        $service = app(SpinWheelService::class);
        $spin = $service->spin($player, '77777777-7777-4777-8777-777777777777');
        $service->spin($player, '77777777-7777-4777-8777-777777777777');
        $this->assertSame(0, app(SpinEligibilityService::class)->available($player));
        $this->assertDatabaseMissing('spin_reward_entitlements', ['spin_id' => $spin->id]);
        $this->assertDatabaseMissing('spin_promotional_point_ledgers', ['spin_id' => $spin->id]);
        $this->assertDatabaseMissing('spin_reward_claims', ['spin_id' => $spin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $player->id, 'type' => 'spin_wheel_win']);
        $this->assertSame(0, Notification::where('entity_id', $spin->id)->where('type', 'spin_wheel_staff')->count());

        SpinWheelAssignment::query()->delete();
        [, $badge] = $this->configuredReward('badge', null, $player, admin: $admin);
        $badge->update(['notify_staff' => true]);
        $this->assignment($badge, 'featured', 2);
        $this->grant($player);
        $badgeSpin = $service->spin($player, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id, 'type' => 'spin_wheel_staff']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $agent->id, 'type' => 'spin_wheel_staff']);
        $service->spin($player, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $this->assertSame(1, Notification::where('entity_id', $badgeSpin->id)->count());
    }

    public function test_category_probability_ranges_are_exact_and_total_one_hundred(): void
    {
        $ranges = app(SpinCategoryProbabilityService::class)->cumulativeRanges(SpinCategoryProbabilityService::DEFAULTS);
        $this->assertSame(['from' => 1, 'to' => 50, 'weight' => 50], $ranges['try_again']);
        $this->assertSame(['from' => 51, 'to' => 70, 'weight' => 20], $ranges['free_spin']);
        $this->assertSame(['from' => 71, 'to' => 80, 'weight' => 10], $ranges['bonus_points']);
        $this->assertSame(['from' => 81, 'to' => 90, 'weight' => 10], $ranges['sajilo_points']);
        $this->assertSame(['from' => 91, 'to' => 100, 'weight' => 10], $ranges['badge']);
        $this->assertSame(100, array_sum(SpinCategoryProbabilityService::DEFAULTS));
        $this->assertSame('badge', app(SpinCategoryProbabilityService::class)->categoryForTicket(SpinCategoryProbabilityService::DEFAULTS, 91));
        $this->assertSame('badge', app(SpinCategoryProbabilityService::class)->categoryForTicket(SpinCategoryProbabilityService::DEFAULTS, 100));
    }

    public function test_admin_tabs_and_offer_type_crud_use_livewire_state_transitions(): void
    {
        $admin = $this->user('admin');
        $component = Livewire::actingAs($admin)->test(SpinningWheel::class)
            ->assertSet('activeTab', 'types');

        foreach (['offers', 'assignments', 'wins', 'settings', 'types'] as $tab) {
            $component->call('setTab', $tab)->assertSet('activeTab', $tab);
        }

        $component->call('openTypeForm')->assertSet('showTypeForm', true)
            ->set('typeName', 'Codex UI Test')->set('typeAction', 'try_again')->call('saveType')
            ->assertHasNoErrors()->assertSet('showTypeForm', false)->assertSee('Offer Type created.');
        $type = SpinWheelOfferType::where('slug', 'codex-ui-test')->firstOrFail();

        $component->call('editType', $type->id)->assertSet('showTypeForm', true)
            ->assertSet('typeName', 'Codex UI Test')->set('typeName', 'Codex UI Test Edited')->call('saveType')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('spin_wheel_offer_types', ['id' => $type->id, 'name' => 'Codex UI Test Edited', 'slug' => 'codex-ui-test-edited']);

        $component->call('confirmDeleteType', $type->id)->assertSet('showTypeDeleteConfirm', true)
            ->call('cancelDeleteType')->assertSet('showTypeDeleteConfirm', false)
            ->call('confirmDeleteType', $type->id)->call('deleteConfirmedType')
            ->assertSet('showTypeDeleteConfirm', false)->assertSee('Offer Type deleted.');
        $this->assertDatabaseMissing('spin_wheel_offer_types', ['id' => $type->id]);

        $component->call('openTypeForm')->set('typeName', '')->call('saveType')->assertHasErrors('typeName');
        $component->set('typeName', 'Invalid')->set('typeAction', 'arbitrary_callback')->call('saveType')->assertHasErrors('typeAction');
    }

    public function test_admin_offer_type_delete_archives_types_with_dependencies(): void
    {
        $admin = $this->user('admin');
        [, $offer] = $this->configuredReward(admin: $admin);

        Livewire::actingAs($admin)->test(SpinningWheel::class)
            ->call('confirmDeleteType', $offer->offer_type_id)->call('deleteConfirmedType')
            ->assertSee('safely disabled');

        $this->assertDatabaseHas('spin_wheel_offer_types', ['id' => $offer->offer_type_id, 'is_active' => false]);
    }

    public function test_visible_reward_counters_remain_frozen_until_animation_finishes(): void
    {
        [$player] = $this->configuredReward('sajilo_points', '10');
        $this->grant($player, 3);
        $component = Livewire::actingAs($player)->test(SpinWheel::class)
            ->assertSet('availableSpins', 3)->assertSet('sajiloPoints', 0)->assertSet('bonusPoints', 0)
            ->call('spin')->assertSet('animationPending', true)
            ->assertSet('availableSpins', 3)->assertSet('sajiloPoints', 0)->assertSet('bonusPoints', 0)
            ->call('refreshState')->assertSet('availableSpins', 3)->assertSet('sajiloPoints', 0)
            ->call('animationFinished')->assertSet('animationPending', false)
            ->assertSet('availableSpins', 2)->assertSet('sajiloPoints', 10)->assertSet('bonusPoints', 0)
            ->assertDispatched('spin-wheel-reveal-state');
        $component->assertSee("TODAY'S WINS", false)->assertSee('Test Reward');
    }

    public function test_bonus_remains_pending_until_explicit_verified_source_fulfillment(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        [$player] = $this->configuredReward('bonus_points', '5', admin: $admin);
        $this->grant($player);
        $spin = app(SpinWheelService::class)->spin($player, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc');
        $entry = SpinPromotionalPointLedger::where('spin_id', $spin->id)->firstOrFail();
        $this->assertSame('pending', $entry->status);
        $this->assertSame(5, $entry->remaining_amount);

        $game = Game::create(['name' => 'Bonus Fulfillment', 'image' => 'test.png', 'is_active' => true]);
        $play = BrahmaPlayRequest::create(['user_id' => $player->id, 'game_id' => $game->id, 'points_to_load' => 10, 'balance_at_submission' => 10, 'status' => 'rejected']);
        $component = Livewire::actingAs($agent)->test(BrahmaPlays::class)
            ->call('openModal', $play->id)->assertSet('pendingPromotionalBonus', 5)
            ->assertSee('Pending Promotional Bonus')->assertSee('Informational only.')
            ->call('fulfillPromotionalBonus', $play->id)->assertHasErrors('bonus');
        $this->assertSame('pending', $entry->fresh()->status);

        $play->update(['status' => 'verified', 'verified_at' => now(), 'debited_at' => now()]);
        Livewire::actingAs($agent)->test(BrahmaPlays::class)->assertSee('Processed');
        $component->call('fulfillPromotionalBonus', $play->id)->assertHasNoErrors()->assertSet('pendingPromotionalBonus', 0);
        $entry->refresh();
        $this->assertSame('fulfilled', $entry->status);
        $this->assertSame(0, $entry->remaining_amount);
        $this->assertSame($agent->id, $entry->processed_by);
        $this->assertSame(BrahmaPlayRequest::class, $entry->fulfillment_source_type);
        $this->assertSame($play->id, $entry->fulfillment_source_id);
        $component->call('fulfillPromotionalBonus', $play->id)->assertHasErrors('bonus');
        $this->assertSame(1, Notification::where('type', 'spin_bonus_fulfilled')->where('entity_id', $play->id)->count());

        Livewire::actingAs($player)->test(ProfilePage::class)->set('activeTab', 'spin_wins')
            ->assertSee('Total Bonus Won')->assertSee('Total Bonus Awarded')->assertSee('Awarded');
    }

    public function test_deposit_bonus_reminder_aggregates_pending_entries_and_failed_or_rejected_sources_do_not_fulfill(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        [$player] = $this->configuredReward('bonus_points', '5', admin: $admin);
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, 'dddddddd-dddd-4ddd-8ddd-dddddddddddd');
        [, $secondOffer] = $this->configuredReward('bonus_points', '7', $player, admin: $admin);
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee');
        $this->assertSame(12, (int) SpinPromotionalPointLedger::where('user_id', $player->id)->where('status', 'pending')->sum('remaining_amount'));

        $game = Game::create(['name' => 'Deposit Bonus Test', 'image' => 'test.png', 'is_active' => true]);
        $deposit = Deposit::create(['user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'cashapp', 'amount' => 100, 'proof_image' => 'proof.jpg', 'status' => 'pending']);
        $component = Livewire::actingAs($agent)->test(AdminDeposits::class)->call('openModal', $deposit->id)
            ->assertSet('pendingPromotionalBonus', 12)->assertSee('Pending Promotional Bonus')->assertSee('112.00');

        try {
            DB::transaction(function () use ($deposit): void {
                $deposit->update(['status' => 'verified', 'verified_at' => now()]);
                throw new \RuntimeException('Force verification rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('pending', $deposit->fresh()->status);
        $component->call('fulfillPromotionalBonus', $deposit->id)->assertHasErrors('bonus');
        $deposit->update(['status' => 'rejected']);
        $component->call('fulfillPromotionalBonus', $deposit->id)->assertHasErrors('bonus');
        $this->assertSame(12, (int) SpinPromotionalPointLedger::where('user_id', $player->id)->sum('remaining_amount'));
        $this->assertDatabaseMissing('notifications', ['user_id' => $player->id, 'type' => 'spin_bonus_fulfilled']);

        $deposit->update(['status' => 'verified', 'verified_at' => now()]);
        $component->call('fulfillPromotionalBonus', $deposit->id)->assertHasNoErrors()->assertSet('pendingPromotionalBonus', 0);
        $this->assertSame(100.0, (float) $deposit->fresh()->amount);
        $this->assertSame(0, (int) SpinPromotionalPointLedger::where('user_id', $player->id)->sum('remaining_amount'));
        $this->assertSame(2, SpinPromotionalPointLedger::where('user_id', $player->id)->where('status', 'fulfilled')->count());
        $this->assertSame(1, Notification::where('user_id', $player->id)->where('type', 'spin_bonus_fulfilled')->count());
    }

    public function test_today_wins_are_day_scoped_while_profile_keeps_month_history_and_ledger_totals(): void
    {
        [$player, $offer] = $this->configuredReward('sajilo_points', '10');
        $yesterday = SpinWheelSpin::create([
            'request_token' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'user_id' => $player->id,
            'spin_number' => 1, 'wheel_number' => 101, 'wheel_slot' => 1, 'offer_id' => $offer->id,
            'offer_snapshot_name' => 'Yesterday Sajilo', 'offer_snapshot_value' => '10',
            'offer_snapshot_type' => 'sajilo_points', 'offer_snapshot_category' => 'sajilo_points',
            'offer_snapshot_score' => 10, 'status' => 'awarded', 'spun_at' => now()->subDay(),
        ]);
        $today = SpinWheelSpin::create([
            'request_token' => 'abababab-abab-4aba-8aba-abababababab', 'user_id' => $player->id,
            'spin_number' => 2, 'wheel_number' => 101, 'wheel_slot' => 1, 'offer_id' => $offer->id,
            'offer_snapshot_name' => 'Today Sajilo', 'offer_snapshot_value' => '10',
            'offer_snapshot_type' => 'sajilo_points', 'offer_snapshot_category' => 'sajilo_points',
            'offer_snapshot_score' => 10, 'status' => 'awarded', 'spun_at' => now(),
        ]);
        app(SpinRewardService::class)->award($yesterday, $offer);
        app(SpinRewardService::class)->award($today, $offer);

        Livewire::actingAs($player)->test(SpinWheel::class)
            ->assertSet('todayWins', fn ($wins) => count($wins) === 1 && $wins[0]['label'] === 'Today Sajilo')
            ->assertSee('Today Sajilo')->assertDontSee('Yesterday Sajilo');
        Livewire::actingAs($player)->test(ProfilePage::class)->set('activeTab', 'spin_wins')
            ->assertViewHas('spinWins', fn ($wins) => $wins->pluck('offer_snapshot_name')->all() === ['Today Sajilo', 'Yesterday Sajilo'])
            ->assertSee('Sajilo Points')->assertSee('Total Sajilo Won')->assertSee('20');
    }

    public function test_badge_configuration_warning_requires_a_compatible_active_slot(): void
    {
        $admin = $this->user('admin');
        SpinWheelSetting::findOrFail(1)->update(['badge_chance' => 10]);
        $type = SpinWheelOfferType::create(['name' => 'Badge', 'slug' => 'badge-warning', 'action_type' => 'badge', 'is_active' => true, 'created_by' => $admin->id]);
        $offer = SpinWheelOffer::create(['offer_type_id' => $type->id, 'name' => 'VIP Badge', 'category' => 'badge', 'rarity_weight' => 1, 'is_active' => true, 'created_by' => $admin->id]);

        $warnings = app(SpinOfferService::class)->categoryWarnings(SpinWheelSetting::findOrFail(1));
        $this->assertContains('Badge category is configured for 10% but has no active eligible offer/slot.', $warnings);
        $this->assignment($offer, 'featured', 2);
        $this->assertNotContains('Badge category is configured for 10% but has no active eligible offer/slot.', app(SpinOfferService::class)->categoryWarnings(SpinWheelSetting::findOrFail(1)));
    }

    public function test_admin_reward_chances_must_be_nonnegative_integers_totalling_one_hundred(): void
    {
        $admin = $this->user('admin');
        $this->ensureAllRewardCategoriesAreEligible($admin);
        Livewire::actingAs($admin)->test(SpinningWheel::class)
            ->set('settingForm.try_again_chance', 49)
            ->call('saveSettings')
            ->assertHasErrors('settingForm.reward_chances');

        Livewire::actingAs($admin)->test(SpinningWheel::class)
            ->set('settingForm.try_again_chance', 50)
            ->set('settingForm.free_spin_chance', 20)
            ->set('settingForm.bonus_points_chance', 10)
            ->set('settingForm.sajilo_points_chance', 10)
            ->set('settingForm.badge_chance', 10)
            ->call('saveSettings')
            ->assertHasNoErrors();
    }

    public function test_brahma_play_bonus_notifications_use_committed_fulfillment_breakdown(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        [$player] = $this->configuredReward('bonus_points', '40', admin: $admin);
        $player->forceFill(['brahma_balance' => 500])->save();
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, '12121212-1212-4212-8212-121212121212');
        $game = Game::create(['name' => 'Notification Breakdown', 'image' => 'test.png', 'is_active' => true]);
        $play = BrahmaPlayRequest::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'points_to_load' => 200,
            'balance_at_submission' => 500, 'status' => 'pending',
        ]);

        Livewire::actingAs($agent)->test(BrahmaPlays::class)
            ->call('openModal', $play->id)
            ->assertSet('pendingPromotionalBonus', 40)
            ->set('status', 'verified')
            ->set('game_username', 'testingjw3')
            ->set('game_password', 'testingjw3')
            ->call('processPlay')
            ->assertHasNoErrors();

        $playerNotification = Notification::where('user_id', $player->id)->where('type', 'brahma_play_verified')->firstOrFail();
        $this->assertStringContainsString('Requested Points: 200.00', $playerNotification->message);
        $this->assertStringContainsString('Pending Bonus Points: 40', $playerNotification->message);
        $this->assertStringContainsString('Total Points Loaded: 240.00', $playerNotification->message);
        $this->assertStringContainsString('Game Username: testingjw3', $playerNotification->message);
        $this->assertStringContainsString('Game Password: testingjw3', $playerNotification->message);
        foreach ([$admin, $agent] as $staff) {
            $message = Notification::where('user_id', $staff->id)->where('type', 'brahma_play_verified_admin')->firstOrFail()->message;
            $this->assertStringContainsString('Player: '.$player->name, $message);
            $this->assertStringContainsString('Requested Points: 200.00', $message);
            $this->assertStringContainsString('Pending Bonus Points: 40', $message);
            $this->assertStringContainsString('Total Points Loaded: 240.00', $message);
        }
        $this->assertDatabaseHas('spin_promotional_point_ledgers', [
            'user_id' => $player->id, 'amount' => 40, 'remaining_amount' => 0,
            'status' => 'fulfilled', 'processed_by' => $agent->id,
            'fulfillment_source_type' => BrahmaPlayRequest::class, 'fulfillment_source_id' => $play->id,
        ]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $player->id, 'type' => 'spin_bonus_fulfilled', 'entity_id' => $play->id]);

        $withoutBonus = $this->user('player');
        $withoutBonus->forceFill(['brahma_balance' => 500])->save();
        $plainPlay = BrahmaPlayRequest::create([
            'user_id' => $withoutBonus->id, 'game_id' => $game->id, 'points_to_load' => 200,
            'balance_at_submission' => 500, 'status' => 'pending',
        ]);
        Livewire::actingAs($agent)->test(BrahmaPlays::class)
            ->call('openModal', $plainPlay->id)
            ->set('status', 'verified')->set('game_username', 'plain-user')->set('game_password', 'plain-pass')
            ->call('processPlay')->assertHasNoErrors();
        $plainMessage = Notification::where('user_id', $withoutBonus->id)->where('type', 'brahma_play_verified')->firstOrFail()->message;
        $this->assertStringContainsString('Requested Points: 200.00', $plainMessage);
        $this->assertStringContainsString('Pending Bonus Points: 0', $plainMessage);
        $this->assertStringContainsString('Total Points Loaded: 200.00', $plainMessage);
    }

    public function test_successful_deposit_automatically_consumes_all_pending_bonus_once(): void
    {
        $agent = $this->user('agent');
        [$player, $offer] = $this->configuredReward('bonus_points', '10');
        foreach ([10, 15, 15] as $index => $amount) {
            $offer->update(['display_value' => (string) $amount]);
            $this->grant($player);
            app(SpinWheelService::class)->spin($player, sprintf('31%06d-3131-4313-8313-313131313131', $index));
        }
        $this->assertSame(40, app(SpinBonusService::class)->pending($player));

        $game = Game::create(['name' => 'Deposit Bonus Auto', 'image' => 'test.png', 'is_active' => true]);
        $deposit = Deposit::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'cashapp',
            'amount' => 100, 'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        Livewire::actingAs($agent)->test(AdminDeposits::class)
            ->call('openModal', $deposit->id)->assertSet('pendingPromotionalBonus', 40)
            ->set('status', 'verified')->set('game_username', 'deposit-user')->set('game_password', 'deposit-pass')
            ->set('game_points_loaded', 100)->call('processDeposit')->assertHasNoErrors();

        $this->assertSame(0, app(SpinBonusService::class)->pending($player));
        $this->assertSame(3, SpinPromotionalPointLedger::where('user_id', $player->id)->where('status', 'fulfilled')->where('remaining_amount', 0)->count());
        $this->assertSame(40, (int) SpinPromotionalPointLedger::where('user_id', $player->id)->sum(DB::raw('amount - remaining_amount')));
        $message = Notification::where('user_id', $player->id)->where('type', 'deposit_verified')->firstOrFail()->message;
        $this->assertStringContainsString('Pending Bonus Points: 40', $message);
        $this->assertStringContainsString('Total Promotional Reference: 140.00', $message);

        $nextDeposit = Deposit::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'cashapp',
            'amount' => 50, 'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        Livewire::actingAs($agent)->test(AdminDeposits::class)->call('openModal', $nextDeposit->id)
            ->assertSet('pendingPromotionalBonus', 0);
        Livewire::actingAs($player)->test(ProfilePage::class)
            ->assertViewHas('pendingBonus', 0)
            ->assertViewHas('totalBonusWon', 40)
            ->assertViewHas('totalBonusAwarded', fn ($value) => (int) $value === 40);
    }

    public function test_profile_bonus_totals_reconcile_after_multiple_entries_are_fulfilled(): void
    {
        $admin = $this->user('admin');
        [$player] = $this->configuredReward('bonus_points', '10', admin: $admin);
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, '13131313-1313-4313-8313-131313131313');
        SpinWheelAssignment::query()->delete();
        [, $offer] = $this->configuredReward('bonus_points', '5', $player, admin: $admin);
        $this->assignment($offer, 'featured', 2);
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, '14141414-1414-4414-8414-141414141414');

        Livewire::actingAs($player)->test(ProfilePage::class)->assertViewHas('pendingBonus', 15);
        $game = Game::create(['name' => 'Bonus Reconciliation', 'image' => 'test.png', 'is_active' => true]);
        $play = BrahmaPlayRequest::create(['user_id' => $player->id, 'game_id' => $game->id, 'points_to_load' => 100, 'balance_at_submission' => 100, 'status' => 'verified']);
        app(SpinBonusService::class)->fulfill($admin, $play);

        Livewire::actingAs($player)->test(ProfilePage::class)
            ->assertViewHas('pendingBonus', 0)
            ->assertViewHas('totalBonusWon', 15)
            ->assertViewHas('totalBonusAwarded', fn ($total) => (int) $total === 15);
    }

    public function test_every_custom_notification_type_floats_once_for_every_role(): void
    {
        foreach (['player', 'admin', 'agent'] as $role) {
            $viewer = $this->user($role);
            $component = Livewire::actingAs($viewer)->test(FloatingAlertCenter::class);
            foreach (['deposit_verified', 'spin_wheel_win', 'future_unknown_type'] as $index => $type) {
                Notification::create(['user_id' => $viewer->id, 'type' => $type, 'title' => 'Alert '.$index, 'message' => 'Visible '.$type]);
            }
            $component->call('pollAlerts')->assertCount('alerts', 3)->call('pollAlerts')->assertCount('alerts', 3);
            $this->assertSame(3, collect($component->get('alerts'))->where('kind', 'notification')->count());
        }
    }

    public function test_spin_notifications_and_win_reports_include_only_actual_benefits(): void
    {
        $admin = $this->user('admin');
        $agent = $this->user('agent');
        $player = $this->user('player');
        $types = ['sajilo_points', 'bonus_points', 'badge', 'free_spin', 'try_again'];

        foreach ($types as $index => $action) {
            SpinWheelAssignment::query()->delete();
            [, $offer] = $this->configuredReward(
                $action,
                in_array($action, ['sajilo_points', 'bonus_points', 'free_spin'], true) ? '1' : null,
                $player,
                admin: $admin
            );
            $this->grant($player);
            app(SpinWheelService::class)->spin($player, sprintf('41%06d-4141-4414-8414-414141414141', $index));
        }

        $this->assertSame(5, SpinWheelSpin::where('user_id', $player->id)->count());
        $this->assertSame(3, Notification::where('user_id', $player->id)->where('type', 'spin_wheel_win')->count());
        $this->assertSame(0, Notification::whereIn('user_id', [$admin->id, $agent->id])->where('type', 'spin_wheel_staff')->count());

        Livewire::actingAs($player)->test(ProfilePage::class)
            ->assertViewHas('spinWins', fn ($wins) => $wins->pluck('offer_snapshot_type')->sort()->values()->all() === ['badge', 'bonus_points', 'sajilo_points']);
        Livewire::actingAs($player)->test(SpinWheel::class)
            ->assertSet('todayWins', fn ($wins) => collect($wins)->pluck('type')->sort()->values()->all() === ['badge', 'bonus_points', 'sajilo_points']);
        Livewire::actingAs($admin)->test(SpinningWheel::class)->call('setTab', 'wins')
            ->assertViewHas('wins', fn ($wins) => $wins->total() === 3)
            ->assertViewHas('topWins', fn ($wins) => $wins->total() === 3)
            ->assertViewHas('topWinners', fn ($winners) => $winners->total() === 1 && $winners->first()->total_wins === 3);
    }

    public function test_failed_or_rejected_verification_does_not_consume_pending_bonus(): void
    {
        $agent = $this->user('agent');
        [$player] = $this->configuredReward('bonus_points', '40');
        $this->grant($player);
        app(SpinWheelService::class)->spin($player, '42424242-4242-4242-8242-424242424242');
        $game = Game::create(['name' => 'Failed Bonus Verification', 'image' => 'test.png', 'is_active' => true]);

        $rejectedDeposit = Deposit::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'cashapp',
            'amount' => 100, 'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        Livewire::actingAs($agent)->test(AdminDeposits::class)->call('openModal', $rejectedDeposit->id)
            ->set('status', 'rejected')->call('processDeposit')->assertHasNoErrors();
        $this->assertSame(40, app(SpinBonusService::class)->pending($player));

        $player->forceFill(['brahma_balance' => 0])->save();
        $play = BrahmaPlayRequest::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'points_to_load' => 200,
            'balance_at_submission' => 0, 'status' => 'pending',
        ]);
        Livewire::actingAs($agent)->test(BrahmaPlays::class)->call('openModal', $play->id)
            ->set('status', 'verified')->set('game_username', 'failed-user')->set('game_password', 'failed-pass')
            ->call('processPlay')->assertHasErrors('status');
        $this->assertSame('pending', $play->fresh()->status);
        $this->assertSame(40, app(SpinBonusService::class)->pending($player));
        $this->assertDatabaseMissing('notifications', ['user_id' => $player->id, 'type' => 'brahma_play_verified', 'entity_id' => $play->id]);
    }

    public function test_active_vip_badge_is_visible_in_deposit_modal_and_expired_badge_is_not(): void
    {
        $admin = $this->user('admin');
        $player = $this->user('player');
        $game = Game::create(['name' => 'VIP Deposit', 'image' => 'test.png', 'is_active' => true]);
        $deposit = Deposit::create(['user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'cashapp', 'amount' => 100, 'proof_image' => 'proof.jpg', 'status' => 'pending']);
        $spin = SpinWheelSpin::create([
            'request_token' => '15151515-1515-4515-8515-151515151515', 'user_id' => $player->id, 'spin_number' => 1,
            'wheel_number' => 101, 'wheel_slot' => 1, 'offer_snapshot_name' => 'VIP Badge',
            'offer_snapshot_type' => 'badge', 'offer_snapshot_category' => 'badge', 'status' => 'awarded', 'spun_at' => now(),
        ]);
        $badge = SpinRewardEntitlement::create(['spin_id' => $spin->id, 'user_id' => $player->id, 'entitlement_type' => 'badge', 'metadata' => ['label' => 'VIP Badge'], 'expires_at' => now()->addDays(3)]);

        Livewire::actingAs($admin)->test(AdminDeposits::class)->call('openModal', $deposit->id)
            ->assertSee('VIP BADGE ACTIVE')->assertSee('VIP Badge')->assertSee('Valid until');
        $badge->update(['expires_at' => now()->subSecond()]);
        Livewire::actingAs($admin)->test(AdminDeposits::class)->call('openModal', $deposit->id)->assertDontSee('VIP BADGE ACTIVE');
    }

    public function test_invalid_runtime_pool_rolls_back_without_consuming_attempt_or_creating_result(): void
    {
        [$player, $offer] = $this->configuredReward('try_again');
        $grant = $this->grant($player, 1);
        $offer->assignments()->update(['is_active' => false]);

        try {
            app(SpinWheelService::class)->spin($player, '16161616-1616-4616-8616-161616161616');
            $this->fail('Invalid configuration should not spin.');
        } catch (ValidationException $exception) {
            $this->assertSame('The Spin Wheel is temporarily unavailable. Your spin was not used.', $exception->errors()['spin'][0]);
        }

        $this->assertSame(1, $grant->fresh()->attempts_remaining);
        $this->assertDatabaseMissing('spin_wheel_spins', ['request_token' => '16161616-1616-4616-8616-161616161616']);
        $this->assertDatabaseCount('spin_promotional_point_ledgers', 0);
    }

    public function test_animation_is_transition_driven_and_open_player_uses_current_settings_for_every_reward(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/player/spin-wheel.blade.php'));
        $this->assertStringContainsString('@transitionend="transitionFinished($event)"', $blade);
        $this->assertStringContainsString('rotate3d(0,0,1,${rotation}deg)', $blade);
        $this->assertStringContainsString('wire:ignore x-ref="disk"', $blade);
        $this->assertStringContainsString('cubic-bezier(.12,.72,.18,1)', $blade);
        $this->assertStringContainsString('if (!this.spinning && !this.reveal && !this.awaitingTransition) this.$wire.refreshState()', $blade);
        $this->assertStringNotContainsString('wire:poll', $blade);

        $player = $this->user('player');
        $admin = $this->user('admin');
        foreach (array_keys(SpinCategoryProbabilityService::DEFAULTS) as $index => $action) {
            SpinWheelAssignment::query()->delete();
            [$player] = $this->configuredReward($action, in_array($action, ['free_spin', 'bonus_points', 'sajilo_points'], true) ? '1' : null, $player, admin: $admin);
            $this->grant($player);
            $component = Livewire::actingAs($player)->test(SpinWheel::class);
            SpinWheelSetting::findOrFail(1)->update(['animation_duration_ms' => 1700]);
            $component->call('spin')->assertSet('animationPending', true)->assertDispatched('spin-wheel-result')
                ->call('animationFinished')->assertSet('animationPending', false)->assertDispatched('spin-wheel-reveal-state');
        }
    }

    public function test_profile_defaults_to_latest_spin_month_and_shows_immutable_spin_history(): void
    {
        [$player, $offer] = $this->configuredReward('sajilo_points', '10');
        SpinWheelSpin::create([
            'request_token' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'user_id' => $player->id,
            'spin_number' => 1, 'wheel_number' => 101, 'wheel_slot' => 1, 'offer_id' => $offer->id,
            'offer_snapshot_name' => 'Sajilo 10', 'offer_snapshot_value' => '10',
            'offer_snapshot_type' => 'sajilo_points', 'offer_snapshot_category' => 'sajilo_points',
            'offer_snapshot_score' => 10, 'status' => 'awarded', 'spun_at' => '2026-08-18 12:00:00',
        ]);

        Livewire::actingAs($player)->test(ProfilePage::class)
            ->assertSet('month', 8)
            ->assertSet('year', 2026)
            ->set('activeTab', 'spin_wins')
            ->assertSee('Sajilo Points')
            ->assertSee('10')
            ->assertSee('awarded');
    }

    public function test_history_is_immutable_and_wins_paginate_twenty(): void
    {
        $admin = $this->user('admin');
        [$player, $offer] = $this->configuredReward('sajilo_points', '10', admin: $admin);
        foreach (range(1, 21) as $number) {
            SpinWheelSpin::create([
                'request_token' => sprintf('88888888-8888-4888-8888-%012d', $number), 'user_id' => $player->id,
                'spin_number' => $number, 'wheel_number' => 101, 'wheel_slot' => 1, 'offer_id' => $offer->id,
                'offer_snapshot_name' => $offer->name, 'offer_snapshot_value' => $offer->display_value,
                'offer_snapshot_type' => 'sajilo_points', 'offer_snapshot_category' => 'sajilo_points',
                'offer_snapshot_score' => 10, 'status' => 'awarded', 'spun_at' => now()->subMinutes($number),
            ]);
        }
        $spin = SpinWheelSpin::firstOrFail();
        $this->assertThrows(fn () => $spin->update(['wheel_slot' => 9, 'offer_snapshot_name' => 'Changed']), \LogicException::class);
        app(SpinOfferService::class)->deleteOffer($admin, $offer);
        $this->assertFalse($offer->fresh()->is_active);
        Livewire::actingAs($admin)->test(SpinningWheel::class)->call('setTab', 'wins')
            ->assertViewHas('wins', fn ($wins) => $wins->perPage() === 20 && $wins->total() === 21);
    }

    private function configuredReward(string $action = 'try_again', ?string $value = null, ?User $player = null, array $metadata = [], ?User $admin = null): array
    {
        $admin ??= $this->user('admin');
        $player ??= $this->user('player');
        SpinWheelAssignment::query()->whereHas('offer.type', fn ($query) => $query->where('action_type', $action))->delete();
        $type = SpinWheelOfferType::create(['name' => ucfirst($action), 'slug' => $action.'-'.uniqid(), 'action_type' => $action, 'is_active' => true, 'created_by' => $admin->id]);
        $offer = SpinWheelOffer::create([
            'offer_type_id' => $type->id, 'name' => 'Test Reward', 'display_value' => $value,
            'category' => $action,
            'rarity_weight' => 1, 'ranking_score' => is_numeric($value) ? (int) $value : 10,
            'is_active' => true, 'is_featured' => true, 'metadata' => $metadata, 'created_by' => $admin->id,
        ]);
        $this->assignment($offer, 'numeric', 1);
        $settings = SpinWheelSetting::findOrFail(1);
        $weights = array_fill_keys(array_keys(SpinCategoryProbabilityService::DEFAULTS), 0);
        $weights[$action] = 100;
        foreach ($weights as $category => $weight) {
            $settings->{$category.'_chance'} = $weight;
        }
        $settings->save();

        return [$player, $offer];
    }

    private function assignment(SpinWheelOffer $offer, string $type, int $position): SpinWheelAssignment
    {
        $visual = app(SpinOfferService::class)->visualSlot($type, $position);

        return SpinWheelAssignment::create([
            'wheel_number' => 100 + $visual, 'slot_type' => $type, 'slot_position' => $position,
            'offer_id' => $offer->id, 'display_label' => $type === 'featured' ? $offer->name : null,
            'weight' => 1, 'is_active' => true, 'is_featured' => $type === 'featured',
        ]);
    }

    private function ensureAllRewardCategoriesAreEligible(User $admin): void
    {
        SpinWheelAssignment::query()->delete();
        foreach (array_keys(SpinCategoryProbabilityService::DEFAULTS) as $index => $action) {
            $type = SpinWheelOfferType::create(['name' => 'Eligible '.$action, 'slug' => 'eligible-'.$action, 'action_type' => $action, 'is_active' => true, 'created_by' => $admin->id]);
            $offer = SpinWheelOffer::create(['offer_type_id' => $type->id, 'name' => 'Eligible '.$action, 'display_value' => in_array($action, ['free_spin', 'bonus_points', 'sajilo_points'], true) ? '1' : null, 'category' => $action, 'rarity_weight' => 1, 'is_active' => true, 'created_by' => $admin->id]);
            $this->assignment($offer, 'numeric', $index + 1);
        }
    }

    private function grant(User $player, int $attempts = 1): SpinAttemptGrant
    {
        return SpinAttemptGrant::create([
            'user_id' => $player->id, 'source_type' => 'test', 'source_id' => random_int(1, 1000000),
            'attempts_granted' => $attempts, 'attempts_remaining' => $attempts, 'granted_at' => now(),
        ]);
    }

    private function deposit(User $player, string $status): BrahmaDeposit
    {
        return BrahmaDeposit::create(['user_id' => $player->id, 'amount' => 10, 'proof_image' => 'proof.jpg', 'status' => $status]);
    }

    private function normalDeposit(User $player, Game $game, string $amount, string $status): Deposit
    {
        return Deposit::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'cashapp',
            'amount' => $amount, 'proof_image' => 'proof.jpg', 'status' => $status,
        ]);
    }

    private function brahmaPlay(User $player, Game $game, string $status): BrahmaPlayRequest
    {
        return BrahmaPlayRequest::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'points_to_load' => 10,
            'balance_at_submission' => 10, 'status' => $status,
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
