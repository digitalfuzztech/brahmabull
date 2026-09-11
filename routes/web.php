<?php

use App\Http\Controllers\ChatE2eeAttachmentController;
use App\Http\Controllers\ChatE2eeConversationController;
use App\Http\Controllers\ChatE2eeDeviceController;
use App\Http\Controllers\ChatE2eeMessageController;
use App\Http\Controllers\ChatE2eeReactionController;
use App\Http\Controllers\TeamChatAttachmentController;
use App\Livewire\Admin\Agents;
use App\Livewire\Admin\AgentShow;
use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Admin\BrahmaPlays;
use App\Livewire\Admin\Cashouts;
use App\Livewire\Admin\ChatSettings;
use App\Livewire\Admin\Cms\GeneralSettings;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Deposits;
use App\Livewire\Admin\GameShow;
use App\Livewire\Admin\Notifications;
use App\Livewire\Admin\PlayerRankSettings;
use App\Livewire\Admin\Players;
use App\Livewire\Admin\PlayersAll;
use App\Livewire\Admin\SpinningWheel;
use App\Livewire\Admin\SupportInbox;
use App\Livewire\Admin\TopPlayers;
use App\Livewire\Admin\WalletAgents;
use App\Livewire\Admin\WalletAgentShow;
use App\Livewire\Admin\WalletShow;
use App\Livewire\Admin\WalletTypeShow;
use App\Livewire\Agent\Dashboard as AgentDashboard;
use App\Livewire\Pages\Games;
use App\Livewire\Player\ProfilePage;
use App\Livewire\Public\HomePage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;

// Route::view('/', 'welcome');

Route::get('/', HomePage::class)
    ->name('home');

Route::view('/privacy-policy', 'public.privacy-policy')
    ->name('privacy-policy');
Route::view('/terms-and-conditions', 'public.terms-and-conditions')
    ->name('terms-and-conditions');
Route::view('/guide-to-play', 'public.guide-to-play')
    ->name('guide-to-play');
Route::view('/brahmabull-rules', 'public.brahmabull-rules')
    ->name('brahmabull-rules');

// Route::get('/dashboard', function () {
//  return view('dashboard');
// })->middleware(['auth'])->name('dashboard');
// Route::view('dashboard', 'dashboard')
//  ->middleware(['auth', 'verified'])
// ->name('dashboard');
Route::middleware(['auth', 'role:admin'])->group(function () {

    Route::get('/admin', AdminDashboard::class)
        ->name('admin.dashboard');
    Route::get('/admin/agents', Agents::class)
        ->name('admin.agents');
    Route::get('/admin/agents/{id}', AgentShow::class)
        ->name('admin.agents.show');

    Route::get('/admin/catalog', App\Livewire\Admin\Games::class)
        ->name('admin.games');

    Route::get('/admin/catalog/{game}', GameShow::class)
        ->name('admin.games.show');

    Route::get('/admin/accounts', WalletAgents::class)
        ->name('admin.wallets');
    Route::get('/admin/accounts/agent-{agent}', WalletAgentShow::class)
        ->name('admin.wallets.agent');
    Route::get(
        '/admin/accounts/agent-{agent}/accounts-type-{type}',
        WalletTypeShow::class
    )->name('admin.wallets.type');
    Route::get(
        '/admin/accounts/agent-{agent}/accounts-type-{type}/{wallet}',
        WalletShow::class
    )->name('admin.wallets.wallet');

    Route::get('/admin/funding', Deposits::class)
        ->name('admin.deposits');
    Route::get('/admin/payout', Cashouts::class)
        ->name('admin.cashouts');

    Route::get('/admin/brahma-accounts/deposits', BrahmaDeposits::class)
        ->name('admin.brahma.deposits');
    Route::get('/admin/brahma-accounts/plays', BrahmaPlays::class)
        ->name('admin.brahma.plays');

    Route::get('/admin/members', Players::class)
        ->name('admin.players');
    Route::get('/admin/members/all_members', PlayersAll::class)
        ->name('admin.players.all');
    Route::get(
        '/admin/members/top_members',
        TopPlayers::class
    )->name('admin.players.top');

    Route::get(
        '/admin/notifications',
        Notifications::class
    )->name('admin.notifications');

    Route::get('/admin/inbox', SupportInbox::class)
        ->name('admin.inbox');
    Route::get('/admin/chat-settings', ChatSettings::class)
        ->name('admin.chat-settings');
    Route::get('/admin/spinning-wheel', SpinningWheel::class)
        ->name('admin.spinning-wheel');
    Route::get('/admin/player-rank-settings', PlayerRankSettings::class)
        ->name('admin.player-rank-settings');
    Route::get('/admin/cms/general-settings', GeneralSettings::class)
        ->name('admin.cms.general-settings');
});

Route::middleware(['auth', 'role:agent'])->group(function () {

    Route::get('/agent', AgentDashboard::class)
        ->name('agent.dashboard');

    Route::get('/agent/funding', Deposits::class)
        ->name('agent.deposits');

    Route::get('/agent/accounts', WalletAgents::class)
        ->name('agent.wallets');
    Route::get('/agent/accounts/agent-{agent}', WalletAgentShow::class)
        ->name('agent.wallets.agent');
    Route::get(
        '/agent/accounts/agent-{agent}/accounts-type-{type}',
        WalletTypeShow::class
    )->name('agent.wallets.type');
    Route::get(
        '/agent/accounts/agent-{agent}/accounts-type-{type}/{wallet}',
        WalletShow::class
    )->name('agent.wallets.wallet');
    Route::get('/agent/catalog', App\Livewire\Agent\Games::class)
        ->name('agent.games');

    Route::get('/agent/payout', Cashouts::class)
        ->name('agent.cashouts');

    Route::get('/agent/brahma-accounts/deposits', BrahmaDeposits::class)
        ->name('agent.brahma.deposits');
    Route::get('/agent/brahma-accounts/plays', BrahmaPlays::class)
        ->name('agent.brahma.plays');

    Route::get('/agent/members', Players::class)
        ->name('agent.players');
    Route::get('/agent/members/all_members', PlayersAll::class)
        ->name('agent.players.all');
    Route::get(
        '/agent/members/top_members',
        TopPlayers::class
    )->name('agent.players.top');

    Route::get(
        '/agent/notifications',
        Notifications::class
    )->name('agent.notifications');

    Route::get('/agent/inbox', SupportInbox::class)
        ->name('agent.inbox');
});

Route::middleware(['auth', 'role:player'])->group(function () {
    //  Route::get('/member', function () {
    //      return view('member.home');
    //   })->name('member');
    Route::get(
        '/notifications',
        App\Livewire\Pages\Notifications::class
    )->middleware('auth')
        ->name('player.notifications');
    Route::get('/catalog', Games::class)->name('games');
});

Route::middleware('auth')->group(function () {
    Route::get('/team-chat/attachments/{attachment}', [TeamChatAttachmentController::class, 'view'])
        ->name('team.attachments.view');
    Route::get('/team-chat/attachments/{attachment}/download', [TeamChatAttachmentController::class, 'download'])
        ->name('team.attachments.download');
});

Route::middleware(['auth', 'role:admin|agent', 'throttle:30,1'])
    ->prefix('team-chat/e2ee')
    ->name('team.e2ee.')
    ->group(function (): void {
        Route::get('/devices', [ChatE2eeDeviceController::class, 'index'])->name('devices.index');
        Route::post('/devices', [ChatE2eeDeviceController::class, 'store'])->name('devices.store');
        Route::get('/devices/{device}/approval-plan', [ChatE2eeDeviceController::class, 'approvalPlan'])
            ->name('devices.approval-plan');
        Route::post('/devices/{device}/approve', [ChatE2eeDeviceController::class, 'approve'])
            ->name('devices.approve');
        Route::get('/devices/{device}/restoration-plan', [ChatE2eeDeviceController::class, 'restorationPlan'])
            ->name('devices.restoration-plan');
        Route::post('/devices/{device}/restore', [ChatE2eeDeviceController::class, 'restore'])
            ->name('devices.restore');
        Route::delete('/devices/{device}', [ChatE2eeDeviceController::class, 'destroy'])->name('devices.destroy');
        Route::get('/conversations/{conversation}/devices', [ChatE2eeConversationController::class, 'devices'])
            ->name('conversations.devices');
        Route::post('/conversations/{conversation}/activate', [ChatE2eeConversationController::class, 'activate'])
            ->name('conversations.activate');
        Route::post('/conversations/{conversation}/disable-request', [ChatE2eeConversationController::class, 'requestDisable'])
            ->name('conversations.disable-request');
        Route::delete('/conversations/{conversation}/disable-request', [ChatE2eeConversationController::class, 'keepEncryption'])
            ->name('conversations.disable-request.destroy');
        Route::post('/conversations/{conversation}/disable', [ChatE2eeConversationController::class, 'approveDisable'])
            ->name('conversations.disable');
        Route::post('/conversations/{conversation}/rotate', [ChatE2eeConversationController::class, 'rotate'])
            ->name('conversations.rotate');
        Route::get('/conversations/{conversation}/wrapped-key', [ChatE2eeConversationController::class, 'wrappedKey'])
            ->name('conversations.wrapped-key');
        Route::post('/conversations/{conversation}/messages', [ChatE2eeMessageController::class, 'store'])
            ->name('conversations.messages.store');
        Route::post('/conversations/{conversation}/attachments', [ChatE2eeAttachmentController::class, 'store'])
            ->name('conversations.attachments.store');
        Route::patch('/messages/{message}', [ChatE2eeMessageController::class, 'update'])
            ->name('messages.update');
        Route::put('/messages/{message}/reaction', [ChatE2eeReactionController::class, 'store'])
            ->name('messages.reactions.store');
        Route::delete('/messages/{message}/reaction', [ChatE2eeReactionController::class, 'destroy'])
            ->name('messages.reactions.destroy');
        Route::post('/conversations/{conversation}/keys', [ChatE2eeConversationController::class, 'storeKey'])
            ->name('conversations.keys.store');
        Route::get('/conversations/{conversation}/keys/next-version', [ChatE2eeConversationController::class, 'nextVersion'])
            ->name('conversations.keys.next-version');
    });
Route::get(
    '/profile',
    ProfilePage::class
)->middleware('auth')
    ->name('profile');

Route::get('/payout-request', App\Livewire\Pages\Cashouts::class)
    ->middleware('auth')
    ->name('cashouts');

Route::post('/logout', function () {

    Auth::logout();

    Session::invalidate();

    Session::regenerateToken();

    return redirect('/');

})->name('logout');

require __DIR__.'/auth.php';
