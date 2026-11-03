<div class="apple-loading-overlay"
     x-show="isLoading"
     x-transition:enter="apple-fade-enter"
     x-transition:enter-start="apple-fade-enter-start"
     x-transition:enter-end="apple-fade-enter-end"
     x-transition:leave="apple-fade-leave"
     x-transition:leave-start="apple-fade-leave-start"
     x-transition:leave-end="apple-fade-leave-end"
     role="status" aria-live="polite" aria-label="Memuat" style="display: none;">
    <div class="apple-loading-backdrop"></div>
    <div class="apple-loading-hud">
        <svg class="apple-spinner-svg" viewBox="0 0 36 36" width="36" height="36" fill="currentColor" aria-hidden="true">
            <g transform="translate(18, 18)">
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="1.0" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.91" transform="rotate(30)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.82" transform="rotate(60)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.73" transform="rotate(90)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.64" transform="rotate(120)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.55" transform="rotate(150)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.46" transform="rotate(180)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.37" transform="rotate(210)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.28" transform="rotate(240)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.20" transform="rotate(270)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.13" transform="rotate(300)" />
                <rect x="-1.5" y="-15" width="3" height="7.5" rx="1.5" ry="1.5" opacity="0.08" transform="rotate(330)" />
            </g>
        </svg>
        <span class="apple-loading-text" x-text="loadingText">Memuat…</span>
    </div>
</div>
