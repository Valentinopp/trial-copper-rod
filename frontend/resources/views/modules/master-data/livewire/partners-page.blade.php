<div class="page-stack">
    @php($title = $kind === 'supplier' ? 'Supplier' : 'Customer')
    <div class="page-title"><div><p class="eyebrow">MASTER DATA</p><h1>Master {{ $title }}</h1><p>{{ $kind === 'supplier' ? 'Kelola identitas supplier dan barang yang mereka sediakan.' : 'Kelola identitas dan informasi kontak customer.' }}</p></div></div>
    <section class="panel">
        <div class="panel-head wrap-head"><div><h2>Daftar {{ $kind }}</h2><p>{{ $rows->total() }} data terdaftar</p></div><button type="button" class="button button-primary" wire:click="openCreate">+ Tambah {{ $kind }}</button></div>
        <div class="master-search"><label class="field"><span class="field-label">Cari kode atau nama</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Ketik kode atau nama" /></label></div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar {{ $kind }}"><table class="data-table master-table"><thead><tr><th>Kode</th><th>Nama</th><th>Contact person</th><th>Kontak</th>@if($kind === 'supplier')<th>Barang</th>@endif<th>Status</th><th>Aksi</th></tr></thead><tbody>
            @forelse($rows as $row)
                <tr wire:key="{{ $kind }}-{{ $row->id }}"><td class="strong-cell">{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->contact_name ?: '—' }}</td><td>{{ $row->phone ?: '—' }}@if($row->email)<br /><small>{{ $row->email }}</small>@endif</td>@if($kind === 'supplier')<td>{{ $row->items_count }} barang</td>@endif<td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><div class="master-row-actions"><button type="button" class="button button-secondary button-sm" wire:click="openEdit({{ $row->id }})">Lihat / ubah</button><button type="button" class="button button-sm master-delete-button" wire:click="confirmRemoval('{{ $kind }}', {{ $row->id }})">Hapus</button></div></td></tr>
            @empty<tr><td colspan="{{ $kind === 'supplier' ? 7 : 6 }}" class="catalog-empty">Belum ada data yang sesuai. Tambahkan {{ $kind }} atau ubah pencarian.</td></tr>@endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $rows])
    </section>
    @include('modules.master-data.components.confirm-delete')
    <dialog class="marketing-dialog" wire:ignore.self x-data
        x-effect="let open = $wire.showForm; $nextTick(() => { if (open && !$el.open) { $el.showModal(); $el.querySelector('[autofocus]')?.focus(); } else if (!open && $el.open) $el.close(); })"
        @cancel.prevent="$wire.closeForm()" aria-labelledby="partner-dialog-title">
        <header class="catalog-modal-header"><div><h2 id="partner-dialog-title">{{ $editingId ? 'Ubah' : 'Tambah' }} {{ $kind }}</h2><p>Lengkapi identitas, kontak, dan status data.</p></div><button type="button" class="button button-ghost" wire:click="closeForm" aria-label="Tutup formulir">Tutup</button></header>
        <div class="catalog-modal-body">
            @if($showForm)
                @include('components.form-errors')
                <form wire:submit="save" class="entry-form catalog-modal-form">
                    <div class="form-grid two">
                        <label class="field"><span class="field-label">Kode *</span><input wire:model="form.code" maxlength="60" required autofocus placeholder="{{ $kind === 'supplier' ? 'SUP-001' : 'CUS-001' }}" /></label>
                        <label class="field"><span class="field-label">Nama *</span><input wire:model="form.name" maxlength="255" required /></label>
                        <label class="field"><span class="field-label">Contact person</span><input wire:model="form.contact_name" maxlength="255" /></label>
                        <label class="field"><span class="field-label">Telepon</span><input type="tel" wire:model="form.phone" maxlength="100" /></label>
                        <label class="field wide"><span class="field-label">Email</span><input type="email" wire:model="form.email" maxlength="255" /></label>
                        <label class="field wide"><span class="field-label">Alamat</span><textarea wire:model="form.address" rows="2" maxlength="2000"></textarea></label>
                        <label class="field wide"><span class="field-label">Catatan</span><textarea wire:model="form.notes" rows="2" maxlength="2000"></textarea></label>
                        <label class="field master-status"><span class="field-label">Status</span><span><input type="checkbox" wire:model="form.is_active" /> Aktif</span></label>
                    </div>
                    @if($kind === 'supplier')
                        @include('modules.master-data.livewire.supplier-items')
                    @endif
                    <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeForm">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">Simpan {{ $kind }}</button></div>
                </form>
            @endif
        </div>
    </dialog>
</div>
