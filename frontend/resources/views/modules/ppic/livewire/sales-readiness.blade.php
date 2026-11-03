<section class="panel">
    <div class="panel-head"><div><h2>Penjualan final dari Marketing</h2><p>Periksa kecukupan bahan sebelum penjualan diserahkan ke Production Plan.</p></div><button type="button" class="button button-secondary button-sm" wire:click="$refresh">Muat ulang</button></div>
    <div class="table-shell" tabindex="0" role="region" aria-label="Penjualan final untuk diperiksa IC"><table class="data-table procurement-table"><thead><tr><th>Penjualan</th><th>Pelanggan</th><th>Produk</th><th class="numeric">Qty / UoM</th><th>Harus dikirim</th><th>Status IC</th><th>Master BoM</th><th>Aksi</th></tr></thead><tbody>
        @forelse($sales as $sale)
            <tr wire:key="readiness-sale-{{ $sale->id }}"><td>{{ $sale->reference }}</td><td>{{ $sale->customer_snapshot['name'] }}</td><td class="strong-cell">{{ $sale->product_snapshot['name'] }}</td><td class="numeric">{{ number_format((float) $sale->quantity, 6, ',', '.') }} {{ $sale->unit_snapshot['code'] }}</td><td>{{ $sale->ship_by->format('d/m/Y') }}</td><td><span class="procurement-status">{{ $sale->productionHandoff ? 'Diserahkan ke PP' : ($sale->material_requirements_count ? 'Kebutuhan sudah diperiksa' : 'Belum diperiksa') }}</span></td><td>
    @if($sale->bom_snapshot)
        {{ $sale->bom_snapshot['code'] }} · {{ $sale->bom_snapshot['name'] }}
    @elseif($sale->material_requirements_count)
        Kebutuhan tersimpan
    @elseif(\Illuminate\Support\Facades\Gate::allows('ppic.ic.sales.check'))
        @php($options = $boms->get($sale->product_id, collect()))
        <select wire:model="bomSelections.{{ $sale->id }}" aria-label="Master BoM untuk {{ $sale->reference }}">
            <option value="">{{ $options->count() === 1 ? 'Otomatis: '.$options->first()->code : 'Pilih Master BoM' }}</option>
            @foreach($options as $bom)<option value="{{ $bom->id }}">{{ $bom->code }} · {{ $bom->name }}</option>@endforeach
        </select>
        @if($options->isEmpty())<small>Belum ada BoM aktif untuk produk ini.</small>@endif
    @else
        Belum diperiksa
    @endif
</td><td>
    @if($sale->material_requirements_count)
        <button type="button" class="button button-secondary button-sm" wire:click="viewSale({{ $sale->id }})" wire:loading.attr="disabled">Lihat bahan</button>
    @else
        @can('ppic.ic.sales.check')<button type="button" class="button button-secondary button-sm" wire:click="checkSale({{ $sale->id }})" wire:loading.attr="disabled">Cek kebutuhan</button>@endcan
    @endif
</td></tr>
        @empty
            <tr><td colspan="8" class="procurement-empty">Belum ada penjualan final dari Marketing.</td></tr>
        @endforelse
    </tbody></table></div>
    @include('components.pagination', ['paginator' => $sales])
</section>

@if($readiness)
    <section class="panel form-panel" id="sales-readiness">
        <div class="panel-head wrap-head"><div><h2>Kebutuhan bahan · {{ $readiness['sale']->reference }}</h2><p>{{ $readiness['sale']->product_snapshot['name'] }} · {{ number_format((float) $readiness['sale']->quantity, 6, ',', '.') }} {{ $readiness['sale']->unit_snapshot['code'] }} · {{ $readiness['sale']->customer_snapshot['name'] }}</p></div><button type="button" class="button button-secondary button-sm" wire:click="$refresh">Perbarui stok</button></div>
        <div class="ic-readiness-summary">
            @if($readiness['sale']->bom_snapshot)<p>BoM: {{ $readiness['sale']->bom_snapshot['code'] }} · {{ $readiness['sale']->bom_snapshot['name'] }}</p>@endif
            @if($readiness['handoff'])
                <strong>Sudah diserahkan · {{ $readiness['handoff']->reference }}</strong><p>Bahan dicadangkan untuk penjualan ini. Data sudah tersedia pada Production Plan.</p>
            @elseif($readiness['ready'])
                <strong>Bahan mencukupi untuk qty penjualan ini</strong><p>Penjualan dapat diserahkan ke Production Plan. Ketersediaan diperiksa kembali saat penyerahan.</p>
            @else
                <strong>Bahan belum mencukupi</strong><p>Ajukan kekurangan bahan, lalu catat penerimaannya di Barang Masuk sebelum diserahkan.</p>
            @endif
            <p>Kebutuhan = qty bahan BoM × qty penjualan ÷ jumlah hasil acuan. BoM disimpan pada pemeriksaan pertama; qty dibulatkan ke atas hingga 6 desimal.</p>
        </div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Perbandingan kebutuhan bahan dan stok"><table class="data-table procurement-table"><thead><tr><th>Material</th><th>UoM</th><th class="numeric">Dibutuhkan</th><th class="numeric">Tersedia untuk SO ini</th><th class="numeric">Kurang</th><th class="numeric">Pengajuan berjalan</th><th class="numeric">Perlu diajukan</th></tr></thead><tbody>
            @foreach($readiness['rows'] as $row)
                <tr wire:key="requirement-{{ $selectedSaleId }}-{{ $row['material_id'] }}"><td class="strong-cell">{{ $row['name'] }}</td><td>{{ $row['uom'] }}</td><td class="numeric">{{ number_format((float) $row['required'], 6, ',', '.') }}</td><td class="numeric">{{ number_format((float) $row['available'], 6, ',', '.') }}</td><td class="numeric">{{ number_format((float) $row['shortage'], 6, ',', '.') }}</td><td class="numeric">{{ number_format((float) $row['outstanding'], 6, ',', '.') }}</td><td class="numeric">{{ number_format((float) $row['requestable'], 6, ',', '.') }}</td></tr>
            @endforeach
        </tbody></table></div>
        <div class="ic-readiness-summary">
            @if($readiness['sale']->bom_snapshot)<p>BoM: {{ $readiness['sale']->bom_snapshot['code'] }} · {{ $readiness['sale']->bom_snapshot['name'] }}</p>@endif<p>Stok tersedia mengecualikan cadangan penjualan lain. Pengajuan berjalan belum dihitung sebagai stok sampai barang diterima.</p></div>
        @if(!$readiness['handoff'])
            @if($readiness['ready'])
                @can('ppic.ic.sales.handover')
                <div class="ic-readiness-actions"><span class="form-hint">Penyerahan mencadangkan bahan tanpa mengurangi stok fisik.</span><button type="button" class="button button-primary" wire:click="handOverSale" wire:loading.attr="disabled" wire:target="handOverSale">Serahkan ke Production Plan</button></div>
                @endcan
            @elseif($readiness['can_request'])
                @can('ppic.ic.sales.request')
                <form class="ic-readiness-actions" wire:submit="requestShortages">
                    <label class="field"><span class="field-label">Tanggal bahan dibutuhkan *</span><input type="date" wire:model="materials_required_on" min="{{ now()->format('Y-m-d') }}" required /></label>
                    <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="requestShortages"><span wire:loading.remove wire:target="requestShortages">Ajukan pembelian kekurangan</span><span wire:loading wire:target="requestShortages">Mengajukan...</span></button>
                </form>
                @endcan
            @else
                <div class="ic-readiness-summary">
            @if($readiness['sale']->bom_snapshot)<p>BoM: {{ $readiness['sale']->bom_snapshot['code'] }} · {{ $readiness['sale']->bom_snapshot['name'] }}</p>@endif<p>Kekurangan sudah memiliki pengajuan pembelian. Tunggu penerimaan, lalu perbarui stok. Pengajuan yang sama tidak akan digandakan.</p></div>
            @endif
        @endif
    </section>
@endif
