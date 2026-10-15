<?php
/**
 * Verwaltung → Report: gedeuteter Bericht für einen Zeitraum als Dokument (Bildschirm wie Ausdruck: eine DIN-A4-Seite).
 * @var string $sel  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter  @var array $cur
 * @var array $report  (Narrative::build)  @var array $options (Dimensionen der letzten 30 Tage für die Filterauswahl)
 */
use Core\Charts;
use Klxm\Report\Geo;
use Klxm\Report\Narrative;
use Klxm\Report\Report;

$periods = ['7' => __('Letzte 7 Tage'), '30' => __('Letzte 30 Tage'), 'monat' => __('Dieser Monat'), 'vormonat' => __('Letzter Monat'),
    'quartal' => __('Letzte 90 Tage'), 'jahr' => __('Dieses Jahr'), 'frei' => __('Eigener Zeitraum …')];
$opt = function (string $dim, int $n = 12) use ($options): array {
    $out = [];
    foreach (array_slice($options[$dim] ?? [], 0, $n, true) as $k => $v) {
        $k = (string) $k;
        if ($k === '') continue;
        $key = $dim === 'campaign' ? explode('|', $k)[0] : $k;
        $out[$key] = match ($dim) { 'page' => Narrative::pageName($k), 'campaign' => $key, default => Report::label($dim === 'page' ? 'path' : $dim, $k) };
    }
    return $out;
};
$filters = ['path' => [__('Seite'), $opt('page', 15)], 'channel' => [__('Herkunft'), $opt('channel')], 'campaign' => [__('Kampagne'), $opt('campaign')],
    'device' => [__('Gerät'), $opt('device')], 'country' => [__('Land'), $opt('country')]];
$chips = [];
foreach ($filter as $k => $v) $chips[] = $filters[$k][0] . ': ' . ($filters[$k][1][$v] ?? $v);
$geoUsed = !empty(array_filter(array_keys($cur['dims']['country'] ?? []), fn($k) => $k !== ''));
?>
<form class="rp-bar" method="get" action="<?= e(url('/admin/report')) ?>" aria-label="<?= e(__('Zeitraum und Filter')) ?>">
  <div class="f"><label for="rp-sel"><?= e(__('Zeitraum')) ?></label>
    <select id="rp-sel" name="zeitraum" data-autosubmit><?php foreach ($periods as $k => $l): ?><option value="<?= e((string) $k) ?>"<?= (string) $sel === (string) $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="f rp-bar__dates"<?= $sel === 'frei' ? '' : ' hidden' ?> data-rp-dates>
    <label for="rp-von"><?= e(__('von')) ?></label><input id="rp-von" type="date" name="von" value="<?= e($from) ?>" max="<?= e(date('Y-m-d')) ?>">
    <label for="rp-bis"><?= e(__('bis')) ?></label><input id="rp-bis" type="date" name="bis" value="<?= e($to) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
  <?php foreach ($filters as $k => [$label, $list]): if (!$list && empty($filter[$k])) continue; ?>
  <div class="f"><label for="rp-f-<?= e($k) ?>"><?= e($label) ?></label>
    <select id="rp-f-<?= e($k) ?>" name="<?= e($k) ?>" data-autosubmit><option value=""><?= e(__('Alle')) ?></option>
      <?php foreach ($list as $v => $l): ?><option value="<?= e((string) $v) ?>"<?= ($filter[$k] ?? '') === (string) $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <?php endforeach; ?>
  <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Anzeigen')) ?></button>
  <?php if ($filter): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/report') . '?zeitraum=' . rawurlencode($sel)) ?>"><?= e(__('Filter zurücksetzen')) ?></a><?php endif; ?>
</form>
<?php if ($cur['limited']): ?><p class="adm-flash adm-flash--info" role="status"><?= e(__('Filter gelten nur für die letzten {n} Tage – ältere Zeiträume liegen nur als Tagessummen vor.', ['n' => Report::RAW_DAYS])) ?></p><?php endif; ?>

<article class="rp-doc" aria-labelledby="rp-h">
  <header class="rp-doc__head">
    <p class="rp-kicker"><?= e(__('Report')) ?> · <?= e(site()->label()) ?></p>
    <h2 id="rp-h"><?= e(Narrative::rangeLabel($cur['from'], $to)) ?></h2>
    <p class="rp-sub"><?= e(__('Vergleich mit {range}', ['range' => Narrative::rangeLabel($pfrom, $pto)])) ?><?php foreach ($chips as $c): ?> <span class="rp-chip"><?= e($c) ?></span><?php endforeach; ?></p>
  </header>

  <div class="rp-lead"><?php foreach ($report['lead'] as $s): ?><?= e($s) ?> <?php endforeach; ?></div>

  <?php if (!$report['empty']): ?>
  <ul class="rp-figs" role="list">
    <?php foreach ($report['figures'] as $f): ?>
    <li class="rp-fig"><span class="rp-fig__label"><?= e($f['label']) ?></span><strong class="rp-fig__value"><?= e($f['value']) ?></strong>
      <?php if ($f['pct'] !== null): $a = abs($f['pct']); ?><span class="rp-fig__chg <?= $a < 5 ? 'is-flat' : ($f['good'] ? 'is-up' : 'is-down') ?>"><?= e(($f['pct'] >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>

  <div class="rp-grid">
    <?php foreach ($report['sections'] as $s): ?>
    <section class="rp-sec rp-sec--<?= e($s['id']) ?>" aria-labelledby="rp-s-<?= e($s['id']) ?>">
      <h3 id="rp-s-<?= e($s['id']) ?>"><?= e($s['title']) ?></h3>
      <p><?php foreach ($s['text'] as $t): ?><?= e($t) ?> <?php endforeach; ?></p>
      <?php if (!empty($s['chart'])): ?><?= Charts::render($s['chart'], ['caption' => false]) ?><?php endif; ?>
      <?php if (!empty($s['list'])): ?><ol class="rp-list"><?php foreach ($s['list'] as [$l, $n]): ?><li><span><?= e($l) ?></span><b><?= e($n) ?></b></li><?php endforeach; ?></ol><?php endif; ?>
    </section>
    <?php endforeach; ?>
  </div>

  <?php if ($report['tips']): ?>
  <aside class="rp-tips" aria-labelledby="rp-tips-h"><h3 id="rp-tips-h"><?= e(__('Was Sie tun können')) ?></h3>
    <ul><?php foreach ($report['tips'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul></aside>
  <?php endif; ?>
  <?php endif; ?>

  <footer class="rp-foot">
    <span><?= e(__('Gezählt ohne Cookies und ohne Speicher im Browser; keine IP-Adressen gespeichert. Besucher werden je Tag gezählt.')) ?></span>
    <?php if ($geoUsed): ?><span><?= e(__('Standorte ungefähr.')) ?> <a href="<?= e(Geo::SOURCE) ?>" rel="noopener" target="_blank">IP Geolocation by DB-IP</a></span><?php endif; ?>
    <span><?= e(__('Erstellt am {date}', ['date' => fmt()->date(time()) . ' ' . date('H:i')])) ?></span>
  </footer>
</article>
