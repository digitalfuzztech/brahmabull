<div>
    <div class="flex justify-between items-center mb-6">

        <h1 class="text-2xl font-bold text-white">Games</h1>

        <button
            wire:click="$set('showAddModal', true)"
            class="px-4 py-2 bg-purple-600 rounded-xl"
        >
            + Add Game
        </button>

    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @foreach($this->games as $game)

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800">

                <!-- CLICK GAME PAGE -->
                <a href="{{ route('admin.games.show', $game->id) }}">

                    <!-- FIXED IMAGE -->
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::url($game->image) }}"
                        class="h-40 w-full object-cover rounded-xl"
                    >

                    <h2 class="mt-2 text-white font-bold">
                        {{ $game->name }}
                    </h2>
                </a>

                <!-- STATUS -->
                <div class="mt-3 flex justify-between items-center">

                    <span class="{{ $game->is_active ? 'text-green-400' : 'text-gray-400' }}">
                        {{ $game->is_active ? 'Active' : 'Disabled' }}
                    </span>

                    <button
                        wire:click="toggleGame({{ $game->id }})"
                        class="px-3 py-1 text-xs rounded-lg
                        {{ $game->is_active ? 'bg-green-600' : 'bg-gray-600' }}"
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
                    <h2 class="text-white font-bold">Add Game</h2>

                    <button wire:click="$set('showAddModal', false)">✕</button>
                </div>

                <div class="p-5 space-y-4">

                    <input wire:model="name" placeholder="Game Name"
                           class="w-full bg-slate-800 rounded-xl p-2 text-white">

                    <input wire:model="game_url" placeholder="Game URL"
                           class="w-full bg-slate-800 rounded-xl p-2 text-white">

                    <label
                        for="game_image"
                        class="group mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-4 transition hover:border-indigo-500 hover:bg-slate-800"
                    >

                        @if($image)

                            <img
                                src="{{ $image->temporaryUrl() }}"
                                class="w-20 h-20 rounded-xl object-cover border-2 border-indigo-500"
                            >

                            <span class="mt-2 text-indigo-300 text-sm">
            Click to change image
        </span>

                        @else

                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-8 w-8 text-slate-400 group-hover:text-indigo-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>

                            <span class="mt-2 text-slate-300 text-sm">
            Upload Game Image
        </span>

                            <span class="text-xs text-slate-500 mt-1">
            Click to browse
        </span>

                        @endif

                        <input
                            id="game_image"
                            type="file"
                            wire:model="image"
                            class="hidden"
                            accept="image/*"
                        />

                    </label>

                    <div wire:loading wire:target="image" class="mt-2 text-sm text-indigo-400">
                        Uploading image...
                    </div>

                </div>

                <div class="p-5 flex justify-end gap-3 border-t border-slate-800">

                    <button wire:click="$set('showAddModal', false)"
                            class="px-4 py-2 bg-gray-700 rounded-xl">
                        Cancel
                    </button>

                    <button wire:click="createGame"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 bg-purple-600 rounded-xl">
                        <span wire:loading.remove wire:target="createGame">
    Save
</span>

                        <span wire:loading wire:target="createGame">
    Saving...
</span>
                    </button>

                </div>

            </div>

        </div>

    @endif
</div>
