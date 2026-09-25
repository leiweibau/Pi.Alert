# Journal-Seite unter AdminLTE 4

Stand: 23. September 2026

`front/v4_journal.php` portiert die Journaltabelle und die Farbverwaltung auf
die v4-Shell. Die Seite nutzt DataTables 1.10.25 mit Bootstrap-5-Adapter und
Coloris 0.24.0 aus den bereits gepinnten lokalen Assets. Backenddateien, die
Bestandsseite und die Journalquery wurden nicht geändert.

## Erhaltene Verträge

- Der öffentliche Einstieg ist `v4_journal.php`; der eindeutige Seitenmarker
  ist `#journal-page`, die Tabelle bleibt `#tableJournal` und das Farbmodal
  bleibt `#modal-set-journal-colors`.
- Die unveränderte Query liefert höchstens 500 Zeilen in absteigender
  `Journal_DateTime`-Reihenfolge. Datum, Methode, Trigger, versteckter Hash und
  Zusatzinformation bleiben in derselben Spaltenreihenfolge.
- `php/server/parameters.php?action=getJournalParameter` lädt die vier Werte
  `journal_trigger_filter`, `journal_trigger_filter_color`,
  `journal_method_filter` und `journal_method_filter_color`.
- Speichern verwendet weiterhin `pialertPost` mit
  `action=setJournalParameter`, `column=trigger|method` und den Arrayfeldern
  `triggerNames`/`triggerColors` beziehungsweise
  `methodNames`/`methodColors`. Damit bleiben CSRF-Header, `_operation_id` und
  die Unterdrückung identischer paralleler POSTs im gemeinsamen v4-Adapter.
- DataTables behält Suche, Reset, Paging, Seitengrößen, Datumssortierung,
  versteckte Hashspalte und die farbige Hervorhebung für Zeit, Methode und
  Trigger bei.

## Sichere Ausgabe und Coloris

Alle Werte aus Datenbank und Übersetzung werden serverseitig kodiert. Nur die
bewusst erzeugten `<br>`-Elemente und ein `span.text-danger` für den Dateihash
werden beim DataTables-Neuzeichnen clientseitig zugelassen; sonstige Elemente,
Attribute und Klassen werden verworfen. Textspalten verwenden den
DataTables-Text-Renderer. Dynamische Eingabewerte werden ausschließlich über
DOM-Eigenschaften gesetzt.

Neu erzeugte Farbeingaben werden mit der in Coloris 0.24.0 vorhandenen API
`Coloris.wrap(colorInput)` registriert. Der ungültige Bestandsaufruf
`Coloris.init()` wird nicht übernommen. Das Pill-Theme bleibt wie bisher fest
dunkel, ohne Alpha und mit Clear-/Close-Schaltflächen. Seitenspezifisches CSS
begrenzt den Picker auf schmalen Viewports, ohne das gemeinsame Vendor-CSS zu
ändern.

## Abdeckung und Grenzen

Die Navigation gehört der gemeinsamen Shell und wird vom Shell-Eigentümer auf
`v4_journal.php` geschaltet. Die Seite verändert weder Backendvalidierung noch
die kommagetrennte Speicherung; Namen mit Kommas behalten daher die bekannte
Bestandsgrenze. Das Schließen über die Footer-Schaltfläche lädt wie bisher nach
einer Sekunde neu. Schließen über Kopfzeile, Escape oder Backdrop lädt nicht neu
und entspricht damit weiterhin dem expliziten alten Close-Handler.

Die isolierte synthetische Browserprüfung lief mit Chromium 153 bei
1440 × 1000 und 390 × 844 Pixeln. Beide Läufe zeigten drei Fixture-Zeilen,
filterten den XSS-Prüfeintrag einzeln, setzten die Suche wieder auf alle drei
Zeilen zurück und hielten die Hashspalte verborgen. Ein injiziertes
`img`/`script`-Fragment erschien als Text; weder ein Element noch eine
Ausführung entstand. Methoden- und Triggerfarben sowie die erlaubte rote
Hashmarkierung waren vorhanden.

Ein dynamisches Methodenfeld wurde von `Coloris.wrap` mit `.clr-field`
umschlossen; der dunkle Pill-Picker blieb ohne Viewportüberlauf über dem
sichtbaren Bootstrap-Modal geöffnet und sein Eingabefeld behielt den Fokus.
Die Prüfung bestätigt außerdem, dass `Coloris.init` weiterhin nicht existiert.
Je ein Methoden- und Trigger-POST wurde im Browser abgefangen: Route, Aktion,
Spalte, vorhandene Arraywerte und die dynamischen Werte `dynamic-method` /
`#abcdef` sowie `dynamic-trigger` / `#fedcba` waren vollständig. Beide POSTs
wurden gemockt und änderten keine Fixture-Daten. Es gab in beiden Viewports
keinen Dokumentüberlauf und keine HTTP-, Netzwerk-, Console- oder
JavaScriptfehler; die Screenshots wurden visuell geprüft.
Die Browserartefakte wurden dauerhaft nach
`_workspace/adminlte4-browser-shell/journal/` übernommen.

Nicht abgedeckt sind produktive Schreibvorgänge, Backendvalidierungsfehler,
Screenreader sowie ein vollständiger Tastatur-/Escape-/Tab-Zyklus. Entfernen
und Speichern nutzen dieselben geprüften DOM- und Arraypfade, wurden im
Chromium-Lauf aber nicht als eigener POST-Fall wiederholt.
