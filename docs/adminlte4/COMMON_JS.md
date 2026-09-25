# Gemeinsame v4-Browserlogik

Stand: 23. September 2026. Dieses Dokument beschreibt den abgegrenzten
P3-Kompatibilitätskern und die anschließend freigegebene Shell-Runtime. Die
bestehenden Serverendpunkte und die alte Oberfläche wurden nicht geändert.

## Dateien und Ladereihenfolge

- `front/js/pialert-common.js`: schreibende Requests, CSRF, Dialoge und Toasts.
- `front/js/pialert-shell-runtime.js`: ausschließlich lesende Shell-Pollings,
  Serveruhr, Countdown, Auto-Reload, Temperaturanzeige und Theme-Metadaten.

Die benötigte Reihenfolge ist jQuery 3.6.2, optionale jQuery-Plugins,
`bootstrap.bundle.min.js`, AdminLTE, `pialert-common.js`,
`pialert-shell-runtime.js` und danach `pialert-v4.js` beziehungsweise
Seitenskripte. Ein `meta[name="csrf-token"]` muss bereits vorhanden sein.

## Request-Vertrag

`pialertPost(url, data, success)` bleibt global und gibt dasselbe
jQuery-Deferred zurück, das `$.ajax` liefert. Die Variante erhält folgende
Eigenschaften der Referenz:

- ausschließlich Same-Origin-Mutationen, Methode `POST`;
- `X-CSRF-Token` bei allen schreibenden Same-Origin-jQuery-Requests, aber nicht
  bei `GET`, `HEAD`, `OPTIONS` oder Cross-Origin-Aufrufen;
- Queryparameter werden in den POST-Body übernommen, ohne gleichnamige explizite
  Bodywerte zu überschreiben;
- Objekt-/Arraywerte, `serializeArray()`-Listen, URLSearchParams und bereits
  URL-kodierte Formulardaten bleiben serialisierbar;
- zufällige 128-Bit-`_operation_id` pro tatsächlich gestarteter Operation;
- identische parallele Requests teilen sich ein Deferred; nach `always()` ist
  eine neue Operation mit neuer ID möglich;
- die vorhandene Success-Callback-Signatur bleibt unverändert;
- HTTP 403 mit `X-PiAlert-CSRF: invalid` zeigt den bisherigen Hinweis und lädt
  die Seite neu.

Der Deduplizierungsschlüssel wird vor Erzeugung der Operations-ID gebildet.
Damit verhindert die zufällige ID nicht die Zusammenführung eines Doppelklicks.
Es wurde weder `fetch` noch ein allgemeiner `eval`- oder Script-Ausführungsweg
eingeführt.

## Dialoge und Meldungen

Global verfügbar sind `showModalDefault`, `showModalWarning`, `showModalOK`,
`showModalWarningOK`, `modalDefaultOK`, `modalWarningOK` und `showMessage`.
Die Modal-API verwendet `bootstrap.Modal.getOrCreateInstance`; Callbacks dürfen
weiterhin als bisheriger globaler Funktionsname oder direkt als Funktion
übergeben werden. Namen werden ausschließlich als direkte `window`-Eigenschaft
aufgelöst und niemals ausgewertet.

Fehlendes Markup wird mit den bisherigen IDs (`modal-default*`,
`modal-warning*`, `notification`, `alert-message`) erzeugt. Dialogtexte
akzeptieren nur eine kleine Format-Whitelist (`br`, `span`, Hervorhebung und
Code); Attribute und aktive Elemente werden entfernt. Toasttexte werden immer
mit `textContent` gesetzt. Meldungen, deren Text `error` enthält, behalten das
bisherige sichtbare Alert-Verhalten.

## Shell-Runtime

`PiAlertV4Shell.init()` räumt einen vorherigen Lauf zuerst auf;
`PiAlertV4Shell.destroy()` beendet Intervalle und Timeouts, entfernt Listener
und bricht noch laufende GETs ab. Auto-Init erfolgt einmal nach DOM-Bereitschaft.
Die bisherigen globalen Funktionsnamen für Badge-Refresh, Uhr, Auto-Reload,
Temperatur und `toggle_systeminfobox()` bleiben als Adapter verfügbar.

Die Runtime liest unverändert:

| Takt | Endpoint / Zustand | Ausgabe |
| --- | --- | --- |
| 30 s | `devices.php?action=getDevicesTotals&scansource=...` | Geräte-, Neu-, Down- und Presence-Badges |
| 30 s | `icmpmonitor.php?action=getICMPHostTotals` | ICMP-On/Down |
| 30 s | `services.php?action=getServiceMonTotals` | Service-On/Down/Warning |
| 30 s | `files.php?action=GetUpdateStatus` | Update-Badge |
| 15 s | `files.php?action=getReportTotals` | Report-Badge, Icon und Dokumenttitel |
| Start | `files.php?action=GetServerTime` | driftkorrigierte Uhr und 5-Minuten-Countdown |
| lokal | `autoReloadChecked` | unverändert 120 Sekunden Auto-Reload |
| lokal | `tempunit` | unverändert `C`, `F` oder `K` |

Scanquellen werden aus vorhandenen `header_<source>_count_on`-IDs erkannt; so
werden spätere Satelliten-Badges ohne zweite Timerregistrierung mitgeführt.
Parallele identische Shell-GETs werden zusammengeführt. Fehlerhafte Antworten
ändern den letzten sichtbaren Stand nicht und beenden nicht die späteren
Pollingläufe.

Der Server bleibt Eigentümer der Themeentscheidung. Die Runtime übernimmt nur
`data-pialert-color-mode`/`data-bs-theme` (`dark` oder `light`) sowie optional
`body[data-pialert-skin]` in Dokumentmetadaten. Sie schreibt keine neuen
Theme-/Skin-Schlüssel und ändert bestehende gespeicherte Werte nicht.

## Prüfnachweise

- `node --check front/js/pialert-common.js`: erfolgreich.
- `node --check front/js/pialert-shell-runtime.js`: erfolgreich.
- isolierter Node-Vertragstest: Objekt-, Array- und kodierte Formularpayloads,
  Queryübernahme, CSRF-Header, Operations-ID, Deduplizierung, Freigabe nach
  Abschluss, Cross-Origin-Sperre und CSRF-Ablauf erfolgreich.
- isolierter Node-Shelltest mit synthetischen Endpointantworten: alle Badge-
  Zuordnungen, Uhr/Countdown, Temperatur, Theme, Auto-Reload sowie wiederholtes
  `init()`/`destroy()` ohne doppelte Intervalle oder Listener erfolgreich.
- Chromium-Synthetest mit lokalem Bootstrap-5-Bundle: dynamisches Modal-Markup,
  Bootstrap-Komponenteninstanz und Entfernung aktiver Testmarkups wurden im DOM
  nachgewiesen. Der vollständige Headless-Dump war in dieser Umgebung durch
  nicht beendete Chromium-GCM-Hintergrunddienste nicht stabil reproduzierbar;
  deshalb wird daraus kein vollständiger visueller Abnahmenachweis abgeleitet.

Verwendete isolierte Testdateien liegen nur unter `/tmp` und sind kein
Produktionsbestandteil.

## Noch offen

- Die v4-Shell lädt beide Dateien bereits in der oben genannten Reihenfolge;
  die weitere Seitenportierung muss diese zentrale Einbindung wiederverwenden
  und darf keine zweite Runtime registrieren.
- Eine visuelle Prüfung in der laufenden Fixture-Instanz ist weiterhin nötig,
  insbesondere Fokus-Rückgabe, Tastaturbedienung, Toastposition und schmale
  Viewports.
- Nicht in diesen begrenzten Kern übernommen wurden die übrigen Legacy-Helfer
  aus `pialert_common.js` (Cookie-Massenlöschung, Clipboard, Tabellenzellen,
  Journal-Sanitizer und seitenspezifische Parameter-Retries). Einige davon sind
  bereits separat in `pialert-v4.js` vorhanden; weitere werden erst mit ihren
  konsumierenden Seiten portiert.
