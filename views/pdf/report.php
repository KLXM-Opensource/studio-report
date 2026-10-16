<?php
/**
 * Report als PDF (Core\Pdf, dompdf) – gesetzt wie eine kleine Zeitung, zum Weitergeben. CSS 2.1 (Spalten als Tabellen), Diagramme als PNG
 * vom Browser (data:-URL, $charts[Abschnitt]), Schrift Lato aus dem Kern (PdfFont): Black für Zeitungskopf, Schlagzeile und Rubriken,
 * Light für große Zahlen und Zitat. Höchstens zwei DIN-A4-Seiten:
 *   Seite 1 – Zeitungskopf mit Ausgabe, Schlagzeile, Vorspann, Leitzahl mit Kennzahlen, Verlauf, Zitat, die ersten beiden Abschnitte
 *   Seite 2 – „Im Detail“: übrige Abschnitte, Tipps (dunkler Kasten), „Die Zahlen dazu“, wie gezählt wird
 * @var array $report  @var array $charts  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter
 * @var array $cur  @var array $prev
 */
use Klxm\Report\Narrative;
use Klxm\Report\Numbers;
use Klxm\Report\Report;

$sections = $report['sections'];
$course = null;
foreach ($sections as $i => $s) if ($s['id'] === 'verlauf') { $course = $s; unset($sections[$i]); }
$pairs = array_chunk(array_values($sections), 2);
$first = array_shift($pairs);   // Seite 1
$tipsHtml = $report['tips'] ? '<div class="tips"><div class="tips-h">' . e(__('Was Sie tun können')) . '</div><table class="tips-l">'
    . implode('', array_map(fn($i, $t) => '<tr><td class="tn">' . ($i + 1) . '</td><td>' . e($t) . '</td></tr>', array_keys($report['tips']), $report['tips'])) . '</table></div>' : '';
$tipsInGrid = $tipsHtml !== '' && $pairs && count(end($pairs)) === 1;   // Tipps füllen die freie Zelle neben dem letzten Abschnitt
$pct = function (?float $p, ?bool $good = true): string {
    if ($p === null) return '';
    $a = abs($p);
    $cls = $a < 5 ? 'flat' : ($good ? 'up' : 'down');
    return '<span class="chg ' . $cls . '">' . e(($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') . '</span>';
};
$filterText = [];
foreach ($filter as $k => $v) $filterText[] = match ($k) { 'path' => Narrative::pageName($v), default => Report::label($k, $v) };
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
$row = fn(array $pair, string $fill = '') => '<tr>' . implode('', array_map(fn($s, $i) => '<td class="section c' . ($i + 1) . '">' . $section($s) . '</td>', $pair, array_keys($pair))) . (count($pair) === 1 ? '<td class="section c2">' . $fill . '</td>' : '') . '</tr>';
$appendix = Numbers::appendix($cur, $prev ?? ['dims' => []]);
$hasPrev = (($prev['dims']['total'][''][0] ?? 0) > 0);
$geo = !empty(array_filter(array_keys($cur['dims']['country'] ?? []), fn($k) => $k !== ''));
$name = Report::siteName();
$figs = $report['figures'];
$hero = array_shift($figs);
?><!doctype html>
<html lang="<?= e(substr((string) \Core\I18n::locale(), 0, 2) ?: 'de') ?>"><head><meta charset="utf-8"><title><?= e(__('Report')) ?> – <?= e($report['kicker']) ?></title>
<style>
@page{margin:11mm 15mm 14mm}
body{font-size:9pt;line-height:11.8pt;color:#1d2125}
p{margin:0 0 4pt}
/* Zeitungskopf */
.plate{width:100%;border-collapse:collapse}
.plate td{vertical-align:bottom;padding:0}
.name{font-size:50pt;line-height:46pt;font-weight:900;letter-spacing:-1.6pt;color:#1d2125}
.name span{color:#7a2a5c}
.edition{text-align:right;font-size:8pt;line-height:11pt;color:#5d6670;padding-bottom:4pt}
.edition b{display:block;font-size:15pt;line-height:18pt;font-weight:900;color:#1d2125;letter-spacing:-.2pt}
.rule{border-top:2.6pt solid #1d2125;border-bottom:.7pt solid #1d2125;height:1.6pt;margin:6pt 0 0}
.strip{width:100%;border-collapse:collapse;font-size:7pt;letter-spacing:1.3pt;text-transform:uppercase;color:#5d6670;font-weight:700;border-bottom:.6pt solid #cfd3d8}
.strip td{padding:4pt 0}
.strip .r{text-align:right}
h1{font-size:28pt;line-height:30pt;margin:11pt 0 7pt;font-weight:900;letter-spacing:-.8pt}
.stand{font-size:12.4pt;line-height:16.4pt;margin:0 0 12pt;font-weight:300}
/* Leitzahl und Kennzahlen */
.hero{width:100%;border-collapse:collapse;border-top:1.2pt solid #1d2125;border-bottom:.6pt solid #cfd3d8;margin-bottom:12pt}
.hero td{vertical-align:top;padding:8pt 0}
.hero .big{width:46%;padding-right:14pt;border-right:.6pt solid #cfd3d8}
.hl{font-size:7.4pt;letter-spacing:1.4pt;text-transform:uppercase;color:#5d6670;font-weight:700}
.hv{font-size:64pt;line-height:62pt;font-weight:300;letter-spacing:-2.4pt;margin:2pt 0 4pt}
.pill{background:#7a2a5c;color:#fff;font-weight:900;font-size:9pt;padding:2pt 6pt}
.pill.down{background:#B42318}.pill.flat{background:#5d6670}
.hs{font-size:8pt;color:#5d6670;padding-left:4pt}
.side{padding-left:14pt}
.st{width:100%;border-collapse:collapse}
.st td{padding:4pt 0;border-bottom:.5pt solid #e1e4e8;vertical-align:baseline}
.st tr.last td{border-bottom:0}
.st .v{text-align:right;font-size:19pt;line-height:21pt;font-weight:300;letter-spacing:-.4pt;white-space:nowrap}
.st .c{text-align:right;width:46pt}
.chg{font-size:8.4pt;font-weight:bold}.up{color:#1A7240}.down{color:#B42318}.flat{color:#5d6670}
/* Abschnitte */
h3{font-size:7.6pt;letter-spacing:1.8pt;text-transform:uppercase;color:#7a2a5c;margin:0 0 5pt;padding-bottom:3pt;border-bottom:.6pt solid #cfd3d8;font-weight:900}
.course{margin-bottom:6pt}
img.chart{height:96pt;width:auto;margin:3pt 0 0}
.course img.chart{height:116pt;width:auto}
.pull{width:100%;border-collapse:collapse;margin:4pt 0 12pt;border-top:.6pt solid #1d2125;border-bottom:.6pt solid #1d2125}
.pull td{vertical-align:top;padding:7pt 0}
.pull .q{width:40pt;font-weight:900;font-size:58pt;line-height:46pt;color:#7a2a5c}
.pull .t{font-weight:300;font-style:italic;font-size:16pt;line-height:20.5pt;letter-spacing:-.2pt}
.cols{width:100%;border-collapse:collapse}
.cols td.c1,.cols td.c2{width:50%;vertical-align:top;padding:0 9pt 10pt 0}
.cols td.c2{padding:0 0 10pt 9pt}
.list{width:100%;border-collapse:collapse}
.list td{border-bottom:.5pt solid #e1e4e8;padding:2pt 0}.num{text-align:right;font-weight:bold}
/* Seite 2 */
.page2{page-break-before:always}
.run{width:100%;border-collapse:collapse;border-bottom:1.2pt solid #1d2125;margin-bottom:10pt;font-size:7.4pt;letter-spacing:1.6pt;text-transform:uppercase;color:#5d6670;font-weight:700}
.run td{padding:0 0 4pt}
.run b{color:#1d2125;font-weight:900}
.run .r{text-align:right}
h2{font-size:22pt;line-height:24pt;font-weight:900;margin:4pt 0 10pt;letter-spacing:-.5pt}
.tips{background:#2a1c29;color:#fff;padding:11pt 13pt}
.tips-h{font-size:7.6pt;letter-spacing:1.8pt;text-transform:uppercase;font-weight:900;color:#f2c6e0;margin-bottom:6pt}
.tips-l{width:100%;border-collapse:collapse}
.tips-l td{vertical-align:top;padding:2pt 0 5pt;font-size:9.6pt;line-height:12.6pt}
.tips-l .tn{width:22pt;font-size:18pt;line-height:16pt;font-weight:900;color:#f2c6e0}
.num-t{width:100%;border-collapse:collapse;font-size:8.6pt}
.num-t th{text-align:right;font-size:6.8pt;letter-spacing:1pt;text-transform:uppercase;color:#5d6670;font-weight:700;padding:0 0 3pt 6pt;border-bottom:.8pt solid #1d2125}
.num-t th.l,.num-t td.l{text-align:left;padding-left:0}
.num-t td{text-align:right;padding:2.2pt 0 2.2pt 6pt;border-bottom:.5pt solid #e1e4e8;white-space:nowrap}
.num-t td.l{padding-right:4pt;white-space:normal;width:56%}
.colo{margin-top:6pt;padding-top:5pt;border-top:.6pt solid #cfd3d8;font-size:7.2pt;line-height:9.6pt;color:#5d6670}
.colo b{color:#1d2125}
.section,.hero,.pull,.tips,.num-t{page-break-inside:avoid}
</style></head>
<body>
<table class="plate"><tr>
  <td class="name"><?= e(__('Report')) ?><span>.</span></td>
  <td class="edition"><?= e(__('Ausgabe')) ?><b><?= e($report['kicker']) ?></b><?= e($name) ?></td>
</tr></table>
<div class="rule"></div>
<table class="strip"><tr>
  <td><?= e(__('Besucherbericht')) ?> · <?= e(__('ohne Cookies gezählt')) ?></td>
  <td class="r"><?= e(__('Vergleich mit {range}', ['range' => Narrative::rangeLabel($pfrom, $pto)])) ?><?= $filterText ? ' · ' . e(__('Filter: {list}', ['list' => implode(', ', $filterText)])) : '' ?></td>
</tr></table>

<h1><?= e($report['headline']) ?></h1>
<p class="stand"><?= e(implode(' ', $report['lead'])) ?></p>

<?php if (!$report['empty']): ?>
<?php if ($hero): $hp = $hero['pct']; ?>
<table class="hero"><tr>
  <td class="big"><div class="hl"><?= e($hero['label']) ?></div><div class="hv"><?= e($hero['value']) ?></div>
    <?php if ($hp !== null): $a = abs($hp); ?><span class="pill<?= $a < 5 ? ' flat' : ($hero['good'] ? '' : ' down') ?>"><?= e(($hp >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') ?></span><span class="hs"><?= e(__('gegenüber dem Zeitraum davor')) ?></span><?php endif; ?></td>
  <td class="side"><table class="st">
    <?php foreach ($figs as $i => $f): ?><tr<?= $i === array_key_last($figs) ? ' class="last"' : '' ?>><td class="hl"><?= e($f['label']) ?></td><td class="v"><?= e($f['value']) ?></td><td class="c"><?= $pct($f['pct'], $f['good']) ?></td></tr><?php endforeach; ?>
  </table></td>
</tr></table>
<?php endif; ?>

<?php if ($course): ?><div class="section course"><?= $section($course) ?></div><?php endif; ?>
<?php if ($report['highlight']): ?><table class="pull"><tr><td class="q">&#8222;</td><td class="t"><?= e($report['highlight']) ?></td></tr></table><?php endif; ?>
<?php if ($first): ?><table class="cols"><?= $row($first) ?></table><?php endif; ?>

<?php if ($pairs || $tipsHtml || $appendix): ?>
<div class="page2">
  <table class="run"><tr><td><b><?= e(__('Report')) ?></b> · <?= e($report['kicker']) ?></td><td class="r"><?= e($name) ?></td></tr></table>
  <h2><?= e(__('Im Detail')) ?></h2>
  <?php if ($pairs): $last = count($pairs) - 1; ?>
  <table class="cols"><?php foreach ($pairs as $i => $pair): ?><?= $row($pair, $tipsInGrid && $i === $last ? $tipsHtml : '') ?><?php endforeach; ?></table>
  <?php endif; ?>
  <?php if (!$tipsInGrid && $tipsHtml): ?><?= $tipsHtml ?><?php endif; ?>

  <?php if ($appendix): ?>
  <h2><?= e(__('Die Zahlen dazu')) ?></h2>
  <table class="cols">
    <?php foreach (array_chunk($appendix, 2) as $pair): ?>
    <tr><?php foreach ($pair as $ti => $t): ?><td class="c<?= $ti + 1 ?>">
      <table class="num-t"><tr><th class="l"><?= e($t['title']) ?></th><th><?= e($t['col']) ?></th><th><?= e(__('Anteil')) ?></th><?php if ($hasPrev): ?><th>±</th><?php endif; ?></tr>
        <?php foreach ($t['rows'] as $r): ?><tr><td class="l"><?= e($r['label']) ?></td><td><?= e(fmt()->number($r['a'])) ?></td><td><?= e(fmt()->number($r['share'], $r['share'] < 10 ? 1 : 0)) ?> %</td>
          <?php if ($hasPrev): ?><td><?= $r['change'] === null ? '<span class="chg flat">' . e(__('neu')) . '</span>' : $pct($r['change']) ?></td><?php endif; ?></tr><?php endforeach; ?>
      </table></td><?php endforeach; ?><?php if (count($pair) === 1): ?><td class="c2"></td><?php endif; ?></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="colo"><b><?= e(__('So wird gezählt')) ?>:</b> <?= e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.')) ?>
  <?= $geo ? e(__('Standorte ungefähr.')) . ' IP Geolocation by DB-IP (db-ip.com).' : '' ?> <?= e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) ?> · KLXM Studio</div>
</body></html>
