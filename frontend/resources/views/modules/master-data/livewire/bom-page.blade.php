<div class="page-stack">
    <div class="page-title"><div><p class="eyebrow">MASTER DATA</p><h1>Master BoM</h1><p>Susun kebutuhan material untuk menghasilkan produk dari Master Barang.</p></div></div>
    <section class="panel">
        <div class="panel-head wrap-head"><div><h2>Daftar BoM</h2><p>{{ $rows->total() }} BoM terdaftar</p></div><button type="button" class="button button-primary" wire:click="openForm">+ Tambah BoM</button></div>
        <div class="master-search"><label class="field"><span class="field-label">Cari kode, nama BoM, atau produk</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Ketik kode atau nama" /></label></div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar Master BoM">
            <table class="data-table master-table">
                <thead><tr><th>Kode</th><th>Nama BoM</th><th>Produk hasil</th><th>Hasil acuan</th><th>Material</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr wire:key="bom-{{ $row->id }}">
                            <td class="strong-cell">{{ $row->code }}</td><td>{{ $row->name }}</td>
                            <td>{{ $row->product->code }} · {{ $row->product->name }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $row->output_quantity, 6, ',', '.'), '0'), ',') }} {{ $row->product->unit->code }}</td>
                            <td>{{ $row->lines_count }} material</td><td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td><button type="button" class="button button-secondary button-sm" wire:click="openForm({{ $row->id }})">Lihat / ubah</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="catalog-empty">Belum ada BoM yang sesuai. Tambahkan BoM atau ubah pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('components.pagination', ['paginator' => $rows])
    </section>
    <dialog class="marketing-dialog" wire:ignore.self x-data
        x-effect="let visible = $wire.showForm; $nextTick(() => { if (visible) { if (!$el.open) $el.showModal(); } else if ($el.open) $el.close(); })"
        @cancel.prevent="$wire.closeModal()" aria-labelledby="bom-dialog-title">
        <header class="catalog-modal-header"><div><h2 id="bom-dialog-title">{{ $editingId ? 'Ubah' : 'Tambah' }} BoM</h2><p>Tentukan produk hasil dan material penyusunnya.</p></div><button type="button" class="button button-ghost" wire:click="closeModal">Tutup</button></header>
        <div class="catalog-modal-body">
            @if($showForm)
                @include('components.form-errors')
                @if($products->isEmpty() || $materials->isEmpty())
                    <p>Siapkan barang berkategori Produk untuk hasil, serta Material atau subkategori Material untuk penyusun di <a href="{{ route('master-data.items') }}">Master Barang</a> terlebih dahulu.</p>
                @endif
                <form wire:submit="save" class="entry-form catalog-modal-form">
                    <div class="form-grid two">
                        <label class="field"><span class="field-label">Kode BoM *</span><input wire:model="form.code" maxlength="60" required autofocus /></label>
                        <label class="field"><span class="field-label">Nama BoM *</span><input wire:model="form.name" maxlength="255" required /></label>
                        <label class="field"><span class="field-label">Produk hasil *</span><select wire:model.live="form.product_id" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }} ({{ $product->unit->code }}){{ !$product->is_active ? ' — nonaktif' : '' }}</option>@endforeach</select></label>
                        @php($selectedProduct = $products->firstWhere('id', (int) $form['product_id']))
                        <label class="field"><span class="field-label">Jumlah hasil acuan ({{ $selectedProduct?->unit->code ?? 'UoM produk' }}) *</span><input type="number" wire:model="form.output_quantity" min="0.000001" max="999999999" step="0.000001" required /><small>Kebutuhan material di bawah berlaku untuk jumlah hasil ini.</small></label>
                    </div>
                    <fieldset>
                        <legend>Material penyusun</legend>
                        <p>Jumlah setiap material mengikuti UoM pada Master Barang.</p>
                        @foreach($form['lines'] as $index => $line)
                            @php($selectedMaterial = $materials->firstWhere('id', (int) $line['material_id']))
                            <div class="form-grid two" wire:key="bom-line-{{ $editingId ?? 'new' }}-{{ $index }}">
                                <label class="field"><span class="field-label">Material {{ $index + 1 }} *</span><select wire:model.live="form.lines.{{ $index }}.material_id" required><option value="">Pilih material</option>@foreach($materials as $material)<option value="{{ $material->id }}">{{ $material->code }} · {{ $material->name }} ({{ $material->unit->code }}){{ !$material->is_active ? ' — nonaktif' : '' }}</option>@endforeach</select></label>
                                <label class="field"><span class="field-label">Jumlah ({{ $selectedMaterial?->unit->code ?? 'UoM material' }}) *</span><input type="number" wire:model="form.lines.{{ $index }}.quantity" min="0.000001" max="999999999" step="0.000001" required /></label>
                                @if(count($form['lines']) > 1)<div><button type="button" class="button button-ghost button-sm" wire:click="removeLine({{ $index }})">Hapus material {{ $index + 1 }}</button></div>@endif
                            </div>
                        @endforeach
                        <button type="button" class="button button-secondary button-sm" wire:click="addLine" @disabled(count($form['lines']) >= 100)>+ Tambah material</button>
                    </fieldset>
                    <label class="field"><span class="field-label">Catatan (opsional)</span><textarea wire:model="form.notes" maxlength="2000" rows="3"></textarea></label>
                    <label class="field master-status"><span class="field-label">Status</span><span><input type="checkbox" wire:model="form.is_active" /> Aktif</span></label>
                    <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeModal">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save" @disabled($products->isEmpty() || $materials->isEmpty())>Simpan BoM</button></div>
                </form>
            @endif
        </div>
    </dialog>
</div>
