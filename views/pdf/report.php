<?php
/**
 * Report als PDF (Core\Pdf, dompdf): gleiche Inhalte wie views/admin/report.php im Magazin-Stil, aber für dompdf gesetzt – CSS 2.1,
 * Spalten als Tabellen, Diagramme als PNG vom Browser (data:-URL, $charts[Abschnitt]). Schrift: Lato aus dem Kern (PdfFont) –
 * Black für Schlagzeile und Rubriken, Light für große Zahlen und den hervorgehobenen Satz.
 * @var array $report  @var array $charts  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter  @var array $cur
 */
use Klxm\Report\Narrative;

$sections = $report['sections'];
$course = null;
foreach ($sections as $i => $s) if ($s['id'] === 'verlauf') { $course = $s; unset($sections[$i]); }
$sections = array_values($sections);
$pairs = array_chunk($sections, 2);
$tipsInGrid = $report['tips'] && $pairs && count(end($pairs)) === 1;   // Tipps füllen die freie Zelle neben dem letzten Abschnitt
$tipsHtml = $report['tips'] ? '<div class="tips"><h3>' . e(__('Was Sie tun können')) . '</h3><ol>' . implode('', array_map(fn($t) => '<li>' . e($t) . '</li>', $report['tips'])) . '</ol></div>' : '';
$chg = function (?float $p, ?bool $good): string {
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
$geo = !empty(array_filter(array_keys($cur['dims']['country'] ?? []), fn($k) => $k !== ''));
$footHtml = '<div class="foot">' . e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.'))
    . ($geo ? ' ' . e(__('Standorte ungefähr.')) . ' IP Geolocation by DB-IP (db-ip.com).' : '') . ' ' . e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) . '</div>';
?><!doctype html>
<html lang="<?= e(substr((string) \Core\I18n::locale(), 0, 2) ?: 'de') ?>"><head><meta charset="utf-8"><title><?= e(__('Report')) ?></title>
<style>
@page{margin:11mm 15mm 13mm}
body{font-size:8.7pt;line-height:10.8pt;color:#1d2125}
.mast{border-top:2.5pt solid #1d2125;border-bottom:.6pt solid #cfd3d8;width:100%;border-collapse:collapse;margin-bottom:6pt}
.mast td{padding:3pt 0;vertical-align:baseline;line-height:12pt}
.brand{font-weight:900;letter-spacing:2.4pt;text-transform:uppercase;font-size:8.6pt}
.site{color:#5d6670;font-size:8.6pt;padding-left:6pt}
.issue{text-align:right;color:#5d6670;font-size:8.2pt}
.issue b{color:#1d2125;font-weight:700;letter-spacing:.6pt;text-transform:uppercase;font-size:8.6pt}
h1{font-size:24pt;line-height:25pt;margin:5pt 0 6pt;font-weight:900;letter-spacing:-.4pt}
.stand{font-size:10.6pt;line-height:13.6pt;margin:0 0 7pt;font-weight:300}
.figs{width:100%;border-collapse:separate;border-spacing:10pt 0;margin:0 -10pt 6pt}
.figs td{border-top:.9pt solid #1d2125;padding-top:5pt;vertical-align:top;width:25%}
.label{font-size:6.8pt;letter-spacing:1.1pt;text-transform:uppercase;color:#5d6670;font-weight:700}
.value{font-size:24pt;font-weight:300;line-height:27pt;letter-spacing:-.4pt}
.chg{font-size:8pt;font-weight:bold}.up{color:#1A7240}.down{color:#B42318}.flat{color:#5d6670}
h3{font-size:7.2pt;letter-spacing:1.6pt;text-transform:uppercase;color:#7a2a5c;margin:0 0 4pt;padding-bottom:2pt;border-bottom:.6pt solid #cfd3d8;font-weight:900}
p{margin:0 0 3pt}
.pull{margin:2pt 0 6pt}
.pull td{vertical-align:top}
.pull .q{width:26pt;font-weight:900;font-size:36pt;line-height:30pt;color:#7a2a5c}
.pull .t{font-weight:300;font-style:italic;font-size:13pt;line-height:16.5pt}
.cols{width:100%;border-collapse:separate;border-spacing:14pt 0;margin:0 -14pt}
.cols td{width:50%;vertical-align:top;padding-bottom:4pt}
img.chart{height:76pt;width:auto;margin:1pt 0}
.course img.chart{height:96pt;width:auto}
.course{margin-bottom:4pt}
.list{width:100%;border-collapse:collapse;font-size:8.2pt}
.list td{border-bottom:.5pt solid #e1e4e8;padding:1pt 0}.num{text-align:right;font-weight:bold}
.tips{background:#f6eff4;padding:6pt 9pt;margin-top:2pt}
.tips ol{margin:0;padding-left:14pt}.tips li{margin-bottom:2pt}
.foot{margin-top:4pt;padding-top:4pt;border-top:.6pt solid #cfd3d8;font-size:7pt;color:#5d6670}
.section,.figs,.pull,.tips{page-break-inside:avoid}
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
  <td><div class="label"><?= e($f['label']) ?></div><div class="value"><?= e($f['value']) ?></div><?= $chg($f['pct'], $f['good']) ?></td>
  <?php endforeach; ?>
</tr></table>

<?php if ($course): ?><div class="section course"><?= $section($course) ?></div><?php endif; ?>
<?php if ($report['highlight']): ?><table class="pull"><tr><td class="q">&#8222;</td><td class="t"><?= e($report['highlight']) ?></td></tr></table><?php endif; ?>

<table class="cols">
  <?php foreach ($pairs as $pair): ?>
  <tr><?php foreach ($pair as $s): ?><td class="section"><?= $section($s) ?></td><?php endforeach; ?><?php if (count($pair) === 1): ?><td class="section"><?php if ($tipsInGrid): ?><?= $tipsHtml ?><?= $footHtml ?><?php endif; ?></td><?php endif; ?></tr>
  <?php endforeach; ?>
</table>

<?php if (!$tipsInGrid): ?><?= $tipsHtml ?><?php endif; ?>
<?php endif; ?>

<?php if (!$tipsInGrid): ?><?= $footHtml ?><?php endif; ?>
</body></html>
