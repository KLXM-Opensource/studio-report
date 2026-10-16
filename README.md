# KLXM Studio – Report

Datensparsame Besucherauswertung **ohne Cookies** für [KLXM Studio](https://github.com/KLXM-Opensource/studio) – ausgewertet als
**gedeuteter Bericht** (eine DIN-A4-Seite je Zeitraum) statt als Statistik-Ansicht. Kein Nachbau großer Analyse-Werkzeuge:
keine Profile, keine Wege über mehrere Tage, keine Daten an Dritte.

## Was es kann

- Seitenaufrufe, Besuche und Besucher je Tag – ohne Cookie über einen Tages-Hash (Salz wechselt täglich, IP-Adressen werden nie gespeichert)
- Herkunft: Suchmaschinen, direkt, soziale Netzwerke, andere Websites, Kampagnen (`?utm_campaign=…` oder kurz `?k=…`)
- Gerät, Browser, System, Sprache, Standort (Land, Bundesland, Stadt – DB-IP Lite, einstellbar bis „kein Standort“)
- Aktionen: Formular abgeschickt, Download, Link nach außen, E-Mail-/Telefon-Link, eigene per `data-report="Name"` oder `window.cmsReport('Name')`
- Ziele je Kampagne (z. B. Anfragen aus dem Newsletter)
- **Report**: Zeitraum und Filter wählen, Bericht in verständlicher Sprache im Stil eines Magazins (Schlagzeile, Vorspann, Kennzahlen, Diagramme, Hinweise); Drucken auf einer Seite, **PDF herunterladen** über `Core\Pdf` (mit Paket `klxm/studio-pdf`)
- **Zahlen**: klassische Statistik zum Durchklicken – Kennzahlen, Verlauf, Tabellen mit Anteil und Veränderung, Klick auf eine Zeile filtert
- Kachel und Karte in der Übersicht, Datenquellen für den Block „Diagramm“ (nur Summen), Textbaustein für die Datenschutzerklärung

## Installation

```bash
composer require klxm/studio-report         # oder Repository nach extensions/report klonen und dort: composer install --no-dev
php bin/console extensions:publish
php bin/console report:geoip                # GeoIP-Datenbank (≈ 130 MB, einmal je Installation)
```

Aktivieren: Administration → Funktionen & Erweiterungen (Erweiterung und Funktion „Report“, Standard aus).
Geplante Aufgaben (Manifest `schedule`, Kern: System → Geplante Aufgaben, ein Cronjob `php bin/console cron:run`): `report:rollup` täglich auf jeder Website mit Report, `report:geoip` monatlich einmal je Installation.

## Datenschutz

Gezählt wird ohne Zugriff auf das Endgerät (keine Cookies, kein localStorage). Rohdaten mit Tages-Kennung werden nach 30 Tagen
gelöscht, danach bleiben Tagessummen. Angemeldete Personen, Bots und – standardmäßig – „Do Not Track“/GPC werden nicht gezählt.
Ein Textbaustein für die Datenschutzerklärung steht unter Report → Einstellungen (bitte rechtlich prüfen).

## Lizenz

MIT. Abhängigkeit: `maxmind-db/reader` (Apache-2.0). GeoIP-Daten: [DB-IP](https://db-ip.com) „IP to City Lite“, CC BY 4.0 –
die Quellenangabe erscheint im Report.
