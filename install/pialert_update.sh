#!/usr/bin/env bash
# ------------------------------------------------------------------------------
#  Pi.Alert (Debian 13)
#  Open Source Network Guard / WIFI & LAN intrusion detector 
#
#  pialert_update.sh - Update script
# ------------------------------------------------------------------------------
#  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
#  leiweibau 2024                                          GNU GPLv3
# ------------------------------------------------------------------------------
#
# ------------------------------------------------------------------------------
# Variables
# ------------------------------------------------------------------------------
BRANCH="main"
INSTALL_DIR="/opt"
EXEC_USER="$(whoami)"
PIALERT_HOME="$INSTALL_DIR/pialert"
LOG="pialert_update_`date +"%Y-%m-%d_%H-%M"`.log"
PYTHON_BIN=python3
#PIHOLE_MOVED=false

# ------------------------------------------------------------------------------
# Main
# ------------------------------------------------------------------------------
main() {
  update_warning
  print_superheader "Pi.Alert Update"
  log "`date`"
  log "Logfile: $LOG"
  log ""

  set -e

  check_pihole
  check_pialert_home
  check_python_version

  stop_pialert
  reset_permissions
  create_backup
  move_files
  clean_files

  check_packages
  check_package_integrity
  download_pialert
  update_config
  update_db
  move_files_again
  update_permissions
  start_pialert

  test_pialert
  
  print_header "Update process finished"
  # if $PIHOLE_MOVED ; then
  #   print_msg ""
  #   print_header "!! Pi-hole Webinterface moved from Port 80 to Port 8080 !!"
  # fi
  print_msg ""

  move_logfile
}

# ------------------------------------------------------------------------------
# Initial Warning
# ------------------------------------------------------------------------------
update_warning() {
  clear
  print_msg "############################################################################"
  print_msg "# You are planning to update Pi.Alert. Please make sure that no scan takes #"
  print_msg "# place during the update to avoid possible database errors afterwards!!!  #"
  print_msg "#                                                                          #"
  print_msg "# This can be done by pausing Pi.Alert (at least 10min) via the settings   #"
  print_msg "# page. If the script does not correctly recognize that a scan is no       #"
  print_msg "# longer running, the update can be forced with “f”. Scans that are        #"
  print_msg "# already running will be terminated, which could lead to database errors. #"
  print_msg "############################################################################"
  print_msg ""
  print_msg ""
  printf "%s " "Press enter to retry or press 'F' to force the update"
  read -n 1 ans

  # Check if the user pressed "F" to force the update
  if [ "$ans" = "F" ] || [ "$ans" = "f" ]; then
    print_msg ""
    print_msg "####################################################################"
    print_msg "# Update forced. Skipping scan check...                            #"
    print_msg "####################################################################"
    print_msg ""
    return
  fi

  scan_file="$PIALERT_HOME/back/.scanning"

  if [ -f "$scan_file" ]; then
    print_msg ""
    print_msg "####################################################################"
    print_msg "# A SCAN IS CURRENTLY RUNNING!!! Please wait until it is finished. #"
    print_msg "####################################################################"
    print_msg ""
    sleep 2
    update_warning
  fi
}

# ------------------------------------------------------------------------------
# Check Pihole
# ------------------------------------------------------------------------------
check_pihole() {
    if systemctl is-active --quiet pihole-FTL; then
        VERSION_OUTPUT=$(sudo pihole -v)
        CORE_VERSION=$(echo "$VERSION_OUTPUT" | grep -oP 'Core version is v\K[0-9]+' || echo "")
        if [[ -n "$CORE_VERSION" && "$CORE_VERSION" -ge 6 ]]; then
            print_header "Pi-hole 6.x detected."
        fi
    fi
}

# ------------------------------------------------------------------------------
# Stop Pi.Alert, if possible
# ------------------------------------------------------------------------------
stop_pialert() {
  print_msg "- Stopping Pi.Alert..."
  $PIALERT_HOME/back/pialert-cli disable_scan
}

# ------------------------------------------------------------------------------
# Start Pi.Alert
# ------------------------------------------------------------------------------
start_pialert() {
  print_msg "- Starting Pi.Alert..."
  $PIALERT_HOME/back/pialert-cli enable_scan
}

# ------------------------------------------------------------------------------
# Reset Permissions
# ------------------------------------------------------------------------------
reset_permissions() {
  echo ""
  print_msg "- Reset permissions..."
  sudo chgrp -R www-data $PIALERT_HOME/db                             2>&1 >> "$LOG"
  sudo chmod -R 775 $PIALERT_HOME/db                                  2>&1 >> "$LOG"
  sudo chgrp -R www-data $PIALERT_HOME/config                         2>&1 >> "$LOG"
  sudo chmod 1775 $PIALERT_HOME/config                                2>&1 >> "$LOG"
  sudo find "$PIALERT_HOME/config" -maxdepth 1 -type f ! -name version.conf -exec chown www-data:www-data {} +  2>&1 >> "$LOG"
  sudo chown root:root "$PIALERT_HOME/config/version.conf"           2>&1 >> "$LOG"
  sudo chmod 0644 "$PIALERT_HOME/config/version.conf"                2>&1 >> "$LOG"
}

# ------------------------------------------------------------------------------
# Create backup
# ------------------------------------------------------------------------------
create_backup() {
  # Previous backups are deleted to preserve storage 
  print_msg "- Deleting previous Pi.Alert backups..."
  rm -f "$INSTALL_DIR/"pialert_update_backup_*.tar
  print_msg "- Creating new Pi.Alert backup..."
  cd "$INSTALL_DIR"
  tar cvf "$INSTALL_DIR"/pialert_update_backup_`date +"%Y-%m-%d_%H-%M"`.tar pialert --checkpoint=100 --checkpoint-action="ttyout=."     2>&1 >> "$LOG"
  echo ""
}

# ------------------------------------------------------------------------------
# Move files to the temp directory
# ------------------------------------------------------------------------------
move_files() {
  if [ -e "$PIALERT_HOME/back/speedtest/speedtest" ] ; then
    echo "- Moving speedtest to temporary directory..."
    mv "$PIALERT_HOME/back/speedtest" "$PIALERT_HOME/config"
  fi
  if ls "$PIALERT_HOME/db/setting_"* 1> /dev/null 2>&1; then
    echo "- Moving setting-files to new directory..."
    mv -f "$PIALERT_HOME/db/setting_"* "$PIALERT_HOME/config/"
  fi
}

# ------------------------------------------------------------------------------
# Move files from the temp directory
# ------------------------------------------------------------------------------
move_files_again() {
  if [ -e "$PIALERT_HOME/config/speedtest/speedtest" ] ; then
    echo "- Moving speedtest from temporary directory..."
    rm -rf "$PIALERT_HOME/back/speedtest"
    mv "$PIALERT_HOME/config/speedtest" "$PIALERT_HOME/back"
  fi
}

# ------------------------------------------------------------------------------
# Remove old files
# ------------------------------------------------------------------------------
clean_files() {
  print_msg "- Cleaning previous version..."
  rm -rf "$PIALERT_HOME/back"
  rm -rf "$PIALERT_HOME/doc"
  rm -rf "$PIALERT_HOME/docs"
  rm -rf "$PIALERT_HOME/front"
  rm -rf "$PIALERT_HOME/install"
  rm -rf "$PIALERT_HOME/*.txt"
  rm -rf "$PIALERT_HOME/*.md"
}

# ------------------------------------------------------------------------------
# Check packages
# ------------------------------------------------------------------------------
check_packages() {
  sudo apt-get update 2>&1 >>"$LOG"
  packages=("apt-utils" "sqlite3" "dnsutils" "net-tools" "wakeonlan" "nbtscan" "avahi-utils" "php-curl" "php-xml"  \
  "python3-requests" "python3-cryptography" "libwww-perl" "mmdb-bin" "libtext-csv-perl" "aria2" "python3-tz"  \
  "python3-tzlocal" "fping" "usbutils")
  print_msg "- Checking packages..."
  missing_packages=()
  for package in "${packages[@]}"; do
    if ! dpkg -l | grep -q "$package"; then
      missing_packages+=("$package")
    fi
  done
  if [ ${#missing_packages[@]} -gt 0 ]; then
    print_msg "- Installing missing packages: ${missing_packages[*]}"
    sudo apt-get install -y "${missing_packages[@]}" 2>&1 >>"$LOG"
  fi
}

# ------------------------------------------------------------------------------
# Check package integrity
# ------------------------------------------------------------------------------
check_package_integrity() {
  print_msg "- Checking package integrity..."
  if [ ! -f /usr/sbin/get-oui ]; then
      if ! sudo apt-get reinstall -y arp-scan 2>&1 >>"$LOG"; then
          print_msg "- Reinstall of arp-scan failed, trying 'apt-get install --fix-broken' option."
          sudo apt-get install --fix-broken                     2>&1 >>"$LOG"
          sudo apt-get install -y arp-scan                      2>&1 >>"$LOG"
      else
          print_msg "- 'arp-scan' reinstalled."
      fi
  else
      print_msg "- 'arp-scan' seems to be installed correctly"
  fi
}

# ------------------------------------------------------------------------------
# Download and uncompress Pi.Alert
# ------------------------------------------------------------------------------
download_pialert() {
  if [ -f "$INSTALL_DIR/pialert_latest.tar" ] ; then
    print_msg "- Deleting previous downloaded tar file"
    rm -f "$INSTALL_DIR/pialert_latest.tar"
  fi

  print_msg "- Downloading update file..."
  URL="https://github.com/leiweibau/Pi.Alert/raw/$BRANCH/tar/pialert_latest.tar"
  wget -q --show-progress -O "$INSTALL_DIR/pialert_latest.tar" "$URL"

  print_msg "- Uncompressing tar file"

  EXCLUDES=(
    --exclude=pialert/config/pialert.conf
    --exclude=pialert/db/pialert.db
    --exclude=pialert/log/*
  )

  if [ -f "$INSTALL_DIR/pialert/db/pialert_tools.db" ]; then
      EXCLUDES+=( --exclude=pialert/db/pialert_tools.db )
  fi

  tar xf "$INSTALL_DIR/pialert_latest.tar" -C "$INSTALL_DIR" \
      "${EXCLUDES[@]}" \
      --checkpoint=100 --checkpoint-action="ttyout=."                2>&1 >> "$LOG"

  echo ""

  print_msg "- Deleting downloaded tar file..."
  rm -f "$INSTALL_DIR/pialert_latest.tar"

  print_msg "- Generate autocomplete file..."
  PIALERT_CLI_PATH=$(dirname $PIALERT_HOME)
  sed -i "s|<YOUR_PIALERT_PATH>|$PIALERT_CLI_PATH/pialert|" $PIALERT_HOME/install/pialert-cli.autocomplete

  print_msg "- Copy autocomplete file..."
  if [ -d "/etc/bash_completion.d" ] ; then
      sudo cp $PIALERT_HOME/install/pialert-cli.autocomplete /etc/bash_completion.d/pialert-cli 2>&1 >> "$LOG"
  elif [ -d "/usr/share/bash-completion/completions" ] ; then
      sudo cp $PIALERT_HOME/install/pialert-cli.autocomplete /usr/share/bash-completion/completions/pialert-cli 2>&1 >> "$LOG"
  fi
}

# ------------------------------------------------------------------------------
#  Update conf file
# ------------------------------------------------------------------------------
update_config() {
  print_msg "- Config backup..."
  sudo cp -p "$PIALERT_HOME/config/pialert.conf" \
    "$PIALERT_HOME/config/pialert.conf.back" 2>&1 >> "$LOG"

  print_msg "- Updating config file..."
  # Migrate by assignment name. Comments, section names, and section order are
  # intentionally irrelevant. The helper writes atomically and validates the
  # complete candidate before replacing the live configuration.
  if ! sudo "$PYTHON_BIN" "$PIALERT_HOME/install/migrate_pialert_config.py" \
      "$PIALERT_HOME/config/pialert.conf" \
      "$PIALERT_HOME" >> "$LOG" 2>&1; then
    print_msg "- Configuration migration failed; pialert.conf was not changed."
    process_error "Invalid configuration after migration"
  fi
}

# ------------------------------------------------------------------------------
#  DB DDL
# ------------------------------------------------------------------------------
update_db() {
  print_msg "- Create user_vendors.txt if not exists..."
  CUSTOM_VENDORFILE="$PIALERT_HOME/db/user_vendors.txt"

  if [ ! -f "$CUSTOM_VENDORFILE" ]; then
      cat <<EOL > "$CUSTOM_VENDORFILE"
# Syntax
#
# <MAC-Prefix><TAB><Vendor>
# 010B45DH      Your Hardware Vendor
#
# Where <MAC-Prefix> is the prefix of the MAC address in hex, and <Vendor>
# is the name of the vendor. The prefix must have a length of 8 hex 
# digits. This makes it easier to filter the correct entry.
#
# The order of entries in this file are not important.
#
# If a manufacturer can already be assigned during the arp-scan, entries 
# in this file have no relevance. However, if the Mac address in question 
# cannot be assigned to a manufacturer using the arp-scan vendor database,
# this file can be used for assignment.
#
#
EOL

  else
      print_msg "- 'user_vendors.txt' already exists"
  fi

  print_msg "- Updating DB permissions..."
  sudo chgrp -R www-data $PIALERT_HOME/db                         2>&1 >> "$LOG"
  sudo chmod -R 775 $PIALERT_HOME/db                              2>&1 >> "$LOG"

  print_msg "- Installing sqlite3..."
  sudo apt-get install sqlite3 -y                                 2>&1 >> "$LOG"

}

# ------------------------------------------------------------------------------
# Update permissions
# ------------------------------------------------------------------------------
update_permissions() {
  print_msg "- Set Permissions..."
  sudo chgrp -R www-data "$PIALERT_HOME/db"                              2>&1 >> "$LOG"
  sudo chmod -R 775 "$PIALERT_HOME/db/temp"                              2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/shoutrrr/arm64/shoutrrr"             2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/shoutrrr/armhf/shoutrrr"             2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/shoutrrr/x86/shoutrrr"               2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/speedtest-cli"                       2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/pialert-cli"                         2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/pialert.py"                          2>&1 >> "$LOG"
  sudo chmod +x "$PIALERT_HOME/back/update_vendors.sh"                   2>&1 >> "$LOG"
  sudo chgrp -R www-data "$PIALERT_HOME/config"                          2>&1 >> "$LOG"
  sudo chmod 1775 "$PIALERT_HOME/config"                                 2>&1 >> "$LOG"
  sudo find "$PIALERT_HOME/config" -maxdepth 1 -type f ! -name version.conf -exec chown www-data:www-data {} +  2>&1 >> "$LOG"
  sudo chown root:root "$PIALERT_HOME/config/version.conf"               2>&1 >> "$LOG"
  sudo chmod 0644 "$PIALERT_HOME/config/version.conf"                    2>&1 >> "$LOG"
  sudo chmod -R 775 "$PIALERT_HOME/front/reports"                        2>&1 >> "$LOG"
  sudo chgrp -R www-data "$PIALERT_HOME/front/reports"                   2>&1 >> "$LOG"
  sudo chmod -R 775 "$PIALERT_HOME/front/php/tmp"                        2>&1 >> "$LOG"
  sudo chgrp -R www-data "$PIALERT_HOME/front/php/tmp"                   2>&1 >> "$LOG"
  sudo chmod -R 775 "$PIALERT_HOME/front/satellites"                     2>&1 >> "$LOG"
  sudo chgrp -R www-data "$PIALERT_HOME/front/satellites"                2>&1 >> "$LOG"
  sudo chmod -R 775 "$PIALERT_HOME/back/speedtest/"                      2>&1 >> "$LOG"
  sudo chgrp -R www-data "$PIALERT_HOME/back/speedtest/"                 2>&1 >> "$LOG"
  print_msg "- Create Logfiles..."
  dest_dir="$INSTALL_DIR/pialert/front/php/server"
  for file in pialert.vendors.log pialert.IP.log pialert.1.log pialert.cleanup.log pialert.webservices.log pialert.speedtest.log pialert.nmap.log usercron.log; do
      sudo touch "$PIALERT_HOME/log/$file"                               2>&1 >> "$LOG"
      sudo chmod 644 "$PIALERT_HOME/log/$file"                           2>&1 >> "$LOG"
      if [ -L "$dest_dir/$file" ]; then
          sudo rm -- "$dest_dir/$file"                                   2>&1 >> "$LOG"
      fi
  done
  print_msg "- Set sudoers..."

  sudo $PIALERT_HOME/back/pialert-cli set_sudoers --lxc             2>&1 >> "$LOG"

  print_msg "- Patch DB..."
  $PIALERT_HOME/back/pialert-cli update_db

}

# ------------------------------------------------------------------------------
# Test Pi.Alert
# ------------------------------------------------------------------------------
test_pialert() {
  print_msg "- Testing Pi.Alert HW vendors database update process..."
  print_msg "*** PLEASE WAIT A COUPLE OF MINUTES..."
  stdbuf -i0 -o0 -e0 $PYTHON_BIN $PIALERT_HOME/back/pialert.py update_vendors_silent  2>&1 | tee -ai "$LOG"

  echo ""
  print_msg "- Testing Pi.Alert Internet IP Lookup..."
  stdbuf -i0 -o0 -e0 $PYTHON_BIN $PIALERT_HOME/back/pialert.py internet_IP            2>&1 | tee -ai "$LOG"

  echo ""
  print_msg "- Testing Pi.Alert Network scan..."
  print_msg "*** PLEASE WAIT A COUPLE OF MINUTES..."
  stdbuf -i0 -o0 -e0 $PYTHON_BIN $PIALERT_HOME/back/pialert.py 1                      2>&1 | tee -ai "$LOG"
}

# ------------------------------------------------------------------------------
# Check Pi.Alert Installation Path
# ------------------------------------------------------------------------------
check_pialert_home() {
  if [ ! -e "$PIALERT_HOME" ] ; then
    process_error "Pi.Alert directory doesn't exists: $PIALERT_HOME"
  fi
}

# ------------------------------------------------------------------------------
# Check Python versions available
# ------------------------------------------------------------------------------
check_and_install_package() {
  package_name="$1"
  if pip3 show "$package_name" > /dev/null 2>&1; then
    print_msg "  - $package_name is already installed"
  else
    print_msg "  - Installing $package_name..."
    if [ -e "$(find /usr/lib -path '*/python3.*/EXTERNALLY-MANAGED' -print -quit)" ]; then
      pip3 -q install "$package_name" --break-system-packages --no-warn-script-location         2>&1 >> "$LOG"
    else
      pip3 -q install "$package_name" --no-warn-script-location                                 2>&1 >> "$LOG"
    fi
    print_msg "    - $package_name is now installed"
  fi
}
check_python_version() {
  print_msg "- Checking Python..."
  PYTHON_BIN=""
  if [ -f /usr/bin/python3 ]; then
    PYTHON_BIN="python3"
    print_msg "Python 3 is installed on your system"
    check_and_install_package "mac-vendor-lookup"
    check_and_install_package "fritzconnection"
    check_and_install_package "routeros_api"
    check_and_install_package "pyunifi"
    check_and_install_package "openwrt-luci-rpc"
    check_and_install_package "asusrouter"
    check_and_install_package "paho-mqtt"

    REQUIRED_VERSION="2.31.0"
    INSTALLED_VERSION=$(pip3 show requests 2>/dev/null | awk '/^Version:/ {print $2}')

    version_lt () {
      [ "$(printf '%s\n' "$1" "$2" | sort -V | head -n1)" != "$2" ]
    }

    if [ -z "$INSTALLED_VERSION" ] || version_lt "$INSTALLED_VERSION" "$REQUIRED_VERSION"; then
      print_msg "  - Updating requests (installed: ${INSTALLED_VERSION:-none})"
      if [ -e "$(find /usr/lib -path '*/python3.*/EXTERNALLY-MANAGED' -print -quit)" ]; then
        pip3 -q install "requests>=${REQUIRED_VERSION}" --break-system-packages --no-warn-script-location   2>&1 >> "$LOG"
      else
        pip3 -q install "requests>=${REQUIRED_VERSION}" --no-warn-script-location                           2>&1 >> "$LOG"
      fi
    fi

  else
    print_msg "Python 3 NOT installed"
    process_error "Python 3 is required for this application"
  fi
}

# ------------------------------------------------------------------------------
# Move Logfile
# ------------------------------------------------------------------------------
move_logfile() {
  NEWLOG="$PIALERT_HOME/log/$LOG"

  mkdir -p "$PIALERT_HOME/log"
  mv $LOG $NEWLOG

  LOG="$NEWLOG"
  NEWLOG=""
}

# ------------------------------------------------------------------------------
# Log
# ------------------------------------------------------------------------------
log() {
  echo "$1" | tee -a "$LOG"
}

log_no_screen () {
  echo "$1" >> "$LOG"
}

log_only_screen () {
  echo "$1"
}

print_msg() {
  log_no_screen ""
  log "$1"
}

print_superheader() {
  log ""
  log "############################################################"
  log " $1"
  log "############################################################"  
}

print_header() {
  log ""
  log "------------------------------------------------------------"
  log " $1"
  log "------------------------------------------------------------"
}

process_error() {
  log ""
  log "************************************************************"
  log "************************************************************"
  log "**             ERROR UPDATING PI.ALERT                    **"
  log "************************************************************"
  log "************************************************************"
  log ""
  log "$1"
  log ""
  log "Use 'cat $LOG' to view update log"
  log ""

  exit 1
}

# ------------------------------------------------------------------------------
  main
  exit 0
