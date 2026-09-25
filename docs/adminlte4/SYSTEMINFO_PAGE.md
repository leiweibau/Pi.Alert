# AdminLTE-4-Seite: Systeminformation

Stand: 23. September 2026

## Umfang

`front/v4_systeminfo.php` ist der gleichstufige, authentisierte v4-Einstieg
für die Systeminformation. Die Altseite `front/systeminfo.php`, Serverendpunkte,
Datenbanken und Abfragen bleiben unverändert. Die Seite verwendet nur den
lokalen v4-Asset-Stack.

## Erhaltene Verträge

- Der Einstieg akzeptiert nur `GET`; ohne Anmeldung erfolgt die Weiterleitung
  zu `v4_index.php`.
- `#pageTitle`, `#sys_info_gen_head` und `#resolution` bleiben vorhanden. Der
  bestehende Titel `System Infomation` bleibt wegen des Referenzvertrags
  einschließlich seiner Schreibweise erhalten.
- Die Anzeigen für Client, lokales System, Satelliten, Datenbanken, Cronjobs,
  Datenträger, Netzwerk, laufende Dienste und USB-Geräte bleiben erhalten.
- Die Satellitenabfrage, System-Zeitzonenabfrage und Tabellen-/Zeilenzählung
  verwenden dieselben SQL-Texte, Datenbanken, Tabellen und Auswahlbedingungen
  wie die Altseite. Zugriffe sind auf read-only SQLite-Verbindungen beschränkt.
- `askPialertReboot`, `PialertReboot`, `askPialertShutdown` und
  `PialertShutdown` bleiben global erreichbar. Die bestätigten Aktionen senden
  weiterhin mit `pialertPost` an `php/server/commands.php` und verwenden die
  Aktionsnamen `PialertReboot` beziehungsweise `PialertShutdown`. Dadurch
  bleiben CSRF-Header, Operation-ID und Parallelanforderungsschutz der
  gemeinsamen v4-Browserlogik aktiv.

## Grenzen und Sicherheit

Die Seite zeigt lokale Hostinformationen an und führt dafür nur lesende
Systemabfragen aus. Browserprüfungen dürfen die Bestätigungsdialoge öffnen,
aber die Aktionsbuttons in den Dialogen nicht bestätigen. Weder Reboot noch
Shutdown oder andere schreibende Systemaktionen sind Teil des Seitentests.

Die angezeigten Host-, Session-, Datenbank- und Satellitenwerte werden vor der
HTML-Ausgabe escaped. Die einzige gezielt erzeugte HTML-Struktur innerhalb
eines Wertes ist der lokal erzeugte, URL-kodierte Link zur bestehenden
Gerätedetailseite eines Satelliten.

## Responsive Verhalten

Lange Werte umbrechen, Tabellen erhalten auf schmalen Viewports einen lokalen
horizontalen Scrollbereich, und umfangreiche Datenbank-/Dienstlisten scrollen
innerhalb ihrer Karte. Tabs verwenden ausschließlich die Bootstrap-5-API.

## Prüfergebnis

Die Seite wurde in einer eigenen Kopie der synthetischen Installation mit
Chromium 153 bei 1440 × 1000 und 390 × 844 geprüft. In beiden Ansichten waren
`#sys_info_gen_head`, Shell, Tabs, Auflösungsanzeige und die vier globalen
Aktionsfunktionen vorhanden. Der Tools-Datenbanktab sowie beide
Bestätigungsdialoge funktionierten. Es gab kein horizontales Überlaufen und
keine HTTP-, Netzwerk-, Console- oder JavaScriptfehler. Während des gesamten
Laufs ging kein Request an `commands.php`; keine Systemaktion wurde ausgelöst.
PHP- und JavaScript-Syntaxprüfungen waren erfolgreich.
