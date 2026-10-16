<?php
/**
 * Report als PDF (Core\Pdf, dompdf): gleiche Inhalte wie views/admin/report.php im Magazin-Stil, für dompdf gesetzt – CSS 2.1, Spalten als
 * Tabellen, Diagramme als PNG vom Browser (data:-URL, $charts[Abschnitt]). Höchstens zwei DIN-A4-Seiten: Seite 1 Kopf, Schlagzeile,
 * Kennzahlen, Verlauf, Zitat und die ersten beiden Abschnitte; Seite 2 die übrigen Abschnitte, Tipps und „Die Zahlen dazu“.
 * Schrift: Lato aus dem Kern (PdfFont) – Black für Schlagzeile und Rubriken, Light für große Zahlen und das Zitat.
 * @var array $report  @var array $charts  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter
 * @var array $cur  @var array $prev
 */
use Klxm\Report\Narrative;
use Klxm\Report\Numbers;

$sections = $report['sections'];
$course = null;
foreach ($sections as $i => $s) if ($s['id'] === 'verlauf') { $course = $s; unset($sections[$i]); }
$pairs = array_chunk(array_values($sections), 2);
$first = array_shift($pairs);   // Seite 1
$tipsHtml = $report['tips'] ? '<div class="tips"><h3>' . e(__('Was Sie tun können')) . '</h3><ol>' . implode('', array_map(fn($t) => '<li>' . e($t) . '</li>', $report['tips'])) . '</ol></div>' : '';
$tipsInGrid = $tipsHtml !== '' && $pairs && count(end($pairs)) === 1;   // Tipps füllen die freie Zelle neben dem letzten Abschnitt
$pct = function (?float $p, ?bool $good = true): string {
    if ($p === null) return '';
    $a = abs($p);
    $cls = $a < 5 ? 'flat' : ($good ? 'up' : 'down');
    return '<span class="chg ' . $cls . '">' . e(($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') . '</span>';
};
$filterText = [];
foreach ($filter as $k => $v) $filterText[] = match ($k) { 'path' => Narrative::pageName($v), default => \Klxm\Report\Report::label($k, $v) };
$section = function (array $s) use ($charts): string {
    $h = '<h3>' . e($s['title']) . '</h3><p>' . e(implode(' ', $s['text'])) . '</p>';
    if (!empty($charts[$s['id']])) $h .= '<img class="chart" src="' . e($charts[$s['id']]) . '" alt="">';
    if (!empty($s['list'])) {
        $h .= '<table class="list">';
        foreach ($s['list'] as [$l, $n]) $h .= '<tr><td>' . e($l) . '</td><td class="num">' . e($n) . '</td></tr>';
        $h .= '</table>';
    }
    return $h;
};
$row = fn(array $pair, string $fill = '') => '<tr>' . implode('', array_map(fn($s) => '<td class="section">' . $section($s) . '</td>', $pair)) . (count($pair) === 1 ? '<td class="section">' . $fill . '</td>' : '') . '</tr>';
$appendix = Numbers::appendix($cur, $prev ?? ['dims' => []]);
$hasPrev = (($prev['dims']['total'][''][0] ?? 0) > 0);
$geo = !empty(array_filter(array_keys($cur['dims']['country'] ?? []), fn($k) => $k !== ''));
?><!doctype html>
<html lang="<?= e(substr((string) \Core\I18n::locale(), 0, 2) ?: 'de') ?>"><head><meta charset="utf-8"><title><?= e(__('Report')) ?></title>
<style>
@page{margin:12mm 16mm 14mm}
body{font-size:9pt;line-height:11.8pt;color:#1d2125}
.mast{border-top:2.6pt solid #1d2125;border-bottom:.6pt solid #cfd3d8;width:100%;border-collapse:collapse;margin-bottom:8pt}
.mast td{padding:4pt 0;vertical-align:baseline;line-height:12.5pt}
.brand{font-weight:900;letter-spacing:2.6pt;text-transform:uppercase;font-size:9pt}
.site{color:#5d6670;font-size:9pt;padding-left:7pt}
.issue{text-align:right;color:#5d6670;font-size:8.4pt}
.issue b{color:#1d2125;font-weight:700;letter-spacing:.7pt;text-transform:uppercase;font-size:9pt}
h1{font-size:26pt;line-height:28pt;margin:7pt 0 7pt;font-weight:900;letter-spacing:-.6pt}
.stand{font-size:11.6pt;line-height:15.4pt;margin:0 0 10pt;font-weight:300}
.figs{width:100%;border-collapse:separate;border-spacing:12pt 0;margin:0 -12pt 10pt}
.figs td{border-top:1pt solid #1d2125;padding-top:6pt;vertical-align:top;width:25%}
.label{font-size:7.2pt;letter-spacing:1.3pt;text-transform:uppercase;color:#5d6670;font-weight:700}
.value{font-size:28pt;font-weight:300;line-height:32pt;letter-spacing:-.6pt}
.chg{font-size:8.6pt;font-weight:bold}.up{color:#1A7240}.down{color:#B42318}.flat{color:#5d6670}
h3{font-size:7.6pt;letter-spacing:1.8pt;text-transform:uppercase;color:#7a2a5c;margin:0 0 5pt;padding-bottom:3pt;border-bottom:.6pt solid #cfd3d8;font-weight:900}
h2{font-size:15pt;line-height:18pt;font-weight:900;margin:2pt 0 8pt;letter-spacing:-.2pt}
p{margin:0 0 4pt}
.pull{margin:2pt 0 10pt}
.pull td{vertical-align:top}
.pull .q{width:32pt;font-weight:900;font-size:44pt;line-height:36pt;color:#7a2a5c}
.pull .t{font-weight:300;font-style:italic;font-size:14pt;line-height:18pt}
.cols{width:100%;border-collapse:separate;border-spacing:16pt 0;margin:0 -16pt}
.cols td{width:50%;vertical-align:top;padding-bottom:10pt}
img.chart{height:100pt;width:auto;margin:3pt 0 0}
.course{margin-bottom:8pt}
.course img.chart{width:100%;height:auto}
.list{width:100%;border-collapse:collapse}
.list td{border-bottom:.5pt solid #e1e4e8;padding:2pt 0}.num{text-align:right;font-weight:bold}
.tips{background:#f6eff4;padding:9pt 12pt}
.tips ol{margin:0;padding-left:15pt}.tips li{margin-bottom:3pt}
.page2{page-break-before:always}
.mini{font-size:7.6pt;letter-spacing:1.6pt;text-transform:uppercase;color:#5d6670;font-weight:700;border-bottom:.6pt solid #cfd3d8;padding-bottom:4pt;margin-bottom:12pt}
.mini b{color:#1d2125;font-weight:900}
.num-t{width:100%;border-collapse:collapse;font-size:8.6pt}
.num-t th{text-align:right;font-size:6.8pt;letter-spacing:1pt;text-transform:uppercase;color:#5d6670;font-weight:700;padding:0 0 3pt;border-bottom:.6pt solid #1d2125}
.num-t th.l,.num-t td.l{text-align:left}
.num-t td{text-align:right;padding:2.2pt 0 2.2pt 6pt;border-bottom:.5pt solid #e1e4e8;white-space:nowrap}
.num-t td.l{padding:2.2pt 4pt 2.2pt 0;white-space:normal;width:58%}
.foot{margin-top:8pt;padding-top:5pt;border-top:.6pt solid #cfd3d8;font-size:7.4pt;color:#5d6670}
.section,.figs,.pull,.tips,.num-t{page-break-inside:avoid}
</style></head>
<body>
<table class="mast"><tr>
  <td><span class="brand"><?= e(__('Report')) ?></span><span class="site"><?= e(site()->label()) ?></span></td>
  <td class="issue"><b><?= e($report['kicker']) ?></b><br><?= e(__('Vergleich mit {range}', ['range' => Narrative::rangeLabel($pfrom, $pto)])) ?><?= $filterText ? ' · ' . e(__('Filter: {list}', ['list' => implode(', ', $filterText)])) : '' ?></td>
</tr></table>

<h1><?= e($report['headline']) ?></h1>
<p class="stand"><?= e(implode(' ', $report['lead'])) ?></p>

<?php if (!$report['empty']): ?>
<table class="figs"><tr>
  <?php foreach ($report['figures'] as $f): ?>
  <td><div class="label"><?= e($f['label']) ?></div><div class="value"><?= e($f['value']) ?></div><?= $pct($f['pct'], $f['good']) ?></td>
  <?php endforeach; ?>
</tr></table>

<?php if ($course): ?><div class="section course"><?= $section($course) ?></div><?php endif; ?>
<?php if ($report['highlight']): ?><table class="pull"><tr><td class="q">&#8222;</td><td class="t"><?= e($report['highlight']) ?></td></tr></table><?php endif; ?>
<?php if ($first): ?><table class="cols"><?= $row($first) ?></table><?php endif; ?>

<?php if ($pairs || $tipsHtml || $appendix): ?>
<div class="page2">
  <div class="mini"><b><?= e(__('Report')) ?></b> · <?= e($report['kicker']) ?> · <?= e(site()->label()) ?></div>
  <?php if ($pairs): $last = count($pairs) - 1; ?>
  <table class="cols"><?php foreach ($pairs as $i => $pair): ?><?= $row($pair, $tipsInGrid && $i === $last ? $tipsHtml : '') ?><?php endforeach; ?></table>
  <?php endif; ?>
  <?php if (!$tipsInGrid && $tipsHtml): ?><?= $tipsHtml ?><?php endif; ?>

  <?php if ($appendix): ?>
  <h2><?= e(__('Die Zahlen dazu')) ?></h2>
  <table class="cols">
    <?php foreach (array_chunk($appendix, 2) as $pair): ?>
    <tr><?php foreach ($pair as $t): ?><td>
      <table class="num-t"><tr><th class="l"><?= e($t['title']) ?></th><th><?= e($t['col']) ?></th><th><?= e(__('Anteil')) ?></th><?php if ($hasPrev): ?><th>±</th><?php endif; ?></tr>
        <?php foreach ($t['rows'] as $r): ?><tr><td class="l"><?= e($r['label']) ?></td><td><?= e(fmt()->number($r['a'])) ?></td><td><?= e(fmt()->number($r['share'], $r['share'] < 10 ? 1 : 0)) ?> %</td>
          <?php if ($hasPrev): ?><td><?= $r['change'] === null ? '<span class="chg flat">' . e(__('neu')) . '</span>' : $pct($r['change']) ?></td><?php endif; ?></tr><?php endforeach; ?>
      </table></td><?php endforeach; ?><?php if (count($pair) === 1): ?><td></td><?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="foot"><?= e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.')) ?>
  <?= $geo ? e(__('Standorte ungefähr.')) . ' IP Geolocation by DB-IP (db-ip.com).' : '' ?> <?= e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) ?></div>
</body></html>
