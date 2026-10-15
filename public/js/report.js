/* KLXM Studio – Report: Seitenaufrufe und Aktionen melden – ohne Cookies, ohne Speicher im Browser (kein localStorage).
   Ereignisse ohne Code: data-report="Name" (optional data-report-value) an Links oder Buttons. Im Skript: window.cmsReport('Name', 'Wert').
   Automatisch: Downloads/PDF, Links nach außen, E-Mail- und Telefon-Links. Respektiert „Do Not Track“/GPC (Einstellung). */
(() => {
  const me = document.currentScript, ep = me && me.dataset.endpoint;
  if (!ep || (me.dataset.dnt && (navigator.doNotTrack === '1' || navigator.globalPrivacyControl))) return;
  const send = (o) => {
    const b = JSON.stringify(o);
    try { if (navigator.sendBeacon && navigator.sendBeacon(ep, new Blob([b], { type: 'text/plain' }))) return; } catch (e) {}
    fetch(ep, { method: 'POST', body: b, keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' } }).catch(() => {});
  };
  const path = () => location.pathname;
  send({ t: 'pv', p: path(), q: location.search, r: document.referrer, w: screen.width, l: navigator.language });
  // Sichtbare Zeit auf der Seite (beim Verlassen bzw. Wechsel des Tabs)
  let shown = Date.now(), total = 0;
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { total += Date.now() - shown; send({ t: 'end', p: path(), s: Math.round(total / 1000) }); } else shown = Date.now();
  });
  const ev = (n, d) => send({ t: 'ev', n: String(n).slice(0, 80), d: d == null ? '' : String(d).slice(0, 160), p: path() });
  window.cmsReport = ev;
  document.addEventListener('click', (e) => {
    const el = e.target.closest && e.target.closest('[data-report],a[href]');
    if (!el) return;
    if (el.dataset.report) return ev(el.dataset.report, el.dataset.reportValue || el.textContent.trim().slice(0, 80));
    const href = el.getAttribute('href') || '';
    if (/^mailto:/i.test(href)) return ev('E-Mail-Link');
    if (/^tel:/i.test(href)) return ev('Telefon-Link');
    let u; try { u = new URL(href, location.href); } catch (x) { return; }
    const file = u.pathname.split('/').pop();
    if (/\.(pdf|zip|docx?|xlsx?|pptx?|odt|ods|mp3|mp4)$/i.test(u.pathname) || /\/pdf\/\d+/.test(u.pathname) || el.hasAttribute('download'))
      return ev('Download', decodeURIComponent(el.dataset.pdfTitle || el.getAttribute('title') || file || u.pathname));
    if (u.host && u.host !== location.host && /^https?:$/.test(u.protocol)) return ev('Link nach außen', u.host.replace(/^www\./, ''));
  }, true);
})();
