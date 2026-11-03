// One navigation tree serves both the desktop sidebar and the mobile popover.
const mobileViewport = window.matchMedia('(max-width: 850px)');

function updateNavigation() {
  const sidebar = document.getElementById('app-sidebar');
  if (!sidebar) return;

  if (mobileViewport.matches) {
    sidebar.setAttribute('popover', 'auto');
  } else {
    if (sidebar.matches(':popover-open')) sidebar.hidePopover();
    sidebar.removeAttribute('popover');
    updateToggle(false);
  }
}

function updateToggle(open) {
  const button = document.querySelector('.mobile-nav-toggle');
  if (!button) return;
  button.setAttribute('aria-expanded', String(open));
  button.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
}

function closeNavigation() {
  const sidebar = document.getElementById('app-sidebar');
  if (sidebar?.matches(':popover-open')) sidebar.hidePopover();
}

document.addEventListener('toggle', (event) => {
  if (event.target.id === 'app-sidebar') updateToggle(event.newState === 'open');
}, true);

document.addEventListener('click', (event) => {
  if (event.target.closest('#app-sidebar a[href], #app-sidebar [data-mobile-nav-close]')) closeNavigation();
});

mobileViewport.addEventListener('change', updateNavigation);
document.addEventListener('livewire:navigating', closeNavigation);
document.addEventListener('livewire:navigated', updateNavigation);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', updateNavigation, { once: true });
} else {
  updateNavigation();
}
