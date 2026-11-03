@if($paginator->hasPages())
    <nav class="procurement-pagination" aria-label="Halaman daftar">
        <span>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} · {{ $paginator->total() }} data</span>
        <div class="form-buttons">
            <button type="button" class="button button-secondary button-sm" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage()) wire:loading.attr="disabled">Sebelumnya</button>
            <button type="button" class="button button-secondary button-sm" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(!$paginator->hasMorePages()) wire:loading.attr="disabled">Berikutnya</button>
        </div>
    </nav>
@endif
