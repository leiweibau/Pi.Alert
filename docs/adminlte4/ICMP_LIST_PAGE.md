# ICMP-Monitor-Hauptseite unter AdminLTE 4

Stand: 23. September 2026

## Route und Marker

Die parallele Route ist `front/v4_icmpmonitor.php`. Die Listenansicht besitzt den eindeutigen DOM-Marker `#icmpmonitor-page`, der Bulk-Modus `#icmpmonitor-bulk-page`. Seiteneigene Ressourcen sind `front/css/icmpmonitor.css` und `front/js/icmpmonitor.js`.

## Abgedeckte Verträge

- Statuskacheln und Filter verwenden unverändert `getICMPHostTotals` und `getDevicesList&status=all|connected|favorites|down|archived`.
- DataTables behält Suche, Sortierung, Seitenlänge, Paging sowie die Parameter `Front_Devices_Rows` und `Front_Devices_Order`. Serverwerte werden als Text ausgegeben; nur kontrolliertes Link-, Status- und Aktionsmarkup wird erzeugt.
- Der Verlauf nutzt dieselben von `prepare_icmpscan_graph_history()` gelieferten Reihen. Die Chart.js-3-Konfiguration ist gestapelt und hat einen ganzzahligen Nullpunkt.
- Anlegen nutzt `insertNewICMPHost` mit `icmp_ip`, `icmp_hostname`, `icmp_fav`, `alertdown` und `alertevents`. Die sichtbaren Checkboxen bleiben die maßgebliche Payloadquelle und werden über das gepinnte iCheck 1.0.3 bedient.
- Bearbeiten öffnet unverändert `icmpmonitorDetails.php?hostip=…`. Einzellöschen nutzt `deleteICMPHost` mit `icmp_ip`; beide Ziele sind pro Tabellenzeile erreichbar.
- Bulk-Felder behalten ihre bisherigen Namen. Die Seite führt dieselben vorbereiteten Updates gegen `ICMP_Mon` aus und nutzt für die Bulk-Löschung `BulkDeletion` mit `hosts[]`. Alle POSTs laufen über `pialertPost` und damit über den gemeinsamen CSRF-/Deduplizierungsvertrag.
- Die Seite lädt ausschließlich die lokal gepinnten DataTables-, iCheck- und Chart.js-Ressourcen der v4-Shell. Bootstrap-3- und AdminLTE-2-Ressourcen werden nicht geladen.

## Responsive und Null-Daten

Die Statuskacheln brechen auf Mobilbreite zweispaltig um, die Tabelle bleibt in einem horizontal begrenzten Bootstrap-Container und die Bulk-Hostkarten werden einspaltig. Leere Endpointdaten werden als DataTables-Leerzustand dargestellt; leere Graphreihen und eine leere Bulk-Auswahl bleiben ohne JavaScript-Ausnahme bedienbar.

## Prüfung

Die synthetische Fixture wurde mit Chromium 153 bei 1440×1000 und 390×844 geprüft. Vier Hostdatensätze deckten Online, Down, Offline und Archiv ab: Totals, alle fünf Filter, Diagramm, DataTables, iCheck, Bulk-Feldfreigabe, Suche und Auswahl funktionierten ohne Console-, Netzwerk- oder JavaScriptfehler und ohne Dokumentüberlauf. Add und Delete wurden im Browser bis zum exakten Endpoint/Payload gemockt; die Bulk-Löschung wurde nicht produktiv ausgelöst. Ein zweiter Lauf mit geleerten ICMP-Fixturetabellen ergab fünf Nullzähler, den DataTables-Leerzustand, ein leeres Bulk-Raster und ein weiterhin gültiges Diagramm ohne Browserfehler. Die isolierte Fixture-Datenbank wurde anschließend aus der Sicherung wiederhergestellt.

## Grenzen

Die bestehende Detailseite ist noch nicht nach v4 portiert. Daher bleiben Bearbeiten und alle erweiterten Hostfelder bewusst auf der bisherigen Detailroute. Reale Schreiboperationen, persistierte Parameter und ein echter Monitoring-Schalter sind nicht Teil der synthetischen Browserprüfung; Add/Delete/Bulk werden dort nur gemockt. Navigation und Hotkey-Integration erfolgen zentral durch den Shell-Verantwortlichen.

Die Seite ist noch nicht an die neue eigenständige v4-UI-Konfiguration angebunden. Nach Bereitstellung des gemeinsamen Helpers müssen die Sichtbarkeit der fünf ICMP-Headerkacheln (`all`, `con`, `fav`, `dnw`, `arc`) und eine künftige ICMP-Tabellenspaltenauswahl daraus gelesen werden. Bis dahin sind alle fünf Kacheln und die sechs fachlichen Spalten Name, IP, Favorit, Antwortzeit, Scanzeit und Status sichtbar; interne Transportspalten bleiben ausgeblendet, die Aktionsspalte bleibt fest sichtbar.
