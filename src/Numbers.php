<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

/**
 * Zahlen: klassische Statistik zum Durchklicken (views/admin/numbers.php) – Kennzahlen mit Vergleich, Verlauf einer gewählten
 * Kennzahl, Tabellen je Merkmal mit Anteil und Veränderung. Zeilen, für die es einen Filter gibt, setzen ihn per Klick.
 */
final class Numbers
{
    /** Merkmale mit Tabelle, in dieser Reihenfolge */
    public const DIMS = ['page', 'entry', 'channel', 'ref', 'campaign', 'device', 'browser', 'os', 'lang', 'country', 'region', 'city', 'event', 'eprop'];
    /** Merkmal → Filter (Query::FILTERS) */
    public const FILTER = ['page' => 'path', 'entry' => 'path', 'channel' => 'channel', 'campaign' => 'campaign', 'device' => 'device', 'country' => 'country'];
    public const METRICS = ['uv', 'visits', 'pv', 'bounce', 'dur', 'ppv'];

    public static function title(string $dim): string
    {
        return match ($dim) {
            'page' => __('Seiten'), 'entry' => __('Einstiegsseiten'), 'channel' => __('Herkunft'), 'ref' => __('Verweisende Websites'),
            'campaign' => __('Kampagnen'), 'device' => __('Geräte'), 'browser' => __('Browser'), 'os' => __('Betriebssysteme'), 'lang' => __('Sprachen'),
            'country' => __('Länder'), 'region' => __('Regionen'), 'city' => __('Städte (ungefähr)'), 'event' => __('Aktionen'), 'eprop' => __('Aktionen im Einzelnen'),
            default => $dim,
        };
    }

    /** Spalten: [Hauptwert, Nebenwert|null] */
    public static function columns(string $dim): array
    {
        return match ($dim) {
            'page' => [__('Aufrufe'), __('Besucher')], 'entry' => [__('Einstiege'), null], 'event' => [__('Anzahl'), __('Besuche')], 'eprop' => [__('Anzahl'), null],
            default => [__('Besuche'), null],
        };
    }

    public static function metricLabel(string $m): string
    {
        return match ($m) {
            'visits' => __('Besuche'), 'pv' => __('Seitenaufrufe'), 'bounce' => __('Absprungrate'), 'dur' => __('Zeit je Seite'), 'ppv' => __('Seiten je Besuch'),
            default => __('Besucher'),
        };
    }

    /** Kennzahl formatiert (Kacheln) */
    public static function format(string $m, int|float|null $v): string
    {
        if ($v === null) return '–';
        return match ($m) {
            'bounce' => fmt()->number($v) . ' %',
            'dur' => $v >= 60 ? sprintf('%d:%02d min', intdiv((int) $v, 60), (int) $v % 60) : (int) $v . ' s',
            'ppv' => fmt()->number($v, 1),
            default => fmt()->number($v),
        };
    }

    /** Lesbare Bezeichnung eines Schlüssels */
    public static function label(string $dim, string $k): string
    {
        $p = explode('|', $k);
        return match ($dim) {
            'page', 'entry' => Narrative::pageName($k),
            'ref' => $k === '' ? __('(ohne)') : $k,
            'campaign' => $p[0] . (($p[1] ?? '') !== '' || ($p[2] ?? '') !== '' ? ' (' . implode(' / ', array_filter([$p[1] ?? '', $p[2] ?? ''])) . ')' : ''),
            'region' => Geo::name($p[1] ?? $k) . (isset($p[0]) && $p[0] !== '' ? ', ' . Report::countryName($p[0]) : ''),
            'city' => Geo::name($p[2] ?? $k) . (($p[1] ?? '') !== '' ? ', ' . Geo::name($p[1]) : ''),
            'event' => Narrative::eventLabel($k),
            'eprop' => Narrative::eventLabel($p[0]) . ': ' . ($p[1] ?? ''),
            'browser', 'os', 'lang' => $k === '' ? __('Unbekannt') : Report::label($dim, $k),
            default => Report::label($dim, $k),
        };
    }

    /** Wert für den Filter, den die Zeile setzt (null = keiner) */
    public static function filterValue(string $dim, string $k): ?string
    {
        if (!isset(self::FILTER[$dim]) || $k === '') return null;
        return $dim === 'campaign' ? explode('|', $k)[0] : $k;
    }

    /**
     * Zeilen einer Tabelle: label, key, a, b, share (% von a), change (% gegenüber Vorzeitraum), filter
     * @return array{rows: list<array>, total: int}
     */
    public static function rows(array $cur, array $prev, string $dim, int $limit = 10): array
    {
        $list = $cur['dims'][$dim] ?? [];
        if ($dim === 'ref') unset($list['']);
        $sum = array_sum(array_column($list, 0));
        $rows = [];
        foreach (array_slice($list, 0, $limit, true) as $k => [$a, $b]) {
            $k = (string) $k;
            $before = $prev['dims'][$dim][$k][0] ?? 0;
            $rows[] = ['key' => $k, 'label' => self::label($dim, $k), 'a' => $a, 'b' => $b, 'share' => $sum ? $a / $sum * 100 : 0,
                'change' => $before > 0 ? ($a - $before) / $before * 100 : null, 'filter' => self::filterValue($dim, $k)];
        }
        return ['rows' => $rows, 'total' => count($list)];
    }

    /**
     * „Die Zahlen dazu“ im Bericht (Seite 2 von Druck und PDF): kompakte Tabellen – meistbesuchte Seiten, Herkunft, Kampagnen und Aktionen
     * (die beiden letzten nur, wenn es welche gibt). @return list<array{dim: string, title: string, col: string, rows: list<array>}>
     */
    public static function appendix(array $cur, array $prev): array
    {
        $out = [];
        foreach (['page' => 7, 'channel' => 6, 'campaign' => 5, 'event' => 6] as $dim => $n) {
            $res = self::rows($cur, $prev, $dim, $n);
            if (!$res['rows']) continue;
            $out[] = ['dim' => $dim, 'title' => $dim === 'page' ? __('Meistbesuchte Seiten') : self::title($dim), 'col' => self::columns($dim)[0], 'rows' => $res['rows']];
        }
        return $out;
    }

    /** Diagramm des Verlaufs einer Kennzahl (Tage) mit Vorzeitraum */
    public static function chart(array $cur, array $prev, string $m): array
    {
        $days = $cur['days'];
        $labels = array_map(fn($d) => date('d.m.', strtotime($d)), array_keys($days));
        $series = [['name' => self::metricLabel($m), 'data' => array_map(fn($d) => Query::metric($d, $m), array_values($days))]];
        $before = array_map(fn($d) => Query::metric($d, $m), array_values($prev['days']));
        if (array_filter($before, fn($v) => $v !== null && $v > 0)) $series[] = ['name' => __('Zeitraum davor'), 'data' => array_slice($before, 0, count($days))];
        return ['type' => count($days) > 1 ? 'line' : 'bar', 'labels' => $labels, 'series' => $series, 'height' => 260,
            'format' => match ($m) { 'bounce' => 'percent', 'dur' => 'duration', default => 'number' },
            'description' => __('{metric} je Tag im Vergleich zum Zeitraum davor', ['metric' => self::metricLabel($m)])];
    }
}
