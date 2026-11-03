<div class="page-stack">
    <div class="page-title"><div><p class="eyebrow">MARKETING</p><h1>Penjualan</h1><p>Catat penjualan yang sudah final dan tanggal barang harus dikirim ke pelanggan.</p></div></div>
    <section class="panel">
        <div class="panel-head wrap-head">
            <div><h2>Daftar penjualan final</h2><p>{{ $sales->total() }} catatan penjualan</p></div>
            @can('marketing.sales.create')
            <button type="button" class="button button-primary" wire:click="openSale" wire:loading.attr="disabled">+ Catat penjualan</button>
            @endcan
        </div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar penjualan final">
            <table class="data-table marketing-sales-table">
                <thead><tr><th>No. penjualan</th><th>Customer</th><th>Produk</th><th class="numeric">Qty</th><th>UoM</th><th>Tanggal penjualan</th><th>Harus dikirim</th><th>Status</th><th>Catatan</th></tr></thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}">
                            <td class="strong-cell">{{ $sale->reference }}</td><td>@if($sale->customer_snapshot['code']){{ $sale->customer_snapshot['code'] }} · @endif{{ $sale->customer_snapshot['name'] }}</td><td>@if($sale->product_snapshot['code']){{ $sale->product_snapshot['code'] }} · @endif{{ $sale->product_snapshot['name'] }}</td>
                            <td class="numeric">{{ rtrim(rtrim(number_format((float) $sale->quantity, 6, ',', '.'), '0'), ',') }}</td>
                            <td>{{ $sale->unit_snapshot['code'] }}</td>
                            <td class="nowrap">{{ $sale->sold_on->format('d/m/Y') }}</td><td class="nowrap strong-cell">{{ $sale->ship_by->format('d/m/Y') }}</td>
                            <td><span class="marketing-sale-status">Final</span></td><td class="marketing-sale-notes">{{ $sale->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="catalog-empty">Belum ada penjualan.@can('marketing.sales.create') Klik Catat penjualan untuk memasukkan pesanan pelanggan yang sudah final.@endcan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('components.pagination', ['paginator' => $sales])
    </section>

    @can('marketing.sales.create')
    <dialog class="marketing-dialog" wire:ignore.self x-data
            x-effect="let open = $wire.showForm; $nextTick(() => { if (open) { if (!$el.open) { $el.showModal(); $el.querySelector('[autofocus]')?.focus(); $el.querySelector('.catalog-modal-body').scrollTop = 0; } } else if ($el.open) $el.close(); })"
            @cancel.prevent="$wire.closeSale()" aria-labelledby="marketing-sales-dialog-title">
        <header class="catalog-modal-header">
            <div><h2 id="marketing-sales-dialog-title">Catat penjualan final</h2><p>Pilih customer dan produk dari Master Data.</p></div>
            <button type="button" class="icon-button" wire:click="closeSale" aria-label="Tutup modal penjualan"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg></button>
        </header>
        <div class="catalog-modal-body">
            @if($showForm)
                @if($errors->any())<div tabindex="-1" x-init="$nextTick(() => $el.focus())">@include('components.form-errors')</div>@endif
                @if($products->isEmpty())
                    <div class="catalog-note"><p>Belum ada produk aktif yang dapat dijual. Siapkan barang berkategori Produk dengan kategori dan UoM aktif.</p><a href="{{ route('master-data.items') }}" wire:navigate class="button button-secondary">Buka Master Barang</a></div>
                @endif
                @if($customers->isEmpty())
                    <div class="catalog-note"><p>Belum ada customer aktif.</p><a href="{{ route('master-data.customers') }}" wire:navigate class="button button-secondary">Buka Master Customer</a></div>
                @endif
                <form wire:submit="saveSale" class="entry-form catalog-modal-form">
                    <p class="required-fields-note">* Wajib diisi. Data customer, produk, dan UoM disimpan sesuai Master Data saat penjualan dicatat.</p>
                    <div class="form-grid two">
                        <label class="field"><span class="field-label">Customer *</span><select wire:model="customer_id" required autofocus><option value="">Pilih customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->code }} · {{ $customer->name }}</option>@endforeach</select></label>
                        <label class="field"><span class="field-label">Produk *</span><select wire:model.live="product_id" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }} ({{ $product->unit->code }})</option>@endforeach</select></label>
                        <label class="field"><span class="field-label">Qty penjualan ({{ $selectedProduct?->unit->code ?? 'UoM produk' }}) *</span><input type="number" wire:model="quantity" min="0.000001" max="999999999" step="0.000001" required placeholder="Contoh: 2500" /><small>Satuan mengikuti UoM produk pada Master Barang.</small></label>
                        <label class="field"><span class="field-label">Tanggal penjualan *</span><input type="date" wire:model.live="sold_on" max="{{ now()->format('Y-m-d') }}" required /></label>
                        <label class="field"><span class="field-label">Barang harus dikirim tanggal *</span><input type="date" wire:model="ship_by" min="{{ $sold_on }}" required /><small>Batas tanggal pengiriman sesuai kesepakatan dengan pelanggan.</small></label>
                        <label class="field wide"><span class="field-label">Catatan (opsional)</span><textarea wire:model="notes" rows="2" maxlength="2000" placeholder="Referensi pelanggan atau catatan pengiriman"></textarea></label>
                    </div>
                    <div class="form-footer">
                        <button type="button" class="button button-secondary" wire:click="closeSale">Batal</button>
                        <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="saveSale" @disabled($products->isEmpty() || $customers->isEmpty())><span wire:loading.remove wire:target="saveSale">Simpan penjualan final</span><span wire:loading wire:target="saveSale">Menyimpan...</span></button>
                    </div>
                </form>
            @endif
        </div>
    </dialog>
    @endcan
</div>
