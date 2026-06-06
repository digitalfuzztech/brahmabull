<div class="p-6 space-y-6">

    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-white">Agents</h1>

        <button
            wire:click="$set('showAddModal', true)"
            class="px-4 py-2 bg-purple-600 rounded-xl"
        >
            + Add Agent
        </button>
    </div>


    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @foreach($this->agents as $agent)

            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">

                <!-- CLICK CARD -->
                <a href="{{ route('admin.agents.show', $agent->id) }}">
                    <h2 class="text-lg font-bold text-white">
                        {{ $agent->name }}
                    </h2>

                    <p class="text-sm text-slate-400">
                        {{ $agent->email }}
                    </p>
                </a>

                <!-- STATUS -->
                <div class="mt-4 flex items-center justify-between">

            <span class="{{ $agent->is_active ? 'text-green-400' : 'text-gray-400' }}">
                {{ $agent->is_active ? 'Active' : 'Disabled' }}
            </span>

                    <button
                        wire:click="toggleAgent({{ $agent->id }})"
                        class="px-3 py-1 rounded-lg text-xs
                {{ $agent->is_active ? 'bg-green-600' : 'bg-gray-600' }}"
                    >
                        Toggle
                    </button>

                </div>

            </div>

        @endforeach

    </div>
    @if($showAddModal)

        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

            <div class="w-full max-w-md bg-slate-900 rounded-2xl border border-slate-700">

                <div class="p-5 border-b border-slate-800 flex justify-between">
                    <h2 class="text-white font-bold">Add Agent</h2>

                    <button wire:click="$set('showAddModal', false)">✕</button>
                </div>

                <div class="p-5 space-y-4">

                    <input wire:model="name" placeholder="Name"
                           class="w-full bg-slate-800 rounded-xl p-2 text-white">

                    <input wire:model="email" placeholder="Email"
                           class="w-full bg-slate-800 rounded-xl p-2 text-white">

                    <div x-data="{ show:false }" class="relative">

                        <input
                            wire:model="password"
                            x-bind:type="show ? 'text' : 'password'"
                            placeholder="Password"
                            class="w-full bg-slate-800 rounded-xl p-2 pr-12 text-white"
                        >

                        <button
                            type="button"
                            @click="show=!show"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-purple-400"
                        >

                            {{-- Eye --}}
                            <svg x-show="!show"
                                 xmlns="http://www.w3.org/2000/svg"
                                 class="h-5 w-5"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"/>
                            </svg>

                            {{-- Eye Off --}}
                            <svg x-show="show"
                                 xmlns="http://www.w3.org/2000/svg"
                                 class="h-5 w-5"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"/>
                            </svg>

                        </button>

                    </div>

                    <input wire:model="phone" placeholder="Phone"
                           class="w-full bg-slate-800 rounded-xl p-2 text-white">

                </div>

                <div class="p-5 flex justify-end gap-3 border-t border-slate-800">

                    <button wire:click="$set('showAddModal', false)"
                            class="px-4 py-2 bg-gray-700 rounded-xl">
                        Cancel
                    </button>

                    <button wire:click="createAgent"
                            class="px-4 py-2 bg-purple-600 rounded-xl">
                        Save
                    </button>

                </div>

            </div>

        </div>

    @endif
</div>
