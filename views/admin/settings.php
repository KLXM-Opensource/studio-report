<?php
/** Verwaltung → Report → Einstellungen: Standort, „Do Not Track“, Aufbewahrung, Ausschlüsse, GeoIP, Text für die Datenschutzerklärung. @var array $cfg */
use Core\AdminUi;
use Klxm\Report\Geo;
use Klxm\Report\Report;

$geo = ['city' => __('Land, Bundesland und Stadt'), 'region' => __('Land und Bundesland'), 'country' => __('Nur Land'), 'off' => __('Kein Standort')];
$updated = Geo::updated();
$privacy = __('Diese Website zählt Besuche ohne Cookies und ohne Speicher in Ihrem Browser. Bei jedem Seitenaufruf werden Seite, verweisende Website (nur die Adresse der Website), Kampagnen-Kennzeichen, Gerätetyp, Browser, Betriebssystem, Sprache und – aus Ihrer IP-Adresse ermittelt – ungefähr Land, Region und Stadt erfasst. Die IP-Adresse wird nicht gespeichert. Um Besuche eines Tages zusammenzufassen, wird aus IP-Adresse und Browserkennung eine Kennung gebildet, die nur für diesen Tag gilt und sich nicht zurückrechnen lässt; sie wird nach {days} Tagen gelöscht, danach bleiben nur Tagessummen ({months} Monate). Die Daten verlassen den Server nicht. Rechtsgrundlage ist unser berechtigtes Interesse an einer datensparsamen Auswertung (Art. 6 Abs. 1 lit. f DSGVO). „Do Not Track“ bzw. Global Privacy Control werden respektiert.', ['days' => Report::RAW_DAYS, 'months' => $cfg['keep_months']]);
?>
<form method="post" action="<?= e(url('/admin/report/einstellungen')) ?>" class="adm-panel--groups" novalidate>
  <?= csrf_field() ?>
  <?= AdminUi::group(__('Zählung'), [
    AdminUi::row(__('Standort'), '<select id="rp-geo" name="geo">' . implode('', array_map(fn($k, $l) => '<option value="' . e($k) . '"' . ($cfg['geo'] === $k ? ' selected' : '') . '>' . e($l) . '</option>', array_keys($geo), $geo)) . '</select>',
      __('Aus der IP-Adresse im Moment des Aufrufs ermittelt – gespeichert werden nur die Namen. Städte sind ungefähr (Mobilfunk: oft der Ort des Netzknotens).'), ['for' => 'rp-geo']),
    AdminUi::row(__('„Do Not Track“ und GPC respektieren'), '<input type="checkbox" role="switch" name="dnt" value="1"' . ($cfg['dnt'] ? ' checked' : '') . '>', __('Wer im Browser „nicht verfolgen“ eingestellt hat, wird nicht gezählt.')),
    AdminUi::row(__('Tagessummen aufbewahren (Monate)'), '<input id="rp-keep" type="number" name="keep_months" min="1" max="60" value="' . (int) $cfg['keep_months'] . '">',
      __('Rohdaten mit der Tages-Kennung werden immer nach {n} Tagen gelöscht.', ['n' => Report::RAW_DAYS]), ['for' => 'rp-keep']),
    AdminUi::row(__('Nicht zählen'), '<textarea id="rp-ex" name="exclude" rows="3" placeholder="/intern*">' . e($cfg['exclude']) . '</textarea>',
      __('Pfade, je Zeile; * am Ende für alle Unterseiten. Angemeldete Personen werden nie gezählt.'), ['for' => 'rp-ex', 'stack' => true]),
  ]) ?>
  <?= AdminUi::savebar() ?>
</form>

<?= AdminUi::group(__('GeoIP-Datenbank'), [
    AdminUi::row(__('Stand'), $updated ? e(fmt()->date($updated)) : '<span class="adm-badge adm-badge--adm-warn">' . e(__('fehlt')) . '</span>',
      __('„IP to City Lite“ von DB-IP (CC BY 4.0), einmal je Installation, monatlich neu über System → Geplante Aufgaben (report:geoip)')),
    AdminUi::row(__('Jetzt laden'), '<form method="post" action="' . e(url('/admin/report/einstellungen')) . '">' . csrf_field() . '<input type="hidden" name="do" value="geoip"><button class="adm-btn adm-btn--small" type="submit">' . e($updated ? __('Aktualisieren') : __('Laden')) . '</button></form>', __('Etwa 130 MB, dauert einen Moment.')),
], '') ?>

<?= AdminUi::group(__('Für die Datenschutzerklärung'), [
    AdminUi::row(__('Textbaustein'), '<textarea readonly rows="7" class="rp-privacy" aria-label="' . e(__('Textbaustein')) . '">' . e($privacy) . '</textarea>', __('Bitte vor dem Einsatz rechtlich prüfen.'), ['stack' => true]),
]) ?>

<?= AdminUi::group(__('Aktionen zählen'), [
    AdminUi::row(__('Automatisch'), '', __('Formular abgeschickt, Download (auch PDF), Link nach außen, E-Mail- und Telefon-Link.')),
    AdminUi::row(__('Ohne Code'), '<code>data-report="Newsletter"</code>', __('An einen Link oder Button – zählt jeden Klick unter diesem Namen.')),
    AdminUi::row(__('Kampagnen'), '<code>?utm_campaign=herbst</code> · <code>?k=herbst</code>', __('Links mit Kennzeichen; der Report zeigt Besuche und Ziele je Kampagne.')),
]) ?>
