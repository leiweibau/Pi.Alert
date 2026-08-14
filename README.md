# Pi.Alert

Pi.Alert keeps an eye on your Wi-Fi and LAN and lets you know when something
changes. It discovers devices on your network, tracks their availability, and
provides a web interface for managing everything in one place.

![Pi.Alert main screen][main]

## What Pi.Alert can do

- Discover devices connected to your Wi-Fi or LAN.
- Notify you when an unknown device appears or a known device goes offline.
- Monitor devices outside the local subnet using ICMP.
- Detect unwanted or foreign DHCP servers.
- Monitor internal and external web services by checking HTTP status, response
  time, and SSL certificate information.
- Alert you when a monitored service becomes unavailable or its status or
  certificate changes.
- Run manual Nmap scans and send Wake-on-LAN commands.
- Show how devices are connected through routers, switches, and other network
  infrastructure.

Need visibility into another network segment? The companion
[Pi.Alert-Satellite](https://github.com/leiweibau/Pi.Alert-Satellite) project
can scan independently and send its results to an existing Pi.Alert instance.

[See how this version compares with the original project](docs/VERSIONCOMPARE.md).

## Scan methods and imports

Pi.Alert can collect device information from several sources:

- **arp-scan** for local network discovery
- **Pi-hole 5 and 6** for DNS and DHCP data
- **FRITZ!Box** for active hosts
- **MikroTik** for DHCP leases
- **UniFi** for client devices
- **OpenWrt** and **AsusWRT** for active hosts
- **pfSense** and **OPNsense** for active hosts, DHCP leases, and ARP tables
- **AdGuard Home** for DHCP leases and hosts detected through recent DNS queries
- **Pi.Alert-Satellite** using arp-scan, Pi-hole 6, MikroTik, UniFi, OpenWrt,
  AsusWRT, pfSense, OPNsense, or AdGuard Home

## How it works

### Backend

The backend is normally started by the operating system's cron service every
five minutes. It runs the configured scans and imports, stores their results,
and sends notifications when relevant changes are detected. It also monitors
manually configured hosts and web services.

Notifications can be delivered through the frontend, email
([setup guide](docs/NOTIFICATION_MAIL.md)),
[Pushsafer](https://www.pushsafer.com/), [Pushover](https://pushover.net/),
ntfy, or Telegram through the Telegram Bot API. Shoutrrr remains available as
an optional discovery helper ([setup guide](docs/NOTIFICATION_TELEGRAM.md)).

### Web interface

The web interface gives you a central place to:

- review current and historical device information;
- add owners, locations, groups, notes, and other device metadata;
- organize network relationships;
- configure the frontend and edit backend settings;
- view events, reports, journals, and scan results;
- use the API with integrations such as
  [Home Assistant](docs/API-USAGE.md) or
  [Homepage](https://github.com/gethomepage/homepage).

The interface is available in English, German, Spanish, French, Italian,
Polish, Danish, Dutch, Czech, Finnish, Swedish, Norwegian, Lithuanian,
Ukrainian, and Russian. Login protection is enabled during installation with a
randomly generated password. You can change it later with
[`pialert-cli`](docs/PIALERTCLI.md).

Skin-specific [favicons and home-screen icons](docs/ICONS.md) are included.
They can also be linked directly from this repository when iOS refuses to load
icons from an HTTP or self-signed HTTPS installation.

## Installation

Pi.Alert was originally designed for the Raspberry Pi and targets Debian-based
systems using `apt`. Before installing it on DietPi, Ubuntu Server, or another
Debian-based distribution, check the
[distribution notes](docs/LINUX-DISTRIBUTIONS.md).

Run the installer as root:

```bash
sudo bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_install.sh)"
```

Once installation is complete, continue with the
[first-start guide](docs/FIRST_START_GUIDE.md).

### Proxmox LXC

For an LXC installation, take a look at the
[Proxmox VE Helper-Scripts](https://github.com/community-scripts/ProxmoxVE),
originally provided by the now archived
[tteck/Proxmox](https://github.com/tteck/Proxmox) repository. This installation
method uses the Pi.Alert version from this repository after the container has
been created and provides its own update command.

## Updating

The **Update Check** entry in the sidebar shows available Pi.Alert releases,
their changes, and the status of the GeoLite2 database.

To update an existing installation, run:

```bash
sudo bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)"
```

If your installation still uses the previous home-directory layout, follow the
[migration guide](docs/MIGRATION_HOME_TO_OPT.md).

Older releases and their release notes are available from the
[Pi.Alert release archive](https://leiweibau.net/archive/pialert/).

## Documentation

- [First-start guide](docs/FIRST_START_GUIDE.md)
- [FAQ](docs/HELP_FAQ.md) and [troubleshooting](docs/TROUBLESHOOTING.md)
- [Screenshots](docs/SCREENSHOTS.md)
- [Device management](docs/DEVICE_MANAGEMENT.md) and
  [bulk editor](docs/BULKEDITOR.md)
- [`pialert-cli`](docs/PIALERTCLI.md) and
  [`pialert.conf`](docs/PIALERT_CONF.md)
- [Network relationships](docs/NETWORK_RELATIONSHIP.md)
- [Web service monitoring](docs/WEBSERVICES.md)
- [Satellite configuration](docs/SATELLITES.md)
- [API usage](docs/API-USAGE.md)
- [Favicons and home-screen icons](docs/ICONS.md)
- [Uninstallation](docs/UNINSTALL.md)

## Related projects

- [NetAlertX](https://github.com/jokob-sk/NetAlertX) began as a Pi.Alert fork
  and is now an independent, Docker-based project.
- [pucherot/Pi.Alert](https://github.com/pucherot/Pi.Alert/) is the original,
  currently unmaintained project.

## Support and thanks

If Pi.Alert is useful to you and you would like to support its development:

| GitHub Sponsors | Buy Me a Coffee |
| --- | --- |
| [<img src="https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/githubsponsor.png" height="30" alt="Sponsor on GitHub">](https://github.com/sponsors/leiweibau) | [<img src="https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png" height="30" alt="Buy Me a Coffee">](https://www.buymeacoffee.com/leiweibau) |

Thank you to everyone who sponsors, contributes to, tests, translates, or
otherwise supports the project.

## License

Pi.Alert is released under the [GNU General Public License 3.0](LICENSE.txt).

## Contact

Questions and feedback are welcome at `leiweibau@gmail.com`.

## Credits

Pi.Alert also uses or has benefited from the following projects and services:

- [Loading animation](https://commons.wikimedia.org/wiki/File:Loading_Animation.gif)
- [Adobe Fonts](https://github.com/adobe-fonts/source-sans)
- [Bootstrap Icons](https://github.com/twbs/icons)
- [Material Design Icons](https://github.com/Pictogrammers)
- [IMGonline seamless texture tool](https://www.imgonline.com.ua/eng/make-seamless-texture.php)
- [DeepL](https://www.deepl.com)
- [ChatGPT](https://chat.openai.com)

[main]: https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/screen_main_da_li.png "Pi.Alert main screen"
