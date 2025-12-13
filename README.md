# Table of Contents

* [Introduction](#pialert)
* [Scan Methodes](#scan-methods)
* [Backend](#backend-back)
* [Frontend](#frontend-front)
* [Installation](#installation)
* [Update](#update)
* Additional information
  * [Guide for the first start](docs/FIRST_START_GUIDE.md)
  * [FAQ](docs/HELP_FAQ.md), [Troubleshooting](docs/TROUBLESHOOTING.md)
  * [Screenshots](docs/SCREENSHOTS.md), [Favicons/Homescreen icons](docs/ICONS.md)
  * [Device Management](docs/DEVICE_MANAGEMENT.md)
  * [Bulk Editor](docs/BULKEDITOR.md)
  * [pialert-cli](docs/PIALERTCLI.md), [pialert.conf](docs/PIALERT_CONF.md)
  * [Network Relationship](docs/NETWORK_RELATIONSHIP.md)
  * [Web service monitoring](docs/WEBSERVICES.md)
  * [Satellite Config](docs/SATELLITES.md)
  * [Uninstall Pi.Alert](docs/UNINSTALL.md)
* [Closing words](#closing-words)


# Pi.Alert
<!--- --------------------------------------------------------------------- --->

WIFI / LAN intruder detector with web service monitoring. The main functions are as follows:

- Scan your WIFI/LAN-connected devices and receive alerts for unknown device connections. 
- Get warnings if an "always connected" device disconnects. 
- Assess web service availability by evaluating the HTTP status code, SSL certificate, and service response time. 
- Receive notifications if the SSL certificate changes, the HTTP status code changes, or if the service becomes unreachable. 
- Detect unwanted/foreign DHCP servers 
- Device monitoring using the ping command

There is also a companion script, [Pi.Alert-Satellite](https://github.com/leiweibau/Pi.Alert-Satellite), 
which performs its own scans and the results can be sent to an existing Pi.Alert instance.

![Main screen][main]
[Compare this fork with the main project](docs/VERSIONCOMPARE.md)


### Scan Methods and Imports

<ins>**arp-scan**</ins> (system utility to search for devices using arp frames), 
<ins>**Pi-hole DNS**</ins> (v5 or v6), <ins>**Pi-hole DHCP**</ins>. (v5 or v6),
<ins>**Fritzbox**</ins> (active Hosts), <ins>**Mikrotik**</ins> (DHCP leases), <ins>**UniFi**</ins> (Client Devices), <ins>**OpenWRT**</ins> (active hosts), 
<ins>**AsusWRT**</ins> (active hosts), <ins>**pfSense**</ins> (active hosts, DHCP leases, ARP Table), 
<ins>**Satellite Scan**</ins> (arp-scan, Pi-hole DNS, Pi-hole DHCP, Mikrotik, UniFi, OpenWRT, AsusWRT)

### Backend (back)

The backend is controlled via the operating system's own cron service and is executed at 5-minute intervals. The task of the backend is to execute the 
various scans and imports, save the results in the database and send notifications according to the settings. In addition to host detection, it is also 
possible to check the availability of manually entered hosts or websites for their reachability and to receive notifications in the event of status changes. 
Various services are available for the notifications (Frontend, Mail ([Guide](docs/NOTIFICATION_MAIL.md)), [Pushsafer](https://www.pushsafer.com/), 
[Pushover](https://pushover.net/), ntfy and Telegram through shoutrrrr ([Guide](docs/NOTIFICATION_SHOUTRRR.md))).

### Frontend (front)

The frontend is used to manage the host information determined and for general management. You can store additional information for each device, view the historical 
history, perform manual nmap scans or send Wake-on-LAN commands. You also have the option of assigning individual devices to other network devices such as routers and 
switches in order to maintain an overview of the relationships between the devices. A settings page allows you to configure individual parts of the frontend, while a 
config file editor allows you to configure the backend. This interface, which is available in English, German, Spanish, French, Italian, Polish, Danish, Dutch, Czech, 
Finnish, Swedish, Norwegian, Lithuanian, Ukrainian and Russian can be protected with a 
login that uses the password “123456” by default. You can change this using the CLI tool [pialert-cli](docs/PIALERTCLI.md).

New [Favicons/Homescreen icons](docs/ICONS.md) have been created based on the original design, tailored to different skins. To ensure compatibility with 
iOS devices, icons can be directly linked from the repository, as iOS devices may not load homescreen icons from insecure sources (without SSL or self-signed SSL).

It is possible to send various requests to the backend with the help of an [API](docs/API-USAGE.md). The API can also be used to create an integration in Home Assistant 
or [Homepage](https://github.com/gethomepage/homepage).


# Installation
<!--- --------------------------------------------------------------------- --->
Initially designed to run on a Raspberry Pi, probably it can run on some other
Linux distributions which use the "apt" package manager. Check "[Things to keep in mind when using different Linux distributions](docs/LINUX-DISTRIBUTIONS.md)" before using Pi.Alert with another Debian based distribution like DietPi or Ubuntu Server to see, if there are any special notes to follow.

<table>
  <thead>
    <tr><th align="left">Installation</th></tr>
  </thead>
  <tbody>
  <tr><td>

```
bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_install.sh)"
```
  </td></tr>
  </tbody>
</table>

- [Guide for the first start](docs/FIRST_START_GUIDE.md)
- If you want to use **Pi.Alert as LXC container**, feel free to check out the [Proxmox VE Helper-Scripts](https://github.com/community-scripts/ProxmoxVE) (originally [tteck/Proxmox (archived)](https://github.com/tteck/Proxmox)). I also support this version, as this Pi.Alert version is used with the exception of initial container creation. Updates to the LXC version are also installed from this repository. A separate update command is used for this purpose.

:bulb: <ins>Additional components and information</ins>

 - [Things to keep in mind when using different Linux distributions](docs/LINUX-DISTRIBUTIONS.md) (will be updated if necessary)
 - An initial fork but now independent version of Pi.Alert named NetAlertX based on Docker can be found here: [jokob-sk/NetAlertX](https://github.com/jokob-sk/NetAlertX)
 - The original, but unmaintained, Pi.Alert can be found here [pucherot/Pi.Alert](https://github.com/pucherot/Pi.Alert/)

# Update
<!--- --------------------------------------------------------------------- --->
You can always check for a new release using the "Update Check" button in the sidebar. This check will show you if the GeoLite2 DB is 
installed or up to date and which new features, fixes or changes are available in the new Pi.Alert release, if you are not already using the latest version.

With version v2025-12-15, there has been a change to the update script. With the change to the installation directory from this version onwards, it has become necessary to provide two update scripts.

If your Pi.Alert installation is located in a user directory ($HOME/pialert), use the updater in the "Updater HOME" section.

If the Pi.ALert installation is located in the "/opt/pialert" directory, use the updater in the "Updater OPT" section.

<table>
  <thead>
    <tr><th align="left">Updater OPT</th></tr>
  </thead>
  <tbody>
  <tr><td>
    - Installation is located in the "/opt/pialert"
    - Installed Pi.Alert with or after version v2025-12-15
    - Created a Pi.Alert container using the Proxmox Helper Scripts<br><br>

```
bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)"
```
  </td></tr>
  </tbody>
</table>


<table>
  <thead>
    <tr><th align="left">Updater HOME (Outdated)</th></tr>
  </thead>
  <tbody>
  <tr><td>
    - Installation is located in a user directory ($HOME/pialert)
    - Pi.Alert was manual installed before version v2025-12-15<br><br>

```
bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update_old.sh)"
```
  </td></tr>
  </tbody>
</table>

‼️ “Outdated” refers to the updater itself. Both the old and new updater use the same installation package.

‼️ Help with migrating to the new installation path [Here](docs/MIGRATION_HOME_TO_OPT.md)

An archive of older versions can be found at [https://leiweibau.net/archive/pialert](https://leiweibau.net/archive/pialert/). This archive contains all release notes of my fork.

# Closing words
<!--- --------------------------------------------------------------------- --->

### Support

  If you would like to support me and my work, I offer the following options.

  | [<img src="https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/githubsponsor.png" height="30px">](https://github.com/sponsors/leiweibau) | [<img src="https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png" height="30px">](https://www.buymeacoffee.com/leiweibau) |
  | ---- | ---- |

  <ins>**:pray: A personal thank you to every sponsor of this project.**</ins>

  <ins>**:pray: A big thank you also goes to everyone who contributed directly or indirectly.**</ins>

### Additionally used components and services
[Animated GIF (Loading Animation)](https://commons.wikimedia.org/wiki/File:Loading_Animation.gif), 
[Selfhosted Fonts](https://github.com/adobe-fonts/source-sans), 
[Bootstrap Icons](https://github.com/twbs/icons), 
[Material Design Icons](https://github.com/Pictogrammers), 
[For final processing of background images](https://www.imgonline.com.ua/eng/make-seamless-texture.php), 
[DeepL](https://www.deepl.com), 
[ChatGPT](https://chat.openai.com)


### License
  GPL 3.0
  [Read more here](LICENSE.txt)

### Contact

  leiweibau@gmail.com

<!--- --------------------------------------------------------------------- --->
[main]:    https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/screen_main_da_li.png          "Main screen"

