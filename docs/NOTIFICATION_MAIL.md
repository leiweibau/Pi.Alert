## Configure Mail Notification

During the setup, you have the opportunity to configure the mail. Pi.Alert does not use this
configuration by default, as only the notification via the web interface is activated after installation. 
the installation is activated.

Screenshot Status Box
![Mail deactivated][status_box_no_mail]

To change this, you can open the Config File Editor under Settings, at the bottom of the page, and under
“Mail Reporting” the entries `REPORT_MAIL`, and if you want to use the Web Service Monitoring, 
set `REPORT_MAIL_WEBMON` from `False` to `True`. After saving, the status box should now look like this.

| ![Config File Editor][config_file_editor] | ![Mail activated][status_box_mail] |
| ----------------------------------------- | ---------------------------------- |

To check whether the configuration is successful, you can click on the “Test Notifications” button. A message will 
now be sent via all activated notification services. If no notification arrives by email, additional configuration 
may be necessary in the relevant mail account. Services (Gmail, iCloud, Outlook) that work with 2FA authentication 
usually use specific “app passwords” to allow services to access the mailbox. Here it is now necessary 
configure a corresponding app password for Pi.Alert.

[Gmail Support Document](https://support.google.com/accounts/answer/185833?p=InvalidSecondFactor)

[iCloud Support Document](https://support.apple.com/en-us/102654)

[Outlook Support Document](https://support.microsoft.com/en-us/account-billing/how-to-get-and-use-app-passwords-5896ed9b-4263-e681-128a-a6f2979a7944)

[Back](https://github.com/leiweibau/Pi.Alert)

<!--- --------------------------------------------------------------------- --->
[status_box_no_mail]:  https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/notification_mail_01.png          "Mail deactivated"
[config_file_editor]:  https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/notification_mail_02.png          "Config File Editor"
[status_box_mail]:     https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/notification_mail_03.png          "Mail activated"