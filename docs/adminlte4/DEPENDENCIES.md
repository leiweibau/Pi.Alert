# Tatsächlich geladene Frontend-Abhängigkeiten (P0)

Stand der Inventur: 22. September 2026. Die Angaben beruhen auf den
`<script>`- und `<link>`-Einbindungen der bestehenden PHP-Seiten sowie auf den
tatsächlich ausgelieferten lokalen Dateien. Ein Bibliotheksordner allein wurde
nicht als aktive Abhängigkeit gewertet.

## Prüfmethode und Grenzen

- Die Standardseiten binden `php/templates/header.php` und
  `php/templates/footer.php` ein. Diese beiden Templates bestimmen die
  Basisabhängigkeiten.
- `dashboard.php` hat einen eigenen Kopf und lädt die dort genannten Dateien
  selbst. `index.php` hat einen eigenen, kleineren Login-Stack.
- Seitenspezifische Bibliotheken wurden nur dann als aktiv erfasst, wenn die
  Seite sie direkt einbindet und ihr eigener Code deren API aufruft.
- PHP-Bedingungen können eine Einbindung abhängig von Konfiguration oder
  Ansicht unterdrücken. Die Tabelle beschreibt deshalb den durch Quellcode
  möglichen Ladepfad, nicht einen einzelnen, zur Laufzeit aufgezeichneten
  Browser-Trace.
- SHA-256 bezieht sich auf die genannte Datei im Arbeitsbaum zum oben genannten
  Zeitpunkt. Eine spätere Aktualisierung muss die Prüfsumme erneut ermitteln.

## Basisstack

`header.php` wird von allen Hauptseiten außer `index.php` und `dashboard.php`
verwendet. Es lädt CSS, Icons, Manifest, Pi.Alert-CSS und abhängig von den
Einstellungen Skin-, Theme- und Dark-Mode-Dateien. `footer.php` lädt danach
die JavaScript-Basis. `dashboard.php` wiederholt den CSS-Stack und lädt seinen
eigenen JavaScript-Stack; es bindet **kein** `adminlte.min.js` ein.

| Bibliothek | Tatsächlich geladene Datei(en), SHA-256 | Version / Lizenz / Herkunft | Seiten und Befund | Entscheidung für v4 |
| --- | --- | --- | --- | --- |
| AdminLTE | `lib/AdminLTE/dist/css/AdminLTE.min.css` `a901dbf7e56a5dcf43a3e7ddf84d63dd00e5c62539f0e2ef2282780df1b1a242`; `lib/AdminLTE/dist/js/adminlte.min.js` `b42729f850b123c0530dae9595e1e520d8e2d2db9ffb1ad8efa817e59fdeaa9b` | 2.4.18, MIT; Banner und `lib/AdminLTE/LICENSE`, ColorlibHQ/AdminLTE | CSS über Header auf Standardseiten und Dashboard; JS über Footer nur auf Standardseiten. Das JS verlangt jQuery. | V4 getrennt lokal einbinden; dessen Markup und JavaScript-API nicht mit v2 mischen. |
| Bootstrap | `lib/AdminLTE/bower_components/bootstrap/dist/css/bootstrap.min.css` `6d92dfc1700fd38cd130ad818e23bc8aef697f815b2ea5face2b5dfad22f2e11`; `.../js/bootstrap.min.js` `9ee2fcff6709e4d0d24b09ca0fc56aade12b4961ed9c43fd13b03248bfb57afe` | 3.4.1, MIT; Dateibanner (twbs/bootstrap) | CSS über Header/Dashboard, JS über Footer/Dashboard/Login. JS verlangt jQuery 1.9.1 bis kleiner 4. | Eigenen Bootstrap-5-Stack je v4-Dokument verwenden; `data-toggle`, jQuery-Plugin-Aufrufe und Klassen werden portiert. |
| jQuery | `lib/AdminLTE/bower_components/jquery/dist/jquery.min.js` `da4ad864a87ffcf71c851b5df87f95cb242867f7b711cae4c6133cc9cc0048f0` | 3.6.2, MIT; Dateibanner OpenJS Foundation / jquery.org | Footer aller Standardseiten, Dashboard und Login. Anwendungscode verwendet `$.ajax`, `$.get`, `$.getJSON`, DOM-Manipulationen und Plugins. | Vorläufig als explizite Kompatibilitätsabhängigkeit behalten; exakt einmal pro v4-Seite laden. |
| Pi.Alert Browserbasis | `js/pialert_common.js` `2258c331eb54bf8703bf7738083a42bfd53fd53cef887ffe46eeb096e320952f`; `js/hotkeys.js` `5c787e4982fd881851dcefb9c7f3db1c2d74c8325b14d6d8a58110bacfe66ced` | Projekteigener Code; Lizenz/Herkunft im Repository | Footer/Dashboard. `pialert_common.js` implementiert CSRF, AJAX-Fehlerbehandlung, POST-Deduplizierung, Cookies, Modale, Benachrichtigungen und Temperaturdarstellung. | Neue v4-Kopie mit identischem Request-Vertrag erstellen; global benötigte Funktionen bis zur Ablösung der Inline-Handler bereitstellen. |
| Anwendungsthemen und lokale Schrift | `css/pialert.css` `76a627c6868100fa81b73803b5476e03d563bef8b758255f3818c37afc4358ef`; `css/dark-patch.css` `a97f30d1717a9dc696964379f45d7d996079310d096f6386f2b43efe3f631972`; `css/offline-font.css` `f3bc102acfb5c8f1d1ffb520b1d37e08d4d7c3d3bc7b265a651a1dfbffa54eb6` | Projekteigene CSS-Dateien; lokale Source-Sans-3-Webfonts unter `css/font/` | Header/Dashboard; Dark-Patch abhängig von `setting_darkmode`, Theme-CSS abhängig von Theme-Einstellung. | Themewerte behalten, CSS für v4 neu zuordnen. Fontdateien und CSS-Pfade lokal testen. |
| AdminLTE-Skins | dynamisch `lib/AdminLTE/dist/css/skins/<setting_skin>.min.css`, Fallback `skin-blue.min.css` | Bestandteil AdminLTE 2.4.18, MIT | Header/Dashboard, abhängig von Konfigurationsdateien | Nicht übernehmen als v2-Skin; bestehende Benutzerauswahl semantisch auf das v4-Theme abbilden. |

### Tatsächliche Ladeduplikate und Reihenfolgeprobleme

- `networkSettings.php:369` lädt jQuery vor seinem eigenen Inline-Skript und
  erhält über `footer.php` anschließend dieselbe jQuery-Datei erneut.
- `devices.php` und `icmpmonitor.php` können in bedingten Ansichten ebenfalls
  jQuery vorzeitig laden; ihre Standardseite erhält zusätzlich die Footer-Datei.
- `dashboard.php` lädt jQuery, Bootstrap, DataTables und `pialert_common.js`
  im `<head>`. Es lädt kein AdminLTE-JavaScript, verwendet aber weiterhin das
  AdminLTE-2-HTML/CSS.

Für v4 ist eine Seitendefinition mit genau einem Owner für jede Bibliothek
erforderlich. Die bestehende Ladeordnung darf nicht blind in neue Templates
kopiert werden.

## Aktiv verwendete Komponenten

| Bibliothek | Geladene Datei(en), SHA-256 | Version / Lizenz / Herkunft | Aktive Seiten und API-Vertrag | V4-Relevanz |
| --- | --- | --- | --- | --- |
| DataTables Kern | `lib/AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js` `56cd4fafefd322acdf1047e13620fb13586b8713ca2da55c4a7055e06fb54b41` | 1.10.25, MIT; Dateibanner und `datatables.net/License.txt`, SpryMedia | `dashboard`, `devices`, `devicesEvents`, `deviceDetails`, `services`, `serviceDetails`, `icmpmonitor`, `icmpmonitorDetails`, `journal`. Verwendet `DataTable()`, AJAX-`url().load()`, Renderer, Spalten, Paging und `length.dt`/`order.dt`/`search.dt`. | jQuery-Abhängigkeit. Kernversion zunächst festhalten; Bootstrap-5-Adapter nur in einer kompatiblen Kombination verwenden. |
| DataTables Bootstrap-3-Adapter | `.../datatables.net-bs/js/dataTables.bootstrap.min.js` `1ff6491e3f74d9ea86a1c349623903dce06eb63ebc9fe4f63520639df5764289`; `.../css/dataTables.bootstrap.min.css` `755bc038d66aa6b8c114d10540cc6e29c69944dbbc72e06a15102656e7fe045d` | 1.10.x nicht im Dateibanner präzisiert; MIT, `datatables.net-bs/License.txt`, SpryMedia | Auf denselben neun Seiten direkt eingebunden. | Nicht im v4-Dokument laden. Passenden Bootstrap-5-Adapter zur gewählten DataTables-Version nachweisen. |
| iCheck | `lib/AdminLTE/plugins/iCheck/icheck.min.js` `68a72f76afe90409c84fca5c63e5954e370621201481103921cc80aab3452ad7`; `.../all.css` `292fca03a97afd382299c051a1b157d3bccee0b0236004ab5df17bf531419354`; Login zusätzlich `.../square/blue.css` | 1.0.3, MIT; `plugins/iCheck/bower.json`, Damir Sultanov | Login, `devices`, `deviceDetails`, `services`, `serviceDetails`, `icmpmonitor`, `icmpmonitorDetails`, `maintenance`. Die Anwendungsseiten laden `all.css`, das Login stattdessen `square/blue.css`. Eigene Aufrufe `check`/`uncheck`; `ifToggled` wird mindestens in Detailseiten verarbeitet. | jQuery-Plugin. Für Parität vorläufig übernehmen oder Checkbox-Adapter mit identischen Ereignissen und Zuständen schreiben. |
| Chart.js | `lib/AdminLTE/bower_components/chart.js/chart.js` `471a1627c41b1ea6227a867d00393bf449a680248c246a0c42bd41e7c5919d5a` | 3.0.2, MIT; Dateibanner, chartjs.org | `dashboard`, `devices`, `deviceDetails`, `services`-Details, ICMP-Seiten, `presence` sowie `js/graph_online_history.js`. API `new Chart(...)`; Dashboard enthält noch v2-Optionssyntax. | Keine AdminLTE-Abhängigkeit. 3.0.2 zuerst pinnen; v2-Konfiguration nur in der v4-Kopie auf v3-Syntax umstellen. |
| Moment.js | `lib/AdminLTE/bower_components/moment/moment.js` `1fd8c0cfffd02e40cecbf9f313d1b86988a342d90bb7d16f1a67544f0064ea0b` | 2.24.0, MIT; `moment.js` setzt `hooks.version`, `moment/LICENSE` | Direkt vor FullCalendar auf `deviceDetails`, `serviceDetails`, `icmpmonitorDetails`, `presence`. | Zusammen mit FullCalendar 3 beibehalten. |
| FullCalendar | `.../fullcalendar/dist/fullcalendar.min.js` `ec9f750e1712f9fbe08e6655cedd90c44b6f644ee6cec0b26a1e7b0dcab555e5`; CSS `207c35d2a317b6544cfd5fe992ed40a9222c7c7037cdf9030430a0bce491039a`; Druck-CSS `08f10f1444ac85af080ef21485d909ab1a8581e02b29498fddb81568a3de6e14`; Locale `b966be1db984da05a18308310b05ecfcfeab70bff64aed85f9f53a6e0ec5eb67` | 3.10.5, MIT; Dateibanner und `fullcalendar/LICENSE.txt`, Adam Shaw | `deviceDetails`, `serviceDetails`, `icmpmonitorDetails`, `presence`. jQuery-API `fullCalendar()`, Quellen, Ansichten, Tooltips, Nachladen. | Jquery- und Moment-abhängig. Separate Kompatibilitätskomponente; kein erzwungener Versionswechsel in P2/P3. |
| FullCalendar Scheduler | `lib/fullcalendar-scheduler/scheduler.min.js` `2d213cb86740960dab227598ff99310d1ee94d0fcb448a7495656768aed798d1`; CSS `16ddb9c498fb825f69770320a403e60b2a458616dbd96daeffdc73ab815fc339` | 3.10.4, tri-lizenziert (kommerziell, CC BY-NC-ND oder GPLv3); `LICENSE.txt`, FullCalendar | Nur `presence.php`; Timeline-Ressourcen. Die Seite setzt `schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source'`. | Lizenzschlüssel, Daten- und Ressourcenvertrag unverändert prüfen. Erst nach Parität separat modernisieren. |
| Coloris | `lib/Coloris/dist/coloris.min.js` `1df04e1e5d7b694adc60e2bcf366206dced08200abc6d24b09b2523574221e01`; CSS `3df9389e23a9519d7f5e2695dd45b4d65a2ba06eebf0d25f07395f8419a97dd3` | 0.24.0, MIT; `lib/Coloris/version`, `LICENSE`, Mohammed Bassit | `journal.php` und `reports.php`; `Coloris.init()` und `Coloris({...})`, auch nach dynamischem DOM-Aufbau. | Vanilla-JS, keine jQuery-Abhängigkeit. Zunächst unverändert lokal übernehmen; Modal- und Overlay-Stapel prüfen. |

Die in `presence.php` verwendete Ressourcen-Timeline gehört auch in der
aktuellen FullCalendar-Dokumentation zu [FullCalendar Premium](https://fullcalendar.io/docs/timeline-view).
Die [v7-Lizenz](https://fullcalendar.io/license) unterscheidet diese Plugins
von den freien Standardfunktionen und verwendet für passende Open-Source-Projekte
AGPLv3 statt des bisherigen GPLv3-Schlüssels. Darum bleibt die vorhandene
Kombination aus FullCalendar 3.10.5 und Scheduler 3.10.4 während der
AdminLTE-Migration festgepinnt; ihre Lizenzvoraussetzungen und die tatsächliche
Timeline-Funktion werden gesondert geprüft. Ein v7-Upgrade wäre eine eigene
API- und Lizenzentscheidung.

## Aktiv geladene Iconfamilien

| Familie | Datei, SHA-256 | Ermittelter Stand / Lizenz | Befund |
| --- | --- | --- | --- |
| Bootstrap Icons | `front/lib/bootstrap-icons-1.13.1/font/bootstrap-icons.min.css` `a5d6387a32ca3baec4d02336b5b3edab50c9dd518355576a011ea3dd9c1d884e` | 1.13.1, MIT; lokale `LICENSE` und `package.json` | Lokal gepinnte v4-Iconfont für Shell und Seiten. Die bisherige 1.11.2-Kopie liegt außerhalb des Webroots unter `_archive/adminlte2-2026-09-23/retired-v4-assets/`. |
| Font Awesome | `.../font-awesome/css/font-awesome.min.css` `5ceaaba22d75b58e04150311f596306562a3e595e27ed4b1dfa451b82dda9e50` | **Free 6.5.2** laut Banner der tatsächlich geladenen CSS-Datei; Icons CC BY 4.0, Webfonts SIL OFL 1.1, CSS-Code MIT laut Banner und `LICENSE.txt` | Header/Dashboard/Login; Klassen beider Generationen (`fa`, `fa-solid`, `fa-regular`) kommen im Anwendungscode vor. Die CSS-Datei referenziert lokale `webfonts/`, darunter `fa-v4compatibility.woff2`; diese Datei ist vorhanden. Der Header-Kommentar „6.40“ ist nicht maßgeblich. |
| Ionicons | `.../Ionicons/css/ionicons.min.css` `49d470cf6a1752308180dc337c38bb0d1b94775c9f7078326c36c2cf809a67af` | 4.5.10-1 laut CSS-Banner; Lizenzdatei vorhanden | Header/Dashboard/Login. |
| Material Design Icons | `.../material-design-icons/css/materialdesignicons.min.css` `03fe3caba05e65b14e4035139eee89b12be87cd0bcf342ac3886770eec3a9962` | **7.4.47** laut `?v=7.4.47` in den `@font-face`-URLs der geladenen CSS-Datei; lokale `LICENSE` vorhanden | Header/Dashboard, insbesondere `mdi-pi-hole`. Die referenzierte lokale WOFF2-Datei ist vorhanden. Relative Fontpfade müssen in v4 erhalten bleiben. |

## Vorhanden, aber derzeit nicht als aktive Anwendungskomponente belegt

Die folgenden Pakete liegen unter `front/lib/AdminLTE/`, aber es gibt keine
direkte `<script>`-/`<link>`-Einbindung durch die Hauptseiten und keine eigene
Initialisierung mittels der jeweiligen API: Select2, Bootstrap Datepicker,
ion.rangeSlider, seiyria-bootstrap-slider, FastClick, Morris, jQuery SlimScroll,
Sparkline, jQuery Knob, Bootstrap WYSIHTML5 und jQuery UI.

Auch im separaten Ordner `front/lib/fullcalendar-scheduler/lib/` vorhandene
`jquery.min.js`, `jquery-ui.min.js`, `moment.min.js` und `gcal.min.js` werden
von `presence.php` nicht eingebunden. Die Seite verwendet statt dessen die
AdminLTE-Varianten von jQuery, Moment und FullCalendar. Diese Dateien sind bis
zu einem nachgewiesenen Laufzeitpfad lediglich mitgeliefert und werden nicht in
den v4-Abhängigkeitsumfang übernommen.

AdminLTE-Alternativ-CSS (`dist/css/alt/`), Demo-JavaScript, die restlichen
AdminLTE-Plugins und Beispielbilder wurden ebenfalls nicht als aktive
Anwendungsabhängigkeiten festgestellt.

## Abhängigkeiten außerhalb von Bibliotheken

- Das Manifest `img/manifest.json` wird durch Header und Dashboard geladen. Es
  enthält derzeit kein `id`, `start_url` oder `scope`; der Icon-Eintrag hat eine
  leere Quelle. Dies ist ein PWA-Vertrag, keine AdminLTE-Bibliothek.
- `js/static_reload.js` und `js/static_stop_spinner.js` sind projektinterne
  Skripte für Status-/Neustartseiten. Sie werden nicht durch die 17
  Hauptseiten-Einbindungen geladen, gehören aber bei separaten Statusansichten
  zur Browserlogik.
- `css/dark-patch-cal.css` wird von Seiten mit FullCalendar bei aktiviertem
  Dark Mode nachgeladen. SHA-256:
  `531f89af1e6c3a7e880ad83b8f03f971e7b41991f807dfc30dc8b445deee7853`.

## Migrationsrelevante Entscheidungen

1. Die v4-Seiten erhalten eigene, lokal versionierte Assetpfade. Bootstrap 3,
   AdminLTE 2 und ihr DataTables-Bootstrap-3-Adapter werden in keinem v4-
   Dokument geladen.
2. jQuery bleibt vorläufig Bestandteil des v4-Stacks, weil DataTables 1.10,
   FullCalendar 3, Scheduler 3, iCheck und der gemeinsame Anwendungscode daran
   hängen.
3. Ein Versionswechsel von Chart.js, Coloris, FullCalendar/Scheduler,
   DataTables oder jQuery ist ein separates Paket. Er ist nicht implizit durch
   einen AdminLTE-Download abgedeckt.
4. Vor der v4-Integration wird für jede geladene CSS-Datei mit Webfonts und
   Bildern die tatsächliche Browseranforderung geprüft. CSS-Prüfsummen allein
   decken nicht die referenzierten Binärdateien ab.
