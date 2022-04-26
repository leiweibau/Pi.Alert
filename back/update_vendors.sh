#!/bin/sh
# ------------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector 
#
#  update_vendors.sh - Back module. IEEE Vendors db update
# ------------------------------------------------------------------------------
#  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
# ------------------------------------------------------------------------------

# ----------------------------------------------------------------------
#  Main directories to update:
#    /usr/share/arp-scan
#    /usr/share/ieee-data
#    /var/lib/ieee-data
# ----------------------------------------------------------------------


# ----------------------------------------------------------------------
echo Updating... /usr/share/ieee-data/
cd /usr/share/ieee-data/

curl -L $1 -# -O https://standards-oui.ieee.org/iab/iab.csv
curl -L $1 -# -O https://standards-oui.ieee.org/iab/iab.txt
curl -L $1 -# -O https://standards-oui.ieee.org/oui28/mam.csv
curl -L $1 -# -O https://standards-oui.ieee.org/oui28/mam.txt
curl -L $1 -# -O https://standards-oui.ieee.org/oui36/oui36.csv
curl -L $1 -# -O https://standards-oui.ieee.org/oui36/oui36.txt
curl -L $1 -# -O https://standards-oui.ieee.org/oui/oui.csv
curl -L $1 -# -O https://standards-oui.ieee.org/oui/oui.txt

# ----------------------------------------------------------------------
# echo ""
# echo Updating... /usr/share/arp-scan/
# cd /usr/share/arp-scan

# Update from /usb/lib/ieee-data
# get-iab -v
# get-oui -v

# Update from ieee website
# get-iab -v -u http://standards-oui.ieee.org/iab/iab.txt
# get-oui -v -u http://standards-oui.ieee.org/oui/oui.txt

# Update from ieee website develop
# get-iab -v -u http://standards.ieee.org/develop/regauth/iab/iab.txt
# get-oui -v -u http://standards.ieee.org/develop/regauth/oui/oui.txt

# Update from Sanitized oui (linuxnet.ca)
# get-oui -v -u https://linuxnet.ca/ieee/oui.txt

