<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

/**
 * php bin/console report:selftest --site=… – Tages-Hash (gleich am Tag, anders am nächsten), Bots, Gerät/Browser/System, Herkunft,
 * Pfade (Verwaltung, Ausschlüsse), Zählung von Besuchen/Absprüngen/Ereignissen/Zielen, Verdichtung, Zeiträume, Bericht in Worten.
 * Läuft in einer Transaktion der Report-Datenbank, die zurückgerollt wird.
 */
final class SelfTest
{
    public static function run(): int
    {
        $ok = 0; $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; echo "  ✓ $what\n"; return; }
            $fails[] = $what; echo "  ✗ $what – erwartet " . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n";
        };
        $d1 = date('Y-m-d'); $d0 = date('Y-m-d', strtotime('-1 day'));
        $h = Report::visitorHash('203.0.113.7', 'UA', $d1);
        $eq('Tages-Hash: gleich am selben Tag, 16 Zeichen', [$h === Report::visitorHash('203.0.113.7', 'UA', $d1), strlen($h)], [true, 16]);
        $eq('Tages-Hash: anderer Tag → andere Kennung', $h !== Report::visitorHash('203.0.113.7', 'UA', $d0), true);
        $eq('Salz früherer Tage gelöscht', count(glob(Report::dir() . '/salt-*') ?: []), 1);
        $eq('Bots erkannt', [Report::isBot('Mozilla/5.0 (compatible; Googlebot/2.1)'), Report::isBot('curl/8.0'), Report::isBot('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1')], [true, true, false]);
        $eq('Gerät/Browser/System', [Report::agent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605 Version/17.0 Mobile/15E148 Safari/604.1'),
            Report::agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0'), Report::agent('Mozilla/5.0 (iPad; CPU OS 17_0) Safari/604.1')],
            [['mobile', 'Safari', 'iOS'], ['desktop', 'Edge', 'Windows'], ['tablet', 'Safari', 'iOS']]);
        $eq('Herkunft', [Report::channel('', ''), Report::channel('google.de', ''), Report::channel('instagram.com', ''), Report::channel('beispiel.de', ''), Report::channel('google.de', 'herbst')],
            ['direct', 'search', 'social', 'referral', 'campaign']);
        $eq('Pfade: Verwaltung und /_ ausgeschlossen', [Collect::path('/admin/pages'), Collect::path('/_report'), Collect::path('/kontakt/'), Collect::path('/')], [null, null, '/kontakt', '/']);
        $eq('Referrer: nur Host, eigene Website leer', [Collect::refHost('https://www.google.de/search?q=x', 'meine.de'), Collect::refHost('https://meine.de/a', 'meine.de')], ['google.de', '']);

        $db = Report::db();
        $db->pdo->beginTransaction();
        try {
            $db->query('DELETE FROM report_hits WHERE day = ?', [$d0]); $db->query('DELETE FROM report_events WHERE day = ?', [$d0]); $db->query('DELETE FROM report_daily WHERE day = ?', [$d0]);
            $ins = fn(string $vh, int $visit, bool $first, string $path, array $a = []) => $db->insert('report_hits', ['ts' => strtotime($d0 . ' 12:00'), 'day' => $d0, 'vh' => $vh, 'visit' => $visit,
                'first' => $first ? 1 : 0, 'path' => $path, 'dur' => 30] + $a + ['ref' => '', 'channel' => 'direct', 'campaign' => '', 'src' => '', 'med' => '', 'device' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS', 'lang' => 'de', 'country' => 'DE', 'region' => 'Nordrhein-Westfalen', 'city' => 'Moers']);
            $camp = ['channel' => 'campaign', 'campaign' => 'herbst', 'device' => 'desktop'];
            $ins('a', 1, true, '/', $camp); $ins('a', 1, false, '/kontakt', $camp);
            $ins('b', 1, true, '/'); $ins('c', 1, true, '/leistungen');
            $db->insert('report_events', ['ts' => strtotime($d0 . ' 12:01'), 'day' => $d0, 'vh' => 'a', 'visit' => 1, 'name' => 'Formular abgeschickt', 'prop' => 'Kontakt', 'path' => '/kontakt']);
            $rows = [];
            foreach (Query::dayFromRaw($d0) as [$dim, $k, $a, $b]) $rows[$dim][$k] = [$a, $b];
            $eq('Aufrufe, Besucher, Besuche, Absprünge', [$rows['total'][''], $rows['visits']['']], [[4, 3], [3, 2]]);
            $eq('Einstiegsseiten und Geräte je Besuch', [$rows['entry']['/'][0], $rows['device']['mobile'][0], $rows['device']['desktop'][0]], [2, 2, 1]);
            $eq('Ziel je Kampagne', $rows['goal']['herbst|Formular abgeschickt'][0] ?? 0, 1);
            $eq('Filter Kampagne', (Query::dayFromRaw($d0, ['campaign' => 'herbst'])[0] ?? []), ['total', '', 2, 1]);
            $db->query("DELETE FROM report_meta WHERE k = 'rolled'");
            Query::rollup();
            $eq('Verdichtung: Tagessumme gespeichert', (int) $db->fetchValue("SELECT a FROM report_daily WHERE day = ? AND dim = 'total'", [$d0]), 4);
            $p = Query::period($d0, $d0);
            $eq('Zeitraum aus Tagessummen', Query::totals($p)['visits'], 3);
            $r = (new Narrative($p, Query::period(date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('-2 days')))))->build();
            $eq('Bericht: Text und Abschnitte', [$r['empty'], count($r['lead']) >= 1, count($r['figures']), in_array('herkunft', array_column($r['sections'], 'id'), true)], [false, true, 4, true]);
            [$f, $t] = Query::range('vormonat');
            $eq('Zeitraum „Letzter Monat“', [$f, $t], [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))]);
        } finally {
            if ($db->pdo->inTransaction()) $db->pdo->rollBack();
            $db->query('DELETE FROM report_hits WHERE day = ? AND vh IN (\'a\', \'b\', \'c\')', [$d0]);
            $db->query('DELETE FROM report_events WHERE day = ? AND vh = \'a\'', [$d0]);
            $db->query('DELETE FROM report_daily WHERE day = ?', [$d0]);
            $db->query("DELETE FROM report_meta WHERE k = 'rolled'");
        }
        echo "\n  " . $ok . ' Prüfungen bestanden, ' . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }
}
