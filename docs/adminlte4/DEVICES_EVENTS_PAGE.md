# Devices-Events-Seite unter AdminLTE 4

Stand: 23. September 2026

`front/v4_devicesEvents.php` portiert Zeitraumwahl, sechs Ereignisfilter,
Summenzähler und die Ereignistabelle auf die v4-Shell. Die Seite nutzt den
gepinnten DataTables-Kern 1.10.25 mit dem Bootstrap-5-Adapter. Backend, Queries
und Antwortformate wurden nicht geändert.

## Erhaltene Verträge

- Parameter `Front_Events_Period` und `Front_Events_Rows` werden über
  `php/server/parameters.php` gelesen und bei einer Bedienänderung mit dem
  gemeinsamen CSRF-geschützten `pialertPost` gespeichert.
- Summen kommen unverändert über `events.php?action=getEventsTotals&period=…`.
- Tabellenzeilen kommen unverändert über
  `events.php?action=getEvents&type=…&period=…`.
- Ereignisarten, Zeitraumwerte, Standardsortierung, Spaltensichtbarkeit,
  Seitengrößen und die sichere Textdarstellung entsprechen der Altseite.
- Geräte- und ICMP-Ziele werden weiterhin URL-kodiert. Bis zur Migration der
  Detailseiten führen sie bewusst auf die bestehenden AdminLTE-2-Seiten
  `deviceDetails.php` und `icmpmonitorDetails.php`.

## Abdeckung und Grenzen

Die Seite enthält keine neue Schreibaktion. Nur die bereits vorhandenen beiden
Darstellungsparameter werden gespeichert. Der synthetische Browsertest mockt
diese POSTs und führt deshalb keine produktive Änderung aus. Die Navigation in
der gemeinsamen Shell ist nicht Bestandteil dieses Seitenpakets und muss vom
Shell-Eigentümer auf `v4_devicesEvents.php` geschaltet werden.

Die isolierte Fixture-Kopie wurde mit Chromium 153 bei 1440 × 1000 und
390 × 844 Pixeln geprüft. Beide Ansichten blieben ohne Dokumentüberlauf,
HTTP-, Netzwerk-, Console- oder JavaScriptfehler. DataTables meldete Version
1.10.25; die Tabelle zeigte sieben synthetische Zeilen und die Zähler
`5 / 4 / 2 / 1 / 1 / 1`. Die Umschaltung auf Sessions stellte die fünf
betroffenen Spalten korrekt um. Zeitraum `7 days` und Seitengröße `10` lösten
die bisherigen Parameterverträge aus; beide POSTs wurden im Browser abgefangen
und beantwortet, ohne die Fixture-Daten zu verändern. Desktop- und Mobilbild
wurden visuell geprüft.
