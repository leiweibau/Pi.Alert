# Service-Detailseite unter AdminLTE 4

Stand: 23. September 2026

## Ergebnis und Route

Die neue, gleichstufige Route ist `front/v4_serviceDetails.php`. Sie verwendet
die gemeinsame v4-Shell und besitzt mit `front/css/service-details.css` und
`front/js/service-details.js` eigene Assets. Der eindeutige Seitenmarker ist
`#service-details-page`; die drei Bereiche behalten die IDs `#panDetails`,
`#panEvents` und `#panGraph` sowie die Trigger `#tabDetails`, `#tabEvents` und
`#tabGraph`.

Die bereits getrennt bearbeitete Service-Liste verweist derzeit noch auf die
Alt-Route `serviceDetails.php`. Die spätere Integration muss dort ausschließlich
die drei Detailziele beziehungsweise `data-details-route` auf
`v4_serviceDetails.php` umstellen. `shell.php`, `paths.php`, `STATUS.md` und die
Service-Listen-Dateien wurden in diesem Arbeitspaket absichtlich nicht geändert.

## Erhaltene Daten- und URL-Verträge

- `url` bleibt der fachliche Schlüssel. Derselbe `pialert_validate_service_key()`-
  Vertrag schützt den direkten Einstieg; ungültige Werte werden zum v4-Einstieg
  zurückgeführt. Der Filterparameter akzeptiert `all`, `2`, `3`, `4`, `5` und
  `99999999`. Ein Filterlink bleibt ein echter Deep Link und öffnet den
  Ereignis-Tab.
- Die SQL-Texte, Bindings, Sortierung und Limits der Altseite wurden erhalten:
  Service per URL, maximal 2.000 Ereignisse, 144 Chartpunkte, Gesamtstatistik
  sowie 24-Stunden- und Sieben-Tage-Fenster. Auch das bestehende WAL- und
  DB-Öffnungsverhalten blieb unverändert.
- Die Serviceänderung sendet `setServiceData` mit `url`, `tags`, `mac`,
  `alertdown`, `alertup` und `alertevents`. Löschen verwendet weiterhin
  `deleteService` und die Service-URL. Die vorhandene `pialertPost()`-Schicht
  ergänzt CSRF und `_operation_id` und überführt Queryfelder wie bisher in den
  POST-Payload.
- GeoLite verwendet weiterhin `downloadGeoDB` beziehungsweise `deleteGeoDB` am
  bestehenden Endpoint `php/server/services.php`. Die Browserprüfung fing diese
  Aktionen vor dem Request ab; es wurde weder eine DB installiert/gelöscht noch
  eine andere Systemaktion ausgelöst.
- Der fachliche Tabzustand bleibt wie auf der Altseite im `serviceTab`-Cookie.
  Die Ereignistabelle startet wie bisher mit zehn Zeilen. Die neue gemeinsame
  UI-Konfigurationsdatei wird von dieser Seite daher derzeit nicht benötigt.

## Komponenten

Die Seite lädt DataTables Core 1.10.25 mit dem Bootstrap-5-Adapter, Chart.js
3.0.2 sowie die vorhandenen Pins von Moment 2.24.0 und FullCalendar 3.10.5. Das
Chart besitzt dieselben fünf gestapelten Reihen (2xx, 3xx, 4xx, 5xx und Down),
dieselben Daten und denselben 144-Einträge-Vertrag. Die Instanz ist während der
Laufzeit zusätzlich als `window.serviceHistoryChart` prüfbar und wird bei
`pagehide` zerstört.

Die Altseite lädt FullCalendar, initialisiert aber keinen Kalender und besitzt
kein `#calendar`-Element. Der gepinnte Stack bleibt aus Kompatibilitätsgründen
geladen; es wurde keine neue Kalenderfunktion erfunden. Native Bootstrap-5-
Switches ersetzen auf dieser Seite lediglich die optische iCheck-Hülle; die
maßgeblichen Checkbox-IDs und die übertragenen 0/1-Werte sind unverändert.

## Browsernachweis

Der wiederholbare Test liegt in
`_workspace/tests/adminlte4/browser_service_details.mjs`. Er lief gegen die mit
`build_synthetic_fixture.py --force` frisch erzeugte, isolierte Fixture unter
`/tmp/pialert-adminlte4-fixtures/` und Chromium 153:

- Desktop 1440 × 1000 und Mobil 390 × 844: Seitenmarker, DataTables-Wrapper,
  sechs Filter, fünf Chart-Datensätze und Titel vorhanden; kein horizontaler
  Dokumentüberlauf und keine HTTP-, Netzwerk-, Console- oder JavaScriptfehler.
- Gültiger Deep Link für `https://app.example.test/` und `filter=5`: URL bleibt
  erhalten, Ereignis-Tab ist aktiv und zeigt die gefilterte synthetische Zeile.
- Ungültiger URL-Wert mit Zeilenumbruch: kein Detailseitenmarker; Rückführung
  über `v4_index.php` endet erwartungsgemäß auf `v4_devices.php`.
- `setServiceData`, `deleteService` und die in der Fixture angebotene
  `downloadGeoDB`-Aktion wurden mit vollständigen Feldwerten aufgezeichnet. Der
  Test ersetzte `window.pialertPost` vor jeder Aktion; kein produktiver oder
  Fixture-schreibender Request wurde gesendet.

Ergebnisdatei und Screenshots liegen unter
`/tmp/pialert-v4-service-details-browser-run/`. `php -l` für den Einstieg und
`node --check` für Seitenlogik und Testskript waren erfolgreich.

## Grenzen

- Die Altseite `serviceDetails.php` enthält weder Nmap-Dialog noch Aufruf von
  `php/server/nmap_scan.php`. Dieser Endpoint gehört laut DOM-Vertrag zur
  Geräte-Detailseite. Deshalb gibt es auf der Service-Detailseite keinen zu
  erhaltenden Nmap-Vertrag und es wurde keine fachfremde Nmap-Funktion ergänzt.
- FullCalendar bleibt auf dieser Seite wie im Bestand uninitialisiert. Ein
  sichtbarer Kalender wäre eine neue Funktion und benötigt ein eigenes Paket.
- Reale GeoIP-Auflösung mit `mmdblookup`, echte Schreibantworten, weitere
  Browser-Engines, Screenreader und reale Mobilgeräte bleiben Teil der späteren
  Gesamtprüfung.
- Die drei Links aus `v4_services.php` müssen vom Besitzer der Service-Liste
  noch auf die oben genannte Route umgestellt werden.
