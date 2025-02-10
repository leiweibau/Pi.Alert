# Guide for the first start
<!--- --------------------------------------------------------------------- --->

This guide is for those users who were successfully able to complete the installation and are now looking for help with the first steps.

To access the web interface for the first time, start the web browser of your choice and enter the IP address or the host name of the 
device on which you have installed Pi.Alert, followed by `/pialert` (e.g. http://192.168.1.25/pialert or http://pialerthost/pialert). 
The Pi.Alert page will now open. Depending on whether you have selected to mark devices as "new" during installation or not, you will 
now have corresponding information in the sidebar.

![Guide_001][Guide_001] 

The "**1**" (🟥) is the button for the device list. The colored badges show you the following: 
- in green the currently active (online) devices
- the yellow ones are "new" devices that can be both online and offline
- red are devices in which the notification for "Down" has been activated, i.e. certain devices that are offline

You can filter the device list accordingly using the tiles in area "**2**" (🟦)

The "**3**" (🟩) represents the device list, which is filled by the various scans and imports. For devices with the name "(unknown)", an 
attempt is made to determine the host name with each scan. However, the options here are limited. To speed up the scan a bit, it is 
advisable to enter a reasonable host name.
Various badges can appear in the "Status" column: 
 - "red" = offline, "Down" notification activated
 - "gray" = offline
 - "green" = online
 - "green" = online*, pending Scan Validation
 - "yellow, green" = new and online
 - "yellow, gray" = new and offline

‼️ ***Especially after the first start, many devices appear in the list where there is a need to enter additional information into the 
database over a longer period of time. In this case, it is advisable to activate the "Pause" timer in the settings after 5-10 minutes 
for a predefined time in order to make these changes undisturbed without the database being repeatedly blocked for changes by the scans.***

The basis of this device list is the MAC address that each device has. The device can define this Mac address randomly or use its own 
permanently programmed address. In the first case, there are fixed rules according to which Pi.Alert can recognize these addresses and 
then displays the "Random Mac" indicator. Pi.Alert cannot recognize devices that are located behind other routers or possibly also behind 
repeaters. However, if this is desired, the network must be reconfigured so that Pi.Alert has direct access to every network area. Pi.Alert 
must also be configured separately in this case. Another option for monitoring the accessibility of a device in a non-scannable area 
would be ICMP monitoring. However, this cannot be used to detect new devices, only whether a device is online or offline. With the 
satellite function, I offer a possibility to place a satellite in such a separate network, which independently executes scans there and 
sends the results to a previously configured Pi.Alert instance. These satellites can also be used to detect new devices in separate networks.

The "**4**" (🟪) is a link to the “docs” path in this repository


[Next - Discreet buttons and menus](./guide/001.md)

[Back to Readme](https://github.com/leiweibau/Pi.Alert)

[Guide_001]:             https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/guide_001.png         "Guide_001"
