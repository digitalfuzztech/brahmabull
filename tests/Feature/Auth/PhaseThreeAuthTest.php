<?php

namespace Tests\Feature\Auth;

use App\Models\Notification as PlayerNotification;
use App\Models\PlayerProfile;
use App\Models\Referral;
use App\Models\SiteSetting;
use App\Models\SpinAttemptGrant;
use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseThreeAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requested_auth_pages_render_with_their_dedicated_backgrounds(): void
    {
        foreach ([
            'login' => 'images/ui/casino/auth-login-bg.png',
            'register' => 'images/ui/casino/auth-register-bg.png',
            'password.request' => 'images/ui/casino/auth-forgot-bg.png',
        ] as $route => $background) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee(asset($background), false);
        }
    }

    public function test_auth_layout_uses_cms_site_name_and_uploaded_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/branding/auth-logo.png', 'auth-logo');

        SiteSetting::query()->findOrFail(1)->update([
            'site_name' => 'CMS Auth Brand',
            'logo_path' => 'site/branding/auth-logo.png',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('CMS Auth Brand')
            ->assertSee(Storage::disk('public')->url('site/branding/auth-logo.png'), false);
    }

    public function test_auth_layout_uses_bundled_logo_fallback_when_cms_logo_is_null(): void
    {
        SiteSetting::query()->findOrFail(1)->update(['logo_path' => null]);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(asset('images/logo-brahma.png'), false);
    }

    public function test_registration_renders_terms_link_checkbox_and_internal_scroll_structure(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('href="'.route('terms-and-conditions').'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('wire:model.live="terms"', false)
            ->assertSee('bb-auth-register-scroll', false)
            ->assertSee('I agree to the Terms and Conditions');
    }

    public function test_login_still_accepts_a_username(): void
    {
        Role::findOrCreate('player');
        $player = User::factory()->create([
            'username' => 'phase-three-player',
            'is_active' => true,
        ]);
        $player->assignRole('player');

        Volt::test('pages.auth.login')
            ->set('form.login', $player->username)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($player);
    }

    public function test_login_still_accepts_an_email(): void
    {
        Role::findOrCreate('player');
        $player = User::factory()->create([
            'username' => 'phase-three-email-player',
            'is_active' => true,
        ]);
        $player->assignRole('player');

        Volt::test('pages.auth.login')
            ->set('form.login', $player->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($player);
    }

    public function test_unchecked_terms_prevent_every_registration_side_effect(): void
    {
        Storage::fake('public');
        Event::fake([Registered::class]);
        Role::findOrCreate('player');

        $referrer = User::factory()->create(['referral_code' => 'REFTERMS']);

        Volt::test('pages.auth.register')
            ->set('name', 'Blocked Player')
            ->set('email', 'blocked@example.test')
            ->set('username', 'blocked-player')
            ->set('password', 'Secure1!Pass')
            ->set('password_confirmation', 'Secure1!Pass')
            ->set('phone', '5551234567')
            ->set('referral_code', $referrer->referral_code)
            ->set('photo', UploadedFile::fake()->image('profile.png'))
            ->set('terms', false)
            ->call('register')
            ->assertHasErrors(['terms' => 'accepted'])
            ->assertSet('registered', false);

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.test']);
        $this->assertSame(0, PlayerProfile::query()->count());
        $this->assertSame(0, Referral::query()->count());
        $this->assertSame(0, PlayerNotification::query()->count());
        $this->assertSame(0, SpinAttemptGrant::query()->count());
        $this->assertSame(0, \DB::table('model_has_roles')->count());
        Storage::disk('public')->assertMissing('profiles');
        Event::assertNotDispatched(Registered::class);
        $this->assertGuest();
    }

    public function test_accepted_terms_preserve_normal_registration_profile_role_and_onboarding_spin(): void
    {
        Event::fake([Registered::class]);
        Role::findOrCreate('player');

        Volt::test('pages.auth.register')
            ->set('name', 'Terms Player')
            ->set('email', 'terms-player@example.test')
            ->set('username', 'terms-player')
            ->set('password', 'Secure1!Pass')
            ->set('password_confirmation', 'Secure1!Pass')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('registered', true);

        $player = User::query()->where('email', 'terms-player@example.test')->firstOrFail();

        $this->assertTrue($player->hasRole('player'));
        $this->assertDatabaseHas('player_profiles', ['user_id' => $player->id]);
        $this->assertDatabaseHas('spin_attempt_grants', [
            'user_id' => $player->id,
            'source_type' => 'onboarding',
            'source_id' => $player->id,
            'attempts_granted' => 1,
            'attempts_remaining' => 1,
        ]);
        $this->assertSame(1, SpinAttemptGrant::query()->where('user_id', $player->id)->count());
        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->is($player));
        $this->assertGuest();
    }

    public function test_accepted_terms_preserve_referral_records_and_notification(): void
    {
        Role::findOrCreate('player');
        Role::findOrCreate('admin');
        Role::findOrCreate('agent');
        $referrer = User::factory()->create(['referral_code' => 'REFWORKS']);

        Volt::test('pages.auth.register')
            ->set('name', 'Referred Player')
            ->set('email', 'referred-player@example.test')
            ->set('username', 'referred-player')
            ->set('password', 'Secure1!Pass')
            ->set('password_confirmation', 'Secure1!Pass')
            ->set('referral_code', $referrer->referral_code)
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $player = User::query()->where('email', 'referred-player@example.test')->firstOrFail();

        $this->assertSame($referrer->id, $player->referred_by);
        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_user_id' => $player->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $referrer->id,
            'type' => 'referral',
            'title' => 'New Referral Registered',
        ]);
    }

    public function test_forgot_password_keeps_the_custom_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, CustomResetPasswordNotification::class);
    }
}
