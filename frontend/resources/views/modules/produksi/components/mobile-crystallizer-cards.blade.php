@php
    use App\Modules\Produksi\Services\WipFormatter;
@endphp
<div class="mobile-record-list" aria-label="Rincian lifetime crystallizer">
    @foreach($items as $item)
        <article class="mobile-record-card" wire:key="mobile-crystallizer-{{ $item['id'] }}">
            <div class="mobile-record-heading">
                <div><span class="mobile-record-caption">CRYSTALLIZER</span><h3>{{ $item['crystallizer'] ?: '—' }}</h3></div>
                <span class="mobile-record-status">{{ $item['lifetime_total_hours'] !== null ? 'Selesai' : 'Belum lengkap' }}</span>
            </div>
            <p class="mobile-record-context">LOT {{ $item['lot'] ?: '—' }} <span>·</span> Take Up {{ $item['take_up'] ?: '—' }}</p>
            <div class="mobile-record-metrics">
                <div><span>Lifetime</span><strong>{{ WipFormatter::number($item['lifetime_total_hours'], 2) }} <small>jam</small></strong></div>
                <div><span>Berat timbang</span><strong>{{ WipFormatter::number($item['weight_kg'], 2) }} <small>kg</small></strong></div>
            </div>
            <dl class="mobile-record-timestamps">
                <div><dt>Mulai</dt><dd>{{ WipFormatter::dateTimeDisplay($item['start_date'], $item['start_time']) }}</dd></div>
                <div><dt>Selesai</dt><dd>{{ WipFormatter::dateTimeDisplay($item['stop_date'], $item['stop_time']) }}</dd></div>
            </dl>
            @if($item['remarks'])<p class="mobile-record-time">{{ $item['remarks'] }}</p>@endif
        </article>
    @endforeach
</div>
