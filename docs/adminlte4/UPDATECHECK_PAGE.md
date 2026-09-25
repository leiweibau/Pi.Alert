# AdminLTE-4-Seite: Updateprüfung

Stand: 23. September 2026

## Umfang

`front/v4_updatecheck.php` ist der gleichstufige, authentisierte v4-Einstieg
für die Updateprüfung. Die Altseite `front/updatecheck.php` und der bestehende
Serverendpunkt `front/php/server/updatecheck_v2.php` bleiben unverändert.
Die Seite verwendet ausschließlich den lokalen v4-Asset-Stack.

## Erhaltene Verträge

- Eine fehlende Anmeldung leitet wie die übrigen v4-Seiten auf
  `v4_index.php` um. Der Einstieg akzeptiert nur `GET`.
- Der vorhandene Button behält die ID `rewwejwejpjo` und die globale Aktion
  `check_github_for_updates()`.
- Die Prüfung sendet weiterhin einen leeren `POST` an
  `./php/server/updatecheck_v2.php`. Zusätzlich wird der vorhandene CSRF-Token
  als `X-CSRF-Token` mitgegeben; URL, Daten- und Rückgabeformat ändern sich
  nicht. Ein paralleler zweiter Aufruf wird unterdrückt.
- `#updatecheck`, `#updatecheck_result`, `#auto_update_releasenotes` und
  `#bashupdatecommand` bleiben als DOM-Verträge bestehen. Während des Requests
  trägt `#updatecheck` weiterhin `ajax_scripts_loading`.
- `auto_Update.info` wird weiterhin nur lesend angezeigt. Seine Textzeilen
  werden vor der gezielten Hervorhebung escaped.

## Fragment-Kompatibilität

Der Endpunkt liefert weiterhin AdminLTE-2-Klassen wie `.box`, `.box-body`,
`.box-footer`, `.btn-default` und Farbhilfen. `front/css/updatecheck.css`
stellt diese Klassen im Seitenkontext passend zum Bootstrap-5-/AdminLTE-4-
Theme dar. `front/js/updatecheck.js` ergänzt nach dem Einfügen additive
Bootstrap-5-Klassen und sichert externe Links mit `noopener noreferrer` ab.
Der Serverendpunkt musste deshalb nicht geändert und die freigegebene
Server-HTML-Ausnahme nicht genutzt werden.

Die Seite registriert `css/updatecheck.css` und `js/updatecheck.js` über die
Asset-Parameter der gemeinsamen Shell. Das Stylesheet steht dadurch im
Dokumentkopf. Die Scriptreihenfolge lautet jQuery, Bootstrap, AdminLTE,
Common, Shell-Runtime, Updatecheck und abschließend die allgemeine v4-Logik.

## Sicherheits- und Testgrenze

Die Browserprüfung lädt die Seite ausschließlich mit einer synthetischen
Session und betätigt den Update-Button nicht. Damit werden weder die externen
GitHub-Abfragen des Endpunkts noch Update- oder Systemaktionen ausgelöst.
Lade-, Erfolgs- und Fehlerpfad des Seitenscripts werden isoliert mit einem
lokalen synthetischen Fragment geprüft. Die Produktionsdatenbanken und
Konfigurationen werden nicht verändert.

## Prüfergebnis

Die auf den 23. September 2026 geankerte Fixture wurde vollständig neu gebaut.
PHP- und JavaScript-Syntaxprüfungen waren erfolgreich. Chromium lud die Seite
bei 1440 × 1000 und 390 × 844 ohne JavaScript-Ausnahme, unerwarteten HTTP-
Fehler oder horizontales Überlaufen. Vor einer Benutzeraktion ging kein Request
an `updatecheck_v2.php`. Ein per DevTools lokal abgefangener POST belegte den
Lade- und Erfolgszustand sowie die additive Umsetzung von Box, Body, Footer und
externem Link. Eine lokal simulierte HTTP-503-Antwort zeigte den Fehlerzustand
und stellte Button sowie Ladeanzeige anschließend wieder her. Der erwartete
503-Konsolenhinweis war der einzige Eintrag dieses absichtlichen Fehlerlaufs.
