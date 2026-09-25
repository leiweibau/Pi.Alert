# DOM- und Browserverträge der bestehenden Oberfläche (P0)

Stand: 22. September 2026. Dieses Dokument erfasst die Verträge, die ein
v4-Frontend zunächst erfüllen muss. Es ist kein vollständiger Ersatz für die
Funktionsmatrix; die Matrix ordnet jede Benutzeraktion einer Seite zu.

## Gemeinsame Oberfläche

Die Standardseiten erwarten den von `php/templates/header.php` erzeugten
Shell-Aufbau. Die zentralen Selektoren und ihre Konsumenten sind:

| Vertrag | Abhängige Logik | Portierungsregel |
| --- | --- | --- |
| `#PIA_Servertime_place`, `#nextscancountdown`, `#autoReloadCheckbox` | Footer-Serveruhr, Countdown und Auto-Reload | IDs oder gleichwertige, zentral nachgeführte Selektoren erhalten. |
| `#sidebar_systeminfobox`, `.custom_filter` | `toggle_systeminfobox()` in Header/Dashboard | Sidebar-Logik auf v4 abbilden; Funktion bleibt global erreichbar, solange Inline-`onclick` besteht. |
| `#header_<scansource>_count_on`, `_count_new`, `_count_down`, `_presence`, `#header_icmp_count_on`, `#header_services_count_on`, `#Menu_Report_Counter_Badge`, `#header_updatecheck_notification` | Footer-Polling (`getDevicesTotalsBadge`, `getICMPTotalsBadge`, `getServicesTotalsBadge`, `GetUpdateStatus`, `getReportTotalsBadge`) | IDs oder Adapter beibehalten. Polling nicht doppelt registrieren. |
| `#modal-default`, `#modal-warning` und Unterelemente `-title`, `-message`, `-cancel`, `-OK` | `pialert_common.js`: `showModalDefault`, `showModalWarning`, `showModalOK`, `showModalWarningOK` | Bootstrap-5-Modal-API hinter derselben Anwendungs-API kapseln; Callbacks und Button-IDs erhalten. |
| `#notification`, `#alert-message`, `#pageTitle`, `#rawtemp`, `#tempdisplay`, `#tempunit-selector` | Benachrichtigungen, Seitentitel, Temperaturdarstellung in `pialert_common.js` | Bei Template-Neubau gemeinsam migrieren. |

Der gemeinsame Code setzt außerdem einen `<meta name="csrf-token">` voraus.
`pialert_common.js` liest ihn für AJAX-Anfragen und behandelt globale
`ajaxError`-Ereignisse. Das ist ein Request- und Sicherheitsvertrag, kein
optisches Detail.

## Bootstrap-3- und AdminLTE-2-Verträge

Der Quellbestand enthält 492 eindeutige statische `id`/`name`-Werte und 277
Inline-Eventattribute. Zusätzlich gibt es PHP-generierte IDs. Eine pauschale
Umbenennung ist deshalb nicht sicher.

Folgende Muster sind tatsächlich in Anwendungscode und Markup vorhanden und
müssen je Seite portiert oder vorübergehend adaptiert werden:

- `data-toggle="tab"`, `data-toggle="modal"`, `data-toggle="dropdown"`,
  `data-toggle="tooltip"`, `data-dismiss="modal"`, `data-widget="collapse"`
  und `data-target`.
- jQuery-Bootstrap-Aufrufe `.modal('show'|'hide')`, `.tab('show')` und
  `.tooltip(...)` sowie Ereignisse `shown.bs.tab`, `show.bs.modal`,
  `hidden.bs.modal`.
- AdminLTE-2-Strukturen und Klassen: `.wrapper`, `.main-header`,
  `.main-sidebar`, `.content-wrapper`, `.main-footer`, `.box`, `.box-header`,
  `.box-body`, `.box-title`, `.small-box`, `.sidebar-toggle`.
- Bootstrap-3-Helfer wie `col-xs-*`, `pull-left/right`, `hidden-xs`,
  `btn-default`, `input-group-addon`, `label`, `close`, `glyphicon` und
  `navbar-*`.

Bootstrap 5 verwendet andere Data-Attribute und keine jQuery-Plugins. Die neue
Seite muss daher die aufgerufene Anwendungsfunktion und das Ereignisverhalten
bewahren, nicht das alte Bootstrap-Attribut selbst.

## Komponentenverträge

| Komponente | Selektoren / Ereignisse / Methoden, die im eigenen Code nachgewiesen sind |
| --- | --- |
| DataTables | Tabellen `#tableDevices`, `#tableEvents`, `#tableSessions`, `#tableSpeedtest`, `#servicesJournalTable`, `#tableJournal`; `DataTable()`, `ajax.url(...).load()`, `column().visible()`, `page.len()`, `order()`, `draw()` sowie `length.dt`, `order.dt`, `search.dt`. Renderer nutzen `$.fn.dataTable.render.text()`. |
| iCheck | Farbselektoren wie `input[type="checkbox"].blue`, `.orange`, `.red`, `.green`, `.purple`; Methoden `iCheck('check'|'uncheck')`; mindestens die Ereignisse `ifToggled`. Die tatsächlichen `<input>`-Werte bleiben die maßgebliche Formulardatenquelle. |
| Kalender | `#calendar`; jQuery-API `fullCalendar(...)`, `getView`, `removeEventSources`, `addEventSource`, `refetchResources`, `rerenderEvents`; Tooltip-Initialisierung im Event-Renderpfad. Detailseiten koppeln Kalender-Neurendering an Tabwechsel. |
| Scheduler-Präsenz | `#calendar` zusätzlich mit Ressourcen- und Timeline-Ansichten. Die Seite setzt `schedulerLicenseKey`, liest `timelineYear`/`timelineDay` und erwartet `.fc-cell-text` in gerenderten Beschriftungen. |
| Chart.js | Canvas-Elemente werden per `getContext('2d')` beschafft; Diagramminstanzen werden teils global gespeichert (`window.speedtestChart`, `window.devicesDonutChart`, `window.devicesDonutIcmpChart`, `historyStackedCharts`). Vor Neubau destroy-/refresh-Verhalten pro Diagramm prüfen. |
| Coloris | Dynamische Inputs erhalten `data-coloris`; `journal.php` ruft `Coloris.init()` mehrfach nach DOM-Aufbau auf. `reports.php` und `journal.php` übergeben Konfiguration per `Coloris({...})`. |

## Globale Funktionen und Inline-Handler

Inline-Handler rufen zahlreiche nicht modulare Funktionen auf. Repräsentative,
seitlich besonders kritische Gruppen sind:

- Navigation und Details: `update_tabURL`, `back_to_devices`,
  `getDeviceData`, `saveDeviceData`, `clearInput`, `copyiptoclipboard`,
  `copymactoclipboard`, `askwakeonlan`.
- Wartung und Konfiguration: `SaveConfigFile`, `RestoreConfigFile`,
  `PialertReboot`, `PialertShutdown`, `BackupDBtoArchive`, `BackupDBtoCSV`.
- Listen und Filter: `SetDeviceFilter`, `SaveFilterID`, `filterDevicesByLabel`,
  `JournalReload`, `ReportReload`.
- Monitoring: `addManagedDev`, `addUnManagedDev`, `deleteService`,
  `deleteICMPHost`, `download_speedtest`.

Vor der Umstellung auf ES-Module werden alle per HTML aufgerufenen Funktionen
entweder als normale globale Funktionen beibehalten oder gezielt an `window`
gebunden. Die vollständige Zuordnung pro Seite gehört in die Funktionsmatrix.

## Browserzustand und Lebenszyklus

- Cookies mit `path=/`: `devicesList`, `serviceTab`, `icmpTab` und weitere
  Hilfszustände. `pialert_common.js` setzt Cookies mit `SameSite=Strict` und
  `path=/`; einige Detailseiten enthalten eigene, ältere Cookiehelfer.
- Local Storage: `tempunit` und `autoReloadChecked`.
- Wiederkehrende Aufrufe: Header-Footer-Polling (15/30 Sekunden), Dashboard-
  Refresh, Wartungs-Updatebox, Services-Aktualisierung und Nmap-Detail-Polling.
  Der v4-Initialisierungspfad muss alte Timer vor einer erneuten Registrierung
  beenden und bei parallelen Tabs nicht zusätzliche Timer in derselben Seite
  erzeugen.
- PWA/Standalone: Header und Dashboard registrieren einen
  `visibilitychange`-Handler, der eine Standalone-Ansicht bei Rückkehr neu lädt.

## Dynamische HTML-Fragmente

`network.php` füllt die Infrastrukturansicht mit HTML aus
`php/server/network.php`; `deviceDetails.php` übernimmt Nmap-Ausgabe aus
`php/server/nmap_scan.php`; Update- und Speedtest-Ansichten nutzen ihre
jeweiligen Serverfragmente. Diese Fragmente verwenden zum Teil Bootstrap-3-
Klassen und Inline-Skripte. Während der Parallelphase benötigen sie kompatibles
Markup oder einen gezielten v4-Adapter. Für die vier freigegebenen Serverdateien
gilt vor einer Änderung der im Hauptplan festgelegte Sicherungs- und
Rollbackprozess.
