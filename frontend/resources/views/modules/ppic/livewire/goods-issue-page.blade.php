<div class="page-stack">
    <div class="page-title"><div><p class="eyebrow">PPIC · INVENTORY CONTROL</p><h1>Barang Keluar</h1><p>Pengeluaran Maintenance dan Operasional. Draft belum mengurangi stok.</p></div>
        @can('ppic.ic.issues.create')<button type="button" class="button button-primary" wire:click="openCreate">+ Buat pengeluaran</button>@endcan
    </div>
    @if(!$showForm && !$detail) @include('components.form-errors') @endif
    <section class="panel">
        <div class="panel-head"><div><h2>Riwayat barang keluar</h2><p>{{ $rows->total() }} dokumen</p></div><button type="button" class="button button-secondary" wire:click="$refresh">Muat ulang</button></div>
        <div class="issue-filters">
            <label class="field"><span class="field-label">Cari dokumen / referensi / penerima</span><input type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="BK-000001" /></label>
            <label class="field"><span class="field-label">Status</span><select wire:model.live="status"><option value="">Semua status</option><option value="draft">Draft</option><option value="confirmed">Dikonfirmasi</option><option value="cancelled">Dibatalkan</option><option value="reversed">Dibalikkan</option></select></label>
            <label class="field"><span class="field-label">Keperluan</span><select wire:model.live="purpose"><option value="">Semua keperluan</option><option value="maintenance">Maintenance</option><option value="operational">Operasional</option></select></label>
            <label class="field"><span class="field-label">Divisi</span><select wire:model.live="division"><option value="">Semua divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}">{{ $division->name }}</option>@endforeach</select></label>
            <label class="field"><span class="field-label">Dari</span><input type="date" wire:model.live="from" /></label><label class="field"><span class="field-label">Sampai</span><input type="date" wire:model.live="until" /></label>
        </div>
        <div class="table-shell"><table class="data-table procurement-table"><thead><tr><th>Dokumen</th><th>Tanggal</th><th>Keperluan</th><th>Divisi / penerima</th><th>Barang</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
            @forelse($rows as $row)<tr wire:key="issue-{{ $row->id }}"><td>{{ $row->reference }}<br /><small>{{ $row->request_reference }}</small></td><td>{{ $row->issued_on->format('d/m/Y') }}</td><td>{{ $row->purpose === 'maintenance' ? 'Maintenance' : 'Operasional' }}</td><td>{{ $row->division_name }}<br /><small>{{ $row->recipient }}</small></td><td>{{ $row->lines_count }}</td><td><span class="procurement-status">{{ $row->status_label }}</span></td><td><button type="button" class="button button-secondary button-sm" wire:click="viewIssue({{ $row->id }})">Detail</button></td></tr>
            @empty<tr><td colspan="7" class="procurement-empty">Belum ada dokumen yang sesuai. Buat draft pengeluaran atau ubah filter.</td></tr>@endforelse
        </tbody></table></div>
        @include('components.pagination', ['paginator' => $rows])
    </section>
    <dialog class="issue-dialog" wire:ignore.self x-data="{ screenKey: null }"
        x-effect="let open = $wire.showForm || $wire.selectedId !== null; let screen = $wire.showForm ? 'form-' + $wire.editingId : 'detail-' + $wire.selectedId; $nextTick(() => { if (open) { if (!$el.open) $el.showModal(); if (screenKey !== screen) { $el.scrollTop = 0; $el.querySelector('[autofocus]')?.focus(); screenKey = screen; } } else { if ($el.open) $el.close(); screenKey = null; } })"
        @cancel.prevent="$wire.showForm ? $wire.closeForm() : $wire.closeDetail()"
        aria-labelledby="issue-dialog-title">
    @if($showForm)
        <section aria-label="Formulir barang keluar">
            <div class="panel-head"><div><h2 id="issue-dialog-title">{{ $editingId ? 'Ubah draft' : 'Draft pengeluaran baru' }}</h2><p>Konfirmasi dilakukan terpisah setelah barang benar-benar diserahkan.</p></div><button type="button" class="button button-secondary" wire:click="closeForm">Tutup</button></div>
            @include('components.form-errors')
            <form wire:submit="save" class="entry-form">
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Tanggal pengeluaran *</span><input type="date" wire:model="form.issued_on" max="{{ now()->format('Y-m-d') }}" required autofocus /></label>
                    <label class="field"><span class="field-label">Keperluan *</span><select wire:model="form.purpose"><option value="maintenance">Maintenance</option><option value="operational">Operasional</option></select></label>
                    <label class="field"><span class="field-label">Divisi peminta *</span><select wire:model="form.division_id" required><option value="">Pilih divisi</option>@foreach($divisions->where('is_active', true) as $division)<option value="{{ $division->id }}">{{ $division->name }}</option>@endforeach</select></label>
                    <label class="field"><span class="field-label">Penerima barang *</span><input wire:model="form.recipient" maxlength="255" required /></label>
                    <label class="field"><span class="field-label">Mesin / aset tujuan</span><input wire:model="form.asset" maxlength="255" /></label>
                    <label class="field"><span class="field-label">Referensi permintaan</span><input wire:model="form.request_reference" maxlength="255" /></label>
                    <label class="field"><span class="field-label">Keterangan</span><textarea wire:model="form.notes" maxlength="2000"></textarea></label>
                </div>
                <h3>Daftar barang</h3>
                @if($items->isEmpty())<p>Belum ada barang persediaan aktif. Daftarkan Material, Sparepart, Perlengkapan, atau Barang Habis Pakai di Master Barang, lalu catat penerimaannya melalui alur pembelian.</p>@endif
                @foreach($form['lines'] ?? [] as $index => $line)
                    @php($item = $items->firstWhere('id', (int) ($line['product_id'] ?? 0)))
                    <div class="issue-line" wire:key="issue-line-{{ $draftToken }}-{{ $index }}">
                        <label class="field"><span class="field-label">Barang {{ $index + 1 }} *</span><select wire:model.live="form.lines.{{ $index }}.product_id" required><option value="">Pilih barang</option>@foreach($items as $option)<option value="{{ $option->id }}">{{ $option->code }} · {{ $option->name }}</option>@endforeach</select></label>
                        <label class="field"><span class="field-label">Jumlah *</span><input type="number" wire:model="form.lines.{{ $index }}.quantity" min="0.000001" max="999999999" step="0.000001" required /><small>{{ $item?->unit->code ?? '—' }} · Tersedia {{ $item ? number_format((float) app(\App\Modules\Ppic\Services\InventoryStockService::class)->available($item), 6, ',', '.') : '—' }}</small></label>
                        <button type="button" class="button button-secondary" wire:click="removeLine({{ $index }})" @disabled(count($form['lines']) <= 1) aria-label="Hapus barang {{ $index + 1 }}">Hapus</button>
                    </div>
                @endforeach
                <div class="form-footer"><button type="button" class="button button-secondary" wire:click="addLine" @disabled(count($form['lines'] ?? []) >= 50)>+ Tambah barang</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">Simpan draft</button></div>
            </form>
        </section>
    @elseif($detail)
        <section aria-label="Detail barang keluar">
            <div class="panel-head"><div><h2 id="issue-dialog-title">{{ $detail->reference }}</h2><p>{{ $detail->status_label }} · {{ $detail->issued_on->format('d/m/Y') }}</p></div><button type="button" class="button button-secondary" wire:click="closeDetail" autofocus>Tutup detail</button></div>
            @include('components.form-errors')
            <div class="issue-detail">
                <dl><dt>Keperluan</dt><dd>{{ $detail->purpose === 'maintenance' ? 'Maintenance' : 'Operasional' }}</dd><dt>Divisi / penerima</dt><dd>{{ $detail->division_name }} · {{ $detail->recipient }}</dd><dt>Mesin / aset</dt><dd>{{ $detail->asset ?: '—' }}</dd><dt>Referensi permintaan</dt><dd>{{ $detail->request_reference ?: '—' }}</dd><dt>Keterangan</dt><dd>{{ $detail->notes ?: '—' }}</dd></dl>
            </div>
            <div class="table-shell"><table class="data-table"><thead><tr><th>Barang</th><th class="numeric">Jumlah</th><th>Satuan</th></tr></thead><tbody>@foreach($detail->lines as $line)<tr><td>{{ $line->product_code }} · {{ $line->product_name }}</td><td class="numeric">{{ number_format((float) $line->quantity, 6, ',', '.') }}</td><td>{{ $line->uom_code }}</td></tr>@endforeach</tbody></table></div>
            <div class="issue-detail"><h3>Riwayat dokumen</h3><ul><li>Dibuat {{ $detail->created_at->format('d/m/Y H:i') }} oleh {{ $detail->creator?->name }}</li><li>Terakhir diperbarui {{ $detail->updated_at->format('d/m/Y H:i') }} oleh {{ $detail->updater?->name }}</li>@if($detail->confirmed_at)<li>Dikonfirmasi {{ $detail->confirmed_at->format('d/m/Y H:i') }} oleh {{ $detail->confirmer?->name }}</li>@endif @if($detail->cancelled_at)<li>Dibatalkan {{ $detail->cancelled_at->format('d/m/Y H:i') }} oleh {{ $detail->canceller?->name }}</li>@endif @if($detail->reversed_at)<li>Dibalikkan {{ $detail->reversed_at->format('d/m/Y H:i') }} oleh {{ $detail->reverser?->name }}. Alasan: {{ $detail->reversal_reason }}</li>@endif</ul></div>
            @if($detail->status === 'draft')
                <div class="issue-actions">
                    @can('ppic.ic.issues.create')<button type="button" class="button button-secondary" wire:click="openEdit({{ $detail->id }})">Ubah draft</button><button type="button" class="button button-secondary" wire:click="cancel" wire:confirm="Batalkan draft ini? Stok tidak berubah." wire:loading.attr="disabled">Batalkan draft</button>@endcan
                    @can('ppic.ic.issues.confirm')<button type="button" class="button button-primary" wire:click="confirm" wire:confirm="Konfirmasi bahwa barang sudah diserahkan? Stok gudang akan berkurang." wire:loading.attr="disabled">Konfirmasi pengeluaran</button>@endcan
                </div>
            @elseif($detail->status === 'confirmed')
                @can('ppic.ic.issues.reverse')<form wire:submit="reverse" wire:confirm="Balikkan pengeluaran dan kembalikan stok? Pastikan barang memang kembali atau pengeluaran salah dicatat." class="entry-form"><label class="field"><span class="field-label">Alasan pembalikan *</span><textarea wire:model="reversal_reason" minlength="5" maxlength="2000" required></textarea></label><button type="submit" class="button button-secondary" wire:loading.attr="disabled">Balikkan pengeluaran</button></form>@endcan
            @endif
        </section>
    @endif
    </dialog>
</div>
