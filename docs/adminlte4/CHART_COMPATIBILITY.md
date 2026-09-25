# Chart.js-Kompatibilität für AdminLTE 4

> Historischer Stand der Erstportierung vom 23. September 2026. Die aktive
> Oberfläche lädt inzwischen Chart.js 4.5.1 als lokales UMD-Bundle aus
> `front/lib/chart.js-4.5.1/`. Der Tooltip-Modus `index` ist auf Webservice-
> und ICMP-Details ausdrücklich gesetzt. Die Vorprüfung und ihre Grenzen sind
> in `_workspace/tests/adminlte4/chartjs/UPGRADE_451_FINDINGS.md` dokumentiert.

Stand: 23. September 2026

## Entscheidung

Chart.js bleibt für die AdminLTE-4-Migration auf Version 3.0.2. Die tatsächlich
geladene, nicht minifizierte UMD-Runtime wurde bytegleich nach
`front/lib/chart.js-3.0.2/chart.js` übernommen. Chart.js hat keine
Abhängigkeit von AdminLTE, Bootstrap oder jQuery; die Probe lädt dennoch den
v4-Shell-Stack, um Konflikte im vorgesehenen Dokumentkontext auszuschließen.

Produktive Seiten, Datenabfragen und Altdateien wurden in diesem Schritt nicht
geändert.

## Dateien, Lizenz und Integrität

| Datei | Bytes | SHA-256 |
| --- | ---: | --- |
| `chart.js` | 374056 | `471a1627c41b1ea6227a867d00393bf449a680248c246a0c42bd41e7c5919d5a` |
| `LICENSE` | 1083 | `36c7537a410ca4cf061505c8a06a26cb05d02793128db76a8f1fcca0cdfc78e5` |

`chart.js` wurde mit `cmp` gegen
`front/lib/AdminLTE/bower_components/chart.js/chart.js` geprüft und ist
bytegleich. Dateibanner und Lizenz nennen Chart.js 3.0.2, MIT und die Chart.js
Contributors. Reproduzierbare Integritätsprüfung:

```sh
cd front/lib/chart.js-3.0.2
sha256sum -c SHA256SUMS
```

Die vorhandenen `chart.min.js`, ESM- und Helper-Dateien werden von den
Pi.Alert-Seiten nicht geladen und gehören deshalb nicht zum v4-Runtime-Pin.

## Bestehende Diagrammverträge

Der Bestand erzeugt Chart-Instanzen auf `dashboard.php`, `devices.php`,
`deviceDetails.php`, `serviceDetails.php`, `icmpmonitor.php`,
`icmpmonitorDetails.php` und `presence.php` sowie aus
`js/graph_online_history.js`. Relevant sind:

- gestapelte Balken für Online-/Offline-/Archiv-Verläufe;
- Doughnut-Charts für Geräte-, ICMP- und Servicezustände;
- Liniencharts für Speedtest-, Latenz- und Verlaufsdaten;
- globale Instanzen wie `window.speedtestChart`,
  `window.devicesDonutChart`, `window.devicesDonutIcmpChart` und das
  `historyStackedCharts`-Objekt, die vor einem Neuaufbau zerstört werden
  müssen.

Die bereits v3-konfigurierten Diagramme verwenden `scales.x`/`scales.y`,
`options.plugins.legend`, `options.plugins.tooltip`, `cutout: '60%'` und
`tension`. Diese Formen sind unter der gepinnten Runtime lauffähig.

## Bestandsfehler im Dashboard

Nur der History-Stack in `dashboard.php` verwendet weiterhin v2-Syntax:

```js
scales: {
  xAxes: [{ stacked: true }],
  yAxes: [{ stacked: true, ticks: { beginAtZero: true, stepSize: 1 } }]
},
legend: { position: 'bottom' },
tooltips: { mode: 'index', intersect: false }
```

Chart.js 3.0.2 bricht damit nicht ab und protokolliert in diesem Fall auch
keine Warnung. Das macht den Fehler leicht übersehbar. Die Chromium-Probe
belegt jedoch die stille Fehlinterpretation:

- die Runtime erzeugt Skalen mit den IDs `xAxes` und `yAxes`, übernimmt aber
  `stacked: true` aus den Arrays nicht;
- die Legende bleibt oben statt der angeforderten Position unten;
- der Tooltip-Modus `index` wird übernommen, `intersect: false` dagegen nicht
  und bleibt `true`.

Die v4-Dashboard-Kopie muss diese Konfiguration mit unveränderten Daten auf
v3-Syntax umstellen:

```js
scales: {
  x: { stacked: true },
  y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
},
plugins: {
  legend: { position: 'bottom' },
  tooltip: { mode: 'index', intersect: false }
}
```

Diese Korrektur darf nicht in die Altseite zurückgeschrieben werden. Nach der
Dashboard-Portierung sind die tatsächlichen History-Daten und der
Destroy-/Refresh-Pfad separat zu prüfen.

## Ladefolge und Integration

Eine v4-Seite lädt `front/lib/chart.js-3.0.2/chart.js` einmal vor ihrer
seitenspezifischen Diagrammlogik. jQuery ist dafür nicht erforderlich. Vor
einem erneuten `new Chart(...)` auf demselben Canvas muss eine vorhandene
Instanz mit `destroy()` entfernt werden; globale Instanznamen und
`historyStackedCharts[dataSource]` sind bis zur Seitenmigration beizubehalten.

## Isolierte Chromium-Probe

`_workspace/tests/adminlte4/chartjs/compatibility.html` verwendet nur
synthetische Daten und keine Backend-Abfrage. Sie erzeugt mit deaktivierten
Animationen:

- einen gestapelten Balkenchart mit zwei Datensätzen und acht Elementen;
- einen Doughnut-Chart mit drei Segmenten und `60%` Ausschnitt;
- einen Linienchart mit vier Punkten und `tension: 0.2`;
- einen separaten Balkenchart mit der unveränderten v2-Konfiguration, um den
  Dashboardfehler maschinenlesbar nachzuweisen.

Der Lauf unter Chromium 153.0.8010.52 ergab
`data-probe-status="pass"`. Die drei v3-Diagramme wurden mit den erwarteten
Elementzahlen und Optionen aufgebaut; die v2-Probe bestätigte die oben
beschriebenen stillen Abweichungen. Es trat keine JavaScript-Ausnahme auf.

## Grenzen

Die Probe deckt die relevanten Diagrammtypen und Optionsformen ab, aber keine
produktiven AJAX-Antworten, großen Datenmengen, Resize-Schleifen, Druckansicht
oder Touch-/Tastaturinteraktion. Eigene Center-Text-Plugins des Dashboards und
Farbanpassungen für Hell-/Dunkelmodus müssen in den jeweiligen Seiten-E2E-
Tests geprüft werden.
