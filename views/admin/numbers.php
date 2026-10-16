<?php
/**
 * Verwaltung → Report → Zahlen: klassische Statistik zum Durchklicken. Kennzahlen (Klick wählt die Kennzahl des Verlaufs), Verlauf mit
 * Vorzeitraum, Tabellen je Merkmal mit Anteil und Veränderung; Zeilen mit Filter (Seite, Herkunft, Kampagne, Gerät, Land) filtern per Klick,
 * gesetzte Filter lassen sich einzeln entfernen. alle=dim zeigt eine Tabelle vollständig.
 * @var string $sel  @var string $from  @var string $to  @var string $pfrom  @var string $pto  @var array $filter  @var array $cur  @var array $prev
 * @var string $metric  @var string $all  @var array $options
 */
use Core\Charts;
use Klxm\Report\Narrative;
use Klxm\Report\Numbers;
use Klxm\Report\Query;

$base = '/admin/report/zahlen';
$keep = $metric !== 'uv' ? ['kennzahl' => $metric] : [];
include __DIR__ . '/_bar.php';
$state = ['zeitraum' => $sel] + ($sel === 'frei' ? ['von' => $from, 'bis' => $to] : []) + $filter + $keep;
$link = fn(array $set = [], array $drop = []) => url($base) . '?' . http_build_query(array_diff_key(array_merge($state, $set), array_flip($drop)));
$t = Query::totals($cur); $pt = Query::totals($prev);
$badge = function (?float $p, bool $lowerIsBetter = false): string {
    if ($p === null) return '';
    $a = abs($p);
    $cls = $a < 5 ? 'is-flat' : (($lowerIsBetter ? $p < 0 : $p > 0) ? 'is-up' : 'is-down');
    return '<span class="rz-d ' . $cls . '">' . e(($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %') . '</span>';
};
$chg = fn(int|float|null $now, int|float|null $before, bool $lower = false): string => $now === null || !$before ? '' : $badge(($now - $before) / $before * 100, $lower);
$hasPrev = $pt['pv'] > 0;
$labels = ['path' => __('Seite'), 'channel' => __('Herkunft'), 'campaign' => __('Kampagne'), 'device' => __('Gerät'), 'country' => __('Land')];
$dims = $all !== '' ? [$all] : Numbers::DIMS;
?>
<?php if ($filter): ?>
<p class="rz-chips" aria-label="<?= e(__('Gesetzte Filter')) ?>">
  <?php foreach ($filter as $k => $v): ?>
  <a href="<?= e($link([], [$k])) ?>" title="<?= e(__('Filter entfernen')) ?>"><?= e($labels[$k] . ': ' . match ($k) { 'path' => Narrative::pageName($v), 'campaign' => $v, default => \Klxm\Report\Report::label($k, $v) }) ?> <?= icon('x') ?></a>
  <?php endforeach; ?>
</p>
<?php endif; ?>

<ul class="rz-kpis" role="list" aria-label="<?= e(__('Kennzahlen – Auswahl zeigt den Verlauf')) ?>">
  <?php foreach (Numbers::METRICS as $m): ?>
  <li class="rz-kpi"><a href="<?= e($link(['kennzahl' => $m])) ?>"<?= $metric === $m ? ' aria-current="true"' : '' ?>>
    <span class="rz-kpi__label"><?= e(Numbers::metricLabel($m)) ?></span>
    <strong class="rz-kpi__value"><?= e(Numbers::format($m, $t[$m])) ?></strong>
    <?= $chg($t[$m], $pt[$m] ?? null, in_array($m, ['bounce'], true)) ?></a></li>
  <?php endforeach; ?>
</ul>

<section class="adm-card rz-main" aria-labelledby="rz-main-h">
  <h2 id="rz-main-h"><?= e(Numbers::metricLabel($metric)) ?> <small class="adm-muted"><?= e(Narrative::rangeLabel($cur['from'], $to)) ?> · <?= e(__('Vergleich mit {range}', ['range' => Narrative::rangeLabel($pfrom, $pto)])) ?></small></h2>
  <?= Charts::render(Numbers::chart($cur, $prev, $metric), ['caption' => false]) ?>
</section>

<?php if ($all !== ''): ?><p><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($link([], ['alle'])) ?>"><?= icon('arrow-left') ?> <?= e(__('Alle Tabellen')) ?></a></p><?php endif; ?>
<div class="rz-cards">
  <?php foreach ($dims as $dim):
      $res = Numbers::rows($cur, $prev, $dim, $all !== '' ? 500 : 10);
      if (!$res['rows'] && $all === '' && in_array($dim, ['ref', 'campaign', 'region', 'city', 'eprop', 'lang'], true)) continue;
      [$colA, $colB] = Numbers::columns($dim); ?>
  <section class="adm-card adm-card--flush rz-card<?= in_array($dim, ['page', 'entry', 'region', 'city', 'event', 'eprop'], true) || $all !== '' ? ' rz-card--wide' : '' ?>" aria-labelledby="rz-<?= e($dim) ?>">
    <h2 id="rz-<?= e($dim) ?>"><?= e(Numbers::title($dim)) ?>
      <?php if ($all === '' && $res['total'] > count($res['rows'])): ?><a href="<?= e($link(['alle' => $dim])) ?>"><?= e(__('Alle {n} anzeigen', ['n' => fmt()->number($res['total'])])) ?></a><?php endif; ?></h2>
    <?php if (!$res['rows']): ?><p class="rz-empty"><?= e(__('Keine Daten in diesem Zeitraum.')) ?></p><?php else: ?>
    <div class="rz-scroll"><table class="rz-table">
      <thead><tr><th scope="col"><?= e(Numbers::title($dim)) ?></th><th scope="col"><?= e($colA) ?></th><?php if ($colB): ?><th scope="col"><?= e($colB) ?></th><?php endif; ?><th scope="col"><?= e(__('Anteil')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Veränderung')) ?></span>±</th></tr></thead>
      <tbody>
      <?php foreach ($res['rows'] as $row): $f = $row['filter']; $fk = Numbers::FILTER[$dim] ?? ''; ?>
        <tr><td><span class="rz-bar" style="--w:<?= e(number_format(min(100, $row['share']), 1, '.', '')) ?>%"></span>
          <?php $path = in_array($dim, ['page', 'entry'], true) && $row['label'] !== $row['key'] ? ' (' . $row['key'] . ')' : ''; ?>
          <?php if ($f !== null && ($filter[$fk] ?? null) !== $f): ?><a href="<?= e($link([$fk => $f], ['alle'])) ?>" title="<?= e(__('Nach „{v}“ filtern', ['v' => $row['label']]) . $path) ?>"><?= e($row['label']) ?></a>
          <?php else: ?><span<?= $path !== '' ? ' title="' . e(trim($path, ' ()')) . '"' : '' ?>><?= e($row['label']) ?></span><?php endif; ?></td>
          <td><?= e(fmt()->number($row['a'])) ?></td><?php if ($colB): ?><td><?= e(fmt()->number($row['b'])) ?></td><?php endif; ?>
          <td><?= e(fmt()->number($row['share'], $row['share'] < 10 ? 1 : 0)) ?> %</td>
          <td><?= $row['change'] === null ? ($hasPrev ? '<span class="rz-d is-flat">' . e(__('neu')) . '</span>' : '') : $badge($row['change']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>
