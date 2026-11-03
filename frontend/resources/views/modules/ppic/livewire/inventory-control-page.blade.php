<div class="page-stack">
    <div class="page-title"><div><p class="eyebrow">PPIC</p><h1>{{ ['sales' => 'Kesiapan Penjualan', 'requests' => 'Pengajuan Pembelian', 'in' => 'Barang Masuk', 'stock' => 'Stok'][$tab] }}</h1><p>Inventory Control · Kelola kebutuhan bahan, penerimaan, dan persediaan.</p></div></div>
    @include('components.form-errors')
    @if($tab === 'sales')
        @include('modules.ppic.livewire.sales-readiness')
    @elseif($tab === 'requests')
        @can('ppic.ic.requests.create')
        <section class="panel">
            <div class="panel-head"><div><h2>Pengajuan pembelian barang</h2><p>Pengajuan dikirim ke Purchasing untuk disetujui menjadi pesanan pembelian.</p></div></div>
            <form wire:submit="submitRequest" class="entry-form">
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Nama produk / material *</span><select wire:model.live="product_id" required><option value="">Pilih barang dari master</option>@foreach($productOptions as $option)<option value="{{ $option->id }}">{{ $option->code }} · {{ $option->name }} ({{ $option->unit->code }})</option>@endforeach</select></label>
                    <label class="field"><span class="field-label">Qty *</span><div class="ic-quantity-input"><input type="number" wire:model="quantity" min="0.000001" step="0.000001" max="999999999" required aria-describedby="request-quantity-uom" /><span id="request-quantity-uom" class="ic-quantity-uom" aria-live="polite">{{ $selectedRequestProduct?->unit->code ?? '—' }}</span></div></label>
                    <label class="field"><span class="field-label">Barang harus datang tanggal *</span><input type="date" wire:model="required_on" min="{{ now()->format('Y-m-d') }}" required /></label>
                </div>
                @if($productOptions->isEmpty())<p class="form-hint">Belum ada barang terdaftar. Tambahkan melalui Master Data → Master Barang. <a href="{{ route('master-data.items') }}" wire:navigate>Buka Master Barang</a></p>@endif
                <div class="form-footer"><span class="form-hint">Qty dan tanggal kebutuhan diisi sesuai pengajuan. Qty mendukung 6 angka desimal.</span><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="submitRequest" @disabled($productOptions->isEmpty())><span wire:loading.remove wire:target="submitRequest">Kirim pengajuan</span><span wire:loading wire:target="submitRequest">Mengirim...</span></button></div>
            </form>
        </section>
        @endcan
        <section class="panel">
            <div class="panel-head"><h2>Riwayat pengajuan</h2><button type="button" class="button button-secondary button-sm" wire:click="$refresh">Muat ulang</button></div>
            <div class="table-shell" tabindex="0" role="region" aria-label="Riwayat pengajuan"><table class="data-table procurement-table"><thead><tr><th>Pengajuan</th><th>Produk</th><th class="numeric">Qty</th><th>UoM</th><th>Dibutuhkan</th><th>Status</th><th>Pesanan</th></tr></thead><tbody>
                @forelse($requests as $request)
                    <tr wire:key="pr-{{ $request->id }}"><td>{{ $request->reference }}@if($request->salesOrder)<br /><small class="muted-text">{{ $request->salesOrder->reference }}</small>@endif</td><td class="strong-cell">{{ $request->product->name }}</td><td class="numeric">{{ number_format((float) $request->quantity, 6, ',', '.') }}</td><td>{{ $request->unit->code }}</td><td>{{ $request->required_on->format('d/m/Y') }}</td><td><span class="procurement-status">{{ $request->order ? $request->order->status : 'Menunggu persetujuan' }}</span></td><td>{{ $request->order?->reference ?? 'Belum dibuat' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="procurement-empty">Belum ada pengajuan pembelian.</td></tr>
                @endforelse
            </tbody></table></div>
            @include('components.pagination', ['paginator' => $requests])
        </section>
    @elseif($tab === 'in')
        @if($selectedOrder)
            <section class="panel form-panel" id="receipt-form">
                <div class="panel-head"><div><h2>Terima barang · {{ $selectedOrder->reference }}</h2><p>{{ $selectedOrder->request->product->name }} · Sisa {{ number_format($selectedOrder->remaining, 6, ',', '.') }} {{ $selectedOrder->request->unit->code }}</p></div><button type="button" class="button button-secondary" wire:click="cancelReceipt">Batal</button></div>
                <form wire:submit="receiveGoods" class="entry-form">
                    <div class="form-grid two">
                        <label class="field"><span class="field-label">Qty diterima *</span><input wire:model="received_quantity" type="number" step="0.000001" min="0.000001" max="{{ $selectedOrder->remaining }}" required /><small>Penerimaan sebagian diperbolehkan. Isi jumlah yang benar-benar datang.</small></label>
                        <label class="field"><span class="field-label">Tanggal diterima *</span><input wire:model="received_on" type="date" min="{{ $selectedOrder->ordered_on->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required /></label>
                    </div>
                    <div class="form-footer"><span class="form-hint">Stok bertambah sesuai jumlah diterima.</span><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="receiveGoods"><span wire:loading.remove wire:target="receiveGoods">Simpan penerimaan</span><span wire:loading wire:target="receiveGoods">Menyimpan...</span></button></div>
                </form>
            </section>
        @endif
        <section class="panel">
            <div class="panel-head"><div><h2>Pesanan dari Purchasing</h2><p>Pesanan yang telah disetujui dapat diterima di sini.</p></div><button type="button" class="button button-secondary button-sm" wire:click="$refresh">Muat ulang</button></div>
            <div class="table-shell" tabindex="0" role="region" aria-label="Pesanan untuk diterima"><table class="data-table procurement-table"><thead><tr><th>Pesanan</th><th>Produk</th><th class="numeric">Dipesan</th><th class="numeric">Sisa</th><th>UoM</th><th>Estimasi datang</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @forelse($orders as $order)
                    <tr wire:key="incoming-{{ $order->id }}"><td>{{ $order->reference }}</td><td class="strong-cell">{{ $order->request->product->name }}</td><td class="numeric">{{ number_format((float) $order->request->quantity, 6, ',', '.') }}</td><td class="numeric">{{ number_format($order->remaining, 6, ',', '.') }}</td><td>{{ $order->request->unit->code }}</td><td>{{ $order->expected_on->format('d/m/Y') }}</td><td><span class="procurement-status">{{ $order->status }}</span></td><td>@if($order->remaining > 0)@can('ppic.ic.receipts.create')<button type="button" class="button button-secondary button-sm" wire:click="selectReceipt({{ $order->id }})" wire:loading.attr="disabled">Terima barang</button>@endcan @else Selesai @endif</td></tr>
                @empty
                    <tr><td colspan="8" class="procurement-empty">Belum ada pesanan yang disetujui Purchasing.</td></tr>
                @endforelse
            </tbody></table></div>
            @include('components.pagination', ['paginator' => $orders])
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Riwayat barang masuk</h2></div>
            <div class="table-shell" tabindex="0" role="region" aria-label="Riwayat penerimaan"><table class="data-table procurement-table"><thead><tr><th>Tanggal diterima</th><th>Pesanan</th><th>Produk</th><th class="numeric">Qty diterima</th><th>UoM</th></tr></thead><tbody>
                @forelse($receipts as $receipt)
                    <tr wire:key="receipt-{{ $receipt->id }}"><td>{{ $receipt->received_on->format('d/m/Y') }}</td><td>{{ $receipt->order->reference }}</td><td>{{ $receipt->product->name }}</td><td class="numeric">{{ number_format((float) $receipt->quantity, 6, ',', '.') }}</td><td>{{ $receipt->unit->code }}</td></tr>
                @empty
                    <tr><td colspan="5" class="procurement-empty">Belum ada penerimaan barang.</td></tr>
                @endforelse
            </tbody></table></div>
            @include('components.pagination', ['paginator' => $receipts])
        </section>
    @elseif($tab === 'stock')
        <section class="panel">
            <div class="panel-head"><div><h2>Stok barang</h2><p>Stok fisik = penerimaan dikurangi pengeluaran yang dikonfirmasi. Tersedia = stok fisik dikurangi cadangan Production Plan.</p></div><button type="button" class="button button-secondary button-sm" wire:click="$refresh">Muat ulang</button></div>
            <div class="table-shell" tabindex="0" role="region" aria-label="Stok barang"><table class="data-table procurement-table"><thead><tr><th>Nama produk</th><th>UoM</th><th class="numeric">Stok fisik</th><th class="numeric">Dicadangkan</th><th class="numeric">Tersedia</th></tr></thead><tbody>
                @forelse($products as $product)
                    <tr wire:key="stock-{{ $product->id }}"><td class="strong-cell">{{ $product->name }}</td><td>{{ $product->unit->code }}</td><td class="numeric">{{ number_format((float) app(\App\Modules\Ppic\Services\InventoryStockService::class)->physical($product), 6, ',', '.') }}</td><td class="numeric">{{ number_format((float) $product->reservations_sum_quantity, 6, ',', '.') }}</td><td class="numeric">{{ number_format((float) app(\App\Modules\Ppic\Services\InventoryStockService::class)->available($product), 6, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="5" class="procurement-empty">Belum ada barang pada Master Barang.</td></tr>
                @endforelse
            </tbody></table></div>
            @include('components.pagination', ['paginator' => $products])
        </section>
    @endif
</div>
