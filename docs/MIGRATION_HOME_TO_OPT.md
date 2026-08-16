## Pi.Alert Migration / Reinstallation Guide

### Automated move without reinstallation

The supplied `install/pialert_migrate_home_to_opt.sh` script can move an
existing `$HOME/pialert` installation directly to `/opt/pialert`. It does not
download, update, uninstall, or reinstall Pi.Alert. Run it from the existing
installation after pausing Pi.Alert and confirming that no scan is active:

```
sudo bash "$HOME/pialert/install/pialert_migrate_home_to_opt.sh"
```

The script creates a complete timestamped backup under `/opt`, moves the full
installation directory, updates `PIALERT_PATH`, adjusts existing Pi.Alert path
references in crontabs, sudoers files, and shell completion, and recreates the
`/var/www/html/pialert` WebRoot symlink. For older installations, existing log
symlinks under `front/php/server` are detected and recreated with their new
targets. The script refuses to overwrite an existing `/opt/pialert` directory
or a non-symlink WebRoot entry. Pi.Alert remains paused afterwards and must be
verified and resumed through the WebGUI.

For a source directory other than `$HOME/pialert`, use:

```
sudo PIALERT_HOME_OVERRIDE=/path/to/pialert \
  bash /path/to/pialert/install/pialert_migrate_home_to_opt.sh
```

The manual reinstallation procedure below remains available as an alternative.

### Manual reinstallation

1. Pause Pi.Alert via the web interface:

	Settings -> Tab/Settings -> Section/Security -> Toggle Pi.Alert

	Afterwards, check the Status Box and make sure that no scan is currently active.
	If a scan is running, wait until it has finished.


2. Switch to the console of your Pi.Alert host and create a new directory outside of your user directory, for example:

	```
	/opt/pialertmigration
	```


3. Create a backup of the following directories:

	```
	$HOME/pialert/config
	$HOME/pialert/db
	```

4. Copy Backups to the Migration Directory "/opt/pialertmigration"

	Verify that the backed-up files are present in the "/opt/pialertmigration" directory.


5. Uninstall the Current Pi.Alert Installation. Depending on your installed version, run the appropriate uninstall script.

	If you are on version v2025-12-14 or newer:
	```
	$HOME/pialert/install/pialert_uninstall_old.sh
	```
	If your version is older than v2025-12-14:
	```
	$HOME/pialert/install/pialert_uninstall.sh
	```


6. Install Pi.Alert Using the Latest Installer

	```
	sudo bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_install.sh)"
	```


7. Open the Pi.Alert web interface and verify that the installation was completed successfully.


8. Restore your previously saved files in "/opt/pialertmigration" as follows:

	- Copy all \*.db files to "/opt/pialert/db/" and overwrite existing files if prompted.

	- Copy all setting_\* files to "/opt/pialert/config/"

	- Copy the file pialert.conf to "/opt/pialert/config/"

9. Edit the restored configuration file "pialert.conf" in "/opt/pialert/config/".

	```
	sudo nano /opt/pialert/config/pialert.conf
	```

	Set the value of PIALERT_PATH to the new directory	

	```
	PIALERT_PATH               = '/opt/pialert'
	```


10. Set Correct Permissions

	```
	sudo chgrp -R www-data "/opt/pialert/db"
	sudo chmod -R 775 "/opt/pialert/db"
	sudo chmod -R 775 "/opt/pialert/db/temp"
	sudo chgrp -R www-data "/opt/pialert/config"
	sudo chmod -R 775 "/opt/pialert/config"
	```


11. If you have previously downloaded the Ookla Speedtest Client to use the automatic speed tests, you will need to download it again via the WebGUI.
