<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

use Core\Charts;

/** Übersicht (Karte, Kachel) und Datenquellen für den Block „Diagramm“ – nur Summen */
final class Card
{
    /** Besucher der letzten 7 Tage und der 7 davor */
    public static function week(): array
    {
        try {
            [$f, $t, $pf, $pt] = Query::range('7');
            return [Query::totals(Query::period($f, $t))['uv'], Query::totals(Query::period($pf, $pt))['uv']];
        } catch (\Throwable) {
            return [0, 0];
        }
    }

    public static function render(): string
    {
        Query::rollup();
        [$f, $t, $pf, $pt] = Query::range('30');
        $cur = Query::period($f, $t);
        $rep = (new Narrative($cur, Query::period($pf, $pt)))->build();
        if ($rep['empty']) return '<p class="adm-muted">' . e(__('In den letzten 30 Tagen wurden noch keine Besuche gezählt.')) . '</p>'
            . '<p class="dash-links"><a href="' . e(url('/admin/report')) . '">' . e(__('Zum Report')) . '</a></p>';
        $h = '<p>' . e(implode(' ', array_slice($rep['lead'], 0, 2))) . '</p>';
        $h .= Charts::render(self::chartVisitors(30) + ['height' => 160], ['caption' => false, 'class' => 'dash-chart']);
        return $h . '<p class="dash-links"><a href="' . e(url('/admin/report')) . '">' . e(__('Ganzen Report ansehen')) . '</a></p>';
    }

    public static function chartVisitors(int $days): array
    {
        $p = Query::period(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')), date('Y-m-d'));
        return ['type' => 'area', 'labels' => array_map(fn($d) => date('d.m.', strtotime($d)), array_keys($p['days'])),
            'series' => [['name' => Report::t('Besucher'), 'data' => array_column(array_values($p['days']), 'uv')]], 'description' => Report::t('Besucher je Tag')];
    }

    public static function chartDim(string $dim, int $days): array
    {
        $p = Query::period(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')), date('Y-m-d'));
        $rows = array_slice(array_filter($p['dims'][$dim] ?? [], fn($k) => $k !== '', ARRAY_FILTER_USE_KEY), 0, 8, true);
        return ['type' => 'donut', 'labels' => array_map(fn($k) => Report::label($dim, (string) $k), array_keys($rows)),
            'series' => [['name' => Report::t('Besuche'), 'data' => array_column(array_values($rows), 0)]]];
    }
}
