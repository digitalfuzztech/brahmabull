<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicInformationPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_keys(config('brahmabull.social')) as $platform) {
            config()->set("brahmabull.social.$platform", null);
        }
    }

    public function test_privacy_policy_is_public_and_uses_expected_view(): void
    {
        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertViewIs('public.privacy-policy')
            ->assertSee('Privacy Policy')
            ->assertSee('Information Players Provide');
    }

    public function test_terms_and_conditions_is_public_and_uses_expected_view(): void
    {
        $this->get(route('terms-and-conditions'))
            ->assertOk()
            ->assertViewIs('public.terms-and-conditions')
            ->assertSee('Terms and Conditions')
            ->assertSee('Cashout and Withdrawal Requests');
    }

    public function test_guide_to_play_is_public_and_describes_existing_workflow(): void
    {
        $this->get(route('guide-to-play'))
            ->assertOk()
            ->assertViewIs('public.guide-to-play')
            ->assertSee('Guide To Play')
            ->assertSee('Submit a normal game deposit')
            ->assertSee('Submit a Brahma Play request')
            ->assertSee('Request a cashout');
    }

    public function test_authenticated_player_can_access_all_information_pages(): void
    {
        Role::findOrCreate('player');
        $player = User::factory()->create();
        $player->assignRole('player');

        $this->actingAs($player);

        foreach (['privacy-policy', 'terms-and-conditions', 'guide-to-play'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_footer_contains_named_information_links(): void
    {
        $response = $this->get(route('privacy-policy'));

        $response
            ->assertSee('href="'.route('privacy-policy').'"', false)
            ->assertSee('href="'.route('terms-and-conditions').'"', false)
            ->assertSee('href="'.route('guide-to-play').'"', false);
    }

    public function test_social_links_are_hidden_when_not_configured(): void
    {
        $this->get(route('privacy-policy'))
            ->assertDontSee('BrahmaBull social media')
            ->assertDontSee('Visit BrahmaBull on Facebook')
            ->assertDontSee('href="#"', false);
    }

    public function test_configured_database_social_link_is_safe_and_visible(): void
    {
        SiteSetting::query()->whereKey(1)->update(['facebook_url' => 'https://social.example/brahmabull']);

        $this->get(route('privacy-policy'))
            ->assertSee('href="https://social.example/brahmabull"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Visit BrahmaBull on Facebook');
    }

    public function test_non_http_social_values_do_not_render_links(): void
    {
        SiteSetting::query()->whereKey(1)->update(['discord_url' => 'javascript:alert(1)']);

        $this->get(route('privacy-policy'))
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('Visit BrahmaBull on Discord');
    }
}
