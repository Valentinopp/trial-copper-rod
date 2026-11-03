<div class="page-stack purchasing-workspace">
    <div class="page-title"><div><p class="eyebrow">PURCHASING</p><h1>Pesanan pembelian</h1><p>Pantau jadwal kedatangan, status penerimaan, dan sisa pesanan pembelian.</p></div></div>
    @if(session('purchase-order-created'))
        <div class="panel" role="status"><div class="panel-head">{{ session('purchase-order-created') }}</div></div>
    @endif
    <section class="panel">
        <div class="panel-head"><div><h2>Daftar pesanan pembelian</h2><p>Pesanan yang sudah diproses beserta perkembangan penerimaan barang.</p></div><button type="button" class="button button-secondary button-sm" wire:click="$refresh" wire:loading.attr="disabled">Muat ulang</button></div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Pesanan pembelian"><table class="data-table procurement-table"><thead><tr><th>Pesanan</th><th>Pengajuan</th><th>Produk</th><th class="numeric">Qty</th><th>UoM</th><th>Dibeli</th><th>Estimasi datang</th><th class="numeric">Sisa diterima</th><th>Status</th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr wire:key="po-{{ $order->id }}"><td>{{ $order->reference }}</td><td>{{ $order->request->reference }}</td><td class="strong-cell">{{ $order->request->product->name }}</td><td class="numeric">{{ number_format((float) $order->request->quantity, 6, ',', '.') }}</td><td>{{ $order->request->unit->code }}</td><td>{{ $order->ordered_on->format('d/m/Y') }}</td><td>{{ $order->expected_on->format('d/m/Y') }}</td><td class="numeric">{{ number_format($order->remaining, 6, ',', '.') }}</td><td><span class="procurement-status">{{ $order->status }}</span></td></tr>
            @empty
                <tr><td colspan="9" class="procurement-empty">Belum ada pesanan pembelian. Proses pengajuan untuk membuat pesanan.</td></tr>
            @endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $orders])
    </section>
</div>
