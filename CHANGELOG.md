# Changelog – Report

## 0.1.0

- Besucher ohne Cookies zählen: Seitenaufrufe, Besuche, Besucher je Tag (Tages-Hash mit täglich neuem Salz, keine IP-Adressen), Herkunft (Suchmaschinen, direkt, soziale Netzwerke, andere Websites, Kampagnen über `utm_*` bzw. `?k=`), Gerät, Browser, System, Sprache, Standort (DB-IP Lite: Land, Bundesland, Stadt).
- Ereignisse: Formular abgeschickt (Server), Download, Link nach außen, E-Mail- und Telefon-Link, eigene über `data-report="Name"` oder `window.cmsReport()`; Ziele je Kampagne.
- **Report** statt Statistik-Ansicht: gedeuteter Bericht in Worten für einen Zeitraum (mit Vergleich zum Zeitraum davor), Filter, Kennzahlen, Diagramme (`Core\Charts`), Hinweise – druckbar auf einer DIN-A4-Seite. Deutsch und Englisch.
- Rohdaten 30 Tage, danach Tagessummen (Standard 25 Monate); `report:rollup`, `report:geoip`, `report:selftest`.
- Kachel und Karte in der Übersicht, Datenquellen für den Block „Diagramm“, Textbaustein für die Datenschutzerklärung.
