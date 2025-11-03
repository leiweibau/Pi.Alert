#!/bin/bash

# Files
OLD_DB="pialert_old.db"
NEW_DB="pialert_new.db"
TABLES=("Sessions" "Devices" "Events")

for TABLE in "${TABLES[@]}"; do
    echo "Copying table $TABLE..."

    # Columns in new.db
    NEW_COLUMNS=$(sqlite3 "$NEW_DB" "PRAGMA table_info($TABLE);" | awk -F'|' '{print $2}' | paste -sd "," -)

    if [ "$TABLE" == "Devices" ]; then
        # Insert only if dev_MAC not exists
        sqlite3 "$NEW_DB" <<EOF
ATTACH DATABASE '$OLD_DB' AS old;

INSERT INTO $TABLE ($NEW_COLUMNS)
SELECT $NEW_COLUMNS FROM old.$TABLE AS old_table
WHERE NOT EXISTS (
    SELECT 1 FROM $TABLE AS new_table
    WHERE new_table.dev_MAC = old_table.dev_MAC
);

DETACH DATABASE old;
EOF
    else
        # Generic copy for other tables
        sqlite3 "$NEW_DB" <<EOF
ATTACH DATABASE '$OLD_DB' AS old;

INSERT INTO $TABLE ($NEW_COLUMNS)
SELECT $NEW_COLUMNS FROM old.$TABLE AS old_table
WHERE NOT EXISTS (
    SELECT 1 FROM $TABLE AS new_table
    WHERE $(echo $NEW_COLUMNS | awk -F, '{for(i=1;i<=NF;i++){printf "old_table.%s=new_table.%s",$i,$i; if(i<NF){printf " AND "}}}')
);

DETACH DATABASE old;
EOF
    fi
done

echo "Transfer completed."
