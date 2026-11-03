<x-layouts.app title="Akses belum tersedia - irris.next">
    <div class="page-stack">
        <div class="page-title"><div><h1>Akses belum tersedia</h1><p>Akun Anda aktif, tetapi izin Penjualan belum diberikan untuk jabatan dan divisi Anda. Hubungi administrator untuk mengatur Hak Akses.</p></div></div>
        @can('marketing.sales.view')<a class="button button-primary" href="{{ route('marketing.sales') }}" wire:navigate>Buka Penjualan</a>@endcan
    </div>
</x-layouts.app>