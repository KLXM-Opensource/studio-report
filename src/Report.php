<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

use Core\Database;
use Core\Db\Table;

/**
 * Datensparsame Besucherauswertung ohne Cookies. Ein kleines Skript meldet Seitenaufrufe und Ereignisse an /_report;
 * gezählt wird ohne Cookie über einen Tages-Hash aus IP-Adresse und Browserkennung mit einem Salz, das täglich neu entsteht
 * und danach gelöscht wird – Besucher lassen sich so innerhalb eines Tages zählen, aber nie über Tage verfolgen.
 * Gespeichert werden nie IP-Adressen. Daten: eigene SQLite-Datei je Website (storage/…/report/report.sqlite).
 */
final class Report
{
    public const FEATURE = 'report';
    public const PERM = 'report.view';
    public const ENDPOINT = '/_report';
    /** Rohdaten (Aufrufe mit Tages-Hash) – danach nur Tagessummen */
    public const RAW_DAYS = 30;
    /** Neuer Besuch nach 30 Minuten Pause */
    public const VISIT_GAP = 1800;

    private static ?Database $db = null;

    public static function setting(string $key, mixed $default = null): mixed
    {
        return app()->settings->get('report.' . $key, $default);
    }

    /** Einstellungen mit Standardwerten */
    public static function config(): array
    {
        return [
            'geo' => (string) self::setting('geo', 'city'),            // off | country | region | city
            'dnt' => (bool) self::setting('dnt', true),                 // „Do Not Track“ und GPC respektieren
            'keep_months' => max(1, min(60, (int) self::setting('keep_months', 25))),
            'exclude' => (string) self::setting('exclude', ''),         // Pfade (je Zeile, * am Ende = Präfix)
        ];
    }

    public static function dir(): string
    {
        $d = site()->storage('report');
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    /** Eigene Datenbank der Auswertung (WAL, eigene Datei – Zugriffe der Besucher sperren nie die Datenbank der Website) */
    public static function db(): Database
    {
        if (self::$db) return self::$db;
        $path = self::dir() . '/report.sqlite';
        $fresh = !is_file($path);
        $db = new Database(['driver' => 'sqlite', 'path' => $path]);
        $db->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL; PRAGMA busy_timeout=3000');
        if ($fresh || !Table::exists('report_hits', $db)) self::schema($db);
        return self::$db = $db;
    }

    /** Für Tests: andere Website / frische Verbindung */
    public static function reset(): void
    {
        self::$db = null;
    }

    public static function schema(Database $db): void
    {
        Table::named('report_hits')->id()
            ->column('ts', 'int', ['null' => false])->column('day', 'string', ['length' => 10, 'null' => false])
            ->column('vh', 'string', ['length' => 16])->column('visit', 'int', ['default' => 1])->column('first', 'bool', ['default' => false])
            ->column('path', 'string', ['length' => 255])->column('ref', 'string', ['length' => 120])->column('channel', 'string', ['length' => 20])
            ->column('campaign', 'string', ['length' => 120])->column('src', 'string', ['length' => 80])->column('med', 'string', ['length' => 80])
            ->column('device', 'string', ['length' => 12])->column('browser', 'string', ['length' => 20])->column('os', 'string', ['length' => 20])
            ->column('lang', 'string', ['length' => 8])->column('country', 'string', ['length' => 2])->column('region', 'string', ['length' => 80])
            ->column('city', 'string', ['length' => 80])->column('dur', 'int', ['default' => 0])
            ->index(['day'])->index(['vh', 'day'])->ensure($db);
        Table::named('report_events')->id()
            ->column('ts', 'int', ['null' => false])->column('day', 'string', ['length' => 10, 'null' => false])
            ->column('vh', 'string', ['length' => 16])->column('visit', 'int', ['default' => 1])
            ->column('name', 'string', ['length' => 80])->column('prop', 'string', ['length' => 160])->column('path', 'string', ['length' => 255])
            ->index(['day'])->ensure($db);
        Table::named('report_daily')
            ->column('day', 'string', ['length' => 10, 'null' => false])->column('dim', 'string', ['length' => 12, 'null' => false])
            ->column('k', 'string', ['length' => 255, 'null' => false, 'default' => ''])->column('a', 'int', ['default' => 0])->column('b', 'int', ['default' => 0])
            ->primary(['day', 'dim', 'k'])->ensure($db);
        Table::named('report_meta')->column('k', 'string', ['length' => 40, 'null' => false])->column('v', 'text')->primary(['k'])->ensure($db);
    }

    // ------------------------------------------------------------------ Tages-Hash ohne Cookie

    /** Salz des Tages (zufällig, je Website); Salze früherer Tage werden gelöscht */
    public static function salt(string $day): string
    {
        $f = self::dir() . '/salt-' . $day;
        if (!is_file($f)) {
            foreach (glob(self::dir() . '/salt-*') ?: [] as $old) if (basename($old) !== 'salt-' . $day) @unlink($old);
            @file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
            @chmod($f, 0600);
        }
        return (string) @file_get_contents($f);
    }

    /** Besucher-Kennung für einen Tag: nicht umkehrbar, am nächsten Tag ohne Bezug zur alten */
    public static function visitorHash(string $ip, string $ua, string $day): string
    {
        return substr(hash('sha256', self::salt($day) . '|' . site()->key . '|' . $ip . '|' . $ua), 0, 16);
    }

    // ------------------------------------------------------------------ Gerät, Browser, System (ohne Bibliothek, grob)

    public static function isBot(string $ua): bool
    {
        return $ua === '' || (bool) preg_match('~bot|crawl|spider|slurp|preview|headless|lighthouse|pagespeed|python|curl|wget|httpclient|go-http|java/|okhttp|scrapy|monitor|uptime|feed|facebookexternalhit|embedly|quora link|whatsapp|telegrambot|discordbot|bingpreview|petal|semrush|ahrefs|mj12|dotbot~i', $ua);
    }

    /** @return array{0: string, 1: string, 2: string} Gerät (mobile|tablet|desktop), Browser, System */
    public static function agent(string $ua, int $screen = 0): array
    {
        $device = preg_match('~iPad|Tablet|Kindle|Silk|PlayBook|(Android(?!.*Mobile))~i', $ua) ? 'tablet'
            : (preg_match('~Mobi|iPhone|iPod|Android.*Mobile|Windows Phone~i', $ua) ? 'mobile' : 'desktop');
        if ($device === 'desktop' && $screen > 0 && $screen < 768) $device = 'mobile';
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet', str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Andere',
        };
        $os = match (true) {
            (bool) preg_match('~iPhone|iPad|iPod~', $ua) => 'iOS', str_contains($ua, 'Android') => 'Android', str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS', str_contains($ua, 'CrOS') => 'ChromeOS', str_contains($ua, 'Linux') => 'Linux', default => 'Andere',
        };
        return [$device, $browser, $os];
    }

    // ------------------------------------------------------------------ Herkunft

    private const SEARCH = ['google.', 'bing.com', 'duckduckgo.com', 'ecosia.org', 'startpage.com', 'search.yahoo', 'yahoo.com', 'qwant.com', 'yandex.', 'baidu.com', 'search.brave.com', 'perplexity.ai', 'chatgpt.com'];
    private const SOCIAL = ['facebook.com', 'fb.me', 'instagram.com', 'linkedin.com', 'lnkd.in', 't.co', 'x.com', 'twitter.com', 'youtube.com', 'pinterest.', 'xing.com', 'tiktok.com', 'reddit.com', 'threads.net', 'bsky.app', 'mastodon.', 'whatsapp.com', 'telegram.'];

    /** Kanal aus Kampagne und Herkunft: campaign | search | social | referral | direct */
    public static function channel(string $refHost, string $campaign): string
    {
        if ($campaign !== '') return 'campaign';
        if ($refHost === '') return 'direct';
        foreach (self::SEARCH as $s) if (str_contains($refHost, $s)) return 'search';
        foreach (self::SOCIAL as $s) if (str_contains($refHost, $s)) return 'social';
        return 'referral';
    }

    /** Text in der Sprache des Ortes: Verwaltung __(), Website lt() (z. B. Block „Diagramm“ mit Report-Daten) */
    public static function t(string $s, array $r = []): string
    {
        return app()->request && !app()->request->isAdminPath() ? lt($s, $r) : __($s, $r);
    }

    /** Bezeichnungen */
    public static function label(string $dim, string $k): string
    {
        return match ($dim) {
            'channel' => ['campaign' => self::t('Kampagnen'), 'search' => self::t('Suchmaschinen'), 'social' => self::t('Soziale Netzwerke'), 'referral' => self::t('Andere Websites'), 'direct' => self::t('Direkt')][$k] ?? $k,
            'device' => ['mobile' => self::t('Smartphone'), 'tablet' => self::t('Tablet'), 'desktop' => self::t('Computer')][$k] ?? $k,
            'country' => self::countryName($k),
            default => $k === 'Andere' ? self::t('Andere') : $k,
        };
    }

    /** Name der Website für Kopf und PDF: Organisation bzw. Kurzname aus den Einstellungen, sonst Name/Domain der Website */
    public static function siteName(): string
    {
        foreach (['org_name', 'short_name', 'sys.pwa_name'] as $k) {
            $v = trim((string) (app()->settings->get($k, '') ?? ''));
            if ($v !== '') return $v;
        }
        return site()->label();
    }

    public static function countryName(string $code): string
    {
        if ($code === '') return self::t('Unbekannt');
        if (class_exists(\Locale::class)) {
            $n = \Locale::getDisplayRegion('-' . $code, \Core\I18n::locale() ?: 'de');
            if ($n !== '' && $n !== $code) return $n;
        }
        return $code;
    }

    public static function asset(string $path): string
    {
        $x = \Core\Extensions::active()['report'] ?? null;
        return $x ? $x->asset($path) : '';
    }

    /** Ist die Auswertung auf dieser Website eingeschaltet? */
    public static function on(): bool
    {
        return \Core\Features::on(self::FEATURE, false);
    }
}
