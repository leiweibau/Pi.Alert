# AdminLTE-4-Reports-Seite

Stand: 23. September 2026

## Umfang und Einstieg

Die parallele Reports-Seite liegt unter `front/v4_reports.php`. Ihr stabiler
Seitenmarker ist `#reports-page`, der Kartencontainer bleibt als `#Container`
erhalten. Die aktuelle Ansicht verwendet `data-source="current"`, das Archiv
`data-source="archive"`. Seiteneigene Darstellung und Verhalten liegen in
`front/css/reports.css` und `front/js/reports.js`.

Die Seite verwendet die vorhandene v4-Shell. Im anschließenden
Root-Integrationsschritt wurde `v4_reports.php` in Sidebar, Benutzermenü und
Hotkey `R` aktiviert; das Seitenpaket selbst hatte die gemeinsamen Dateien
nicht geändert.

## Übernommenes Verhalten

- Die vorhandene Dateinamenerkennung und die Klassen für Internet, Geräte,
  Webservices, ICMP, Test/System, Nmap und unbekannte DHCP-Server bleiben
  erhalten. Rogue-DHCP-Berichte erscheinen wie in der Referenz vor den
  normalen Karten.
- MAC-, Service- und ICMP-Ziele bleiben
  `deviceDetails.php?mac=…`, `serviceDetails.php?url=…` und
  `icmpmonitorDetails.php?hostip=…`. Status- und Ereignisfarben sowie der
  SSL-Änderungshinweis werden weiter dargestellt; Tooltips verwenden nun den
  Bootstrap-5-Vertrag `data-bs-toggle="tooltip"`.
- Aktuelle und archivierte Reports werden weiterhin über
  `report_source=archive` umgeschaltet. Der Archivzähler und der konfigurierte
  automatische Archivhinweis bleiben sichtbar.
- Downloadziele bleiben `download/report.php?report=<Name>` und ergänzen für
  Archivdateien `report_source=archive`. Sie öffnen wie zuvor einen neuen Tab.
- Einzelnes Löschen und Archivieren bleiben CSRF-geschützte POSTs an den
  vorhandenen Endpunkt `reports.php`; dessen Dateinamensprüfung und
  Journal-Logschlüssel bleiben damit unverändert. JavaScript sendet den POST im
  Hintergrund und lädt anschließend die v4-Ansicht neu. Ohne JavaScript bleibt
  der vorhandene Formular-/Weiterleitungsvertrag als Rückfall erhalten. Vor
  beiden Aktionen erscheint nun ein Bootstrap-5-Warndialog.
- Massenlöschung verwendet unverändert `php/server/files.php` mit
  `action=deleteAllNotifications` beziehungsweise
  `action=deleteAllNotificationsArchive`. Farbspeicherung verwendet
  `php/server/parameters.php`, `action=setReportParameter` und das Array
  `HeadLineColors[]`.
- Die sechs vorhandenen Farbfelder und Vorgabefarben bleiben erhalten. Coloris
  0.24.0 läuft weiterhin als dunkles Pill-Theme ohne Alpha, mit Clear- und
  Close-Button. Das mobile CSS begrenzt die feste Pill-Breite auf den
  Viewport.
- Ein lokaler Text- und Typfilter blendet Karten clientseitig aus. Er verändert
  weder Dateien noch Endpunkte. Bei leerer oder vollständig ausgefilterter
  Menge wird ein Statushinweis ausgegeben.
- Für den Ausdruck werden Shell, Filter und Aktionsleisten ausgeblendet; die
  Reportinhalte verlieren ihre Bildschirm-Scrollhöhe und bleiben druckbar.

DataTables wird absichtlich nicht geladen. Die Referenzseite zeigt Reports als
Karten und hat keinen DataTables-Vertrag; der gepinnte Bootstrap-5-Adapter ist
für diese Seite deshalb ohne Funktion.

## Browsernachweis

Die reproduzierbare Prüfung liegt in
`_workspace/tests/adminlte4/browser_reports.mjs`. Getestet wurde gegen die
isolierte synthetische Installation unter
`/tmp/pialert-adminlte4-fixtures/` mit sieben aktuellen Berichten (alle
Kategorien) und einem archivierten Bericht. Chromium 153 lief bei 1440 × 1000
und 390 × 844 Pixeln.

Der Lauf bestätigte:

- sieben aktuelle Karten, die vorangestellte Rogue-DHCP-Warnung und eine
  Archivkarte;
- Zielparameter aller Detail- und Downloadlinks einschließlich
  `report_source=archive`;
- Textfilter, Archivumschaltung und deaktivierte Archivaktion im Archiv;
- sichtbare Einzel-Lösch-, Einzel-Archiv- und Massenlöschdialoge;
- ein gleichzeitig sichtbares Bootstrap-Modal und Coloris-Picker mit
  `clr-dark` und `clr-pill`;
- gemockte POST-Verträge für `setReportParameter` und
  `deleteAllNotifications`; kein schreibender Request erreichte PHP;
- keine HTTP-, Netzwerk-, Konsolen- oder JavaScriptfehler und keine horizontale
  Dokumentüberbreite in beiden Viewports.

Das maschinenlesbare Ergebnis und die beiden visuell geprüften Screenshots
liegen dauerhaft unter `_workspace/adminlte4-browser-shell/reports/`. Der
Abschlussstatus des Laufs war `pass: true`. Anschließend bestand auch der
integrierte v4-Smoketest auf Desktop/Mobil.

## Offene Grenzen

- Einzel-POSTs wurden bis zum sichtbaren Bestätigungsdialog und anhand ihrer
  Formularfelder geprüft, aber nicht bestätigt. Farb- und Massenlösch-POSTs
  wurden auf Browserebene beantwortet; weder Dateien noch Datenbankparameter
  wurden im Test verändert. Ein ausdrücklich freigegebener, wegwerfbarer
  Schreibintegrationstest bleibt offen.
- Fokus-Rückgabe durch Bootstrap wurde grundlegend benutzt, aber ein
  vollständiger Tastatur-, Screenreader-, Escape- und Tab-Zyklus wurde nicht
  automatisiert.
- Der Druckstil wurde statisch geprüft, jedoch noch nicht als PDF gegen alle
  Reportlängen abgenommen.
- Die neuen neutralen Bezeichnungen `Filter`, `Report Type`, `All`, `Archive`
  und der Leerzustand haben im Altbestand keine eigenen Sprachschlüssel und
  bleiben bis zu einer freigegebenen Spracherweiterung englisch.
- Reale, ungewöhnlich große Reports und nicht aktualisierbare Android-WebViews
  wurden nicht geprüft. Lange Inhalte bleiben innerhalb der Karten scrollbar;
  im Druck wird die Begrenzung aufgehoben.
