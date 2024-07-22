## Configure Mail Notification

Während des Setups, hat du die Gelegenheit, die Mail Konfiguration durchzuführen. Pi.Alert verwendet diese
Konfiguration standardmäßig jedoch ersteinmal nicht, da nur die Benachrichtigung über die Weboberfläche nach 
der Installation aktiviert ist.

Screenshot Status Box

Um dies zu ändern, kannst du unter Settings, ganz unten auf der Seite, den Config File Editor öffnen und unter
"Mail Reporting" die Einträge "REPORT_MAIL", und wenn du das Web Service Monitoring verwenden möchtest, 
"REPORT_MAIL_WEBMON" von "False" auf "True" setzen. Nach dem Speichern sollte die Status box nun folgendermaßen aussehen.

Screenshot Status Box

Um zu Prüfen, ob die Konfiguration erfolgreich ist, kannst du den Button "Test Notifications" betätigen. Über alle aktivierten 
Benachrichtigungsdienste wird nun eine Nachricht gesendet. Sollte keine Benachrigtigung per Mail eintreffen, kann einer zusätzliche 
Konfiguration im betreffenden Mail-Konto notwendig sein. Dienste (Gmail, iCloud, Outlook) die mit 2FA-Authentifizierung arbeiten, 
verwenden meist spezifische "App Passworter", um Diensten den Zugriff auf das Postfach zu ermöglichen. Hier ist es nun notwendig, 
ein entsprechendes App-Passwort für Pi.Alert zu konfigurieren.

[Gmail Support Document](https://support.google.com/accounts/answer/185833?p=InvalidSecondFactor)

[iCloud Support Document](https://support.apple.com/en-us/102654)

[Outlook Support Document](https://support.microsoft.com/en-us/account-billing/how-to-get-and-use-app-passwords-5896ed9b-4263-e681-128a-a6f2979a7944)

[Back](https://github.com/leiweibau/Pi.Alert)
