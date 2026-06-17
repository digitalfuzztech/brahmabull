<div>
<aside
    id="sidebar"
    class="w-64 bg-slate-900 border-r border-slate-800
           fixed top-0 left-0 h-screen
           text-white flex flex-col
           transform -translate-x-full
           transition-all duration-300
           lg:translate-x-0 z-50"
>
    {{-- MOBILE CLOSE --}}
    <div class="lg:hidden flex justify-end p-3">
        <button onclick="closeSidebarMobile()" class="text-white text-xl">
            ✕
        </button>
    </div>

    {{-- HEADER --}}
    <div id="sidebarHeader" class="p-6 border-b border-slate-800 flex-shrink-0 text-center">
        <div class="flex flex-col items-center">
            <img src="{{ asset('images/logo-brahma.png') }}"
                 class="w-16 h-16 mb-2 transition-all duration-300">

            <h1 id="sidebarBrand"
                class="text-xl font-black text-purple-500 transition-all duration-300">
                BrahmaBull
            </h1>
        </div>

        <p id="sidebarTitle" class="text-xs text-slate-500 mt-1 sidebar-text">
            Control Panel
        </p>
    </div>

    <nav class="mt-6 space-y-1 overflow-y-auto flex-1 px-2 pb-6">
        @if(auth()->user()->hasRole('admin'))
            <a href="/admin"
               class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                <i data-lucide="layout-dashboard"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        @endif

        @if(auth()->user()->hasRole('agent'))
                <a href="/agent"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="layout-dashboard"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
        @endif

        @if(auth()->user()->hasRole('admin'))
                <a href="{{route('admin.agents')}}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="user-star"></i>
                    <span class="sidebar-text">Agents</span>
                </a>
        @endif


            @if(auth()->user()->hasRole('admin'))
                {{-- PLAYERS --}}
                <a href="{{ route('admin.players') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="users"></i>
                    <span class="sidebar-text">Players</span>
                </a>

                {{-- GAMES --}}
                <a href="{{ route('admin.games') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="gamepad-2"></i>
                    <span class="sidebar-text">Games</span>
                </a>
                {{-- Wallets --}}
                <a href="{{ route('admin.wallets') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="wallet"></i>
                    <span class="sidebar-text">Wallets</span>
                </a>

                {{-- Deposits --}}
                <a href="{{ route('admin.deposits') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="circle-dollar-sign"></i>
                    <span class="sidebar-text">Deposits</span>
                </a>

                {{-- CASHOUTS --}}
                <a href="{{ route('admin.cashouts') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="banknote-arrow-down"></i>
                    <span class="sidebar-text">Cashouts</span>
                </a>

                {{-- NOTIFICATIONS --}}
                <a href="{{ route('admin.notifications') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="bell"></i>
                    <span class="sidebar-text">Notifications</span>
                </a>
            @endif

            @if(auth()->user()->hasRole('agent'))
                {{-- PLAYERS --}}
                <a href="{{ route('agent.players') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="users"></i>
                    <span class="sidebar-text">Players</span>
                </a>

                {{-- GAMES --}}
                <a href="{{ route('agent.games') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="gamepad-2"></i>
                    <span class="sidebar-text">Games</span>
                </a>
                {{-- Wallets --}}
                <a href="{{ route('agent.wallets') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="wallet"></i>
                    <span class="sidebar-text">Wallets</span>
                </a>

                {{-- Deposits --}}
                <a href="{{ route('agent.deposits') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="bell"></i>
                    <span class="sidebar-text">Deposits</span>
                </a>

                {{-- CASHOUTS --}}
                <a href="{{ route('agent.cashouts') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="wallet"></i>
                    <span class="sidebar-text">Cashouts</span>
                </a>

                {{-- NOTIFICATIONS --}}
                <a href="{{ route('agent.notifications') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <i data-lucide="bell"></i>
                    <span class="sidebar-text">Notifications</span>
                </a>
            @endif

<!--
        <a href="#" class="block rounded-xl p-3 hover:bg-slate-800">
            Reports
        </a>



        <a href="#" class="block rounded-xl p-3 hover:bg-slate-800">
            Settings
        </a>
-->
    </nav>

</aside>
{{-- OVERLAY --}}
<div id="sidebarOverlay"
     onclick="closeSidebarMobile()"
     class="fixed inset-0 bg-black/40 hidden lg:hidden z-40">
</div>
</div>
