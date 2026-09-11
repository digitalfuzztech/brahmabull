<?php

namespace Tests\Feature;

use App\Livewire\Pages\BrahmaBalance;
use App\Livewire\Pages\Games as PlayerGames;
use App\Models\BrahmaBalanceTransaction;
use App\Models\BrahmaDeposit;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletAgent;
use App\Models\WalletType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepositSubmissionUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'agent', 'player'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_normal_deposit_requires_payment_screenshot_and_keeps_the_modal_ready_for_correction(): void
    {
        $player = $this->userWithRole('player');
        $game = $this->game();
        $wallet = $this->wallet();

        $component = Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->assertSee('Payment Reminder:')
            ->assertSee('generic payment remark')
            ->assertSeeHtml('wire:model="proofImage"')
            ->assertSeeHtml('wire:target="proofImage,submitDeposit"')
            ->set('amount', '25')
            ->set('paymentType', $wallet->type)
            ->set('selectedWallet', $wallet->id)
            ->call('submitDeposit')
            ->assertHasErrors(['proofImage' => 'required'])
            ->assertSee('Payment screenshot is required.')
            ->assertSet('showModal', true)
            ->assertSet('depositSubmitted', false);

        $this->assertSame(0, Deposit::count());
        $this->assertSame(0, Notification::count());

        $component
            ->call('closeModal')
            ->call('openPlayModal', $game->id)
            ->assertHasNoErrors()
            ->assertSet('amount', null)
            ->assertSet('proofImage', null);
    }

    public function test_normal_deposit_success_shows_close_and_reopens_with_clean_state(): void
    {
        Storage::fake('public');

        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $game = $this->game();
        $wallet = $this->wallet();

        $component = Livewire::actingAs($player)
            ->test(PlayerGames::class)
            ->call('openPlayModal', $game->id)
            ->set('amount', '25')
            ->set('paymentType', $wallet->type)
            ->set('selectedWallet', $wallet->id)
            ->set('proofImage', UploadedFile::fake()->image('normal-proof.jpg'))
            ->call('submitDeposit')
            ->assertHasNoErrors()
            ->assertSet('depositSubmitted', true)
            ->assertSee('Deposit Submitted Successfully')
            ->assertSee('Close')
            ->assertDontSeeHtml('wire:click="submitDeposit"');

        $deposit = Deposit::sole();

        $this->assertSame('pending', $deposit->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'deposit_submitted']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'deposit_created']);
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'deposit_created']);
        Storage::disk('public')->assertExists($deposit->proof_image);

        $component
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertSet('depositSubmitted', false)
            ->assertSet('depositReference', null)
            ->call('openPlayModal', $game->id)
            ->assertSet('amount', null)
            ->assertSet('proofImage', null)
            ->assertSet('depositSubmitted', false)
            ->assertSeeHtml('wire:click="submitDeposit"');

        $this->assertSame(1, Deposit::count());
    }

    public function test_brahma_deposit_requires_payment_screenshot_without_creating_financial_state(): void
    {
        $player = $this->userWithRole('player');
        $wallet = $this->wallet();

        Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->call('openModal')
            ->assertSee('Payment Reminder:')
            ->assertSee('generic payment remark')
            ->assertSeeHtml('wire:target="proofImage,submitDeposit"')
            ->set('amount', '100')
            ->set('paymentType', $wallet->type)
            ->set('selectedWallet', $wallet->id)
            ->call('submitDeposit')
            ->assertHasErrors(['proofImage' => 'required'])
            ->assertSee('Payment screenshot is required.')
            ->assertSet('showModal', true)
            ->assertSet('depositSubmitted', false);

        $this->assertSame(0, BrahmaDeposit::count());
        $this->assertSame(0, BrahmaBalanceTransaction::count());
        $this->assertSame('0.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, Notification::count());
    }

    public function test_brahma_deposit_success_shows_close_without_crediting_and_reopens_cleanly(): void
    {
        Storage::fake('public');

        $player = $this->userWithRole('player');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $wallet = $this->wallet();

        $component = Livewire::actingAs($player)
            ->test(BrahmaBalance::class)
            ->call('openModal')
            ->set('amount', '100')
            ->set('paymentType', $wallet->type)
            ->set('selectedWallet', $wallet->id)
            ->set('proofImage', UploadedFile::fake()->image('brahma-proof.jpg'))
            ->call('submitDeposit')
            ->assertHasNoErrors()
            ->assertSet('depositSubmitted', true)
            ->assertSee('Deposit Submitted Successfully')
            ->assertSee('Close')
            ->assertDontSeeHtml('wire:click="submitDeposit"');

        $deposit = BrahmaDeposit::sole();

        $this->assertSame('pending', $deposit->status);
        $this->assertSame('0.00', $player->fresh()->brahma_balance);
        $this->assertSame(0, BrahmaBalanceTransaction::count());
        $this->assertDatabaseHas('notifications', ['user_id' => $player->id, 'type' => 'brahma_deposit_submitted']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'brahma_deposit_created']);
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'brahma_deposit_created']);
        Storage::disk('public')->assertExists($deposit->proof_image);

        $component
            ->call('closeModal')
            ->assertSet('showModal', false)
            ->assertSet('depositSubmitted', false)
            ->assertSet('depositReference', null)
            ->assertSet('amount', null)
            ->assertSet('proofImage', null)
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertSet('depositSubmitted', false)
            ->assertSet('amount', null)
            ->assertSet('proofImage', null)
            ->assertSeeHtml('wire:click="submitDeposit"');

        $this->assertSame(1, BrahmaDeposit::count());
        $this->assertSame(0, BrahmaBalanceTransaction::count());
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

    private function game(): Game
    {
        return Game::create([
            'name' => 'Deposit UX Game',
            'slug' => 'deposit-ux-game-'.fake()->unique()->numberBetween(1000, 9999),
            'image' => 'games/test.jpg',
            'game_url' => 'example.com/play',
            'is_active' => true,
        ]);
    }

    private function wallet(): Wallet
    {
        $owner = $this->userWithRole('admin');
        $walletAgent = WalletAgent::create([
            'name' => 'Deposit UX Agent',
            'slug' => 'deposit-ux-agent-'.fake()->unique()->numberBetween(1000, 9999),
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
        $walletType = WalletType::create([
            'wallet_agent_id' => $walletAgent->id,
            'name' => 'CashApp',
            'slug' => 'deposit-ux-cashapp-'.fake()->unique()->numberBetween(1000, 9999),
        ]);

        return Wallet::create([
            'wallet_type_id' => $walletType->id,
            'wallet_agent_id' => $walletAgent->id,
            'type' => 'cashapp',
            'name' => 'Deposit UX Wallet',
            'account_identifier' => '$depositux',
            'qr_image' => 'wallets/deposit-ux-qr.png',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);
    }
}
