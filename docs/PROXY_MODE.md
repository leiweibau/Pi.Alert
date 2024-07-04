## Use case:
If it is not desired for security reasons that the Satellite sends the data directly to the Pi.Alert API, a second web server can be used as a "proxy". 
In this case, the satellite sends the data to the proxy and Pi.Alert retrieves the data from it. The data is not decrypted on the proxy.

## Requirements:

- Web server with PHP support
- Web server must support file upload
- The web server must be accessible for both the satellite and the Pi.Alert instance itself
- An already installed version of Pi.Alert for creation of a configuration file

## Installation:

You can find the API files in the "api" folder of the Pi.Alert Satellite Archive.

This step only needs to be performed once and can be used for multiple satellites.
Create a new folder for the Pi.Alert API Proxy on your web server within the web root (it can also be a subfolder). For the purposes of this guide, 
I will use the folder "pialert_proxy". Place the "api" folder and its contents into this directory. Additionally, create the "satellites" folder.
The resulting folder structure should be as follows:

```
pialert_proxy
├── api
├───── satellite.php
└── satellites
```

The URL for the API is now, for example: https://example.com/pialert_proxy/api/satellite.php

## Configuration:
In the configuration file of the satellite ("config/satellite.conf"), the variable "PROXY_MODE" must be set to "True". The URL of the proxy is entered
as the value for the variable "SATELLITE_MASTER_URL" according to the example above. Finally, a file called "config.php", which can be downloaded from 
the Pi.Alert satellite settings, is also copied to the "api" directory. This file contains the tokens of all created satellites and ensures that only 
valid satellites are allowed to interact with the API.

```
pialert_proxy
├── api
├───── satellite.php
├───── config.php
└── satellites
```

## Additional information

The specification of a token and the mode used is mandatory for the API. If this is not the case, it outputs an HTTP status code 404 including a 
corresponding error page. All other states are "answered" in the form of a JSON message when using the "proxy" and "direct" modes. The "get" mode is 
used to download the files and has the additional function of deleting all scan results on the proxy that are older than 10 minutes. The reason for this 
is that if the satellite or the Internet connection from the satellite fails, the last available scan is not constantly loaded, making it appear as if 
everything is OK. By deleting the old scans, the satellite and all connected devices are displayed as offline.

[Back](https://github.com/leiweibau/Pi.Alert)
