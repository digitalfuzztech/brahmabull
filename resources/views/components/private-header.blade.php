
<div>
    <header id="headerBar"
        class="fixed left-0 right-0 lg:left-64 top-0 z-40 border-b border-slate-800 bg-slate-950/80 backdrop-blur-xl transition-all duration-300"
    >

        <div class="h-20 px-8 flex items-center justify-between">

            <div class="flex gap-3 items-center">
                <button onclick="toggleSidebar()"
                        id="sidebarToggleBtn"
                        class="p-2 rounded text-purple-800 font-bold bg-white hover:bg-gray-300">

                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-logs-icon lucide-logs"><path d="M3 5h1"/><path d="M3 12h1"/><path d="M3 19h1"/><path d="M8 5h1"/><path d="M8 12h1"/><path d="M8 19h1"/><path d="M13 5h8"/><path d="M13 12h8"/><path d="M13 19h8"/></svg>
                </button>
                <h2 class="text-sm md:text-xl font-bold">
                    Control Panel
                </h2>

            </div>

            <div class="flex items-center gap-6">

                <livewire:admin.notification-bell />

                <div class="text-sm">

                    {{ auth()->user()->name }}

                </div>


                <button
                    x-data
                    @click="$dispatch('open-logout-modal')"
                    class="rounded-xl border border-red-500 px-4 py-2 text-sm font-semibold text-red-400 hover:bg-red-500 hover:text-white transition"
                >
                    Logout
                </button>


            </div>

        </div>
        <form
            id="logout-form"
            method="POST"
            action="{{ route('logout') }}"
            class="hidden"
        >
            @csrf
        </form>


    </header>
    <div
        x-data="{ open: false, loading:false }"
        x-on:open-logout-modal.window="open = true"
        x-show="open"
        x-transition.opacity
        x-cloak
        class="fixed inset-0 z-[99999]"
    >

        <!-- BACKDROP -->
        <div
            class="fixed inset-0 bg-black/70 backdrop-blur-2xl"
            @click="open = false"
        ></div>

        <!-- MODAL WRAPPER (THIS IS THE KEY FIX) -->
        <div class="fixed inset-0 flex items-center justify-center p-4">

            <!-- MODAL -->
            <div
                class="w-full max-w-sm rounded-3xl border border-slate-700 bg-slate-900 p-8 shadow-2xl"
            >

                <h2 class="mb-3 text-center text-2xl font-black text-white">
                    Logout?
                </h2>

                <p class="mb-8 text-center text-slate-300">
                    Are you sure you want to log out of your account?
                </p>

                <div class="flex gap-3">

                    <button
                        @click="open = false"
                        class="flex-1 rounded-xl border border-slate-600 py-3 font-semibold text-slate-300 hover:bg-slate-800"
                    >
                        Cancel
                    </button>

                    <button
                        @click="loading = true; setTimeout(() => document.getElementById('logout-form').submit(), 600)"
                        :disabled="loading"
                        class="flex-1 rounded-xl bg-red-600 py-3 font-bold text-white hover:bg-red-700 flex items-center justify-center gap-2 disabled:opacity-70"
                    >

                        <svg
                            x-show="loading"
                            class="w-4 h-4 animate-spin"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>

                        <span x-text="loading ? 'Logging out...' : 'Logout'"></span>

                    </button>

                </div>

            </div>

        </div>

    </div>
</div>
