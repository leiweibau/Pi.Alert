#!/bin/bash
clear
echo "#################################################################"
echo "# Try to fix the port conflict between Pi.Alert and Pi-hole 6.x #"
echo "#################################################################"
echo ""
echo "- Check Pi-hole 6.x presence"
if systemctl is-active --quiet pihole-FTL; then
    echo "- Get Pi-hole Version"
    VERSION_OUTPUT=$(sudo pihole -v)
    CORE_VERSION=$(echo "$VERSION_OUTPUT" | grep -oP 'Core version is v\K[0-9]+' || echo "")

    if [[ -n "$CORE_VERSION" && "$CORE_VERSION" -ge 6 ]]; then
        echo "- Pi-hole 6.x detected."
        echo ""

        read -p "Enter the new web interface port (default 8080): " WEB_PORT
        read -p "Enter the new HTTPS port (default 443): " HTTPS_PORT

        WEB_PORT=${WEB_PORT:-8080}
        HTTPS_PORT=${HTTPS_PORT:-443}
        
        echo "Webinterface moved to Port $WEB_PORT/$HTTPS_PORT..."
        sudo systemctl stop lighttpd
        sudo pihole-FTL --config webserver.port ${WEB_PORT}o,${HTTPS_PORT}so,[::]:${WEB_PORT}o,[::]:${HTTPS_PORT}so
        sudo systemctl restart pihole-FTL
        sudo systemctl enable lighttpd
        sudo systemctl start lighttpd
    else
        echo "- no Pi-hole 6.x detected."
        echo "- The script has not made any changes "
    fi
fi
