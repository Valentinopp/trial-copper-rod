<div class="page-stack">
    @php($types = ['items' => 'item', 'categories' => 'category', 'units' => 'unit'])
    @php($labels = ['item' => 'barang', 'category' => 'kategori', 'unit' => 'UoM'])
    <div class="page-title"><div><p class="eyebrow">MASTER DATA</p><h1>{{ ['items' => 'Master Barang', 'categories' => 'Master Kategori', 'units' => 'Master UoM'][$tab] }}</h1><p>{{ ['items' => 'Kelola data dasar barang, material, dan sparepart.', 'categories' => 'Kelola kategori dan pengelompokan barang.', 'units' => 'Kelola satuan, Related UoM, dan conversion.'][$tab] }}</p></div></div>
    <section class="panel">
        <div class="panel-head wrap-head"><div><h2>{{ ['items' => 'Daftar barang', 'categories' => 'Daftar kategori', 'units' => 'Daftar UoM'][$tab] }}</h2><p>{{ $rows->total() }} data terdaftar</p></div><button type="button" class="button button-primary" wire:click="openForm('{{ $types[$tab] }}')">+ Tambah {{ $labels[$types[$tab]] }}</button></div>
        <div class="master-search"><label class="field"><span class="field-label">Cari kode atau nama</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Ketik kode atau nama" /></label></div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar master data"><table class="data-table master-table"><thead><tr><th>Kode</th><th>Nama</th>
            @if($tab === 'items')<th>Kategori</th><th>UoM</th><th>Deskripsi / spesifikasi</th>
            @elseif($tab === 'categories')<th>Kategori induk</th><th>Deskripsi</th>
            @else<th>Related UoM</th><th class="numeric">Conversion</th>@endif
            <th>Status</th><th>Aksi</th></tr></thead><tbody>
            @forelse($rows as $row)
                <tr wire:key="master-{{ $tab }}-{{ $row->id }}"><td class="strong-cell">{{ $row->code }}</td><td>{{ $row->name }}</td>
                    @if($tab === 'items')<td>{{ $row->category->name }}</td><td>{{ $row->unit->code }}</td><td class="master-description">{{ $row->description ?: '—' }}@if($row->rodSpecification)<small class="planning-cell-secondary">Cu Rod {{ $row->rodSpecification->diameter_mm }} mm</small>@endif</td>
                    @elseif($tab === 'categories')<td>{{ $row->parent?->name ?? 'Kategori utama' }}</td><td class="master-description">{{ $row->description ?: '—' }}</td>
                    @else<td>{{ $row->related?->code ?? '—' }}</td><td class="numeric">{{ rtrim(rtrim($row->factor, '0'), '.') }}@if($row->related) {{ $row->related->code }} / {{ $row->code }}@endif</td>@endif
                    <td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><div class="master-row-actions"><button type="button" class="button button-secondary button-sm" wire:click="openForm('{{ $types[$tab] }}', {{ $row->id }})">Lihat / ubah</button><button type="button" class="button button-sm master-delete-button" wire:click="confirmRemoval('{{ $types[$tab] }}', {{ $row->id }})">Hapus</button></div></td>
                </tr>
            @empty<tr><td colspan="7" class="catalog-empty">Belum ada data yang sesuai. Tambahkan data atau ubah pencarian.</td></tr>@endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $rows])
    </section>
    @include('modules.master-data.components.confirm-delete')
    <dialog class="marketing-dialog" wire:ignore.self x-data
        x-effect="let mode = $wire.modal; $nextTick(() => { if (mode) { if (!$el.open) $el.showModal(); } else if ($el.open) $el.close(); })"
        @cancel.prevent="$wire.closeModal()" aria-labelledby="master-dialog-title">
        <header class="catalog-modal-header"><div><h2 id="master-dialog-title">{{ $editingId ? 'Ubah' : 'Tambah' }} {{ $labels[$modal] ?? 'master data' }}</h2><p>Lengkapi identitas dan status data.</p></div><button type="button" class="button button-ghost" wire:click="closeModal" aria-label="Tutup formulir">Tutup</button></header>
        <div class="catalog-modal-body">
            @if($modal)
                @include('components.form-errors')
                <form wire:submit="save" class="entry-form catalog-modal-form">
                    <div class="form-grid two">
                        <label class="field"><span class="field-label">Kode *</span><input wire:model="form.code" maxlength="{{ $modal === 'item' ? 60 : 30 }}" required autofocus placeholder="Kode unik" /></label>
                        <label class="field"><span class="field-label">Nama *</span><input wire:model="form.name" maxlength="255" required /></label>
                        @if($modal === 'item')
                            <label class="field"><span class="field-label">Kategori *</span><select wire:model="form.category_id" required><option value="">Pilih kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->code }} · {{ $category->name }}{{ !$category->is_active ? ' (nonaktif)' : '' }}</option>@endforeach</select></label>
                            <label class="field"><span class="field-label">UoM *</span><select wire:model="form.uom_id" required><option value="">Pilih UoM</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->code }} · {{ $unit->name }}{{ !$unit->is_active ? ' (nonaktif)' : '' }}</option>@endforeach</select></label>
                            <label class="field wide"><span class="field-label">Spesifikasi Copper Rod (untuk produk Cu Rod)</span><select wire:model="form.rod_specification_id"><option value="">Bukan produk Copper Rod / belum diatur</option>@foreach($rodSpecifications as $rod)<option value="{{ $rod->id }}">{{ $rod->diameter_mm }} mm · {{ $rod->name }}{{ !$rod->is_active ? ' (nonaktif)' : '' }}</option>@endforeach</select><small>Diatur sekali pada produk. Diameter otomatis mengikuti produk pada SO dan Production Plan.</small>@error('form.rod_specification_id')<small role="alert">{{ $message }}</small>@enderror</label>
                        @elseif($modal === 'category')
                            <label class="field wide"><span class="field-label">Kategori induk (opsional)</span><select wire:model="form.parent_id"><option value="">Kategori utama</option>@foreach($categories->whereNull('parent_id')->where('is_active', true) as $category)@if($category->id !== $editingId)<option value="{{ $category->id }}">{{ $category->code }} · {{ $category->name }}</option>@endif @endforeach</select></label>
                        @else
                            <label class="field"><span class="field-label">Related UoM (opsional)</span><select wire:model="form.related_uom_id"><option value="">Tanpa acuan</option>@foreach($units->whereNull('related_uom_id')->where('is_active', true) as $unit)@if($unit->id !== $editingId)<option value="{{ $unit->id }}">{{ $unit->code }} · {{ $unit->name }}</option>@endif @endforeach</select></label>
                            <label class="field"><span class="field-label">Conversion *</span><input type="number" wire:model="form.factor" min="0.000000001" max="999999999" step="0.000000001" required /><small>1 UoM ini = faktor × Related UoM. Tanpa acuan, isi 1.</small></label>
                        @endif
                        @if($modal !== 'unit')<label class="field wide"><span class="field-label">{{ $modal === 'item' ? 'Deskripsi / spesifikasi referensi (opsional)' : 'Deskripsi (opsional)' }}</span><textarea wire:model="form.description" maxlength="2000" rows="3" placeholder="{{ $modal === 'item' ? 'Keterangan barang sesuai spesifikasi yang digunakan' : 'Keterangan kategori' }}"></textarea></label>@endif
                        <label class="field master-status"><span class="field-label">Status</span><span><input type="checkbox" wire:model="form.is_active" /> Aktif</span></label>
                    </div>
                    <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeModal">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">Simpan {{ $labels[$modal] }}</button></div>
                </form>
            @endif
        </div>
    </dialog>
</div>
