@php
    use App\Modules\Produksi\Services\WipFormatter;
    use App\Modules\Produksi\Enums\EventStatus;
@endphp
<div class="mobile-record-list" aria-label="Riwayat LOT">
    @foreach($entries as $e)
        @php
            $estimate = \App\Modules\Produksi\Services\LotEstimateService::calculate($e->getAttributes());
            $completed = $e->production_actual_kg !== null;
        @endphp
        <article class="mobile-record-card" wire:key="mobile-board-{{ $e->id }}">
            <div class="mobile-record-heading">
                <div><span class="mobile-record-caption">LOT</span><h3>{{ $e->lot_sequence ?: ($e->lot ?: '—') }}</h3></div>
                @if($e->normalized_event_status)
                    <span class="event-status {{ EventStatus::color($e->normalized_event_status) }}">{{ EventStatus::label($e->normalized_event_status) }}</span>
                @else
                    <span class="mobile-record-status">Berjalan</span>
                @endif
            </div>
            <p class="mobile-record-context">Cryst. {{ $e->crystallizer ?: '—' }} <span>·</span> {{ $e->take_up ?: '—' }} / {{ $e->cooling ?: '—' }} <span>·</span> Ø {{ $e->rod_diameter_mm ?? '—' }} mm</p>
            <div class="mobile-record-metrics">
                <div><span>{{ $completed ? 'Aktual hasil baik' : 'Estimasi berjalan' }}</span><strong>{{ $completed ? WipFormatter::number($e->production_actual_kg, 2) : WipFormatter::number($estimate['current_mt'], 3) }} <small>{{ $completed ? 'kg' : 'MT' }}</small></strong></div>
                <div><span>Jam operasi</span><strong>{{ WipFormatter::number($e->operating_hours, 2) }} <small>jam</small></strong></div>
            </div>
            <p class="mobile-record-time">Mulai {{ WipFormatter::dateTimeDisplay($e->upcasting_date, $e->upcasting_time) }}</p>
            <details class="mobile-record-details">
                <summary>Rincian LOT <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                <dl>
                    <div><dt>Kecepatan</dt><dd>{{ WipFormatter::number($e->take_up_speed, 2) }} mm/menit</dd></div>
                    <div><dt>Estimasi pickup</dt><dd>{{ WipFormatter::dateTimeDisplay($e->estimated_pickup_date, $e->estimated_pickup_time) }}</dd></div>
                    <div><dt>Aktual pickup</dt><dd>{{ WipFormatter::dateTimeDisplay($e->actual_pickup_date, $e->actual_pickup_time) }}</dd></div>
                    <div><dt>Berat timbang</dt><dd>{{ WipFormatter::number($e->weight_kg, 2) }} kg</dd></div>
                    <div><dt>Reject</dt><dd>{{ WipFormatter::number($e->reject_weight_kg, 2) }} kg</dd></div>
                    <div><dt>Keterangan</dt><dd>{{ $e->remarks ?: '—' }}</dd></div>
                </dl>
                <button type="button" class="button mobile-record-delete" data-entry-id="{{ $e->id }}" data-lot="{{ $e->lot_sequence ?: $e->lot }}" data-date="{{ WipFormatter::prettyDate($e->board_date) }}"
                    @click="deleteId = Number($el.dataset.entryId); deleteLot = $el.dataset.lot; deleteDate = $el.dataset.date; $refs.deleteConfirm.showModal()">Hapus catatan</button>
            </details>
            <button type="button" class="button mobile-record-edit" wire:click="edit({{ $e->id }})">{{ $completed ? 'Ubah catatan' : 'Perbarui LOT' }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
        </article>
    @endforeach
</div>
