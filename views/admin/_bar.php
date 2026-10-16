<?php
/**
 * Zeitraum und Filter (Bericht und Zahlen). @var string $sel  @var string $from  @var string $to  @var array $filter  @var array $cur
 * @var array $options (Dimensionen der letzten 30 Tage)  @var string $base (Adresse der Ansicht)  @var array $keep (weitere Parameter)
 */
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
// Gesetzter Filter, der nicht unter den häufigsten Werten ist (z. B. aus der Zahlen-Ansicht): trotzdem auswählbar halten
foreach ($filter as $k => $v) if (isset($filters[$k]) && !isset($filters[$k][1][$v])) $filters[$k][1][$v] = match ($k) { 'path' => Narrative::pageName($v), 'campaign' => $v, default => Report::label($k, $v) };
$chips = [];
foreach ($filter as $k => $v) $chips[] = $filters[$k][0] . ': ' . ($filters[$k][1][$v] ?? $v);
?>
<form class="rp-bar" method="get" action="<?= e(url($base)) ?>" aria-label="<?= e(__('Zeitraum und Filter')) ?>">
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
  <?php foreach ($keep ?? [] as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
  <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Anzeigen')) ?></button>
  <?php if ($filter): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url($base) . '?' . http_build_query(['zeitraum' => $sel] + ($sel === 'frei' ? ['von' => $from, 'bis' => $to] : []) + ($keep ?? []))) ?>"><?= e(__('Filter zurücksetzen')) ?></a><?php endif; ?>
</form>
<?php if ($cur['limited']): ?><p class="adm-flash adm-flash--info" role="status"><?= e(__('Filter gelten nur für die letzten {n} Tage – ältere Zeiträume liegen nur als Tagessummen vor.', ['n' => Report::RAW_DAYS])) ?></p><?php endif; ?>
