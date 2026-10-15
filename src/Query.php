<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

/**
 * Auswertung für einen Zeitraum (optional gefiltert). Ein Tag wird zu Zeilen [dim, k, a, b] verdichtet – aus den Rohdaten
 * (letzte RAW_DAYS Tage) oder aus den gespeicherten Tagessummen (report_daily). Abgeschlossene Tage werden beim Aufruf und per
 * report:rollup gesichert; danach verfallen Rohdaten nach RAW_DAYS Tagen und Tagessummen nach keep_months Monaten.
 *   dim total(a=Aufrufe, b=Besucher) · visits(a=Besuche, b=Absprünge) · dur(a=Sekunden, b=gemessene Aufrufe)
 *   page(a=Aufrufe, b=Besucher) · entry · channel · ref · campaign · device · browser · os · lang · country · region · city (a=Besuche)
 *   event(k=Name, a=Anzahl, b=Besuche) · eprop(k=Name|Wert) · goal(k=Kampagne|Ereignis, a=Besuche)
 * Filter (nur Rohdaten): path, channel, campaign, device, country – auf Besuche bezogen.
 */
final class Query
{
    public const FILTERS = ['path', 'channel', 'campaign', 'device', 'country'];
    private const VISIT_DIMS = ['channel', 'ref', 'campaign', 'device', 'browser', 'os', 'lang', 'country', 'region', 'city'];

    /** Erster Tag mit Rohdaten */
    public static function rawStart(): string
    {
        return date('Y-m-d', strtotime('-' . (Report::RAW_DAYS - 1) . ' days'));
    }

    /** Zeilen eines Tages aus den Rohdaten */
    public static function dayFromRaw(string $day, array $filter = []): array
    {
        $db = Report::db();
        $w = 'h.day = ?'; $p = [$day];
        // Filter auf Besuche: Merkmal steht an jedem Aufruf (vom ersten geerbt); Seite: Besuche, die die Seite enthalten
        foreach ($filter as $f => $v) {
            if (!in_array($f, self::FILTERS, true) || $v === '') continue;
            if ($f === 'path') { $w .= ' AND EXISTS (SELECT 1 FROM report_hits x WHERE x.day = h.day AND x.vh = h.vh AND x.visit = h.visit AND x.path = ?)'; $p[] = $v; }
            else { $w .= " AND h.$f = ?"; $p[] = $v; }
        }
        $rows = [];
        $t = $db->fetch("SELECT COUNT(*) AS pv, COUNT(DISTINCT h.vh) AS uv, COALESCE(SUM(h.dur), 0) AS ds, SUM(CASE WHEN h.dur > 0 THEN 1 ELSE 0 END) AS dn FROM report_hits h WHERE $w", $p) ?: [];
        if (!(int) ($t['pv'] ?? 0)) return [];
        $rows[] = ['total', '', (int) $t['pv'], (int) $t['uv']];
        $rows[] = ['dur', '', (int) $t['ds'], (int) $t['dn']];
        $v = $db->fetch("SELECT COUNT(*) AS n, SUM(CASE WHEN c = 1 THEN 1 ELSE 0 END) AS b FROM (SELECT COUNT(*) AS c FROM report_hits h WHERE $w GROUP BY h.vh, h.visit)", $p) ?: [];
        $rows[] = ['visits', '', (int) ($v['n'] ?? 0), (int) ($v['b'] ?? 0)];
        foreach ($db->fetchAll("SELECT h.path AS k, COUNT(*) AS a, COUNT(DISTINCT h.vh) AS b FROM report_hits h WHERE $w GROUP BY h.path", $p) as $r) $rows[] = ['page', $r['k'], (int) $r['a'], (int) $r['b']];
        foreach ($db->fetchAll("SELECT h.path AS k, COUNT(*) AS a FROM report_hits h WHERE $w AND h.first = 1 GROUP BY h.path", $p) as $r) $rows[] = ['entry', $r['k'], (int) $r['a'], 0];
        foreach (self::VISIT_DIMS as $dim) {
            $k = match ($dim) { 'region' => "h.country || '|' || h.region", 'city' => "h.country || '|' || h.region || '|' || h.city", 'campaign' => "h.campaign || '|' || h.src || '|' || h.med", default => "h.$dim" };
            foreach ($db->fetchAll("SELECT $k AS k, COUNT(*) AS a FROM report_hits h WHERE $w AND h.first = 1 GROUP BY 1", $p) as $r) {
                $key = (string) $r['k'];
                if (in_array($dim, ['region', 'city'], true) && str_ends_with($key, '|')) continue;   // ohne Region/Stadt
                if ($dim === 'campaign' && str_starts_with($key, '|')) continue;
                $rows[] = [$dim, $key, (int) $r['a'], 0];
            }
        }
        // Ereignisse der (gefilterten) Besuche
        $ev = "SELECT e.name, e.prop, e.vh, e.visit FROM report_events e WHERE e.day = ? AND EXISTS (SELECT 1 FROM report_hits h WHERE $w AND h.vh = e.vh AND h.visit = e.visit)";
        $ep = array_merge([$day], $p);
        foreach ($db->fetchAll("SELECT name AS k, COUNT(*) AS a, COUNT(DISTINCT vh || '-' || visit) AS b FROM ($ev) GROUP BY name", $ep) as $r) $rows[] = ['event', $r['k'], (int) $r['a'], (int) $r['b']];
        foreach ($db->fetchAll("SELECT name || '|' || prop AS k, COUNT(*) AS a FROM ($ev) WHERE prop != '' GROUP BY 1 ORDER BY 2 DESC LIMIT 50", $ep) as $r) $rows[] = ['eprop', $r['k'], (int) $r['a'], 0];
        // Ziele je Kampagne: Besuche einer Kampagne mit einem Ereignis
        foreach ($db->fetchAll("SELECT h.campaign || '|' || e.name AS k, COUNT(DISTINCT h.vh || '-' || h.visit) AS a FROM report_hits h JOIN report_events e
            ON e.day = h.day AND e.vh = h.vh AND e.visit = h.visit WHERE $w AND h.first = 1 AND h.campaign != '' GROUP BY 1", $p) as $r) $rows[] = ['goal', $r['k'], (int) $r['a'], 0];
        return $rows;
    }

    /** Abgeschlossene Tage sichern (Tagessummen) und Fristen anwenden – idempotent */
    public static function rollup(): array
    {
        $db = Report::db();
        $done = (string) ($db->fetchValue("SELECT v FROM report_meta WHERE k = 'rolled'") ?: '');
        $today = date('Y-m-d');
        $n = 0;
        foreach ($db->fetchAll('SELECT DISTINCT day FROM report_hits WHERE day < ? AND day > ? ORDER BY day', [$today, $done]) as $r) {
            $day = (string) $r['day'];
            $own = !$db->pdo->inTransaction();   // in einer laufenden Transaktion (Selbsttest) mitlaufen
            if ($own) $db->pdo->beginTransaction();
            $db->query('DELETE FROM report_daily WHERE day = ?', [$day]);
            foreach (self::dayFromRaw($day) as [$dim, $k, $a, $b]) $db->insert('report_daily', ['day' => $day, 'dim' => $dim, 'k' => mb_substr((string) $k, 0, 255), 'a' => $a, 'b' => $b]);
            $db->query("INSERT OR REPLACE INTO report_meta (k, v) VALUES ('rolled', ?)", [$day]);
            if ($own) $db->pdo->commit();
            $n++;
        }
        // Fristen: Rohdaten (mit Tages-Hash) nach RAW_DAYS, Tagessummen nach keep_months
        $raw = $db->query('DELETE FROM report_hits WHERE day < ?', [self::rawStart()])->rowCount() + $db->query('DELETE FROM report_events WHERE day < ?', [self::rawStart()])->rowCount();
        $old = $db->query('DELETE FROM report_daily WHERE day < ?', [date('Y-m-d', strtotime('-' . Report::config()['keep_months'] . ' months'))])->rowCount();
        return ['days' => $n, 'raw_deleted' => $raw, 'daily_deleted' => $old];
    }

    /**
     * Daten eines Zeitraums: Summen je Dimension, Verlauf je Tag.
     * @return array{from: string, to: string, days: array<string, array{pv:int, uv:int}>, dims: array<string, array<string, array{0:int,1:int}>>, filtered: bool, limited: bool}
     */
    public static function period(string $from, string $to, array $filter = []): array
    {
        $filter = array_filter(array_intersect_key($filter, array_flip(self::FILTERS)), fn($v) => $v !== '' && $v !== null);
        $limited = false;
        if ($filter && $from < self::rawStart()) { $from = self::rawStart(); $limited = true; }   // Filter nur auf Rohdaten
        $db = Report::db();
        $daily = [];
        if (!$filter) foreach ($db->fetchAll('SELECT day, dim, k, a, b FROM report_daily WHERE day BETWEEN ? AND ?', [$from, $to]) as $r) $daily[$r['day']][] = [$r['dim'], $r['k'], (int) $r['a'], (int) $r['b']];
        $out = ['from' => $from, 'to' => $to, 'days' => [], 'dims' => [], 'filtered' => (bool) $filter, 'limited' => $limited];
        for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
            $day = date('Y-m-d', $t);
            $rows = $daily[$day] ?? ($day >= self::rawStart() ? self::dayFromRaw($day, $filter) : []);
            $out['days'][$day] = ['pv' => 0, 'uv' => 0];
            foreach ($rows as [$dim, $k, $a, $b]) {
                if ($dim === 'total') $out['days'][$day] = ['pv' => $a, 'uv' => $b];
                $cur = $out['dims'][$dim][$k] ?? [0, 0];
                $out['dims'][$dim][$k] = [$cur[0] + $a, $cur[1] + $b];
            }
        }
        foreach ($out['dims'] as &$list) uasort($list, fn($x, $y) => $y[0] <=> $x[0]);
        return $out;
    }

    /** Kennzahlen aus period() */
    public static function totals(array $p): array
    {
        $g = fn(string $dim) => $p['dims'][$dim][''] ?? [0, 0];
        [$pv, $uv] = $g('total'); [$visits, $bounces] = $g('visits'); [$ds, $dn] = $g('dur');
        return ['pv' => $pv, 'uv' => $uv, 'visits' => $visits, 'bounce' => $visits ? round($bounces / $visits * 100) : null,
            'dur' => $dn ? (int) round($ds / $dn) : null, 'ppv' => $visits ? round($pv / $visits, 1) : null];
    }

    /** Zeitraum aus Auswahl: 7, 30, monat, vormonat, quartal, jahr, frei (von/bis) → [von, bis, Vorzeitraum von, bis] */
    public static function range(string $sel, string $von = '', string $bis = ''): array
    {
        $today = date('Y-m-d');
        [$f, $t] = match ($sel) {
            '7' => [date('Y-m-d', strtotime('-6 days')), $today],
            'monat' => [date('Y-m-01'), $today],
            'vormonat' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
            'quartal' => [date('Y-m-d', strtotime('-89 days')), $today],
            'jahr' => [date('Y-01-01'), $today],
            'frei' => [preg_match('~^\d{4}-\d{2}-\d{2}$~', $von) ? $von : date('Y-m-d', strtotime('-29 days')), preg_match('~^\d{4}-\d{2}-\d{2}$~', $bis) ? min($bis, $today) : $today],
            default => [date('Y-m-d', strtotime('-29 days')), $today],
        };
        if ($f > $t) [$f, $t] = [$t, $f];
        $len = (int) round((strtotime($t) - strtotime($f)) / 86400) + 1;
        if ($sel === 'monat' || $sel === 'vormonat') {
            $pf = date('Y-m-01', strtotime($f . ' -1 month'));
            $pt = date('Y-m-d', min(strtotime($pf . ' +' . ($len - 1) . ' days'), strtotime(date('Y-m-t', strtotime($pf)))));
        } else {
            $pt = date('Y-m-d', strtotime($f . ' -1 day'));
            $pf = date('Y-m-d', strtotime($pt . ' -' . ($len - 1) . ' days'));
        }
        return [$f, $t, $pf, $pt];
    }
}
