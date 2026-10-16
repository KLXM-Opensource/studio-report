<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/**
 * Verwaltung → Report: Bericht (gedeutet, Magazin, eine DIN-A4-Seite, als PDF über Core\Pdf), Zahlen (klassische Statistik zum
 * Durchklicken: Kennzahlen, Verlauf, Tabellen mit Filtern) und Einstellungen.
 */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(Request $r): array
    {
        if (!Report::on()) throw new HttpException(404);
        return $this->auth($r);
    }

    private function page(string $view, array $vars = []): Response
    {
        $vars += ['title' => __('Report'), 'tab' => $view];
        $vars['flash'] = app()->session->takeFlash();
        $vars['user'] = app()->auth->user();
        $dir = dirname(__DIR__) . '/views/admin/';
        $content = '<link rel="stylesheet" href="' . e(Report::asset('css/report-admin.css')) . '">'
            . Theme::capture($dir . '_nav.php', $vars) . Theme::capture($dir . $view . '.php', $vars) . \Core\AdminUi::close()
            . '<script type="module" src="' . e(Report::asset('js/report-admin.js')) . '"></script>';
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'report']);
        return self::secure(new Response($html));
    }

    /** Zeitraum, Vergleich und Filter aus der Anfrage (GET oder – beim PDF – POST) */
    private static function params(Request $r, bool $post = false): array
    {
        $in = fn(string $k, string $d = '') => $post ? trim((string) ($r->post[$k] ?? $d)) : $r->str($k, $d);
        Query::rollup();
        $sel = $in('zeitraum', '30');
        [$f, $t, $pf, $pt] = Query::range($sel, $in('von'), $in('bis'));
        $filter = [];
        foreach (Query::FILTERS as $k) if ($in($k) !== '') $filter[$k] = mb_substr($in($k), 0, 255);
        $cur = Query::period($f, $t, $filter);
        $prev = Query::period($pf, $pt, $filter);
        return ['sel' => $sel, 'from' => $f, 'to' => $t, 'pfrom' => $pf, 'pto' => $pt, 'filter' => $filter, 'cur' => $cur, 'prev' => $prev];
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $p = self::params($r);
        $options = Query::period(Query::rawStart(), date('Y-m-d'));   // Auswahl für Filter
        return $this->page('report', $p + ['report' => (new Narrative($p['cur'], $p['prev']))->build(), 'options' => $options['dims']]);
    }

    /** Zahlen: klassische Statistik – Kennzahl wählen (kennzahl), Tabellen je Merkmal, ganze Liste (alle=dim) */
    public function numbers(Request $r): Response
    {
        $this->guard($r);
        $p = self::params($r);
        $metric = in_array($r->str('kennzahl'), ['uv', 'visits', 'pv', 'bounce', 'dur', 'ppv'], true) ? $r->str('kennzahl') : 'uv';
        $all = in_array($r->str('alle'), Numbers::DIMS, true) ? $r->str('alle') : '';
        $options = Query::period(Query::rawStart(), date('Y-m-d'));
        return $this->page('numbers', $p + ['tab' => 'zahlen', 'title' => __('Report') . ' · ' . __('Zahlen'), 'metric' => $metric, 'all' => $all, 'options' => $options['dims']]);
    }

    /**
     * PDF des Berichts (Core\Pdf): der Browser schickt Zeitraum, Filter und die Diagramme als PNG (cmsCharts.snapshot) – der Server
     * setzt daraus ein Dokument für dompdf (views/pdf/report.php, Tabellen statt Grid) und liefert es zum Herunterladen.
     */
    public function pdf(Request $r): Response
    {
        $this->guard($r);
        if (!\Core\Pdf::available()) throw new HttpException(404);
        $p = self::params($r, true);
        $charts = [];
        foreach ((array) ($r->post['chart'] ?? []) as $id => $png) {
            $png = (string) $png;
            if (!preg_match('~^[a-z]{2,20}$~', (string) $id) || strlen($png) > 3_000_000 || !preg_match('~^data:image/png;base64,[A-Za-z0-9+/=]+$~', $png)) continue;
            $bin = base64_decode(substr($png, 22), true);
            if ($bin === false || !str_starts_with($bin, "\x89PNG")) continue;
            $charts[$id] = $png;
            if (count($charts) >= 8) break;
        }
        $report = (new Narrative($p['cur'], $p['prev']))->build();
        $html = Theme::capture(dirname(__DIR__) . '/views/pdf/report.php', $p + ['report' => $report, 'charts' => $charts]);
        $bytes = \Core\Pdf::render($html, ['paper' => 'A4', 'title' => __('Report') . ' – ' . $report['kicker'],
            'footer' => site()->label() . ' · ' . __('Report') . ' · ' . $report['kicker']]);
        $name = 'Report-' . preg_replace('~[^A-Za-z0-9-]+~', '-', site()->key) . '-' . $p['from'] . '-' . $p['to'] . '.pdf';
        return self::secure(\Core\Pdf::response($bytes, $name));
    }

    public function settings(Request $r): Response
    {
        $this->guard($r);
        if (!can('report.manage')) throw new HttpException(403);
        return $this->page('settings', ['cfg' => Report::config()]);
    }

    public function save(Request $r): Response
    {
        $this->guard($r);
        if (!can('report.manage')) throw new HttpException(403);
        $s = app()->settings;
        if ($r->str('do') === 'geoip') {
            $msg = Geo::download();
            app()->session->flash(Geo::available() ? 'success' : 'error', $msg);
            return Response::redirect(url('/admin/report/einstellungen'), 303);
        }
        $s->set('report.geo', in_array($r->str('geo'), ['off', 'country', 'region', 'city'], true) ? $r->str('geo') : 'city');
        $s->set('report.dnt', !empty($r->post['dnt']));
        $s->set('report.keep_months', max(1, min(60, (int) $r->str('keep_months', '25'))));
        $s->set('report.exclude', mb_substr(trim((string) ($r->post['exclude'] ?? '')), 0, 2000));
        app()->session->flash('success', __('Einstellungen gespeichert.'));
        return Response::redirect(url('/admin/report/einstellungen'), 303);
    }
}
