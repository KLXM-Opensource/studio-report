<?php /** Handbuch · Kapitel „Report“ (Erweiterung report) */ ?>
  <p class="lead">Der <b>Report</b> zeigt, wie Ihre Website genutzt wird – als kurzer Bericht in Worten (was gut lief, woher die Besucher kamen, welche Kampagne etwas gebracht hat, was Sie tun können) und, für alle, die selbst nachsehen möchten, als <b>Zahlen</b> zum Durchklicken.</p>
  <h3>Report ansehen</h3>
  <ul>
    <li>Menü <b>Report</b>: oben Zeitraum (letzte 7 oder 30 Tage, dieser oder letzter Monat, 90 Tage, dieses Jahr, eigener Zeitraum) und Filter (Seite, Herkunft, Kampagne, Gerät, Land).</li>
    <li>Der Bericht vergleicht immer mit dem gleich langen Zeitraum davor: „deutlich mehr (+23 %)“ heißt, dass es im Vergleich klar mehr waren.</li>
    <li>Der <b>Bericht</b> ist gesetzt wie eine Magazinseite: Schlagzeile, kurzer Vorspann, die wichtigsten Zahlen groß, ein hervorgehobener Satz und die Abschnitte Verlauf, Inhalte, Herkunft, Geräte, Standorte und Aktionen.</li>
    <li><b>PDF herunterladen</b> erzeugt daraus eine Datei mit höchstens zwei DIN-A4-Seiten – Seite 2 mit „Die Zahlen dazu“ (meistbesuchte Seiten, Herkunft, Kampagnen, Aktionen) – zum Ablegen oder Weitergeben, mit allen Diagrammen (hell, auch wenn die Verwaltung dunkel eingestellt ist). <b>Drucken</b> ergibt dieselben zwei Seiten. Der Knopf „PDF herunterladen“ erscheint, sobald auf dem Server die PDF-Engine installiert ist (Paket „PDF-Engine (dompdf)“).</li>
    <li>Filter gelten für die letzten 30 Tage; ältere Zeiträume liegen nur als Tagessummen vor.</li>
  </ul>
  <h3>Zahlen zum Durchklicken</h3>
  <ul>
    <li><b>Report → Zahlen</b> zeigt dieselben Daten als klassische Statistik: oben sechs Kennzahlen (Besucher, Besuche, Seitenaufrufe, Absprungrate, Zeit je Seite, Seiten je Besuch) mit Veränderung zum Zeitraum davor. Ein Klick auf eine Kennzahl zeigt deren Verlauf je Tag.</li>
    <li>Darunter Tabellen: Seiten, Einstiegsseiten, Herkunft, verweisende Websites, Kampagnen, Geräte, Browser, Betriebssysteme, Sprachen, Länder, Regionen, Städte und Aktionen – jeweils mit Anteil und Veränderung. „Alle … anzeigen“ öffnet die ganze Liste.</li>
    <li><b>Klick auf eine Seite, eine Herkunft, eine Kampagne, ein Gerät oder ein Land</b> filtert alles danach – z. B. „Suchmaschinen“: Welche Seiten finden Besucher über Google? Gesetzte Filter stehen als Marken oben und lassen sich mit einem Klick wieder entfernen. Sie gelten auch im Bericht.</li>
  </ul>
  <h3>Was gezählt wird – und was nicht</h3>
  <ul>
    <li>Gezählt werden Seitenaufrufe, Besuche und Besucher (je Tag), Herkunft (Suchmaschine, direkt, soziale Netzwerke, andere Websites, Kampagnen), Gerät, Browser, System, Sprache und ungefähr der Standort (Land, Bundesland, Stadt).</li>
    <li><b>Ohne Cookies</b> und ohne Speicher im Browser. IP-Adressen werden nicht gespeichert. Ein Besucher ist nur innerhalb eines Tages wiederzuerkennen, nie über mehrere Tage.</li>
    <li>Nicht gezählt: angemeldete Personen (die Redaktion), Suchmaschinen-Roboter, automatisierte Browser (z. B. Test- und Crawler-Programme) und – wenn eingestellt – Besucher mit „Do Not Track“.</li>
    <li>Städte sind ungefähr: Im Mobilfunk erscheint oft der Ort des Netzknotens.</li>
  </ul>
  <h3>Aktionen und Kampagnen</h3>
  <ul>
    <li>Automatisch gezählt: abgeschickte Formulare, Downloads (auch PDFs), Links zu anderen Websites, E-Mail- und Telefon-Links.</li>
    <li>Eigene Aktionen ohne Programmierung: Ihre Agentur setzt an einen Link oder Button <code>data-report="Name"</code>.</li>
    <li>Kampagnen: Links mit <code>?utm_campaign=herbst</code> (z. B. im Newsletter) oder kurz <code>?k=herbst</code>. Der Report zeigt, wie viele Besuche eine Kampagne brachte und was daraus wurde (z. B. Anfragen).</li>
  </ul>
  <h3>Einstellungen</h3>
  <p>Unter <b>Report → Einstellungen</b> (Recht „Report einstellen“): Genauigkeit des Standorts, „Do Not Track“, Aufbewahrung der Tagessummen, Pfade, die nicht gezählt werden, die GeoIP-Datenbank und ein Textbaustein für die Datenschutzerklärung.</p>
