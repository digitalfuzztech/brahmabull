<?php

use App\Http\Controllers\TeamChatAttachmentController;
use App\Livewire\Admin\Agents;
use App\Livewire\Admin\AgentShow;
use App\Livewire\Admin\BrahmaDeposits;
use App\Livewire\Admin\BrahmaPlays;
use App\Livewire\Admin\Cashouts;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Deposits;
use App\Livewire\Admin\GameShow;
use App\Livewire\Admin\Notifications;
use App\Livewire\Admin\Players;
use App\Livewire\Admin\PlayersAll;
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
