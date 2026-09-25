# DataTables-Kompatibilität für AdminLTE 4

Stand: 23. September 2026

## Entscheidung

DataTables bleibt für die AdminLTE-4-Migration auf Version 1.10.25. Der
vorhandene Kern wird bytegleich weiterverwendet; nur der Bootstrap-3-Adapter
`datatables.net-bs` wird auf einer später migrierten Seite durch den offiziellen
und ebenfalls exakt auf 1.10.25 gepinnten Adapter `datatables.net-bs5` ersetzt.
Es gibt damit weder ein stilles Core-Upgrade noch einen Major-Wechsel.

Die Kompatibilität ist durch drei Primärquellen belegt:

- Das [offizielle historische CDN-Verzeichnis für
  1.10.25](https://cdn.datatables.net/1.10.25/) veröffentlicht ausdrücklich
  `dataTables.bootstrap5.css` und `dataTables.bootstrap5.js` neben dem
  DataTables-Kern dieser Version.
- Die [offiziellen Hinweise zu Styling-Frameworks](https://datatables.net/manual/core/styling/frameworks)
  bezeichnen `datatables.net-bs5` als Bootstrap-5-Stylingpaket für den Kern.
- Die [npm-Registry-Metadaten für
  `datatables.net-bs5@1.10.25`](https://registry.npmjs.org/datatables.net-bs5/1.10.25)
  nennen Version 1.10.25, MIT und die Abhängigkeiten `datatables.net ^1.10.15`
  sowie `jquery >=1.7`. DataTables 1.10.25 und das bereits gepinnte jQuery 3.6.2
  liegen innerhalb dieser Bereiche.

Der offizielle npm-Tarball
`https://registry.npmjs.org/datatables.net-bs5/-/datatables.net-bs5-1.10.25.tgz`
wurde vor der Übernahme gegen beide Registry-Prüfwerte verifiziert:

- SHA-1: `68d6a3e109aec0d6a57645844434025215a89656`
- SHA-512, Base64:
  `SiONLsCrVOuSZm8AcKcMrpo9ZriCdtuf9udzxwK92yrI2a+mnuSi0EreG35Rcq1ORRELqrp3/2o+qcTHo7fSsg==`

Der CDN- und npm-Build der minifizierten CSS-Datei sind bytegleich. Die
minifizierten JavaScript-Dateien sind zwei offizielle, funktional gleiche
Build-Artefakte, aber nicht bytegleich: Der CDN-Build enthält zusätzliche
Compiler-Polyfills. Installiert ist konsequent nur das aus dem verifizierten
npm-Paket stammende Browser-/UMD-Artefakt; CDN- und npm-Dateien wurden nicht
vermischt.

## Bestehender Einsatz

Der alte DataTables-Kern und der Bootstrap-3-Adapter werden derzeit von neun
Seiten geladen: `dashboard.php`, `devices.php`, `devicesEvents.php`,
`deviceDetails.php`, `services.php`, `serviceDetails.php`, `icmpmonitor.php`,
`icmpmonitorDetails.php` und `journal.php`.

Die alten Dateien unter `front/lib/` wurden nicht geändert. Die neue,
isolierte Kopie unter `front/lib/datatables/` ist lediglich die vorbereitete
v4-Laufzeit. Produktive Seiten wurden in diesem Schritt nicht umgeschaltet.

## Gepinnte Dateien und Integrität

| Paket | Datei | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| DataTables 1.10.25 | `datatables.net-1.10.25/jquery.dataTables.min.js` | 86549 | `56cd4fafefd322acdf1047e13620fb13586b8713ca2da55c4a7055e06fb54b41` |
| DataTables-Lizenz, MIT | `datatables.net-1.10.25/LICENSE.txt` | 1096 | `4f6610cece9940c3bf3de202a920e7b69491e7947d968330f03db592caa456dd` |
| Bootstrap-5-Adapter 1.10.25 | `datatables.net-bs5-1.10.25/css/dataTables.bootstrap5.min.css` | 5694 | `424b53d5a48e6a670464f7d4661d21a6f06d18dff230c462c9a6c354a55c33ac` |
| Bootstrap-5-Adapter 1.10.25 | `datatables.net-bs5-1.10.25/js/dataTables.bootstrap5.min.js` | 2058 | `6280342d66e0095fe6f6ba4ffb5951b16d2a3e660dde2dc905ad18621d5b6389` |
| Adapter-Lizenz, MIT | `datatables.net-bs5-1.10.25/LICENSE.txt` | 1120 | `62b5fdd54371b4f6bd07a60dbc78b38814f49a8028213f540dc2733cad605382` |
| offizielle Paketmetadaten | `datatables.net-bs5-1.10.25/package.json` | 1147 | `de53ec8b0aae9f988a68f488e291eb421708001bf4a9cb5e2e1b31618b5c648f` |

`jquery.dataTables.min.js` wurde zusätzlich mit `cmp` gegen
`front/lib/AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js`
geprüft und ist bytegleich. Das Manifest kann reproduzierbar geprüft werden:

```sh
cd front/lib/datatables
sha256sum -c SHA256SUMS
```

## Ladefolge für eine spätere v4-Seite

1. `front/lib/adminlte-4.9.1/css/adminlte.min.css`
2. `front/lib/datatables/datatables.net-bs5-1.10.25/css/dataTables.bootstrap5.min.css`
3. `front/lib/jquery-3.6.2/jquery.min.js`
4. `front/lib/datatables/datatables.net-1.10.25/jquery.dataTables.min.js`
5. `front/lib/datatables/datatables.net-bs5-1.10.25/js/dataTables.bootstrap5.min.js`
6. `front/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js`
7. `front/lib/adminlte-4.9.1/js/adminlte.min.js`
8. seitenbezogene Tabelleninitialisierung

Der Bootstrap-3-Adapter darf in einem v4-Dokument nicht parallel geladen
werden. AdminLTE 4 enthält bereits das Bootstrap-5-CSS; eine zusätzliche
`bootstrap.min.css`-Datei ist nicht erforderlich.

## Isolierte Chromium-Probe

Die statische Probe
`_workspace/tests/adminlte4/datatables/compatibility.html` enthält 13 rein
synthetische Zeilen und führt keine Backend-Abfrage aus. Sie verwendet die
gepinnten v4-Dateien und testet über die echten UI-Ereignisse:

- Sortieren der Latenzspalte absteigend: erster Wert `120`;
- Suchen nach `Offline`: genau drei Treffer;
- Wechsel über die Bootstrap-Paginierung auf Seite 2: Seite `1` der
  nullbasierten API, erster Datensatz `Node-06`;
- fünf sichtbare Zeilen bei einer Seitengröße von fünf;
- Bootstrap-5-Klassen `form-control`, `form-select`, `pagination`, `page-item`
  und `page-link` an den generierten Bedienelementen;
- Bootstrap-Container `.table-responsive` mit `overflow-x: auto`.

Die Probe bestand unter Chromium 153.0.8010.52 bei 1440 × 900 und bei
390 × 844 Pixeln. Der Desktop- und Mobil-Screenshot wurden visuell geprüft. Im
schmalen Viewport bleiben Suche, Seitengröße, Paging und Tabellenzeilen nutzbar;
weitere Spalten sind über den sichtbaren horizontalen Scrollbalken erreichbar.
Der maschinenlesbare DOM-Status war `data-probe-status="pass"`, DataTables
meldete Runtime-Version `1.10.25`, und es wurden keine JavaScript-Fehler erfasst.

Die visuell geprüften Desktop- und Mobilbilder liegen dauerhaft unter
`_workspace/adminlte4-browser-reference/datatables-compatibility-*.png`.

Die Bezeichnung „responsive“ meint hier bewusst den Bootstrap-5-Container, nicht
die optionale DataTables-Responsive-Erweiterung. Der Altbestand lädt diese
Erweiterung nicht; sie ungefragt hinzuzufügen würde Verhalten und DOM-Vertrag
ändern. Eine echte Spaltenumschaltung bleibt deshalb ein separates, späteres
Migrationspaket.
