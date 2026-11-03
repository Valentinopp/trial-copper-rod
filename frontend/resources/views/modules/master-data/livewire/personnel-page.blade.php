<div class="page-stack">
    <div class="page-title"><div><p class="eyebrow">MASTER DATA</p><h1>Master {{ $title }}</h1><p>Kelola {{ strtolower($title) }} dan status aktifnya.</p></div></div>
    @if(session('personnel-saved'))<div role="status" class="panel">{{ session('personnel-saved') }}</div>@endif
    <section class="panel">
        <div class="panel-head wrap-head"><div><h2>Daftar {{ strtolower($title) }}</h2><p>{{ $rows->total() }} data terdaftar</p></div><button type="button" class="button button-primary" wire:click="openCreate">+ Tambah {{ strtolower($title) }}</button></div>
        <div class="master-search form-grid two">
            <label class="field"><span class="field-label">Pencarian</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ $section === 'employees' ? 'Nama, username, nomor karyawan, atau email' : 'Kode atau nama' }}" /></label>
            <label class="field"><span class="field-label">Status</span><select wire:model.live="status"><option value="">Semua status</option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label>
        </div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Daftar {{ $title }}"><table class="data-table master-table"><thead><tr>
            <th>{{ $section === 'employees' ? 'Nomor karyawan' : 'Kode' }}</th><th>Nama</th>
            @if($section === 'employees')<th>Username</th><th>Jabatan</th><th>Divisi</th><th>Role</th>@else<th>Deskripsi</th>@endif<th>Status</th><th>Aksi</th>
        </tr></thead><tbody>
            @forelse($rows as $row)<tr wire:key="{{ $section }}-{{ $row->id }}">
                <td>{{ $section === 'employees' ? ($row->employee_number ?: '—') : $row->code }}</td><td>{{ $row->name }}</td>
                @if($section === 'employees')<td>{{ $row->username }}</td><td>{{ $jobs->firstWhere('id',$row->job_title_id)?->name ?? $row->job_title }}</td><td>{{ $divisions->firstWhere('id',$row->division_id)?->name ?? $row->department }}</td><td>{{ $row->role->label() }}</td>@else<td>{{ $row->description ?: '—' }}</td>@endif
                <td>{{ $row->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><button type="button" class="button button-secondary button-sm" wire:click="openEdit({{ $row->id }})">Lihat / ubah</button></td>
            </tr>@empty<tr><td colspan="{{ $section === 'employees' ? 8 : 5 }}" class="catalog-empty">Belum ada data yang sesuai.</td></tr>@endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator'=>$rows])
    </section>
    <dialog class="marketing-dialog" wire:ignore.self x-data
        x-effect="let open = $wire.showForm; $nextTick(() => { if (open && !$el.open) { $el.showModal(); $el.querySelector('[autofocus]')?.focus(); } else if (!open && $el.open) $el.close(); })"
        @cancel.prevent="$wire.closeForm()" aria-labelledby="personnel-dialog-title">
        <header class="catalog-modal-header"><h2 id="personnel-dialog-title">{{ $editingId ? 'Ubah' : 'Tambah' }} {{ $title }}</h2><button type="button" class="button button-ghost" wire:click="closeForm">Tutup</button></header>
        <div class="catalog-modal-body">@if($showForm)
            @include('components.form-errors')
            <form wire:submit="save" class="entry-form catalog-modal-form"><div class="form-grid two">
                @if($section === 'employees')
                    <label class="field"><span class="field-label">Username *</span><input wire:model="form.username" maxlength="60" required autofocus autocomplete="off" /></label>
                    <label class="field"><span class="field-label">Nomor karyawan</span><input wire:model="form.employee_number" maxlength="40" /></label>
                @else
                    <label class="field"><span class="field-label">Kode *</span><input wire:model="form.code" maxlength="60" required autofocus /></label>
                @endif
                <label class="field"><span class="field-label">Nama {{ $section === 'employees' ? 'lengkap' : '' }} *</span><input wire:model="form.name" maxlength="{{ $section === 'employees' ? 255 : 100 }}" required /></label>
                @if($section === 'employees')
                    <label class="field"><span class="field-label">Jabatan *</span><select wire:model="form.job_title_id" required><option value="">Pilih jabatan</option>@foreach($jobs as $job)@if($job->is_active || (int)$form['job_title_id'] === $job->id)<option value="{{ $job->id }}">{{ $job->code }} — {{ $job->name }}{{ $job->is_active ? '' : ' (Nonaktif)' }}</option>@endif @endforeach</select></label>
                    <label class="field"><span class="field-label">Divisi *</span><select wire:model="form.division_id" required><option value="">Pilih divisi</option>@foreach($divisions as $division)@if($division->is_active || (int)$form['division_id'] === $division->id)<option value="{{ $division->id }}">{{ $division->code }} — {{ $division->name }}{{ $division->is_active ? '' : ' (Nonaktif)' }}</option>@endif @endforeach</select></label>
                    <label class="field"><span class="field-label">Role akses *</span><select wire:model="form.role" required>@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
                    <label class="field"><span class="field-label">Email</span><input type="email" wire:model="form.email" maxlength="255" /></label>
                    <label class="field"><span class="field-label">Password {{ $editingId ? '(kosongkan jika tetap)' : '*' }}</span><input type="password" wire:model="form.password" minlength="8" maxlength="255" autocomplete="new-password" @required(!$editingId) /><small>Minimal 8 karakter.</small></label>
                    @if($jobs->where('is_active',true)->isEmpty() || $divisions->where('is_active',true)->isEmpty())<p class="wide">Buat jabatan dan divisi aktif terlebih dahulu melalui menu Master Data.</p>@endif
                @else
                    <label class="field wide"><span class="field-label">Deskripsi</span><textarea wire:model="form.description" maxlength="2000" rows="3"></textarea></label>
                @endif
                <label class="field master-status"><span class="field-label">Status</span><span><input type="checkbox" wire:model="form.is_active" /> Aktif</span></label>
            </div><div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeForm">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">Simpan {{ strtolower($title) }}</button></div></form>
        @endif</div>
    </dialog>
</div>