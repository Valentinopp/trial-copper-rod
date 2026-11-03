@php
    use App\Modules\Produksi\Services\WipFormatter;
    use App\Modules\Produksi\Services\MonthlyOutputReportService;
    $format = fn ($value, $percentage = false) => MonthlyOutputReportService::format($value, $percentage);
@endphp

<div class="page-stack monthly-output-page">
    <div class="page-title">
        <div><div class="eyebrow">OUTPUT DAN PRODUKTIVITAS</div><h1>Rekap Output per Shift</h1><p>{{ $standalone ? 'Aktual timbang dan hasil kalkulasi produksi dalam satu laporan bulanan.' : 'Target, aktual, dan hasil kalkulasi produksi dalam satu laporan bulanan.' }}</p></div>
        <div class="page-action"><div class="form-buttons">
            @include('modules.produksi.components.mobile-exports', ['disabled' => empty($days)])

            <button type="button" class="button button-secondary desktop-export-action" wire:click="exportPNG" @disabled(empty($days))>Ekspor PNG</button>
            <button type="button" class="button button-secondary desktop-export-action" wire:click="exportExcel" @disabled(empty($days))>Ekspor Excel</button>
        </div></div>
    </div>

    <section class="panel monthly-output-filter" aria-label="Periode laporan output">
        <div><span class="planning-overline">BULAN LAPORAN</span><h2>{{ $monthLabel }}</h2></div>
        <div class="monthly-output-controls">
            <button type="button" class="button button-secondary" wire:click="moveMonth(-1)" aria-label="Bulan sebelumnya">←</button>
            <label class="field"><span class="sr-only">Bulan laporan</span><input type="month" wire:model.live="month" aria-label="Bulan laporan" /></label>
            <button type="button" class="button button-secondary" wire:click="moveMonth(1)" aria-label="Bulan berikutnya">→</button>
            <button type="button" class="button button-primary" wire:click="recalculate" wire:loading.attr="disabled">Muat ulang</button>
        </div>
    </section>

    <div class="stats-grid {{ $standalone ? 'stats-two' : 'stats-four' }}">
        @unless($standalone)
        <div class="stat-card"><div class="stat-copy"><span>Target MTD · PPIC</span><strong>{{ $format($asOfDay['plan_mtd_mt'] ?? null) }} MT</strong><small>Hingga {{ WipFormatter::prettyDate($asOfDay['date'] ?? null) }}</small></div></div>
        @endunless
        <div class="stat-card"><div class="stat-copy"><span>Aktual MTD · Timbang</span><strong>{{ $format($asOfDay['actual_mtd_mt'] ?? null) }} MT</strong><small>{{ $standalone ? 'Berat timbang dikurangi reject dari LOT selesai' : 'Berat hasil baik dari LOT terhubung' }}</small></div></div>
        @unless($standalone)
        <div class="stat-card"><div class="stat-copy"><span>Pencapaian MTD</span><strong>{{ $format($asOfDay['plan_mtd_achievement_pct'] ?? null, true) }}</strong><small>Aktual MTD ÷ target MTD</small></div></div>
        @endunless
        <div class="stat-card"><div class="stat-copy"><span>Output MTD · Kalkulasi</span><strong>{{ $format($asOfDay['output_mtd_mt'] ?? null) }} MT</strong><small>Diameter, kecepatan, dan durasi LOT</small></div></div>
    </div>

    <section class="panel monthly-output-panel" aria-labelledby="monthly-output-title">
        <div class="panel-head wrap-head"><div><h2 id="monthly-output-title">Output harian dan kumulatif · {{ $monthLabel }}</h2><p>{{ $standalone ? 'Aktual mengikuti waktu pick up. Hari produksi dimulai pukul 08.00; pick up sebelum 08.00 masuk hari sebelumnya. Seluruh berat dalam MT.' : 'Plan PPIC dan Aktual Timbang mengikuti tanggal jadwal asal. Seluruh berat dalam MT.' }}</p></div></div>
        @if($days)
            <p class="monthly-output-scroll-hint desktop-record-view">Geser ke kanan untuk melihat seluruh tanggal. Kolom shift dan indikator tetap terlihat.</p>
            <div class="monthly-output-scroll" tabindex="0" role="region" aria-label="Rekap output bulanan {{ $monthLabel }}">
                <table class="monthly-output-table">
                    <caption class="sr-only">Rekap {{ $monthLabel }}. Tanggal ditampilkan sebagai kolom dan indikator produksi sebagai baris.</caption>
                    <thead>
                        <tr><th rowspan="2" scope="col" class="monthly-shift">SHIFT</th><th rowspan="2" scope="col" class="monthly-metric">BULAN<br />TANGGAL</th><th colspan="{{ count($days) }}" scope="colgroup" class="monthly-month">{{ $monthLabel }}</th></tr>
                        <tr>@foreach($days as $day)<th scope="col" class="monthly-date {{ $day['date'] === $todayIso ? 'is-today' : '' }}" @if($day['date'] === $todayIso) aria-current="date" @endif>{{ (int) substr($day['date'], 8, 2) }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach($reportRows as $row)
                            <tr wire:key="monthly-output-{{ $month }}-{{ $row['key'] }}" class="monthly-row-{{ $row['kind'] }}">
                                @if($row['shift'] !== '')
                                    <td class="monthly-shift">{{ $row['shift'] }}</td><th scope="row" class="monthly-metric">{{ $row['label'] }}</th>
                                @else
                                    <th scope="row" colspan="2" class="monthly-metric monthly-metric-wide">{{ $row['label'] }}</th>
                                @endif
                                @foreach($row['values'] as $index => $value)
                                    @php $day = $days[$index]; @endphp
                                    <td class="{{ $day['date'] === $todayIso ? 'is-today' : '' }}" @if($row['key'] === 'plan_mt' && $day['unconverted_plans']) title="Konversi KG belum lengkap" @elseif($row['key'] === 'plan_mt' && !$day['plan_count']) title="Belum ada jadwal" @endif>{{ $format($value, $row['kind'] === 'percentage') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(collect($days)->sum('unconverted_plans'))<p class="monthly-output-note desktop-record-view">Konversi KG belum lengkap pada sebagian jadwal. Target, selisih, dan pencapaian terkait ditampilkan “—” sampai konversinya dilengkapi.</p>@endif
        @else
            <div class="empty-state"><strong>Pilih bulan laporan yang valid</strong><p>Gunakan pilihan bulan di atas untuk membuka rekap.</p></div>
        @endif
    </section>

</div>
