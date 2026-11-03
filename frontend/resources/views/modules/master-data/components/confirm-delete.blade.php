<dialog class="copper-warning-dialog" wire:ignore.self x-data="{ deleting: false }"
    x-effect="if ($wire.pendingDeleteId && !$el.open) $el.showModal(); else if (!$wire.pendingDeleteId && $el.open) $el.close()"
    @cancel.prevent="if (!deleting) $wire.cancelRemoval()" role="alertdialog" aria-labelledby="master-delete-title" aria-describedby="master-delete-description">
    <div class="copper-warning-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14H5V6M9 6V3h6v3M10 10v6m4-6v6"/></svg></div>
    <h2 id="master-delete-title">Hapus master data?</h2>
    <div id="master-delete-description" class="copper-warning-messages">
        <p><strong>{{ $pendingDeleteLabel }}</strong></p>
        <p>Data yang dihapus tidak dapat dikembalikan.</p>
        @if($pendingDeleteKind === 'supplier')<p>Hubungan dengan barang akan dilepas. Data barang tetap tersimpan.</p>@endif
        @error('delete')<p class="master-delete-error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="board-delete-actions">
        <button type="button" class="button button-secondary" autofocus :disabled="deleting" wire:click="cancelRemoval">Batal</button>
        <button type="button" class="button board-delete-button" :disabled="deleting"
            @click="if (!deleting) { deleting = true; try { await $wire.deleteConfirmed() } catch (error) { $dispatch('notify', { type: 'error', message: 'Data belum berhasil dihapus. Silakan coba kembali.' }) } finally { deleting = false } }"
            x-text="deleting ? 'Menghapus…' : 'Ya, hapus'"></button>
    </div>
</dialog>
