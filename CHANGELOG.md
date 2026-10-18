# Changelog – Report

## 0.2.2

- Automatisierte Browser (Playwright, Puppeteer, Selenium, KI-Crawler mit gewöhnlicher Browserkennung) zählen nicht mehr: das Skript meldet nichts, wenn `navigator.webdriver` gesetzt ist.

## 0.2.1

- Sicherheit: Zählung nur von der eigenen Website (`Sec-Fetch-Site`), höchstens 600 Aufrufe je Anschluss und Stunde (IP nur als HMAC – eine wechselnde Browserkennung umgeht die Grenze nicht mehr) und 200.000 je Website und Tag.
- Tages-Salz: wird gleichzeitig nur einmal angelegt; Salze früherer Tage löscht auch die nächtliche Verdichtung (`report:rollup`), nicht erst der erste Besuch.

## 0.2.0

- Bericht im Stil eines Magazins, ganz in Lato (Black für Schlagzeile und Rubriken, Light für Zahlen und Zitat): Ausgabe („Oktober 2026“), Schlagzeile nach festen Regeln („Deutlich mehr Besucher – Kampagnen wirken“), Vorspann, große Kennzahlen, hervorgehobener Satz, Abschnitte in zwei Spalten, nummerierte Tipps und „Die Zahlen dazu“ (kompakte Tabellen). Druck und PDF auf höchstens zwei DIN-A4-Seiten. Das PDF ist gesetzt wie eine kleine Zeitung: Zeitungskopf mit Ausgabe, große Leitzahl, Zitat, Seite 2 „Im Detail“ mit Tipps im dunklen Kasten und „Die Zahlen dazu“, Seitenzahlen.
- **PDF herunterladen** über `Core\Pdf` (Paket `klxm/studio-pdf`): eine A4-Seite mit allen Diagrammen in heller Fassung.
- **Zahlen**: klassische Statistik zum Durchklicken – sechs Kennzahlen (Klick zeigt den Verlauf), Tabellen für Seiten, Einstiegsseiten, Herkunft, Verweise, Kampagnen, Geräte, Browser, Systeme, Sprachen, Länder, Regionen, Städte und Aktionen mit Anteil und Veränderung; Klick auf eine Zeile filtert.
- Geplante Aufgaben über den Kern (`Core\Cron`, Manifest `schedule`).

## 0.1.0

- Besucher ohne Cookies zählen: Seitenaufrufe, Besuche, Besucher je Tag (Tages-Hash mit täglich neuem Salz, keine IP-Adressen), Herkunft (Suchmaschinen, direkt, soziale Netzwerke, andere Websites, Kampagnen über `utm_*` bzw. `?k=`), Gerät, Browser, System, Sprache, Standort (DB-IP Lite: Land, Bundesland, Stadt).
- Ereignisse: Formular abgeschickt (Server), Download, Link nach außen, E-Mail- und Telefon-Link, eigene über `data-report="Name"` oder `window.cmsReport()`; Ziele je Kampagne.
- **Report** statt Statistik-Ansicht: gedeuteter Bericht in Worten für einen Zeitraum (mit Vergleich zum Zeitraum davor), Filter, Kennzahlen, Diagramme (`Core\Charts`), Hinweise – druckbar auf einer DIN-A4-Seite. Deutsch und Englisch.
- Rohdaten 30 Tage, danach Tagessummen (Standard 25 Monate); `report:rollup`, `report:geoip`, `report:selftest`.
- Kachel und Karte in der Übersicht, Datenquellen für den Block „Diagramm“, Textbaustein für die Datenschutzerklärung.
