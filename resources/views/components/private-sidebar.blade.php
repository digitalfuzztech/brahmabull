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
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                <span class="sidebar-text">Dashboard</span>
            </a>
        @endif

        @if(auth()->user()->hasRole('agent'))
                <a href="/agent"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard-icon lucide-layout-dashboard"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span class="sidebar-text">Dashboard</span>
                </a>
        @endif

        @if(auth()->user()->hasRole('admin'))
                <a href="{{route('admin.agents')}}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-star-icon lucide-user-star"><path d="M16.051 12.616a1 1 0 0 1 1.909.024l.737 1.452a1 1 0 0 0 .737.535l1.634.256a1 1 0 0 1 .588 1.806l-1.172 1.168a1 1 0 0 0-.282.866l.259 1.613a1 1 0 0 1-1.541 1.134l-1.465-.75a1 1 0 0 0-.912 0l-1.465.75a1 1 0 0 1-1.539-1.133l.258-1.613a1 1 0 0 0-.282-.866l-1.156-1.153a1 1 0 0 1 .572-1.822l1.633-.256a1 1 0 0 0 .737-.535z"/><path d="M8 15H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/></svg>
                    <span class="sidebar-text">Agents</span>
                </a>
        @endif


            @if(auth()->user()->hasRole('admin'))
                {{-- PLAYERS --}}
                <a href="{{ route('admin.players') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                    <span class="sidebar-text">Players</span>
                </a>

                {{-- GAMES --}}
                <a href="{{ route('admin.games') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-gamepad2-icon lucide-gamepad-2"><line x1="6" x2="10" y1="11" y2="11"/><line x1="8" x2="8" y1="9" y2="13"/><line x1="15" x2="15.01" y1="12" y2="12"/><line x1="18" x2="18.01" y1="10" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.151A4 4 0 0 0 17.32 5z"/></svg>
                    <span class="sidebar-text">Games</span>
                </a>
                {{-- Wallets --}}
                <a href="{{ route('admin.wallets') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wallet-icon lucide-wallet"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
                    <span class="sidebar-text">Wallets</span>
                </a>

                {{-- Deposits --}}
                <a href="{{ route('admin.deposits') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-dollar-sign-icon lucide-circle-dollar-sign"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                    <span class="sidebar-text">Deposits</span>
                </a>

                {{-- CASHOUTS --}}
                <a href="{{ route('admin.cashouts') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-banknote-arrow-down-icon lucide-banknote-arrow-down"><path d="M12 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5"/><path d="m16 19 3 3 3-3"/><path d="M18 12h.01"/><path d="M19 16v6"/><path d="M6 12h.01"/><circle cx="12" cy="12" r="2"/></svg>
                    <span class="sidebar-text">Cashouts</span>
                </a>

                {{-- NOTIFICATIONS --}}
                <a href="{{ route('admin.notifications') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-ring-icon lucide-bell-ring"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M22 8c0-2.3-.8-4.3-2-6"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/><path d="M4 2C2.8 3.7 2 5.7 2 8"/></svg>
                    <span class="sidebar-text">Notifications</span>
                </a>
            @endif

            @if(auth()->user()->hasRole('agent'))
                {{-- PLAYERS --}}
                <a href="{{ route('agent.players') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                    <span class="sidebar-text">Players</span>
                </a>

                {{-- GAMES --}}
                <a href="{{ route('agent.games') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-gamepad2-icon lucide-gamepad-2"><line x1="6" x2="10" y1="11" y2="11"/><line x1="8" x2="8" y1="9" y2="13"/><line x1="15" x2="15.01" y1="12" y2="12"/><line x1="18" x2="18.01" y1="10" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.151A4 4 0 0 0 17.32 5z"/></svg>
                    <span class="sidebar-text">Games</span>
                </a>
                {{-- Wallets --}}
                <a href="{{ route('agent.wallets') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wallet-icon lucide-wallet"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
                    <span class="sidebar-text">Wallets</span>
                </a>

                {{-- Deposits --}}
                <a href="{{ route('agent.deposits') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-dollar-sign-icon lucide-circle-dollar-sign"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>
                    <span class="sidebar-text">Deposits</span>
                </a>

                {{-- CASHOUTS --}}
                <a href="{{ route('agent.cashouts') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-banknote-arrow-down-icon lucide-banknote-arrow-down"><path d="M12 18H4a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5"/><path d="m16 19 3 3 3-3"/><path d="M18 12h.01"/><path d="M19 16v6"/><path d="M6 12h.01"/><circle cx="12" cy="12" r="2"/></svg>
                    <span class="sidebar-text">Cashouts</span>
                </a>

                {{-- NOTIFICATIONS --}}
                <a href="{{ route('agent.notifications') }}"
                   class="flex items-center gap-3 py-2 px-4 rounded-lg hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-ring-icon lucide-bell-ring"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M22 8c0-2.3-.8-4.3-2-6"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/><path d="M4 2C2.8 3.7 2 5.7 2 8"/></svg>
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
