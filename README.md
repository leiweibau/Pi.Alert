# Table of Contents

* [Introduction](#pialert)
* [Scan Methodes](#scan-methods)
* [Components](#components)
  * [Back](#back)
  * [Front](#front)
  * [API](#api)
* [Installation](#installation)
* [Update](#update)
* Additional information
  * [Uninstall Pi.Alert](docs/UNINSTALL.md)
  * [Device Management](docs/DEVICE_MANAGEMENT.md)
  * [Bulk Editor](docs/BULKEDITOR.md)
  * [Network Relationship](docs/NETWORK_RELATIONSHIP.md)
  * [Web service monitoring](docs/WEBSERVICES.md)
  * [Screenshots](docs/SCREENSHOTS.md)
* [Closing words](#closing-words)


# Pi.Alert
<!--- --------------------------------------------------------------------- --->

WIFI / LAN intruder detector with web service monitoring.

Scan the devices connected to your WIFI / LAN and alert you the connection of
unknown devices. It also warns if a "always connected" device disconnects. In addition, it is 
possible to check web services for availability. For this purpose HTTP status codes and the 
response time of the service are evaluated. If a service fails or the HTTP status code changes, 
a notification can be sent.

![Main screen][main]
[Compare this fork with the main project](docs/VERSIONCOMPARE.md)

## Scan Methods

  - **arp-scan**. The arp-scan system utility is used to search
        for devices on the network using arp frames.
  - **Pi-hole**. This method is optional. If the Pi-hole DNS server is active, Pi.Alert examines its
        activity looking for active devices using DNS that have not been
        detected by method 1.
  - **dnsmasq**. This method is optional. If the DHCP server dnsmasq is active, Pi.Alert
        examines the DHCP leases (addresses assigned) to find active devices
        that were not discovered by the other methods.
  - **Fritzbox**. This method is optional. If you use a Fritzbox (a router from the company "AVM"), 
        it is possible to perform a query of the active hosts. This also 
        includes hosts of the guest WLAN and Powerline devices from "AVM".
  - **Mikrotik**. This method is optional. If you use Mikrotik RouterBoard as DHCP server,
        it is possible to read DHCP leases.
  - **UniFi**. This method is optional. If you use UniFi controller,
        it is possible to read clients (Client Devices)
  - **Web service monitoring**. This method is optional. An HTTP request is 
        sent and the web server's response is processed. If self signed 
        certificates are used, no validation of the certificate is performed.
  - **ICMP monitoring**. This method is optional. A "ping" is sent to a manually specified
        IP/hostname/domain name and the response is evaluated
  - **DHCP Server Scan**. This method is optional. Nmap is used to send DHCP 
        requests into the network to detect unknown (rogue) DHCP servers.

## Components

### Back

  - Scan the network searching connected devices using the scanning methods described earlier
  - Checks the reachability of web services
  - Store the information in the DB
  - Report the changes detected by e-mail and/or other services ([Pushsafer](https://www.pushsafer.com/), [Pushover](https://pushover.net/), NTFY, Gotify and Telegram via [shoutrrr](https://github.com/containrrr/shoutrrr/)) and to the Frontend
  - DB cleanup tasks via cron
  - a [pialert-cli](docs/PIALERTCLI.md) that helps to configure login, password and some other things
  - Additional information
    - [pialert-cli - Overview of supported commands](docs/PIALERTCLI.md)
    - [shoutrrr - Implementation notes](docs/SHOUTRRR.md)

### Front

There is a configurable login to prevent unauthorized use. The default password is "123456". By default, this is disabled. If you want to use password protection, enable it in the configuration file `$HOME/pialert/config/pialert.conf` or via [pialert-cli](docs/PIALERTCLI.md).
  - Manage the devices inventory and the characteristics (individually or with a [bulk editor](docs/BULKEDITOR.md))
  - Display in a visual way all the information collected by the back *(Sessions, Connected devices, Favorites, Events, Presence, Internet IP address changes, ...)*
  - Manual Nmap scans for regular devices and speedtest for the device "Internet" in the details view
  - Simple [network relationship](docs/NETWORK_RELATIONSHIP.md) display
  - Maintenance tasks and settings like:
    - Status information *(active scans, database size, backup counter)*
    - Light/Dark-Mode switch and theme selection *(blue, red, green, yellow, black, purple)*
    - Language selection *(english, german, spanish, french)*
    - Enable/Disable network activity graph 
    - Pause arp-scan
    - Set API-key
    - Enable/Disable login
    - DB maintenance tools and a possibility for backup, restore and cleanup the database and the config file
    - Send test notifications
    - Config file editor
  - [Web service monitoring](docs/WEBSERVICES.md)
  - Help/FAQ section
  - Wake-on-LAN (must be supported by the target device)
  - Notification page with download options
  - Journal that tracks operations via the frontend

Based on the original, I have created new icons according to the skins. Since I made the experience that iOS devices do not load homescreen icons from insecure sources (no SSL or selfsigned SSL), you can also link the icons directly from this repository.

```https://raw.githubusercontent.com/leiweibau/Pi.Alert/main/front/img/favicons/glass_black_white.png```

Instead of 'glass_black_white.png' you can use one of the following files.

[List of Favicons/Homescreen icons](docs/ICONS.md)

### API

A possibility to send a request to the Pi.Alert backend via different ways. Currently the API offers the possibility to query 4 things:
  - System status *(Scan on or off, Counts all, online, offline, archived and new devices)*
  - All online devices *(MAC, Name, Vendor, LastIP, Infrastructure, Infrastructure_port)*
  - All offline devices *(MAC, Name, Vendor, LastIP, Infrastructure, Infrastructure_port)*
  - Information about a specific device *(all information, without events and presence)*

With the API (from Pi.Alert 3.7.9+) it is possible to integrate Pi.Alert into Home Assistant. How it works, you can look up in the API documentation.

[Pi.Alert API Usage and Examples / Home Assistant integration](docs/API-USAGE.md)

# Installation
<!--- --------------------------------------------------------------------- --->
Initially designed to run on a Raspberry Pi, probably it can run on some other
Linux distributions which use the "apt" package manager. With minor adjustments (FAQ page) I tested Pi.Alert on Dietpi and Ubuntu Server. 
An also active developed Docker version of Pi.Alert can be found here: [jokob-sk/Pi.Alert](https://github.com/jokob-sk/Pi.Alert)

- One-step Automated Install:
```
bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_install.sh)"
``` 


- [Installation Guide (step by step)](docs/INSTALL.md)

# Update
<!--- --------------------------------------------------------------------- --->
You can always check for a new release using the "Update Check" button in the sidebar. This check will show you if the GeoLite2 DB is 
installed or up to date and which new features, fixes or changes are available in the new Pi.Alert release, if you are not already using the latest version.

There are no updates as Github release package, because the update function is done by the "wget" command anyway. Instead, after a certain number of commits, 
a new archive is created, which is used as the source for the update.

This update script is only recommended for an already existing installation of this fork. If you are using another fork, 
I recommend uninstalling it first. If you backup the database, it may be possible to continue using it with my fork after a patch ([pialert-cli](docs/PIALERTCLI.md)).

- One-step Automated Update:
```
bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)"
```

# Closing words
<!--- --------------------------------------------------------------------- --->

### Versions History
  [Versions History](docs/VERSIONS_HISTORY.md)
  
  An archive of older versions can be found at [https://leiweibau.net/archive/pialert](https://leiweibau.net/archive/pialert/). This archive contains all release notes of my fork.

### License
  GPL 3.0
  [Read more here](LICENSE.txt)

### Additionally used components
  - Animated GIF (Loading Animation) https://commons.wikimedia.org/wiki/File:Loading_Animation.gif
  - Selfhosted Fonts https://github.com/adobe-fonts/source-sans
  - Bootstrap Icons https://github.com/twbs/icons

### Special thanks

  This code is a collaborative body of work, with special thanks to:

[Macleykun](https://github.com/Macleykun), [Final-Hawk](https://github.com/Final-Hawk), [TeroRERO](https://github.com/terorero), [jokob-sk](https://github.com/jokob-sk/Pi.Alert) and many more

### Contact

  leiweibau@gmail.com

<!--- --------------------------------------------------------------------- --->
[main]:    ./docs/img/screen_main.png          "Main screen"

