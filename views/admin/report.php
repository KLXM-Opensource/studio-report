<?php
/**
 * Verwaltung → Report → Bericht: gedeuteter Bericht für einen Zeitraum im Stil eines Magazins (Kopf mit Ausgabe, Schlagzeile, Vorspann,
 * große Kennzahlen, hervorgehobener Satz, Abschnitte in zwei Spalten, Tipps). Bildschirm wie Ausdruck: eine DIN-A4-Seite; als PDF
 * über Core\Pdf (views/pdf/report.php), sobald eine PDF-Engine installiert ist.
 * @var string $sel  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter  @var array $cur
 * @var array $report  (Narrative::build)  @var array $options (Dimensionen der letzten 30 Tage für die Filterauswahl)
 */
use Core\Charts;
use Klxm\Report\Geo;
use Klxm\Report\Narrative;

$base = '/admin/report';
include __DIR__ . '/_bar.php';
$geoUsed = !empty(array_filter(array_keys($cur['dims']['country'] ?? []), fn($k) => $k !== ''));
$sections = $report['sections'];
$course = null;
foreach ($sections as $i => $s) if ($s['id'] === 'verlauf') { $course = $s; unset($sections[$i]); }
$chg = function (?float $p, ?bool $good): string {
    if ($p === null) return '';
    $a = abs($p);
    $cls = $a < 5 ? 'is-flat' : ($good ? 'is-up' : 'is-down');
    return '<span class="rp-fig__chg ' . $cls . '">' . e(($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') . '</span>';
};
?>
<article class="rp-doc rp-mag" aria-labelledby="rp-h" data-report-doc>
  <header class="rp-mast">
    <p class="rp-mast__brand"><span class="rp-mast__name"><?= e(__('Report')) ?></span> <span class="rp-mast__site"><?= e(site()->label()) ?></span></p>
    <p class="rp-mast__issue"><b><?= e($report['kicker']) ?></b>
      <span><?= e(__('Vergleich mit {range}', ['range' => Narrative::rangeLabel($pfrom, $pto)])) ?></span>
      <?php foreach ($chips as $c): ?><span class="rp-chip"><?= e($c) ?></span><?php endforeach; ?></p>
  </header>

  <h2 id="rp-h" class="rp-headline"><?= e(str_replace(' – ', "\u{00A0}– ", $report['headline'])) ?></h2>
  <p class="rp-stand"><?php foreach ($report['lead'] as $s): ?><?= e($s) ?> <?php endforeach; ?></p>

  <?php if (!$report['empty']): ?>
  <ul class="rp-figs" role="list">
    <?php foreach ($report['figures'] as $f): ?>
    <li class="rp-fig"><span class="rp-fig__label"><?= e($f['label']) ?></span><strong class="rp-fig__value"><?= e($f['value']) ?></strong><?= $chg($f['pct'], $f['good']) ?></li>
    <?php endforeach; ?>
  </ul>

  <?php if ($course): ?>
  <section class="rp-sec rp-sec--verlauf" aria-labelledby="rp-s-verlauf" data-section="verlauf">
    <h3 id="rp-s-verlauf"><?= e($course['title']) ?></h3>
    <p><?php foreach ($course['text'] as $t): ?><?= e($t) ?> <?php endforeach; ?></p>
    <?= Charts::render($course['chart'], ['caption' => false, 'class' => 'rp-chart']) ?>
  </section>
  <?php endif; ?>

  <?php if ($report['highlight']): ?>
  <blockquote class="rp-pull"><p><?= e($report['highlight']) ?></p></blockquote>
  <?php endif; ?>

  <div class="rp-grid">
    <?php foreach ($sections as $s): ?>
    <section class="rp-sec rp-sec--<?= e($s['id']) ?>" aria-labelledby="rp-s-<?= e($s['id']) ?>" data-section="<?= e($s['id']) ?>">
      <h3 id="rp-s-<?= e($s['id']) ?>"><?= e($s['title']) ?></h3>
      <p><?php foreach ($s['text'] as $t): ?><?= e($t) ?> <?php endforeach; ?></p>
      <?php if (!empty($s['chart'])): ?><?= Charts::render($s['chart'], ['caption' => false, 'class' => 'rp-chart']) ?><?php endif; ?>
      <?php if (!empty($s['list'])): ?><ol class="rp-list"><?php foreach ($s['list'] as [$l, $n]): ?><li><span><?= e($l) ?></span><b><?= e($n) ?></b></li><?php endforeach; ?></ol><?php endif; ?>
    </section>
    <?php endforeach; ?>
  </div>

  <?php if ($report['tips']): ?>
  <aside class="rp-tips" aria-labelledby="rp-tips-h"><h3 id="rp-tips-h"><?= e(__('Was Sie tun können')) ?></h3>
    <ol><?php foreach ($report['tips'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ol></aside>
  <?php endif; ?>
  <?php endif; ?>

  <footer class="rp-foot">
    <span><?= e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.')) ?></span>
    <?php if ($geoUsed): ?><span><?= e(__('Standorte ungefähr.')) ?> <a href="<?= e(Geo::SOURCE) ?>" rel="noopener" target="_blank">IP Geolocation by DB-IP</a></span><?php endif; ?>
    <span><?= e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) ?></span>
  </footer>
</article>
