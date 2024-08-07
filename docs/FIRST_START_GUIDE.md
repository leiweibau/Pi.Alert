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

The "**3**" (🟩) represents the device list, which is filled by the various scans and imports. For devices with the name "(unknown)", an attempt is 
made to determine the host name with each scan. However, the options here are limited. To speed up the scan a bit, it is advisable to enter 
a reasonable host name.
Various badges can appear in the "Status" column: 
 - "red" = offline, "Down" notification activated
 - "gray" = offline
 - "green" = online
 - "yellow, green" = new and online
 - "yellow, gray" = new and offline

[Next - Discreet buttons and menus](./guide/001.md)

[Back to Readme](https://github.com/leiweibau/Pi.Alert)

[Guide_001]:             https://raw.githubusercontent.com/leiweibau/Pi.Alert/assets/guide_001.png         "Guide_001"
