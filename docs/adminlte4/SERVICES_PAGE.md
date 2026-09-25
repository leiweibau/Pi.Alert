# Webservices-Liste unter AdminLTE 4

Stand: 23. September 2026

`front/v4_services.php` portiert die Webservices-Liste auf die v4-Shell. Die
Seite besitzt eigene Assets unter `front/js/services.js` und
`front/css/services.css`; Bestandsseite, Backend, Queries und insbesondere
die Service-URL-Auflösung wurden nicht geändert.

## Route und DOM-Verträge

- Öffentlicher Einstieg: `v4_services.php`; eindeutiger Seitenmarker:
  `#services-page`.
- Die Journal-Tabelle behält `#servicesJournalTable`. Der GeoLite-Zustand ist
  über `#services-geodb-status`, die Kartengruppen über
  `#services-card-groups` und der Statusfilter über `#services-status-filter`
  prüfbar.
- Jede Karte trägt `[data-service-card]`, `data-service-state` und die URL in
  `data-service-url`; jede Gerätegruppe trägt `[data-service-group]`.
- Das Editor-Modal ist `#service-editor-modal`. Die übernommenen Feld-IDs
  `#serviceURL`, `#serviceTag`, `#serviceMAC`, `#insAlertEvents`,
  `#insAlertUp` und `#insAlertDown` bleiben vorhanden.
- Die v4-Shell-Navigation führt inzwischen auf `v4_services.php`.

## Erhaltene Darstellung und Leseverträge

- Services kommen aus `Services`, nach `mon_Tags COLLATE NOCASE` sortiert und
  wie bisher in Geräte sowie die Gruppe ohne MAC-Adresse gegliedert. Namen
  werden aus `Devices` bezogen; unbekannte MAC-Adressen erhalten den bisherigen
  Fallback.
- Jede Karte zeigt Protokoll, letzten HTTP-Status, URL, Tag, Ziel-IP,
  Benachrichtigungen und die letzten 18 Einträge aus `Services_Events` in
  chronologischer Leserichtung. Die Detailabfrage bleibt auf dieselbe URL,
  Sortierung und Höchstzahl begrenzt wie in `services.php`.
- Grün steht für 2xx, Warnung für andere von null verschiedene Codes und Rot
  für Status null beziehungsweise die Offline-Latenz `99999999`. Der neue
  clientseitige Filter blendet entsprechend alle, grüne, warnende oder
  ausgefallene Karten ein, ohne Daten erneut abzufragen.
- Das Journal lädt weiterhin alle 30 Sekunden lesend über
  `php/server/services.php?action=getServicesJournal`. URL und Zusatztext
  werden sicher als Text beziehungsweise aus kontrolliert erzeugten Links
  dargestellt.
- `GeoLite2-Country.mmdb` wird nur lesend auf Vorhandensein, Änderungszeit und
  Größe geprüft. Die Länderzuordnung bleibt bis zur Migration von
  `serviceDetails.php` in der bestehenden Detailseite; Karten und Journal
  verlinken URL-kodiert dorthin. Externe Links werden nur für HTTP und HTTPS
  erzeugt.

## Schreib- und Systemverträge

Alle Aktionen verwenden den gemeinsamen CSRF-geschützten `pialertPost` und den
unveränderten Endpunkt `php/server/services.php`:

| Bedienung | Aktion und Felder |
| --- | --- |
| Anlegen | `insertNewService`; `url`, `tags`, `mac`, `alertdown`, `alertup`, `alertevents` |
| Ändern | `setServiceData`; dieselben Felder, URL als unveränderlicher Schlüssel |
| Löschen | `deleteService`; `url`, nach Bestätigungsdialog |
| Monitoring umschalten | `EnableWebServiceMon`; nach Bestätigungsdialog |

Damit bleiben `_operation_id`, CSRF-Header und die Unterdrückung paralleler
identischer POSTs im gemeinsamen v4-Adapter erhalten. Die URL wird absichtlich
nicht im Browser aufgelöst oder vorab geprüft. `insertNewService` übernimmt
weiterhin vollständig die vorhandene Backendprüfung einschließlich der dort
implementierten IPv4-vor-IPv6-Auswahl; an `service_url.php`,
`php/server/services.php` oder einer anderen Backenddatei wurde nichts
geändert.

## Synthetische Chromium-Prüfung

Der wiederholbare Lauf liegt in
`_workspace/tests/adminlte4/browser_services.mjs`. Er wurde gegen die isolierte
Fixture-Kopie mit Chromium 153 bei 1440 × 1000 und 390 × 844 Pixeln ausgeführt.
Das Ergebnis und beide Screenshots liegen unter
`_workspace/adminlte4-browser-shell/services/`.

Beide Viewports blieben auf `v4_services.php`, fanden Seitenmarker,
DataTables-Journal, GeoDB-Karte und vier Servicekarten mit insgesamt 72
Historiensegmenten. Es gab keinen Dokumentüberlauf und keine HTTP-, Netzwerk-,
Console- oder JavaScriptfehler. Die Screenshots wurden visuell geprüft.

Der Statusfilter ergab reproduzierbar `4 / 1 / 2 / 1` für
Alle/2xx/Warnung/Down. Der Interaktionstest erfasste die vier Aktionen in der
Reihenfolge `insertNewService`, `setServiceData`, `deleteService` und
`EnableWebServiceMon` samt Feldwerten. `window.pialertPost` war dabei vor der
Bedienung ersetzt; alle vier POSTs wurden ausschließlich aufgezeichnet und
beantwortet. Es wurden weder Fixture- noch Produktivdaten geändert und kein
CLI-/Monitoringkommando ausgeführt.

## Grenzen

- Die Detailziele führen inzwischen auf `v4_serviceDetails.php`.
  Bearbeiten und Löschen sind zusätzlich direkt in der Liste möglich.
- GeoLite-Installation, -Aktualisierung und -Löschung sind bewusst nicht Teil
  der Liste. Die Karte zeigt nur den lokalen Zustand; die bestehenden
  Systemaktionen bleiben an ihren bisherigen Stellen.
- Produktive Schreibantworten, URL-/DNS-/TLS-Fehlerpfade, der reale
  IPv4-/IPv6-Verbindungsaufbau, `mmdblookup`, Screenreader und ein vollständiger
  Tastaturzyklus wurden nicht ausgeführt. Der Test belegt den Requestvertrag,
  nicht die Seiteneffekte dieser Backendaktionen.
