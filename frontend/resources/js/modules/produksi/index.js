import { downloadExcel, downloadPNG } from './export-report';

window.downloadWIPExcel = downloadExcel;
window.downloadWIPPNG = downloadPNG;

document.addEventListener('livewire:init', () => {
  Livewire.on('export-excel', async (event) => {
    try {
      const payload = Array.isArray(event) ? event[0] : event;
      if (payload.format === 'monthly-output') {
        const { downloadMonthlyOutputExcel } = await import('./monthly-output-excel');
        downloadMonthlyOutputExcel(payload);
        return;
      }
      downloadExcel(payload.filename, payload.sheetName, payload.headers, payload.rows);
    } catch (err) {
      console.error(err);
    }
  });

  Livewire.on('export-png', (event) => {
    try {
      const payload = Array.isArray(event) ? event[0] : event;
      downloadPNG(payload.filename, payload.title, payload.period, payload.headers, payload.rows);
    } catch (err) {
      console.error(err);
    }
  });

  Livewire.on('scroll-to-element', (event) => {
    const payload = Array.isArray(event) ? event[0] : event;
    const el = document.getElementById(payload.id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

document.addEventListener('livewire:navigating', () => {
  window.dispatchEvent(new CustomEvent('copper-loading-start', { detail: { text: 'Memuat…' } }));
});

document.addEventListener('livewire:navigated', () => {
  window.dispatchEvent(new CustomEvent('copper-loading-stop'));
});
