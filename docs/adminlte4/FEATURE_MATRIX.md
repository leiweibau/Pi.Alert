# Funktionsmatrix: AdminLTE-4-Migration

Stand: 23. September 2026. Der Status `inventarisiert` bestätigt nur die
Erfassung der Funktion. `teilgeprüft` benennt bestandene v4-Teiltests, aber
noch keine vollständige Funktionsparität; diese wird erst in P5/P6 abgenommen.
Alle Seiten verlangen eine aktive Pi.Alert-Session, sofern in der Spalte
„Bedingung“ nichts anderes steht.

| Seite / Teilansicht | Quellen und Benutzeraktionen | Bedingung | Endpunkte / Verträge | Solltest | Status |
| --- | --- | --- | --- | --- | --- |
| `index.php` | Login, Remember-me, Logout, Login bei ausgeschaltetem Webschutz | `PIALERT_WEB_PROTECTION`, Sprache, Favicon, Dark Mode | Formular-POST und bestehende Auth-/Session-Helfer | Erfolgreich/falsch, Logout POST, Cookie-Pfad unter `/` und `/pialert/` | teilgeprüft: isolierte Auth-/Cookie-Fälle; Gesamtabnahme offen |
| `systeminfo.php` | Systemwerte, Cronlog, Neustart, Herunterfahren | angemeldet; Host-Systemdaten verfügbar | `commands.php`: `PialertReboot`, `PialertShutdown` | reine Anzeige; Systemaktionen ausschließlich Testinstanz | teilgeprüft: v4-Anzeige und Dialoge in Chromium; Systemaktionen nicht ausgelöst |
| `updatecheck.php` | Updateprüfung und Updateausgabe | angemeldet | `updatecheck_v2.php` | Lade-, Fehler- und Ergebniszustand; Fragment in beiden UIs | teilgeprüft: v4 mit gemockten Erfolgs-/Fehlerantworten; echte Updateaktion offen |
| `journal.php` | Parameter laden/speichern, Filter, Journaltabelle, Coloris-Felder | angemeldet | `parameters.php`: `getJournalParameter`, `setJournalParameter` | Filter, Farbe, Löschen/Speichern, XSS-Ausgabe | teilgeprüft: v4-Filter/Coloris/XSS und gemockte POSTs; Persistenz offen |
| `reports.php` | Reportfilter, Farben, Anzeige, Archiv- und Löschdialoge, Downloads | angemeldet | `parameters.php`, `files.php`: Benachrichtigungen/Downloads | leere und große Mengen, Dialoge, Download | teilgeprüft: v4-Karten/Filter/Coloris/Dialoge und gemockte POSTs; echte Mutation/Druck offen |
| `devices.php` | Statusboxen, Tabellen, Filter, Bulk-Aktionen, Satelliten, Graphen | angemeldet; `SCANSOURCE` und Konfiguration | `devices.php`, `parameters.php`, Graphdaten | Sortierung, Suche, Filter, Checkboxen, add/edit/delete nur Testdaten | teilgeprüft: v4-Liste/Chart/Filter/Bulk mit synthetischen und leeren Daten; Schreibpfade offen |
| `devicesEvents.php` | Zeitraum, Statusboxen, Ereignistabelle | angemeldet | `events.php`: Totals und `getEvents`; `parameters.php` | alle Zeitraum-/Ereignisarten, Tabelle und Deep Link | teilgeprüft: v4-Tabelle/Filter mit synthetischen Daten; Parameter-POSTs gemockt |
| `services.php` | Webservice-Tabelle, Anlegen, Ändern, Löschen, GeoDB, Aktivierung | Webservice-Monitor aktiviert/konfiguriert | `services.php` | IPv4/IPv6-angezeigte Daten, Filter, Edit- und Resetabläufe | inventarisiert |
| `serviceDetails.php` | Servicehistorie, Eventfilter, Graph, Kalender, Nmap-Werkzeug | gültiger `url`-Parameter | `services.php`, `nmap_scan.php`, Graphdaten | Parameter, Tabs, Kalender, Scan-Dialog und Fehlerfälle | inventarisiert |
| `icmpmonitor.php` | Hostliste, Statusboxen, Filter, Bulk-Aktionen, Graphen, Anlegen | ICMP-Monitor konfiguriert | `icmpmonitor.php`, `devices.php`, `parameters.php` | Filter, iCheck-Ereignisse, Add/Edit/Delete auf Testdaten | inventarisiert |
| `icmpmonitorDetails.php` | Hostdaten, Historie, Kalender, Nmap-Werkzeug | gültiger `hostip`-Parameter | `icmpmonitor.php`, `devices.php`, `nmap_scan.php` | ungültiger Host, Tabs, Kalender, Scanqueue | inventarisiert |
| `network.php` | Infrastrukturansicht, Knoten-/Gerätelinks und Benachrichtigung | Netzwerkdaten vorhanden | Seiten-DB-Ausgabe, Navigationslinks | leere/verbundene Infrastruktur, responsive Darstellung | inventarisiert |
| `networkSettings.php` | Managed/Unmanaged Geräte und Verknüpfungen anlegen, ändern, löschen | Netzwerkverwaltung verfügbar | `network.php` (Serverendpunkt) | Listen, Formvalidierung, CRUD nur Testdaten | inventarisiert |
| `deviceDetails.php` | Stammdaten, Sessions, Events, Kalender, Graphen, Nmap/Speedtest, Werkzeuge | gültiger `mac`-Parameter | `devices.php`, `events.php`, `files.php`, `parameters.php`, `nmap_scan.php`, `speedtest_ookla.php` | Dirty-State, Tabs, Kalender, Toolfragmente, Downloads | inventarisiert |
| `maintenance.php` | Wartungsregister, Config-Editor, GUI-, Satelliten- und Bereinigungsaktionen | administrative Konfiguration | `devices.php`, `files.php`, `icmpmonitor.php`, `services.php`, `updatecheck.php` | nur read-only gegen Bestand; schreibende Abläufe in Testinstanz | inventarisiert |
| `dashboard.php` | Statuskacheln, Logs, Reports, Service-/Gerätestatus, Chart.js, Polling | angemeldet; optionale Datenquellen | `dashboard.php`, `events.php`, `files.php` | Diagramme, Autorefresh, Nullwerte, Timer und Tastatur | inventarisiert |
| `presence.php` | Anwesenheitsgraph, FullCalendar Scheduler Timeline, Ressourcen, Filter | `SCANSOURCE`, Anwesenheitsdaten | `devices.php`, `events.php`, Graphdaten | Tag/Woche/Monat, Ressourcen, kurze Intervalle, DST, Druck | inventarisiert |

## Gemeinsame Teilansichten

| Teilansicht | Quelle | Vertrag / Test | Status |
| --- | --- | --- | --- |
| Hauptnavigation, Badges, Uhr, Serverzeit, Reload | `php/templates/header.php`, `footer.php` | Device-/ICMP-/Service-/Report-/Update-Badges, Polling ohne Doppelinitialisierung | inventarisiert |
| Benachrichtigung und Bestätigungsmodale | `php/templates/notification.php`, `js/pialert_common.js` | Modal-Fokus, OK/Abbruch, Textausgabe, Bootstrap-5-Events | inventarisiert |
| Sprache, Skin, Favicon, Dark Mode | `header_func.php`, `maintenance_gui.php` | alle bestehenden gespeicherten Werte, kein Flash beim Farbmodus | inventarisiert |
| Hotkeys, Deep Links und Zurück | `js/hotkeys.js`, Seitenlinks | Ziel-URLs auf v4, alte URLs bleiben erreichbar | inventarisiert |
| Serverfragmente | `network.php`, `nmap_scan.php`, `speedtest_ookla.php`, `updatecheck_v2.php` | Antworten mit Alt- und Neu-UI; nur freigegebene Ausgabeteile | inventarisiert |

## Referenzfehler und Abweichungen

`dashboard.php` enthält eine Chart.js-v2-Konfiguration, obwohl Chart.js 3.0.2
geladen wird. Das ist als bestehender Referenzfehler zu behandeln. Eine Korrektur
ist nur in der neuen Dashboard-Kopie vorgesehen und wird bei P4 mit identischen
Datenwerten und Screenshots belegt.
