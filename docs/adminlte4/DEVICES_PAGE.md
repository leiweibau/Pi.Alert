# Geräte-Hauptseite unter AdminLTE 4

Stand: 23. September 2026

## Umfang

`front/v4_devices.php` ersetzt die bisherige v4-Vorschau durch die funktionale
Geräte-Hauptseite. Das Rendering ist in v4-eigene PHP-Partials unter
`front/php/devices-page*.php` aufgeteilt; Darstellung und Browserlogik liegen
ausschließlich in `front/css/devices.css` und `front/js/devices.js`.
Bootstrap-3- oder AdminLTE-2-Assets werden nicht geladen.

Die Seite umfasst die sechs Gerätezähler, Statusfilter, den 12-Stunden-Verlauf,
die DataTable, vordefinierte Filter, Wake-on-LAN sowie den Bulk-Editor mit allen
13 vorhandenen Änderungsfeldern, Gerätesuche, Sichtbarkeitsfiltern und
Auswahlfunktionen. Links zu `deviceDetails.php` bleiben vorläufig bewusst auf
der nicht migrierten Detailseite.

## Beibehaltene Server- und Request-Verträge

Die Migration ändert weder Backenddateien noch Datenbankschema oder
Geräteabfragen. Folgende Verträge entsprechen `front/devices.php`:

| Funktion | Methode und Ziel | Parameter / Antwort |
| --- | --- | --- |
| Zähler | `GET php/server/devices.php` | `action=getDevicesTotals`, `scansource`; Zahlenarray mit zusätzlichem Presence-Wert |
| Tabelle | `GET php/server/devices.php` | `action=getDevicesList`, `scansource`, `status`; unveränderte 19-Spalten-Zeilen |
| Tabellenpräferenzen | `GET/POST php/server/parameters.php` | `Front_Devices_Rows`, `Front_Devices_Order` |
| Wake-on-LAN | `POST php/server/devices.php` | `action=wakeonlan`, `mac`, `ip` |
| Filter anlegen/löschen | `POST php/server/devices.php` | unveränderte `SetDeviceFilter`-/`DeleteDeviceFilter`-Felder |
| Bulk-Löschen | `POST php/server/devices.php` | `action=BulkDeletion`, wiederholtes `hosts[]` |
| Bulk-Ändern | `POST v4_devices.php` | `mod=bulkedit`, `savedata=yes`, bestehende Feld- und MAC-Namen, CSRF |

Die DataTables-Spaltenindizes, versteckten Hilfsspalten, Suchausschlüsse,
IP-Sortierspalte, Row-ID-Cookie und Statuswerte bleiben unverändert. Der
DataTables-Kern bleibt 1.10.25 und verwendet nur den gepinnten Bootstrap-5-
Adapter. Chart.js bleibt 3.0.2; die vorhandenen History-Daten werden ohne neue
Query in einer v3-konformen gestapelten Balkenkonfiguration dargestellt.

Der SQL-/Geschäftslogikblock der Bulk-Aktualisierung ist in
`devices-page.php` ausdrücklich markiert und aus der Altseite übernommen:
dieselben 13 Eingabeschalter, dieselbe Spaltenzuordnung einschließlich
`dev_MQTTDevice_cleanup`, dieselbe Geräteauswahl, dasselbe vorbereitete
`UPDATE` und derselbe Journal-Eintrag `a_021`. Nur das Post/Redirect/Get-Ziel
führt zurück auf `v4_devices.php`. Auch die lesende Geräteauswahl des
Bulk-Editors und die History-Helferabfrage sind unverändert.

## Bootstrap-5- und Sicherheitsanpassungen

Die Seite übernimmt Authentifizierung und Session aus dem v4-Bootstrap. POST
auf den Seiteneinstieg validiert den vorhandenen CSRF-Token; AJAX-Mutationen
laufen über `pialertPost` und erhalten denselben Schutz. Sämtliche dynamischen
Tabellenwerte werden zunächst durch den DataTables-Textrenderer behandelt;
Links, Badges und Icons entstehen danach über DOM-Methoden. In den PHP-Partials
werden variable Texte mit `h()` ausgegeben.

Modals verwenden die Bootstrap-5-Runtime der Shell. iCheck 1.0.3 wird weiterhin
lokal gepinnt und geladen, damit der Bestandspin und spätere Detailseiten den
gleichen Runtime-Vertrag behalten. Die v4-Geräteformulare verwenden native
Bootstrap-Schalter; ihr maßgeblicher Zustand ist weiterhin das echte
`input.checked`. Es gibt keine Inline-Handler, Inline-Styles oder seiteneigenen
Inline-Skripte; das einzige Script-Element im Markup enthält ausschließlich
JSON-Konfiguration als `application/json`.

## Browserprüfung

Die synthetische Kopie unter `/tmp/pialert-adminlte4-fixtures/` wurde mit
Chromium 153 auf 1440×1000 und 390×844 geprüft. Der allgemeine v4-Lauf bestand
in beiden Größen ohne HTTP-, Netzwerk-, Console- oder JavaScriptfehler und ohne
Dokumentüberlauf. Der interaktive, ausschließlich lesende Lauf
`_workspace/tests/adminlte4/browser_devices_v4.mjs` bestätigte:

- Zähler `5 / 3 / 2 / 1 / 1 / 1` und fünf sichtbare Tabellenzeilen;
- Statusfilter mit `3 / 2 / 1 / 1 / 1 / 5` Zeilen für Connected, Favorites,
  New, Down, Archived und All;
- aktive `aria-pressed`-Zustände, passende Kartentitel und aufgebautes Chart;
- 13 Bulk-Feldschalter, sechs auswählbare Datensätze einschließlich des vom
  normalen Listenendpunkt ausgeblendeten Internetdatensatzes, Suche und
  Gesamt-/Sichtbarauswahl;
- CSRF-Feld, aktivierbare Werteingaben und kein Dokumentüberlaufen im
  Desktop- oder Mobilviewport;
- mobilen Tabellencontainer mit horizontalem `overflow-x: auto`.

Keine Speichern-, Lösch-, Filter-, Wake-on-LAN- oder andere Schreibaktion wurde
ausgelöst. Die Null-Daten-Kopie unter `/tmp/pialert-adminlte4-p0/` bestand den
allgemeinen Desktop-/Mobiltest ebenfalls ohne Browser- oder HTTP-Fehler.
Ergebnisse und visuell geprüfte Screenshots liegen unter
`_workspace/adminlte4-browser-devices/`.

## Offene Grenzen

- Die nicht migrierte Gerätedetailseite bleibt ein Altfrontend-Ziel.
- Die produktiven Schreibpfade wurden aus Sicherheitsgründen nicht gegen die
  Fixture-Datenbank ausgeführt; geprüft sind Formular-/Requestaufbau, CSRF und
  die unverändert übernommene serverseitige Logik.
- iCheck-Touch-/Screenreaderfälle und große produktive Datenmengen sind durch
  die Komponentenprobe beziehungsweise diesen Seitenlauf nicht vollständig
  abgenommen.
- Ein vordefinierter Filter blendet wie im Bestand die DataTables-Suche nicht
  aus dem DOM aus; die v4-Seite lässt sie sichtbar, damit der aktive Suchwert
  transparent und auf Mobilgeräten änderbar bleibt. Der Backendvertrag bleibt
  dabei unverändert.

Für die Root-Integration muss ausschließlich die bereits vorhandene v4-Route
`home => v4_devices.php` beibehalten werden. Änderungen an `shell.php`,
`paths.php`, Altseiten oder Backend sind für diese Seite nicht erforderlich.
