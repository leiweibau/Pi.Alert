## Comparison

To make it clearer how this version differs from the original project, I made this table.

|  |   | **Pi.Alert (leiweibau)** | **Pi.Alert (original)** |
|---|---|:---:|:---:|
| **Security** | Login | 🟢 | 🔴 |
|  | API-Key for API-Calls | 🟢 | 🔴 |
|  | Journal (Frontend/Backend) | 🟢 | 🔴 |
| **Scans / Imports** | Scan-Methodes | arp-scan<br>nmap<br>ping | arp-scan |
|  | Import-Methodes | Pi-hole v5 & v6<br>dnsmasq<br>Fritz!Box (Router)<br>Microtik, UniFi<br>Asus Router, OpenWRT<br>pfSense | Pi-hole v5<br>dnsmasq |
|  | Accuracy (arp-scan) | 1 cycle every 5 min with 6 retries | 1 cycle every 5 min with 6 retries<br>1 cycle every 15 min with viel mehr retries |
|  | Hostname detection | DNS<br>mDNS<br>NetBIOS | DNS |
|  | Web Services / Monitoring | 🟢 | 🔴 |
|  | ICMP Monitoring (ping) | 🟢 | 🔴 |
|  | Rogue DHCP Detection | 🟢 | 🔴 |
|  | Ignorelist (MAC, Name, IP)| 🟢 | 🔴 |
|  | Satellites (Remote Scan) | 🟢 | 🔴 |
| **User Interface** | Skins / Themes / Darkmode | 🟢 / 🟢 / 🟢 | 🟢 / 🔴 / 🔴 |
|  | FavIcon / Homescreen Icon| 🟢 | 🔴 |
|  | Column configuration (Devicelist)| 🟢 | 🔴 |
|  | Bulk Editor | 🟢 | 🔴 |
|  | Network activity graph | 🟢 | 🔴 |
|  | Config-File editor | 🟢 | 🔴 |
|  | Multilanguage | 🟢 | 🔴 |
|  | Network relationship page | 🟢 | 🔴 |
|  | Custom predefined filters in the sidebar | 🟢 | 🔴 |
|  | Exclude devices from presence page | 🟢 | 🔴 |
|  | System/Pi.Alert status page | 🟢 | 🔴 |
|  | Dashboard | 🟢 | 🔴 |
| **Notifications** | Email | 🟢 | 🔴 |
|  | Push Services | Pushover, Pushsaver<br>ntfy, Telegram, Discord | 🔴 |
|  | Notifications on WebGui | 🟢 | 🔴 |
|  | Notification test | 🟢 | 🔴 |
|  | Download notifications as PDF | 🟢 | 🔴 |
|  | optional continuously notifications | 🟢 | 🔴 |
|  | MQTT Messages | 🟢 | 🔴 |
| **Maintenance** | Backup / Restore Database | 🟢 | 🔴 |
|  | Backup / Restore Configfile | 🟢 | 🔴 |
|  | Export / Download devices as CSV file | 🟢 | 🔴 |
|  | DB Cleanup | 🟢 | 🔴 |
|  | Update Check (manual / automatically)| 🟢 / 🟢 | 🔴 / 🔴 |
|  | Log-Viewer | 🟢 | 🔴 |
| **Misc** | CLI Tool | 🟢 | 🔴 |
|  | API | 🟢 | 🔴 |
|  | Wake-on-LAN | 🟢 | 🔴 |
|  | Manual nmap Scan per device | 🟢 | 🔴 |
|  | Speedtest (manual / automatically)| 🟢 / 🟢 | 🔴 / 🔴 |
|  | Hotkeys | 🟢 | 🔴 |


[Back](https://github.com/leiweibau/Pi.Alert)