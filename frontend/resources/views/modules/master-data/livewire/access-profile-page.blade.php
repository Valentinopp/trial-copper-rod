<div class="page-stack">
    <div class="page-title"><div><p class="eyebrow">MASTER DATA</p><h1>Hak Akses</h1><p>Daftarkan pengaturan untuk satu jabatan dan satu divisi.</p></div></div>
    @if(session('access-profile-saved'))<div class="panel" role="status"><div class="panel-head">{{ session('access-profile-saved') }}</div></div>@endif
    <section class="panel">
        <div class="panel-head wrap-head"><div><h2>Pengaturan hak akses</h2><p>{{ $rows->total() }} kombinasi jabatan dan divisi terdaftar</p></div><button type="button" class="button button-primary" wire:click="openCreate">+ Tambah hak akses</button></div>
        <div class="master-search form-grid two">
            <label class="field"><span class="field-label">Cari jabatan atau divisi</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Ketik kode atau nama" /></label>
            <label class="field"><span class="field-label">Status pengaturan</span><select wire:model.live="status"><option value="">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label>
        </div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar pengaturan hak akses"><table class="data-table master-table"><thead><tr><th>Jabatan</th><th>Divisi</th><th>Status pengaturan</th><th>Izin fitur</th><th>Aksi</th></tr></thead><tbody>
            @forelse($rows as $row)
                <tr wire:key="access-profile-{{ $row->id }}">
                    <td class="strong-cell">{{ $row->jobTitle->name }}<br /><small>{{ $row->jobTitle->code }}{{ $row->jobTitle->is_active ? '' : ' · Nonaktif' }}</small></td>
                    <td>{{ $row->division->name }}<br /><small>{{ $row->division->code }}{{ $row->division->is_active ? '' : ' · Nonaktif' }}</small></td>
                    <td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>{{ count($row->permissions ?? []) }} izin fitur</td>
                    <td><button type="button" class="button button-secondary button-sm" wire:click="openEdit({{ $row->id }})">Lihat / ubah</button></td>
                </tr>
            @empty
                <tr><td colspan="5" class="catalog-empty">{{ trim($search) !== '' || $status !== '' ? 'Tidak ada pengaturan yang sesuai. Ubah pencarian atau filter status.' : 'Belum ada pengaturan hak akses. Tambahkan kombinasi jabatan dan divisi untuk memulai.' }}</td></tr>
            @endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $rows])
    </section>
    <p class="catalog-note">Checklist berlaku untuk jabatan dan divisi ini. Marketing, IC, dan Purchasing diatur terpisah; Production Plan dan modul lain masih mengikuti role lama. IT Super Admin tetap memiliki akses penuh.</p>
    <dialog class="marketing-dialog" wire:ignore.self x-data
        x-effect="let open = $wire.showForm; $nextTick(() => { if (open && !$el.open) { $el.showModal(); $el.querySelector('[autofocus]')?.focus(); } else if (!open && $el.open) $el.close(); })"
        @cancel.prevent="$wire.closeForm()" aria-labelledby="access-profile-dialog-title">
        <header class="catalog-modal-header"><div><h2 id="access-profile-dialog-title">{{ $editingId ? 'Ubah' : 'Tambah' }} hak akses</h2><p>Satu pengaturan untuk setiap kombinasi jabatan dan divisi.</p></div><button type="button" class="button button-ghost" wire:click="closeForm" aria-label="Tutup formulir">Tutup</button></header>
        <div class="catalog-modal-body">@if($showForm)
            @include('components.form-errors')
            <form wire:submit="save" class="entry-form catalog-modal-form">
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Jabatan *</span><select wire:model="form.job_title_id" required autofocus><option value="">Pilih jabatan</option>@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->code }} — {{ $job->name }}{{ $job->is_active ? '' : ' (Nonaktif)' }}</option>@endforeach</select></label>
                    <label class="field"><span class="field-label">Divisi *</span><select wire:model="form.division_id" required><option value="">Pilih divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}">{{ $division->code }} — {{ $division->name }}{{ $division->is_active ? '' : ' (Nonaktif)' }}</option>@endforeach</select></label>
                    <label class="field master-status"><span class="field-label">Status pengaturan</span><span><input type="checkbox" wire:model="form.is_active" /> Aktif</span></label>
                </div>
                @if($jobs->isEmpty() || $divisions->isEmpty())
                    <p>Buat jabatan dan divisi aktif terlebih dahulu. <a href="{{ route('master-data.job-titles') }}" wire:navigate>Buka Jabatan</a> atau <a href="{{ route('master-data.divisions') }}" wire:navigate>Buka Divisi</a>.</p>
                @endif
                <fieldset class="access-permissions">
                    <legend>Izin fitur</legend>
                    @foreach($permissionModules as $moduleKey => $module)
                        <section class="access-permission-module" aria-label="{{ $module['label'] }}">
                            <h3>{{ $module['label'] }}</h3>
                            @if($moduleKey === 'purchasing')
                                <label class="access-permission-option"><input type="checkbox" wire:model.live="form.configured_modules" value="purchasing" @disabled(collect($form['permissions'] ?? [])->contains(fn ($value) => is_string($value) && str_starts_with($value, 'purchasing.'))) /><span>Gunakan checklist Purchasing</span></label>
                                <p>Aktifkan agar Purchasing mengikuti checklist ini, termasuk menolak akses jika semua pilihan kosong. Jika belum diaktifkan, Purchasing mengikuti role lama. Memilih izin Purchasing otomatis mengaktifkannya.</p>
                            @endif
                            @if($moduleKey === 'ppic_ic')
                                <label class="access-permission-option"><input type="checkbox" wire:model.live="form.configured_modules" value="ppic_ic" @disabled(collect($form['permissions'] ?? [])->contains(fn ($value) => is_string($value) && str_starts_with($value, 'ppic.ic.'))) /><span>Gunakan checklist Inventory Control</span></label>
                                <p>Aktifkan agar IC mengikuti checklist ini, termasuk menolak akses jika semua pilihan kosong. Jika belum diaktifkan, IC mengikuti role lama. Memilih izin IC otomatis mengaktifkannya.</p>
                            @endif
                            @foreach($module['features'] as $feature)
                                <p>{{ $feature['label'] }}</p>
                                @foreach($feature['permissions'] as $permission => $label)
                                    <label class="access-permission-option" wire:key="permission-{{ $permission }}">
                                        <input type="checkbox" wire:model.live="form.permissions" value="{{ $permission }}"
                                            @disabled(is_array($form['permissions'] ?? null) && \App\Services\AccessPermissionService::requiredBySelected($permission, $form['permissions'])) />
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            @endforeach
                        </section>
                    @endforeach
                    <p class="catalog-note">Izin tindakan otomatis menyertakan izin lihat terkait. Pengajuan kekurangan dan penyerahan ke PP membutuhkan hasil pemeriksaan bahan yang sudah tersimpan.</p>
                </fieldset>
                <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeForm">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">Simpan hak akses</button></div>
            </form>
        @endif</div>
    </dialog>
</div>