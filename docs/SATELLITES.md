## Satellites

### The satellite can operate in 1 of 2 different modes

![Satellite Modes][Satellite_Modes]

### Standard Configuration

If you activate the satellite option, a new "Satellites" tab will appear on the settings page. The following steps are necessary to use a satellite that 
works in "Standard" or "Direct" mode.

![Config MainScreen][Config] 

1. Create a new satellite by specifying a name and clicking on the green " floppy disk" symbol
2. The new satellite will now be displayed under this input field. A 48-character token and a 96-character password have been generated for the name. It is not possible to change these two fields.
3. These two fields are required for the satellite configuration file and must be entered there.
4. The URL of Pi.Alert must also be entered in the satellite (e.g. 'http://pialert.lan/pialert/api/satellite.php'). Instead of "pialert.lan", the correct name of the device running Pi.Alert must be entered here. An IP address can also be entered. It is only important that the URL can actually be called up directly from the satellite. If this is not possible, the satellite can also work in "PROXY_MODE".
5. As soon as the satellite is installed and configured, it starts transmitting its scan results and Pi.Alert can process them.

The red “R” next to the version indicates that notification of transmission errors is activated for this satellite. This requires the storage of mail account data in the satellite's configuration file.

The satellite scan is performed every 5 minutes and the log file is located in the "pialert-satellite" folder under "/log/satellite.scan.log"

### Configuration PROXY_MODE

If it is not possible for the satellite to access the Pi.Alert URL directly, for example because you do not want to set up port forwarding on the router 
or because firewall rules prevent this, there is another option for data transmission.

To do this, the "PROXY_MODE" option must be set to "True" for the satellite. However, this makes it necessary to run the API on another web server that 
can be called by both the satellite and Pi.Alert. 

#### Preparation (web server)
To install the API on a web server, follow [these](PROXY_MODE.md) instructions

#### Configuration (satellite)
In the configuration file of the satellite ("config/satellite.conf"), the variable "PROXY_MODE" must be set to "True". The URL of the proxy is entered
as the value for the variable "SATELLITE_MASTER_URL" according to the example above.

#### Configuration (Pi.Alert)

```
# Satellite Configuration
# -----------------------
SATELLITE_PROXY_MODE = False
SATELLITE_PROXY_URL = ''
```
In the configuration file of the satellite ("config/satellite.conf"), the variable "SATELLITE_PROXY_MODE" must be set to "True". The URL of the proxy is entered
as the value for the variable "SATELLITE_PROXY_URL" according to your environment.

[Back](https://github.com/leiweibau/Pi.Alert)

[Config]:          https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/satellite_config.png      "Config MainScreen"
[Satellite_Modes]: https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/Satellite_Modes.png       "Satellite Modes"
