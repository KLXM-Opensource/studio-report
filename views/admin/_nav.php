<?php
/** Kopf und Bereichsliste „Report“ (Core\AdminUi). @var string $tab  @var array $flash */
echo \Core\AdminUi::head(__('Report'), site()->label(), __('Besucher ohne Cookies gezählt – als Bericht in Worten, eine Seite je Zeitraum.'),
    ($tab === 'report' ? '<button type="button" class="adm-btn adm-btn--ghost" data-report-print>' . icon('printer') . ' ' . e(__('Drucken / als PDF')) . '</button>' : ''));
$items = ['report' => ['href' => url('/admin/report'), 'label' => __('Bericht'), 'icon' => 'chart-line-up']];
if (can('report.manage')) $items['settings'] = ['href' => url('/admin/report/einstellungen'), 'label' => __('Einstellungen'), 'icon' => 'gear-six',
    'warn' => \Klxm\Report\Report::config()['geo'] !== 'off' && !\Klxm\Report\Geo::available() ? __('GeoIP-Datenbank fehlt') : ''];
echo \Core\AdminUi::open($items, $tab === 'settings' ? 'settings' : 'report', __('Report'), false);
foreach ($flash as [$type, $msg]): ?>
<p class="adm-flash adm-flash--<?= e($type) ?>" role="status"><?= e($msg) ?></p>
<?php endforeach;
