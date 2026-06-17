<?php

namespace App\Livewire\Player;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Deposit;
use App\Models\Cashout;
use App\Models\Referral;
use App\Models\GameAccount;
use Livewire\Attributes\Layout;
#[Layout('layouts.public')]
class ProfilePage extends Component
{
    use WithFileUploads;

    public $month;

    public $showEditModal = false;

    public $phone;

    public $password;
    public $password_confirmation;

    public $photo;
    public $username;
    public $year;
    public $current_password;
    public string $activeTab = 'deposits';
    public bool $showPassword = false;
    public bool $showPasswordConfirmation = false;

    public function mount()
    {
        $this->month = now()->month;
        $this->year = now()->year;

        $this->phone = auth()->user()->phone;
        $this->username = auth()->user()->username;
    }

    public function previousMonth()
    {
        $this->month--;

        if ($this->month < 1) {
            $this->month = 12;
            $this->year--;
        }
    }

    public function nextMonth()
    {
        $this->month++;

        if ($this->month > 12) {
            $this->month = 1;
            $this->year++;
        }
    }

    public function saveProfile()
    {
        $user = auth()->user();

        // Detect if anything is being changed
        $isChangingPhone = $this->phone !== $user->phone;
        $isChangingUsername = $this->username !== $user->username;
        $isChangingPhoto = $this->photo !== null;
        $isChangingPassword = !empty($this->password);

        if ($isChangingPhone || $isChangingUsername || $isChangingPhoto || $isChangingPassword) {
            $this->resetErrorBag('current_password');
            // MUST VERIFY CURRENT PASSWORD
            if (empty($this->current_password)) {
                $this->addError('current_password', 'Please enter your current password.');
                return;
            }

            if (!Hash::check($this->current_password, $user->password)) {
                $this->addError('current_password', 'Your current password is wrong.');
                return;
            }
        }

        $rules = [
            'phone' => ['nullable','string','max:20'],
            'username' => [
                'required',
                'string',
                'min:4',
                'max:20',
                'alpha_dash',
                'unique:users,username,' . $user->id,
            ],
            'photo' => ['nullable','image','max:2048'],
        ];

        if ($this->password) {

            $rules['password'] = [
                'required',
                'confirmed',
                'min:8',
                function ($attribute, $value, $fail) {

                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('Password must contain uppercase.');
                    }

                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('Password must contain lowercase.');
                    }

                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('Password must contain number.');
                    }

                    if (!preg_match('/[@$!%*#?&]/', $value)) {
                        $fail('Password must contain symbol.');
                    }
                }
            ];
        }

        $this->validate($rules);

        $user->username = $this->username;
        // UPDATE PHONE
        $user->phone = $this->phone;

        // UPDATE PHOTO
        if ($this->photo) {

            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            $user->photo = $this->photo->store('profiles', 'public');
        }

        // UPDATE PASSWORD
        if ($this->password) {
            $user->password = Hash::make($this->password);
        }

        $user->save();

        $this->reset([
            'password',
            'password_confirmation',
            'photo',
            'current_password',
            'username'
        ]);

        $this->showEditModal = false;

        session()->flash('success', 'Profile updated successfully.');
    }

    public function openEditModal()
    {
        $user = auth()->user();

        $this->username = $user->username;
        $this->phone = $user->phone;

        // Always clear sensitive fields
        $this->password = null;
        $this->password_confirmation = null;
        $this->current_password = null;

        $this->resetErrorBag();

        $this->showEditModal = true;
    }
    public function closeModal()
    {
        $this->reset([
            'username',
            'password',
            'password_confirmation',
            'photo',
            'current_password',
        ]);
        $this->phone = auth()->user()->phone;
        $this->resetErrorBag();
        $this->showEditModal = false;
    }
    public function render()
    {
        $user = auth()->user();
        $gameAccounts = GameAccount::all()
            ->keyBy(fn($a) => $a->user_id.'-'.$a->game_id);
        return view('livewire.player.profile-page', [
            'gameAccounts' => $gameAccounts,
            'totalDeposits' =>
                Deposit::where(
                    'user_id',
                    $user->id
                )->sum('amount'),

            'totalCashouts' =>
                Cashout::where(
                    'user_id',
                    $user->id
                )->sum('amount'),

            'totalReferrals' =>
                Referral::where(
                    'referrer_id',
                    $user->id
                )->count(),

            'monthDeposits' =>
                Deposit::where(
                    'user_id',
                    $user->id
                )
                    ->whereMonth(
                        'created_at',
                        $this->month
                    )
                    ->sum('amount'),

            'monthCashouts' =>
                Cashout::where(
                    'user_id',
                    $user->id
                )
                    ->whereMonth(
                        'created_at',
                        $this->month
                    )
                    ->sum('amount'),

            'monthReferrals' =>
                Referral::where(
                    'referrer_id',
                    $user->id
                )
                    ->whereMonth(
                        'created_at',
                        $this->month
                    )
                    ->count(),
            'depositRows' => Deposit::query()
                ->where('user_id', auth()->id())
                ->where('status', 'verified')
                ->whereMonth('verified_at', $this->month)
                ->whereYear('verified_at', $this->year)
                ->latest('verified_at')
                ->get(),

            'cashoutRows' => Cashout::query()
                ->where('user_id', auth()->id())
                ->where('status', 'paid')
                ->whereMonth('paid_at', $this->month)
                ->whereYear('paid_at', $this->year)
                ->latest('paid_at')
                ->get(),

            'referralRows' => Referral::query()
                ->with('referredUser')
                ->where('referrer_id', auth()->id())
                ->whereMonth('created_at', $this->month)
                ->whereYear('created_at', $this->year)
                ->latest()
                ->get(),
        ]);
    }
}
