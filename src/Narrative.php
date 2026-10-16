<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Report. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Report;

/**
 * Der Report in Worten: Zahlen werden verglichen (Vorzeitraum), eingeordnet und in verständliche Sätze gefasst – nach festen
 * Regeln, ohne KI (nachvollziehbar, in jeder Verwaltungssprache über __()). Ergebnis für views/admin/report.php:
 *   headline (Schlagzeile), kicker (Ausgabe, z. B. „Oktober 2026“), lead (2–3 Sätze), highlight (hervorgehobener Satz),
 *   figures (Kennzahlen mit Veränderung), sections (Titel, Text, Diagramm, Liste), tips (Hinweise).
 */
final class Narrative
{
    private array $cur;
    private array $prev;
    private array $t;
    private array $pt;
    /** @var list<array{0:int,1:string}> Kandidaten für den hervorgehobenen Satz [Gewicht, Satz] */
    private array $pulls = [];

    public function __construct(array $cur, array $prev)
    {
        $this->cur = $cur; $this->prev = $prev;
        $this->t = Query::totals($cur); $this->pt = Query::totals($prev);
    }

    // ------------------------------------------------------------------ Bausteine

    private static function n(int|float|null $v, int $d = 0): string
    {
        return fmt()->number($v ?? 0, $d);
    }

    /** Veränderung in Prozent (null = kein Vergleich möglich) */
    private static function pct(int|float|null $now, int|float|null $before): ?float
    {
        if ($before === null || $now === null || $before == 0) return null;
        return ($now - $before) / $before * 100;
    }

    /** „deutlich mehr (+23 %)“ – Einordnung einer Veränderung */
    private static function trend(?float $p, string $more, string $less, string $same): string
    {
        if ($p === null) return '';
        $a = abs($p);
        $sign = ($p >= 0 ? '+' : '−') . fmt()->number($a, $a < 10 ? 1 : 0) . ' %';
        if ($a < 5) return $same;
        $word = $a < 20 ? __('etwas') : ($a < 50 ? __('deutlich') : __('viel'));
        return __('{word} {dir} ({sign})', ['word' => $word, 'dir' => $p > 0 ? $more : $less, 'sign' => $sign]);
    }

    private function share(string $dim, string $k): float
    {
        $sum = array_sum(array_column($this->cur['dims'][$dim] ?? [], 0));
        return $sum ? ($this->cur['dims'][$dim][$k][0] ?? 0) / $sum * 100 : 0;
    }

    private function top(string $dim, int $n = 5): array
    {
        return array_slice($this->cur['dims'][$dim] ?? [], 0, $n, true);
    }

    /** Lesbarer Name einer Seite (Titel aus dem Seitenbaum, sonst Pfad) */
    public static function pageName(string $path): string
    {
        static $cache = [];
        if (isset($cache[$path])) return $cache[$path];
        $title = '';
        try {
            $title = (string) (app()->db->fetchValue("SELECT title FROM pages WHERE path = ? AND status = 'published' LIMIT 1", [ltrim($path, '/')]) ?: '');
            if ($title === '' && $path === '/') $title = (string) (app()->db->fetchValue("SELECT title FROM pages WHERE is_home = 1 LIMIT 1") ?: '');
        } catch (\Throwable) {
        }
        return $cache[$path] = $title !== '' ? $title : $path;
    }

    private static function date(string $d, string $style = 'short'): string
    {
        return fmt()->date($d, $style);
    }

    /** Zeitraum lesbar: „1. bis 31. Oktober 2026“ bzw. „12.09. bis 11.10.2026“ */
    public static function rangeLabel(string $from, string $to): string
    {
        if ($from === $to) return self::date($from, 'long');
        $sameYear = substr($from, 0, 4) === substr($to, 0, 4);
        return __('{from} bis {to}', ['from' => self::date($from, $sameYear ? 'day_month' : 'long'), 'to' => self::date($to, 'long')]);
    }

    // ------------------------------------------------------------------ Report

    public function build(): array
    {
        $t = $this->t; $pt = $this->pt;
        $kicker = self::edition($this->cur['from'], $this->cur['to']);
        if (!$t['pv']) return ['empty' => true, 'kicker' => $kicker, 'headline' => __('Noch keine Besuche gezählt'), 'highlight' => null,
            'lead' => [__('In diesem Zeitraum wurden keine Besuche gezählt.')], 'figures' => [], 'sections' => [], 'tips' => []];
        $sections = array_values(array_filter([$this->course(), $this->content(), $this->sources(), $this->devices(), $this->places(), $this->events()]));
        usort($this->pulls, fn($a, $b) => $b[0] <=> $a[0]);
        return ['empty' => false, 'kicker' => $kicker, 'headline' => $this->headline(), 'lead' => $this->lead(), 'highlight' => $this->pulls[0][1] ?? null,
            'figures' => $this->figures(), 'sections' => $sections, 'tips' => $this->tips()];
    }

    /** Ausgabe: ganzer Monat → „Oktober 2026“, sonst der Zeitraum */
    public static function edition(string $from, string $to): string
    {
        $monthEnd = date('Y-m-t', strtotime($from));
        if (substr($from, 8) === '01' && ($to === $monthEnd || ($to === date('Y-m-d') && substr($to, 0, 7) === substr($from, 0, 7))))
            return (string) preg_replace('~^\d+\.?\s+~u', '', self::date($from, 'long'));
        return self::rangeLabel($from, $to);
    }

    /** Schlagzeile: Richtung der Besucherzahl und – falls deutlich – der Treiber (Kanal, Anfragen) */
    private function headline(): string
    {
        $t = $this->t; $pt = $this->pt;
        if (!$pt['uv']) return __('Der erste Überblick: {n} Besucher', ['n' => self::n($t['uv'])]);
        $p = self::pct($t['uv'], $pt['uv']) ?? 0.0;
        $main = $p >= 20 ? __('Deutlich mehr Besucher') : ($p >= 5 ? __('Mehr Besucher') : ($p <= -20 ? __('Deutlich weniger Besucher') : ($p <= -5 ? __('Weniger Besucher') : __('Stabile Besucherzahlen'))));
        // Treiber: größte Veränderung eines Kanals in Richtung der Gesamtentwicklung
        $best = null; $bestDiff = 0;
        foreach (array_unique(array_merge(array_keys($this->cur['dims']['channel'] ?? []), array_keys($this->prev['dims']['channel'] ?? []))) as $k) {
            $d = ($this->cur['dims']['channel'][$k][0] ?? 0) - ($this->prev['dims']['channel'][$k][0] ?? 0);
            if (($p >= 0 ? $d : -$d) > $bestDiff) { $bestDiff = abs($d); $best = (string) $k; }
        }
        $forms = $this->eventCount(['Formular abgeschickt']);
        $formsBefore = (int) ($this->prev['dims']['event']['Formular abgeschickt'][0] ?? 0);
        $driver = '';
        if ($forms >= 2 && $forms > $formsBefore * 1.2) $driver = __('mehr Anfragen');
        elseif ($best !== null && abs($p) >= 5 && $bestDiff >= max(5, $t['visits'] * 0.05)) {
            $driver = $p > 0
                ? (['search' => __('Suchmaschinen bringen mehr'), 'social' => __('soziale Netzwerke bringen mehr'), 'campaign' => __('Kampagnen wirken'),
                    'referral' => __('andere Websites verweisen öfter'), 'direct' => __('mehr direkte Besuche')][$best] ?? '')
                : __('Rückgang vor allem {channel}', ['channel' => self::channelPhrase($best)]);
        }
        return $driver !== '' ? $main . ' – ' . $driver : $main;
    }

    private function lead(): array
    {
        $t = $this->t; $pt = $this->pt;
        $out = [];
        $p = self::pct($t['uv'], $pt['uv'] ?: null);
        $out[] = $p === null
            ? __('{uv} Besucher kamen auf die Website und sahen sich {pv} Seiten an.', ['uv' => self::n($t['uv']), 'pv' => self::n($t['pv'])])
            : __('{uv} Besucher kamen auf die Website – {trend} als im Zeitraum davor. Zusammen sahen sie sich {pv} Seiten an.',
                ['uv' => self::n($t['uv']), 'pv' => self::n($t['pv']), 'trend' => self::trend($p, __('mehr'), __('weniger'), __('etwa so viele'))]);
        // Wichtigster Treiber: Kanal mit der größten Zunahme bzw. die stärkste Kampagne
        $best = null; $bestDiff = 0;
        foreach ($this->cur['dims']['channel'] ?? [] as $k => [$a]) {
            $d = $a - ($this->prev['dims']['channel'][$k][0] ?? 0);
            if ($d > $bestDiff) { $bestDiff = $d; $best = $k; }
        }
        if ($best && $p !== null && $p > 5 && $bestDiff >= max(5, $t['visits'] * 0.05)) {
            $out[] = __('Den größten Zuwachs brachten {channel} (+{n} Besuche).', ['channel' => Report::label('channel', $best), 'n' => self::n($bestDiff)]);
        }
        $forms = $this->eventCount(['Formular abgeschickt']);
        if ($forms) $out[] = $forms === 1 ? __('Ein Formular wurde abgeschickt.') : __('{n} Formulare wurden abgeschickt.', ['n' => self::n($forms)]);
        return $out;
    }

    private function figures(): array
    {
        $t = $this->t; $pt = $this->pt;
        $f = fn(string $label, $v, string $txt, ?float $p, bool $lowerIsBetter = false) => ['label' => $label, 'value' => $txt, 'pct' => $p, 'good' => $p === null ? null : ($lowerIsBetter ? $p < 0 : $p > 0)];
        return [
            $f(__('Besucher'), $t['uv'], self::n($t['uv']), self::pct($t['uv'], $pt['uv'] ?: null)),
            $f(__('Besuche'), $t['visits'], self::n($t['visits']), self::pct($t['visits'], $pt['visits'] ?: null)),
            $f(__('Seitenaufrufe'), $t['pv'], self::n($t['pv']), self::pct($t['pv'], $pt['pv'] ?: null)),
            $f(__('Zeit je Seite'), $t['dur'], $t['dur'] === null ? '–' : ($t['dur'] >= 60 ? sprintf('%d:%02d min', intdiv($t['dur'], 60), $t['dur'] % 60) : $t['dur'] . ' s'), self::pct($t['dur'], $pt['dur'])),
        ];
    }

    private function course(): array
    {
        $days = $this->cur['days'];
        $prevDays = array_values($this->prev['days']);
        $peakDay = array_key_first(array_filter($days, fn($d) => $d['uv'] === max(array_column($days, 'uv'))));
        $text = [__('Am meisten los war am {day} mit {n} Besuchern.', ['day' => self::date($peakDay, 'weekday') . ', ' . self::date($peakDay), 'n' => self::n($days[$peakDay]['uv'])])];
        // Wochentage (ab 14 Tagen aussagekräftig)
        if (count($days) >= 14) {
            $wd = [];
            foreach ($days as $d => $v) { $w = (int) date('N', strtotime($d)); $wd[$w][] = $v['uv']; }
            $avg = array_map(fn($l) => array_sum($l) / count($l), $wd);
            arsort($avg);
            $all = array_sum($avg) / count($avg);
            $topWd = array_key_first($avg);
            if ($all > 0 && $avg[$topWd] > $all * 1.2) {
                $text[] = __('Stärkster Wochentag ist der {weekday}: dann kommen im Schnitt {p} % mehr Besucher als an anderen Tagen.',
                    ['weekday' => self::date(date('Y-m-d', strtotime('monday this week +' . ($topWd - 1) . ' days')), 'weekday'), 'p' => self::n(($avg[$topWd] / $all - 1) * 100)]);
            }
            $we = array_sum(array_intersect_key($avg, [6 => 1, 7 => 1])) / 2;
            $wk = array_sum(array_intersect_key($avg, array_flip([1, 2, 3, 4, 5]))) / 5;
            if ($wk > 0 && $we < $wk * 0.6) $text[] = __('Am Wochenende ist es deutlich ruhiger.');
        }
        $labels = array_map(fn($d) => date(count($days) > 62 ? 'd.m.' : 'd.m.', strtotime($d)), array_keys($days));
        $series = [['name' => __('Besucher'), 'data' => array_column(array_values($days), 'uv')]];
        if (array_sum(array_column($prevDays, 'uv')) > 0) $series[] = ['name' => __('Zeitraum davor'), 'data' => array_slice(array_column($prevDays, 'uv'), 0, count($days))];
        return ['id' => 'verlauf', 'title' => __('Verlauf'), 'text' => $text,
            'chart' => ['type' => 'line', 'labels' => $labels, 'series' => $series, 'height' => 200, 'description' => __('Besucher je Tag im Vergleich zum Zeitraum davor')]];
    }

    private function content(): ?array
    {
        $top = $this->top('page', 5);
        if (!$top) return null;
        $keys = array_keys($top);
        $first = $keys[0];
        $text = [__('Am häufigsten aufgerufen wurde „{page}“ ({p} % aller Seitenaufrufe).', ['page' => self::pageName($first), 'p' => self::n($this->share('page', $first))])];
        $this->pulls[] = [1, $text[0]];
        if (count($keys) > 2) $text[] = __('Dahinter folgen „{a}“ und „{b}“.', ['a' => self::pageName($keys[1]), 'b' => self::pageName($keys[2])]);
        // Aufsteiger: größte Zunahme gegenüber dem Zeitraum davor (mindestens 10 Aufrufe)
        $rise = null; $rp = 0;
        foreach ($this->cur['dims']['page'] ?? [] as $k => [$a]) {
            $before = $this->prev['dims']['page'][$k][0] ?? 0;
            if ($a >= 10 && $before > 0 && ($p = ($a - $before) / $before * 100) > max(50, $rp)) { $rise = $k; $rp = $p; }
            if ($a >= 10 && $before === 0 && !$rise && ($this->prev['dims']['total'][''][0] ?? 0) > 0) { $rise = $k; $rp = INF; }
        }
        if ($rise && $rise !== $first) {
            $text[] = is_infinite($rp)
                ? __('Neu gefragt: „{page}“ mit {n} Aufrufen.', ['page' => self::pageName($rise), 'n' => self::n($this->cur['dims']['page'][$rise][0])])
                : __('Aufsteiger: „{page}“ wurde {p} % häufiger aufgerufen als zuvor.', ['page' => self::pageName($rise), 'p' => self::n($rp)]);
            $this->pulls[] = [2, end($text)];
        }
        return ['id' => 'inhalte', 'title' => __('Inhalte'), 'text' => $text,
            'chart' => ['type' => 'hbar', 'labels' => array_map([self::class, 'pageName'], $keys), 'series' => [['name' => __('Aufrufe'), 'data' => array_column(array_values($top), 0)]],
                'height' => 40 * count($keys) + 40, 'description' => __('Die meistbesuchten Seiten')]];
    }

    private function sources(): ?array
    {
        $ch = $this->cur['dims']['channel'] ?? [];
        if (!$ch) return null;
        $parts = [];
        foreach (array_slice($ch, 0, 3, true) as $k => [$a]) $parts[] = __('{p} % {channel}', ['p' => self::n($this->share('channel', $k)), 'channel' => self::channelPhrase($k)]);
        $text = [__('Die Besucher kamen zu {list}.', ['list' => self::join($parts)])];
        $refs = array_slice($this->cur['dims']['ref'] ?? [], 0, 3, true);
        $refs = array_filter($refs, fn($k) => $k !== '', ARRAY_FILTER_USE_KEY);
        if ($refs) $text[] = __('Wichtigste verweisende Websites: {list}.', ['list' => self::join(array_map(fn($k, $v) => $k . ' (' . self::n($v[0]) . ')', array_keys($refs), $refs))]);
        // Kampagnen mit Zielen
        foreach (array_slice($this->cur['dims']['campaign'] ?? [], 0, 3, true) as $k => [$a]) {
            [$name] = explode('|', $k);
            $goals = [];
            foreach ($this->cur['dims']['goal'] ?? [] as $gk => [$ga]) {
                $ev = substr((string) $gk, strlen($name) + 1);
                if (str_starts_with((string) $gk, $name . '|') && $ev !== 'Link nach außen') $goals[] = self::n($ga) . ' × ' . self::eventLabel($ev);   // Ziele, keine Absprünge nach außen
            }
            $text[] = $goals
                ? __('Die Kampagne „{c}“ brachte {n} Besuche – daraus {goals}.', ['c' => $name, 'n' => self::n($a), 'goals' => self::join($goals)])
                : __('Die Kampagne „{c}“ brachte {n} Besuche.', ['c' => $name, 'n' => self::n($a)]);
            if ($goals) $this->pulls[] = [3, end($text)];
        }
        return ['id' => 'herkunft', 'title' => __('Herkunft'), 'text' => $text,
            'chart' => ['type' => 'donut', 'labels' => array_map(fn($k) => Report::label('channel', (string) $k), array_keys($ch)), 'series' => [['name' => __('Besuche'), 'data' => array_column(array_values($ch), 0)]],
                'height' => 200, 'description' => __('Besuche nach Herkunft')]];
    }

    private static function channelPhrase(string $k): string
    {
        return ['campaign' => __('über Kampagnen'), 'search' => __('über Suchmaschinen'), 'social' => __('aus sozialen Netzwerken'), 'referral' => __('über andere Websites'), 'direct' => __('direkt')][$k] ?? $k;
    }

    private function devices(): ?array
    {
        $dv = $this->cur['dims']['device'] ?? [];
        if (!$dv) return null;
        $mobile = $this->share('device', 'mobile');
        $text = [__('{p} % der Besuche kamen vom Smartphone, {d} % vom Computer.', ['p' => self::n($mobile), 'd' => self::n($this->share('device', 'desktop'))])];
        if ($mobile >= 55) $text[] = __('Die Darstellung auf dem Smartphone ist damit die wichtigste.');
        $br = array_key_first($this->cur['dims']['browser'] ?? []);
        $os = array_slice($this->cur['dims']['os'] ?? [], 0, 2, true);
        if ($br) $text[] = __('Häufigster Browser: {b}; Systeme vor allem {os}.', ['b' => Report::label('browser', (string) $br), 'os' => self::join(array_map(fn($k) => Report::label('os', (string) $k), array_keys($os)))]);
        return ['id' => 'geraete', 'title' => __('Geräte'), 'text' => $text,
            'chart' => ['type' => 'donut', 'labels' => array_map(fn($k) => Report::label('device', (string) $k), array_keys($dv)), 'series' => [['name' => __('Besuche'), 'data' => array_column(array_values($dv), 0)]],
                'format' => 'number', 'height' => 180, 'description' => __('Besuche nach Gerät')]];
    }

    private function places(): ?array
    {
        $co = array_filter($this->cur['dims']['country'] ?? [], fn($k) => $k !== '', ARRAY_FILTER_USE_KEY);
        if (!$co) return null;
        $first = array_key_first($co);
        $text = [__('{p} % der Besuche kamen aus {country}.', ['p' => self::n($this->share('country', (string) $first)), 'country' => Report::countryName((string) $first)])];
        $rg = array_slice($this->cur['dims']['region'] ?? [], 0, 3, true);
        if ($rg) $text[] = __('Die meisten aus {list}.', ['list' => self::join(array_map(fn($k) => Geo::name(explode('|', $k)[1] ?? $k), array_keys($rg)))]);
        $ct = array_slice($this->cur['dims']['city'] ?? [], 0, 3, true);
        if ($ct) $text[] = __('Häufigste Städte (ungefähr): {list}.', ['list' => self::join(array_map(fn($k) => Geo::name(explode('|', $k)[2] ?? $k), array_keys($ct)))]);
        $list = [];
        foreach (array_slice($this->cur['dims'][$rg ? 'region' : 'country'], 0, 5, true) as $k => [$a]) $list[] = [$rg ? Geo::name(explode('|', $k)[1] ?? $k) : Report::countryName((string) $k), self::n($a)];
        return ['id' => 'standorte', 'title' => __('Standorte'), 'text' => $text, 'list' => $list];
    }

    private function eventCount(array $names): int
    {
        $n = 0;
        foreach ($names as $name) $n += $this->cur['dims']['event'][$name][0] ?? 0;
        return $n;
    }

    public static function eventLabel(string $name): string
    {
        return __($name);
    }

    private function events(): ?array
    {
        $ev = $this->cur['dims']['event'] ?? [];
        if (!$ev) return null;
        $parts = [];
        foreach (array_slice($ev, 0, 6, true) as $k => [$a]) {
            $before = $this->prev['dims']['event'][$k][0] ?? 0;
            $p = self::pct($a, $before ?: null);
            $parts[] = self::n($a) . ' × ' . self::eventLabel((string) $k) . ($p !== null && abs($p) >= 20 ? ' (' . ($p > 0 ? '+' : '−') . self::n(abs($p)) . ' %)' : '');
        }
        $text = [__('Gezählte Aktionen: {list}.', ['list' => self::join($parts)])];
        $dl = array_filter($this->cur['dims']['eprop'] ?? [], fn($k) => str_starts_with((string) $k, 'Download|') || str_starts_with((string) $k, 'PDF geöffnet|'), ARRAY_FILTER_USE_KEY);
        if ($dl) $text[] = __('Am häufigsten heruntergeladen: „{file}“.', ['file' => explode('|', (string) array_key_first($dl), 2)[1]]);
        $conv = $this->t['visits'] ? $this->eventCount(['Formular abgeschickt']) / $this->t['visits'] * 100 : 0;
        if ($conv > 0) $text[] = __('Auf {n} Besuche kam ein abgeschicktes Formular.', ['n' => self::n(100 / $conv)]);
        return ['id' => 'aktionen', 'title' => __('Aktionen & Ziele'), 'text' => $text];
    }

    private function tips(): array
    {
        $t = $this->t;
        $tips = [];
        if ($t['bounce'] !== null && $t['bounce'] >= 70 && $t['visits'] >= 30) {
            $entry = array_key_first($this->cur['dims']['entry'] ?? []);
            $tips[] = __('{p} % der Besuche enden nach einer Seite. Ein deutlicher nächster Schritt auf „{page}“ (Link, Button, Kontakt) kann helfen.', ['p' => self::n($t['bounce']), 'page' => self::pageName((string) $entry)]);
        }
        if ($this->share('device', 'mobile') >= 55) $tips[] = __('Prüfen Sie neue Inhalte zuerst auf dem Smartphone – dort sind die meisten Besucher.');
        foreach ($this->cur['dims']['campaign'] ?? [] as $k => [$a]) {
            [$name] = explode('|', $k);
            $hasGoal = (bool) array_filter(array_keys($this->cur['dims']['goal'] ?? []), fn($g) => str_starts_with((string) $g, $name . '|'));
            if ($a >= 20 && !$hasGoal) { $tips[] = __('Die Kampagne „{c}“ bringt Besuche, aber noch keine Anfragen – lohnt ein Blick auf die Zielseite.', ['c' => $name]); break; }
        }
        if (($this->cur['dims']['channel']['search'][0] ?? 0) < $t['visits'] * 0.1 && $t['visits'] >= 50) $tips[] = __('Nur wenige Besucher kommen über Suchmaschinen – Seitentitel und Beschreibungen (SEO-Übersicht) lohnen einen Blick.');
        return array_slice($tips, 0, 3);
    }

    private static function join(array $parts): string
    {
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        if (count($parts) < 2) return (string) ($parts[0] ?? '');
        $last = array_pop($parts);
        return implode(', ', $parts) . ' ' . __('und') . ' ' . $last;
    }
}
