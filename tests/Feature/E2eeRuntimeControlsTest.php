<?php

namespace Tests\Feature;

use App\Livewire\Admin\SupportInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class E2eeRuntimeControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_inbox_loads_e2ee_runtime_before_dynamically_mounted_team_markup(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/private.blade.php'));
        $team = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));

        $this->assertStringContainsString('<meta name="csrf-token" content="{{ csrf_token() }}">', $layout);
        $this->assertStringContainsString("request()->routeIs('admin.inbox', 'agent.inbox')", $layout);
        $this->assertStringContainsString("@vite('resources/js/chat/e2ee/team-messenger.js')", $layout);
        $this->assertStringNotContainsString("@vite('resources/js/chat/e2ee/team-messenger.js')", $team);
        $this->assertStringContainsString('x-data="window.BrahmaE2eeDevices(', $team);
        $this->assertStringContainsString('x-data="{ ...window.BrahmaE2eeTeam(', $team);
    }

    public function test_runtime_controls_are_non_submitting_and_bound_to_the_mounted_alpine_scopes(): void
    {
        $team = file_get_contents(resource_path('views/livewire/admin/team-messenger.blade.php'));
        $runtime = file_get_contents(resource_path('js/chat/e2ee/team-messenger.js'));

        $this->assertMatchesRegularExpression('/<button type="button" x-on:click="show\(\)"[^>]*>Secure Devices<\/button>/', $team);
        $this->assertMatchesRegularExpression('/<button type="button" x-on:click="window\.dispatchEvent\(new CustomEvent\(\'team-e2ee-enable\'\)\)"[^>]*>Enable End-to-End Encryption<\/button>/', $team);
        $this->assertStringContainsString('x-on:team-e2ee-enable.window="enable"', $team);
        $this->assertStringContainsString('x-show="loading"', $team);
        $this->assertStringContainsString('export function createE2eeDeviceManager(config, dependencies = {})', $runtime);
        $this->assertStringContainsString('async show()', $runtime);
        $this->assertStringContainsString('export function createTeamE2eeMessenger(config)', $runtime);
        $this->assertStringContainsString('async enable()', $runtime);
        $this->assertStringContainsString('window.BrahmaE2eeDevices = createE2eeDeviceManager;', $runtime);
        $this->assertStringContainsString('window.BrahmaE2eeTeam = createTeamE2eeMessenger;', $runtime);
        $this->assertStringContainsString('const deviceRegistrations = new Map();', $runtime);
    }

    public function test_team_domain_still_mounts_the_real_child_component(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $agent = User::factory()->create();
        $agent->assignRole('agent');

        Livewire::actingAs($agent)->test(SupportInbox::class)
            ->call('selectDomain', 'team')
            ->assertSet('domain', 'team')
            ->assertSeeLivewire('admin.team-messenger');

        $response = $this->actingAs($agent)->get(route('agent.inbox', ['domain' => 'team']));
        $response->assertOk()->assertSee('Secure Devices');

        $html = $response->getContent();
        $runtimePosition = strpos($html, 'resources/js/chat/e2ee/team-messenger.js');
        if ($runtimePosition === false) {
            $runtimePosition = strpos($html, 'team-messenger-');
        }
        $this->assertNotFalse($runtimePosition);
        $this->assertNotFalse(strpos($html, 'window.BrahmaE2eeDevices('));
        $this->assertLessThan(
            strpos($html, 'window.BrahmaE2eeDevices('),
            $runtimePosition,
            'The E2EE module must be loaded before Alpine evaluates the dynamically rendered Team scope.',
        );
    }
}
