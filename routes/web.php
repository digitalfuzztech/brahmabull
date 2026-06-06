<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Public\HomePage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Livewire\Pages\Games;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Agent\Dashboard as AgentDashboard;
use App\Livewire\Admin\Players;
use App\Livewire\Admin\TopPlayers;


//Route::view('/', 'welcome');

Route::get('/', HomePage::class)
    ->name('home');

//Route::get('/dashboard', function () {
  //  return view('dashboard');
//})->middleware(['auth'])->name('dashboard');
//Route::view('dashboard', 'dashboard')
  //  ->middleware(['auth', 'verified'])
   // ->name('dashboard');
Route::middleware(['auth', 'role:admin'])->group(function () {

    Route::get('/admin', AdminDashboard::class)
        ->name('admin.dashboard');
    Route::get('/admin/agents', \App\Livewire\Admin\Agents::class)
        ->name('admin.agents');
    Route::get('/admin/agents/{id}', \App\Livewire\Admin\AgentShow::class)
        ->name('admin.agents.show');

    Route::get('/admin/catalog', \App\Livewire\Admin\Games::class)
        ->name('admin.games');

    Route::get('/admin/catalog/{game}', \App\Livewire\Admin\GameShow::class)
        ->name('admin.games.show');

    Route::get('/admin/accounts', \App\Livewire\Admin\WalletAgents::class)
        ->name('admin.wallets');
    Route::get('/admin/accounts/agent-{agent}', \App\Livewire\Admin\WalletAgentShow::class)
        ->name('admin.wallets.agent');
    Route::get(
        '/admin/accounts/agent-{agent}/accounts-type-{type}',
        \App\Livewire\Admin\WalletTypeShow::class
    )->name('admin.wallets.type');
    Route::get(
        '/admin/accounts/agent-{agent}/accounts-type-{type}/{wallet}',
        \App\Livewire\Admin\WalletShow::class
    )->name('admin.wallets.wallet');

    Route::get('/admin/funding', \App\Livewire\Admin\Deposits::class)
        ->name('admin.deposits');
    Route::get('/admin/payout', \App\Livewire\Admin\Cashouts::class)
        ->name('admin.cashouts');

    Route::get('/admin/members', \App\Livewire\Admin\Players::class)
        ->name('admin.players');
    Route::get('/admin/members/all_members', \App\Livewire\Admin\PlayersAll::class)
        ->name('admin.players.all');
    Route::get(
        '/admin/members/top_members',
        TopPlayers::class
    )->name('admin.players.top');

    Route::get(
        '/admin/notifications',
        \App\Livewire\Admin\Notifications::class
    )->name('admin.notifications');
});

Route::middleware(['auth', 'role:agent'])->group(function () {

    Route::get('/agent', AgentDashboard::class)
        ->name('agent.dashboard');

    Route::get('/agent/funding', \App\Livewire\Admin\Deposits::class)
        ->name('agent.deposits');

    Route::get('/agent/accounts', \App\Livewire\Admin\WalletAgents::class)
        ->name('agent.wallets');
    Route::get('/agent/accounts/agent-{agent}', \App\Livewire\Admin\WalletAgentShow::class)
        ->name('agent.wallets.agent');
    Route::get(
        '/agent/accounts/agent-{agent}/accounts-type-{type}',
        \App\Livewire\Admin\WalletTypeShow::class
    )->name('agent.wallets.type');
    Route::get(
        '/agent/accounts/agent-{agent}/accounts-type-{type}/{wallet}',
        \App\Livewire\Admin\WalletShow::class
    )->name('agent.wallets.wallet');
    Route::get('/agent/catalog', \App\Livewire\Agent\Games::class)
       ->name('agent.games');

    Route::get('/agent/payout', \App\Livewire\Admin\Cashouts::class)
        ->name('agent.cashouts');

    Route::get('/agent/members', \App\Livewire\Admin\Players::class)
        ->name('agent.players');
    Route::get('/agent/members/all_members', \App\Livewire\Admin\PlayersAll::class)
        ->name('agent.players.all');
    Route::get(
        '/agent/members/top_members',
        TopPlayers::class
    )->name('agent.players.top');

    Route::get(
       '/agent/notifications',
        \App\Livewire\Admin\Notifications::class
    )->name('agent.notifications');
});


Route::middleware(['auth', 'role:player'])->group(function () {
  //  Route::get('/member', function () {
  //      return view('member.home');
 //   })->name('member');
    Route::get(
        '/notifications',
        \App\Livewire\Pages\Notifications::class
    )->middleware('auth')
        ->name('player.notifications');
    Route::get('/catalog', Games::class)->name('games');
});
Route::get(
    '/profile',
    \App\Livewire\Player\ProfilePage::class
)->middleware('auth')
    ->name('profile');



Route::get('/payout-request', \App\Livewire\Pages\Cashouts::class)
    ->middleware('auth')
    ->name('cashouts');

Route::post('/logout', function () {

    Auth::logout();

    Session::invalidate();

    Session::regenerateToken();

    return redirect('/');

})->name('logout');


require __DIR__.'/auth.php';
