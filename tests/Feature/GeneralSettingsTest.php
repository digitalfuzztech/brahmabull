<?php

namespace Tests\Feature;

use App\Livewire\Admin\Cms\GeneralSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_general_settings_route_is_admin_only(): void
    {
        $this->actingAs($this->user('admin'))->get(route('admin.cms.general-settings'))->assertOk();
        $this->actingAs($this->user('agent'))->get(route('admin.cms.general-settings'))->assertForbidden();
        $this->actingAs($this->user('player'))->get(route('admin.cms.general-settings'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.cms.general-settings'))->assertRedirect(route('login'));
    }

    public function test_component_rejects_non_admin_access(): void
    {
        Livewire::actingAs($this->user('agent'))
            ->test(GeneralSettings::class)
            ->assertForbidden();
    }

    public function test_initial_singleton_preserves_existing_public_defaults(): void
    {
        $settings = SiteSetting::query()->findOrFail(1);

        $this->assertSame('BrahmaBull Gaming Club', $settings->site_name);
        $this->assertNull($settings->logo_path);
        $this->assertSame('BrahmaBull Member Portal', $settings->meta_title);
        $this->assertSame('BrahmaBull Member Platform', $settings->meta_description);
        $this->assertDatabaseCount('site_settings', 1);
    }

    public function test_admin_can_save_brand_social_and_seo_values(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(GeneralSettings::class)
            ->set('site_name', 'BrahmaBull Test Club')
            ->set('facebook_url', 'https://social.example/facebook')
            ->set('instagram_url', 'https://social.example/instagram')
            ->set('x_url', 'https://social.example/x')
            ->set('youtube_url', 'https://social.example/youtube')
            ->set('discord_url', 'https://social.example/discord')
            ->set('meta_title', 'BrahmaBull Test Title')
            ->set('meta_description', 'A saved test description for the public website.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('General settings saved successfully.');

        $this->assertDatabaseHas('site_settings', [
            'id' => 1,
            'site_name' => 'BrahmaBull Test Club',
            'facebook_url' => 'https://social.example/facebook',
            'instagram_url' => 'https://social.example/instagram',
            'x_url' => 'https://social.example/x',
            'youtube_url' => 'https://social.example/youtube',
            'discord_url' => 'https://social.example/discord',
            'meta_title' => 'BrahmaBull Test Title',
            'meta_description' => 'A saved test description for the public website.',
        ]);
    }

    public function test_invalid_social_url_is_rejected_and_blank_is_allowed(): void
    {
        $admin = $this->user('admin');

        Livewire::actingAs($admin)
            ->test(GeneralSettings::class)
            ->set('facebook_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['facebook_url']);

        Livewire::actingAs($admin)
            ->test(GeneralSettings::class)
            ->set('facebook_url', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(SiteSetting::query()->findOrFail(1)->facebook_url);
    }

    public function test_admin_can_upload_a_valid_logo(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user('admin'))
            ->test(GeneralSettings::class)
            ->set('logo', UploadedFile::fake()->image('brand.png', 300, 120))
            ->call('save')
            ->assertHasNoErrors();

        $path = SiteSetting::query()->findOrFail(1)->logo_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('site/branding/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->user('admin'))
            ->test(GeneralSettings::class)
            ->set('logo', UploadedFile::fake()->create('brand.svg', 20, 'image/svg+xml'))
            ->call('save')
            ->assertHasErrors(['logo']);

        $this->assertNull(SiteSetting::query()->findOrFail(1)->logo_path);
    }

    public function test_saving_without_a_new_logo_preserves_existing_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/branding/existing.png', 'existing-logo');
        SiteSetting::query()->whereKey(1)->update(['logo_path' => 'site/branding/existing.png']);

        Livewire::actingAs($this->user('admin'))
            ->test(GeneralSettings::class)
            ->set('site_name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('site/branding/existing.png', SiteSetting::query()->findOrFail(1)->logo_path);
        Storage::disk('public')->assertExists('site/branding/existing.png');
    }

    public function test_replacing_a_cms_logo_updates_setting_then_removes_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/branding/old.png', 'old-logo');
        SiteSetting::query()->whereKey(1)->update(['logo_path' => 'site/branding/old.png']);

        Livewire::actingAs($this->user('admin'))
            ->test(GeneralSettings::class)
            ->set('logo', UploadedFile::fake()->image('replacement.webp', 320, 160))
            ->call('save')
            ->assertHasNoErrors();

        $newPath = SiteSetting::query()->findOrFail(1)->logo_path;
        $this->assertNotSame('site/branding/old.png', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('site/branding/old.png');
    }

    public function test_public_identity_uses_saved_site_name_and_uploaded_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/branding/public-logo.png', 'logo');
        SiteSetting::query()->whereKey(1)->update([
            'site_name' => 'Public CMS Brand',
            'logo_path' => 'site/branding/public-logo.png',
        ]);

        $response = $this->get(route('privacy-policy'));

        $response
            ->assertOk()
            ->assertSee('Public CMS Brand')
            ->assertSee(Storage::disk('public')->url('site/branding/public-logo.png'), false);
    }

    public function test_missing_cms_logo_uses_bundled_header_and_footer_fallbacks(): void
    {
        Storage::fake('public');
        SiteSetting::query()->whereKey(1)->update(['logo_path' => 'site/branding/missing.png']);

        $this->get(route('privacy-policy'))
            ->assertSee(asset('images/logo.png'), false)
            ->assertSee(asset('images/logo-brahma.png'), false)
            ->assertDontSee('/storage/site/branding/missing.png', false);
    }

    public function test_database_social_value_is_authoritative_over_stale_config(): void
    {
        config()->set('brahmabull.social.facebook', 'https://stale.example/facebook');
        SiteSetting::query()->whereKey(1)->update(['facebook_url' => null]);

        $this->get(route('privacy-policy'))
            ->assertDontSee('https://stale.example/facebook', false)
            ->assertDontSee('Visit BrahmaBull on Facebook');

        SiteSetting::query()->whereKey(1)->update(['facebook_url' => 'https://current.example/facebook']);

        $this->get(route('privacy-policy'))
            ->assertSee('href="https://current.example/facebook"', false)
            ->assertSee('Visit BrahmaBull on Facebook');
    }

    public function test_public_metadata_uses_cms_defaults_and_preserves_explicit_page_title(): void
    {
        SiteSetting::query()->whereKey(1)->update([
            'meta_title' => 'CMS Default Title',
            'meta_description' => 'CMS public description.',
        ]);

        $homepage = $this->get(route('home'))->assertOk();
        $homepage->assertSee('<title>CMS Default Title</title>', false);
        $this->assertSame(1, substr_count($homepage->getContent(), '<meta name="description"'));
        $homepage->assertSee('content="CMS public description."', false);

        $this->get(route('privacy-policy'))
            ->assertOk()
            ->assertSee('<title>Privacy Policy | BrahmaBull</title>', false)
            ->assertDontSee('<title>CMS Default Title</title>', false);
    }

    public function test_legal_pages_and_footer_links_remain_available(): void
    {
        foreach (['privacy-policy', 'terms-and-conditions', 'guide-to-play'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('href="'.route('privacy-policy').'"', false)
                ->assertSee('href="'.route('terms-and-conditions').'"', false)
                ->assertSee('href="'.route('guide-to-play').'"', false);
        }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
