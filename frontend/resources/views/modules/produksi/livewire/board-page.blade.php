@php
    use App\Modules\Produksi\Enums\EventStatus;
    use App\Modules\Produksi\Services\WipFormatter;
@endphp

<div class="page-stack mobile-board-page" x-data="{
    deleteId: null,
    deleteLot: '',
    deleteDate: '',
    deleting: false,
    async confirmDelete() {
        if (this.deleting || this.deleteId === null) return;
        this.deleting = true;
        try {
            await this.$wire.remove(this.deleteId);
            this.$refs.deleteConfirm.close();
        } catch (error) {
            this.$refs.deleteConfirm.close();
            this.$dispatch('notify', { type: 'error', message: 'Catatan belum berhasil dihapus. Silakan coba kembali.' });
        } finally {
            this.deleting = false;
        }
    }
}">
    <div class="page-title">
        <div>
            <div class="eyebrow">PENCATATAN PRODUKSI</div>
            <h1>Digital WIP Coiling Board</h1>
            <p>Catat produksi dan telusuri kembali riwayat LOT dalam satu tempat.</p>
        </div>
        <div class="page-action">
            <div class="form-buttons">
                @include('modules.produksi.components.mobile-exports', ['disabled' => $entries->isEmpty()])
                <button type="button" wire:click="openBoardForm" class="button button-primary add-board-entry">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah catatan
                </button>
                <button type="button" class="button button-secondary desktop-export-action" wire:click="exportPNG" @disabled($entries->isEmpty())>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Ekspor PNG
                </button>
                <button type="button" class="button button-secondary desktop-export-action" wire:click="exportExcel" @disabled($entries->isEmpty())>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Ekspor Excel
                </button>
            </div>
        </div>
    </div>

    @unless($standalone)
        @include('modules.produksi.components.production-plan-progress')
    @endunless

    <section class="panel mobile-record-panel">
        <div class="panel-head wrap-head">
            <div>
                <h2>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Riwayat papan
                </h2>
                <p class="desktop-record-view">{{ WipFormatter::number($total, 0) }} catatan pada rentang tanggal terpilih</p>
            </div>
            <div class="search-field">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" aria-label="Cari riwayat berdasarkan LOT, Take Up, atau Crystallizer" placeholder="Cari LOT, Take Up, Crystallizer..." wire:model.live.debounce.250ms="search" />
                <button type="button" class="icon-button" wire:click="clearSearch" aria-label="Hapus pencarian">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="filter-bar">
            <div class="quick-filters">
                @foreach ([7, 14, 30] as $days)
                    @php
                        $periodSelected = $endDate === now('Asia/Jakarta')->format('Y-m-d')
                            && $startDate === now('Asia/Jakarta')->subDays($days - 1)->format('Y-m-d');
                    @endphp
                    <button type="button" wire:click="setQuickPeriod({{ $days }})"
                            class="chip-button {{ $periodSelected ? 'is-selected' : '' }}"
                            aria-pressed="{{ $periodSelected ? 'true' : 'false' }}"
                            wire:loading.attr="disabled" wire:target="setQuickPeriod">
                        {{ $days }} hari
                    </button>
                @endforeach
            </div>
            <div class="date-range">
                <label>
                    <span>Dari</span>
                    <input type="date" wire:model.live="startDate" />
                </label>
                <label>
                    <span>Sampai</span>
                    <input type="date" wire:model.live="endDate" />
                </label>
                <button type="button" class="button button-secondary button-sm" wire:click="refreshEntries" wire:loading.attr="disabled">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    Muat ulang
                </button>
            </div>
        </div>

        <div wire:loading.delay wire:target="startDate,endDate,search,setQuickPeriod,clearSearch,refreshEntries" class="skeleton-table-wrap desktop-record-view" role="status" aria-label="Memuat data">
            <table class="data-table skeleton-table" aria-hidden="true">
                <thead>
                    <tr>
                        @for($i = 0; $i < 18; $i++)
                            <th><span class="skeleton" style="width: {{ 55 + ($i % 3) * 12 }}px; height: 12px;"></span></th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @for($row = 0; $row < 6; $row++)
                        <tr>
                            @for($col = 0; $col < 18; $col++)
                                <td><span class="skeleton" style="width: {{ 42 + (($row + $col) % 4) * 13 }}px; height: 12px;"></span></td>
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <div wire:loading.remove wire:target="startDate,endDate,search,setQuickPeriod,clearSearch,refreshEntries">
            @if($entries->isNotEmpty())
                @include('modules.produksi.components.mobile-board-cards')
                <p class="table-scroll-hint desktop-record-view">Geser tabel untuk melihat semua kolom. Tombol edit dan hapus tetap berada di sisi kanan.</p>
                <div class="table-shell wide-table board-history-scroll desktop-record-view" tabindex="0" role="region" aria-label="Riwayat papan. Gulir vertikal untuk melihat catatan lain dan horizontal untuk melihat kolom tambahan. Kolom Aksi tetap terlihat.">
                    <table class="data-table board-table">
                        <thead>
                            <tr>
                                <th><span>Tanggal<br />Operasi</span></th>
                                <th>Shift</th>
                                <th>Take Up</th>
                                <th><span>Take Up Speed<br />(mm/menit)</span></th>
                                <th><span>Diameter Cu Rod<br />(mm)</span></th>
                                <th>Coiling</th>
                                <th>Servo</th>
                                <th><span>No.<br />Crystallizer</span></th>
                                <th>Upcasting</th>
                                <th><span>Estimasi<br />Pick Up</span></th>
                                <th><span>Aktual<br />Pick Up</span></th>
                                <th>LOT</th>
                                <th><span>Jam<br />Operasi</span></th>
                                <th><span>Status<br />Kejadian</span></th>
                                <th><span>Estimasi berjalan<br />(kg)</span></th>
                                <th><span>Berat<br />(kg)</span></th>
                                @if($standalone)<th>Reject (kg)</th><th>Aktual hasil baik (kg)</th>@endif
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $e)
                                @php
                                    $prettyBoardDate = WipFormatter::prettyDate($e->board_date);
                                    $lotLabelForConfirm = $e->lot_sequence ?: ($e->lot ?: '—');
                                    $estimate = \App\Modules\Produksi\Services\LotEstimateService::calculate($e->getAttributes());
                                @endphp
                                <tr wire:key="board-row-{{ $e->id }}">
                                    <td class="nowrap">{{ $prettyBoardDate }}</td>
                                    <td>{{ $e->shift ?: '—' }}</td>
                                    <td class="strong-cell">{{ $e->take_up ?: '—' }}</td>
                                    <td class="numeric">{{ $e->take_up_speed === null ? '—' : WipFormatter::number($e->take_up_speed, 4) }}</td>
                                    <td class="numeric">{{ $e->rod_diameter_mm === null ? '—' : (string) $e->rod_diameter_mm }}</td>
                                    <td>{{ $e->cooling ?: '—' }}</td>
                                    <td>
                                        @if($e->servo_conflict)
                                            <span class="servo-pill conflict">Konflik</span>
                                        @elseif($e->effective_servo)
                                            <span class="servo-pill {{ strtolower($e->effective_servo) }}">Servo {{ $e->effective_servo }}</span>
                                        @else
                                            <span class="muted-text">Belum terbaca</span>
                                        @endif
                                    </td>
                                    <td>{{ $e->crystallizer ?: '—' }}</td>
                                    <td class="nowrap">{{ WipFormatter::dateTimeDisplay($e->upcasting_date, $e->upcasting_time) }}</td>
                                    <td class="nowrap">{{ WipFormatter::dateTimeDisplay($e->estimated_pickup_date, $e->estimated_pickup_time) }}</td>
                                    <td class="nowrap">{{ WipFormatter::dateTimeDisplay($e->actual_pickup_date, $e->actual_pickup_time) }}</td>
                                    <td>
                                        {{ $e->lot_sequence ?: ($e->lot ?: '—') }}
                                        @if(!$standalone && $e->productionSchedule)<small class="muted-text" style="display: block;">{{ $e->productionSchedule->reference }} · Aktual {{ $e->production_actual_kg === null ? 'menunggu pick up' : WipFormatter::number($e->production_actual_kg, 3).' KG' }}</small>@endif
                                        <small class="muted-text" style="display: block;">{{ $e->lot_position === null ? 'Urutan belum pasti' : 'LOT ke-'.$e->lot_position }}</small>
                                    </td>
                                    <td class="numeric">
                                        {{ $e->operating_hours === null ? '—' : WipFormatter::number($e->operating_hours, 3) }}
                                        @if($e->upcasting_date && $e->upcasting_time && $e->operating_hours !== null)
                                            <small class="muted-text" style="display: block;">{{ $e->actual_pickup_date && $e->actual_pickup_time ? 'Selesai' : 'Berjalan' }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($e->normalized_event_status)
                                            <span class="event-status {{ EventStatus::color($e->normalized_event_status) }}">
                                                {{ EventStatus::label($e->normalized_event_status) }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="numeric lot-estimate-cell">{{ $estimate['current_kg'] === null ? '—' : WipFormatter::number($estimate['current_kg'], 3) }}
                                        @if($e->normalized_event_status === 'putus')<small>Estimasi direset · Putus</small>@elseif($estimate['hours'] !== null && $estimate['hours'] >= 70)<small>70 jam tercapai · Menunggu timbang</small>@endif
                                    </td>
                                    <td class="numeric">{{ $e->weight_kg === null ? '—' : WipFormatter::number($e->weight_kg, 3) }}</td>
                                    @if($standalone)<td class="numeric">{{ WipFormatter::number($e->reject_weight_kg, 3) }}</td><td class="numeric">{{ $e->production_actual_kg === null ? '?' : WipFormatter::number($e->production_actual_kg, 3) }}</td>@endif
                                    <td class="remarks-cell">{{ $e->remarks ?: '—' }}</td>
                                    <td>
                                        <div class="row-actions">
                                            <button type="button" class="icon-button" title="Edit" wire:click="edit({{ $e->id }})">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                            </button>
                                            <button type="button" class="icon-button danger-hover" title="Hapus"
                                                data-entry-id="{{ $e->id }}" data-lot="{{ $lotLabelForConfirm }}" data-date="{{ $prettyBoardDate }}"
                                                @click="deleteId = Number($el.dataset.entryId); deleteLot = $el.dataset.lot; deleteDate = $el.dataset.date; $refs.deleteConfirm.showModal()">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                    </div>
                    @if(trim($search) !== '')
                        <strong>Tidak ada catatan yang cocok</strong>
                        <p>Coba kata kunci lain atau perluas rentang tanggal pencarian.</p>
                        <button type="button" class="button button-secondary" wire:click="clearSearch">Hapus pencarian</button>
                    @else
                        <strong>Belum ada catatan di periode ini</strong>
                        <p>Pilih periode lain atau tambahkan catatan produksi melalui formulir.</p>
                        <button type="button" wire:click="openBoardForm" class="button button-secondary">Tambah catatan pertama</button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <dialog class="copper-warning-dialog board-delete-dialog" x-ref="deleteConfirm" wire:ignore.self role="alertdialog" aria-labelledby="board-delete-title" aria-describedby="board-delete-description" @cancel="if (deleting) $event.preventDefault()" @close="deleteId = null">
        <div class="copper-warning-icon" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14H5V6M9 6V3h6v3M10 10v6m4-6v6"/></svg>
        </div>
        <h2 id="board-delete-title">Hapus catatan produksi?</h2>
        <div id="board-delete-description" class="copper-warning-messages">
            <p>Apakah Anda yakin ingin menghapus catatan ini?</p>
            <p>LOT <strong x-text="deleteLot"></strong> · <span x-text="deleteDate"></span></p>
            <p>Catatan yang dihapus tidak dapat dikembalikan.</p>
        </div>
        <div class="board-delete-actions">
            <button type="button" class="button button-secondary" autofocus :disabled="deleting" @click="$refs.deleteConfirm.close()">Batal</button>
            <button type="button" class="button board-delete-button" :disabled="deleting" @click="confirmDelete()" x-text="deleting ? 'Menghapus…' : 'Ya, hapus'"></button>
        </div>
    </dialog>

    <dialog class="panel board-form-dialog" id="board-form" aria-labelledby="board-form-title"
            wire:ignore.self x-data
            x-effect="if ($wire.showBoardForm && !$el.open) { $el.showModal() } else if (!$wire.showBoardForm && $el.open) { $el.close() }"
            @close="$wire.set('showBoardForm', false)"
            @click="if ($event.target === $el) { const rect = $el.getBoundingClientRect(); if ($event.clientX < rect.left || $event.clientX > rect.right || $event.clientY < rect.top || $event.clientY > rect.bottom) $el.close() }">
        <div class="panel-head">
            <div>
                <h2 id="board-form-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    {{ $editingId ? 'Ubah catatan papan' : 'Input data papan' }}
                </h2>
                <p>{{ $editingId ? 'Perubahan akan disimpan ke catatan yang sama.' : 'Setiap kali menekan simpan, satu catatan masuk ke riwayat digital.' }}</p>
            </div>
                <button class="button button-ghost button-sm" type="button" @click="$el.closest('dialog').close()" aria-label="Tutup formulir">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    Tutup
                </button>
        </div>

        <form wire:submit="save" class="entry-form" novalidate>
            @if($standalone)
                <label class="field plan-source-field">
                    <span class="field-label">Spesifikasi Cu Rod <span class="required-marker" aria-hidden="true">*</span></span>
                    <select id="board-rod_diameter_mm" required wire:model.live="rod_diameter_mm" aria-invalid="{{ $errors->has('rod_diameter_mm') ? 'true' : 'false' }}">
                        <option value="">Pilih spesifikasi Cu Rod...</option>
                        @foreach($rodDiameters as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>@endforeach
                    </select>
                    @error('rod_diameter_mm')<small role="alert">{{ $message }}</small>@enderror
                </label>
            @else
            <label class="field plan-source-field"><span class="field-label">Jadwal Production Plan / SO <span class="required-marker" aria-hidden="true">*</span></span><select id="board-production_schedule_id" required wire:model.live="production_schedule_id" @disabled($existingLinkedPlan) aria-invalid="{{ $errors->has('production_schedule_id') ? 'true' : 'false' }}"><option value="" disabled>Pilih jadwal produksi dari SO...</option>@foreach($scheduleChoices as $choice)<option value="{{ $choice->id }}" @disabled(!$choice->rod_specification_id || !$choice->kg_per_unit || $choice->status !== 'scheduled')>{{ $choice->reference }} · {{ $choice->planned_on->format('d/m/Y') }} · {{ $choice->handoff->sale->reference }} · {{ $choice->handoff->sale->product_snapshot['name'] }}{{ !$choice->rod_specification_id ? ' (spesifikasi produk belum lengkap)' : '' }}</option>@endforeach</select><small>Wajib terhubung ke SO. Jika belum ada jadwal, minta PPIC menjadwalkan SO terlebih dahulu.</small>@error('production_schedule_id')<small role="alert">{{ $message }}</small>@enderror</label>
            @if($selectedProductionPlan)
                @php $planProgress = $selectedProductionPlan->progress(); @endphp
                <div class="plan-actual-context"><strong>{{ $selectedProductionPlan->handoff->sale->product_snapshot['name'] }}</strong><span>Plan {{ WipFormatter::number($selectedProductionPlan->quantity, 3) }} {{ $selectedProductionPlan->handoff->sale->unit_snapshot['code'] }} · Aktual tersimpan {{ $planProgress['actual'] === null ? '—' : WipFormatter::number($planProgress['actual'], 3) }} {{ $selectedProductionPlan->handoff->sale->unit_snapshot['code'] }}</span><small>Berat timbang baru dihitung sebagai aktual setelah tanggal/jam pick up dan status kejadian lengkap.</small></div>
            @endif
            @endif
            <div class="form-section-title">
                <span>01</span>
                <div>
                    <strong>Identitas catatan</strong>
                    <small>Tanggal produksi dan shift awal mengikuti waktu upcasting.</small>
                </div>
            </div>

            <div class="form-grid three">
                <label class="field">
                    <span class="field-label">LOT <span class="required-marker" aria-hidden="true">*</span></span>
                    <input id="board-lot" required maxlength="255" type="text" wire:model="lot" placeholder="Contoh: 10 atau 8B" aria-invalid="{{ $errors->has('lot') ? 'true' : 'false' }}" />
                    @error('lot')<small role="alert">{{ $message }}</small>@enderror
                </label>

                <label class="field">
                    <span class="field-label">No. Take Up <span class="required-marker" aria-hidden="true">*</span></span>
                    <select id="board-take_up" required wire:model.live="take_up" aria-invalid="{{ $errors->has('take_up') ? 'true' : 'false' }}">
                        <option value="">Pilih No. Take Up...</option>
                        @foreach($takeupOptions as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    @error('take_up')<small role="alert">{{ $message }}</small>@enderror
                </label>

                <label class="field">
                    <span class="field-label">Coiling <span class="required-marker" aria-hidden="true">*</span></span>
                    <select id="board-cooling" required wire:model.live="cooling" aria-invalid="{{ $errors->has('cooling') ? 'true' : 'false' }}">
                        <option value="">Pilih Coiling...</option>
                        @foreach($coilingOptions as $opt)
                            <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    @error('cooling')<small role="alert">{{ $message }}</small>@enderror
                </label>

                <label class="field">
                    <span class="field-label">No. Crystallizer <span class="required-marker" aria-hidden="true">*</span></span>
                    <select id="board-crystallizer" required wire:model="crystallizer" aria-invalid="{{ $errors->has('crystallizer') ? 'true' : 'false' }}">
                        <option value="">Pilih crystallizer...</option>
                        @foreach($crystallizerOptions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>@endforeach
                    </select>
                    @if($crystallizerOptions->isEmpty())<small>Belum ada crystallizer aktif. Hubungi admin untuk menambahkannya di Master Data.</small>@endif
                    @error('crystallizer')<small role="alert">{{ $message }}</small>@enderror
                </label>

                <label class="field">
                    <span class="field-label">Take Up Speed (mm/menit) <span class="required-marker" aria-hidden="true">*</span></span>
                    <input id="board-take_up_speed" type="number" min="0.0001" max="99999999.9999" step="0.0001" placeholder="Contoh: 1590" required wire:model.live.debounce.250ms="take_up_speed" aria-invalid="{{ $errors->has('take_up_speed') ? 'true' : 'false' }}" />
                    @error('take_up_speed')<small role="alert">{{ $message }}</small>@enderror
                </label>


                <label class="field" wire:key="board-event-status-field">
                    <span class="field-label">Status kejadian @if($actual_pickup_date !== '' || $actual_pickup_time !== '')<span class="required-marker" aria-hidden="true">*</span>@endif</span>
                    <select id="board-event_status" @required($actual_pickup_date !== '' || $actual_pickup_time !== '') aria-invalid="{{ $errors->has('event_status') ? 'true' : 'false' }}" wire:model.live="event_status">
                        <option value="">Pilih status...</option>
                        @foreach($eventStatuses as $item)
                            <option value="{{ $item['value'] }}">{{ $item['label'] }}</option>
                        @endforeach
                    </select>
                    <small>Wajib dipilih jika Aktual Pick Up diisi.</small>
                    @error('event_status')<small role="alert">{{ $message }}</small>@enderror
                </label>

            </div>

            <div class="form-section-title">
                <span>02</span>
                <div>
                    <strong>Waktu produksi</strong>
                    <small>Isi tanggal dan jam sebenarnya.</small>
                </div>
            </div>

            <div class="production-time-grid">
                <fieldset class="production-time-group">
                    <legend>Upcasting</legend>
                    <p>Awal proses produksi</p>
                    <div class="time-field-pair">
                        <label class="field"><span class="field-label">Tanggal <span class="required-marker" aria-hidden="true">*</span></span><input id="board-upcasting_date" type="date" wire:model.live="upcasting_date" required aria-invalid="{{ $errors->has('upcasting_date') ? 'true' : 'false' }}" />@error('upcasting_date')<small role="alert">{{ $message }}</small>@enderror</label>
                        <label class="field"><span class="field-label">Jam <span class="required-marker" aria-hidden="true">*</span></span><input id="board-upcasting_time" type="time" wire:model.live="upcasting_time" required aria-invalid="{{ $errors->has('upcasting_time') ? 'true' : 'false' }}" />@error('upcasting_time')<small role="alert">{{ $message }}</small>@enderror</label>
                    </div>
                </fieldset>
                <fieldset class="production-time-group">
                    <legend>Aktual Pick Up</legend>
                    <p>Opsional. Isi tanggal dan jam jika waktu aktual sudah diketahui.</p>
                    <div class="time-field-pair">
                        <label class="field"><span class="field-label">Tanggal</span><input id="board-actual_pickup_date" type="date" wire:model.live="actual_pickup_date" aria-invalid="{{ $errors->has('actual_pickup_date') ? 'true' : 'false' }}" />@error('actual_pickup_date')<small role="alert">{{ $message }}</small>@enderror</label>
                        <label class="field"><span class="field-label">Jam</span><input id="board-actual_pickup_time" type="time" wire:model.live="actual_pickup_time" aria-invalid="{{ $errors->has('actual_pickup_time') ? 'true' : 'false' }}" />@error('actual_pickup_time')<small role="alert">{{ $message }}</small>@enderror</label>
                    </div>
                </fieldset>
            </div>
            <div class="inline-calculation" id="board-lot-estimate" wire:key="board-lot-estimate-preview" aria-live="polite">
                <div><small>Estimasi berat LOT</small><strong>{{ WipFormatter::number($lotEstimate['current_mt'], 3) }} MT</strong><small wire:loading.delay wire:target="production_schedule_id,rod_diameter_mm,take_up_speed,upcasting_date,upcasting_time,actual_pickup_date,actual_pickup_time,event_status">Memperbarui estimasi…</small></div>
                <span>{{ $lotEstimate['current_mt'] === null ? ($standalone ? 'Pilih spesifikasi Cu Rod, lalu lengkapi kecepatan dan waktu produksi.' : 'Pilih jadwal produksi dari SO, lalu lengkapi kecepatan dan waktu produksi.') : 'Dihitung sampai Aktual Pick Up, atau waktu sekarang jika belum diisi. Status Putus mereset estimasi.' }}</span>
            </div>
            <div class="form-grid board-weight-grid {{ ($standalone || $production_schedule_id !== '') ? 'has-reject' : '' }}">
                <label class="field"><span class="field-label">Berat (kg)</span><input id="board-weight_kg" aria-invalid="{{ $errors->has('weight_kg') ? 'true' : 'false' }}" type="number" min="0" step="0.01" wire:model.live.debounce.250ms="weight_kg" placeholder="Contoh: 988.5" />@error('weight_kg')<small role="alert">{{ $message }}</small>@enderror</label>
                @if($standalone || $production_schedule_id !== '')<label class="field"><span class="field-label">Berat reject (kg)</span><input id="board-reject_weight_kg" type="number" min="0" max="999999999" step="0.000001" wire:model.live.debounce.250ms="reject_weight_kg" /><small>Aktual hasil baik = berat timbang − reject. Isi 0 jika seluruh hasil layak.</small>@error('reject_weight_kg')<small role="alert">{{ $message }}</small>@enderror</label>@endif
                <label class="field board-remarks-field"><span class="field-label">Keterangan</span><textarea rows="2" wire:model="remarks" placeholder="Catatan tambahan, misalnya Putus / Repro atau Panen"></textarea></label>
            </div>

            <div class="form-footer">
                <span class="form-hint">
                    <span class="required-marker" aria-hidden="true">*</span> Wajib diisi
                </span>
                <div class="form-buttons">
                    <button type="button" class="button button-secondary" wire:click="resetBoardForm">Kosongkan</button>
                    <button class="button button-primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" style="display:inline-flex;align-items:center;">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
                        </span>
                        <span wire:loading wire:target="save" class="skeleton button-skeleton" style="width: 58px; height: 12px;"></span>
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Simpan catatan' }}</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </div>
        </form>
    </dialog>
</div>
