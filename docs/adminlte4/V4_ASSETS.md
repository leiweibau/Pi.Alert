# AdminLTE-4-Kernassets

Stand: 23. September 2026

## Gepinnte Versionen

Die neue Oberfläche verwendet ausschließlich lokal ausgelieferte, exakt
versionierte Dateien. Zur Laufzeit gibt es keine CDN- oder Node.js-Abhängigkeit.

| Paket | Version | Offizielle Herkunft | Lizenz |
| --- | --- | --- | --- |
| AdminLTE | 4.9.1 | npm `admin-lte@4.9.1`, Tarball `https://registry.npmjs.org/admin-lte/-/admin-lte-4.9.1.tgz`; Upstream-Release `https://github.com/ColorlibHQ/AdminLTE/releases/tag/v4.9.1` | MIT; lokaler Lizenztext: `front/lib/adminlte-4.9.1/LICENSE` |
| Bootstrap | 5.3.8 | npm `bootstrap@5.3.8`, Tarball `https://registry.npmjs.org/bootstrap/-/bootstrap-5.3.8.tgz`; Upstream-Release `https://github.com/twbs/bootstrap/releases/tag/v5.3.8` | MIT; lokaler Lizenztext: `front/lib/bootstrap-5.3.8/LICENSE` |
| Popper Core (im Bootstrap-Bundle eingebettet) | 2.11.8 | npm `@popperjs/core@2.11.8`, Tarball `https://registry.npmjs.org/@popperjs/core/-/core-2.11.8.tgz`; Upstream `https://github.com/popperjs/popper-core` | MIT; lokaler Lizenztext: `front/lib/bootstrap-5.3.8/LICENSE.popperjs-core` |
| jQuery | 3.6.2 | npm `jquery@3.6.2`, Tarball `https://registry.npmjs.org/jquery/-/jquery-3.6.2.tgz`; Upstream `https://github.com/jquery/jquery/tree/3.6.2` | MIT; lokaler Lizenztext: `front/lib/jquery-3.6.2/LICENSE` |

AdminLTE 4.9.1 deklariert Bootstrap `^5.3.8` als Peer-Abhängigkeit. Der
`package-lock.json` des offiziellen Tags `v4.9.1` löst Bootstrap exakt auf 5.3.8
und Popper Core exakt auf 2.11.8 auf; seine SHA-256-Prüfsumme ist
`e94f5f904824234e952d6ca49d4a1a9c6b7d2499c41ea107a48d54677c552894`.
Deshalb ist 5.3.8 der konkrete, getestete Bootstrap-Patchstand für diese
Integration. Das verwendete Bootstrap-Bundle enthält Popper; ein separates
Popper-Script ist nicht nötig.

## Installierte Laufzeitdateien

| Datei | Bytes | SHA-256 |
| --- | ---: | --- |
| `front/lib/adminlte-4.9.1/css/adminlte.min.css` | 312124 | `0934e1e6298dd4666440703dd613fdbb1a3c5f1554467923c29464ef4a5682ea` |
| `front/lib/adminlte-4.9.1/css/adminlte-colors.min.css` | 52404 | `61395c7f6fd2e904391e3e80e948cd994449e8e634ffe600fb581a50739b894d` |
| `front/lib/adminlte-4.9.1/js/adminlte.min.js` | 27750 | `922181e2098af61f2c6d8e997b1acccf37bdfa27c9dd0c9e20d15282ae31eddb` |
| `front/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js` | 80496 | `e4fd49181388c48ec5040bd3fe66f57c29c8e67fcd8502b3354b96ec7ab47cc7` |
| `front/lib/adminlte-4.9.1/LICENSE` | 1082 | `9e8b0d4e7ce8c13e67a2373d5094910c263fd95b1e49bdf45dc2a9813be5996b` |
| `front/lib/bootstrap-5.3.8/LICENSE` | 1093 | `4620c84ad5ce8602ff65640ed6b7c8b78ebb9e036584f0ebc1ccc88206a4bb51` |
| `front/lib/bootstrap-5.3.8/LICENSE.popperjs-core` | 1082 | `bf67e2c9b7974543bb18ced3a0b37f53729aaff953b442dd89a3adaf90fd93e8` |
| `front/lib/jquery-3.6.2/jquery.min.js` | 89942 | `da4ad864a87ffcf71c851b5df87f95cb242867f7b711cae4c6133cc9cc0048f0` |
| `front/lib/jquery-3.6.2/LICENSE` | 1097 | `d4db9ebe6f29f5168eac45ad713f055623ac5d0dcd5ba92da23d650ae012020d` |

Die Dateibanner der tatsächlich installierten minifizierten Dateien nennen
AdminLTE 4.9.1 beziehungsweise Bootstrap 5.3.8 und die MIT-Lizenz. Die Dateien
sind unverändert aus den genannten npm-Tarballs übernommen.
Die später ergänzte optionale AdminLTE-Farbdatei 4.9.1 wurde aus dem in der
offiziellen Farbdokumentation genannten Distributionspfad bezogen und
bytegenau mit `ColorlibHQ/AdminLTE`-Tag `v4.9.1` abgeglichen. Sie wird lokal
ausgeliefert und aktiviert die 14 zusätzlichen Farbnamen für Header/Sidebar.

Die npm-Tarballs wurden vor der Übernahme gegen die Registry-Metadaten geprüft:

| Paket | npm-SHA-1 (`dist.shasum`) | npm-SHA-512 (`dist.integrity`, Base64) |
| --- | --- | --- |
| AdminLTE 4.9.1 | `89a016163670e6428368512035b8f0671ed1896a` | `NmVAH7INZjEE+OpVGATGtoRQllaetxzT9AaOMm8eMa9CxW+Okd/kWwCThYeJ7g/sR0/t9sbQoIfCk9Aht8hIqQ==` |
| Bootstrap 5.3.8 | `6401a10057a22752d21f4e19055508980656aeed` | `HP1SZDqaLDPwsNiqRqi5NcP0SSXciX2s9E+RyqJIIqGo+vJeN5AJVM98CXmW/Wux0nQ5L7jeWUdplCEf0Ee+tg==` |
| Popper Core 2.11.8 | `6b79032e760a0899cd4204710beede972a3a185f` | `P1st0aksCrn9sGZhp8GMYwBnQsbvAWsZAX44oXNNvLHGqAOcoVxmjZiohstwQ7SqKnbR47akdNi+uleWD8+g6A==` |
| jQuery 3.6.2 | `8302bbc9160646f507bdf59d136a478b312783c4` | `/e7ulNIEEYk1Z/l4X0vpxGt+B/dNsV8ghOPAWZaJs8pkGvsSC0tm33aMGylXcj/U7y4IcvwtMXPMyBFZn/gK9A==` |

## Einbindung und Duplikatvermeidung

`adminlte.min.css` ist kein reines Theme-Delta. Sein Upstream-SCSS importiert
Bootstrap Root, Reboot, Grid, Formulare, Buttons, Navigation und alle weiteren
Bootstrap-Komponenten, bevor die AdminLTE-Regeln folgen. Der ausgelieferte Build
enthält diese Regeln nachweislich. Daher darf eine v4-Seite **nicht zusätzlich**
`bootstrap.min.css` laden. Unter `front/lib/bootstrap-5.3.8/` wurde folglich
bewusst keine zweite Bootstrap-CSS-Kopie installiert.

Empfohlene Reihenfolge:

1. `lib/adminlte-4.9.1/css/adminlte.min.css`
2. `lib/adminlte-4.9.1/css/adminlte-colors.min.css`
3. vorhandene, für Pi.Alert benötigte Plugin-/Anwendungs-CSS
4. `lib/jquery-3.6.2/jquery.min.js`, solange der Anwendungscode jQuery noch
   benötigt
5. benötigte jQuery-Plugins, jeweils nur einmal
6. `lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js`
7. `lib/adminlte-4.9.1/js/adminlte.min.js`
8. v4-Anwendungs- und Seiten-JavaScript

AdminLTE 4 selbst benötigt kein jQuery. jQuery bleibt in dieser Phase nur wegen
des vorhandenen Pi.Alert-Codes und seiner Plugins erhalten und darf pro Dokument
nur einmal geladen werden. Die neue Kopie ist bytegleich mit der bereits
inventarisierten 3.6.2-Datei, vermeidet aber einen v4-Laufzeitpfad in den
AdminLTE-2-Bibliotheksbaum. Bootstrap-3-CSS/-JavaScript und AdminLTE-2-Dateien
dürfen in einem v4-Dokument nicht vorkommen.

Die minifizierten Upstream-Dateien enthalten Kommentare zu optionalen Source
Maps. Source Maps sind keine Laufzeitabhängigkeit und wurden nicht installiert;
damit bleibt der produktive Satz klein und enthält keine ungenutzten Varianten,
Demo-Bilder, Dokumentationsstile, RTL-Dubletten oder ESM-Dubletten.

## Reproduzierbare Prüfung

```sh
sha256sum \
  front/lib/adminlte-4.9.1/css/adminlte.min.css \
  front/lib/adminlte-4.9.1/css/adminlte-colors.min.css \
  front/lib/adminlte-4.9.1/js/adminlte.min.js \
  front/lib/bootstrap-5.3.8/js/bootstrap.bundle.min.js \
  front/lib/adminlte-4.9.1/LICENSE \
  front/lib/bootstrap-5.3.8/LICENSE \
  front/lib/bootstrap-5.3.8/LICENSE.popperjs-core \
  front/lib/jquery-3.6.2/jquery.min.js \
  front/lib/jquery-3.6.2/LICENSE
```

Zusätzlich sind bei einer Versionsänderung erneut npm-Metadaten, Tarball-
Integrität, Dateibanner, `peerDependencies.bootstrap`, Lizenz und die importierten
Bootstrap-Komponenten im AdminLTE-SCSS zu prüfen. Ein Bereich wie `^5.3.8` ist
kein ausreichender Produktions-Pin; die ausgelieferte Patchversion bleibt exakt.

## Abgrenzung: Kalender und Ressourcen-Timeline

Dieses Assetpaket aktualisiert FullCalendar nicht. Die Ressourcen-Timeline in
`presence.php` ist eine FullCalendar-Premium-Funktion. Für die v4-Migration
bleiben deshalb die vorhandenen, bereits inventarisierten Versionen FullCalendar
3.10.5 und FullCalendar Scheduler 3.10.4 samt bisheriger GPLv3-Open-Source-Option
separat erhalten. Insbesondere darf kein aktueller Premium-Scheduler implizit
durch dieses AdminLTE-/Bootstrap-Paket hinzukommen. Vor einem späteren Upgrade
sind Funktionsumfang und Lizenz erneut anhand von `https://fullcalendar.io/license`
und `https://fullcalendar.io/docs/timeline-view` zu prüfen.
