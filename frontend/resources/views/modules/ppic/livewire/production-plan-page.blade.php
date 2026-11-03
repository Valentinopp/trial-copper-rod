@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 6, ',', '.'), '0'), ',');
@endphp
<div class="page-stack planning-page" wire:poll.30s>
    <div class="page-title planning-page-heading">
        <div><h1>Production Plan</h1><p>Atur tanggal dan jumlah produksi dari pesanan yang sudah siap.</p></div>
        <button type="button" class="button button-secondary" wire:click="$refresh" wire:loading.attr="disabled">Muat ulang</button>
    </div>

    <section class="panel planning-calendar" aria-label="Pilih tanggal produksi">
        <div class="planning-date-toolbar">
            <div class="planning-date-title"><span class="planning-overline">RENCANA PRODUKSI</span><h2>{{ $dateLabel }}</h2></div>
            <div class="planning-date-controls">
                <button type="button" class="button button-secondary planning-arrow" wire:click="moveDate(-7)" aria-label="Minggu sebelumnya"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg></button>
                <button type="button" class="button button-secondary" wire:click="goToToday">Hari ini</button>
                <label class="planning-date-input"><input type="date" wire:model.live="selectedDate" aria-label="Tanggal produksi" /></label>
                <button type="button" class="button button-secondary planning-arrow" wire:click="moveDate(7)" aria-label="Minggu berikutnya"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m9 18 6-6-6-6"/></svg></button>
            </div>
        </div>
        @error('selectedDate')<p class="planning-date-error" role="alert">{{ $message }}</p>@enderror
        <div class="planning-week" aria-label="Jadwal minggu terpilih">
            @foreach($week as $day)
                <button type="button" wire:key="planning-day-{{ $day['date'] }}" wire:click="selectDate('{{ $day['date'] }}')"
                    class="planning-day {{ $day['date'] === $dateKey ? 'is-selected' : '' }} {{ $day['date'] === $today ? 'is-today' : '' }}" aria-pressed="{{ $day['date'] === $dateKey ? 'true' : 'false' }}" aria-label="{{ $day['day'] }} {{ $day['date'] }}, {{ $day['count'] }} jadwal">
                    <span>{{ $day['day'] }}{{ $day['date'] === $today ? ' · Hari ini' : '' }}</span><strong>{{ $day['number'] }}</strong><small>{{ $day['count'] ? $day['count'].' jadwal' : 'Belum ada' }}</small>
                </button>
            @endforeach
        </div>
    </section>

    <div class="planning-summary" aria-label="Ringkasan rencana">
        <section class="planning-summary-card planning-summary-primary">
            <span>Plan vs aktual tanggal ini</span>
            <div class="planning-summary-amounts">
                @forelse($dailyTotals as $total)
                    <div><strong>{{ $quantity($total['quantity']) }} <small>{{ $total['unit'] }} plan</small></strong>
                        <p>Estimasi berjalan {{ $total['estimated'] === null ? '—' : $quantity($total['estimated']) }} {{ $total['unit'] }}</p>
                        <p>Aktual {{ $total['actual'] === null ? '—' : $quantity($total['actual']) }} {{ $total['unit'] }} · <span class="plan-actual-difference {{ $total['difference'] !== null && (float) $total['difference'] >= 0 ? 'is-target-met' : '' }}">Selisih {{ $total['difference'] === null ? '—' : ((float) $total['difference'] > 0 ? '+' : '').$quantity($total['difference']) }} {{ $total['unit'] }}</span></p>
                    </div>
                @empty<strong>0 <small>terjadwal</small></strong>@endforelse
            </div>
            <p>Estimasi LOT berjalan terpisah dari aktual timbang. Saat putus, estimasi menjadi 0. Aktual = berat timbang − reject.</p>
        </section>
        <section class="planning-summary-card"><span>Pesanan pada tanggal ini</span><strong>{{ $dailyOrderCount }} <small>pesanan</small></strong><p>{{ $dailyCount }} jadwal dari penyerahan Inventory Control</p></section>
        <section class="planning-summary-card"><span>Masih perlu dijadwalkan</span><strong>{{ $pendingCount }} <small>pesanan</small></strong><p>Termasuk pesanan yang baru dijadwalkan sebagian</p></section>
    </div>

    <section class="panel" aria-labelledby="planning-daily-title">
        <div class="panel-head wrap-head"><div><h2 id="planning-daily-title">Jadwal produksi</h2><p>{{ $dateLabel }} · {{ $dailyCount }} jadwal aktif</p></div>
            <label class="planning-checkbox"><input type="checkbox" wire:model.live="showCancelled" /> Tampilkan yang dibatalkan</label>
        </div>
        @if($schedules->isNotEmpty())
        <div class="table-shell" tabindex="0" role="region" aria-label="Jadwal produksi per tanggal"><table class="data-table planning-table">
            <thead><tr><th>Jadwal / pesanan</th><th>Produk / customer</th><th class="numeric">Plan</th><th class="numeric">Estimasi berjalan</th><th class="numeric">Aktual</th><th class="numeric">Selisih aktual − plan</th><th>Batas kirim</th><th>Progres Copper Rod</th><th>Planner</th><th>Aksi</th></tr></thead>
            <tbody>@foreach($schedules as $schedule)
                @php $sale = $schedule->handoff->sale; $progress = $schedule->progress(); @endphp
                <tr wire:key="schedule-{{ $schedule->id }}" class="{{ $schedule->status === 'cancelled' ? 'planning-cancelled-row' : '' }}">
                    <td><strong>{{ $schedule->reference }}</strong><span class="planning-cell-secondary">{{ $sale->reference }} · {{ $schedule->handoff->reference }}</span></td>
                    <td><strong>{{ $sale->product_snapshot['name'] }}</strong><span class="planning-cell-secondary">{{ $sale->customer_snapshot['name'] }}</span>@if($schedule->notes)<span class="planning-cell-note">{{ $schedule->notes }}</span>@endif</td>
                    <td class="numeric planning-quantity">{{ $quantity($schedule->quantity) }} <span>{{ $sale->unit_snapshot['code'] }}</span></td>
                    <td class="numeric planning-quantity">{{ $progress['estimated'] === null ? '—' : $quantity($progress['estimated']) }} <span>{{ $sale->unit_snapshot['code'] }}</span></td>
                    <td class="numeric planning-quantity">{{ $progress['actual'] === null ? '—' : $quantity($progress['actual']) }} <span>{{ $sale->unit_snapshot['code'] }}</span><span class="planning-cell-secondary">{{ $progress['lots'] }} LOT · {{ $progress['running'] }} berjalan</span>@if((float) $progress['reject_kg'] > 0)<span class="planning-cell-secondary">Reject {{ $quantity($progress['reject_kg']) }} KG</span>@endif</td>
                    <td class="numeric plan-actual-difference {{ $progress['difference'] !== null && (float) $progress['difference'] >= 0 ? 'is-target-met' : '' }}">@if($progress['difference'] !== null){{ (float) $progress['difference'] > 0 ? '+' : '' }}{{ $quantity($progress['difference']) }} {{ $sale->unit_snapshot['code'] }}<span class="planning-cell-secondary">{{ $progress['percentage'] }}% tercapai</span>@else<span class="planning-cell-secondary">Konversi KG belum tersedia</span>@endif</td>
                    <td class="nowrap">{{ $sale->ship_by->format('d/m/Y') }}@if($schedule->planned_on->gt($sale->ship_by))<span class="planning-cell-secondary planning-late">Lewat batas kirim</span>@endif</td>
                    <td><span class="planning-status {{ $schedule->status === 'cancelled' ? 'is-muted' : '' }}">{{ $progress['status'] }}</span>@if(!$schedule->rod_specification_id)<span class="planning-cell-secondary">Spesifikasi produk belum diatur</span>@else<span class="planning-cell-secondary">Diameter {{ $schedule->rod_diameter_mm }} mm</span>@endif</td>
                    <td>{{ $schedule->planner?->name ?? '—' }}<span class="planning-cell-secondary">{{ $schedule->updated_at->timezone('Asia/Bangkok')->format('d/m H:i') }}</span></td>
                    <td>@if($schedule->status === 'scheduled')<div class="planning-row-actions"><button type="button" class="button button-secondary button-sm" wire:click="openEdit({{ $schedule->id }})">Ubah</button><button type="button" class="button button-ghost button-sm" wire:click="askCancel({{ $schedule->id }})" @disabled($progress['lots'] > 0) title="{{ $progress['lots'] > 0 ? 'Sudah memiliki LOT Copper Rod' : 'Batalkan jadwal' }}">Batalkan</button></div>@else<span class="planning-cell-secondary">{{ $schedule->cancelled_at?->timezone('Asia/Bangkok')->format('d/m H:i') }}</span>@endif</td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @include('components.pagination', ['paginator' => $schedules])
        @else
        <div class="planning-empty"><span class="planning-empty-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M9 16h6"/></svg></span><strong>Belum ada jadwal pada tanggal ini</strong><p>Pilih pesanan di bawah, lalu tentukan jumlah yang akan diproduksi.</p><a href="#planning-backlog" class="button button-secondary">Pilih pesanan</a></div>
        @endif
    </section>

    <section class="panel planning-backlog" id="planning-backlog" aria-labelledby="planning-backlog-title">
        <div class="panel-head wrap-head"><div><h2 id="planning-backlog-title">Pesanan siap dijadwalkan</h2><p>Bahan sudah dicadangkan IC. Pesanan dengan batas kirim terdekat ditampilkan lebih dulu.</p></div><span class="planning-count">{{ $handoffs->total() }} pesanan</span></div>
        <div class="planning-backlog-filters">
            <label class="field"><span class="field-label">Cari pesanan</span><input type="search" maxlength="200" wire:model.live.debounce.300ms="search" placeholder="SO, PP, produk, atau customer" /></label>
            <label class="field"><span class="field-label">Tampilkan</span><select wire:model.live="backlogFilter"><option value="pending">Belum dijadwalkan penuh</option><option value="all">Semua pesanan dari IC</option></select></label>
        </div>
        <div class="table-shell" tabindex="0" role="region" aria-label="Pesanan dari Inventory Control"><table class="data-table planning-table">
            <thead><tr><th>Pesanan</th><th>Produk / customer</th><th>Batas kirim</th><th class="numeric">Jumlah pesanan</th><th class="numeric">Terjadwal</th><th class="numeric">Sisa dijadwalkan</th><th class="numeric" title="Total hasil timbang bersih LOT selesai dari seluruh jadwal pesanan">Aktual</th><th class="numeric" title="Aktual dikurangi jumlah pesanan; positif berarti kelebihan produksi">Sisa Aktual</th><th>Aksi</th></tr></thead>
            <tbody>@forelse($handoffs as $handoff)
                @php $sale = $handoff->sale; $remaining = $handoff->remaining_quantity; $actual = $handoff->actual_quantity; $remainingActual = $handoff->remaining_actual_quantity; @endphp
                <tr wire:key="planning-handoff-{{ $handoff->id }}">
                    <td><strong>{{ $sale->reference }}</strong><span class="planning-cell-secondary">{{ $handoff->reference }}</span></td>
                    <td><strong>{{ $sale->product_snapshot['name'] }}</strong><span class="planning-cell-secondary">{{ $sale->customer_snapshot['name'] }}</span>
                        <details class="planning-materials"><summary>{{ $sale->materialRequirements->count() }} material dicadangkan</summary>@if($sale->bom_snapshot)<p>{{ $sale->bom_snapshot['code'] }} · {{ $sale->bom_snapshot['name'] }}</p>@endif<ul class="production-material-list">@foreach($sale->materialRequirements as $item)<li>{{ $item->material_name }}: {{ $quantity($item->quantity) }} {{ $item->uom }}</li>@endforeach</ul></details>
                    </td>
                    <td class="nowrap">{{ $sale->ship_by->format('d/m/Y') }}@if($sale->ship_by->format('Y-m-d') < $today)<span class="planning-cell-secondary planning-late">Batas kirim terlewat</span>@elseif($sale->ship_by->format('Y-m-d') === $today)<span class="planning-cell-secondary planning-late">Kirim hari ini</span>@endif</td>
                    <td class="numeric">{{ $quantity($sale->quantity) }} <span class="planning-unit">{{ $sale->unit_snapshot['code'] }}</span></td>
                    <td class="numeric">{{ $quantity($handoff->scheduled_quantity ?? 0) }} <span class="planning-unit">{{ $sale->unit_snapshot['code'] }}</span></td>
                    <td class="numeric planning-quantity">{{ $quantity($remaining) }} <span>{{ $sale->unit_snapshot['code'] }}</span></td>
                    <td class="numeric planning-quantity">{{ $actual === null ? '—' : $quantity($actual) }} <span>{{ $sale->unit_snapshot['code'] }}</span>@if($actual === null)<span class="planning-cell-secondary">Konversi KG belum tersedia</span>@endif</td>
                    <td class="numeric planning-quantity {{ $remainingActual === null ? '' : 'planning-remaining-actual '.((float) $remainingActual >= 0 ? 'is-target-met' : 'is-below-target') }}">{{ $remainingActual === null ? '—' : ((float) $remainingActual > 0 ? '+' : '').$quantity($remainingActual) }} <span>{{ $sale->unit_snapshot['code'] }}</span></td>
                    <td>@if((float) $remaining > 0)<button type="button" class="button button-primary button-sm" wire:click="openSchedule({{ $handoff->id }})" wire:loading.attr="disabled">Jadwalkan</button><span class="planning-cell-secondary">{{ (float) $handoff->scheduled_quantity > 0 ? 'Terjadwal sebagian' : 'Menunggu perencanaan' }}</span>@else<span class="planning-status">Sudah terjadwal</span>@endif</td>
                </tr>
            @empty<tr><td colspan="9" class="procurement-empty">@if($search !== '')Tidak ada pesanan yang cocok dengan pencarian.@elseif($backlogFilter === 'pending')Tidak ada pesanan yang perlu dijadwalkan. Pesanan baru akan muncul setelah diserahkan dari IC → Kesiapan Penjualan.@elseBelum ada pesanan yang diserahkan Inventory Control.@endif</td></tr>@endforelse</tbody>
        </table></div>
        @include('components.pagination', ['paginator' => $handoffs])
    </section>

    <dialog class="marketing-dialog planning-dialog" wire:ignore.self x-data
        x-effect="let open = $wire.showForm; $nextTick(() => { if (open && !$el.open) { $el.showModal(); $el.querySelector('[autofocus]')?.focus(); } else if (!open && $el.open) $el.close(); })"
        @cancel.prevent="$wire.closeForm()" aria-labelledby="planning-dialog-title">
        <header class="catalog-modal-header"><div><h2 id="planning-dialog-title">{{ $editingId ? 'Ubah jadwal produksi' : 'Jadwalkan produksi' }}</h2><p>Satu pesanan dapat dibagi menjadi beberapa jadwal pada hari yang sama atau hari berbeda.</p></div><button type="button" class="button button-ghost" wire:click="closeForm">Tutup</button></header>
        <div class="catalog-modal-body">@if($showForm && $selectedHandoff)
            @php $sale = $selectedHandoff->sale; @endphp
            <div class="planning-order-context"><span>{{ $sale->reference }} · {{ $selectedHandoff->reference }}</span><strong>{{ $sale->product_snapshot['name'] }}</strong><p>{{ $sale->customer_snapshot['name'] }} · Batas kirim {{ $sale->ship_by->format('d/m/Y') }}</p><div><span>Jumlah pesanan <b>{{ $quantity($sale->quantity) }} {{ $sale->unit_snapshot['code'] }}</b></span><span>Maksimal pada jadwal ini <b>{{ $quantity($maximumQuantity) }} {{ $sale->unit_snapshot['code'] }}</b></span></div></div>
            @include('components.form-errors')
            <form wire:submit="save" class="entry-form catalog-modal-form">
                <div class="form-grid two">
                    <label class="field"><span class="field-label">Tanggal produksi *</span><input type="date" min="{{ $today }}" wire:model.live="form.planned_on" @disabled($editingHasLots) required />@error('form.planned_on')<small role="alert">{{ $message }}</small>@enderror</label>
                    <label class="field"><span class="field-label">Jumlah produksi *</span><span class="ic-quantity-input"><input type="number" min="0.000001" max="{{ $maximumQuantity }}" step="0.000001" wire:model="form.quantity" required autofocus /><span class="ic-quantity-uom">{{ $sale->unit_snapshot['code'] }}</span></span><button type="button" class="planning-fill-remaining" wire:click="useRemaining">Gunakan semua sisa ({{ $quantity($maximumQuantity) }} {{ $sale->unit_snapshot['code'] }})</button>@error('form.quantity')<small role="alert">{{ $message }}</small>@enderror</label>
                </div>
                @if($planningRodError)<p class="planning-notice" role="alert">{{ $planningRodError }}</p>@endif
                @if(($form['planned_on'] ?? '') > $sale->ship_by->format('Y-m-d'))<div class="planning-notice" role="status">Tanggal produksi melewati batas kirim {{ $sale->ship_by->format('d/m/Y') }}. Periksa kembali tanggal yang dipilih.</div>@endif
                <label class="field planning-notes-field"><span class="field-label">Catatan <span class="planning-optional">opsional</span></span><textarea rows="2" maxlength="2000" wire:model="form.notes" placeholder="Contoh: prioritaskan pesanan ini untuk pengiriman pagi."></textarea></label>
                <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeForm">Batal</button><button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save"><span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Simpan jadwal' }}</span><span wire:loading wire:target="save">Menyimpan…</span></button></div>
            </form>
        @endif</div>
    </dialog>

    <dialog class="marketing-dialog planning-cancel-dialog" wire:ignore.self x-data
        x-effect="let open = !!$wire.cancelId; $nextTick(() => { if (open && !$el.open) $el.showModal(); else if (!open && $el.open) $el.close(); })"
        @cancel.prevent="$wire.closeCancel()" aria-labelledby="planning-cancel-title">
        <header class="catalog-modal-header"><h2 id="planning-cancel-title">Batalkan jadwal ini?</h2></header>
        <div class="catalog-modal-body">@if($cancelSchedule)
            <p><strong>{{ $cancelSchedule->reference }}</strong> · {{ $cancelSchedule->handoff->sale->reference }}<br />{{ $cancelSchedule->planned_on->format('d/m/Y') }} · {{ $quantity($cancelSchedule->quantity) }} {{ $cancelSchedule->handoff->sale->unit_snapshot['code'] }}</p><p>Jumlah ini akan kembali ke sisa pesanan dan dapat dijadwalkan pada tanggal lain.</p>
            @error('cancel')<p class="form-error-summary" role="alert">{{ $message }}</p>@enderror
            <div class="form-footer"><button type="button" class="button button-secondary" wire:click="closeCancel" autofocus>Kembali</button><button type="button" class="button button-primary" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">Ya, batalkan jadwal</button></div>
        @endif</div>
    </dialog>
</div>
