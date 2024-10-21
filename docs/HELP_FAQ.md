## Help/FAQ

<hr>

### General

#### _My network seems to slow down, streaming "freezes"_

It may well be that low-performance devices reach their performance limits with the way Pi.Alert detects new devices in the network. This is further exacerbated if these devices 
communicate with the network via WLAN. Solutions here would be, if possible, to switch to a cable connection or, if the device is only to be used for a limited period of time, 
to pause the arp scan on the maintenance page.


#### _I get the message that the database is read-only_

It is possible that the backend is currently writing changes to the database. Just try again after a short wait. If you want to make major changes to the device list, it makes 
sense to pause Pi.Alert for the duration of editing on the settings page. If the behavior is permanent, follow the instructions below. 

Check in the Pi.Alert directory whether the folder of the database (db) has been assigned the correct rights:

```
drwxrwxr-x 2 (your username) www-data
```

If the authorization is not correct, you can set it again with the following commands in the terminal or console:

```
sudo chgrp -R www-data ~/pialert/db
sudo chown [Username]:www-data ~/pialert/db/pialert.db
chmod -R 775 ~/pialert/db
```

Another possibility would be to reset the necessary rights with the help of pialert-cli in the directory `~/pialert/back`. Several options are available here.

```
./pialert-cli set_permissions
```

Only the group permissions are reset here. The owner of the file remains untouched.

```
./pialert-cli set_permissions --lxc
```

This additional option was introduced for use in an LXC container. It changes the group according to the basic function and sets the user “root” as the owner. This option is not 
relevant outside of an LXC environment.

```
./pialert-cli set_permissions --homedir
```

This should be the preferred option. Here the username is determined based on the parent home directory of the Pi.Alert installation. This user name is set as the owner of the files. 
The group is set according to the basic function.

If this error still appears after all the previous measures, delete the \*-wal and \*-shm files in the directory `~/pialert/db`


#### _The login page does not appear, even after changing the password_

In addition to the password, the parameter PIALERT_WEB_PROTECTION must also be set to True in the configuration file `~/pialert/config/pialert.conf`.


#### _There is an update available. How do I proceed if I want to update Pi.Alert?_

1. Check in the status box on the setup page that no scan is currently being performed
2. Further down, in the Security section, stop Pi.Alert for 15 minutes. This prevents the database from being processed during the update.
3. Now switch to the terminal of the device on which Pi.Alert is installed.
4. Execute the command: 
```
bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)"
```
5. Now follow the instructions
6. After the successful update, Pi.Alert should start again on its own. Alternatively, you can also restart it manually on the installation page.

<hr>

### Devices / Device Details

#### _I have devices in my list that are unknown to me or that I no longer use. After deleting them, they always appear again._

If you are using Pi-hole, please note that Pi.Alert retrieves information from Pi-hole. Pause Pi.Alert, go to the Settings page in Pi-hole and delete the relevant DHCP lease if necessary. 
Then check, also in Pi-hole, under Tools -> Network, whether the recurring hosts can be found there. If so, delete them there too. If these devices keep reappearing in Pi-hole even after 
deletion, restart the pihole-FTL service. Now you can start Pi.Alert again. Now the device(s) should no longer appear. If in doubt, a restart can also do no harm. If such a device continues 
to appear again and again, the MAC address can be added to an ignore list MAC_IGNORE_LIST in pialert.conf.


#### _What does “Random MAC” mean and why can't I select it?_

For data protection reasons, some modern devices generate random MAC addresses that can no longer be assigned to a manufacturer and which change with every new connection. Pi.Alert 
recognizes whether it is such a random MAC address and activates this “field” automatically. To stop this behavior, you must look in your end device to see how to deactivate MAC address 
generation. MAC addresses with the following scheme are marked as “random”: 									
* x2:xx:xx:xx:xx:xx:xx
* x6:xx:xx:xx:xx:xx:xx
* xA:xx:xx:xx:xx:xx:xx
* xE:xx:xx:xx:xx:xx:xx


#### _What is Nmap and what is it used for?_

Nmap is a network scanner with a wide range of options. When a new device appears in your list, you have the option of using the Nmap scan to obtain more detailed information about the device. 									
Pi.Alert offers 3 different preset scans: 									
- Quick scan: Checks only the most important 100 ports (a few seconds)
- Standard Scan: Nmap scans the first 1,000 ports for each requested scan protocol. (approx. 5-10 seconds)
- Detailed scan (timeout 60s): The scan is extended by some UDP ports. The range of TCP ports has also been increased.


[Back](https://github.com/leiweibau/Pi.Alert)
