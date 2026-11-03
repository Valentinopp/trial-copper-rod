<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Digital WIP Coiling Board' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body x-data="{
    isCopperRod: @js(request()->routeIs('board', 'crystallizer', 'copper-break', 'output')),
    warning: { title: '', messages: [] },
    validationTimer: null,
    toast: null,
    toastTimer: null,
    isLoading: false,
    loadingText: 'Memuat…',
    loadingTimer: null,
    startLoading(text = 'Memuat…') {
        if (this.loadingTimer) clearTimeout(this.loadingTimer);
        this.loadingTimer = setTimeout(() => {
            this.loadingText = text || 'Memuat…';
            this.isLoading = true;
        }, 120);
    },
    stopLoading() {
        if (this.loadingTimer) clearTimeout(this.loadingTimer);
        this.loadingTimer = null;
        this.isLoading = false;
    },
    showToast(detail) {
        const data = Array.isArray(detail) ? detail[0] : detail;
        if (this.isCopperRod && ['error', 'warning', 'danger'].includes(data.type)) {
            this.showWarning(data);
            return;
        }
        if (this.toastTimer) clearTimeout(this.toastTimer);
        this.toast = {
            message: data.message || '',
            type: data.type || 'success',
            title: data.title || (data.type === 'error' ? 'Perlu diperiksa' : 'Berhasil')
        };
        this.toastTimer = setTimeout(() => { this.toast = null; }, 7000);
    },
    showWarning(data) {
        const messages = data.messages?.length ? data.messages : [data.message || 'Periksa kembali data yang diisi.'];
        const missingRequired = data.missingRequired || messages.some(message => /wajib diisi|field is required/i.test(message));
        this.warning = {
            title: missingRequired ? 'Lengkapi data' : (data.title || 'Periksa data'),
            messages: missingRequired ? ['Lengkapi data yang wajib diisi.'] : [...new Set(messages)]
        };
        this.$nextTick(() => {
            if (!this.$refs.copperWarning.open) this.$refs.copperWarning.showModal();
        });
    },
    showFieldWarning(field) {
        if (this.validationTimer) return;
        this.validationTimer = setTimeout(() => {
            this.validationTimer = null;
            const fields = field.form ? Array.from(field.form.elements) : [field];
            const invalid = fields.filter(input => input.willValidate && !input.validity.valid);
            if (invalid.some(input => input.id === 'board-event_status' && input.validity.valueMissing)) {
                this.showWarning({ title: 'Lengkapi status kejadian', message: 'Aktual Pick Up sudah diisi. Pilih status Panen atau Putus sebelum menyimpan.' });
                return;
            }
            const messages = invalid.map(input => {
                const label = input.closest('label')?.querySelector('.field-label')?.textContent.replace(/\*/g, '').trim() || 'Isian';
                const group = input.closest('fieldset')?.querySelector('legend')?.textContent.trim();
                const name = group ? label + ' ' + group : label;
                return input.validity.valueMissing ? name + ' wajib diisi.' : name + ': ' + input.validationMessage;
            });
            if (messages.length) this.showWarning({ messages: [...new Set(messages)] });
        }, 0);
    }
}" @notify.window="showToast($event.detail)" @invalid.capture="if (isCopperRod) { $event.preventDefault(); showFieldWarning($event.target); }" @copper-loading-start.window="startLoading($event.detail?.text || 'Memuat…')" @copper-loading-stop.window="stopLoading()" x-on:livewire:navigating.window="startLoading('Memuat…')" x-on:livewire:navigated.window="stopLoading()">

    @php
        $isProduksi = request()->routeIs('board', 'crystallizer', 'copper-break', 'output');
        $moduleLabel = match(true) {
            request()->routeIs('master-data.*') => 'Master Data',
            request()->routeIs('ppic.*') => 'PPIC',
            request()->routeIs('marketing.*') => 'Marketing',
            request()->routeIs('purchasing.*') => 'Purchasing',
            request()->routeIs('access-unavailable') => 'Akun',
            default => 'Produksi',
        };
        $productionMasterLabel = \App\Modules\MasterData\Models\ProductionReference::SECTIONS[
            \Illuminate\Support\Str::after(request()->route()?->getName() ?? '', 'master-data.')
        ] ?? null;
        $activePageLabel = match(true) {
            $productionMasterLabel !== null => $productionMasterLabel,
            request()->routeIs('master-data.items') => 'Master Barang',
            request()->routeIs('master-data.categories') => 'Master Kategori',
            request()->routeIs('master-data.units') => 'Master UoM',
            request()->routeIs('master-data.suppliers') => 'Master Supplier',
            request()->routeIs('master-data.customers') => 'Master Customer',
            request()->routeIs('master-data.boms') => 'Master BoM',
            request()->routeIs('master-data.job-titles') => 'Master Jabatan',
            request()->routeIs('master-data.divisions') => 'Master Divisi',
            request()->routeIs('master-data.employees') => 'Master Karyawan',
            request()->routeIs('master-data.access-profiles') => 'Hak Akses',
            request()->routeIs('ppic.production-plan') => 'Production Plan',
            request()->routeIs('ppic.production-calendar') => 'Kalender Produksi',
            request()->routeIs('ppic.inventory-control') => 'Inventory Control',
            request()->routeIs('ppic.ic.sales') => 'Kesiapan Penjualan',
            request()->routeIs('ppic.ic.requests') => 'Pengajuan Pembelian',
            request()->routeIs('ppic.ic.receipts') => 'Barang Masuk',
            request()->routeIs('ppic.ic.stock') => 'Stok',
            request()->routeIs('ppic.ic.issues') => 'Barang Keluar',
            request()->routeIs('ppic.dashboard') => 'Dashboard PPIC',
            request()->routeIs('marketing.sales') => 'Penjualan',
            request()->routeIs('access-unavailable') => 'Akses belum tersedia',
            request()->routeIs('purchasing.orders') => 'Pesanan Pembelian',
            request()->routeIs('purchasing.requests') => 'Pengajuan Pembelian',
            request()->routeIs('purchasing.dashboard') => 'Dashboard Purchasing',
            request()->routeIs('ppic.*', 'marketing.*') => 'Dashboard',
            request()->routeIs('crystallizer') => 'Monitoring Crystallizer',
            request()->routeIs('copper-break') => 'Monitoring Copper Putus',
            request()->routeIs('output') => 'Rekap Output per Shift',
            default => 'Papan Digital',
        };
    @endphp

    <div class="app-shell {{ $isProduksi ? 'copper-rod-workspace' : '' }}">
        @include('components.layouts.sidebar')

        {{-- AREA UTAMA KANAN --}}
        <main class="main-area">
            <header class="topbar">
                @if($isProduksi)
                    <div class="mobile-page-heading" aria-hidden="true">
                        <span>COPPER ROD</span>
                        <strong>{{ match (request()->route()->getName()) {
                            'crystallizer' => 'Crystallizer', 'copper-break' => 'Copper Putus',
                            'output' => 'Rekap Output', default => 'Papan Digital',
                        } }}</strong>
                    </div>
                @endif
                <div class="breadcrumb">
                    <span>{{ $moduleLabel }}</span>
                    @if($isProduksi)
                    <span class="crumb-separator">/</span>
                    <span>Copper Rod</span>
                    @endif
                    <span class="crumb-separator">/</span>
                    <strong>{{ $activePageLabel }}</strong>
                </div>

                <div class="topbar-right">
                    <button type="button" class="mobile-nav-toggle" popovertarget="app-sidebar" aria-controls="app-sidebar" aria-expanded="false" aria-label="Buka menu navigasi" title="Menu navigasi">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                    </button>
                    @can('access-master-data')
                        <a class="button button-secondary workspace-switch" href="{{ request()->routeIs('master-data.*') ? route('board') : route('master-data.items') }}" wire:navigate>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            {{ request()->routeIs('master-data.*') ? 'Menu Utama' : 'Master Data' }}
                        </a>
                    @endcan
                    @can('use-sino')
                    <button type="button" class="chat-launcher" @click="$dispatch('open-sino-chat')" aria-label="Tanya Sino">
                        <svg class="gemini-sparkle" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <defs>
                                <linearGradient id="geminiGradient" x1="2" y1="22" x2="22" y2="2" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#4285F4"/>
                                    <stop offset="0.48" stop-color="#A142F4"/>
                                    <stop offset="1" stop-color="#EA4335"/>
                                </linearGradient>
                            </defs>
                            <path fill="url(#geminiGradient)" d="M12 1.5C13.65 8.15 15.85 10.35 22.5 12 15.85 13.65 13.65 15.85 12 22.5 10.35 15.85 8.15 13.65 1.5 12 8.15 10.35 10.35 8.15 12 1.5Z"/>
                        </svg>
                        <span>Tanya <strong>Sino</strong></span>
                    </button>

                    @endcan
                    <div class="user-welcome">
                        <div class="user-avatar">{{ auth()->user()?->initials }}</div>
                        <div class="user-copy">
                            <strong>{{ auth()->user()?->name }}</strong>
                            <span class="welcome-label">{{ auth()->user()?->job_title }}</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="topbar-logout">
                        @csrf
                        <button type="submit" aria-label="Keluar" title="Keluar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H4v16h5m5-12 4 4-4 4m-6-4h13"/></svg></button>
                    </form>
                </div>
            </header>

            <div class="content-area">
                {{ $slot }}
                @unless($isProduksi)
                    <footer class="app-footer">
                        <span>Modul {{ $moduleLabel }}</span>
                        <span>Operasional {{ $moduleLabel }}</span>
                    </footer>
                @endunless
            </div>
        </main>

        @can('use-sino')
            <livewire:produksi.sino-chat />
        @endcan

        @if($isProduksi)
            <dialog class="copper-warning-dialog" x-ref="copperWarning" role="alertdialog" aria-labelledby="copper-warning-title" aria-describedby="copper-warning-messages">
                <div class="copper-warning-icon" aria-hidden="true">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 7v6m0 4h.01"/></svg>
                </div>
                <h2 id="copper-warning-title" x-text="warning.title"></h2>
                <div id="copper-warning-messages" class="copper-warning-messages">
                    <template x-for="(message, index) in warning.messages" :key="index">
                        <p x-text="message"></p>
                    </template>
                </div>
                <button type="button" class="button button-primary" autofocus @click="$refs.copperWarning.close()">Mengerti</button>
            </dialog>
        @endif

        <x-apple-loading-popup />

        {{-- Toast Notification --}}
        <template x-if="toast">
            <div class="toast" :class="toast.type === 'error' ? 'error' : 'success'" :role="toast.type === 'error' ? 'alert' : 'status'" :aria-live="toast.type === 'error' ? 'assertive' : 'polite'">
                <span class="toast-icon">
                    <template x-if="toast.type === 'error'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                    </template>
                    <template x-if="toast.type !== 'error'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
                    </template>
                </span>
                <span class="toast-copy">
                    <strong x-text="toast.title"></strong>
                    <span x-text="toast.message"></span>
                </span>
                <button class="toast-close" @click="toast = null" aria-label="Tutup notifikasi">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
