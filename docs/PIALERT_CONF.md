## pialert.conf

In this configuration file many functions of Pi.Alert can be set according to the personal wishes. Since the possibilities are various, 
I would like to give a short explanation to the individual points.

#### General Settings

| Option               | Description |
|--------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| PIALERT_PATH             | This variable is set during installation and should not be changed.                                                                                                                                                                                |
| DB_PATH                  | This variable is set during installation and should not be changed.                                                                                                                                                                                |
| LOG_PATH                 | This variable is set during installation and should not be changed.                                                                                                                                                                                |
| PRINT_LOG                | If this entry is set to True, additional timestamps for the individual sub-functions are added to the scan log. By default this entry is set to False                                                                                              |
| VENDORS_DB               | This variable is set during installation and should not be changed.                                                                                                                                                                                |
| PIALERT_APIKEY           | With the API key it is possible to make queries to the database without using the web page. The API key is a random string that can be set via the settings or via pialert-cli                                                                     |
| PIALERT_WEB_PROTECTION   | Enables or disables the password protection of the Pi.Alert web interface.                                                                                                                                                                         |
| PIALERT_WEB_PASSWORD     | This field contains the hashed password for the web interface. The password cannot be entered here in plain text, but must be set with pialert-cli                                                                                                 |
| NETWORK_DNS_SERVER       | IP address of the DNS server in the network. This entry is required to attempt to resolve a hostname in the network.                                                                                                                               |
| AUTO_UPDATE_CHECK        | Enables or disables automatic search for Pi.Alert updates.                                                                                                                                                                                         |
| AUTO_DB_BACKUP           | Enables or disables automatic creation of database and configuration backups.                                                                                                                                                                      |
| AUTO_DB_BACKUP_KEEP      | This specifies how many automatic backups should be retained, including the current backup. This includes both configuration backups and database backup. This value is not relevant during manual cleanup, where the last 3 backups are retained. |
| REPORT_NEW_CONTINUOUS    | Enables or disables the recurring notification for devices marked as "New".                                                                                                                                                                        |
| NEW_DEVICE_PRESET_EVENTS | Enables or disables the notification for all events on new devices.                                                                                                                                                                                |
| NEW_DEVICE_PRESET_DOWN   | Enables or disables the notification for "Down" events on new devices.                                                                                                                                                                             |
| SYSTEM_TIMEZONE          | If a time zone has already been set in php.ini, this will of course be used. If no time zone is set, i.e. PHP uses "UTC", then the time zone stored in pialert.conf is applied. [PHP Timezones](https://www.php.net/manual/en/timezones.php)       |
| OFFLINE_MODE             | After the installation and the initial test of all script components, the offline mode can be configured to prevent any communication with the Internet                                                                                            |


#### Other Modules

| Option            | Description |
|-------------------|----------------------------------------------------------------------------------------|
| SCAN_WEBSERVICES  | Here the function for monitoring web services can be switched on (True) or off (False) |
| ICMPSCAN_ACTIVE   | ICMP Monitoring on/off                                                                 |
| SATELLITES_ACTIVE | Enable the Satellite management and import function. One or more companion scripts can perform remote scans and send them to the central Pi.Alert instance. |

#### Special Protocol Scanning

| Option               | Description |
|---------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| SCAN_ROGUE_DHCP     | Activates the search for foreign, also called "rogue", DHCP servers. This function is used to detect whether there is a foreign DHCP server in the network that could take control of IP management. |
| DHCP_SERVER_ADDRESS | The IP of the known DHCP server is stored here. Only ONE DHCP server can be entered.                                                                                                                 |


#### Custom Cronjobs

| Option               | Description |
|----------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| AUTO_UPDATE_CHECK_CRON     | Interval, in crontab syntax, at which to search for new updates from Pi.Alert. The shortest interval is 3 minutes. All larger intervals must be an integer multiples of 3 minutes (15, 30, 36, etc). |
| AUTO_DB_BACKUP_CRON        | Interval, in crontab syntax, at which the automatic backups should be created. The shortest interval is 3 minutes. All larger intervals must be aninteger multiples of 3 minutes (15, 30, 36, etc).  |
| REPORT_NEW_CONTINUOUS_CRON | Interval, in Crontab syntax, at which notifications should be sent repeatedly. The shortest interval is 3 minutes. All larger intervals must be an integer multiple of 3 minutes (15, 30, 36, etc.). |
| SPEEDTEST_TASK_CRON        | Full hour, or comma-separated hours, at which the speed test is to be started. The shortest interval is 3 minutes. All larger intervals must be integer multiples of 3 minutes (15, 30, 36, etc).    |


#### Mail-Account Settings

| Option               | Description |
|-----------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| SMTP_SERVER     | Address of the e-mail server (e.g. smtp.gmail.com)                                                                                                |
| SMTP_PORT       | The port of the SMTP server. The port may vary depending on the server configuration.                                                             |
| SMTP_USER       | User name                                                                                                                                         |
| SMTP_PASS       | Password                                                                                                                                          |
| SMTP_SKIP_TLS   | If this entry is set to True, transport encryption of the e-mail is enabled. If the server does not support this, the entry must be set to False. |
| SMTP_SKIP_LOGIN | There are SMTP servers which do not require a login. In such a case, this value can be set to True.                                               |


#### WebGUI Reporting

| Option               | Description |
|----------------------|------------------------------------------------------------------------------------------------------|
| REPORT_WEBGUI        | Enables/disables the notifications about changes in the network in the web interface.                |
| REPORT_WEBGUI_WEBMON | Enables/disables the notifications about changes in the monitored web services in the web interface. |
| REPORT_TO_ARCHIVE    | Number of hours after which a report is moved to the archive. The value 0 disables the feature       |


#### Mail Reporting

| Option               | Description |
|----------------------|---------------------------------------------------------------------------------------------------|
| REPORT_MAIL          | Enables/disables the notifications about changes in the network via e-mail.                       |
| REPORT_MAIL_WEBMON   | Enables/disables the notification of changes in the monitored web services by e-mail.             |
| REPORT_FROM          | Name or identifier of the sender.                                                                 |
| REPORT_TO            | E-mail address to which the notification should be sent.                                          |
| REPORT_DEVICE_URL    | URL of the Pi.Alert installation to create a clickable link in the e-mail to the detected device. |
| REPORT_DASHBOARD_URL | URL of the Pi.Alert installation, to be able to create a clickable link in the e-mail.            |


#### Pushsafer

| Option               | Description |
|-------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| REPORT_PUSHSAFER        | Enables/disables notifications about changes in the network via Pushsafer.                                                                                                       |
| REPORT_PUSHSAFER_WEBMON | Enables/disables notifications about changes in the monitored web services via Pushsafer.                                                                                        |
| PUSHSAFER_TOKEN         | This is the private key that can be viewed on the pushsafer page.                                                                                                                |
| PUSHSAFER_DEVICE        | The device ID to which the message will be sent. &lsquo;a&rsquo; means the message will be sent to all configuring devices and will consume a corresponding number of API calls. |
| PUSHSAFER_PRIO          | Priority level of the message.                                                                                                                                                   |
| PUSHSAFER_SOUND         | Notification sound (integer).                                                                                                                                                    |


#### Pushover

| Option               | Description |
|------------------------|----------------------------------------------------------------------------------------------|
| REPORT_PUSHOVER        | Enables/disables notifications about changes in the network via Pushover.                    |
| REPORT_PUSHOVER_WEBMON | Enables/disables the notifications about changes in the monitored web services via Pushover. |
| PUSHOVER_TOKEN         | Also called "APP TOKEN" or "API TOKEN". This token can be queried on the pushover page.      |
| PUSHOVER_USER          | Also called "USER KEY". This key is displayed right after login on the start page.           |
| PUSHOVER_PRIO          | Priority level of the message.                                                               |
| PUSHOVER_SOUND         | Notification sound.                                                                          |


#### NTFY

| Option               | Description |
|--------------------|---------------------------------------------------------------------------------|
| REPORT_NTFY        | Enables/Disables notifications about changes in the network via NTFY            |
| REPORT_NTFY_WEBMON | Enables/Disables notifications about changes in monitored web services via NTFY |
| NTFY_HOST          | The hostname or IP address of the NTFY server.                                  |
| NTFY_TOPIC         | The subject of notifications sent via NTFY.                                     |
| NTFY_USER          | The username used for authentication with the NTFY server.                      |
| NTFY_PASSWORD      | The password used for authentication with the NTFY server.                      |
| NTFY_PRIORITY      | Priority of notifications sent via NTFY                                         |
| NTFY_CLICKABLE     | Enables or disables the click action for the notification.                      |

:exclamation: If you want to use a token instead of username and password, leave the username blank and use the token as the password.


#### Shoutrrr

| Option               | Description |
|-----------------|-----------------------------------------------------------------------------------------------------------------------------|
| SHOUTRRR_BINARY | Here you have to configure which binary of shoutrrr has to be used. This depends on the hardware Pi.Alert was installed on. |


#### Telegram via Shoutrrr

| Option               | Description |
|------------------------|---------------------------------------------------------------------------------------------|
| REPORT_TELEGRAM        | Enables/disables the notifications about changes in the network via Telegram                |
| REPORT_TELEGRAM_WEBMON | Enables/disables the notifications about changes in the monitored web services via Telegram |
| TELEGRAM_BOT_TOKEN_URL | Here the URL created by the shoutrrr setup wizard is entered.                               |


#### DynDNS and IP

| Option               | Description |
|-------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| QUERY_MYIP_SERVER | Server URL that determines and returns the current public IP.                                                                                                                                                 |
| DDNS_ACTIVE       | Enables/Disables the configured DDNS service in Pi.Alert. DDNS, also known as DynDNS, allows a domain name to be updated with a regularly changing IP address. This service is provided by various providers. |
| DDNS_DOMAIN       |                                                                                                                                                                                                               |
| DDNS_USER         | Username                                                                                                                                                                                                      |
| DDNS_PASSWORD     | Password                                                                                                                                                                                                      |
| DDNS_UPDATE_URL   | URL to update the current IP with the DDNS service                                                                                                                                                            |


#### Automatic Speedtest

| Option               | Description |
|-----------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| SPEEDTEST_TASK_ACTIVE | Activate/deactivate the automatic speed test. This requires the installation of the Ookla speed test in the "Tools" tab of the "Internet" device. Follow the instructions during installation. |


#### Arp-scan Options & Samples

| Option               | Description |
|-----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| MAC_IGNORE_LIST | [&apos;MAC-Address 1&apos;, &apos;MAC-Address 2&apos;]<br>									            This MAC address(es) (save with small letters) will be filtered out from the scan results. It is also possible to specify only the beginning of a MAC address. All addresses with the same prefix will also be filtered out                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| IP_IGNORE_LIST  | [&apos;IP-Address 1&apos;, &apos;IP-Address 2&apos;]<br>									            This IP address(es) will be filtered out from the scan results. It is also possible to specify only the beginning of a IP address. All addresses with the same prefix will also be filtered out                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| SCAN_SUBNETS    | &lsquo;--localnet&rsquo;<br>									        	Normally this option is already the correct settings. This setting is selected when Pi.Alert is installed on a device with a network card and no other networks are configured.<br>									        	&lsquo;--localnet --interface=eth0&rsquo;<br>									        	This configuration is selected if Pi.Alert is installed on a system with at least 2 network cards and a configured network. However, the interface designation may differ and must be adapted to the conditions of the system.<br>									        	[&apos;192.168.1.0/24 --interface=eth0&apos;,&apos;192.168.2.0/24 --interface=eth1&apos;]<br>									        	The last configuration is necessary if several networks are to be monitored. For each network to be monitored, a corresponding network card must be configured. This is necessary because the "arp-scan" used is not routed, i.e. it only works within its own subnet. Each interface is entered here with the corresponding network. The interface designation must be adapted to the conditions of the system.<br>									         |


#### ICMP Monitoring Options

| Option               | Description |
|------------------|-----------------------------------------------------------------------------|
| ICMP_ONLINE_TEST | Number of attempts to determine if a device is online (Default 1).          |
| ICMP_GET_AVG_RTT | Number of "ping&apos;s" to calculate the average response time (Default 2). |


#### Pi-hole Configuration

| Option                   | Description |
|--------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| PIHOLE_ACTIVE            | This variable is set during installation.                                                                                                                                   |
| PIHOLE_VERSION           | The version information is necessary because access to the Pi-hole data has fundamentally changed from version 5 to 6. Default is 6 after the official realease of Pihole 6 |
| PIHOLE_DB                | This variable is set during installation and should not be changed.                                                                                                         |
| PIHOLE6_URL              | If you want to access the Pi-hole data of version 6, enter the URL to the web interface (without the "/admin" suffix) here.                                                 |
| PIHOLE6_PASSWORD         | Enter the password for the Pi-hole web interface here.                                                                                                                      |
| PIHOLE6_API_MAXCLIENTS   | Specifies the maximum number of clients that are returned as a response from the API                                                                                        |
| DHCP_ACTIVE              | This variable is valid for the DHCP server of Pihole 5.x as well as for 6.x                                                                                                 |
| DHCP_LEASES              | This path is only relevant for version 5.x of Pihole and should not be changed.                                                                                             |
| DHCP_INCL_SELF_TO_LEASES | Adds the Mac addresses of Pi-hole itself to the DHCP leases to import Pi-hole itself into the database if it is not in the local network of Pi.Alert                        |


#### Fritzbox Configuration

| Option          | Description |
|-----------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------|
| FRITZBOX_ACTIVE | If a Fritzbox is used in the network, it can be used as a data source. This can be activated or deactivated at this point.                                  |
| FRITZBOX_IP     | IP address of the Fritzbox.                                                                                                                                 |
| FRITZBOX_USER   | This assumes that the Fritzbox is configured for a login with username and password, instead of password only. A login with password only is not supported. |
| FRITZBOX_PASS   | Password                                                                                                                                                    |


#### Mikrotik Configuration

| Option          | Description |
|-----------------|------------------------------------------------------------------------------------------------------------------------------|
| MIKROTIK_ACTIVE | If a Mikrotik router is used in the network, it can be used as a data source. This can be enabled or disabled at this point. |
| MIKROTIK_IP     | IP address of the Mikrotik router.                                                                                           |
| MIKROTIK_USER   | Username                                                                                                                     |
| MIKROTIK_PASS   | Password                                                                                                                     |


#### UniFi Configuration

| Option       | Description |
|--------------|---------------------------------------------------------------------------------------------------------------------------|
| UNIFI_ACTIVE | If a UniFi system is used in the network, it can be used as a data source. This can be enabled or disabled at this point. |
| UNIFI_IP     | IP address of the Unifi system.                                                                                           |
| UNIFI_API    | Possible UNIFI APIs are v4, v5, unifiOS, UDMP-unifiOS, default                                                            |
| UNIFI_USER   | Username                                                                                                                  |
| UNIFI_PASS   | Password                                                                                                                  |


#### OpenWRT Configuration

| Option         | Description |
|----------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| OPENWRT_ACTIVE | The package `luci-mod-rpc`need to be installed, on your OpenWrt router. If a OpenWRT is used in the network, it can be used as a data source. This can be activated or deactivated at this point. |
| OPENWRT_IP     | IP address of the OpenWRT router.                                                                                                                                                                 |
| OPENWRT_USER   | Username                                                                                                                                                                                          |
| OPENWRT_PASS   | Password                                                                                                                                                                                          |

#### Satellite Configuration

| Option               | Description |
|----------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| SATELLITE_PROXY_MODE | Activates/deactivates the support of an external API to which the satellites send their data. If this function is deactivated, Pi.Alert only uses scan events that were sent directly to this instance. |
| SATELLITE_PROXY_URL  | The URL of the Pi.Alert Satellite Poxy API                                                  |


#### Maintenance Tasks

| Option               | Description |
|----------------------------|-----------------------------------------------------------------------------------------------------------------------------------|
| DAYS_TO_KEEP_ONLINEHISTORY | Number of days for which the online history (activity graph) is to be stored in the database. One day generates 288 such records. |
| DAYS_TO_KEEP_EVENTS        | Number of days for which the events of the individual devices are to be stored.                                                   |



[Back](https://github.com/leiweibau/Pi.Alert#back)



















