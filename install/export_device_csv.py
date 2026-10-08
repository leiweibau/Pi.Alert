#!/usr/bin/env python3
"""Export all MAC rows and their logical identities from one SQLite snapshot."""
import argparse
import csv
import io
from pathlib import Path
import os
import sqlite3
import tempfile
import zipfile


def export(database, archive):
    connection = sqlite3.connect(Path(database).resolve().as_uri() + '?mode=ro', uri=True)
    temporary = None
    try:
        connection.execute('BEGIN')
        handle, temporary = tempfile.mkstemp(prefix='.pialertcsv-', suffix='.zip', dir=Path(archive).parent)
        os.close(handle)
        with zipfile.ZipFile(temporary, 'w', zipfile.ZIP_DEFLATED) as output:
            for table, filename in [('Devices','devices.csv'),('Services','services.csv'),
                                    ('ICMP_Mon','icmphosts.csv'),('LogicalDevices','logical_devices.csv')]:
                cursor = connection.execute('SELECT * FROM "' + table + '"')
                buffer = io.StringIO(newline='')
                writer = csv.writer(buffer)
                writer.writerow([column[0] for column in cursor.description])
                writer.writerows(cursor)
                output.writestr(filename, buffer.getvalue())
        connection.commit()
        os.replace(temporary, archive)
        temporary = None
    finally:
        connection.close()
        if temporary is not None:
            os.unlink(temporary)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('database')
    parser.add_argument('archive')
    args = parser.parse_args()
    export(args.database, args.archive)
