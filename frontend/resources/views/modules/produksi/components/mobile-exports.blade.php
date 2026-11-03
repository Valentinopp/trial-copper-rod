<div class="mobile-exports">
    @if($disabled)
        <button type="button" class="button button-secondary" disabled>Unduh</button>
    @else
        <details class="mobile-export-menu" x-data @click.outside="$el.open = false" @keydown.escape.stop="$el.open = false">
            <summary class="button button-secondary" aria-label="Pilihan unduh laporan">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5"/></svg>
                Unduh
            </summary>
            <div class="mobile-export-options">
                <button type="button" wire:click="exportExcel" @click="$el.closest('details').open = false">Excel <span>.xlsx</span></button>
                <button type="button" wire:click="exportPNG" @click="$el.closest('details').open = false">Gambar <span>.png</span></button>
            </div>
        </details>
    @endif
</div>
