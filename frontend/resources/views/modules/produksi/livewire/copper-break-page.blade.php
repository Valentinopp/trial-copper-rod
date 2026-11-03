@php
    use App\Modules\Produksi\Enums\EventStatus;
    use App\Modules\Produksi\Services\WipFormatter;

    $maxEventCount = max(1, ...array_map(fn($i) => $i['count'], $eventSummary ?: [['count' => 1]]));
    $sumEventCount = array_sum(array_column($eventSummary, 'count'));

    $maxCatCount = max(1, ...array_map(fn($i) => $i['count'], $categorySummary ?: [['count' => 1]]));
    $sumCatCount = array_sum(array_column($categorySummary, 'count'));
@endphp

<div class="page-stack mobile-copper-break-page">
    <div class="page-title">
        <div>
            <div class="eyebrow">MONITORING KEJADIAN PRODUKSI</div>
            <h1>Monitoring Copper Putus</h1>
            <p>Pilih kejadian dari Papan Digital, lalu lengkapi SPV dan detailnya.</p>
        </div>
        <div class="page-action">
            <div class="form-buttons">
                @include('modules.produksi.components.mobile-exports', ['disabled' => empty($entries)])
                <button type="button" class="button button-primary" wire:click="openCopperForm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah catatan
                </button>
                <button type="button" class="button button-secondary desktop-export-action" wire:click="exportPNG" @disabled(empty($entries))>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Ekspor PNG
                </button>
                <button type="button" class="button button-secondary desktop-export-action" wire:click="exportExcel" @disabled(empty($entries))>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Ekspor Excel
                </button>
            </div>
        </div>
    </div>

    {{-- 4 Kartu Statistik --}}
    <div class="stats-grid stats-four copper-stats">
        <div class="stat-card">
            <div class="stat-icon orange">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </div>
            <div class="stat-copy">
                <span>Kejadian dari papan</span>
                <strong>{{ WipFormatter::number($total, 0) }}</strong>
                <small>Status Putus dan Putus + LOT baru</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
            </div>
            <div class="stat-copy">
                <span>Sudah dilengkapi</span>
                <strong>{{ WipFormatter::number($completedCount, 0) }}</strong>
                <small>SPV, kejadian, dan kategori terisi</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-copy">
                <span>Perlu detail</span>
                <strong>{{ WipFormatter::number($pendingCount, 0) }}</strong>
                <small>Kejadian yang belum lengkap</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon navy">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
            </div>
            <div class="stat-copy">
                <span>Total berat</span>
                <strong>{{ WipFormatter::number($totalWeight, 2) }} kg</strong>
                <small>{{ WipFormatter::number($crystalCount, 0) }} crystallizer pada filter</small>
            </div>
        </div>
    </div>

    {{-- 2 Grafik Copper Putus --}}
    <div class="crystal-dashboard-grid">
        <section class="panel crystal-chart-panel copper-chart-panel crystal-tone-orange">
            <div class="crystal-chart-head">
                <div class="crystal-chart-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <div class="crystal-chart-title">
                    <h2>Kejadian saat putus</h2>
                    <p>Jumlah berdasarkan jenis kejadian, terbanyak di atas</p>
                </div>
                <span class="crystal-chart-count">{{ $sumEventCount }} kejadian</span>
            </div>
            @if(empty($eventSummary))
                <div class="crystal-chart-empty">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                    <strong>Belum ada rekap</strong>
                    <span>Lengkapi detail kejadian untuk mulai membuat rekap.</span>
                </div>
            @else
                <div class="crystal-bar-list">
                    @foreach($eventSummary as $item)
                        <div class="crystal-bar-item">
                            <div class="crystal-bar-labels">
                                <span class="crystal-bar-name"><strong>{{ $item['label'] }}</strong></span>
                                <strong class="crystal-bar-value">{{ WipFormatter::number($item['count'], 0) }} kejadian</strong>
                            </div>
                            <div class="crystal-bar-track" role="img">
                                <div class="crystal-bar-fill" style="width: {{ ($item['count'] / $maxEventCount) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="panel crystal-chart-panel copper-chart-panel crystal-tone-blue">
            <div class="crystal-chart-head">
                <div class="crystal-chart-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <div class="crystal-chart-title">
                    <h2>Kategori</h2>
                    <p>Jumlah kejadian per kategori, terbanyak di atas</p>
                </div>
                <span class="crystal-chart-count">{{ $sumCatCount }} kejadian</span>
            </div>
            @if(empty($categorySummary))
                <div class="crystal-chart-empty">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                    <strong>Belum ada rekap</strong>
                    <span>Grafik kategori akan muncul setelah kategori dicatat.</span>
                </div>
            @else
                <div class="crystal-bar-list">
                    @foreach($categorySummary as $item)
                        <div class="crystal-bar-item">
                            <div class="crystal-bar-labels">
                                <span class="crystal-bar-name"><strong>{{ $item['label'] }}</strong></span>
                                <strong class="crystal-bar-value">{{ WipFormatter::number($item['count'], 0) }} kejadian</strong>
                            </div>
                            <div class="crystal-bar-track" role="img">
                                <div class="crystal-bar-fill" style="width: {{ ($item['count'] / $maxCatCount) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Tabel Riwayat Copper Putus --}}
    <section class="panel mobile-copper-break-history mobile-record-panel">
        <div class="panel-head wrap-head">
            <div>
                <h2>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Riwayat Copper Putus
                </h2>
                <p class="desktop-record-view">{{ WipFormatter::number($total, 0) }} kejadian pada filter aktif</p>
            </div>
            <div class="search-field">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" placeholder="Cari LOT, crystallizer, SPV, kejadian, kategori..." wire:model.live.debounce.250ms="search" />
                <button type="button" class="icon-button" wire:click="clearSearch" aria-label="Hapus pencarian">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="copper-filter-bar">
            <div class="copper-filter-dates">
                <label class="field">
                    <span class="field-label">Tanggal mulai</span>
                    <input type="date" wire:model.live="startDate" />
                </label>
                <label class="field">
                    <span class="field-label">Tanggal akhir</span>
                    <input type="date" wire:model.live="endDate" />
                </label>
                <button type="button" class="button button-secondary button-sm" wire:click="refreshEntries">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    Muat ulang
                </button>
            </div>
            <button type="button" class="button button-ghost button-sm" wire:click="resetFilters">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                Reset filter
            </button>
        </div>

        @if(!empty($entries))
            @include('modules.produksi.components.mobile-copper-break-cards')
            <div class="table-shell copper-history-scroll desktop-record-view">
                <table class="data-table copper-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Shift</th>
                            <th>Cryst.</th>
                            <th>LOT</th>
                            <th>Take Up</th>
                            <th>Coiling</th>
                            <th>Status papan</th>
                            <th>SPV</th>
                            <th>Lifetime otomatis (jam)</th>
                            <th>Kejadian saat putus</th>
                            <th>Kejadian setelah putus</th>
                            <th>Kategori</th>
                            <th>Berat (kg)</th>
                            <th>Frekuensi</th>
                            <th>Status detail</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $index => $entry)
                            @php
                                $prettyEventDate = WipFormatter::prettyDate($entry['event_date']);
                                $lotConfirm = $entry['lot'] ?: '—';
                            @endphp
                            <tr wire:key="copper-row-{{ $entry['board_entry_id'] }}">
                                <td>{{ $index + 1 }}</td>
                                <td class="nowrap">{{ $prettyEventDate }}</td>
                                <td>{{ $entry['event_time'] ?: '—' }}</td>
                                <td>
                                    @if($entry['shift'])
                                        <span class="shift-pill s{{ $entry['shift'] }}">S{{ $entry['shift'] }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="strong-cell">{{ $entry['crystallizer'] ?: '—' }}</td>
                                <td>{{ $entry['lot'] ?: '—' }}</td>
                                <td>{{ $entry['take_up'] ?: '—' }}</td>
                                <td>{{ $entry['cooling'] ?: '—' }}</td>
                                <td>
                                    <span class="event-status {{ EventStatus::color($entry['event_status']) }}">
                                        {{ EventStatus::label($entry['event_status']) }}
                                    </span>
                                </td>
                                <td>{{ $entry['supervisor'] ?: '—' }}</td>
                                <td class="numeric">{{ $entry['lifetime_hours'] === null ? '—' : WipFormatter::number($entry['lifetime_hours'], 2) }}</td>
                                <td class="copper-wrap-cell">{{ $entry['event_at_break'] ?: '—' }}</td>
                                <td class="copper-wrap-cell">{{ $entry['event_after_break'] ?: '—' }}</td>
                                <td class="copper-wrap-cell">{{ $entry['category'] ?: '—' }}</td>
                                <td class="numeric">{{ $entry['weight_kg'] === null ? '—' : WipFormatter::number($entry['weight_kg'], 2) }}</td>
                                <td class="numeric">{{ $entry['frequency'] === null ? '—' : WipFormatter::number($entry['frequency'], 0) }}</td>
                                <td>
                                    @if($entry['detail_complete'])
                                        <span class="detail-status done">Lengkap</span>
                                    @else
                                        <span class="detail-status pending">Perlu detail</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button type="button" class="icon-button" wire:click="edit({{ $entry['board_entry_id'] }})" title="Lengkapi detail">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                        </button>
                                        @if($entry['detail_id'])
                                            <button type="button" class="icon-button danger-hover"
                                                wire:click="clearDetails({{ $entry['board_entry_id'] }})"
                                                wire:confirm="Kosongkan detail tambahan untuk LOT {{ $lotConfirm }} tanggal {{ $prettyEventDate }}? Catatan di Papan Digital tidak akan dihapus."
                                                title="Kosongkan detail tambahan">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                            </button>
                                        @endif
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
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <strong>Belum ada copper putus dari Papan Digital</strong>
                <p>Catatan akan muncul otomatis saat status di Papan Digital adalah Putus.</p>
            </div>
        @endif
    </section>

    {{-- Formulir Detail Copper Putus --}}
    <dialog class="panel board-form-dialog" id="copper-break-form" aria-labelledby="copper-break-form-title"
            wire:ignore.self x-data
            x-effect="if ($wire.showCopperForm && !$el.open) { $el.showModal() } else if (!$wire.showCopperForm && $el.open) { $el.close() }"
            @close="$wire.set('showCopperForm', false)"
            @click="if ($event.target === $el) { const rect = $el.getBoundingClientRect(); if ($event.clientX < rect.left || $event.clientX > rect.right || $event.clientY < rect.top || $event.clientY > rect.bottom) $el.close() }">
        <div class="panel-head">
            <div>
                <h2 id="copper-break-form-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    {{ $editingId ? 'Lengkapi detail kejadian' : 'Input detail copper putus' }}
                </h2>
                <p>Pilih kejadian yang perlu dilengkapi. Data utama akan terisi otomatis.</p>
            </div>
                <button type="button" class="button button-ghost button-sm" @click="$el.closest('dialog').close()" aria-label="Tutup formulir">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    Tutup
                </button>
        </div>

        <form class="entry-form" wire:submit="save">
            <div class="form-grid two">
                <label class="field">
                    <span class="field-label">Kejadian sumber dari Papan Digital *</span>
                    <select required wire:model.live="board_entry_id">
                        <option value="">Pilih kejadian dari Papan Digital...</option>
                        @foreach($entries as $entry)
                            <option value="{{ $entry['board_entry_id'] }}">
                                {{ WipFormatter::prettyDate($entry['event_date']) }} · {{ $entry['shift'] ? "S{$entry['shift']}" : 'Shift —' }} · Cryst. {{ $entry['crystallizer'] ?: '—' }} · LOT {{ $entry['lot'] ?: '—' }} · {{ EventStatus::label($entry['event_status']) }}{{ $entry['detail_complete'] ? ' · detail lengkap' : ' · perlu detail' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="copper-source-summary">
                    @if($selectedSource)
                        <span class="copper-source-caption">DATA DARI PAPAN DIGITAL</span>
                        <div class="copper-source-values">
                            <span>
                                <small>Tanggal / Shift</small>
                                <strong>{{ WipFormatter::prettyDate($selectedSource['event_date']) }} · {{ $selectedSource['shift'] ? "S{$selectedSource['shift']}" : '—' }}</strong>
                            </span>
                            <span>
                                <small>Crystallizer / LOT</small>
                                <strong>{{ $selectedSource['crystallizer'] ?: '—' }} · {{ $selectedSource['lot'] ?: '—' }}</strong>
                            </span>
                            <span>
                                <small>Take Up / Coiling</small>
                                <strong>{{ $selectedSource['take_up'] ?: '—' }} / {{ $selectedSource['cooling'] ?: '—' }}</strong>
                            </span>
                            <span>
                                <small>Berat</small>
                                <strong>{{ $selectedSource['weight_kg'] === null ? '—' : WipFormatter::number($selectedSource['weight_kg'], 2) . ' kg' }}</strong>
                            </span>
                            <span>
                                <small>Lifetime otomatis</small>
                                <strong>{{ $selectedSource['lifetime_hours'] === null ? 'Menunggu waktu lengkap' : WipFormatter::number($selectedSource['lifetime_hours'], 2) . ' jam' }}</strong>
                            </span>
                        </div>
                    @else
                        <div class="copper-source-empty">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg>
                            Pilih kejadian untuk melihat data otomatis dari papan.
                        </div>
                    @endif
                </div>
            </div>

            <div class="form-section-title">
                <span>01</span>
                <div>
                    <strong>Detail tambahan</strong>
                    <small>Informasi yang tidak tersedia pada Papan Digital</small>
                </div>
            </div>

            @include('components.form-errors')

            <div class="form-grid three">
                <label class="field">
                    <span class="field-label">Jam kejadian (Aktual Pick Up)</span>
                    <input type="time" value="{{ $selectedSource['actual_pickup_time'] ?? '' }}" readonly />
                    <small>Terisi otomatis dari Papan Digital; tidak dapat diedit di halaman ini.</small>
                </label>

                <label class="field">
                    <span class="field-label">SPV *</span>
                    <select required wire:model="supervisor_user_id">
                        <option value="">Pilih SPV...</option>
                        @foreach($supervisors as $spv)
                            <option value="{{ $spv['value'] }}">{{ $spv['label'] }}</option>
                        @endforeach
                    </select>
                    <small>Diambil dari Karyawan aktif dengan jabatan SPV/Supervisor di divisi Produksi.</small>
                    @if($supervisors->isEmpty())<small>Belum ada SPV Produksi yang sesuai. Lengkapi jabatan dan divisi karyawan di Master Data.</small>@endif
                </label>

                <label class="field">
                    <span class="field-label">Kejadian saat putus</span>
                    <select wire:model="event_at_break"><option value="">Pilih kejadian...</option>@foreach($eventSuggestions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>@endforeach</select>
                </label>

                <label class="field">
                    <span class="field-label">Kejadian setelah putus</span>
                    <select wire:model="event_after_break"><option value="">Pilih kondisi...</option>@foreach($afterSuggestions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>@endforeach</select>
                </label>

                <label class="field">
                    <span class="field-label">Kategori</span>
                    <select wire:model="category"><option value="">Pilih kategori...</option>@foreach($categorySuggestions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>@endforeach</select>
                </label>

                <label class="field">
                    <span class="field-label">Frekuensi</span>
                    <input type="number" min="0" step="1" wire:model="frequency" placeholder="Jumlah bila ada" />
                </label>

                <label class="field">
                    <span class="field-label">Keterangan tambahan</span>
                    <input type="text" wire:model="remarks" placeholder="Catatan tambahan" />
                </label>
            </div>

            <div class="form-footer">
                <span class="form-hint">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                    Data utama mengikuti Papan Digital.
                </span>
                <div class="form-buttons">
                    <button type="button" class="button button-secondary" wire:click="resetCopperForm">Kosongkan</button>
                    <button class="button button-primary" type="submit" @disabled(!$board_entry_id) wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" style="display:inline-flex;align-items:center;">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/></svg>
                        </span>
                        <span wire:loading wire:target="save" class="skeleton button-skeleton" style="width: 58px; height: 12px;"></span>
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Simpan Detail' }}</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </div>
        </form>
    </dialog>
</div>
