# AdminLTE-2-Referenz und Wiederherstellung

Stand der Sicherung: 23. September 2026, unmittelbar vor den produktiven
AdminLTE-4-Frontendarbeiten.

Die vollständige Referenz der bisherigen Anwendung und ihrer lokalen Assets
liegt außerhalb des Webroots unter:

```text
/opt/pialert/_workspace/adminlte2-reference/front/
```

`SOURCE_FILES.sha256` enthält SHA-256-Prüfsummen für alle 4.617 gesicherten
Dateien. Die Quelldateien wurden unmittelbar vor und nach dem Kopieren erneut
gehasht; beide Quellmanifeste und das Manifest der Kopie waren identisch. Die
Referenz ist Eigentum von `root` und nach Abschluss der Prüfung rekursiv ohne
Schreibrechte abgelegt. Sie dient damit als unveränderte Implementierungs- und
Rollbackreferenz, nicht als zweiter Webroot.

## Bewusst ausgeschlossene Laufzeitdaten

Die 14 zum Sicherungszeitpunkt vorhandenen Dateien unter `front/reports/`
einschließlich `front/reports/archived/` sind erzeugte Scan-, Ereignis- und
Internetberichte. Sie gehören zum Laufzeitstand, enthalten potenziell lokale
Netzwerkdaten und sind weder Anwendungscode noch für einen Frontend-Rollback
erforderlich. Deshalb wurden sie nicht in die unveränderliche Code- und
Assetreferenz übernommen. Die leere Verzeichnisstruktur `front/reports/` bleibt
in der Referenz erhalten.

`front/php/tmp/` enthielt zum Sicherungszeitpunkt keine Laufzeitdateien, sondern
nur den Platzhalter `.git_ignore`; dieser ist Bestandteil der Referenz.

## Integrität prüfen

```sh
cd /opt/pialert/_workspace/adminlte2-reference
sha256sum --check SOURCE_FILES.sha256
```

Die Prüfung muss für jede Datei `OK` melden. Ein zusätzlicher Vollständigkeits-
check kann die Zahl der manifestierten Dateien bestätigen:

```sh
wc -l /opt/pialert/_workspace/adminlte2-reference/SOURCE_FILES.sha256
```

Erwartet werden `4617` Einträge.

## Wiederherstellung

Vor einem Rollback zuerst die Integritätsprüfung ausführen. Dann den aktuellen
Webroot separat sichern und die Referenz mit administrativen Rechten
zurückkopieren:

```sh
rsync -a --delete \
  /opt/pialert/_workspace/adminlte2-reference/front/ \
  /opt/pialert/front/
```

Die bewusst nicht gesicherten Laufzeitberichte werden durch `--delete`
entfernt. Falls sie erhalten bleiben sollen, müssen sie vor dem Rollback
separat gesichert und anschließend gezielt zurückkopiert werden. Nach dem
Rollback sind Besitzer und Rechte entsprechend dem Webserver-/Paketkontext zu
setzen und die relevanten Seiten sowie Login, Navigation und Schreibaktionen
zu prüfen.
