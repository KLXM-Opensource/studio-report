<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/** Verwaltung → Report: gedeuteter Bericht für einen Zeitraum (druckbar, eine DIN-A4-Seite) und Einstellungen */
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

    public function index(Request $r): Response
    {
        $this->guard($r);
        Query::rollup();
        $sel = $r->str('zeitraum', '30');
        [$f, $t, $pf, $pt] = Query::range($sel, $r->str('von'), $r->str('bis'));
        $filter = [];
        foreach (Query::FILTERS as $k) if ($r->str($k) !== '') $filter[$k] = mb_substr($r->str($k), 0, 255);
        $cur = Query::period($f, $t, $filter);
        $prev = Query::period($pf, $pt, $filter);
        $options = Query::period(Query::rawStart(), date('Y-m-d'));   // Auswahl für Filter
        return $this->page('report', ['sel' => $sel, 'from' => $f, 'to' => $t, 'pfrom' => $pf, 'pto' => $pt, 'filter' => $filter,
            'cur' => $cur, 'report' => (new Narrative($cur, $prev))->build(), 'options' => $options['dims']]);
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
