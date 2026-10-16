<?php
/**
 * Verwaltung → Report → Bericht: gedeuteter Bericht für einen Zeitraum im Stil eines Magazins (Kopf mit Ausgabe, Schlagzeile, Vorspann,
 * große Kennzahlen, hervorgehobener Satz, Abschnitte in zwei Spalten, Tipps, „Die Zahlen dazu“). Ausdruck höchstens zwei DIN-A4-Seiten; als PDF
 * über Core\Pdf (views/pdf/report.php), sobald eine PDF-Engine installiert ist.
 * @var string $sel  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter  @var array $cur
 * @var array $report  (Narrative::build)  @var array $options (Dimensionen der letzten 30 Tage für die Filterauswahl)
 */
use Core\Charts;
use Klxm\Report\Geo;
use Klxm\Report\Narrative;
use Klxm\Report\Numbers;

$base = '/admin/report';
include __DIR__ . '/_bar.php';
$geoUsed = !empty(array_filter(array_keys($cur['dims']['country'] ?? []), fn($k) => $k !== ''));
$sections = $report['sections'];
$course = null;
foreach ($sections as $i => $s) if ($s['id'] === 'verlauf') { $course = $s; unset($sections[$i]); }
$sections = array_values($sections);
$chg = function (?float $p, ?bool $good): string {
    if ($p === null) return '';
    $a = abs($p);
    $cls = $a < 5 ? 'is-flat' : ($good ? 'is-up' : 'is-down');
    return '<span class="rp-fig__chg ' . $cls . '">' . e(($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') . '</span>';
};
?>
<article class="rp-doc rp-mag" aria-labelledby="rp-h" data-report-doc>
  <header class="rp-mast">
    <p class="rp-mast__brand"><span class="rp-mast__name"><?= e(__('Report')) ?></span> <span class="rp-mast__site"><?= e(\Klxm\Report\Report::siteName()) ?></span></p>
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

  <?php foreach (array_filter([array_slice($sections, 0, 2), array_slice($sections, 2)]) as $gi => $part): ?>
  <div class="rp-grid<?= $gi ? ' rp-grid--rest' : '' ?>">
    <?php foreach ($part as $s): ?>
    <section class="rp-sec rp-sec--<?= e($s['id']) ?>" aria-labelledby="rp-s-<?= e($s['id']) ?>" data-section="<?= e($s['id']) ?>">
      <h3 id="rp-s-<?= e($s['id']) ?>"><?= e($s['title']) ?></h3>
      <p><?php foreach ($s['text'] as $t): ?><?= e($t) ?> <?php endforeach; ?></p>
      <?php if (!empty($s['chart'])): ?><?= Charts::render($s['chart'], ['caption' => false, 'class' => 'rp-chart']) ?><?php endif; ?>
      <?php if (!empty($s['list'])): ?><ol class="rp-list"><?php foreach ($s['list'] as [$l, $n]): ?><li><span><?= e($l) ?></span><b><?= e($n) ?></b></li><?php endforeach; ?></ol><?php endif; ?>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <?php if ($report['tips']): ?>
  <aside class="rp-tips" aria-labelledby="rp-tips-h"><h3 id="rp-tips-h"><?= e(__('Was Sie tun können')) ?></h3>
    <ol><?php foreach ($report['tips'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ol></aside>
  <?php endif; ?>

  <?php $appendix = Numbers::appendix($cur, $prev); $hasPrev = ($prev['dims']['total'][''][0] ?? 0) > 0; if ($appendix): ?>
  <section class="rp-appendix" aria-labelledby="rp-app-h">
    <h3 id="rp-app-h" class="rp-appendix__h"><?= e(__('Die Zahlen dazu')) ?></h3>
    <div class="rp-appendix__grid">
      <?php foreach ($appendix as $t): ?>
      <table class="rp-num">
        <thead><tr><th scope="col"><?= e($t['title']) ?></th><th scope="col"><?= e($t['col']) ?></th><th scope="col"><?= e(__('Anteil')) ?></th><?php if ($hasPrev): ?><th scope="col"><span class="adm-sr"><?= e(__('Veränderung')) ?></span>±</th><?php endif; ?></tr></thead>
        <tbody><?php foreach ($t['rows'] as $r): ?><tr><td><?= e($r['label']) ?></td><td><?= e(fmt()->number($r['a'])) ?></td><td><?= e(fmt()->number($r['share'], $r['share'] < 10 ? 1 : 0)) ?> %</td>
          <?php if ($hasPrev): ?><td><?= $r['change'] === null ? '<span class="rp-fig__chg is-flat">' . e(__('neu')) . '</span>' : $chg($r['change'], $r['change'] > 0) ?></td><?php endif; ?></tr><?php endforeach; ?></tbody>
      </table>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <footer class="rp-foot">
    <span><?= e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.')) ?></span>
    <?php if ($geoUsed): ?><span><?= e(__('Standorte ungefähr.')) ?> <a href="<?= e(Geo::SOURCE) ?>" rel="noopener" target="_blank">IP Geolocation by DB-IP</a></span><?php endif; ?>
    <span><?= e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) ?></span>
  </footer>
</article>
