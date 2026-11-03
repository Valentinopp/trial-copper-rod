<div class="page-stack purchasing-workspace">
    <div class="page-title"><div><p class="eyebrow">PURCHASING</p><h1>Pengajuan pembelian</h1><p>Tinjau kebutuhan dari Inventory Control dan proses menjadi pesanan pembelian.</p></div></div>
    @include('components.form-errors')
    @if($selectedRequest)
        <section class="panel form-panel" id="purchase-order-form">
            <div class="panel-head"><div><h2>Pesanan untuk {{ $selectedRequest->reference }}</h2><p>{{ $selectedRequest->product->name }} · {{ number_format((float) $selectedRequest->quantity, 6, ',', '.') }} {{ $selectedRequest->unit->code }} · Dibutuhkan {{ $selectedRequest->required_on->format('d/m/Y') }}</p></div><button type="button" class="button button-secondary" wire:click="cancelOrder">Batal</button></div>
            <form wire:submit="approveOrder" class="entry-form">
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Tanggal barang dibeli *</span><input type="date" wire:model="ordered_on" max="{{ now()->format('Y-m-d') }}" required /></label>
                    <label class="field"><span class="field-label">Estimasi barang datang *</span><input type="date" wire:model="expected_on" min="{{ $ordered_on }}" required /><small>Isi tanggal konfirmasi kedatangan, meskipun berbeda dari tanggal kebutuhan IC.</small></label>
                </div>
                <div class="form-footer"><span class="form-hint">Persetujuan membuat satu pesanan pembelian. IC kemudian dapat menerima barang.</span><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="approveOrder"><span wire:loading.remove wire:target="approveOrder">Setujui &amp; buat pesanan</span><span wire:loading wire:target="approveOrder">Memproses...</span></button></div>
            </form>
        </section>
    @endif
    <section class="panel">
        <div class="panel-head"><div><h2>Pengajuan pembelian dari IC</h2><p>Pengajuan yang menunggu persetujuan Purchasing.</p></div><button type="button" class="button button-secondary button-sm" wire:click="$refresh">Muat ulang</button></div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Pengajuan dari Inventory Control"><table class="data-table procurement-table"><thead><tr><th>Pengajuan</th><th>Nama produk</th><th class="numeric">Qty</th><th>UoM</th><th>Harus datang</th><th>Diajukan</th>@can('purchasing.orders.create')<th>Aksi</th>@endcan</tr></thead><tbody>
            @forelse($requests as $request)
                <tr wire:key="pending-{{ $request->id }}"><td>{{ $request->reference }}@if($request->salesOrder)<br /><small class="muted-text">{{ $request->salesOrder->reference }}</small>@endif</td><td class="strong-cell">{{ $request->product->name }}</td><td class="numeric">{{ number_format((float) $request->quantity, 6, ',', '.') }}</td><td>{{ $request->unit->code }}</td><td>{{ $request->required_on->format('d/m/Y') }}</td><td>{{ $request->created_at->format('d/m/Y') }}</td>@can('purchasing.orders.create')<td><button type="button" class="button button-secondary button-sm" wire:click="selectRequest({{ $request->id }})" wire:loading.attr="disabled">Proses pesanan</button></td>@endcan</tr>
            @empty
                <tr><td colspan="{{ \Illuminate\Support\Facades\Gate::allows('purchasing.orders.create') ? 7 : 6 }}" class="procurement-empty">Tidak ada pengajuan yang menunggu. Pengajuan baru dari IC akan muncul di sini.</td></tr>
            @endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $requests])
    </section>
</div>
