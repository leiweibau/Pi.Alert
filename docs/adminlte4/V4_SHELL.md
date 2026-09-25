# AdminLTE-4-Shell: Layout- und Theme-Mapping

Stand: 24. September 2026

## Umfang

`front/php/shell.php` stellt Header, Sidebar, Systemstatus, Navigation,
Benutzermenü, Inhaltsrahmen und Footer für die aktiven AdminLTE-4-Seiten bereit.
Die Shell verändert weder Konfigurationswerte noch Backend- oder Query-Verhalten.
Die frühere AdminLTE-2-Oberfläche liegt ausschließlich im Rollback-Archiv.
Konfigurationsabhängige Einträge für Webservices und ICMP werden nur angezeigt,
wenn die jeweilige bestehende Option aktiv ist. Dashboard und Reports sind über
das Benutzermenü erreichbar; das Dashboard hat keine Sidebar.

## Theme- und Skin-Abbildung

Die v4-Shell liest `config/setting_ui_v4.json` beziehungsweise bei fehlender
persönlicher Datei `config/setting_ui_v4.default.json`; alte `setting_*`-Marker
werden nicht migriert. Ohne
ausdrückliche Auswahl bleibt die Standardoberfläche unverändert.

Pi.Alert setzt den Bootstrap-Farbmodus serverseitig vor dem ersten Rendern
über `data-bs-theme`. Im Standardtheme folgt er dem gespeicherten Darkmode;
`Glas` verwendet immer den dunklen Modus und lädt die lokale Datei
`css/themes/glas.css` nach den Seitenstyles. Nur für `Glas` erhält das
`html`-Element `data-pialert-theme="glas"`. So bleiben seine Regeln gekapselt;
Header- und Sidebar-Farben sowie der gespeicherte Darkmode bleiben unabhängig
erhalten. Die v2-Skin-Dateien wirken in v4 nicht mehr.

## DOM- und Laufzeitverträge

Die Shell erhält die inventarisierten IDs für Serverzeit, Scan-Countdown,
Auto-Reload, Systeminfo, Temperatur, Geräte-/Service-/ICMP-/Presence-Zähler,
Reports und Updatehinweise. Die Browserlogik initialisiert diese Elemente
idempotent. Scriptreihenfolge: jQuery 3.6.2, Bootstrap 5.3.8 Bundle, AdminLTE
4.9.1, `pialert-common.js`, `pialert-shell-runtime.js`, Seitenscript.
Bootstrap-3- und AdminLTE-2-CSS/JS werden nicht geladen.

Seiteneinstiege übergeben lokale Styles als drittes Argument an
`pialert_v4_shell_start()` und lokale Scripts an `pialert_v4_shell_end()`.
Alternativ stehen vor dem jeweiligen Renderzeitpunkt die Enqueue-Helfer
`pialert_v4_enqueue_style()` und `pialert_v4_enqueue_script()` bereit. Die
Shell validiert lokale v4-Pfade, unterdrückt Dopplungen und gibt Assets in
`<head>` beziehungsweise an der festgelegten Scriptstelle vor dem allgemeinen
`pialert-v4.js` aus.

Die vier bisher verwendeten Iconfamilien bleiben lokal verfügbar: Font Awesome
6, Bootstrap Icons, Ionicons und Material Design Icons. CSS-, Font- und
Lizenzdateien liegen getrennt unter `front/lib/`; insbesondere bleibt das
Material-Design-Pi-hole-Symbol erhalten.

## Responsive Verhalten

AdminLTE steuert die Sidebar über `data-lte-toggle="sidebar"`. Auf schmalen
Viewports entfallen Host/Uhr aus der Toolbar und das Benutzermenü wird auf die
verfügbare Höhe begrenzt. Deaktivierte Navigationspunkte sind nicht fokussierbar;
externe Ziele verwenden `noopener noreferrer`. Bei `prefers-reduced-motion`
werden Übergänge weitgehend abgeschaltet.

Nur im Theme `Glas` erscheinen unter 768 px eine kompakte Markenleiste und eine
Bottom-Navigation. Die Sidebar bleibt per Menübutton erreichbar. Auf der
Geräteliste ersetzt `Glas` die sichtbare Desktop-Tabelle mobil durch Karten,
deren Daten aus derselben DataTables-Instanz stammen.

Das Dashboard zeigt keinen zusätzlichen sichtbaren Seitentitel; ein verborgenes
`h1` erhält die Überschriftenstruktur. Seine Zoom-Buttons skalieren nur den
Dashboard-Inhalt, sodass Header, Footer und andere Seiten unverändert bleiben.
Die Stufe wird für ein Jahr in einem auf `dashboard.php` begrenzten Cookie
gespeichert. Chart.js übernimmt Größenänderungen selbst; ein zusätzlicher
manueller Resize-Aufruf würde die Donut-Diagramme doppelt verkleinern.

## Prüfung

Der funktionale Chromium-Smoke-Test `browser_v4.mjs` deckt aktuelle
Desktop- und Mobilrouten ab. Der Glas-Test `browser_glass_theme.mjs` prüft
Standard ohne Opt-in, gespeicherte Glas-Auswahl, Tabelle/Karten an den
Breakpoints 768/430/390/360 px, Navigation, Zoom, leere Liste, reduzierte
Transparenz und fehlende externe beziehungsweise fehlerhafte Ressourcen.
Screenshots und Ergebnisse liegen unter `_workspace/adminlte4-glass/`.
PHP-Syntax, JavaScript-Syntax und der Konfigurations-Vertragstest
`ui_settings_test.php` werden zusätzlich ausgeführt.
