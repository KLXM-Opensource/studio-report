// Verwaltung → Report: Drucken (höchstens zwei DIN-A4-Seiten), PDF herunterladen (Core\Pdf) und eigener Zeitraum
document.querySelector('[data-report-print]')?.addEventListener('click', () => { window.cmsCharts?.print(); setTimeout(() => window.print(), 50); });
const sel = document.getElementById('rp-sel');
sel?.addEventListener('change', () => { const d = document.querySelector('[data-rp-dates]'); if (d) d.hidden = sel.value !== 'frei'; });

// PDF: Diagramme als helle PNG-Bilder (cmsCharts.snapshot) mitschicken, Zeitraum und Filter aus der Adresse; der Server setzt das PDF
const pdfBtn = document.querySelector('[data-report-pdf]');
pdfBtn?.addEventListener('click', async () => {
  pdfBtn.classList.add('rp-busy');
  try {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = pdfBtn.dataset.reportPdf;
    form.hidden = true;
    const add = (name, value) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = value; form.append(i); };
    add('_csrf', pdfBtn.dataset.csrf || '');
    for (const [k, v] of new URLSearchParams(location.search)) add(k, v);
    for (const sec of document.querySelectorAll('[data-report-doc] [data-section]')) {
      const fig = sec.querySelector('[data-chart]');
      if (!fig || !window.cmsCharts?.snapshot) continue;
      const png = await window.cmsCharts.snapshot(fig, { light: true });
      if (png) add('chart[' + sec.dataset.section + ']', png);
    }
    document.body.append(form);
    form.submit();
    setTimeout(() => form.remove(), 1000);
  } finally {
    setTimeout(() => pdfBtn.classList.remove('rp-busy'), 1500);
  }
});
