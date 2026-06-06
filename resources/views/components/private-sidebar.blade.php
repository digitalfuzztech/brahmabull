<aside
    class="w-72 bg-slate-900 border-r border-slate-800 flex-shrink-0"
>

    <div class="p-6 border-b border-slate-800">

        <h1 class="text-2xl font-black text-purple-500">
            BrahmaBull
        </h1>

        <p class="text-xs text-slate-500 mt-1">
            Control Panel
        </p>

    </div>

    <nav class="p-4 space-y-2">
        @if(auth()->user()->hasRole('admin'))
            <a href="/admin" class="block rounded-xl p-3 hover:bg-slate-800">
                Dashboard
            </a>
        @endif

        @if(auth()->user()->hasRole('agent'))
                <a href="/agent" class="block rounded-xl p-3 hover:bg-slate-800">
                    Dashboard
                </a>
        @endif

        @if(auth()->user()->hasRole('admin'))
            <a href="{{route('admin.agents')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                Agents
            </a>
        @endif


            @if(auth()->user()->hasRole('admin'))
                <a href="{{route('admin.players')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Players
                </a>
                <a href="{{route('admin.games')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Games
                </a>

                <a href="{{route('admin.wallets')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Wallets
                </a>
                <a href="{{route('admin.deposits')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Deposits
                </a>
                <a href="{{route('admin.cashouts')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Cashouts
                </a>
                <a href="{{route('admin.notifications')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Notifications
                </a>
            @endif

            @if(auth()->user()->hasRole('agent'))
                <a href="{{route('agent.players')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Players
                </a>
                <a href="{{route('agent.games')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Games
                </a>
                <a href="{{route('agent.wallets')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Wallets
                </a>
                <a href="{{route('agent.deposits')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Deposits
                </a>
                <a href="{{route('agent.cashouts')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Cashouts
                </a>
                <a href="{{route('agent.notifications')}}" class="block rounded-xl p-3 hover:bg-slate-800">
                    Notifications
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
