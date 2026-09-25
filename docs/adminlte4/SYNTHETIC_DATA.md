# Synthetische Daten für AdminLTE 4

## Zweck und Sicherheitsgrenze

Die P0-UI-Prüfungen verwenden eine eigenständige Laufzeitkopie unter
`/tmp/pialert-adminlte4-fixtures`. Sie enthält ausschließlich synthetische
Datensätze. Produktive Datenbanken, Sicherungen, Konfigurationsdateien,
Geodaten, Berichte, Satellitenablagen und PHP-Temporärdateien werden nicht
kopiert. Das vorhandene System dient nur als Quelle für Anwendungscode und
`sqlite_master`-Schemas. Die Laufzeitverzeichnisse `front/reports/`,
`front/satellites/` und `front/php/tmp/` werden leer angelegt.

Das Fixture-Skript verändert weder `db/` noch `config/` im Quellbaum. Es startet
keinen Scanner, keinen Daemon und keine Systemaktion. Seine PHP-Prüfungen rufen
nur freigegebene GET- beziehungsweise Read-API-Aktionen in der isolierten Kopie
auf.

## Erzeugen

Vom Repository-Wurzelverzeichnis aus:

```bash
python3 _workspace/tests/adminlte4/build_synthetic_fixture.py
```

Existiert das Ziel bereits, bricht das Skript ab. Nur das exakt festgelegte
Fixture-Ziel kann explizit ersetzt werden:

```bash
python3 _workspace/tests/adminlte4/build_synthetic_fixture.py --force
```

Ein anderer `--target` wird absichtlich abgelehnt. Der Aufbau erfolgt zuerst in
einem temporären Staging-Verzeichnis und wird erst nach erfolgreicher Prüfung
nach `/tmp/pialert-adminlte4-fixtures` verschoben.

### Zeitanker

Ohne weitere Option verwendet das Skript das heutige Datum der lokalen
Zeitzone. Dadurch erscheinen History-, Event- und Presence-Daten unmittelbar
in den aktuellen Browseransichten:

```bash
python3 _workspace/tests/adminlte4/build_synthetic_fixture.py --force
```

Für einen byte- und datumsstabilen Referenzlauf kann ein ISO-Datum vorgegeben
werden:

```bash
python3 _workspace/tests/adminlte4/build_synthetic_fixture.py \
  --force --anchor-date 2026-09-23
```

Das tatsächlich verwendete Datum steht außerdem in
`/tmp/pialert-adminlte4-fixtures/.fixture-anchor-date`.

## Datenbestand

Alle Adressen stammen aus reservierten Dokumentationsbereichen und alle
Geräte-MACs sind lokal administrierte Testadressen. Service-Domains enden auf
`.example.test`. Alle UI-relevanten Zeitspalten werden relativ zum Ankerdatum
gesetzt: Geräte-Erstkontakte liegen bis zu 14 Tage davor, aktuelle Scans,
Events und Sessions am Ankertag. Die Graph-Historie umfasst pro Datenquelle 24
Stundenpunkte von 19:00 Uhr am Vortag bis 18:00 Uhr am Ankertag. Service- und
ICMP-Verläufe beginnen ebenfalls am Vorabend. Dadurch enthält ein aktuelles
12-/24-Stunden-Fenster zu jeder Uhrzeit mindestens einen vergangenen Messpunkt.
Zertifikatsdaten liegen jeweils ungefähr ein Jahr vor und nach dem Ankerdatum.

| Bereich | Umfang | Abgedeckte UI-Zustände |
| --- | ---: | --- |
| Devices | 6 | online, offline, down, new, favorite, archived, infrastructure |
| Services | 4 | HTTP 200, Redirect/Warnung, HTTP 503, nicht erreichbar |
| Service-Events | 7 | Status- und Latenzverlauf einschließlich Timeout |
| ICMP Hosts | 4 | online, down, offline ohne Alarm, archived, favorite |
| ICMP Events | 5 | RTT-Verlauf und Nichterreichbarkeit |
| Device Events | 5 | connect, disconnect, new, down, voided |
| Sessions/Presence | 4 | abgeschlossen, aktiv und fehlendes Gegenereignis |
| Online History | 48 | je 24 Punkte für Device- und ICMP-Diagramme |

Zusätzlich enthält das Fixture zwei Service-Journalzeilen und zwei
ICMP-Verbindungsereignisse. Views wie `Events_Devices` und `Sessions_Devices`
stammen aus dem aktuellen Schema und werden dadurch ebenfalls geprüft.

## Automatische Verifikation

Während des Aufbaus prüft das Skript:

- die exakten Zeilenzahlen und mindestens einen Datensatz je wichtigem
  Read-Filter;
- dass History-, Service- und ICMP-Zeilen im 24-Stunden-Ankerfenster sowie
  Event- und Presence-Zeilen auf dem Ankertag liegen und die üblichen
  Zeitfilter damit Daten liefern;
- die öffentliche Read-API `system-status` mit dem ausschließlich im Fixture
  gesetzten Schlüssel `synthetic-adminlte4`;
- die bestehenden Read-Aktionen `getDevicesTotals`, `getServiceMonTotals`,
  `getICMPHostTotals` und `getEventsTotals` über die unveränderten PHP-Endpunkte;
- dass die erwarteten Kategorien und Summen aus den synthetischen Daten
  zurückgegeben werden.

Fehlt die PHP-CLI, bleiben die SQLite-Vertragsprüfungen aktiv und die
PHP-Endpunktprüfung wird übersprungen. Für eine vollständige P0-Prüfung ist eine
PHP-CLI mit SQLite3-Erweiterung erforderlich.

## Lokale Nutzung

Die fertige Kopie kann beispielsweise separat ausgeliefert werden:

```bash
php -S 127.0.0.1:18084 -t /tmp/pialert-adminlte4-fixtures/front
```

Der Server ist nur für lokale, kurzlebige UI-Tests gedacht. Die synthetische
Konfiguration deaktiviert den Webschutz, weil keine produktive Authentisierung
nachgebildet wird. Der API-Schlüssel ist öffentlich dokumentiert und darf nicht
für eine echte Installation übernommen werden.

## Limitierungen

- Das Schema wird aus der lokalen Quellinstallation gelesen. Damit erkennt das
  Fixture Schema-Drift, ist aber kein eigenständiges Datenbank-Migrationsartefakt.
- Scanner-, MQTT-, Nmap-, Backup-, Update-, GeoIP- und sonstige Schreib- oder
  Systemaktionen werden bewusst nicht ausgeführt oder funktional simuliert.
- Die Tools-Datenbank besitzt das aktuelle Schema, enthält aber keine
  synthetischen Scan- oder Queue-Ergebnisse.
- Satelliten, DHCP-Leases und anbieterspezifische Netzwerkimporte bleiben leer.
- Bei einem expliziten, von heute abweichenden `--anchor-date` muss der Kalender
  im Browser zu diesem Datum navigiert werden; aktuelle relative Filter dürfen
  diese Daten erwartungsgemäß ausblenden.
- Die Uhrzeiten sind absichtlich fest und bilden keine Scanner-Taktung nach.
  Für Scheduler- oder exakte Ablaufzeitprüfungen ist das Fixture nicht gedacht.
