<?php
/** Kopf und Bereichsliste „Report“ (Core\AdminUi). @var string $tab  @var array $flash */
$actions = '';
if ($tab === 'report') {
    $actions = '<button type="button" class="adm-btn adm-btn--ghost" data-report-print>' . icon('printer') . ' ' . e(__('Drucken')) . '</button>'
        . (\Core\Pdf::available() ? ' <button type="button" class="adm-btn" data-report-pdf="' . e(url('/admin/report/pdf')) . '" data-csrf="' . e(\Core\Csrf::token()) . '">' . icon('file-pdf') . ' ' . e(__('PDF herunterladen')) . '</button>' : '');
}
echo \Core\AdminUi::head(__('Report'), site()->label(), __('Besucher ohne Cookies gezählt – als Bericht in Worten und als Zahlen zum Durchklicken.'), $actions);
$items = ['report' => ['href' => url('/admin/report'), 'label' => __('Bericht'), 'icon' => 'newspaper'],
    'zahlen' => ['href' => url('/admin/report/zahlen'), 'label' => __('Zahlen'), 'icon' => 'chart-line-up']];
if (can('report.manage')) $items['settings'] = ['href' => url('/admin/report/einstellungen'), 'label' => __('Einstellungen'), 'icon' => 'gear-six',
    'warn' => \Klxm\Report\Report::config()['geo'] !== 'off' && !\Klxm\Report\Geo::available() ? __('GeoIP-Datenbank fehlt') : ''];
echo \Core\AdminUi::open($items, isset($items[$tab]) ? $tab : 'report', __('Report'), false);
foreach ($flash as [$type, $msg]): ?>
<p class="adm-flash adm-flash--<?= e($type) ?>" role="status"><?= e($msg) ?></p>
<?php endforeach;
