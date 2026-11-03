        {{-- SIDEBAR KIRI --}}
        <aside class="sidebar" id="app-sidebar" aria-label="Menu navigasi">
            <div class="mobile-nav-header">
                <div><span>IRRIS.next</span><h2>Menu navigasi</h2></div>
                <button type="button" class="mobile-nav-close" popovertarget="app-sidebar" popovertargetaction="hide" aria-label="Tutup menu navigasi">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="brand-lockup">
                <div class="brand-main">
                    <div class="brand-mark brand-logo">
                        <img src="{{ asset('irrisnext.png') }}" alt="Logo irris.next" />
                    </div>
                    <div class="brand-copy">
                        <strong>IRRIS<span>.next</span></strong>
                    </div>
                </div>
                <small class="brand-slogan">Satu Sistem, Seluruh Operasi.</small>
            </div>

            <nav class="side-nav" aria-label="Navigasi utama">
                @if(request()->routeIs('master-data.*'))
                @can('access-master-data')
                <div class="nav-section-header"><span>MASTER DATA</span></div>
                @foreach([
                    'Barang & Produk' => ['categories' => 'Kategori', 'units' => 'UoM', 'items' => 'Barang', 'boms' => 'BoM'],
                    'Produksi Copper Rod' => ['take-ups' => 'Take Up', 'coilings' => 'Coiling', 'crystallizers' => 'Crystallizer', 'rod-specifications' => 'Spesifikasi Cu Rod', 'break-events' => 'Kejadian Saat Putus', 'break-conditions' => 'Kondisi Setelah Putus', 'break-categories' => 'Kategori Putus'],
                    'Mitra Bisnis' => ['suppliers' => 'Supplier', 'customers' => 'Customer'],
                    'Organisasi & Karyawan' => ['divisions' => 'Divisi', 'job-titles' => 'Jabatan', 'employees' => 'Karyawan', 'access-profiles' => 'Hak Akses'],
                ] as $group => $menus)
                <details class="master-nav-group" @if(collect(array_keys($menus))->contains(fn ($section) => request()->routeIs('master-data.'.$section))) open @endif>
                    <summary class="master-nav-heading"><span>{{ $group }}</span><svg class="master-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="master-nav-links">
                    @foreach($menus as $section => $label)
                    <a href="{{ route('master-data.'.$section) }}" aria-label="Master {{ $label }}" wire:navigate class="nav-item {{ request()->routeIs('master-data.'.$section) ? 'active' : '' }}" @if(request()->routeIs('master-data.'.$section)) aria-current="page" @endif>
                        <span class="nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @switch($section)
                                    @case('take-ups')
                                        <path d="M7 4h10M7 20h10M8 4v16M16 4v16M8 8h8M8 12h8M8 16h8M3 12h5M1 10l2 2-2 2"/>
                                        @break
                                    @case('coilings')
                                        <path d="M21 12a9 9 0 1 1-9-9 6 6 0 1 1-6 6 3 3 0 1 1 3 3h12"/>
                                        @break
                                    @case('crystallizers')
                                        <ellipse cx="12" cy="5" rx="6" ry="3"/><path d="M6 5v14c0 1.7 2.7 3 6 3s6-1.3 6-3V5M9 10v7M15 10v7M12 8v11"/>
                                        @break
                                    @case('rod-specifications')
                                        <circle cx="12" cy="9" r="6"/><path d="M8 13l8-8M8 9v4h4M12 5h4v4M6 18v3M18 18v3M6 20h12"/>
                                        @break
                                    @case('break-events')
                                        <path d="M3 4l6 6-2 3 3-1M21 20l-6-6 2-3-3 1M13 3l-1 3M20 7l-3 1M4 17l3-1M11 21l1-3"/>
                                        @break
                                    @case('break-conditions')
                                        <rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2h6v4H9zM8 13l3 3 5-6"/>
                                        @break
                                    @case('break-categories')
                                        <path d="M3 3h7l10 10a2 2 0 0 1 0 3l-4 4a2 2 0 0 1-3 0L3 10V3Z"/><circle cx="7" cy="7" r="1"/>
                                        @break
                                    @case('boms')
                                        <rect x="8" y="2" width="8" height="5" rx="1"/><path d="M12 7v5M5 12h14M5 12v5M12 12v5M19 12v5"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="9" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/>
                                        @break
                                    @case('items')
                                        <path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5"/>
                                        @break
                                    @case('categories')
                                        <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
                                        @break
                                    @case('units')
                                        <path d="m15 3 6 6L9 21l-6-6L15 3ZM12 6l3 3M9 9l2 2M6 12l3 3"/>
                                        @break
                                    @case('suppliers')
                                        <path d="M14 17V5H3v12h3M10 17h5M14 9h4l3 4v4h-2M21 13h-7"/><circle cx="8" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                                        @break
                                    @case('customers')
                                        <circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5.66"/>
                                        @break
                                    @case('job-titles')
                                        <rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12a20 20 0 0 0 18 0M10 12h4v4h-4z"/>
                                        @break
                                    @case('divisions')
                                        <rect x="8" y="2" width="8" height="6" rx="1.5"/><path d="M12 8v5M5 17v-4h14v4"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/>
                                        @break
                                    @case('access-profiles')
                                        <path d="M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7l-9-4Z"/><path d="m8 12 3 3 5-5"/>
                                        @break
                                    @case('employees')
                                        <rect x="3" y="4" width="18" height="17" rx="2"/><path d="M9 2v4M15 2v4M15 11h3M15 15h3"/><circle cx="9" cy="11" r="2"/><path d="M6 18v-1a3 3 0 0 1 6 0v1"/>
                                        @break
                                @endswitch
                            </svg>
                        </span>
                        <span class="nav-text"><strong>{{ $label }}</strong></span>
                    </a>
                    @endforeach
                    </div>
                </details>
                @endforeach
                @endcan
                @else
                {{-- JUDUL GRUP: PRODUKSI --}}
                @can('access-produksi')
                <div class="nav-section-header">
                    <span class="plant-dot"></span>
                    <span>PRODUKSI</span>
                </div>

                @include('modules.produksi.components.copper-rod-navigation')
                {{-- Tambahkan menu baru di file sidebar ini. --}}
                @endcan
                @can('access-ppic-navigation')
                    @include('modules.ppic.components.sidebar-navigation')
                @endcan
                @can('access-marketing')
                <div class="nav-section-header"><span>MARKETING</span></div>
                <a href="{{ route('marketing.sales') }}" wire:navigate
                   class="nav-item {{ request()->routeIs('marketing.sales') ? 'active' : '' }}"
                   @if(request()->routeIs('marketing.sales')) aria-current="page" @endif>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5v18l3-2 4 2 4-2 3 2V3h-3"/><path d="M9 3h6v4H9zM8 11h8M8 15h5"/></svg>
                    </span>
                    <span class="nav-text"><strong>Penjualan</strong></span>
                </a>
                @endcan
                @can('access-purchasing')
                @php
                    $purchasingActive = request()->routeIs('purchasing.*');
                    $purchasingOrdersActive = request()->routeIs('purchasing.orders');
                @endphp
                <section class="purchasing-sidebar-group" aria-label="Purchasing"
                    x-data="{ open: sessionStorage.getItem('purchasing-active') === 'true', active: @js($purchasingActive) }" x-init="$nextTick(() => requestAnimationFrame(() => requestAnimationFrame(() => { open = active; sessionStorage.setItem('purchasing-active', String(active)); })))">
                    <button type="button" class="nav-item purchasing-sidebar-trigger purchasing-attention-target {{ $purchasingActive ? 'is-current-module' : '' }}"
                        @click="open = !open" :aria-expanded="open"
                        aria-expanded="{{ $purchasingActive ? 'true' : 'false' }}" aria-controls="purchasing-submenu">
                        <span class="nav-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h2l3 12h11l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                        </span>
                        <span class="nav-text"><strong>Purchasing</strong></span>
                        <livewire:purchasing.attention-indicator :key="'sidebar-purchasing-attention'" />
                        <svg class="purchasing-sidebar-chevron" :class="{ 'is-open': open }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
                    </button>
                    <div id="purchasing-submenu" class="purchasing-sidebar-reveal" :class="{ 'is-open': open }" :inert="!open" :aria-hidden="!open">
                    <div class="purchasing-sidebar-submenu">
                        <a href="{{ route('purchasing.dashboard') }}" wire:navigate
                            class="nav-item purchasing-sidebar-link {{ request()->routeIs('purchasing.dashboard') ? 'active' : '' }}"
                            @if(request()->routeIs('purchasing.dashboard')) aria-current="page" @endif>
                            <span class="nav-text"><strong>Dashboard</strong></span>
                        </a>
                        @can('purchasing.requests.view')
                            <a href="{{ route('purchasing.requests') }}" wire:navigate
                                class="nav-item purchasing-sidebar-link purchasing-attention-target {{ request()->routeIs('purchasing.requests') ? 'active' : '' }}"
                                @if(request()->routeIs('purchasing.requests')) aria-current="page" @endif>
                                <span class="nav-text"><strong>Pengajuan Pembelian</strong></span>
                                <livewire:purchasing.attention-indicator :key="'sidebar-purchasing-requests-attention'" />
                            </a>
                        @endcan
                        @can('purchasing.orders.view')
                            <a href="{{ route('purchasing.orders') }}" wire:navigate
                                class="nav-item purchasing-sidebar-link {{ $purchasingOrdersActive ? 'active' : '' }}"
                                @if($purchasingOrdersActive) aria-current="page" @endif>
                                <span class="nav-text"><strong>Pesanan Pembelian</strong></span>
                            </a>
                        @endcan
                    </div>
                    </div>
                </section>
                @endcan
                @endif
            </nav>
            <div class="mobile-nav-footer">
                @can('access-master-data')
                    <a class="button button-secondary" href="{{ request()->routeIs('master-data.*') ? route('board') : route('master-data.items') }}" wire:navigate>
                        {{ request()->routeIs('master-data.*') ? 'Menu Utama' : 'Master Data' }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
                    </a>
                @endcan
                @can('use-sino')
                    <button type="button" class="button button-secondary" data-mobile-nav-close @click="$dispatch('open-sino-chat')">Tanya Sino</button>
                @endcan
                <div class="mobile-nav-account">
                    <div class="user-avatar">{{ auth()->user()?->initials }}</div>
                    <div class="mobile-nav-account-copy"><strong>{{ auth()->user()?->name }}</strong><span>{{ auth()->user()?->job_title }}</span></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="mobile-nav-close" aria-label="Keluar" title="Keluar">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H4v16h5m5-12 4 4-4 4m-6-4h13"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

