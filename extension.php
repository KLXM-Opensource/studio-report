<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
/**
 * Erweiterung „report“: datensparsame Besucherauswertung ohne Cookies – ausgewertet als gedeuteter Bericht (eine DIN-A4-Seite)
 * statt klassischer Statistik-Ansicht. Seiten, Herkunft, Kampagnen, Geräte, Standorte (GeoIP, DB-IP Lite), Ereignisse und Ziele.
 * Standard aus (Funktion „report“). Doku: README.md, Entwicklerhandbuch → Report.
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\Report\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\Report\\')) . '.php';
        if (is_file($file)) require $file;
    }
});
if (!class_exists(\MaxMind\Db\Reader::class) && is_file(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';

use Klxm\Report\Collect;
use Klxm\Report\Query;
use Klxm\Report\Report;

return [
    'name' => 'report',
    'label' => 'Report – Besucher datensparsam auswerten',
    'version' => '0.2.0',
    'requires' => '>=1.0.0',
    'description' => 'Besucher ohne Cookies zählen und als gedeuteten Bericht auswerten: Seiten, Herkunft, Kampagnen, Geräte, Standorte, Ereignisse und Ziele – eine DIN-A4-Seite je Zeitraum.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'provides' => ['Menüpunkt „Report“ (Bericht, Zahlen, PDF, Einstellungen)', 'Karte „Report“ in der Übersicht', 'Datenquellen für den Block „Diagramm“', 'Zählung /_report', 'Rechte „Report ansehen“, „Report einstellen“'],
    'commands' => ['report:rollup', 'report:geoip', 'report:selftest'],
    // Geplante Aufgaben (Core\Cron, System → Geplante Aufgaben): laufen über den einen Cronjob der Installation
    'schedule' => [
        'rollup' => ['label' => 'Tage verdichten, Fristen anwenden', 'command' => 'report:rollup', 'cron' => '20 1 * * *', 'timeout' => 900],
        'geoip' => ['label' => 'GeoIP-Datenbank aktualisieren (DB-IP Lite)', 'command' => 'report:geoip', 'cron' => '40 4 3 * *', 'scope' => 'installation', 'timeout' => 600],
    ],
    'boot' => function (Core\Extension $x): void {
        $x->feature(Report::FEATURE, 'Report: Besucher datensparsam auswerten (ohne Cookies)', [Report::PERM, 'report.manage'], false);
        $x->permissions('Report', [Report::PERM => 'Report ansehen', 'report.manage' => 'Report einstellen (Standort, Ausschlüsse, GeoIP)']);
        $x->adminPage(['href' => '/admin/report', 'label' => 'Report', 'kind' => 'content', 'icon' => 'chart-line-up', 'perm' => Report::PERM, 'feature' => Report::FEATURE,
            'description' => 'Besucher, Herkunft, Kampagnen und Aktionen als gedeuteter Bericht']);

        $x->routes(function (Core\Http\Router $r): void {
            $r->post(Report::ENDPOINT, [Collect::class, 'hit']);
            $c = Klxm\Report\AdminController::class;
            $r->get('/admin/report', [$c, 'index'], Report::PERM);
            $r->get('/admin/report/zahlen', [$c, 'numbers'], Report::PERM);
            $r->post('/admin/report/pdf', [$c, 'pdf'], Report::PERM);
            $r->get('/admin/report/einstellungen', [$c, 'settings'], 'report.manage');
            $r->post('/admin/report/einstellungen', [$c, 'save'], 'report.manage');
        });

        // Zählskript auf jeder Seite der Website (nicht für Angemeldete; ohne Cookie, kein Speicher im Browser)
        $x->htmlFilter(function (string $html, array $ctx): string {
            if (!Report::on() || app()->auth->user() || !str_contains($html, '</body>')) return $html;
            $tag = '<script src="' . e(Report::asset('js/report.js')) . '" data-endpoint="' . e(url(Report::ENDPOINT)) . '"' . (Report::config()['dnt'] ? ' data-dnt="1"' : '') . ' defer></script>';
            return str_replace('</body>', $tag . '</body>', $html);
        });

        // Formular abgeschickt (vom Server, ohne JavaScript)
        $x->on(Core\Events\FormSubmitted::class, function (Core\Events\FormSubmitted $e): void {
            Collect::serverEvent('Formular abgeschickt', (string) ($e->definition['label'] ?? $e->table));
        });

        // Übersicht: kurze Zusammenfassung der letzten 7 Tage
        $x->dashboard(function (array $user): array {
            if (!Report::on()) return [];
            [$now, $prev] = Klxm\Report\Card::week();
            return ['tiles' => [['key' => 'report-besucher', 'label' => __('Besucher'), 'value' => fmt()->number($now), 'text' => __('7 Tage'),
                    'trend' => ['now' => $now, 'prev' => $prev, 'label' => __('Besucher')], 'href' => '/admin/report?zeitraum=7']],
                'cards' => ['report' => ['title' => __('Report'), 'icon' => 'chart-line-up', 'size' => 'half', 'lazy' => true, 'ttl' => 900,
                    'render' => fn(): string => Klxm\Report\Card::render()]]];
        }, Report::PERM);

        // Datenquellen für den Block „Diagramm“ (nur Summen, keine Einzeldaten)
        if (class_exists(Core\Charts::class)) {
            Core\Charts::registerSource('report.visitors', 'Report: Besucher je Tag (30 Tage)', fn(array $d): array => Klxm\Report\Card::chartVisitors(30));
            Core\Charts::registerSource('report.devices', 'Report: Besuche nach Gerät (30 Tage)', fn(array $d): array => Klxm\Report\Card::chartDim('device', 30));
            Core\Charts::registerSource('report.channels', 'Report: Herkunft der Besuche (30 Tage)', fn(array $d): array => Klxm\Report\Card::chartDim('channel', 30));
            Core\Charts::registerSource('report.countries', 'Report: Länder (30 Tage)', fn(array $d): array => Klxm\Report\Card::chartDim('country', 30));
        }

        $x->docs('manual', ['report' => ['title' => 'Report', 'after' => 'uebersicht', 'file' => __DIR__ . '/docs/manual.php']]);
        $x->docs('technical', ['report' => ['title' => 'Report (Erweiterung): Besucher ohne Cookies', 'part' => 3, 'after' => 'live', 'file' => __DIR__ . '/docs/technical.php']]);

        $x->health(fn(): array => Report::on() && Report::config()['geo'] !== 'off' && !Klxm\Report\Geo::available()
            ? [__('Report: GeoIP-Datenbank fehlt – System → Geplante Aufgaben → „GeoIP-Datenbank aktualisieren“ → Jetzt ausführen') => null] : []);

        $x->command('report:rollup', 'Report: abgeschlossene Tage verdichten, Fristen anwenden (geplante Aufgabe, täglich)', function (array $args): int {
            $r = Query::rollup();
            echo "  {$r['days']} Tage verdichtet, {$r['raw_deleted']} Rohdaten und {$r['daily_deleted']} alte Tagessummen gelöscht\n";
            return 0;
        });
        $x->command('report:geoip', 'Report: GeoIP-Datenbank (DB-IP Lite) laden bzw. aktualisieren – einmal je Installation, monatlich', function (array $args): int {
            echo '  ' . Klxm\Report\Geo::download() . "\n";
            return Klxm\Report\Geo::available() ? 0 : 1;
        });
        $x->command('report:selftest', 'Report: Zählung, Hash, Herkunft, Verdichtung, Bericht', fn(array $args): int => Klxm\Report\SelfTest::run());
    },
];
