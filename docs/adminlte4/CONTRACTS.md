# Gemeinsame Browser- und Endpoint-Verträge

Stand: 22. September 2026. Diese Referenz beschreibt den vorhandenen Vertrag.
P1–P5 dürfen ihn nicht ändern.

## Zugriff, Session und CSRF

Die öffentlichen Seiten verwenden `pialert_start_session()` aus
`front/php/server/session.php`. Der Session-Cookie-Pfad wird aus
`SCRIPT_NAME` abgeleitet. Neue öffentliche v4-Einstiegspunkte müssen daher auf
derselben Ebene wie ihre Altseiten liegen, beispielsweise `v4_devices.php`.
Ein Einstieg unter `v4/` würde einen zweiten Cookie-Pfad erzeugen.

`index.php` verarbeitet Login und Logout als POST. Jeder schreibende AJAX-Request
an denselben Origin erhält den Header `X-CSRF-Token` aus
`meta[name="csrf-token"]`. Der Server akzeptiert alternativ das Formularfeld
`_csrf`. Ein ungültiger Token führt zu HTTP 403 mit
`X-PiAlert-CSRF: invalid`; die bestehende Browserlogik lädt die Seite neu.

`pialertPost(url, data, success)` in `js/pialert_common.js` muss semantisch
identisch bleiben: POST, die vorhandene Form-Serialisierung, Query-Parameter
im Payload, ein zufälliges `_operation_id` und die Unterdrückung eines parallel
identischen Requests. Die Aktion darf ausschließlich same-origin erfolgen.

## Gemeinsame Leseaufrufe

| URL | Methode | Eingaben | Rückgabe für Browser |
| --- | --- | --- | --- |
| `php/server/devices.php` | GET | `action=getDevicesTotals`, `scansource`; Listen-/Kalenderaktionen mit Status | JSON oder Tabellen-/Kalenderdaten |
| `php/server/events.php` | GET | `action`, Typ, Zeitraum, Gerätebezug | Ereignis-, Session- oder Kalenderdaten |
| `php/server/icmpmonitor.php` | GET | Totals, Listenstatus, Hostbezug | JSON oder Tabellen-/Statusdaten |
| `php/server/services.php` | GET | Totals, Events, Service-URL | JSON oder Tabellen-/Statusdaten |
| `php/server/files.php` | GET | Serverzeit, Update-/Report-/ARP-/Backupstatus, Logs, Config | Text, JSON oder Download-Metadaten |
| `php/server/dashboard.php` | GET | Status-, Log-, Report-, Graph- und Service-Aktionen | JSON/HTML entsprechend Aktion |
| `php/server/parameters.php` | GET | Parameter-/Journal-/Reportaktion | Text oder JSON |
| `php/server/network.php` | GET | Infrastruktur-, Typ-, Gruppennamen- und Downlink-Aktion | HTML-Listen bzw. Datenfragmente |

## Schreibaufrufe

Alle Aktionen unter `devices.php`, `icmpmonitor.php`, `services.php`,
`files.php`, `parameters.php`, `network.php`, `commands.php`, `nmap_scan.php`
und `speedtest_ookla.php` behalten Methode, Aktionsname, Feldnamen,
Array-Serialisierung, CSRF-Header, Statuscodes und Rückgabeformat bei.
Die neue Oberfläche ruft dieselben URLs auf. Eine vollständige Aktionsliste ist
statisch aus den Switches der Serverdateien erfasst und wird vor jeder
Seitenportierung gegen den jeweiligen Aufruf geprüft.

| Endpoint | Schreibaktionen im Bestand |
| --- | --- |
| `devices.php` | Gerät-/Filter-/Satelliten-/Massenaktionen, Scan-Aktivierung, Historien- und Tooldaten bereinigen |
| `icmpmonitor.php` | Host anlegen/ändern/löschen, Bulk-Aktion, Aktivierung |
| `services.php` | Service anlegen/ändern/löschen, GeoDB, Aktivierung, Gesamtbereinigung |
| `files.php` | Config, Backups, GUI-Einstellungen, Benachrichtigungen, Sperrlisten und Laufzeitoptionen |
| `parameters.php` | allgemeine, Journal- und Reportparameter |
| `network.php` | Managed/Unmanaged Infrastruktur anlegen/ändern/löschen |
| `commands.php` | Neustart und Herunterfahren |

## HTML-Fragmentvertrag

`php/server/network.php`, `nmap_scan.php`, `speedtest_ookla.php` und
`updatecheck_v2.php` geben direkt konsumiertes HTML und teilweise Browser-
JavaScript zurück. Vor einer Änderung an einer dieser Dateien wird ein
verifiziertes, dateibezogenes Rollbackarchiv nach dem Migrationsplan angelegt.
Während des Parallelbetriebs muss jedes Fragment mit der alten und neuen UI
darstellbar bleiben. Datenwerte, Abfragen, Aktionen und Rückgabe-Semantik sind
geschützt.

## Gemeinsame Pfade und Browserzustand

Alle v4-Assets sind lokal. V4-Layoutzustände verwenden den Schlüsselpräfix
`pialert-v4:` in LocalStorage/SessionStorage. Bestehende fachliche Cookies und
Schlüssel dürfen nur übernommen werden, wenn Wert und Semantik identisch sind.
Downloads, Reports, Satellitendateien, `php/tmp/`, APIs und Serverendpunkte
behalten ihre vorhandenen Pfade.

