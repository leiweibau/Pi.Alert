# v4-Oberflächeneinstellungen

Die AdminLTE-4-Oberfläche verwendet `config/setting_ui_v4.json` beziehungsweise
bis zum ersten Speichern `config/setting_ui_v4.default.json`
für konfigurierbare Devices- und ICMP-Spalten, deren Seitengröße und Sortierung sowie Sprache,
Theme, Darkmode, Sidebar-/Header-Farbe, Pi-hole-URL und Header-Widgets. Es gibt dafür keine
Datenbankabfrage und keine Änderung an den bestehenden Queries oder Endpunkten.

Solange die persönliche Datei fehlt, werden ausschließlich die mitgelieferten
Standardwerte aus `setting_ui_v4.default.json` gelesen, einschließlich Sprache
`en_us`, Standardtheme sowie v4-Vorgaben für Spalten, Widgets, Seitengröße und
Sortierung. Alte `setting_*`-Dateien werden nicht migriert; auch die bisherigen
`Front_Devices_*`-Datenbankparameter werden dafür nicht gelesen.
Erst das erste Speichern legt `setting_ui_v4.json` an. Danach ist diese Datei
für v4 maßgeblich; vorhandene persönliche v4-Einstellungen bleiben erhalten.
Die Altoberfläche bleibt im Rollback-Archiv erhalten. Die Headerkarten der
aktiven Seiten verwenden die v4-Widgetwerte.

Die Datei hat Schema `6` und eine monoton steigende `revision`. Bereits
vorhandene Schema-1-Dateien werden beim Lesen ohne Schreibzugriff auf die
neutralen Standardfarben (`body-secondary`/`body`) abgebildet. Schema 1 und 2
verwerfen dabei alte Theme-Werte; Schema 3 erhält beim Lesen `standard` als
neuen Theme-Wert. Schema 4 erhält beim Lesen ohne Dateischreibzugriff die
ICMP-Tabellenvorgaben. Der nächste reguläre Speichervorgang schreibt Schema 6.
Spalten und Sortierung werden mit stabilen IDs wie `MACAddress` gespeichert. Eine zentrale
Registry in `front/php/ui-settings.php` ordnet Devices-IDs den 19 Positionen
und ICMP-IDs den zehn Positionen der unveränderten Backendantwort beziehungsweise
der UI-Aktionsspalte zu. Die technischen Devices-Spalten `LastIPOrder`,
`ScanSource`, `Rowid` und `NmapQueue` sowie die technischen ICMP-Spalten
`AlertDown`, `StatusCode` und `Rowid` sind immer unsichtbar. Bei ICMP bleiben
Name, Status und Aktionen sichtbar; IP, Favorit, Antwortzeit und Scanzeit sind
über die Oberflächenseite wählbar. Der Speichervorgang
validiert die vollständige Struktur, sperrt eine separate Lockdatei, prüft bei
Formularen die Revision und ersetzt die Konfigurationsdatei atomar über eine
temporäre Datei im selben Verzeichnis. Datei und Lock haben Modus `0640`.
Eine beschädigte vorhandene Konfiguration wird nicht stillschweigend
überschrieben, sondern als Fehler gemeldet.

AdminLTE 4 enthält keine direkt übernehmbaren `skin-*`-Dateien aus v2. Ein
bereits importierter Skin-Wert bleibt im Dateiformat erhalten, damit frühere
v4-Konfigurationen lesbar bleiben; er beeinflusst die v4-Farben nicht mehr.
Statt der klassischen Skin-Auswahl bietet das Formular die Themes `Standard`
und `Glas` sowie zwei unabhängige Farbfelder für Sidebar und Header. `Glas`
wird ausschließlich durch ausdrückliche Auswahl aktiviert. Sidebar- und
Header-Farben sowie Darkmode bleiben unabhängig gespeichert. Wählbar sind
neutrale Standardfarben und die optionale AdminLTE-4.9.1-Farbpalette; nur bekannte Namen werden
angenommen. Die Bootstrap-Akzentfarben sind aus der Auswahlliste entfernt,
bleiben aber für bereits gespeicherte Werte lesbar. Die Hintergrundklasse und
der passende Textmodus werden für jedes Element kombiniert. Das Benutzermenü
verwendet passend dazu `pialertLogoBlack.png` oder `pialertLogoWhite.png`, auch
in der Live-Vorschau. Der Header bleibt beim Scrollen durch AdminLTEs
`fixed-header`-Layout sichtbar. Darkmode steuert im Standardtheme das globale
`data-bs-theme`; `Glas` verwendet immer den dunklen Farbmodus. Die
Einstellungsseite zeigt Änderungen sofort als Vorschau.
Die v4-eigene ColorMode-Automatik ist für diese serverseitig
verwaltete Konfiguration deaktiviert, damit ein früherer `lte-theme`-Wert im
Browser die Datei nicht überstimmt.

Der Schalter für den Aktivitätsverlauf befindet sich ebenfalls auf
`ui_settings.php`. Er nutzt den bisherigen Backend-Endpunkt; sein Zustand wird
weiterhin über `config/setting_noonlinehistorygraph` abgebildet und gehört
nicht zum UI-JSON-Schema.

Die Seite `ui_settings.php` bietet einen transaktionalen Formular-POST für
Spalten und Darstellung. Der authentifizierte Endpunkt
`php/server/v4_ui_settings.php` stellt `GET action=get` und
`POST action=save` mit JSON-Patches für die automatische DataTables-Persistenz
bereit. POST verlangt den bestehenden CSRF-Token; optionale Revisionen
verhindern veraltete Updates. Unangemeldete Aufrufe erhalten 401, ungültige
Werte 422 und veraltete Revisionen 409. Die Datei liegt außerhalb des Webroots.

Die Update-Archivextraktion schließt nur die persönliche
`config/setting_ui_v4.json` aus. Die mitgelieferte Default-Datei wird bei
Installationen und Updates aktualisiert und mit `root:root`/`0644` geschützt;
die persönliche Datei bleibt auf `www-data:www-data`/`0640`. Der Download der
UI-Einstellungen liefert bei fehlender persönlicher Datei den gültigen Default.
Beim Update werden aus alten `db/setting_*`-Dateien nur noch die weiterhin
genutzten Betriebsmarker `setting_stoppialert`, `setting_noonlinehistorygraph`
und `setting_favicon` nach `config` verschoben, sofern dort noch keine Datei
gleichen Namens liegt. Beide UI-JSON-Dateien und sonstige Altmarker werden
nicht verschoben oder überschrieben; `setting_stoparpscan` bleibt in `db`.

Prüfungen: `_workspace/tests/adminlte4/ui_settings_test.php` deckt Default-Fallback,
Spaltenzuordnung, Speichern, Rechte, ungültige Werte, Revision und beschädigte
Datei sowie Schema-1-bis-4-Migration, Theme-Werte und Farbnamen ab.
`_workspace/tests/adminlte4/icmp_columns_browser.mjs` prüft gegen synthetische
Daten die Formularspeicherung, DataTables-Sichtbarkeit und Sortierpersistenz.
`browser_ui_colors.mjs` prüft Hell-/Dunkelvorschau und Farbauswahl;
`browser_glass_theme.mjs` prüft das Opt-in, den Roundtrip, Breakpoints und den
Rückwechsel zu Standard. `browser_v4.mjs` prüft zusätzlich Platzierung und
Bestätigungsfluss des Aktivitätsverlaufs ohne echten Backend-Schreibzugriff.
HTTP-Tests decken Authentifizierung, CSRF, Validierung und Revision ab.
Andere Browser-Engines und reale Mobilgeräte sind noch offen.
