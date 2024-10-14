## Troubleshooting

### Repairing an installation after a failed update

1. rename the still existing "pialert" directory e.g. to "pialert-old"
2. reinstall Pi.Alert
3. copy the database from "pialert-old/db" to "pialert/db/" and overwrite the existing database there
4. copy "pialert.conf" from "pialert-old/config" to "pialert/config"
5. execute the command "./pialert-cli set_permissions" in the "pialert/back" directory
6. now everything should work again



[Back](https://github.com/leiweibau/Pi.Alert)
