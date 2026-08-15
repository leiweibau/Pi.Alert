#!/usr/bin/env bash
# Move an existing Pi.Alert installation from $HOME/pialert to /opt/pialert.
# No uninstall, download, update, or reinstallation is performed.
set -Eeuo pipefail
umask 077

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
# When run with sudo, $HOME becomes /root, but the actual pialert install
# usually lives under the invoking (non-root) user's home directory.
# Resolve the real home directory of the user who ran sudo, if applicable.
if [[ -n "${SUDO_USER:-}" && "$SUDO_USER" != 'root' ]]; then
    INVOKING_USER="$SUDO_USER"
    REAL_HOME="$(getent passwd "$SUDO_USER" | cut -d: -f6)"
else
    INVOKING_USER='root'
    REAL_HOME='/root'
fi

PIALERT_HOME_DIR="${PIALERT_HOME_OVERRIDE:-${REAL_HOME}/pialert}"
NEW_INSTALL_DIR="/opt/pialert"
WEB_LINK="/var/www/html/pialert"
LOG_FILE="/tmp/pialert_home_to_opt_$(date +%Y%m%d_%H%M%S).log"
AUTO_YES=0
MOVED=0
BACKUP_ARCHIVE=""
LEGACY_LOG_LINKS=()

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
log()  { printf '[%s] %s\n' "$(date '+%H:%M:%S')" "$*" | tee -a "$LOG_FILE"; }
warn() { printf '[%s] WARNING: %s\n' "$(date '+%H:%M:%S')" "$*" | tee -a "$LOG_FILE" >&2; }
die()  { printf '[%s] ERROR: %s\n' "$(date '+%H:%M:%S')" "$*" | tee -a "$LOG_FILE" >&2; exit 1; }

on_error() {
    local status=$?
    warn "Migration stopped unexpectedly (exit $status)."
    [[ -n "$BACKUP_ARCHIVE" ]] && warn "Pre-migration backup: $BACKUP_ARCHIVE"
    [[ "$MOVED" -eq 1 ]] && warn "The installation has already been moved to $NEW_INSTALL_DIR."
    warn "See the migration log: $LOG_FILE"
    exit "$status"
}
trap on_error ERR

confirm() {
    local prompt="$1"
    if [[ "$AUTO_YES" -eq 1 ]]; then
        return 0
    fi
    read -r -p "$prompt [y/N] " reply
    [[ "$reply" =~ ^[Yy]$ ]]
}

require_root() {
    if [[ "$EUID" -ne 0 ]]; then
        die "This script must be run as root (use sudo). Re-run as: sudo $0"
    fi
}

usage() {
    cat <<EOF
Usage: sudo bash $0 [-y]

  -y    Accept the migration confirmation automatically.
  -h    Show this help.

For a non-standard source location:
  sudo PIALERT_HOME_OVERRIDE=/path/to/pialert bash $0
EOF
}

# ---------------------------------------------------------------------------
# Parse args
# ---------------------------------------------------------------------------
while getopts "yh" opt; do
    case "$opt" in
        y) AUTO_YES=1 ;;
        h) usage; exit 0 ;;
        *) usage; exit 1 ;;
    esac
done

# ---------------------------------------------------------------------------
# Step 0: Pre-flight checks
# ---------------------------------------------------------------------------
require_root
log "Starting Pi.Alert migration. Log: $LOG_FILE"

for command in awk chmod chgrp cmp cp crontab cut find getent grep install ln \
               mktemp mv pgrep python3 readlink rm sed stat tar; do
    command -v "$command" >/dev/null 2>&1 || die "Required command not found: $command"
done

if [[ ! -d "$PIALERT_HOME_DIR" ]]; then
    warn "Could not find existing installation at $PIALERT_HOME_DIR"
    log "Searching common locations for an existing pialert install..."

    FOUND_DIR=""
    for candidate in /home/*/pialert /root/pialert; do
        if [[ -d "$candidate" && -d "$candidate/config" && -d "$candidate/db" ]]; then
            FOUND_DIR="$candidate"
            break
        fi
    done

    if [[ -n "$FOUND_DIR" ]]; then
        log "Found candidate installation at: $FOUND_DIR"
        PIALERT_HOME_DIR="$FOUND_DIR"
    else
        die "Could not locate an existing Pi.Alert installation (checked $PIALERT_HOME_DIR and /home/*/pialert). If your install lives somewhere else, re-run as: sudo PIALERT_HOME_OVERRIDE=/path/to/pialert $0"
    fi
fi

[[ ! -L "$PIALERT_HOME_DIR" ]] || die "Source must not be a symbolic link: $PIALERT_HOME_DIR"
PIALERT_HOME_DIR="$(readlink -f "$PIALERT_HOME_DIR")"
[[ "$PIALERT_HOME_DIR" != "$NEW_INSTALL_DIR" ]] || die "Pi.Alert is already installed at $NEW_INSTALL_DIR."
[[ ! -e "$NEW_INSTALL_DIR" && ! -L "$NEW_INSTALL_DIR" ]] || die "Target already exists: $NEW_INSTALL_DIR"

for required in back/pialert.py config/pialert.conf db front install log; do
    [[ -e "$PIALERT_HOME_DIR/$required" ]] || \
        die "Source is not a complete Pi.Alert installation; missing: $required"
done

# Older Pi.Alert versions exposed their log files through symlinks below the
# PHP server directory. Record only the known links that currently resolve to
# this installation's log directory. Newer installations no longer use them.
LEGACY_SERVER_DIR="$PIALERT_HOME_DIR/front/php/server"
for log_name in pialert.vendors.log pialert.IP.log pialert.1.log \
                pialert.cleanup.log pialert.webservices.log \
                pialert.speedtest.log pialert.nmap.log usercron.log; do
    legacy_link="$LEGACY_SERVER_DIR/$log_name"
    if [[ -L "$legacy_link" ]] && \
       [[ "$(readlink -f "$legacy_link")" == "$PIALERT_HOME_DIR/log/$log_name" ]]; then
        LEGACY_LOG_LINKS+=("$log_name")
    fi
done

if [[ -e "$WEB_LINK" && ! -L "$WEB_LINK" ]]; then
    die "$WEB_LINK exists and is not a symbolic link; it will not be overwritten."
fi

log "Using existing installation at: $PIALERT_HOME_DIR"
log "Target installation path: $NEW_INSTALL_DIR"

# ---------------------------------------------------------------------------
# Step 1: Pause Pi.Alert (manual step - cannot be reliably automated via
# script since it goes through the web UI). We remind the user and require
# explicit confirmation before continuing.
# ---------------------------------------------------------------------------
cat <<EOF

============================================================
STEP 1: Pause Pi.Alert
============================================================
Before continuing, please:
  1. Open the Pi.Alert web interface
  2. Go to Settings -> Security -> Toggle "Pi.Alert" to paused
  3. Check the Status Box and make sure NO scan is currently active
     (wait for any running scan to finish)
============================================================
EOF

if ! confirm "Have you paused Pi.Alert and confirmed no scan is running?"; then
    die "Aborting: please pause Pi.Alert via the web UI first, then re-run this script."
fi

# Refuse to move while the scanner still reports an active run. The WebGUI
# pause marker is extended so cron cannot start a new scan during migration.
if [[ -e "$PIALERT_HOME_DIR/back/.scanning" ]]; then
    die "A scan marker still exists at $PIALERT_HOME_DIR/back/.scanning."
fi
PROCESS_LIST="$(mktemp /tmp/pialert-migration-processes.XXXXXX)"
if pgrep -af "$PIALERT_HOME_DIR/back/pialert" > "$PROCESS_LIST" 2>/dev/null; then
    warn "Active Pi.Alert processes:"
    while IFS= read -r process; do warn "  $process"; done < "$PROCESS_LIST"
    rm -f -- "$PROCESS_LIST"
    die "Stop all Pi.Alert processes before moving the installation."
fi
rm -f -- "$PROCESS_LIST"
printf '1440\n' > "$PIALERT_HOME_DIR/config/setting_stoppialert"

# Create a complete, timestamped archive. No older migration backup is reused
# or deleted, and no live SQLite files are copied back and forth.
BACKUP_ARCHIVE="/opt/pialert_home_backup_$(date +%Y%m%d_%H%M%S).tar"
SOURCE_PARENT="$(dirname "$PIALERT_HOME_DIR")"
SOURCE_NAME="$(basename "$PIALERT_HOME_DIR")"
log "Creating complete backup: $BACKUP_ARCHIVE"
tar --acls --xattrs -cpf "$BACKUP_ARCHIVE" -C "$SOURCE_PARENT" -- "$SOURCE_NAME"
[[ -s "$BACKUP_ARCHIVE" ]] || die "The backup archive is empty."
tar -tf "$BACKUP_ARCHIVE" >/dev/null || die "The backup archive cannot be read."

log "Moving $PIALERT_HOME_DIR to $NEW_INSTALL_DIR"
mv -- "$PIALERT_HOME_DIR" "$NEW_INSTALL_DIR"
MOVED=1

# Update only the active configuration. A copy beside it and the complete tar
# archive allow the previous value to be recovered without touching backups.
CONF_FILE="$NEW_INSTALL_DIR/config/pialert.conf"
CONF_BACKUP="$NEW_INSTALL_DIR/config/pialert.conf.before-home-migration"
cp -p -- "$CONF_FILE" "$CONF_BACKUP"

PATH_COUNT="$(grep -Ec '^[[:space:]]*PIALERT_PATH[[:space:]]*=' "$CONF_FILE" || true)"
if [[ "$PATH_COUNT" -gt 1 ]]; then
    die "pialert.conf contains more than one PIALERT_PATH assignment."
elif [[ "$PATH_COUNT" -eq 1 ]]; then
    sed -i -E "s|^[[:space:]]*PIALERT_PATH[[:space:]]*=.*$|PIALERT_PATH               = '$NEW_INSTALL_DIR'|" "$CONF_FILE"
else
    printf "\nPIALERT_PATH               = '%s'\n" "$NEW_INSTALL_DIR" >> "$CONF_FILE"
fi
log "Updated PIALERT_PATH in $CONF_FILE"

escape_sed_pattern() {
    printf '%s' "$1" | sed 's/[][\\.^$*|]/\\&/g'
}
OLD_PATH_PATTERN="$(escape_sed_pattern "$PIALERT_HOME_DIR")"

replace_path_in_file() {
    local file=$1
    [[ -f "$file" ]] || return 0
    if grep -Fq "$PIALERT_HOME_DIR" "$file" || \
       grep -Fq '$HOME/pialert/' "$file" || grep -Fq '~/pialert/' "$file"; then
        sed -i \
            -e "s|$OLD_PATH_PATTERN|$NEW_INSTALL_DIR|g" \
            -e 's|\$HOME/pialert/|/opt/pialert/|g' \
            -e 's|~/pialert/|/opt/pialert/|g' \
            "$file"
    fi
}

# Preserve each user's complete crontab and replace only Pi.Alert path text.
update_user_crontab() {
    local cron_user=$1 cron_before cron_after
    getent passwd "$cron_user" >/dev/null 2>&1 || return 0
    cron_before="$(mktemp /tmp/pialert-cron-before.XXXXXX)"
    cron_after="$(mktemp /tmp/pialert-cron-after.XXXXXX)"
    if ! crontab -u "$cron_user" -l > "$cron_before" 2>/dev/null; then
        rm -f -- "$cron_before" "$cron_after"
        return 0
    fi
    cp -- "$cron_before" "$cron_after"
    replace_path_in_file "$cron_after"
    if ! cmp -s "$cron_before" "$cron_after"; then
        crontab -u "$cron_user" "$cron_after"
        log "Updated Pi.Alert paths in crontab for $cron_user"
    fi
    rm -f -- "$cron_before" "$cron_after"
}

SOURCE_OWNER="$(stat -c '%U' "$NEW_INSTALL_DIR")"
update_user_crontab "$SOURCE_OWNER"
if [[ "$INVOKING_USER" != "$SOURCE_OWNER" ]]; then
    update_user_crontab "$INVOKING_USER"
fi
if [[ "$SOURCE_OWNER" != 'root' && "$INVOKING_USER" != 'root' ]]; then
    update_user_crontab root
fi

# Update only known Pi.Alert integration files that can contain the absolute
# installation path. Program files and user data are not rewritten globally.
replace_path_in_file "$NEW_INSTALL_DIR/install/pialert-cli.autocomplete"
replace_path_in_file /etc/bash_completion.d/pialert-cli
replace_path_in_file /usr/share/bash-completion/completions/pialert-cli

update_sudoers_file() {
    local file=$1 temporary
    [[ -f "$file" ]] || return 0
    temporary="$(mktemp /tmp/pialert-sudoers.XXXXXX)"
    cp -- "$file" "$temporary"
    replace_path_in_file "$temporary"
    if ! cmp -s "$file" "$temporary"; then
        if command -v visudo >/dev/null 2>&1; then
            visudo -cf "$temporary" >/dev/null
        fi
        install -o root -g root -m 0440 "$temporary" "$file"
        log "Updated sudoers file: $file"
    fi
    rm -f -- "$temporary"
}

update_sudoers_file /etc/sudoers.d/pialert-backend
update_sudoers_file /etc/sudoers.d/pialert-frontend

log "Recreating WebRoot symlink: $WEB_LINK -> $NEW_INSTALL_DIR/front"
ln -sfn "$NEW_INSTALL_DIR/front" "$WEB_LINK"
[[ "$(readlink -f "$WEB_LINK")" == "$NEW_INSTALL_DIR/front" ]] || \
    die "WebRoot symlink verification failed."

# Absolute log symlinks from older releases still point to the former home
# directory after mv. Recreate exactly the links detected before the move.
for log_name in "${LEGACY_LOG_LINKS[@]}"; do
    log_target="$NEW_INSTALL_DIR/log/$log_name"
    log_link="$NEW_INSTALL_DIR/front/php/server/$log_name"
    [[ -f "$log_target" ]] || die "Legacy log link target is missing: $log_target"
    ln -sfn "$log_target" "$log_link"
    [[ "$(readlink -f "$log_link")" == "$log_target" ]] || \
        die "Legacy log symlink verification failed: $log_link"
    log "Recreated legacy log symlink: $log_link -> $log_target"
done

# Preserve existing ownership and file modes while restoring the groups and
# directory access required by Pi.Alert after a possible cross-filesystem move.
chgrp -R www-data "$NEW_INSTALL_DIR/db" "$NEW_INSTALL_DIR/config"
chmod 1775 "$NEW_INSTALL_DIR/config"
[[ -d "$NEW_INSTALL_DIR/db/temp" ]] && chmod 0775 "$NEW_INSTALL_DIR/db/temp"
[[ -d "$NEW_INSTALL_DIR/front/reports" ]] && chgrp -R www-data "$NEW_INSTALL_DIR/front/reports"
[[ -d "$NEW_INSTALL_DIR/front/php/tmp" ]] && chgrp -R www-data "$NEW_INSTALL_DIR/front/php/tmp"
[[ -d "$NEW_INSTALL_DIR/front/satellites" ]] && chgrp -R www-data "$NEW_INSTALL_DIR/front/satellites"

if [[ -f "$NEW_INSTALL_DIR/back/validate_pialert_config.py" ]]; then
    log "Validating moved pialert.conf"
    python3 "$NEW_INSTALL_DIR/back/validate_pialert_config.py" \
        "$CONF_FILE" --expected-pialert-path "$NEW_INSTALL_DIR"
else
    python3 - "$CONF_FILE" <<'PY'
import ast
import sys
with open(sys.argv[1], 'r') as handle:
    ast.parse(handle.read(), filename=sys.argv[1], mode='exec')
PY
    log "Validated Python syntax of moved pialert.conf"
fi

for file in /etc/sudoers.d/pialert-backend /etc/sudoers.d/pialert-frontend \
            /etc/bash_completion.d/pialert-cli \
            /usr/share/bash-completion/completions/pialert-cli; do
    if [[ -f "$file" ]] && grep -Fq "$PIALERT_HOME_DIR" "$file"; then
        die "Old Pi.Alert path remains in $file"
    fi
done

trap - ERR
log "Migration completed successfully."

cat <<EOF

Pi.Alert was moved successfully.

  Installation: $NEW_INSTALL_DIR
  WebRoot link: $WEB_LINK -> $NEW_INSTALL_DIR/front
  Backup:        $BACKUP_ARCHIVE
  Config backup: $CONF_BACKUP
  Log:           $LOG_FILE

Pi.Alert remains paused for safety. Open the WebGUI, verify devices, history,
configuration, scheduled scans, and notifications, then resume Pi.Alert under
Settings -> Security. Keep the backup until the migration has been verified.
EOF
