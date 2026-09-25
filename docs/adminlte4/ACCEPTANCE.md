# AdminLTE 4: technischer Zwischenbericht, keine Schlussabnahme

Stand: 23. September 2026. Die 17 bisherigen PHP-Einstiege und `ui_settings.php` verwenden nun unter ihren Standardnamen AdminLTE 4. Die frühere Oberfläche liegt außerhalb des Webroots unter `_archive/adminlte2-2026-09-23/`; eine vollständige frühere Referenz liegt zusätzlich unter `_workspace/adminlte2-reference/front/`. P6 ist noch nicht vollständig abgenommen, P7 (Nutzerbestätigung) ist offen.

## Nachweise

| Prüfung | Ergebnis |
| --- | --- |
| Integrierter Chromium-153-Lauf mit synthetischen Daten | Nach der Standardrouten-Umschaltung 36/36 Desktop-/Mobilansichten über 18 Routen, 1440×1000 und 390×844, ohne externe Requests, protokollierte HTTP-/Netzwerk-/Console-/JavaScriptfehler oder Dokumentüberlauf. Ergebnis: `_workspace/adminlte4-browser-shell/final-2026-09-23/all-pages-results.json`. |
| Wartung: Filter- und Satellitenaktionen | Reine Icon-Buttons mit lokal geladener Bootstrap-Icons-Schrift 1.13.1 auf Desktop und Mobil sichtbar; Save/Delete mit Tooltip und zugänglichem Namen. Bestehende Aktionen mit gemockten Schreibaufrufen geprüft. Ergebnis und Bilder: `_workspace/adminlte4-browser-shell/final-2026-09-23/maintenance-buttons-result.json`, `mobile-filter-editor.png`, `desktop-satellite-actions.png`, `mobile-satellite-actions.png`. |
| Diagnose-Seiten | Alle drei Diagnose-Seiten erreichbar und mit Rücklink zu `maintenance.php`, ohne externe Requests. JSON-Test: 60/60 lesende Aufrufe; `&parameter=Front_Devices_Rows` korrekt angezeigt und gesendet. Ergebnis: `_workspace/adminlte4-browser-shell/final-2026-09-23/json-calls-result.json`. |
| Report-Aktionen | Archivieren und Löschen eines synthetischen Reports über `reports.php` jeweils mit HTTP 303 und korrektem Dateizustand getestet; keine Produktionsdaten verändert. |
| Login und lokale Sonderseiten | Loginformular bei aktivierter Web-Protection sowie vier Auth-Weiterleitungen auf `index.php` geprüft. Alle 30 lokalen Neustart-/Shutdown-Seiten und fünf erhaltene gemeinsame Assets liefern HTTP 200. Ein gespeicherter Remote-Favicon-Pfad wird auf ein vorhandenes lokales Favicon abgebildet, ein unbekannter auf den lokalen Standard. |
| Systemaktionen | Die Frontend-Navigation nach Neustart-/Shutdown-Antworten wurde mit gemockten Antworten auf lokale Warteseiten geprüft; externe oder unpassende Ziele werden nicht übernommen. Echte Neustart-/Shutdown-Aktionen wurden nicht ausgelöst. |
| PHP-Regression | Zehn Tests aus `_workspace/tests/php/` sowie `_workspace/tests/adminlte4/ui_settings_test.php` bestanden. Legacy-spezifische Quellprüfungen lesen jetzt das Archiv, aktive Sicherheits- und Nmap-Prüfungen den v4-Code. |
| Geschützte Quellreferenz | Das ursprüngliche 4.685-Dateien-Manifest ist wegen der beauftragten Archivierung nicht mehr als unverändertes Arbeitsbaum-Manifest nutzbar. Die 103 geschützten Dateien unter `back/`, `front/api/`, `front/download/` und `front/php/server/` stimmen weiterhin bytegleich mit der Referenz überein. |

## Offene Abnahmepunkte

- Die v4-Wartungsseite zeigt Satelliten-Token, Passwort und daraus erzeugtes Installationskommando noch nicht. Eine automatische Sicherheitsprüfung lehnte die zusätzliche Ausgabe sensibler Zugangsdaten ab. Die v2-Seite liegt jetzt nur im Archiv und ist nicht mehr live erreichbar; damit fehlt dieser Bedienpfad vorerst. Eine sichere Gestaltung und ausdrückliche Freigabe sind nötig, bevor diese Parität hergestellt werden kann.
- Schreibende CRUD-, Konfigurations-, Scan- und Systemaktionen wurden überwiegend nur mit Mocks oder isolierten PHP-Tests geprüft. Echte End-to-End-Abläufe gehören in eine eigens dafür vorgesehene Testinstallation, nicht in den Produktionsbestand.
- Neben lokalem Chromium fehlen Nachweise für aktuelle Firefox-/Safari-Desktop-Engines sowie aktuelle Android- und iOS-Browser beziehungsweise Geräte. Ältere WebViews sind laut Nutzer kein Pflichtziel.
- Barrierefreiheit, Druckansichten, gleichzeitiges Alt-/Neu-Tab-Polling und Timer sowie Detailvergleich dynamischer Datenzugriffsblöcke sind noch nicht vollständig abgenommen.
- Die Funktionsmatrix muss für alle 17 Seiten mit erfolgreichen und negativen Interaktionsfällen abschließend ausgefüllt werden; ein Seiten-Smoke-Test allein beweist keine vollständige Parität.

Der Status „technisch fertig“ aus dem [Implementierungsplan](../../_workspace/CODEX_PLAN_ADMINLTE4_MIGRATION.md) ist damit noch nicht erreicht.
