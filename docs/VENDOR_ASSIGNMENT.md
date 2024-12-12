## General information:
arp-scan uses a regularly updated database to assign a manufacturer to a MAC address. In addition, there is a Python module for certain functions 
within the script, which has its own database that is also updated regularly.

If a vendor is not assigned to a device when it is first detected by Pi.Alert, "(unknown)" is entered in the Vendor field. As long as this 
string is in the field, or the field is completely empty, a new search is triggerd in the VendorDB with every scan, in case there has been an 
update in the meantime and an assignment is now possible.

## Individual entries:
If there is a need to define your own vendors for certain MAC addresses in advance, this can be done from Pi.Alert 2024-12-12 via the "user_vendors.txt" 
file in the "pialert/db/" directory. The file is created there with the installation or update to this version.

```
# Syntax
#
# <MAC-Prefix><TAB><Vendor>
# 010B45DH	Your Hardware Vendor
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
```

This list is processed with priority over the actual VendorDB.

[Back](https://github.com/leiweibau/Pi.Alert)
