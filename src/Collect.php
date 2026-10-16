<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Annahme der Meldungen des Skripts (POST /_report, text/plain JSON per sendBeacon) und Ereignisse vom Server.
 *   {t: 'pv', p: Pfad, q: Suchteil (utm_*, k), r: Referrer, w: Bildschirmbreite, l: Sprache}   Seitenaufruf
 *   {t: 'ev', n: Name, d: Wert (Text), p: Pfad}                                                   Ereignis
 *   {t: 'end', p: Pfad, s: Sekunden sichtbar}                                                     Verweildauer
 * Nicht gezählt: Bots, angemeldete Personen (Redaktion), „Do Not Track“/GPC (Einstellung), ausgeschlossene Pfade, Verwaltung.
 * Antwort immer 204 – keine Rückmeldung, ob gezählt wurde.
 */
final class Collect
{
    /** Höchstens so viele Seitenaufrufe je Website und Tag (Schutz vor Fluten der Statistik) */
    public const DAY_MAX = 200000;

    public function hit(Request $r): Response
    {
        $ok = new Response('', 204, ['Cache-Control' => 'no-store']);
        if (!Report::on()) return $ok;
        // Nur vom eigenen Skript auf der Website (Browser setzen Sec-Fetch-Site; fehlt es, zählt die Begrenzung unten)
        $fetchSite = strtolower((string) ($r->server['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($fetchSite !== '' && $fetchSite !== 'same-origin') return $ok;
        $raw = (string) file_get_contents('php://input', false, null, 0, 4096);
        $d = json_decode($raw, true);
        if (!is_array($d)) return $ok;
        try {
            self::record($r, $d);
        } catch (\Throwable $e) {
            error_log('[report] ' . $e->getMessage());
        }
        return $ok;
    }

    /** Ausschlüsse für eine Anfrage (Bot, Redaktion, DNT/GPC) – true = nicht zählen */
    public static function skip(Request $r): bool
    {
        $ua = (string) ($r->server['HTTP_USER_AGENT'] ?? '');
        if (Report::isBot($ua)) return true;
        if (app()->auth->user()) return true;   // Redaktion zählt nicht (Sitzung nur mit Cookie der Verwaltung)
        if (Report::config()['dnt'] && (($r->server['HTTP_DNT'] ?? '') === '1' || ($r->server['HTTP_SEC_GPC'] ?? '') === '1')) return true;
        return false;
    }

    public static function record(Request $r, array $d): void
    {
        if (self::skip($r)) return;
        $type = (string) ($d['t'] ?? '');
        $path = self::path((string) ($d['p'] ?? '/'));
        if ($path === null) return;
        $now = time();
        $day = date('Y-m-d', $now);
        $ua = (string) ($r->server['HTTP_USER_AGENT'] ?? '');
        $vh = Report::visitorHash($r->ip(), $ua, $day);
        $db = Report::db();
        $last = $db->fetch('SELECT ts, visit FROM report_hits WHERE vh = ? AND day = ? ORDER BY id DESC LIMIT 1', [$vh, $day]);

        if ($type === 'end') {
            $s = max(0, min(3600, (int) ($d['s'] ?? 0)));
            if ($s && $last) $db->query('UPDATE report_hits SET dur = MAX(dur, ?) WHERE id = (SELECT id FROM report_hits WHERE vh = ? AND day = ? AND path = ? ORDER BY id DESC LIMIT 1)', [$s, $vh, $day, $path]);
            return;
        }
        if ($type === 'ev') {
            $name = mb_substr(trim(strip_tags((string) ($d['n'] ?? ''))), 0, 80);
            if ($name === '') return;
            self::event($vh, $day, (int) ($last['visit'] ?? 1), $name, (string) ($d['d'] ?? ''), $path);
            return;
        }
        if ($type !== 'pv') return;
        // Missbrauch begrenzen: höchstens 300 Aufrufe je Besucher und Tag, 600 je Anschluss und Stunde (IP nur als HMAC,
        // wechselnde Browserkennung hilft also nicht) und ein Tageslimit für die ganze Website
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM report_hits WHERE vh = ? AND day = ?', [$vh, $day]) >= 300) return;
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM report_hits WHERE day = ?', [$day]) >= self::DAY_MAX) return;
        $limiter = new \Core\RateLimiter(app()->db);
        $ipKey = 'report:' . substr(hash_hmac('sha256', $r->ip(), app()->key()), 0, 32);
        if ($limiter->tooMany($ipKey, 600, 3600)) return;
        $limiter->hit($ipKey);

        $newVisit = !$last || $now - (int) $last['ts'] > Report::VISIT_GAP;
        $visit = $newVisit ? (int) ($last['visit'] ?? 0) + 1 : (int) $last['visit'];
        if ($newVisit) {
            // Merkmale des Besuchs (beim ersten Aufruf ermittelt, an alle Aufrufe des Besuchs vererbt)
            parse_str(ltrim((string) ($d['q'] ?? ''), '?'), $q);
            $campaign = mb_substr(trim((string) ($q['utm_campaign'] ?? $q['k'] ?? '')), 0, 120);
            $src = mb_substr(trim((string) ($q['utm_source'] ?? '')), 0, 80);
            $med = mb_substr(trim((string) ($q['utm_medium'] ?? '')), 0, 80);
            $refHost = self::refHost((string) ($d['r'] ?? ''), $r->host());
            if ($campaign === '' && $src !== '') $campaign = $src;
            [$device, $browser, $os] = Report::agent($ua, (int) ($d['w'] ?? 0));
            [$country, $region, $city] = Geo::lookup($r->ip(), Report::config()['geo']);
            $attrs = ['ref' => $refHost, 'channel' => Report::channel($refHost, $campaign), 'campaign' => $campaign, 'src' => $src, 'med' => $med,
                'device' => $device, 'browser' => $browser, 'os' => $os, 'lang' => strtolower(substr((string) ($d['l'] ?? ''), 0, 2)),
                'country' => $country, 'region' => $region, 'city' => $city];
        } else {
            $attrs = $db->fetch('SELECT ref, channel, campaign, src, med, device, browser, os, lang, country, region, city FROM report_hits
                WHERE vh = ? AND day = ? AND visit = ? AND first = 1 ORDER BY id LIMIT 1', [$vh, $day, $visit]) ?: [];
        }
        $db->insert('report_hits', ['ts' => $now, 'day' => $day, 'vh' => $vh, 'visit' => $visit, 'first' => $newVisit ? 1 : 0, 'path' => $path, 'dur' => 0] + $attrs);
    }

    /** Ereignis speichern (auch vom Server, z. B. Formular abgeschickt) */
    public static function event(string $vh, string $day, int $visit, string $name, string $prop = '', string $path = ''): void
    {
        $db = Report::db();
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM report_events WHERE vh = ? AND day = ?', [$vh, $day]) >= 300) return;
        $db->insert('report_events', ['ts' => time(), 'day' => $day, 'vh' => $vh, 'visit' => $visit, 'name' => $name,
            'prop' => mb_substr(trim(strip_tags($prop)), 0, 160), 'path' => mb_substr($path, 0, 255)]);
    }

    /** Ereignis zur aktuellen Anfrage (Server): Besuch über denselben Tages-Hash */
    public static function serverEvent(string $name, string $prop = ''): void
    {
        if (!Report::on()) return;
        $r = app()->request;
        if (!$r || self::skip($r)) return;
        try {
            $day = date('Y-m-d');
            $vh = Report::visitorHash($r->ip(), (string) ($r->server['HTTP_USER_AGENT'] ?? ''), $day);
            $visit = (int) (Report::db()->fetchValue('SELECT visit FROM report_hits WHERE vh = ? AND day = ? ORDER BY id DESC LIMIT 1', [$vh, $day]) ?: 1);
            $ref = (string) parse_url((string) ($r->server['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
            self::event($vh, $day, $visit, $name, $prop, $ref ?: $r->path);
        } catch (\Throwable $e) {
            error_log('[report] ' . $e->getMessage());
        }
    }

    /** Pfad prüfen: nur Website-Pfade, ohne Verwaltung/Ausschlüsse → normalisiert oder null */
    public static function path(string $p): ?string
    {
        $p = '/' . ltrim((string) parse_url($p, PHP_URL_PATH), '/');
        $p = mb_substr(rtrim($p, '/') ?: '/', 0, 255);
        $admin = '/' . trim(\Core\AdminPath::prefix(), '/');
        if (str_starts_with($p, $admin) || str_starts_with($p, '/admin') || str_starts_with($p, '/_')) return null;
        foreach (preg_split('~\R~', Report::config()['exclude']) ?: [] as $x) {
            $x = trim($x);
            if ($x === '') continue;
            if (str_ends_with($x, '*') ? str_starts_with($p, rtrim($x, '*')) : $p === rtrim($x, '/')) return null;
        }
        return $p;
    }

    /** Herkunft: nur der Host, ohne www., nie die eigene Website */
    public static function refHost(string $ref, string $ownHost): string
    {
        $h = strtolower((string) parse_url($ref, PHP_URL_HOST));
        $h = preg_replace('~^www\.~', '', $h) ?? '';
        $own = preg_replace('~^www\.~', '', strtolower(explode(':', $ownHost)[0])) ?? '';
        if ($h === '' || $h === $own || in_array($h, array_map(fn($x) => preg_replace('~^www\.~', '', strtolower($x)), site()->hosts()), true)) return '';
        return mb_substr($h, 0, 120);
    }
}
