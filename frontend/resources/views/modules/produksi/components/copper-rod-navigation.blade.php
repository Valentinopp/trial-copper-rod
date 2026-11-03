<details class="master-nav-group copper-sidebar-group" @if(request()->routeIs('board', 'crystallizer', 'copper-break', 'output')) open @endif>
    <summary class="master-nav-heading copper-sidebar-heading">
        <span class="nav-icon"><x-copper-rod-icon /></span>
        <span class="nav-text"><strong>Copper Rod</strong></span>
        <svg class="master-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </summary>
    <nav class="master-nav-links" aria-label="Halaman Copper Rod">
        @foreach ([
            'board' => 'Papan Digital',
            'crystallizer' => 'Crystallizer',
            'copper-break' => 'Copper Putus',
            'output' => 'Rekap Output',
        ] as $routeName => $label)
            <a href="{{ route($routeName) }}" wire:navigate
               class="nav-item {{ request()->routeIs($routeName) ? 'active' : '' }}"
               @if(request()->routeIs($routeName)) aria-current="page" @endif>
                <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    @switch($routeName)
                        @case('board')
                            <rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3M7 8h4v6H7zM15 8h2M15 11h2M15 14h2"/>
                            @break
                        @case('crystallizer')
                            <g transform="rotate(-35 12 12)">
                                <ellipse cx="4" cy="12" rx="2.5" ry="4"/>
                                <path d="M4 8h15c1.4 0 2.5 1.8 2.5 4s-1.1 4-2.5 4H4M8 8h10"/>
                            </g>
                            @break
                        @case('copper-break')
                            <path d="M3 8h6l2 3-3 2 2 3H3M21 8h-6l1 3-3 2 2 3h6M12 2l-1 3M12 19l-1 3"/>
                            @break
                        @case('output')
                            <path d="M4 3v17h17M8 16v-5M13 16V7M18 16v-8"/>
                            @break
                    @endswitch
                </svg></span>
                <span class="nav-text"><strong>{{ $label }}</strong></span>
            </a>
        @endforeach
    </nav>
</details>
