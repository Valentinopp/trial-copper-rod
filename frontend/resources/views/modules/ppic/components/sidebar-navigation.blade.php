@php
    $ppicActive = request()->routeIs('ppic.*');
    $icPages = [
        'sales' => ['label' => 'Kesiapan Penjualan', 'permission' => 'ppic.ic.sales.view', 'attention' => 'sales'],
        'requests' => ['label' => 'Pengajuan Pembelian', 'permission' => 'ppic.ic.requests.view'],
        'receipts' => ['label' => 'Barang Masuk', 'permission' => 'ppic.ic.receipts.view', 'attention' => 'incoming'],
        'issues' => ['label' => 'Barang Keluar', 'permission' => 'ppic.ic.issues.view'],
        'stock' => ['label' => 'Stok', 'permission' => 'ppic.ic.stock.view'],
    ];
    $legacyIcPage = collect(['requests', 'sales', 'receipts', 'stock'])->first(fn ($key) => \Illuminate\Support\Facades\Gate::allows($icPages[$key]['permission']));
@endphp
<section class="ppic-sidebar-group" aria-label="PPIC"
    x-data="{ open: sessionStorage.getItem('ppic-active') === 'true', active: @js($ppicActive) }"
    x-init="$nextTick(() => requestAnimationFrame(() => requestAnimationFrame(() => { open = active; sessionStorage.setItem('ppic-active', String(active)); })))">
    <button type="button" class="nav-item ppic-sidebar-trigger ppic-attention-target {{ $ppicActive ? 'is-current-module' : '' }}"
        @click="open = !open" :aria-expanded="open" aria-expanded="{{ $ppicActive ? 'true' : 'false' }}" aria-controls="ppic-submenu">
        <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2M8 18h2"/></svg></span>
        <span class="nav-text"><strong>PPIC</strong></span>
        <livewire:ppic.attention-indicator kind="inventory" :key="'sidebar-ppic-attention'" />
        <svg class="ppic-sidebar-chevron" :class="{ 'is-open': open }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
    </button>
    <div id="ppic-submenu" class="ppic-sidebar-reveal" :class="{ 'is-open': open }" :inert="!open" :aria-hidden="!open">
        <div class="ppic-sidebar-submenu">
            @can('access-ppic')
                @foreach(['ppic.dashboard' => 'Dashboard', 'ppic.production-plan' => 'Production Plan', 'ppic.production-calendar' => 'Kalender Produksi'] as $route => $label)
                    <a href="{{ route($route) }}" wire:navigate class="nav-item ppic-sidebar-link {{ request()->routeIs($route) ? 'active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif><span class="nav-text"><strong>{{ $label }}</strong></span></a>
                @endforeach
            @endcan
            @can('access-ic')
                @foreach($icPages as $key => $item)
                    @can($item['permission'])
                        @php($isActive = request()->routeIs('ppic.ic.'.$key) || (request()->routeIs('ppic.inventory-control') && $legacyIcPage === $key))
                        <a href="{{ route('ppic.ic.'.$key) }}" wire:navigate class="nav-item ppic-sidebar-link {{ isset($item['attention']) ? 'ppic-attention-target' : '' }} {{ $isActive ? 'active' : '' }}" @if($isActive) aria-current="page" @endif>
                            <span class="nav-text"><strong>{{ $item['label'] }}</strong></span>
                            @if(isset($item['attention']))<livewire:ppic.attention-indicator :kind="$item['attention']" :key="'sidebar-ppic-'.$item['attention']" />@endif
                        </a>
                    @endcan
                @endforeach
            @endcan
        </div>
    </div>
</section>