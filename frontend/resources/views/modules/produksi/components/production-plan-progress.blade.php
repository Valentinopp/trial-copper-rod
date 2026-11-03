@php use App\Modules\Produksi\Services\WipFormatter; @endphp
<section class="panel copper-plan-calendar" aria-label="Jadwal dan target produksi Copper Rod">
    <div class="planning-date-toolbar">
        <div class="planning-date-title"><span class="planning-overline">TARGET PRODUKSI</span><h2>{{ $planDateLabel }}</h2></div>
        <div class="planning-date-controls">
            <button type="button" class="button button-secondary planning-arrow" wire:click="movePlanWeek(-1)" aria-label="Minggu sebelumnya">←</button>
            <button type="button" class="button button-secondary" wire:click="planToday">Hari ini</button>
            <label class="planning-date-input"><input type="date" wire:model.live="planDate" aria-label="Tanggal rencana produksi" /></label>
            <button type="button" class="button button-secondary planning-arrow" wire:click="movePlanWeek(1)" aria-label="Minggu berikutnya">→</button>
        </div>
    </div>
    @error('planDate')<p class="form-error-summary" role="alert">{{ $message }}</p>@enderror
    <div class="planning-week copper-plan-week" aria-label="Target produksi per hari">
        @foreach($planWeek as $day)
            <button type="button" wire:key="copper-plan-day-{{ $day['date'] }}" wire:click="selectPlanDate('{{ $day['date'] }}')"
                class="planning-day {{ $day['date'] === $planDateKey ? 'is-selected' : '' }} {{ $day['date'] === $planToday ? 'is-today' : '' }}"
                aria-pressed="{{ $day['date'] === $planDateKey ? 'true' : 'false' }}" aria-label="{{ $day['day'] }} {{ $day['date'] }}, {{ $day['count'] }} jadwal">
                <span>{{ $day['day'] }}{{ $day['date'] === $planToday ? ' · Hari ini' : '' }}</span><strong>{{ $day['number'] }}</strong>
                @forelse($day['targets'] as $target)<b class="copper-day-target">{{ WipFormatter::number($target['quantity'], 3) }} {{ $target['unit'] }}</b>@empty<small>Belum ada target</small>@endforelse
                @if($day['count'])<small>{{ $day['count'] }} jadwal</small>@endif
            </button>
        @endforeach
    </div>
    <p class="copper-estimate-help desktop-record-view">Estimasi berjalan dihitung dari kecepatan take up × berat rod × durasi, maksimal 70 jam. Estimasi menjadi 0 saat putus atau sudah ditimbang setelah pick up. Aktual tetap memakai hasil timbang dikurangi reject, mengikuti jadwal asal.</p>
    <div class="table-shell" tabindex="0" role="region" aria-label="Perbandingan plan dan aktual Copper Rod"><table class="data-table plan-actual-table">
        <thead><tr><th>Jadwal / pesanan</th><th>Produk / customer</th><th class="numeric">Plan</th><th class="numeric">Estimasi berjalan</th><th class="numeric">Aktual</th><th class="numeric">Selisih aktual − plan</th><th>Progres</th><th>Aksi</th></tr></thead>
        <tbody>@forelse($productionSchedules as $schedule)
            @php $sale = $schedule->handoff->sale; $progress = $schedule->progress(); @endphp
            <tr wire:key="copper-plan-{{ $schedule->id }}"><td><strong>{{ $schedule->reference }}</strong><small>{{ $sale->reference }}</small></td><td><strong>{{ $sale->product_snapshot['name'] }}</strong><small>{{ $sale->customer_snapshot['name'] }}</small>@if($schedule->rod_diameter_mm)<small>Diameter {{ $schedule->rod_diameter_mm }} mm</small>@endif</td>
                <td class="numeric">{{ WipFormatter::number($schedule->quantity, 3) }} {{ $sale->unit_snapshot['code'] }}</td>
                <td class="numeric">{{ $progress['estimated'] === null ? '—' : WipFormatter::number($progress['estimated'], 3) }} {{ $sale->unit_snapshot['code'] }}</td>
                <td class="numeric strong-cell">{{ $progress['actual'] === null ? '—' : WipFormatter::number($progress['actual'], 3) }} {{ $sale->unit_snapshot['code'] }}@if((float) $progress['reject_kg'] > 0)<small>Reject {{ WipFormatter::number($progress['reject_kg'], 3) }} KG</small>@endif</td>
                <td class="numeric plan-actual-difference {{ $progress['difference'] !== null && (float) $progress['difference'] >= 0 ? 'is-target-met' : '' }}">@if($progress['difference'] !== null){{ (float) $progress['difference'] > 0 ? '+' : '' }}{{ WipFormatter::number($progress['difference'], 3) }} {{ $sale->unit_snapshot['code'] }}<small>{{ $progress['percentage'] }}% tercapai</small>@else<small>Konversi KG belum tersedia</small>@endif</td>
                <td><span class="planning-status">{{ $progress['status'] }}</span><small>{{ $progress['lots'] }} LOT · {{ $progress['running'] }} berjalan</small></td>
                <td><button type="button" class="button button-secondary button-sm" wire:click="useProductionSchedule({{ $schedule->id }})" @disabled(!$schedule->rod_specification_id || !$schedule->kg_per_unit)>Input LOT</button>@if(!$schedule->rod_specification_id)<small>Periksa spesifikasi produk pada SO</small>@endif</td>
            </tr>
        @empty<tr><td colspan="8" class="catalog-empty">Belum ada jadwal Production Plan pada tanggal ini. Pilih tanggal lain atau minta planner membuat jadwal.</td></tr>@endforelse</tbody>
    </table></div>
</section>
