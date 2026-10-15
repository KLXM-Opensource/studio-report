// Verwaltung → Report: Drucken (eine DIN-A4-Seite) und eigener Zeitraum
document.querySelector('[data-report-print]')?.addEventListener('click', () => { window.cmsCharts?.print(); setTimeout(() => window.print(), 50); });
const sel = document.getElementById('rp-sel');
sel?.addEventListener('change', () => { const d = document.querySelector('[data-rp-dates]'); if (d) d.hidden = sel.value !== 'frei'; });
