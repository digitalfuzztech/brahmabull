<div>

    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">

        <h1 class="text-2xl font-bold text-white">
            Wallet Agents
        </h1>

        <button
            wire:click="$set('showAddModal', true)"
            class="px-4 py-2 bg-purple-600 rounded-xl"
        >
            + Add Agent
        </button>

    </div>

    <!-- AGENT CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @foreach($this->agents as $agent)
            @if(auth()->user()->hasRole('admin'))
                <a href="{{ url('/admin/accounts/agent-' . $agent->id) }}"
                   class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-purple-500 transition">

                    <h2 class="text-white font-bold text-lg">
                        {{ $agent->name }}
                    </h2>

                    <p class="text-xs mt-2 {{ $agent->is_active ? 'text-green-400' : 'text-gray-400' }}">
                        {{ $agent->is_active ? 'Active' : 'Disabled' }}
                    </p>

                </a>
            @endif

            @if(auth()->user()->hasRole('agent'))
                    <a href="{{ url('/agent/accounts/agent-' . $agent->id) }}"
                       class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-purple-500 transition">

                        <h2 class="text-white font-bold text-lg">
                            {{ $agent->name }}
                        </h2>

                        <p class="text-xs mt-2 {{ $agent->is_active ? 'text-green-400' : 'text-gray-400' }}">
                            {{ $agent->is_active ? 'Active' : 'Disabled' }}
                        </p>

                    </a>
            @endif


        @endforeach

    </div>

    <!-- ADD MODAL -->
    @if($showAddModal)

        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

            <div class="w-full max-w-md bg-slate-900 rounded-2xl border border-slate-700">

                <!-- HEADER -->
                <div class="p-5 border-b border-slate-800 flex justify-between">
                    <h2 class="text-white font-bold">Add Wallet Agent</h2>

                    <button wire:click="$set('showAddModal', false)">✕</button>
                </div>

                <!-- BODY -->
                <div class="p-5 space-y-4">

                    <input
                        wire:model="name"
                        placeholder="Agent Name (e.g. Storm)"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                    />

                </div>

                <!-- FOOTER -->
                <div class="p-5 flex justify-end gap-3 border-t border-slate-800">

                    <button
                        wire:click="$set('showAddModal', false)"
                        class="px-4 py-2 bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>

                    <button
                        wire:click="createAgent"
                        class="px-4 py-2 bg-purple-600 rounded-xl"
                    >
                        Save
                    </button>

                </div>

            </div>

        </div>

    @endif

</div>
