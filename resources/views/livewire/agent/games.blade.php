<div>
    <div class="flex justify-between items-center mb-6">

        <h1 class="text-2xl font-bold text-white">View Games</h1>

    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @foreach($this->games as $game)

            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800">

                <!-- CLICK GAME PAGE -->
                <div>

                    <!-- FIXED IMAGE -->
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::url($game->image) }}"
                        class="h-40 w-full object-cover rounded-xl"
                    >

                    <h2 class="mt-2 text-white font-bold">
                        {{ $game->name }}
                    </h2>
                </div>

                <!-- STATUS -->
                <div class="mt-3 flex justify-between items-center">

                    <span class="{{ $game->is_active ? 'text-green-400' : 'text-gray-400' }}">
                        {{ $game->is_active ? 'Active' : 'Disabled' }}
                    </span>

                </div>

            </div>

        @endforeach

    </div>

</div>
