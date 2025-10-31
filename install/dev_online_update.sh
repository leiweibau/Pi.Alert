#!/bin/bash

echo "--------------------------------------------"
echo " Pi.Alert DEV Update Script"
echo "--------------------------------------------"
echo
echo "1) aktuelles Script (main)"
echo "2) neustes Script (next_update)"
echo "3) Nur Skript herunterladen (next_update)"
echo
read -p "Auswahl (1-3): " auswahl

case $auswahl in
  1)
    echo "Starte aktuelles Script..."
    bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/main/install/pialert_update.sh)"
    ;;
  2)
    echo "Starte neustes Script..."
    bash -c "$(wget -qLO - https://github.com/leiweibau/Pi.Alert/raw/next_update/install/pialert_update.sh)"
    ;;
  3)
    echo "Lade pialert_update.sh herunter (next_update)..."
    wget https://github.com/leiweibau/Pi.Alert/raw/next_update/install/pialert_update.sh
    ;;
  *)
    echo "Ungültige Auswahl. Abbruch."
    exit 1
    ;;
esac
