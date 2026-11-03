<div class="page-stack">
    <div class="page-title"><div><h1>Master {{ $title }}</h1><p>Kelola pilihan {{ strtolower($title) }} untuk pencatatan produksi Copper Rod.</p></div></div>
    @if(session('production-master-saved'))<div role="status" class="panel">{{ session('production-master-saved') }}</div>@endif
    <section class="panel">
        <div class="panel-head wrap-head"><div><h2>Daftar {{ strtolower($title) }}</h2><p>Data nonaktif tetap tersimpan dalam riwayat dan tidak tersedia untuk pencatatan baru.</p></div><button type="button" class="button button-primary" wire:click="openCreate">+ Tambah {{ strtolower($title) }}</button></div>
        <div class="master-search form-grid two">
            <label class="field"><span class="field-label">Pencarian</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Kode atau nama" /></label>
            <label class="field"><span class="field-label">Status</span><select wire:model.live="status"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label>
        </div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar {{ $title }}"><table class="data-table master-table">
            <thead><tr><th>Kode</th><th>Nama</th>@if($hasServo)<th>Servo</th>@endif @if($hasSpecification)<th>Diameter (mm)</th><th>Bobot (ton/m)</th>@endif<th>Deskripsi</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>@forelse($rows as $row)
                <tr wire:key="production-master-{{ $row->id }}"><td>{{ $row->code }}</td><td>{{ $row->name }}</td>@if($hasServo)<td>{{ $row->servo }}</td>@endif @if($hasSpecification)<td>{{ $row->diameter_mm }}</td><td>{{ rtrim(rtrim(number_format($row->weight_ton_per_meter, 10, '.', ''), '0'), '.') }}</td>@endif<td class="master-description">{{ $row->description ?: '—' }}</td><td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><button type="button" class="button button-secondary button-sm" wire:click="openEdit({{ $row->id }})">Lihat / ubah</button></td></tr>
            @empty<tr><td colspan="{{ 5 + ($hasServo ? 1 : 0) + ($hasSpecification ? 2 : 0) }}" class="catalog-empty">Belum ada data yang sesuai. Tambahkan data untuk digunakan pada form Copper Rod.</td></tr>@endforelse</tbody>
        </table></div>
        @include('components.pagination', ['paginator' => $rows])
    </section>
    <dialog class="marketing-dialog" wire:ignore.self x-data
        x-effect="let open = $wire.showForm; $nextTick(() => { if (open && !$el.open) { $el.showModal(); $el.querySelector('[autofocus]')?.focus(); } else if (!open && $el.open) $el.close(); })"
        @cancel.prevent="$wire.closeForm()" aria-labelledby="production-master-dialog-title">
        <header class="catalog-modal-header"><h2 id="production-master-dialog-title">{{ $editingId ? 'Ubah' : 'Tambah' }} {{ $title }}</h2><button type="button" class="button button-ghost" wire:click="closeForm">Tutup</button></header>
        <div class="catalog-modal-body">@if($showForm)
            @include('components.form-errors')
            <form wire:submit="save" class="entry-form catalog-modal-form"><div class="form-grid two">
                <label class="field"><span class="field-label">Kode *</span><input wire:model="form.code" maxlength="255" required autofocus /></label>
                <label class="field"><span class="field-label">Nama *</span><input wire:model="form.name" maxlength="255" required /></label>
                @if($hasServo)<label class="field"><span class="field-label">Servo *</span><select wire:model="form.servo" required><option value="">Pilih servo</option><option value="X">X</option><option value="Y">Y</option></select></label>@endif
                @if($hasSpecification)
                    <label class="field"><span class="field-label">Diameter Cu Rod (mm) *</span><input type="number" wire:model="form.diameter_mm" min="0.1" max="999.9" step="0.1" required /></label>
                    <label class="field"><span class="field-label">Bobot rod per meter (ton/m) *</span><input type="number" wire:model="form.weight_ton_per_meter" min="0.0000000001" max="99" step="0.0000000001" required /><small>Contoh: diameter 8 mm memiliki bobot 0.00045 ton/m.</small></label>
                @endif
                <label class="field wide"><span class="field-label">Deskripsi</span><textarea wire:model="form.description" maxlength="2000" rows="3"></textarea></label>
                <label class="field master-status"><span class="field-label">Status</span><span><input type="checkbox" wire:model="form.is_active" /> Aktif</span></label>
            </div><div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeForm">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">Simpan</button></div></form>
        @endif</div>
    </dialog>
</div>
