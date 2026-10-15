<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

use MaxMind\Db\Reader;

/**
 * Standort aus der IP-Adresse – nur im Moment des Aufrufs, gespeichert werden Land, Bundesland/Region und Stadt, nie die IP.
 * Datenbank: „IP to City Lite“ von DB-IP (CC BY 4.0, ohne Konto, monatlich neu) im MaxMind-Format, einmal je Installation unter
 * storage/report-geo/. Laden/aktualisieren: php bin/console report:geoip (Cron monatlich). Quellenangabe im Report: „IP Geolocation by DB-IP“.
 * Stadt ist ungefähr (Mobilfunk: oft Ort des Netzknotens); Auflösung einstellbar: aus, Land, Region, Stadt.
 */
final class Geo
{
    public const SOURCE = 'https://db-ip.com';
    private static ?Reader $reader = null;
    private static bool $tried = false;

    public static function file(): string
    {
        return ROOT . '/storage/report-geo/dbip-city-lite.mmdb';
    }

    public static function available(): bool
    {
        return is_file(self::file()) && class_exists(Reader::class);
    }

    /** Stand der Datenbank (Datei-Datum) oder null */
    public static function updated(): ?int
    {
        return is_file(self::file()) ? (int) filemtime(self::file()) : null;
    }

    /** @return array{0: string, 1: string, 2: string} Land (ISO), Region, Stadt – je nach Auflösung leer */
    public static function lookup(string $ip, string $level): array
    {
        if ($level === 'off' || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return ['', '', ''];
        if (!self::$tried) {
            self::$tried = true;
            try { if (self::available()) self::$reader = new Reader(self::file()); } catch (\Throwable $e) { error_log('[report] GeoIP: ' . $e->getMessage()); }
        }
        if (!self::$reader) return ['', '', ''];
        try { $r = self::$reader->get($ip); } catch (\Throwable) { return ['', '', '']; }
        if (!is_array($r)) return ['', '', ''];
        $name = fn(?array $n) => is_array($n) ? (string) ($n['de'] ?? $n['en'] ?? reset($n) ?: '') : '';
        $country = strtoupper((string) ($r['country']['iso_code'] ?? ''));
        $region = $level === 'country' ? '' : $name($r['subdivisions'][0]['names'] ?? null);
        $city = $level === 'city' ? $name($r['city']['names'] ?? null) : '';
        return [$country, mb_substr($region, 0, 80), mb_substr($city, 0, 80)];
    }

    /** Datenbank des aktuellen (bzw. vorigen) Monats laden → Meldung */
    public static function download(): string
    {
        $dir = dirname(self::file());
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        foreach ([date('Y-m'), date('Y-m', strtotime('first day of last month'))] as $month) {
            $url = 'https://download.db-ip.com/free/dbip-city-lite-' . $month . '.mmdb.gz';
            $ch = curl_init($url);
            $tmp = $dir . '/download.gz';
            $fh = fopen($tmp, 'wb');
            curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'KLXM-Studio-Report']);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            fclose($fh);
            if ($status !== 200 || filesize($tmp) < 1_000_000) { @unlink($tmp); continue; }
            $out = $dir . '/new.mmdb';
            $in = gzopen($tmp, 'rb'); $o = fopen($out, 'wb');
            while (!gzeof($in)) fwrite($o, (string) gzread($in, 1 << 20));
            gzclose($in); fclose($o); @unlink($tmp);
            try { (new Reader($out))->close(); } catch (\Throwable $e) { @unlink($out); return 'Datei unbrauchbar: ' . $e->getMessage(); }
            rename($out, self::file());
            return "GeoIP-Datenbank $month geladen (" . round(filesize(self::file()) / 1048576) . ' MB).';
        }
        return 'GeoIP-Datenbank konnte nicht geladen werden (download.db-ip.com).';
    }
}
