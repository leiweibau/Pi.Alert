# AdminLTE-4-Migration: Status

Stand: 24. September 2026

| Paket | Status | Nachweis / Stand | Nächster Schritt |
| --- | --- | --- | --- |
| P0 – Referenz, Inventar und Prüfgrundlage | abgeschlossen | 17 Alt-Einstiege inventarisiert; schreibgeschützte Referenz, Quellmanifest mit 4.685 Einträgen und synthetische Fixture vorhanden. Der dokumentierte Legacy-Null-Datenfehler bleibt Referenzbefund. | Kein P0-Rest; Referenz bis zur Nutzerabnahme bewahren. |
| P1 – Pfade, Session und paralleler Zugang | teilgeprüft | Alle 17 ursprünglichen Einstiegspunkte besitzen v4-Gegenstücke; Login, Session, Remember-me, CSRF und Logout wurden isoliert geprüft. | Reale Sessionabläufe und Schreibpfade in einer Testinstallation prüfen. |
| P2 – Bibliotheken, Layout und gemeinsame Komponenten | teilgeprüft | AdminLTE 4.9.1, Bootstrap 5.3.8 und jQuery 3.6.2 lokal gepinnt; Shell, Farbmodus und responsive Navigation in Chromium geprüft. | Weitere Browser-Engines, Tastaturbedienung, Kontrast und Druck prüfen. |
| P3 – Gemeinsame Browserlogik und Server-HTML | teilgeprüft | Request-, CSRF-, Modal-/Toast- und Shell-Runtime integriert; zentrale Browserfälle und gemockte Schreibaufrufe bestanden. | Echte Schreibverträge und Serverfragmente unter Testdaten abnehmen. |
| P4 – Komponenten | teilgeprüft | FullCalendar Scheduler, DataTables, Coloris, iCheck und Chart.js in v4-Seiten integriert; isolierte und integrierte Chromium-Fälle bestanden. | Komponenten über weitere Browser und Grenzfälle prüfen. |
| P5 – Seitenmigration | umgesetzt, teilgeprüft | Alle 17 bisherigen PHP-Einstiege tragen jetzt als AdminLTE-4-Seiten ihre Standardnamen, dazu `ui_settings.php`. Die v2-Seiten und ausschließlich alte Assets liegen außerhalb des Webroots im Root-Archiv. | Funktionsmatrix mit echten Interaktionen und Negativfällen vollständig abarbeiten. |
| P6 – Gesamtprüfung | in Arbeit | Nach Standardrouten-Umschaltung 36/36 Chromium-Ansichten ohne externe Requests/Browserfehler, drei Diagnose-Seiten einschließlich 60/60 JSON-Aufrufen, elf PHP-Tests und isolierte Report-POST-Aktionen bestanden. Die Quellreferenz ist wegen der beauftragten Archivierung kein unverändertes Arbeitsbaum-Manifest mehr. | Offene Schreib-, Sicherheits-, Barrierefreiheits-, Druck-, Polling- und Browserplattform-Fälle im Zwischenbericht abarbeiten. |
| P7 – Nutzerabnahme | offen | Technische Vollabnahme P6 fehlt noch. | Funktionsparität nach P6 bestätigen; Altfrontend bleibt bis dahin erhalten. |

## P0: Festgestellte Grenzen und offene Nachweise

Die Quellreferenz umfasst den statischen und PHP-Quellbestand unter `front/`
außer Laufzeitdaten in `front/reports/`, `front/satellites/` und `front/php/tmp/`
sowie den gesamten geschützten Backendbestand unter `back/` und
`front/php/server/`. Diese Laufzeitverzeichnisse werden nicht gehasht, damit
normale Berichte, Satellitendaten und temporäre Dateien keinen Codeunterschied
vortäuschen.

Eine isolierte Testinstallation mit dem Beispielkonfigurationsprofil und
schema-only SQLite-Datenbanken steht unter `/tmp/pialert-adminlte4-p0/`
bereit. Sie enthält keine Anwendungsdaten. Gegen sie wurden ausschließlich
lesende HTTP-Aufrufe ausgeführt; schreibende Web- und Systemaktionen bleiben
für ausdrücklich eingerichtete Testdaten vorbehalten.

Eine zweite isolierte Kopie unter `/tmp/pialert-adminlte4-fixtures/` enthält
ausschließlich synthetische, auf den 23. September 2026 geankerte Daten. Der
Browsernachweis umfasst dort Devices, Services und Presence einschließlich
Zählern, Tabellenzeilen, Servicekarten, Diagramm und Ressourcen-Timeline.

Der Nutzer hat am 23. September 2026 aktuelle Desktop-Browser sowie aktuelle
Android- und iOS-Browser als verbindliche Browserbasis bestätigt. Ältere
Tablets und nicht aktualisierbare WebViews sind kein Pflichtziel. Die bisherige
automatisierte Prüfung deckt davon nur Chromium 153 auf Desktop- und
Mobilviewport ab; reale Android-/iOS-Geräte und weitere Desktop-Engines bleiben
für die Gesamtprüfung offen.

Chromium 153 ist lokal installiert und headless ausführbar. Der gültige Lauf
verwendete eine von `index.php` erzeugte, persistente PHP-Session. Für alle 13
Hauptseiten wurden endgültige URL, Dokumenttitel und individueller DOM-Marker
geprüft. Maschinenlesbare Ergebnisse und ausgewählte Desktop-/Mobilbilder
liegen unter `_workspace/adminlte4-browser-reference/`; der Lauf ist mit
`_workspace/tests/adminlte4/browser_reference.mjs` wiederholbar. Details und
der reproduzierte Maintenance-Null-Datenfehler stehen in `TEST_ENVIRONMENT.md`.

Der Nutzer hat am 23. September 2026 den Wechsel auf v4 und danach die
Archivierung der v2-Seiten beauftragt. Die frühere Oberfläche wurde zunächst
unter `_workspace/adminlte2-reference/front/` mit 4.617 geprüften Dateien
gesichert. Die 17 alten Einstiege und unbenötigten Assets liegen jetzt unter
`_archive/adminlte2-2026-09-23/` außerhalb des Webroots; die aktiven
Standardnamen unter `front/` bezeichnen AdminLTE-4-Seiten. Ein Live-Link zur
alten Oberfläche besteht nicht mehr.

## Änderungsjournal

| Datum | Paket | Änderung | Prüfung |
| --- | --- | --- | --- |
| 2026-09-22 | P0 | Inventar-, Vertrags- und Funktionsmatrix angelegt. | Statische Quellsuche. |
| 2026-09-22 | P0 | Quellreferenz und synthetische lokale Testkopie angelegt. | 4.685 Hashes vollständig verifiziert; 13 Hauptseiten und die Login-Weiterleitung per lokalem HTTP abgerufen. |
| 2026-09-23 | P0 | Bisheriges Frontend außerhalb des Webroots als schreibgeschützte Referenz gesichert. | 4.617 Dateien vor/nach Kopie per SHA-256 verglichen. |
| 2026-09-23 | P0 | Browserreferenz mit echter Session sowie synthetische Devices-/Services-/Presence-Fälle abgeschlossen. | 13/13 Ziel-URLs und DOM-Marker; Chromium 153; Desktop-/Mobilbilder visuell geprüft; Fixture-Lauf ohne HTTP-/Console-/JavaScript-Fehler. |
| 2026-09-23 | P1/P2 | Entwicklungsstandard auf v4-Einstieg umgestellt; Login, Shell und lokale Assets integriert. | PHP-Syntax, isolierte Auth-/Session-Fälle und Chromium-Screenshot bestanden; umfassende Funktionsparität offen. |
| 2026-09-23 | P4 | FullCalendar 3.10.5, Scheduler 3.10.4 und Moment 2.24.0 für v4 gepinnt. | SHA-256/Lizenzen geprüft; synthetische Ressourcen-Timeline in Chromium mit drei Ereignissen ohne Lizenzwarnung. |
| 2026-09-23 | P4 | DataTables-Kern 1.10.25 und kompatiblen Bootstrap-5-Adapter 1.10.25 gepinnt. | Sortierung, Suche, Paging und schmaler Tabellenviewport in Chromium geprüft; SHA-256/Lizenzen verifiziert. |
| 2026-09-23 | P4 | Coloris 0.24.0 bytegleich gepinnt; ungültigen Bestandsaufruf `Coloris.init()` und mobile Pill-Breite dokumentiert. | Isolierte Bootstrap-5-Modalprobe in Chromium auf Desktop/Mobil; Integrität und Lizenz geprüft. |
| 2026-09-23 | P4 | iCheck 1.0.3 und Chart.js 3.0.2 bytegleich gepinnt; stille Chart.js-v2-Konfigurationsabweichung des Alt-Dashboards belegt. | SHA-256-/Lizenzprüfung und isolierte Chromium-Proben für Checkboxzustände sowie Balken-, Donut- und Liniendiagramme. |
| 2026-09-23 | P5 | Devices Events nach v4 portiert. | Chromium Desktop/Mobil mit synthetischen sieben Zeilen und gemockten Parameter-POSTs ohne Browserfehler. |
| 2026-09-23 | P5 | Systeminfo nach v4 portiert und mit Events in Navigation/Hotkeys integriert. | Read-only Chromium-Lauf der vier v4-Seiten mit 8/8 Desktop-/Mobilansichten ohne Browserfehler; Systemaktionen nicht ausgelöst. |
| 2026-09-23 | P5 | Journal mit Coloris und DataTables nach v4 portiert und in Navigation/Hotkeys integriert. | Chromium Desktop/Mobil: Filter, XSS-Textausgabe, Picker/Modal, zwei gemockte Parameter-POSTs ohne Browserfehler. |
| 2026-09-23 | P0 | Browserzielbasis vom Nutzer festgelegt: aktuelle Desktop-Browser sowie aktuelle Android-/iOS-Browser; ältere Tablets/WebViews nicht verpflichtend. | Abnahmematrix auf diese Plattformen begrenzt; reale Geräte-/Engine-Läufe bleiben offen. |
| 2026-09-23 | P5 | Integrierten v4-Smoketest nach Journal-Einbindung wiederholt. | 10/10 Desktop-/Mobilansichten der fünf v4-Routen ohne HTTP-, Netzwerk-, Console- oder JavaScriptfehler und ohne Dokumentüberlauf. |
| 2026-09-23 | P5 | Geräte-Hauptseite samt Bulk-Modus sowie Reports nach v4 portiert; Reports in Sidebar, Benutzermenü und Hotkey integriert. | Interaktive Chromium-Tests mit synthetischen Daten; alle Schreibpfade unbestätigt oder gemockt; integrierter Smoke-Test 12/12 Ansichten ohne Browserfehler. |
| 2026-09-23 | P2/P5 | v4-Shell und Updatecheck-Seite browsergeprüft; helles Sidebar-Theme kontrastkorrigiert. | Desktop-/Mobil- und Hell-/Dunkel-Läufe sowie gemockte Updatecheck-Antworten; finaler heller Screenshot visuell geprüft. |
| 2026-09-23 | P2/P5 | v4-eigene dateibasierte UI-Konfiguration, zentrale Devices-Spaltenregistry, Einstellungsseite und authentifizierten Speicher-Endpunkt ergänzt. | Isolierte PHP-Tests, HTTP-Status 401/403/422/409, Speichern→Reload sowie Chromium Desktop/Mobil 4/4 ohne Fehler; keine Produktionskonfiguration geschrieben. |
| 2026-09-23 | P5 | Services-Liste, Service-Details und ICMP-Liste in die v4-Navigation integriert; ICMP-Header verwendet die neue UI-Datei. | Integrierter synthetischer Chromium-Smoketest 18/18 Desktop-/Mobilansichten über neun Routen ohne Browserfehler oder Dokumentüberlauf; schreibende Seitenaktionen separat gemockt. |
| 2026-09-23 | P2/P5 | Klassische Skin-Auswahl aus der v4-Einstellungsseite entfernt; bestehende Skin-Werte bleiben nur zum Lesen im Dateiformat erhalten. | PHP-Regressionstest für Werterhalt und nicht schreibbaren Skin-Patch; Chromium Desktop/Mobil 2/2 ohne Browserfehler. |
| 2026-09-23 | P2/P5 | Zwei AdminLTE-4-Farbwähler für Sidebar/Header mit lokal gepinnter Palette ergänzt; Darkmode unabhängig vom Legacy-Theme und mit Sofortvorschau repariert. | Palette bytegleich mit offiziellem v4.9.1-Tag; PHP-Schema-1/2-Tests; Chromium-Farbwerte, Hell-/Dunkelwechsel und Speichern→Reload bestanden; integrierter Smoke-Test 18/18. |
| 2026-09-23 | P5/P6 | Alle 17 Alt-Einstiege als v4-Gegenstücke integriert; GUI-Link, Sidebar, Netzwerk-Icon und Sicherheits-Anordnung nach Nutzerfeedback angepasst. | Integrierter Chromium-Lauf über 18 Routen bei 1440×1000 und 390×844: 36/36 bestanden; Wartungsdialog-Test bestanden. |
| 2026-09-23 | P6 | JSON-Debugseite um lesende Endpunkte ergänzt und HTML-Entity-Anzeigefehler bei `&parameter` korrigiert. | 60/60 JSON-Aufrufe bestanden; angezeigter und gesendeter Parameter im Browser geprüft. |
| 2026-09-23 | P6 | PHP-Regression und Referenzmanifest erneut geprüft. | Zehn PHP-Tests und UI-Settings-Test bestanden; nur die beauftragten Änderungen an `front/index.php` und `front/php/debugging/test_json_calls.php` weichen vom Altquellmanifest ab. |
| 2026-09-23 | P2/P5 | Bootstrap Icons 1.13.1 lokal für v4 gepinnt; GUI-Filtereditor und Satellitenverwaltung nutzen wieder Icon-Buttons mit Tooltip und zugänglichem Namen. | Schriftdatei und CSS im isolierten Chromium-Lauf geladen; Speichern/Löschen auf Desktop und Mobil visuell sowie mit gemockten Aufrufen geprüft. |
| 2026-09-23 | P5/P6 | Alte v2-Seiten und unbenötigte Assets ins Root-Archiv verschoben; v4-Seiten auf Standardnamen gesetzt, Navigation/Diagnose-Rückwege umgestellt und lokales Favicon erzwungen. Report-POST-Aktionen in die aktive Seite übernommen. | 36/36 Chromium-Ansichten ohne externe Requests oder Browserfehler; drei Diagnose-Seiten und 60/60 JSON-Aufrufe; Report-Archivieren/Löschen nur mit synthetischen Daten; Login/Redirects; 30 statische Warteseiten und fünf gemeinsame Assets HTTP 200; elf PHP-Tests und 103 geschützte Backend-/API-/Download-Hashes bestanden. |
| 2026-09-23 | P5/P6 | Systeminfo führt nach Neustart-/Shutdown-Antworten wieder zu der passenden lokalen Warteseite. | Reboot-/Shutdown-Antworten nur gemockt; 2-Sekunden-Navigation und Ablehnung externer/falscher Ziele isoliert geprüft. Keine echte Systemaktion ausgelöst. |
| 2026-09-24 | P5/P6 | Erste und letzte Sitzung in der Device-Tabelle auf 11 rem Mindestbreite und einzeilige Darstellung gesetzt. | Synthetischer Chromium-Test auf Desktop und Mobil: zwei sichtbare Zeitspalten, mindestens 215 px tatsächlich gerendert, kein Umbruch/Abschneiden und kein Dokumentüberlauf. |
| 2026-09-24 | P5/P6 | Zeitspalten auf 10 rem reduziert; WoL als sichtbaren, zugänglichen Button für unterstützte Gerätetypen dargestellt; „New Online“ und „New Offline“ wieder mit getrennten Grün-/Grau-Gelb-Verläufen für Hell- und Dunkelmodus. | Synthetische Chromium-Läufe bei 1440×1000 und 390×844: Zeitspalten 199 px ohne Umbruch/Abschneiden, WoL nur beim passenden Laptop mit korrektem Bestätigungsdialog, beide New-Zustände und dunkler Offline-Verlauf geprüft; keine echte WoL-Aktion ausgelöst. |
| 2026-09-24 | P5/P6 | Gerätedetailfelder erhalten wieder sichtbare, nach Backend-Reihenfolge gruppierte Dropdowns für Besitzer, Typ, Gruppe, Ort, Netzwerkknoten, Verbindungstyp und Link-Speed. Auf Desktop stehen die Labels links vor den Feldern, auf Mobil darüber. | Synthetischer Chromium-Test bei 1440×1000 und 390×844: Vorschläge aus sieben unveränderten Backend-Endpunkten, freie Eingabe, Auswahl-/Dirty-/Restore-Verhalten und Netzwerkknoten-ID geprüft; keine produktive Änderung gespeichert. |
| 2026-09-24 | P5/P6 | Entitäten in den Gerätedetail-Labels „Eigen­tümer“ und „Serien Nr.“ korrekt dargestellt, „Scan source“ aus dem Formular entfernt und nur die Ereignis-/Alarm-Schalter an den rechten Spaltenrand gerückt. | Deutscher synthetischer Chromium-Lauf auf Desktop und Mobil: beide Labeltexte korrekt, kein Scan-Source-Feld, alle fünf Alarm-Labels einzeilig neben den Schaltern, kein Überlauf oder Browserfehler. |
| 2026-09-24 | P5/P6 | Ereignis-/Alarm-Schalter auf ein 9:3-Raster zurückgenommen; in der Zeile „Zufällige MAC“ stehen Statussymbol und Text im 9er-, das Info-Symbol im 3er-Bereich. | Deutscher synthetischer Chromium-Lauf auf Desktop und Mobil: gemessenes Rasterverhältnis 3:1, Info-Symbol mit Schaltern ausgerichtet, alle fünf Labels einzeilig und kein Überlauf oder Browserfehler. |
| 2026-09-24 | P5/P6 | Geräte- und ICMP-Liste sowie beide Detailseiten verwenden für ihre Header-Widgets die Höhe der Anwesenheitsseite. Fehlende AdminLTE- und Bootstrap-Source-Map-Verweise aus lokalen minifizierten Assets entfernt. | Deutscher synthetischer Chromium-Lauf auf fünf Seiten: alle Widgets 88,4 px auf Desktop und 73,6 px mobil, ohne Inhalts-/Dokumentüberlauf oder Browserfehler; verbleibende Source-Map-Datei ist lokal vorhanden. |
| 2026-09-24 | P5/P6 | History-Canvas auf Geräte- und ICMP-Liste auf 180 px gesetzt; Ereignis-Widgets ebenfalls an die Anwesenheits-Widgets angeglichen; zentrale v4-Seitentitel auf 1,6 rem reduziert. | Deutscher synthetischer Chromium-Lauf auf sechs Seiten: beide Canvas 180 px auf Desktop und Mobil, Kartenhöhen passen sich an; alle Widget-Höhen 88,4/73,6 px; alle Seitentitel 1,6 rem; kein Überlauf oder Browserfehler. |
