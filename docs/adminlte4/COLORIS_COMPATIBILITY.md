# Coloris-Kompatibilität für AdminLTE 4

Stand: 23. September 2026

## Entscheidung

Coloris bleibt für die AdminLTE-4-Migration unverändert auf Version 0.24.0.
Die beiden tatsächlich geladenen Laufzeitdateien wurden bytegleich aus dem
Bestand nach `front/lib/coloris-0.24.0/` kopiert. Lizenz- und Versionsdatei
liegen im selben gepinnten Verzeichnis. Es gibt keine CDN- oder
Build-Abhängigkeit und keine Änderung an den produktiven Seiten.

## Bestehende Aufrufe

### `front/journal.php`

- lädt `coloris.min.css` und `coloris.min.js` nach dem DataTables-Stack;
- konfiguriert `theme: 'pill'`, `themeMode: 'dark'`, `alpha: false` sowie
  Clear- und Close-Buttons;
- erstellt Trigger- und Methodenfelder dynamisch mit `data-coloris`;
- fügt die Felder in ein Bootstrap-Modal ein;
- ruft nach den Add-Buttons `Coloris.init()` auf.

Der letzte Punkt ist kein gültiger 0.24.0-API-Aufruf: Die vendorte Runtime
stellt `Coloris.set`, `Coloris.wrap`, `Coloris.close`, `Coloris.setInstance`,
`Coloris.removeInstance`, `Coloris.updatePosition` und `Coloris.ready` bereit,
aber kein `Coloris.init`. Der Aufruf erzeugt daher beim manuellen Hinzufügen
einer Zeile einen `TypeError`, nachdem das Feld bereits in den DOM eingefügt
wurde. Wegen des delegierten `[data-coloris]`-Click-Handlers kann das neue
Textfeld den Picker trotzdem öffnen; es erhält jedoch nicht automatisch den
`.clr-field`-Wrapper mit Farbvorschau.

Für die spätere v4-Portierung muss die Zeilenerzeugung das konkrete neue Input
mit der unterstützten API `Coloris.wrap(colorInput)` registrieren. Dieser
Befund wird hier nur dokumentiert; die Altseite wurde entsprechend der
Auftragsgrenze nicht geändert.

### `front/reports.php`

- lädt Coloris innerhalb des Farbkonfigurations-Modals;
- erzeugt sechs serverseitige `data-coloris`-Inputs;
- verwendet dieselbe Pill-/Dark-Konfiguration ohne Alpha und mit Clear-/Close-
  Button;
- erzeugt keine späteren dynamischen Farbfelder, sodass die automatische
  Initialisierung beim `DOMContentLoaded`-Ereignis ausreicht.

Beide Seiten wählen `themeMode: 'dark'` unabhängig von der globalen
Dark-Mode-Einstellung. Das ist der bestehende Vertrag: Der Picker bleibt auch
bei hellem Seitenthema dunkel. Die Coloris-CSS enthält eigene `.clr-dark`-
Regeln; `front/css/dark-patch.css` enthält Modal-, aber keine Coloris-Regeln.

## Dateien, Lizenz und Integrität

| Datei | Bytes | SHA-256 |
| --- | ---: | --- |
| `coloris.min.js` | 14255 | `1df04e1e5d7b694adc60e2bcf366206dced08200abc6d24b09b2523574221e01` |
| `coloris.min.css` | 8508 | `3df9389e23a9519d7f5e2695dd45b4d65a2ba06eebf0d25f07395f8419a97dd3` |
| `LICENSE` | 1071 | `d4c8ecd8b83c06b41b64083ef41f10e89e59ef194ac91cfed94770aaae33f9de` |
| `version` | 6 | `566e2307cdd34d632f1c522f89ac9f525ccf2e4486af587deabdc9c484cacafe` |

`version` enthält exakt `0.24.0`. Coloris steht laut mitgeliefertem Lizenztext
unter MIT; Copyright 2021 Mohammed Bassit. Alle vier Dateien wurden mit `cmp`
gegen `front/lib/Coloris/` geprüft und sind bytegleich. Reproduzierbare
Prüfung:

```sh
cd front/lib/coloris-0.24.0
sha256sum -c SHA256SUMS
```

## Ladefolge für eine spätere v4-Seite

1. `front/lib/adminlte-4.9.1/css/adminlte.min.css`
2. `front/lib/coloris-0.24.0/coloris.min.css`
3. optional jQuery für den übrigen Seitencode; Coloris selbst benötigt es nicht
4. `front/lib/coloris-0.24.0/coloris.min.js`
5. `front/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js`
6. `front/lib/adminlte-4.9.1/js/adminlte.min.js`
7. seitenbezogene Coloris-Konfiguration

## Isolierte Chromium-Probe

Die statische Probe
`_workspace/tests/adminlte4/coloris/compatibility.html` verwendet ausschließlich
synthetische Felder und führt keine Backend-Abfrage aus. Sie öffnet ein echtes
Bootstrap-5-Modal und prüft anschließend:

- Öffnen des Pickers aus einem beim Laden vorhandenen Input;
- Auswahl von `#abcdef` über ein Swatch und Auslösung von `coloris:pick`;
- dynamisches Erzeugen eines zweiten `data-coloris`-Inputs;
- Öffnen dieses Feldes durch den delegierten Handler vor dem Wrapping;
- Wrapping über die unterstützte API `Coloris.wrap(input)` und Auswahl von
  `#112233`;
- weiterhin sichtbares Modal, vorhandenen Backdrop und Fokus innerhalb des
  Modals nach der Picker-Interaktion;
- `pill`- und `dark`-Klassen sowie Dark-Hintergrund `rgb(68, 68, 68)`;
- Picker-`z-index: 100000`, damit der Picker über Bootstrap-Modal und Backdrop
  liegt;
- explizit `typeof Coloris.init === 'undefined'` als Bestandsbefund.

Unter Chromium 153.0.8010.52 bei 1440 × 900 war der DOM-Prüfstatus
`data-probe-status="pass"`. Modal, Backdrop und Picker wurden gleichzeitig
sichtbar gerendert; beide synthetischen Auswahlen waren im jeweiligen Input
und in den Pick-Events vorhanden. Der Desktop-Screenshot wurde visuell geprüft.
Die Screenshots liegen dauerhaft unter
`_workspace/adminlte4-browser-reference/coloris-compatibility-desktop-1440x900.png`
und `coloris-compatibility-mobile-390x844.png` im selben Verzeichnis.

Eine zusätzliche Sichtprüfung bei 390 × 844 bestätigt die funktionale
Bedienbarkeit, zeigt aber auch eine unveränderte CSS-Grenze: Das Pill-Theme hat
eine feste Breite von 380 px. Innerhalb eines Modals mit Seitenabständen kann
es deshalb in sehr schmalen Viewports leicht über den sichtbaren Rand ragen.
Eine responsive Anpassung des Picker-Themes gehört zur eigentlichen
Seitenportierung und wurde nicht in gemeinsame v4-CSS aufgenommen.

## Grenzen der Aussage

- Geprüft wurden Maus-/synthetische Click-Auswahl und grundlegende
  Modal-Fokuserhaltung. Ein vollständiger Tastatur-, Screenreader- und
  Escape-/Tab-Zyklus bleibt Bestandteil des späteren Seiten-E2E-Tests.
- Der Picker wird wie im Bestand an `body` angehängt. Der hohe z-Index löst die
  visuelle Ebenenreihenfolge; bei komplexen verschachtelten Scrollcontainern
  muss die Positionierung erneut geprüft werden.
- Die Probe bestätigt den festen dunklen Picker. Eine automatische Kopplung an
  `data-bs-theme` findet in der vorhandenen Seitenkonfiguration nicht statt.
- Speichern, Backend-Validierung und Parameter-Queries waren nicht Teil dieser
  rein clientseitigen Kompatibilitätsprüfung.
