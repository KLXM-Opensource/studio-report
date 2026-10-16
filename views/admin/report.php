<?php
/**
 * Verwaltung → Report → Bericht: gedeuteter Bericht für einen Zeitraum, gesetzt wie eine kleine Zeitung – derselbe Aufbau wie das PDF
 * (views/pdf/report.php): Zeitungskopf mit Ausgabe, Schlagzeile, Vorspann, Leitzahl mit Kennzahlen, Verlauf, Zitat, die ersten beiden
 * Abschnitte; Teil 2 „Im Detail“ mit den übrigen Abschnitten, Tipps (dunkler Kasten), „Die Zahlen dazu“ und „So wird gezählt“.
 * Ausdruck höchstens zwei DIN-A4-Seiten (Umbruch vor Teil 2).
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
$first = array_slice($sections, 0, 2);
$rest = array_slice($sections, 2);
$tipsInGrid = $report['tips'] && $rest && count($rest) % 2 === 1;   // Tipps füllen die freie Zelle neben dem letzten Abschnitt
$figs = $report['figures'];
$hero = array_shift($figs);
$name = \Klxm\Report\Report::siteName();
$appendix = $report['empty'] ? [] : Numbers::appendix($cur, $prev);
$hasPrev = ($prev['dims']['total'][''][0] ?? 0) > 0;
$sec = function (array $s): void { ?>
    <section class="rp-sec rp-sec--<?= e($s['id']) ?>" aria-labelledby="rp-s-<?= e($s['id']) ?>" data-section="<?= e($s['id']) ?>">
      <h3 id="rp-s-<?= e($s['id']) ?>"><?= e($s['title']) ?></h3>
      <p><?php foreach ($s['text'] as $t): ?><?= e($t) ?> <?php endforeach; ?></p>
      <?php if (!empty($s['chart'])): ?><?= \Core\Charts::render($s['chart'], ['caption' => false, 'class' => 'rp-chart']) ?><?php endif; ?>
      <?php if (!empty($s['list'])): ?><ol class="rp-list"><?php foreach ($s['list'] as [$l, $n]): ?><li><span><?= e($l) ?></span><b><?= e($n) ?></b></li><?php endforeach; ?></ol><?php endif; ?>
    </section>
<?php };
$tips = function () use ($report): void { ?>
    <aside class="rp-tips" aria-labelledby="rp-tips-h"><p class="rp-tips__h" id="rp-tips-h"><?= e(__('Was Sie tun können')) ?></p>
      <ol><?php foreach ($report['tips'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ol></aside>
<?php };
$chg = function (?float $p, ?bool $good): string {
    if ($p === null) return '';
    $a = abs($p);
    $cls = $a < 5 ? 'is-flat' : ($good ? 'is-up' : 'is-down');
    return '<span class="rp-fig__chg ' . $cls . '">' . e(($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') . '</span>';
};
?>
<article class="rp-doc rp-paper" aria-labelledby="rp-h" data-report-doc>
  <header class="rp-plate">
    <p class="rp-plate__name"><?= e(__('Report')) ?><span>.</span></p>
    <p class="rp-plate__edition"><?= e(__('Ausgabe')) ?><b><?= e($report['kicker']) ?></b><?= e($name) ?></p>
  </header>
  <p class="rp-strip"><span><?= e(__('Besucherbericht')) ?> · <?= e(__('ohne Cookies gezählt')) ?></span>
    <span><?= e(__('Vergleich mit {range}', ['range' => Narrative::rangeLabel($pfrom, $pto)])) ?><?php foreach ($chips as $c): ?> <span class="rp-chip"><?= e($c) ?></span><?php endforeach; ?></span></p>

  <h2 id="rp-h" class="rp-headline"><?= e(str_replace(' – ', "\u{00A0}– ", $report['headline'])) ?></h2>
  <p class="rp-stand"><?php foreach ($report['lead'] as $s): ?><?= e($s) ?> <?php endforeach; ?></p>

  <?php if (!$report['empty']): ?>
  <?php if ($hero): $hp = $hero['pct']; ?>
  <div class="rp-hero">
    <div class="rp-hero__big"><span class="rp-hero__label"><?= e($hero['label']) ?></span><strong class="rp-hero__value"><?= e($hero['value']) ?></strong>
      <?php if ($hp !== null): $a = abs($hp); ?><span class="rp-pill<?= $a < 5 ? ' is-flat' : ($hero['good'] ? '' : ' is-down') ?>"><?= e(($hp >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') ?></span> <span class="rp-hero__note"><?= e(__('gegenüber dem Zeitraum davor')) ?></span><?php endif; ?></div>
    <dl class="rp-hero__side">
      <?php foreach ($figs as $f): ?><div><dt><?= e($f['label']) ?></dt><dd><span class="rp-hero__v"><?= e($f['value']) ?></span><?= $chg($f['pct'], $f['good']) ?></dd></div><?php endforeach; ?>
    </dl>
  </div>
  <?php endif; ?>

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

  <?php if ($first): ?><div class="rp-grid"><?php foreach ($first as $s) $sec($s); ?></div><?php endif; ?>

  <?php if ($rest || $report['tips'] || $appendix): ?>
  <div class="rp-part2">
    <p class="rp-run"><span><b><?= e(__('Report')) ?></b> · <?= e($report['kicker']) ?></span><span><?= e($name) ?></span></p>
    <h3 class="rp-part2__h"><?= e(__('Im Detail')) ?></h3>
    <?php if ($rest): ?><div class="rp-grid"><?php foreach ($rest as $s) $sec($s); ?><?php if ($tipsInGrid) $tips(); ?></div><?php endif; ?>
    <?php if ($report['tips'] && !$tipsInGrid) $tips(); ?>

    <?php if ($appendix): ?>
    <section class="rp-appendix" aria-labelledby="rp-app-h">
      <h3 id="rp-app-h" class="rp-part2__h"><?= e(__('Die Zahlen dazu')) ?></h3>
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
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <footer class="rp-foot">
    <span><b><?= e(__('So wird gezählt')) ?>:</b> <?= e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.')) ?></span>
    <?php if ($geoUsed): ?><span><?= e(__('Standorte ungefähr.')) ?> <a href="<?= e(Geo::SOURCE) ?>" rel="noopener" target="_blank">IP Geolocation by DB-IP</a></span><?php endif; ?>
    <span><?= e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) ?></span>
  </footer>
</article>
