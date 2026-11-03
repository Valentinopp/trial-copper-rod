@php
    use App\Modules\Produksi\Services\WipFormatter;

    $maxTotalHours = max(0, ...array_map(fn($i) => (float) ($i['total_hours'] ?? 0), $totalHoursBars ?: [['total_hours' => 0]]));
    $maxFreq = max(0, ...array_map(fn($i) => (float) ($i['frequency'] ?? 0), $frequencyBars ?: [['frequency' => 0]]));
    $maxAvg = max(0, ...array_map(fn($i) => (float) ($i['average_lifetime_hours'] ?? 0), $avgBars ?: [['average_lifetime_hours' => 0]]));
    $maxPutus = max([0, ...array_column($putusBars, 'putus_count')]);
    $sumPutus = array_sum(array_column($putusBars, 'putus_count'));
@endphp

<div class="page-stack">
    <div class="page-title">
        <div>
            <div class="eyebrow">ANALISIS PERALATAN</div>
            <h1>Monitoring Crystallizer</h1>
            <p>Pantau lifetime, frekuensi pasang, dan kejadian putus setiap crystallizer.</p>
        </div>
        <div class="page-action">
            <div class="form-buttons">
                @include('modules.produksi.components.mobile-exports', ['disabled' => empty($summary)])
                <button type="button" class="button button-secondary desktop-export-action" wire:click="exportPNG" @disabled(empty($summary))>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Ekspor PNG
                </button>
                <button type="button" class="button button-secondary desktop-export-action" wire:click="exportExcel" @disabled(empty($summary))>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Ekspor Excel
                </button>
            </div>
        </div>
    </div>

    <section class="panel report-filter-panel">
        <div class="crystal-filter-grid">
            <label class="field">
                <span class="field-label">Tanggal mulai</span>
                <input type="date" wire:model.live="startDate" />
            </label>
            <label class="field">
                <span class="field-label">Tanggal akhir</span>
                <input type="date" wire:model.live="endDate" />
            </label>
            <div class="report-filter-action">
                <button type="button" class="button button-primary" wire:click="refreshData" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="refreshData,startDate,endDate" style="display:inline-flex;align-items:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    </span>
                    <span wire:loading wire:target="refreshData,startDate,endDate" class="skeleton button-skeleton" style="width: 42px; height: 12px;"></span>
                    <span wire:loading.remove wire:target="refreshData,startDate,endDate">Muat ulang</span>
                    <span wire:loading wire:target="refreshData,startDate,endDate">Memuat…</span>
                </button>
            </div>
        </div>
    </section>

    {{-- 4 Panel Visualisasi Crystallizer --}}
    <div class="crystal-dashboard-grid">
        {{-- 1. Total Jam --}}
        <section class="panel crystal-chart-panel crystal-tone-blue">
            <div class="crystal-chart-head">
                <div class="crystal-chart-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/></svg>
                </div>
                <div class="crystal-chart-title">
                    <h2>Total Jam</h2>
                    <p>Akumulasi lifetime lengkap per crystallizer</p>
                </div>
                <span class="crystal-chart-count">{{ count($totalHoursBars) }} cryst.</span>
            </div>
            @if(empty($totalHoursBars))
                <div class="crystal-chart-empty">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                    <strong>Belum ada data</strong>
                    <span>Chart akan terisi setelah ada siklus lifetime lengkap di Papan Digital.</span>
                </div>
            @else
                <div class="crystal-bar-list">
                    @foreach($totalHoursBars as $item)
                        @php
                            $val = (float) $item['total_hours'];
                            $width = $maxTotalHours > 0 ? ($val / $maxTotalHours) * 100 : 0;
                        @endphp
                        <div class="crystal-bar-item">
                            <div class="crystal-bar-labels">
                                <span class="crystal-bar-name"><strong>Cryst. {{ $item['crystallizer'] }}</strong></span>
                                <strong class="crystal-bar-value">{{ WipFormatter::number($val, 2) }} jam</strong>
                            </div>
                            <div class="crystal-bar-track" role="img">
                                <div class="crystal-bar-fill" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- 2. Frek. Pasang* --}}
        <section class="panel crystal-chart-panel crystal-tone-green">
            <div class="crystal-chart-head">
                <div class="crystal-chart-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                </div>
                <div class="crystal-chart-title">
                    <h2>Frek. Pasang*</h2>
                    <p>Jumlah siklus lifetime yang lengkap</p>
                </div>
                <span class="crystal-chart-count">{{ count($frequencyBars) }} cryst.</span>
            </div>
            @if(empty($frequencyBars))
                <div class="crystal-chart-empty">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                    <strong>Belum ada data</strong>
                    <span>Chart akan terisi setelah ada siklus lifetime lengkap di Papan Digital.</span>
                </div>
            @else
                <div class="crystal-bar-list">
                    @foreach($frequencyBars as $item)
                        @php
                            $val = (float) $item['frequency'];
                            $width = $maxFreq > 0 ? ($val / $maxFreq) * 100 : 0;
                        @endphp
                        <div class="crystal-bar-item">
                            <div class="crystal-bar-labels">
                                <span class="crystal-bar-name"><strong>Cryst. {{ $item['crystallizer'] }}</strong></span>
                                <strong class="crystal-bar-value">{{ WipFormatter::number($val, 0) }} kali</strong>
                            </div>
                            <div class="crystal-bar-track" role="img">
                                <div class="crystal-bar-fill" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- 3. Rata-rata Lifetime (jam) --}}
        <section class="panel crystal-chart-panel crystal-tone-violet">
            <div class="crystal-chart-head">
                <div class="crystal-chart-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="crystal-chart-title">
                    <h2>Rata-rata Lifetime (jam)</h2>
                    <p>Rata-rata durasi per siklus lengkap</p>
                </div>
                <span class="crystal-chart-count">{{ count($avgBars) }} cryst.</span>
            </div>
            @if(empty($avgBars))
                <div class="crystal-chart-empty">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                    <strong>Belum ada data</strong>
                    <span>Chart akan terisi setelah ada siklus lifetime lengkap di Papan Digital.</span>
                </div>
            @else
                <div class="crystal-bar-list">
                    @foreach($avgBars as $item)
                        @php
                            $val = (float) $item['average_lifetime_hours'];
                            $width = $maxAvg > 0 ? ($val / $maxAvg) * 100 : 0;
                        @endphp
                        <div class="crystal-bar-item">
                            <div class="crystal-bar-labels">
                                <span class="crystal-bar-name"><strong>Cryst. {{ $item['crystallizer'] }}</strong></span>
                                <strong class="crystal-bar-value">{{ WipFormatter::number($val, 2) }} jam</strong>
                            </div>
                            <div class="crystal-bar-track" role="img">
                                <div class="crystal-bar-fill" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- 4. Kejadian Putus --}}
        <section class="panel crystal-chart-panel crystal-tone-blue">
            <div class="crystal-chart-head">
                <div class="crystal-chart-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/></svg>
                </div>
                <div class="crystal-chart-title">
                    <h2>Kejadian Putus</h2>
                    <p>Jumlah putus pada siklus lifetime lengkap</p>
                </div>
                <span class="crystal-chart-count">{{ count($putusBars) }} cryst.</span>
            </div>
            @if(empty($putusBars))
                <div class="crystal-chart-empty">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                    <strong>Belum ada data</strong>
                    <span>Kejadian putus akan dihitung setelah waktu Upcasting dan Aktual Pick Up lengkap.</span>
                </div>
            @else
                <div class="crystal-bar-list">
                    @foreach($putusBars as $item)
                        @php
                            $val = (int) $item['putus_count'];
                            $width = $maxPutus > 0 ? ($val / $maxPutus) * 100 : 0;
                        @endphp
                        <div class="crystal-bar-item">
                            <div class="crystal-bar-labels">
                                <span class="crystal-bar-name"><strong>Cryst. {{ $item['crystallizer'] }}</strong></span>
                                <strong class="crystal-bar-value">{{ WipFormatter::number($val, 0) }} kali</strong>
                            </div>
                            <div class="crystal-bar-track" role="img" aria-label="Crystallizer {{ $item['crystallizer'] }}: {{ $val }} kejadian putus dari {{ $item['frequency'] }} siklus lengkap">
                                <div class="crystal-bar-fill" style="width: {{ $width }}%"></div>
                            </div>
                            <small class="muted-text">{{ WipFormatter::number($item['putus_percentage'], 2) }}% dari {{ $item['frequency'] }} siklus lengkap</small>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="crystal-chart-footer">
                <span><i class="crystal-legend-dot"></i> Total kejadian putus</span>
                <strong>{{ WipFormatter::number($sumPutus, 0) }} kali</strong>
            </div>
        </section>
    </div>

    <div class="crystal-note crystal-dashboard-note desktop-record-view">
        Lifetime dihitung dari waktu Upcasting hingga Aktual Pick Up. Jam operasi shift tidak digunakan dalam perhitungan lifetime.
    </div>

    <section class="panel mobile-record-panel">
        <div class="panel-head">
            <div>
                <h2>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    Rincian lifetime crystallizer
                </h2>
                <p class="desktop-record-view">Riwayat turunan dari Papan Digital. Nilai hanya dihitung jika tanggal dan jam tersedia pada sumber.</p>
            </div>
            <span class="table-count">{{ WipFormatter::number(count($items), 0) }} baris</span>
        </div>

        @if(!empty($items))
            @include('modules.produksi.components.mobile-crystallizer-cards')
            <div class="table-shell crystal-detail-scroll desktop-record-view">
                <table class="data-table crystal-detail-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No Cryst.</th>
                            <th>No Take Up</th>
                            <th>Urutan LOT</th>
                            <th><span>Tanggal/Jam<br />Mulai (Upcasting)</span></th>
                            <th><span>Tanggal/Jam<br />Selesai (Aktual Pick Up)</span></th>
                            <th><span>Lifetime<br />Hari</span></th>
                            <th><span>Lifetime<br />Jam</span></th>
                            <th><span>Total Lifetime<br />(jam)</span></th>
                            <th>Jam Operasi</th>
                            <th>Berat (kg)</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $index => $item)
                            <tr wire:key="cryst-detail-{{ $item['id'] }}">
                                <td>{{ $index + 1 }}</td>
                                <td class="strong-cell">{{ $item['crystallizer'] ?: '—' }}</td>
                                <td>{{ $item['take_up'] ?: '—' }}</td>
                                <td>{{ $item['lot'] ?: '—' }}<small class="muted-text" style="display: block;">{{ $item['lot_position'] === null ? 'Urutan belum pasti' : 'LOT ke-'.$item['lot_position'] }}</small></td>
                                <td class="nowrap">{{ WipFormatter::dateTimeDisplay($item['start_date'], $item['start_time']) }}</td>
                                <td class="nowrap">{{ WipFormatter::dateTimeDisplay($item['stop_date'], $item['stop_time']) }}</td>
                                <td class="numeric">{{ $item['lifetime_days'] === null ? '—' : WipFormatter::number($item['lifetime_days'], 0) }}</td>
                                <td class="numeric">{{ $item['lifetime_hours_part'] === null ? '—' : WipFormatter::number($item['lifetime_hours_part'], 2) }}</td>
                                <td class="numeric">{{ $item['lifetime_total_hours'] === null ? '—' : WipFormatter::number($item['lifetime_total_hours'], 2) }}</td>
                                <td class="numeric">{{ $item['operating_hours'] === null ? '—' : WipFormatter::number($item['operating_hours'], 2) }}</td>
                                <td class="numeric">{{ $item['weight_kg'] === null ? '—' : WipFormatter::number($item['weight_kg'], 2) }}</td>
                                <td class="remarks-cell">{{ $item['remarks'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                </div>
                <strong>Belum ada rincian lifetime</strong>
                <p>Rincian lifetime akan muncul setelah catatan Papan Digital memiliki waktu mulai atau akhir. Rekap hanya menghitung siklus lengkap.</p>
            </div>
        @endif
    </section>
</div>
