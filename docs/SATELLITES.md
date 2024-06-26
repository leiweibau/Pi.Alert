## Satellites

### Standard Configuration

If you activate the satellite option, a new "Satellites" tab will appear on the settings page. The following steps are necessary to use a satellite that 
works in "Standard" mode.

1. create a new satellite by specifying a name and clicking on the green " floppy disk" symbol
2. the new satellite will now be displayed under this input field. A 48-character token and a 96-character password have been generated for the name. It is not possible to change these two fields.
3. these two fields are required for the satellite configuration file and must be entered there.
4. the URL of Pi.Alert must also be entered in the satellite (e.g. 'http://pialert.lan/pialert/api/satellite.php'). Instead of "pialert.lan", the correct name of the device running Pi.Alert must be entered here. An IP address must also be entered. It is only important that the URL can actually be called up directly from the satellite. If this is not possible, the satellite can also work in "PROXY_MODE".
5. As soon as the satellite is installed and configured, it starts transmitting its scan results and Pi.Alert can process them.

### Configuration PROXY_MODE

If it is not possible for the satellite to access the Pi.Alert URL directly, for example because you do not want to set up port forwarding on the router 
or because firewall rules prevent this, there is another option for data transmission.

To do this, the "PROXY_MODE" option must be set to "True" for the satellite. However, this makes it necessary to run the API on another web server that 
can be called by both the satellite and Pi.Alert. 

#### Preparation (web server)
To install the API on a web server, follow [these](/docs/PROXY_MODE.md) instructions

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