# iCheck-Kompatibilität für AdminLTE 4

Stand: 23. September 2026

## Entscheidung

iCheck bleibt für die AdminLTE-4-Migration vorläufig auf Version 1.0.3. Das
jQuery-Plugin funktioniert mit dem bereits gepinnten jQuery 3.6.2 sowie in
einem Bootstrap-5-Modal. Seine für Pi.Alert maßgeblichen Verträge
`ifToggled`, `iCheck('check')`, `iCheck('uncheck')` und der Zustand des
ursprünglichen `<input>` wurden in Chromium bestätigt.

Die aktive Runtime liegt isoliert unter `front/lib/icheck-1.0.3/`. Die
produktiven Altseiten und deren Ladepfade wurden nicht geändert.

## Umfang des Pins

Die Altseiten laden `icheck.min.js` und für Anwendungsseiten `all.css`; das
Login lädt stattdessen `square/blue.css`. `all.css` importiert alle sechs
mitgelieferten Skinfamilien und deren Varianten. Damit relative CSS-Imports,
normale Bildauflösungen und `@2x`-Bilder erhalten bleiben, umfasst der Pin
deshalb den vollständigen transitiven CSS-/PNG-Baum:

- 1 JavaScript-Datei;
- 47 CSS-Dateien einschließlich `all.css`;
- 66 PNG-Dateien einschließlich der HiDPI-Varianten;
- MIT-Lizenz und `SHA256SUMS`.

Alle 114 Runtime-Dateien wurden mit `cmp` gegen
`front/lib/AdminLTE/plugins/iCheck/` geprüft und sind bytegleich. Die
maßgeblichen Einstiegspunkte sind:

| Datei | Bytes | SHA-256 |
| --- | ---: | --- |
| `icheck.min.js` | 5219 | `68a72f76afe90409c84fca5c63e5954e370621201481103921cc80aab3452ad7` |
| `all.css` | 1568 | `292fca03a97afd382299c051a1b157d3bccee0b0236004ab5df17bf531419354` |
| `square/blue.css` | 1513 | `23b86f2e796ece063e6ec23c1018b019826b088beac4e126c9a82b01652804f5` |
| `LICENSE` | 1081 | `62d377001d930d13e6a0d57615ffced389c55fca3c5f00c13e6d1743820e2f36` |

Das vollständige Manifest enthält 115 Einträge einschließlich Lizenz. Die
Version und MIT-Lizenz sind zusätzlich durch den Banner in `icheck.js`, die
lokale `bower.json` und den Lizenzabschnitt der lokalen README belegt;
Copyright 2013 Damir Sultanov. Reproduzierbare Integritätsprüfung:

```sh
cd front/lib/icheck-1.0.3
sha256sum -c SHA256SUMS
```

## Bestehender API-Vertrag

Der Bestand initialisiert farbige Checkboxen über Wrapperklassen wie
`icheckbox_flat-blue`, `icheckbox_flat-orange`, `icheckbox_flat-red`,
`icheckbox_flat-green` und `icheckbox_flat-purple`. Detailseiten reagieren auf
`ifToggled`; Geräte- und ICMP-Daten werden programmgesteuert mit `check` und
`uncheck` in die Formulare übertragen. Maßgeblich für Speichern und
Formularauswertung bleibt dabei das echte `input.checked`, nicht nur die
Klasse `checked` auf dem iCheck-Wrapper.

## Ladefolge und Integration

Für eine spätere v4-Seite gilt:

1. `front/lib/adminlte-4.9.1/css/adminlte.min.css`
2. `front/lib/icheck-1.0.3/all.css` oder beim Login ausschließlich
   `square/blue.css`
3. `front/lib/jquery-3.6.2/jquery.min.js`
4. `front/lib/icheck-1.0.3/icheck.min.js`
5. `front/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js`
6. `front/lib/adminlte-4.9.1/js/adminlte.min.js`
7. seitenspezifische iCheck-Initialisierung und Ereignisbindung

Dynamisch eingefügte Inputs müssen nach dem DOM-Einfügen gezielt initialisiert
werden. Ereignishandler sollten entweder danach gebunden oder delegiert
werden. Beim Portieren dürfen die nativen `checked`-Eigenschaften und die
bestehenden `ifToggled`-Reaktionen nicht durch reine CSS-Checkboxen ersetzt
werden, solange kein kompatibler Adapter dieselben Verträge erfüllt.

## Isolierte Chromium-Probe

`_workspace/tests/adminlte4/icheck/compatibility.html` arbeitet ausschließlich
mit zwei synthetischen Checkboxen und ohne Backend-Abfrage. Die Probe öffnet
ein echtes Bootstrap-5-Modal und prüft:

- iCheck-API unter jQuery 3.6.2;
- initial ungeprüften und geprüften Zustand der nativen Inputs;
- programmgesteuertes `check` und `uncheck` samt synchroner Wrapperklasse;
- einen Benutzerpfad über den von iCheck erzeugten `.iCheck-helper`;
- genau drei `ifToggled`-Ereignisse in der erwarteten Reihenfolge und jeweils
  den bereits aktualisierten nativen `checked`-Wert;
- sichtbares Modal, Backdrop und Fokus innerhalb des Modals;
- keine JavaScript-Ausnahme.

Der Lauf unter Chromium 153.0.8010.52 ergab
`data-probe-status="pass"`. Die Zustandsfolge war `false → true → false` für
die programmgesteuerte Checkbox und `true → false` für den Helper-Klick; alle
drei Übergänge lösten `ifToggled` aus.

## Grenzen

Die Probe bestätigt die heute aktiv verwendete Checkbox-API. Radio-Buttons,
indeterminate/disabled, Touchgeräte, Screenreader und ein vollständiger
Tastaturzyklus sind damit nicht abgenommen. Auch die produktive
Formularspeicherung bleibt Teil der jeweiligen Seitenmigration.
