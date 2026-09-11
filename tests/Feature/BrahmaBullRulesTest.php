<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrahmaBullRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_access_the_public_rules_page(): void
    {
        $this->get(route('brahmabull-rules'))
            ->assertOk()
            ->assertViewIs('public.brahmabull-rules')
            ->assertSee('BrahmaBull Rules')
            ->assertSee('Account &amp; Access', false)
            ->assertSee('Cashouts &amp; Withdrawals', false)
            ->assertSee('Promotions &amp; Spin', false)
            ->assertSee('Player Support Chat');
    }

    public function test_player_can_access_the_public_rules_page(): void
    {
        $this->actingAs($this->player())
            ->get(route('brahmabull-rules'))
            ->assertOk()
            ->assertSee('BrahmaBull Rules');
    }

    public function test_rules_page_uses_public_layout_branding_footer_and_specific_metadata(): void
    {
        $this->get(route('brahmabull-rules'))
            ->assertOk()
            ->assertSee('<title>BrahmaBull Rules</title>', false)
            ->assertSee('Practical BrahmaBull account, deposit, balance, game access, cashout, Spin, and support rules for players.')
            ->assertSee('GAMING')
            ->assertSee('Privacy Policy')
            ->assertSee('Terms and Conditions')
            ->assertSee('Guide To Play');
    }

    public function test_homepage_places_rules_between_about_and_cta_after_top_winners(): void
    {
        $source = file_get_contents(resource_path('views/livewire/public/home-page.blade.php'));

        $topWinners = strpos($source, '<livewire:public.top-winners />');
        $about = strpos($source, "@include('livewire.public.sections.about-section')");
        $rules = strpos($source, "@include('livewire.public.sections.rules-section')");
        $cta = strpos($source, "@include('livewire.public.sections.cta-section')");

        $this->assertNotFalse($topWinners);
        $this->assertTrue($topWinners < $about);
        $this->assertTrue($about < $rules);
        $this->assertTrue($rules < $cta);
    }

    public function test_homepage_rules_summary_uses_named_route_and_existing_reveal_attributes(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="rules"', false)
            ->assertSee('Play With Confidence')
            ->assertSee('View All Rules')
            ->assertSee('href="'.route('brahmabull-rules').'"', false)
            ->assertSee('data-bb-reveal', false);
    }

    public function test_player_dropdown_orders_profile_rules_and_logout(): void
    {
        $source = file_get_contents(resource_path('views/components/public-header.blade.php'));

        $profile = strpos($source, 'My Profile');
        $rules = strpos($source, 'BrahmaBull Rules');
        $logout = strpos($source, 'Logout');

        $this->assertNotFalse($profile);
        $this->assertTrue($profile < $rules);
        $this->assertTrue($rules < $logout);

        $this->actingAs($this->player())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee('href="'.route('brahmabull-rules').'"', false)
            ->assertSee('Logout');
    }

    public function test_profile_route_remains_unchanged(): void
    {
        $route = Route::getRoutes()->getByName('profile');

        $this->assertNotNull($route);
        $this->assertSame('profile', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_logout_remains_a_post_action_that_invalidates_authentication(): void
    {
        $route = Route::getRoutes()->getByName('logout');

        $this->assertNotNull($route);
        $this->assertSame('logout', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());

        $this->actingAs($this->player())
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_header_keeps_the_existing_logout_form_and_modal_dispatch(): void
    {
        $source = file_get_contents(resource_path('views/components/public-header.blade.php'));

        $this->assertStringContainsString('id="logout-form"', $source);
        $this->assertStringContainsString('method="POST"', $source);
        $this->assertStringContainsString('action="{{ route(\'logout\') }}"', $source);
        $this->assertStringContainsString('@csrf', $source);
        $this->assertStringContainsString("\$dispatch('open-logout-modal')", $source);
        $this->assertStringContainsString("document.getElementById('logout-form').submit()", $source);
    }

    public function test_existing_public_information_pages_remain_available(): void
    {
        foreach (['privacy-policy', 'terms-and-conditions', 'guide-to-play'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    private function player(): User
    {
        Role::findOrCreate('player');
        $player = User::factory()->create(['is_active' => true]);
        $player->assignRole('player');

        return $player;
    }
}
