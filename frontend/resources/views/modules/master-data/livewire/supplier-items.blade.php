<div class="form-section-title"><div><strong>Barang yang disediakan</strong><small>Pilih barang dari Master Barang. Supplier boleh disimpan tanpa barang terlebih dahulu. Perubahan hubungan berlaku setelah disimpan.</small></div></div>
<div class="partner-item-picker">
    <section aria-label="Pilih barang supplier">
        <h3>Katalog barang</h3>
        <label class="field"><span class="field-label">Cari barang untuk ditambahkan</span><input type="search" wire:model.live.debounce.300ms="itemSearch" placeholder="Kode atau nama barang" /></label>
        <div class="table-shell"><table class="data-table partner-item-table"><thead><tr><th>Barang</th><th>Kategori / UoM</th><th>Aksi</th></tr></thead><tbody>
            @forelse($catalogItems as $item)
                <tr wire:key="catalog-item-{{ $item->id }}"><td><strong>{{ $item->code }}</strong><br />{{ $item->name }}</td><td>{{ $item->category->name }}<br /><small>{{ $item->unit->code }}</small></td><td><button type="button" class="button button-secondary button-sm" wire:click="toggleItem({{ $item->id }})" wire:loading.attr="disabled" wire:target="toggleItem">Tambah</button></td></tr>
            @empty<tr><td colspan="3" class="catalog-empty">Tidak ada barang aktif yang sesuai atau semua sudah dipilih.</td></tr>@endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $catalogItems])
        <p class="form-hint"><a href="{{ route('master-data.items') }}" wire:navigate>Buka Master Barang</a> untuk mengelola daftar barang.</p>
    </section>
    <section aria-label="Barang supplier terpilih">
        <h3>Barang terpilih ({{ count($selectedItemIds) }})</h3>
        <label class="field"><span class="field-label">Cari barang terpilih</span><input type="search" wire:model.live.debounce.300ms="selectedSearch" placeholder="Kode atau nama barang terpilih" /></label>
        <div class="table-shell"><table class="data-table partner-item-table"><thead><tr><th>Barang</th><th>Kategori / UoM</th><th>Aksi</th></tr></thead><tbody>
            @forelse($selectedItems as $item)
                <tr wire:key="selected-item-{{ $item->id }}"><td><strong>{{ $item->code }}</strong><br />{{ $item->name }}@if(!$item->is_active)<br /><small class="muted-text">Nonaktif · hubungan tetap tersimpan</small>@endif</td><td>{{ $item->category->name }}<br /><small>{{ $item->unit->code }}</small></td><td><button type="button" class="button button-ghost button-sm" wire:click="toggleItem({{ $item->id }})" wire:loading.attr="disabled" wire:target="toggleItem">Lepas</button></td></tr>
            @empty<tr><td colspan="3" class="catalog-empty">Belum ada barang terpilih yang sesuai.</td></tr>@endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $selectedItems])
    </section>
</div>
