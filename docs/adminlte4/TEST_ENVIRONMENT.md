# P0-Testumgebung und Referenzläufe

Die lokale Testkopie liegt unter `/tmp/pialert-adminlte4-p0/`. Sie wurde aus
dem Frontendquellbestand ohne `front/reports/`, `front/satellites/` und
`front/php/tmp/` erstellt. Die Konfiguration basiert auf
`config/pialert.example.conf`; `version.conf` wurde für das Asset- und
Versionsverhalten übernommen.

Die beiden Datenbanken wurden ausschließlich aus den SQLite-Schemata erzeugt.
Bei der Erstellung am 22. September 2026 hatten die Tabellen `Devices`,
`Services`, `ICMP_Mon`, `Tools_Nmap_Queue` und
`Tools_Speedtest_History` jeweils null Datensätze. Produktionsdaten,
Berichte, Satelliten- und temporäre Dateien befinden sich nicht in dieser
Testkopie.

## Browser und Startverfahren

Der gültige Referenzlauf vom 23. September 2026 verwendete
`Chromium 153.0.8010.52` (`/usr/bin/chromium`) im neuen Headless-Modus. Vor
jedem Serverstart wurde der lokale Port geprüft. Die Null-Daten-Kopie lief mit:

```sh
php -S 127.0.0.1:18085 -t /tmp/pialert-adminlte4-p0/front
```

Die synthetische Datenkopie lief nach ihrem letzten atomaren Neuaufbau mit:

```sh
php -S 127.0.0.1:18084 -t /tmp/pialert-adminlte4-fixtures/front
```

Chromium verwendete ein isoliertes Profil unter `/tmp`. Zuerst wurde
`index.php` im Browser geöffnet. Bei deaktiviertem Webschutz erzeugte die
Anwendung selbst eine echte PHP-Session, setzte `login=1` und leitete auf
`devices.php` weiter. Erst danach wurden die Zielseiten in derselben laufenden
Browserinstanz aufgerufen. Damit ist der frühere ungültige Lauf ohne
persistente Session nicht Bestandteil dieser Referenz.

Für jede Seite wartete der Prüflauf auf `Page.loadEventFired` und weitere
3,5 Sekunden für AJAX. Danach wurden angeforderte und endgültige URL,
`document.title`, Loginformular, Seitenüberschrift und ein seitenkennzeichnender
DOM-Selektor ausgelesen. Es wurden keine Steuerelemente angeklickt und keine
Schreib- oder Systemaktionen ausgelöst. Beide PHP-Server und Chromium wurden
nach Abschluss beendet.

Der Lauf ist mit `_workspace/tests/adminlte4/browser_reference.mjs`
wiederholbar. Nach dem Start eines der obigen PHP-Server wird Chromium in einem
zweiten Terminal ausschließlich mit lokalem DevTools-Port gestartet:

```sh
/usr/bin/chromium --headless=new --no-sandbox --disable-gpu \
  --disable-dev-shm-usage --disable-background-networking \
  --remote-debugging-address=127.0.0.1 --remote-debugging-port=9223 \
  --remote-allow-origins=http://127.0.0.1:9223 \
  --user-data-dir=/tmp/pialert-adminlte4-browser-profile about:blank
```

In einem dritten Terminal startet der eigentliche read-only-Lauf:

```sh
TEST_PORT=18085 CDP_PORT=9223 \
ARTIFACT_DIR=/tmp/pialert-adminlte4-browser-run \
node _workspace/tests/adminlte4/browser_reference.mjs
```

Das Skript akzeptiert nur numerische Ports und setzt Server wie DevTools fest
auf `127.0.0.1`. Es ruft selbst zuerst `index.php` auf und prüft, dass kein
interaktives Loginformular verbleibt. `PAGE_FILTER` und `SCREENSHOT_PAGES`
akzeptieren kommaseparierte Namen aus der fest eingebauten Seitenliste;
`SKIP_SCREENSHOTS=1` erzeugt nur JSON. Das Skript führt ausschließlich
Navigation, DOM-Auswertung und Screenshotaufnahme aus.

## Null-Daten-Referenz: 13 Hauptseiten

Alle 13 angeforderten URLs blieben nach hergestellter Session auf ihrer
jeweiligen Zielseite; keine landete auf `index.php` oder `devices.php`. Auf
keiner Seite war ein Loginformular vorhanden. Der gemeinsame Titel ist im
Bestand für zwölf Seiten `Pi.Alert - pialert-codex (0)`; nur das Dashboard
verwendet den spezifischen Titel `Pi.Alert - Dashboard`.

| Zielseite | Endgültige URL | Seitenüberschrift | Nachgewiesener DOM-Marker |
| --- | --- | --- | --- |
| `systeminfo.php` | `systeminfo.php` | `System Infomation` | `#sys_info_gen_head`, Text `General` |
| `updatecheck.php` | `updatecheck.php` | `Update Check` | `#updatecheck` |
| `journal.php` | `journal.php` | `Application Journal` | `#tableJournal` |
| `reports.php` | `reports.php` | `Notifications` | `#Container` |
| `devices.php` | `devices.php` | `Devices /` | `#tableDevices` |
| `devicesEvents.php` | `devicesEvents.php` | `Events` | `#tableEvents` |
| `services.php` | `services.php` | `Web Services` | `#servicesJournalTable` |
| `icmpmonitor.php` | `icmpmonitor.php` | `ICMP Monitor` | `#tableDevices` mit ICMP-Spalten |
| `network.php` | `network.php` | `Network Overview` | Link `a.servicelist_add_serv[href="./networkSettings.php"]` |
| `networkSettings.php` | `networkSettings.php` | `Settings - Network Overview Close` | `#netedit`, Text `Manage Devices` |
| `maintenance.php` | `maintenance.php` | `Settings and Maintenance` | `#Maintain-Status` |
| `dashboard.php` | `dashboard.php` | kein `#pageTitle`; eigener Dokumenttitel | `#devicesDonut` |
| `presence.php` | `presence.php` | `Presence by Device /` | `#calendar`, gerenderter September-2026-Kalender |

Die Detailseiten ohne ihre Pflichtparameter behielten den zuvor festgestellten
Vertrag: `serviceDetails.php` und `icmpmonitorDetails.php` antworten mit einem
302-Redirect auf `index.php`; `deviceDetails.php` liefert eine leere
Detailansicht mit HTTP 200.

## Synthetischer Datenlauf

Der finale Fixture-Build ist auf den 23. September 2026 geankert und enthält
keine Produktivdaten. Der Browserlauf gegen `devices.php`, `services.php` und
`presence.php` blieb auf allen drei Ziel-URLs. Alle DOM-Marker wurden gefunden;
es gab keine JavaScript-Ausnahme, keine fehlgeschlagene Browseranfrage und
keinen HTTP-Fehler.

- `devices.php`: Zähler `5 / 3 / 2 / 1 / 1 / 1` für Alle, Verbunden,
  Favoriten, Neu, Down und Archiviert; fünf gerenderte Tabellenzeilen. Der
  zusätzliche Presence-Wert des Endpunkts ist `4`.
- `services.php`: vier gerenderte Servicekarten. Der Endpunkt liefert
  `4 / 1 / 1 / 2` für Alle, Down, Online und Warning; die sichtbaren
  Header-Badges zeigen `1 / 1 / 2` für Online, Down und Warning.
- `presence.php`: vier Geräteressourcen (plus Tabellenkopf) und drei sichtbare
  Kalenderintervalle. Das entspricht dem aktuellen Altfrontend-Vertrag aus
  vier synthetischen `Sessions`: vollständig, noch aktiv, fehlender Start und
  fehlendes Ende. Die Kalender-API stellt davon drei Intervalle dar; dieser
  bestehende Filtereffekt ist Referenzverhalten und keine Fixture-Lücke.
- Der 12-Stunden-Chart zeigt im finalen Build gefüllte Online-/Offline-/Archiv-
  Balken. Die Fixture enthält außerdem 24 Stunden Verlauf je Quelle sowie
  Service- und ICMP-Verläufe für spätere Seitenprüfungen.

## Bekannte Fehler und Grenzen der Referenz

Im ersten Null-Daten-Lauf fehlte `front/reports/`. Dadurch lieferte
`php/server/files.php?action=getReportTotals` HTTP 500: `scandir(../../reports)`
ergab `false`, danach warf `array_diff()` in `files.php:1094` einen `TypeError`.
Die Testhülle wurde ohne Codeänderung um die leeren Verzeichnisse
`front/reports/`, `front/satellites/` und `front/php/tmp/` ergänzt. Der danach
vollständig wiederholte 13-Seiten-Lauf hatte keine HTTP- oder Console-Fehler.
Auf `maintenance.php` protokolliert PHP weiterhin erwartbare Warnungen für
nicht vorhandene Dateien unter `log/`.

Der korrigierte Lauf reproduziert auf `maintenance.php` weiterhin eine
JavaScript-Ausnahme: `Unexpected end of JSON input` in der Callback-Funktion
von `ListInactiveHosts` bei gerenderter Zeile 2161. Der zugehörige GET antwortet
mit HTTP 200; ein direkter Abruf desselben read-only-Endpunkts mit echter Session
liefert korrekt `[""]`. Dieser bestehende Null-Daten-/Timingfehler bleibt als
Referenzbefund offen und wird in P0 nicht durch eine Backendänderung kaschiert.
Der finale synthetische Lauf zeigte weder diese Ausnahme noch HTTP-/Console-
Fehler.

Nicht abgedeckt sind schreibende Aktionen, Scanner- und Systemkommandos sowie
vollständige Interaktionstests aller Dialoge, Filter und Detailseiten. Diese
Referenz belegt URL-/Session-/DOM-/Layoutverhalten und ausgewählte Datenfälle,
aber noch keine vollständige Funktionsparität.

## Dauerhafte Artefakte

Die kompakte, produktivdatenfreie Referenz liegt unter
`_workspace/adminlte4-browser-reference/`:

- `zero-data-results.json`: maschinenlesbare URL-, Titel-, DOM- und
  Fehlerergebnisse aller 13 Hauptseiten;
- `fixture-results.json`: entsprechende Ergebnisse und DOM-Metriken für
  Devices, Services und Presence;
- Desktop-Screenshots der drei synthetischen Datenseiten sowie des leeren
  Dashboards in 1440×1000;
- Mobil-Screenshots von Devices und Presence in 390×844.

Die Screenshots wurden visuell geprüft. Desktop-Navigation, Tabellen,
Servicekarten, Diagramme und Presence-Timeline sind sichtbar; die mobilen
Ansichten wechseln erwartungsgemäß auf die eingeklappte Navigation und zeigen
den horizontal breiteren Tabellen-/Timeline-Inhalt innerhalb des Viewports.

## v4-Shell-Nachprüfung

Die neue Shell wurde zusätzlich mit der synthetischen Kopie in Chromium 153
hell/dunkel und auf Desktop/Mobil geprüft. Nach Korrektur des Textkontrasts im
dunklen Sidebar-Statusblock wurde die helle Desktop-Ansicht erneut aufgenommen
und visuell kontrolliert:
`_workspace/adminlte4-browser-shell/light-contrast-verified-1440x1000.png`.
Die Zielseite `v4_devices.php` und ihre lokalen Assets antworteten mit HTTP 200;
die isolierten Testprozesse wurden danach beendet. Diese Prüfung betrifft die
Shell, nicht die noch ausstehende Funktionsparität aller v4-Seiten.

Für die weitere Seitenmigration gibt es außerdem die wiederholbare,
read-only Prüfroutine `_workspace/tests/adminlte4/browser_v4.mjs`. Sie nutzt
dieselbe echte Fixture-Session und kontrolliert je v4-Seite Desktop und Mobil:
Ziel-URL, DOM-Marker, Login-Redirect, horizontales Überlaufen sowie HTTP-,
Netzwerk-, Console- und JavaScriptfehler. Der erste Lauf gegen
`v4_devices.php` und `v4_updatecheck.php` bestand mit 4/4 Ansichten;
Screenshots und JSON liegen unter
`_workspace/adminlte4-browser-shell/v4-harness/`. Der Test führt keine
Schreib- oder Systemaktion aus. Er ergänzt die interaktiven Seitentests, ersetzt
sie aber nicht.

Nach Integration von `v4_devicesEvents.php` und `v4_systeminfo.php` wurde der
Lauf mit denselben synthetischen Daten wiederholt: 8/8 Desktop-/Mobilansichten
bestanden. Alle vier Seiten blieben auf ihrer Ziel-URL, zeigten den jeweiligen
DOM-Marker und hatten keinen Dokumentüberlauf und keine HTTP-, Netzwerk-,
Console- oder JavaScriptfehler. Die Bilder wurden visuell geprüft; insbesondere
Ereignisfilter/-tabelle und die Systeminfo-Karten sind sichtbar. Die
Systeminfo-Aktionsbuttons wurden nicht betätigt.
Nach der Journal-Einbindung bestand die nächste Wiederholung mit 10/10
Ansichten über fünf v4-Routen ebenfalls ohne diese Fehler. Die interaktive
Journalprüfung einschließlich Coloris, Filter und gemockter Parameter-POSTs
liegt separat unter `_workspace/adminlte4-browser-shell/journal/`.
Nach dem Ersatz der Devices-Vorschau durch die funktionale Seite und der
Reports-Integration bestand der gemeinsame Lauf mit 12/12 Ansichten über sechs
v4-Routen. Die interaktiven Seitenprüfungen für Devices und Reports liegen
separat unter `_workspace/adminlte4-browser-devices/` beziehungsweise
`_workspace/adminlte4-browser-shell/reports/`.

Nach Integration von Services, ICMP und der v4-Oberflächeneinstellungen
bestand der gemeinsame Lauf auf der synthetischen Kopie mit 18/18
Desktop-/Mobilansichten über neun v4-Routen. Der Seitenaufruf der
Devices-Tabelle erhöhte die Revision der neuen UI-Datei nicht. Die Prüfung
der Einstellungsseite umfasste außerdem einen getrennten Speichern→Reload-
Roundtrip und die HTTP-Schutzfälle 401/403/422/409. Das aktive Testprofil
verwendete Darkmode und sechs ausgeblendete Devices-Headerwidgets; trotzdem
traten keine HTTP-, Netzwerk-, Console-, JavaScript- oder Layoutfehler auf.

Für die abschließende Abnahme sind nach Nutzerbestätigung aktuelle Desktop-
Browser und aktuelle Android-/iOS-Browser verbindlich. Ältere Tablets und
nicht aktualisierbare WebViews müssen nicht unterstützt werden. Die lokalen
Chromium-Läufe sind eine erste automatisierte Engine-Prüfung, keine Bestätigung
auf realen Mobilgeräten oder in Safari/Firefox/Edge.
