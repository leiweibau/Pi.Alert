# Kalender-Kompatibilität für AdminLTE 4

Stand: 23. September 2026

## Entscheidung

Die AdminLTE-4-Migration behält den bestehenden Kalender-Stack zunächst
unverändert bei: Moment 2.24.0, FullCalendar 3.10.5 und FullCalendar Scheduler
3.10.4. Die Ressourcen-Timeline ist eine Scheduler-Premium-Funktion. Es wurde
deshalb weder eine neue Premium-Version heruntergeladen noch ein neuer
Lizenz- oder Abonnementvertrag vorausgesetzt.

Die tatsächlich verwendeten Laufzeitdateien liegen nun zusätzlich, bytegleich
und versionsgepinnt unter `front/lib/legacy-calendar/`. Der bestehende Baum
`front/lib/` blieb unverändert. Die Kopie ist Vorbereitung für eine spätere
Seitenmigration; keine produktive Seite wurde in diesem Schritt umgeschaltet.

## Bestehende Ladepfade

Die Prüfung der PHP-Seiten ergab:

| Seite | Moment | FullCalendar CSS/Print-CSS/JS/Locale | Scheduler CSS/JS | Nutzung |
| --- | --- | --- | --- | --- |
| `front/presence.php` | ja | ja | ja | Ressourcen-Timeline; setzt `schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source'` |
| `front/deviceDetails.php` | ja | ja | nein | initialisiert FullCalendar ohne Scheduler |
| `front/icmpmonitorDetails.php` | ja | ja | nein | lädt die FullCalendar-Dateien, enthält derzeit aber keine eigene `fullCalendar()`-Initialisierung |
| `front/serviceDetails.php` | ja | ja | nein | lädt die FullCalendar-Dateien, enthält derzeit aber keine eigene `fullCalendar()`-Initialisierung |

Alle vier Seiten laden in dieser Reihenfolge `moment.js`,
`fullcalendar.min.js`, dann `locale-all.js`. Nur `presence.php` ergänzt danach
`scheduler.min.js`; die zugehörigen Styles sind FullCalendar, Druck-Styles und
auf `presence.php` zusätzlich Scheduler. jQuery wird vorher durch
`front/php/templates/footer.php` geladen. Die unbenutzten Bibliothekskopien im
Unterordner `front/lib/fullcalendar-scheduler/lib/` gehören nicht zu diesem
Laufzeitpfad und wurden daher nicht übernommen.

## Gepinnte Dateien und Integrität

| Paket / Lizenz | Datei | SHA-256 |
| --- | --- | --- |
| Moment 2.24.0 | `moment-2.24.0/moment.js` | `1fd8c0cfffd02e40cecbf9f313d1b86988a342d90bb7d16f1a67544f0064ea0b` |
| Moment, MIT | `moment-2.24.0/LICENSE` | `8f38f320bbf5eb84c08e08676f7ee1d2204ebe5797f6a090d077329cf212fca3` |
| FullCalendar 3.10.5 | `fullcalendar-3.10.5/fullcalendar.min.css` | `207c35d2a317b6544cfd5fe992ed40a9222c7c7037cdf9030430a0bce491039a` |
| FullCalendar 3.10.5 | `fullcalendar-3.10.5/fullcalendar.print.min.css` | `08f10f1444ac85af080ef21485d909ab1a8581e02b29498fddb81568a3de6e14` |
| FullCalendar 3.10.5 | `fullcalendar-3.10.5/fullcalendar.min.js` | `ec9f750e1712f9fbe08e6655cedd90c44b6f644ee6cec0b26a1e7b0dcab555e5` |
| FullCalendar 3.10.5 | `fullcalendar-3.10.5/locale-all.js` | `b966be1db984da05a18308310b05ecfcfeab70bff64aed85f9f53a6e0ec5eb67` |
| FullCalendar, MIT | `fullcalendar-3.10.5/LICENSE.txt` | `fade99398092f16b979536aa19446720caef093b6c256922c6f4d562409f6152` |
| Scheduler 3.10.4 | `fullcalendar-scheduler-3.10.4/scheduler.min.css` | `16ddb9c498fb825f69770320a403e60b2a458616dbd96daeffdc73ab815fc339` |
| Scheduler 3.10.4 | `fullcalendar-scheduler-3.10.4/scheduler.min.js` | `2d213cb86740960dab227598ff99310d1ee94d0fcb448a7495656768aed798d1` |
| Scheduler-Lizenzhinweis | `fullcalendar-scheduler-3.10.4/LICENSE.txt` | `5bb7b168b6d683773fedb3bb1b1cae69fdecbe15c57d1de8c32b82ae11a9409e` |
| gewählte GPLv3-Lizenz | `fullcalendar-scheduler-3.10.4/LICENSE.GPL-3.0.txt` | `3972dc9744f6499f0f9b2dbf76696f2ae7ad8af9b23dde66d6af86c9dfb36986` |

Die Versionsangaben stammen aus den Laufzeitdateien selbst
(`moment.version`, FullCalendar-/Scheduler-Banner und Runtime-API). Sämtliche
Laufzeit- und Upstream-Lizenzkopien wurden mit `cmp` gegen ihre Quellen unter
`front/lib/` geprüft. `front/lib/legacy-calendar/SHA256SUMS` enthält die
vollständige maschinenlesbare Liste. Reproduzierbare Prüfung:

```sh
cd front/lib/legacy-calendar
sha256sum -c SHA256SUMS
```

## Ladefolge für eine spätere v4-Seite

CSS:

1. `front/lib/adminlte-4.9.1/css/adminlte.min.css` (enthält den
   Bootstrap-5-CSS-Stack)
2. `fullcalendar-3.10.5/fullcalendar.min.css`
3. `fullcalendar-3.10.5/fullcalendar.print.min.css` mit `media="print"`
4. nur für die Ressourcenansicht:
   `fullcalendar-scheduler-3.10.4/scheduler.min.css`

JavaScript:

1. `front/lib/jquery-3.6.2/jquery.min.js`
2. `moment-2.24.0/moment.js`
3. `fullcalendar-3.10.5/fullcalendar.min.js`
4. `fullcalendar-3.10.5/locale-all.js`
5. nur für die Ressourcenansicht:
   `fullcalendar-scheduler-3.10.4/scheduler.min.js`
6. `front/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js`
7. `front/lib/adminlte-4.9.1/js/adminlte.min.js`
8. seitenbezogene Kalenderinitialisierung

Weder Bootstrap-3-Assets noch die gebündelten Alternativkopien aus dem alten
Scheduler-Verzeichnis dürfen zusätzlich geladen werden.

## Isolierte Browserprobe

Die statische Probe
`_workspace/tests/adminlte4/legacy-calendar/compatibility.html` enthält nur
synthetische Ressourcen und Ereignisse, führt keine Backend-Abfrage aus und
deaktiviert Bearbeitung und Drag/Resize. Sie lädt die oben beschriebenen
gepinnten Dateien gemeinsam mit jQuery 3.6.2, Bootstrap 5.3.8 und AdminLTE 4.9.1.

Geprüft wurde headless mit Chromium 153 bei 1440 × 900 Pixeln. Ergebnis:

- Timeline sichtbar, drei benannte Ressourcenzeilen und drei farbige Ereignisse
  korrekt den Ressourcen zugeordnet;
- Runtime-Versionen jQuery 3.6.2, Moment 2.24.0, FullCalendar 3.10.5 und
  Scheduler 3.10.4 erkannt;
- Bootstrap- und AdminLTE-Runtime geladen;
- keine JavaScript-Fehler der Probe und keine Scheduler-Lizenzwarnung;
- DOM-Prüfstatus `data-probe-status="pass"`.

Der visuell geprüfte Screenshot liegt dauerhaft unter
`_workspace/adminlte4-browser-reference/calendar-compatibility-1440x900.png`.

Damit ist die bestehende Scheduler-Timeline technisch mit dem gewählten
AdminLTE-4-/Bootstrap-5-Stack lauffähig. Das ist kein Freibrief für ein
Scheduler-Upgrade: Datenvertrag, responsive Detailansicht, Dark Mode und die
vollständige produktive `presence.php` werden erst bei deren eigentlicher
Portierung erneut getestet.

## Lizenzhinweis

FullCalendar 3.10.5 und Moment 2.24.0 stehen unter MIT. Der vorhandene
FullCalendar Scheduler 3.10.4 ist laut mitgeliefertem Hinweis tri-lizenziert
(kommerziell, CC BY-NC-ND oder GPLv3). Pi.Alert steht unter GPLv3 und die
bestehende Seite wählt ausdrücklich den Open-Source-Schlüssel
`GPL-My-Project-Is-Open-Source`; deshalb wird hier die GPLv3-Option mitsamt
Lizenztext erhalten. Eine neue Scheduler-Version kann andere Lizenzbedingungen
haben und benötigt vor jeder Aktualisierung eine eigenständige Lizenzprüfung.
