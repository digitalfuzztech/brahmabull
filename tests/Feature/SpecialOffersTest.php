<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Admin\Deposits as AdminDeposits;
use App\Livewire\Admin\SpecialOffers as AdminSpecialOffers;
use App\Livewire\Pages\BrahmaBalance;
use App\Livewire\Pages\Games as PlayerGames;
use App\Livewire\Player\SpecialOffers as PlayerSpecialOffers;
use App\Models\BrahmaDeposit;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpecialOffersTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_route_is_protected_and_only_admin_can_access_it(): void
    {
        $this->actingAs($this->user('admin'))->get(route('admin.special-offers'))->assertOk();
        $this->actingAs($this->user('agent'))->get(route('admin.special-offers'))->assertForbidden();
        $this->actingAs($this->user('player'))->get(route('admin.special-offers'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.special-offers'))->assertRedirect('/login');

        $route = app('router')->getRoutes()->getByName('admin.special-offers');
        $this->assertContains('role:admin', $route->gatherMiddleware());
    }

    public function test_admin_can_create_and_edit_an_offer(): void
    {
        $admin = $this->user('admin');

        Livewire::actingAs($admin)->test(AdminSpecialOffers::class)
            ->call('create')
            ->set('name', 'Double Weekend')
            ->set('description', 'A polished weekend promotion.')
            ->set('starts_at', '2026-09-19')
            ->set('ends_at', '2026-09-21')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $offer = SpecialOffer::firstOrFail();
        $this->assertSame('Double Weekend', $offer->name);

        Livewire::actingAs($admin)->test(AdminSpecialOffers::class)
            ->call('edit', $offer->id)
            ->set('name', 'Updated Weekend')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('special_offers', ['id' => $offer->id, 'name' => 'Updated Weekend']);
    }

    public function test_admin_can_disable_reenable_and_delete_an_offer(): void
    {
        $admin = $this->user('admin');
        $offer = $this->offer();
        $component = Livewire::actingAs($admin)->test(AdminSpecialOffers::class);

        $component->call('toggle', $offer->id);
        $this->assertFalse($offer->fresh()->is_active);

        $component->call('toggle', $offer->id);
        $this->assertTrue($offer->fresh()->is_active);

        $component->call('delete', $offer->id);
        $this->assertSoftDeleted($offer);
    }

    public function test_fifth_offer_cannot_be_created_server_side(): void
    {
        foreach (range(1, 4) as $number) {
            $this->offer(['name' => "Offer {$number}"]);
        }

        Livewire::actingAs($this->user('admin'))->test(AdminSpecialOffers::class)
            ->call('create')
            ->assertHasErrors(['offerLimit']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->offer(['name' => 'Fifth Offer']);
    }

    public function test_deleting_one_offer_allows_a_replacement(): void
    {
        $offers = collect(range(1, 4))->map(fn ($number) => $this->offer(['name' => "Offer {$number}"]));
        $offers->first()->delete();

        $replacement = $this->offer(['name' => 'Replacement']);

        $this->assertSame(4, SpecialOffer::query()->count());
        $this->assertSame('Replacement', $replacement->name);
    }

    public function test_active_date_scope_is_inclusive_and_filters_future_expired_and_disabled_offers(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');
        $startsToday = $this->offer(['name' => 'Starts Today', 'starts_at' => '2026-09-19', 'ends_at' => '2026-09-22']);
        $endsToday = $this->offer(['name' => 'Ends Today', 'starts_at' => '2026-09-15', 'ends_at' => '2026-09-19']);
        $this->offer(['name' => 'Future', 'starts_at' => '2026-09-20', 'ends_at' => '2026-09-22']);
        $this->offer(['name' => 'Expired', 'starts_at' => '2026-09-10', 'ends_at' => '2026-09-18']);

        $visibleIds = SpecialOffer::query()->activeOnDate(today())->pluck('id');
        $this->assertTrue($visibleIds->contains($startsToday->id));
        $this->assertTrue($visibleIds->contains($endsToday->id));
        $this->assertCount(2, $visibleIds);

        $startsToday->update(['is_active' => false]);
        $this->assertFalse(SpecialOffer::query()->activeOnDate(today())->pluck('id')->contains($startsToday->id));
    }

    public function test_player_launcher_tracks_current_active_offers(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');
        $player = $this->user('player');

        Livewire::actingAs($player)->test(PlayerSpecialOffers::class)
            ->assertDontSee('OFFERS');

        $this->offer(['name' => 'Player Special']);

        Livewire::actingAs($player)->test(PlayerSpecialOffers::class)
            ->assertSee('OFFERS')
            ->call('toggleList')
            ->assertSee('Player Special')
            ->call('showOffer', SpecialOffer::first()->id)
            ->assertSee('PLAY GAME');
    }

    public function test_player_deposit_modals_display_current_offers_only_in_payment_ui(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');
        $player = $this->user('player');
        $game = Game::create(['name' => 'Offer Game', 'image' => 'game.png', 'is_active' => true]);
        $this->offer(['name' => 'Deposit Spotlight']);

        Livewire::actingAs($player)->test(BrahmaBalance::class)
            ->call('openModal')
            ->assertSee('Active Offers')
            ->assertSee('Deposit Spotlight');

        Livewire::actingAs($player)->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->assertSee('Active Offers')
            ->assertSee('Deposit Spotlight')
            ->set('playModalTab', 'brahma')
            ->assertDontSee('Deposit Spotlight');
    }

    public function test_normal_deposit_processing_displays_offers_covering_submission_date(): void
    {
        $staff = $this->user('agent');
        $player = $this->user('player');
        $game = Game::create(['name' => 'Historical Game', 'image' => 'game.png', 'is_active' => true]);
        $offer = $this->offer([
            'name' => 'Historical Normal Offer',
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-10',
            'is_active' => false,
        ]);
        $deposit = Deposit::create([
            'user_id' => $player->id, 'game_id' => $game->id, 'wallet_type' => 'test',
            'amount' => 25, 'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        $deposit->forceFill(['created_at' => '2026-08-10 23:30:00'])->save();
        $offer->delete();

        Livewire::actingAs($staff)->test(AdminDeposits::class)
            ->call('openModal', $deposit->id)
            ->assertSee('OFFERS ACTIVE WHEN THIS DEPOSIT WAS SUBMITTED')
            ->assertSee('Historical Normal Offer');
    }

    public function test_brahma_deposit_processing_displays_offers_covering_submission_date(): void
    {
        $staff = $this->user('agent');
        $player = $this->user('player');
        $this->offer([
            'name' => 'Historical Brahma Offer',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-07-31',
            'is_active' => false,
        ]);
        $deposit = BrahmaDeposit::create([
            'user_id' => $player->id, 'wallet_type' => 'test', 'wallet_name' => 'Test Wallet',
            'wallet_account_identifier' => 'wallet-1', 'amount' => 50,
            'proof_image' => 'proof.jpg', 'status' => 'pending',
        ]);
        $deposit->forceFill(['created_at' => '2026-07-31 22:00:00'])->save();

        Livewire::actingAs($staff)->test(BrahmaDeposits::class)
            ->call('openModal', $deposit->id)
            ->assertSee('OFFERS ACTIVE WHEN THIS DEPOSIT WAS SUBMITTED')
            ->assertSee('Historical Brahma Offer');
    }

    public function test_homepage_contains_parallax_hooks_while_standalone_rules_page_does_not(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-bb-home-parallax', false)
            ->assertSee('data-bb-rules-parallax', false)
            ->assertSee('data-bb-parallax-cta', false);

        $this->get(route('brahmabull-rules'))
            ->assertOk()
            ->assertDontSee('data-bb-home-parallax', false);
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

    private function offer(array $attributes = []): SpecialOffer
    {
        return SpecialOffer::create(array_merge([
            'name' => 'Current Offer',
            'description' => 'A special promotion for BrahmaBull players.',
            'starts_at' => '2026-09-18',
            'ends_at' => '2026-09-20',
            'is_active' => true,
        ], $attributes));
    }
}
