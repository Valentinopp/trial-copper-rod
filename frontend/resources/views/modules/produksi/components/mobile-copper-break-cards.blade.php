@php
    use App\Modules\Produksi\Services\WipFormatter;
@endphp
<div class="mobile-record-list" aria-label="Riwayat Copper Putus">
    @foreach($entries as $entry)
        <article class="mobile-record-card" wire:key="mobile-copper-break-{{ $entry['board_entry_id'] }}">
            <div class="mobile-record-heading">
                <div><span class="mobile-record-caption">LOT</span><h3>{{ $entry['lot'] ?: '—' }}</h3></div>
                <span class="detail-status {{ $entry['detail_complete'] ? 'done' : 'pending' }}">{{ $entry['detail_complete'] ? 'Lengkap' : 'Perlu detail' }}</span>
            </div>
            <p class="mobile-record-context">Cryst. {{ $entry['crystallizer'] ?: '—' }} <span>·</span> {{ WipFormatter::prettyDate($entry['event_date']) }} {{ $entry['event_time'] }}</p>
            <div class="mobile-record-metrics">
                <div><span>Lifetime</span><strong>{{ WipFormatter::number($entry['lifetime_hours'], 2) }} <small>jam</small></strong></div>
                <div><span>Berat timbang</span><strong>{{ WipFormatter::number($entry['weight_kg'], 2) }} <small>kg</small></strong></div>
            </div>
            <details class="mobile-record-details">
                <summary>Rincian kejadian <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
                <dl>
                    <div><dt>SPV</dt><dd>{{ $entry['supervisor'] ?: 'Belum diisi' }}</dd></div>
                    <div><dt>Saat putus</dt><dd>{{ $entry['event_at_break'] ?: 'Belum diisi' }}</dd></div>
                    <div><dt>Setelah putus</dt><dd>{{ $entry['event_after_break'] ?: 'Belum diisi' }}</dd></div>
                    <div><dt>Kategori</dt><dd>{{ $entry['category'] ?: 'Belum diisi' }}</dd></div>
                    <div><dt>Frekuensi</dt><dd>{{ $entry['frequency'] ?? '—' }}</dd></div>
                    <div><dt>Keterangan</dt><dd>{{ $entry['remarks'] ?: '—' }}</dd></div>
                </dl>
                @if($entry['detail_id'])
                    <button type="button" class="button mobile-record-delete" wire:click="clearDetails({{ $entry['board_entry_id'] }})" wire:confirm="Kosongkan detail tambahan untuk LOT {{ $entry['lot'] }}? Catatan di Papan Digital tidak akan dihapus.">Kosongkan detail</button>
                @endif
            </details>
            <button type="button" class="button mobile-record-edit" wire:click="edit({{ $entry['board_entry_id'] }})">{{ $entry['detail_complete'] ? 'Ubah detail' : 'Lengkapi detail' }} <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
        </article>
    @endforeach
</div>
