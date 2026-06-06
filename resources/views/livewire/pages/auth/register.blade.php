<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Referral;
use App\Models\Notification;

new #[Layout('layouts.guest')] class extends Component
{
    use WithFileUploads;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public $photo = null;
    public bool $registered = false;
    public string $referral_code = '';
    public string $phone = '';

    /**
     * Handle an incoming registration request.
     */
    public function mount()
    {
        $this->referral_code = request('ref', '');
    }


    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                function ($attribute, $value, $fail) {
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('Password must include at least 1 uppercase letter.');
                    }
                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('Password must include at least 1 lowercase letter.');
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $fail('Password must include at least 1 number.');
                    }
                    if (!preg_match('/[@$!%*#?&]/', $value)) {
                        $fail('Password must include at least 1 symbol.');
                    }
                }
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
            'referral_code' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $photoPath = null;

        if ($this->photo) {
            $photoPath = $this->photo->store('profiles', 'public');
        }

        $referrer = null;

        if (!empty($validated['referral_code'])) {
            $referrer = User::where('referral_code', $validated['referral_code'])->first();
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'photo' => $photoPath,
            'referred_by' => $referrer?->id,
            'phone' => $validated['phone'],
            'email_verified_at' => now(),
        ]);
        $user->update([
            'referral_code' => 'REF' . $user->id . strtoupper(str()->random(4)),
        ]);
        if ($referrer) {

            Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $user->id,
            ]);

            Notification::create([
                'user_id' => $referrer->id,
                'type' => 'referral',
                'title' => 'New Referral Registered',
                'message' => $user->name .
                    ' has registered from your referral using referral code ' .
                    $referrer->referral_code,
            ]);

            $admins = User::role('admin')->get();

            foreach ($admins as $admin) {

                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'referral',
                    'title' => 'Referral Registered',
                    'message' => $user->name .
                        ' has registered through ' .
                        $referrer->name .
                        '\'s referral code ' .
                        $referrer->referral_code,
                ]);
            }

            $agents = User::role('agent')->get();

            foreach ($agents as $agent) {

                Notification::create([
                    'user_id' => $agent->id,
                    'type' => 'referral',
                    'title' => 'Referral Registered',
                    'message' => $user->name .
                        ' has registered through ' .
                        $referrer->name .
                        '\'s referral code ' .
                        $referrer->referral_code,
                ]);
            }
        }

        $user->syncRoles(['player']);



        $lastPlayerId = \App\Models\PlayerProfile::max('player_id');

        \App\Models\PlayerProfile::create([
            'user_id' => $user->id,
            'player_id' => $lastPlayerId
                ? $lastPlayerId + 1
                : 10001,
        ]);
        event(new Registered($user));

       // Auth::login($user);

       // $this->redirect(route('dashboard', absolute: false), navigate: true);
        $this->registered = true;
    }
}; ?>

<div class="">
    @if($registered)

        <div class="mb-6 rounded-2xl border border-green-500/30 bg-green-500/10 p-5 text-green-300">

            <h3 class="font-bold text-lg">
                Registration Successful
            </h3>

            <p class="mt-2">
                A verification email has been sent to your email address.
            </p>

            <p class="mt-2">
                Please verify your email before logging in.
            </p>

        </div>

    @endif
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <div x-data="{ show:false }" class="relative mt-1">

                <x-text-input
                    wire:model="password"
                    id="password"
                    x-bind:type="show ? 'text' : 'password'"
                    class="block w-full pr-12"
                    name="password"
                    required
                    autocomplete="new-password"
                />

                <button
                    type="button"
                    @click="show=!show"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-400"
                >
                    {{-- Eye --}}
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"/>
                    </svg>

                    {{-- Eye Off --}}
                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"/>
                    </svg>
                </button>

            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <div x-data="{ show:false }" class="relative mt-1">

                <x-text-input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    x-bind:type="show ? 'text' : 'password'"
                    class="block w-full pr-12"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <button
                    type="button"
                    @click="show=!show"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-400"
                >
                    {{-- Eye --}}
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"/>
                    </svg>

                    {{-- Eye Off --}}
                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"/>
                    </svg>
                </button>

            </div>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>
        <!-- Phone -->
        <div class="mt-4">
            <x-input-label for="phone" value="Phone Number" />



                <x-text-input
                    wire:model="phone"
                    id="phone"
                    type="text"
                    class="w-full"
                    placeholder="+xxx xxxxxxxxxx"
                />


            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="referral_code" value="Referral Code (Optional)" />

            <x-text-input
                wire:model="referral_code"
                id="referral_code"
                type="text"
                class="block mt-1 w-full"
                placeholder="Enter referral code if you have one" />

            <x-input-error :messages="$errors->get('referral_code')" class="mt-2" />
        </div>
        <label
            for="photo"
            class="group mt-6 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-8 transition hover:border-indigo-500 hover:bg-slate-800"
        >

            @if($photo)

                <img
                    src="{{ $photo->temporaryUrl() }}"
                    class="w-sm h-sm rounded-full object-cover border-4 border-indigo-500"
                >

                <span class="mt-3 text-indigo-300">
            Click to change photo
        </span>

            @else

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-12 w-12 text-slate-400 group-hover:text-indigo-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>

                <span class="mt-4 text-slate-300">
            Upload Profile Picture
        </span>

                <span class="text-xs text-slate-500 mt-1">
            Click to browse
        </span>

            @endif

            <input
                id="photo"
                type="file"
                wire:model="photo"
                class="hidden w-10"
                accept="image/*"
            />

        </label>

        <div wire:loading wire:target="photo" class="mt-3 text-sm text-indigo-400">
            Uploading image...
        </div>
        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button
                class="ms-4"
                wire:loading.attr="disabled"
                wire:target="register"
            >
    <span wire:loading.remove wire:target="register">
        Register
    </span>

                <span wire:loading wire:target="register">
        Registering...
    </span>
            </x-primary-button>
        </div>
    </form>
</div>
