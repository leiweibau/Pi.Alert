#!/usr/bin/env python3
#
#===============================================================================
# IMPORTS
#===============================================================================
from __future__ import print_function
from requests.packages.urllib3.exceptions import InsecureRequestWarning
from time import sleep, time, strftime
from base64 import b64encode
from urllib.parse import urlparse
from pathlib import Path
from datetime import datetime, timedelta, timezone
from config_validation import ConfigValidationError, load_pialert_config
import sys, subprocess, os, re, datetime, sqlite3, socket, io, requests, time, pwd, glob, ipaddress, ssl, json, tzlocal, asyncio, aiohttp, threading
import fcntl
import xml.etree.ElementTree as ET
import logging
from logging.handlers import RotatingFileHandler

#===============================================================================
# CONFIG CONSTANTS
#===============================================================================
PIALERT_BACK_PATH = os.path.dirname(os.path.abspath(__file__))
PIALERT_PATH = PIALERT_BACK_PATH + "/.."
PIALERT_DBTOOLS_FILE = PIALERT_PATH + "/db/pialert_tools.db"
STATUS_FILE_SCAN = PIALERT_BACK_PATH + "/.scanning_tools"
NMAP_BINARY = "/usr/bin/nmap"
SUDO_BINARY = "/usr/bin/sudo"
NMAP_TCP_HOST_TIMEOUT = "10m"
NMAP_UDP_HOST_TIMEOUT = "5m"
NMAP_TCP_PROCESS_TIMEOUT_SECONDS = 660
NMAP_UDP_PROCESS_TIMEOUT_SECONDS = 360
NMAP_STALE_JOB_MINUTES = 40
NMAP_MAX_ATTEMPTS = 3

if (sys.version_info > (3,0)):
    exec(open(PIALERT_PATH + "/config/version.conf").read())
else:
    execfile(PIALERT_PATH + "/config/version.conf")
try:
    globals().update(load_pialert_config(
        PIALERT_PATH + "/config/pialert.conf", PIALERT_PATH))
except ConfigValidationError as exc:
    print("[Config] Invalid configuration: {}".format(exc), file=sys.stderr)
    raise SystemExit(1)

#===============================================================================
# MAIN
#===============================================================================
def main():
    global startTime
    global sql_connection_tools
    global sql_tools
    global sql_connection
    global sql
    global cycle
    global log_timestamp

    # Initialize global variables
    log_timestamp  = datetime.datetime.now()

    # DB
    sql_connection       = None
    sql                  = None
    sql_connection_tools = None
    sql_tools            = None
    sqlite3.register_adapter(datetime.datetime, adapt_datetime)

    # Timestamp
    startTime = datetime.datetime.now()
    startTime = startTime.replace (second=0, microsecond=0)

    # print('Timestamp:', startTime )

    # Check parameters
    if len(sys.argv) != 2 :
        print('usage pialert_tools speedtest | nmap_scan | cleanup' )
        return
    cycle = str(sys.argv[1])

    if cycle == 'speedtest':
        res = speedtest()
    elif cycle in ('nmap', 'nmap_scan'):
        res = nmap_scan()
    elif cycle == 'cleanup':
        res = cleanup_database_tools()
    else:
        return 0
    return res

#===============================================================================
# Set Env (Userpermissions DB-file)
#===============================================================================
def adapt_datetime(dt):
    return dt.isoformat().replace('T', ' ')

# ------------------------------------------------------------------------------
def get_username():
    return pwd.getpwuid(os.getuid())[0]

# ------------------------------------------------------------------------------
def set_db_file_permissions():
    global PIALERT_DBTOOLS_FILE

    print_log(f"\nPrepare Scan...")
    print_log(f"    Force file permissions on Pi.Alert db...")

    # Set permissions Experimental
    os.system("sudo /usr/bin/chown " + get_username() + ":www-data " + PIALERT_DBTOOLS_FILE + "*")
    os.system("sudo /usr/bin/chmod 775 " + PIALERT_DBTOOLS_FILE + "*")

    # Get permissions
    fileinfo = Path(PIALERT_DBTOOLS_FILE)
    file_stat = fileinfo.stat()
    print_log(f"        DB permission mask: {oct(file_stat.st_mode)[-3:]}")
    print_log(f"        DB Owner and Group: {fileinfo.owner()}:{fileinfo.group()}")

# ------------------------------------------------------------------------------
def set_reports_file_permissions():
    os.system("sudo chown -R " + get_username() + ":www-data " + REPORTPATH_WEBGUI)
    os.system("sudo chmod -R 775 " + REPORTPATH_WEBGUI)

#===============================================================================
# Save logs
#===============================================================================


def write_cycle_logs_to_tables(log_dir=PIALERT_PATH + "/log"):
    global sql_connection_tools
    global sql_tools
    global startTime

    print_log("Save Log in Tools-DB")
    LOGFILE_TABLE_MAP = {
        "pialert.speedtest.log": "Log_History_Speedtest",
    }

    CYCLE_LOGFILES = {
        "speedtest": [
            "pialert.speedtest.log"
        ],
    }

    logfiles = CYCLE_LOGFILES.get(cycle, [])
    if not logfiles:
        return

    for logfile in logfiles:
        table = LOGFILE_TABLE_MAP.get(logfile)
        if not table:
            continue  # kein Ziel definiert → bewusst ignorieren

        if logfile == "pialert.webservices.log":
            if startTime.minute % 10 != 0:
                continue

        logfile_path = os.path.join(log_dir, logfile)
        if not os.path.isfile(logfile_path):
            continue

        with open(logfile_path, "r", encoding="utf-8", errors="replace") as f:
            content = f.read()
        # print(content)

        sql_tools.execute(
            f"""
            INSERT INTO {table} (ScanDate, Logfile)
            VALUES (?, ?)
            """,
            (startTime, content)
        )

    sql_connection_tools.commit()

#===============================================================================
# Main Tasks
#===============================================================================

def ensure_nmap_queue_schema(connection):
    connection.executescript("""
        CREATE TABLE IF NOT EXISTS Tools_Nmap_Queue (
            queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_mac TEXT NOT NULL COLLATE NOCASE,
            target_ip TEXT NOT NULL,
            scan_type TEXT NOT NULL DEFAULT 'detail',
            source TEXT NOT NULL DEFAULT 'manual',
            status TEXT NOT NULL DEFAULT 'queued',
            requested_at TEXT NOT NULL,
            started_at TEXT,
            completed_at TEXT,
            attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT,
            result_id INTEGER,
            UNIQUE (device_mac, scan_type)
        );
        CREATE TABLE IF NOT EXISTS Tools_Nmap_Schedule (
            schedule_id INTEGER PRIMARY KEY AUTOINCREMENT,
            device_mac TEXT NOT NULL COLLATE NOCASE UNIQUE,
            scan_type TEXT NOT NULL DEFAULT 'detail',
            interval_minutes INTEGER NOT NULL,
            next_run_at TEXT,
            enabled INTEGER NOT NULL DEFAULT 0,
            last_queued_at TEXT
        );
    """)


def enqueue_due_nmap_schedules(connection, now=None):
    """Prepared scheduling hook. It is intentionally not called anywhere yet."""
    now = now or datetime.datetime.now()
    now_text = adapt_datetime(now)
    due_rows = connection.execute(
        """SELECT device_mac, scan_type, interval_minutes
           FROM Tools_Nmap_Schedule
           WHERE enabled = 1
             AND next_run_at IS NOT NULL
             AND datetime(next_run_at) <= datetime(?)""",
        (now_text,)
    ).fetchall()
    queued = 0
    for row in due_rows:
        inserted = connection.execute(
            """INSERT OR IGNORE INTO Tools_Nmap_Queue
               (device_mac, target_ip, scan_type, source, status, requested_at, attempts)
               VALUES (?, '', ?, 'scheduled', 'queued', ?, 0)""",
            (row['device_mac'], row['scan_type'], now_text)
        )
        queued += max(inserted.rowcount, 0)
        next_run = now + datetime.timedelta(minutes=max(int(row['interval_minutes']), 1))
        connection.execute(
            """UPDATE Tools_Nmap_Schedule
               SET last_queued_at = ?, next_run_at = ?
               WHERE device_mac = ? COLLATE NOCASE""",
            (now_text, adapt_datetime(next_run), row['device_mac'])
        )
    connection.commit()
    return queued


def build_nmap_detail_commands(target_ip, effective_uid=None):
    parsed_ip = ipaddress.ip_address(target_ip)
    ip_arguments = ['-6'] if parsed_ip.version == 6 else []
    tcp_command = [NMAP_BINARY] + ip_arguments + [
        '-sS', '-sV', '--top-ports', '1000',
        '--host-timeout', NMAP_TCP_HOST_TIMEOUT,
        '-oX', '-', str(parsed_ip)
    ]
    udp_command = [NMAP_BINARY] + ip_arguments + [
        '-sU', '-sV', '-T4', '--version-intensity', '0',
        '--top-ports', '140',
        '--host-timeout', NMAP_UDP_HOST_TIMEOUT,
        '-oX', '-', str(parsed_ip)
    ]
    uid = os.geteuid() if effective_uid is None else effective_uid
    if uid != 0:
        tcp_command = [SUDO_BINARY, '-n', '--'] + tcp_command
        udp_command = [SUDO_BINARY, '-n', '--'] + udp_command
    return [
        ('TCP', tcp_command, NMAP_TCP_PROCESS_TIMEOUT_SECONDS),
        ('UDP', udp_command, NMAP_UDP_PROCESS_TIMEOUT_SECONDS),
    ]


def parse_nmap_xml(xml_output):
    root = ET.fromstring(xml_output)
    ports = []
    for port_node in root.findall('.//port'):
        state_node = port_node.find('state')
        state = state_node.get('state', '') if state_node is not None else ''
        if state not in ('open', 'open|filtered'):
            continue
        protocol = port_node.get('protocol', '')
        port_id = port_node.get('portid', '')
        service_node = port_node.find('service')
        service_parts = []
        if service_node is not None:
            for attribute in ('name', 'product', 'version', 'extrainfo'):
                value = service_node.get(attribute, '').strip()
                if value:
                    service_parts.append(value)
        service = ' '.join(service_parts) or 'unknown'
        clean_values = [port_id, protocol, state, service]
        clean_values = [value.replace('###', ' ').replace('\r', ' ').replace('\n', ' ').strip() for value in clean_values]
        ports.append({
            'port': clean_values[0],
            'protocol': clean_values[1],
            'status': clean_values[2],
            'service': clean_values[3],
        })
    ports.sort(key=lambda item: (
        item['protocol'],
        0 if item['port'].isdigit() else 1,
        int(item['port']) if item['port'].isdigit() else item['port']
    ))
    return ports


def serialize_nmap_ports(ports):
    return '\n'.join(
        '{port}###{protocol}###{status}###{service}'.format(**port)
        for port in ports
    )


def _open_sqlite(path):
    connection = sqlite3.connect(path, timeout=5)
    connection.execute('PRAGMA busy_timeout = 5000')
    connection.row_factory = sqlite3.Row
    return connection


def _claim_next_nmap_job(connection):
    connection.execute('BEGIN IMMEDIATE')
    try:
        stale_modifier = '-{} minutes'.format(NMAP_STALE_JOB_MINUTES)
        connection.execute(
            """UPDATE Tools_Nmap_Queue
               SET status = 'queued', started_at = NULL,
                   last_error = 'Recovered stale worker job'
               WHERE status = 'running'
                 AND datetime(started_at) <= datetime('now', ?)""",
            (stale_modifier,)
        )
        row = connection.execute(
            """SELECT * FROM Tools_Nmap_Queue
               WHERE status = 'queued'
               ORDER BY queue_id ASC LIMIT 1"""
        ).fetchone()
        if row is None:
            connection.commit()
            return None
        connection.execute(
            """UPDATE Tools_Nmap_Queue
               SET status = 'running', started_at = datetime('now', 'localtime'),
                   attempts = attempts + 1, last_error = NULL
               WHERE queue_id = ? AND status = 'queued'""",
            (row['queue_id'],)
        )
        claimed = connection.execute(
            'SELECT * FROM Tools_Nmap_Queue WHERE queue_id = ?',
            (row['queue_id'],)
        ).fetchone()
        connection.commit()
        return claimed
    except Exception:
        connection.rollback()
        raise


def _get_nmap_device(device_mac):
    connection = _open_sqlite(DB_PATH)
    try:
        return connection.execute(
            """SELECT dev_MAC, dev_LastIP, dev_Name
               FROM Devices WHERE dev_MAC = ? COLLATE NOCASE LIMIT 1""",
            (device_mac,)
        ).fetchone()
    finally:
        connection.close()


def _write_nmap_journal(log_string, additional_info):
    connection = None
    try:
        connection = _open_sqlite(DB_PATH)
        connection.execute(
            """INSERT INTO pialert_journal
               (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
               VALUES (?, 'a_002', 'nmap_scan', ?, '', ?)""",
            (adapt_datetime(datetime.datetime.now()), log_string, additional_info)
        )
        connection.commit()
    except sqlite3.Error as error:
        print('Unable to write Nmap journal entry: {}'.format(error), file=sys.stderr)
    finally:
        if connection is not None:
            connection.close()


def _store_nmap_success(connection, job, target_ip, ports, duration_seconds, return_code):
    scan_time = adapt_datetime(datetime.datetime.now())
    scan_result = serialize_nmap_ports(ports)
    connection.execute('BEGIN IMMEDIATE')
    try:
        result = connection.execute(
            """INSERT INTO Tools_Nmap_ManScan
               (scan_date, scan_target, scan_type, scan_result,
                reserve_a, reserve_b, reserve_c, reserve_d)
               VALUES (?, ?, 'detail', ?, ?, ?, ?, ?)""",
            (scan_time, target_ip, scan_result, int(duration_seconds),
             job['device_mac'], str(job['queue_id']), 'completed:{}'.format(return_code))
        )
        connection.execute(
            """UPDATE Tools_Nmap_Queue
               SET status = 'completed', target_ip = ?, completed_at = ?,
                   result_id = ?, last_error = NULL
               WHERE queue_id = ?""",
            (target_ip, scan_time, result.lastrowid, job['queue_id'])
        )
        connection.commit()
    except Exception:
        connection.rollback()
        raise


def _handle_nmap_failure(connection, job, error_message):
    error_message = str(error_message).replace('\x00', '')[:1000]
    if int(job['attempts']) < NMAP_MAX_ATTEMPTS:
        connection.execute(
            """UPDATE Tools_Nmap_Queue
               SET status = 'queued', started_at = NULL, last_error = ?
               WHERE queue_id = ?""",
            (error_message, job['queue_id'])
        )
        connection.commit()
        return False
    connection.execute(
        """UPDATE Tools_Nmap_Queue
           SET status = 'failed', completed_at = datetime('now', 'localtime'),
               last_error = ? WHERE queue_id = ?""",
        (error_message, job['queue_id'])
    )
    connection.commit()
    _write_nmap_journal('LogStr_0262', '{} / {}'.format(job['device_mac'], error_message))
    return True


def _finalize_nmap_job(connection, queue_id):
    reporter = os.path.join(PIALERT_BACK_PATH, 'pialert_reporting_test.py')
    try:
        completed = subprocess.run(
            [sys.executable, reporter, 'nmap_scan_complete', str(queue_id)],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            timeout=120,
            check=False,
            shell=False
        )
        if completed.returncode != 0:
            print('Nmap completion notification returned {}.'.format(completed.returncode), file=sys.stderr)
    except (OSError, subprocess.TimeoutExpired) as error:
        print('Nmap completion notification failed: {}'.format(error), file=sys.stderr)
    connection.execute('DELETE FROM Tools_Nmap_Queue WHERE queue_id = ?', (queue_id,))
    connection.commit()
    print('Queue job {} finalized and removed.'.format(queue_id), flush=True)


def _process_nmap_job(connection, job):
    device = _get_nmap_device(job['device_mac'])
    if device is None:
        raise RuntimeError('Device no longer exists')
    target_ip = str(device['dev_LastIP'] or '').strip()
    try:
        ipaddress.ip_address(target_ip)
    except ValueError:
        raise RuntimeError('Device has no valid current IP address')
    if not os.path.isfile(NMAP_BINARY) or not os.access(NMAP_BINARY, os.X_OK):
        raise RuntimeError('Nmap executable is unavailable')
    if os.geteuid() != 0 and (not os.path.isfile(SUDO_BINARY) or not os.access(SUDO_BINARY, os.X_OK)):
        raise RuntimeError('Non-interactive sudo is unavailable')

    commands = build_nmap_detail_commands(target_ip)
    print('Starting queue job {} for {} ({})'.format(
        job['queue_id'], job['device_mac'], target_ip), flush=True)
    started = time.monotonic()
    ports = []
    for scan_label, command, process_timeout in commands:
        print('{} command: {}'.format(scan_label, ' '.join(command)), flush=True)
        try:
            completed = subprocess.run(
                command,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                timeout=process_timeout,
                check=False,
                shell=False
            )
        except subprocess.TimeoutExpired:
            raise RuntimeError('{} Nmap process timeout exceeded'.format(scan_label))
        if completed.returncode != 0:
            error_text = (completed.stderr or completed.stdout or 'Nmap failed').strip()
            raise RuntimeError('{} Nmap returned {}: {}'.format(
                scan_label, completed.returncode, error_text[:800]))
        try:
            ports.extend(parse_nmap_xml(completed.stdout))
        except ET.ParseError as error:
            raise RuntimeError('Invalid {} Nmap XML output: {}'.format(scan_label, error))

    duration = max(int(time.monotonic() - started), 0)

    _store_nmap_success(connection, job, target_ip, ports, duration, 0)
    print('Queue job {} completed in {} seconds (TCP: {}, UDP: {}).'.format(
        job['queue_id'], duration,
        sum(1 for port in ports if port['protocol'] == 'tcp'),
        sum(1 for port in ports if port['protocol'] == 'udp')), flush=True)
    _write_nmap_journal(
        'LogStr_0261',
        '{} / {} / TCP: {} / UDP: {}'.format(
            job['device_mac'], target_ip,
            sum(1 for port in ports if port['protocol'] == 'tcp'),
            sum(1 for port in ports if port['protocol'] == 'udp')
        )
    )


def nmap_scan():
    print('\n{} Pi.Alert Nmap queue worker'.format(
        datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')), flush=True)
    lock_handle = open(STATUS_FILE_SCAN, 'a+')
    try:
        try:
            fcntl.flock(lock_handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            print('Nmap queue worker is already running.', flush=True)
            return 0

        connection = _open_sqlite(PIALERT_DBTOOLS_FILE)
        try:
            ensure_nmap_queue_schema(connection)
            while True:
                terminal_job = connection.execute(
                    """SELECT * FROM Tools_Nmap_Queue
                       WHERE status IN ('completed', 'failed')
                       ORDER BY queue_id ASC LIMIT 1"""
                ).fetchone()
                if terminal_job is not None:
                    _finalize_nmap_job(connection, terminal_job['queue_id'])
                    continue

                job = _claim_next_nmap_job(connection)
                if job is None:
                    print('No queued Nmap jobs.', flush=True)
                    break
                try:
                    _process_nmap_job(connection, job)
                    _finalize_nmap_job(connection, job['queue_id'])
                except Exception as error:
                    print('Nmap queue job {} failed: {}'.format(job['queue_id'], error), file=sys.stderr)
                    if _handle_nmap_failure(connection, job, error):
                        _finalize_nmap_job(connection, job['queue_id'])
        finally:
            connection.close()
    finally:
        fcntl.flock(lock_handle.fileno(), fcntl.LOCK_UN)
        lock_handle.close()
    return 0

def speedtest(retries=3):
    import logging
    import subprocess
    import json

    LOG_FILE = LOG_PATH + "/pialert.speedtest.log"

    header = (
        "\nPi.Alert v" + VERSION_DATE + " (Speedtest)\n"
        "---------------------------------------------------------\n"
        "\n"
    )

    with open(LOG_FILE, "w") as f:
        f.write(header)

    logger = logging.getLogger("pialert_speedtest")
    logger.setLevel(logging.INFO)

    if logger.hasHandlers():
        logger.handlers.clear()

    handler = logging.FileHandler(LOG_FILE, mode='a')
    formatter = logging.Formatter(
        "%(asctime)s - %(levelname)s - %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S"
    )
    handler.setFormatter(formatter)
    logger.addHandler(handler)

    def logprint(msg):
        print(msg)
        logger.info(msg)

    command = ["sudo", PIALERT_BACK_PATH + "/speedtest/speedtest",
               "--accept-license", "--accept-gdpr", "-p", "no", "-f", "json"]

    logger.info("Speedtest Launched")
    logger.info(f"Retries left: {retries}")

    process = subprocess.Popen(
        command,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True
    )

    output_lines = []
    error_lines = []

    for line in process.stdout:
        line = line.rstrip()
        if line:
            logprint(f"[STDOUT] {line}")
            output_lines.append(line)

    process.wait()

    for line in process.stderr:
        line = line.rstrip()
        if line:
            logprint(f"[STDERR] {line}")
            error_lines.append(line)

    # Prüfen, ob Binary fehlgeschlagen ist
    if process.returncode != 0:
        logprint(f"Speedtest Binary returned error code: {process.returncode}")
        if retries > 0:
            logprint(f"Try again. Remaining attempts: {retries}")
            return speedtest(retries=retries-1)
        else:
            logprint("Maximum number of attempts reached. Abort.")
            return 1

    # JSON aus stdout zusammensetzen
    json_text = "\n".join(output_lines)

    try:
        result = json.loads(json_text)
    except json.JSONDecodeError as e:
        logprint(f"JSON parsing error: {e}")
        return 1

    speedtest_isp = result['isp']
    speedtest_server = f"{result['server']['name']} ({result['server']['location']}) ({result['server']['host']})"
    speedtest_ping = result['ping']['latency']
    speedtest_down = round(result['download']['bandwidth'] / 125000, 2)
    speedtest_up = round(result['upload']['bandwidth'] / 125000, 2)

    speedtest_output = (
        f"    ISP:            {speedtest_isp}\n"
        f"    Server:         {speedtest_server}\n\n"
        f"    Ping:           {speedtest_ping} ms\n"
        f"    Download Speed: {speedtest_down} Mbps\n"
        f"    Upload Speed:   {speedtest_up} Mbps\n"
    )

    for line in speedtest_output.split("\n"):
        if line.strip():
            logprint(line)

    logger.info("Speedtest successfully completed.\n\n")

    # Insert in db
    speedtest_db_output = speedtest_output.replace("\n", "<br>")

    openDB_tools()
    sql_tools.execute("""INSERT INTO Tools_Speedtest_History (speed_date, speed_isp, speed_server, speed_ping, speed_down, speed_up)
                         VALUES (?, ?, ?, ?, ?, ?) """,
                      (startTime, speedtest_isp, speedtest_server, speedtest_ping, speedtest_down, speedtest_up))
    closeDB_tools()

    openDB()
    sql.execute("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                   VALUES (?, 'c_002', 'cronjob', 'LogStr_0255', '', ?) """,
                (startTime, speedtest_db_output))

    closeDB()

    sys.stdout.flush()
    sys.stderr.flush()

    # Save Log to ToolsDB
    openDB_tools()
    write_cycle_logs_to_tables()
    closeDB_tools()
    
    return 0

#===============================================================================
# Cleanup Tasks
#===============================================================================
def cleanup_database_tools():
    openDB_tools()
    # print('\nCleanup tables, up to the lastest ' + str("180") + ' days:')

    print('    Nmap Scan Results (180)')
    sql_tools.execute("DELETE FROM Tools_Nmap_ManScan WHERE scan_date <= date('now', '-" + str("180") + " day')")

    print('    Speedtest_History (180)')
    sql_tools.execute("DELETE FROM Tools_Speedtest_History WHERE speed_date <= date('now', '-" + str("180") + " day')")

    print('    Log_History_Scan (4)')
    sql_tools.execute("DELETE FROM Log_History_Scan WHERE ScanDate <= date('now', '-" + str("4") + " day')")

    print('    Log_History_Cleanup (16)')
    sql_tools.execute("DELETE FROM Log_History_Cleanup WHERE ScanDate <= date('now', '-" + str("16") + " day')")

    print('    Log_History_Vendors (16)')
    sql_tools.execute("DELETE FROM Log_History_Vendors WHERE ScanDate <= date('now', '-" + str("16") + " day')")

    print('    Log_History_WebServices (4)')
    sql_tools.execute("DELETE FROM Log_History_WebServices WHERE ScanDate <= date('now', '-" + str("4") + " day')")

    print('    Log_History_InternetIP (4)')
    sql_tools.execute("DELETE FROM Log_History_InternetIP WHERE ScanDate <= date('now', '-" + str("4") + " day')")

    print('    Log_History_Speedtest (7)')
    sql_tools.execute("DELETE FROM Log_History_Speedtest WHERE ScanDate <= date('now', '-" + str("7") + " day')")

    print('\nShrink Tools-Database...')
    sql_tools.execute("VACUUM;")
    closeDB_tools()

    openDB()
    sql.execute("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                    VALUES (?, 'c_010', 'cronjob', 'LogStr_0101', '', 'Cleanup DB_Tools') """, (startTime,))
    closeDB()
    return 0

#-------------------------------------------------------------------------------
def SafeParseGlobalBool(boolVariable):
    if boolVariable in globals():
        return eval(boolVariable)
    return False

#===============================================================================
# DB
#===============================================================================
def openDB_tools():
    global sql_connection_tools
    global sql_tools

    # Check if DB is open
    if sql_connection_tools != None :
        return

    # Log    
    print_log ('Opening DB...')

    # Open DB and Cursor
    sql_connection_tools = sqlite3.connect (PIALERT_DBTOOLS_FILE, isolation_level=None)
    sql_connection_tools.execute('pragma journal_mode=wal') #
    sql_connection_tools.text_factory = str
    sql_connection_tools.row_factory = sqlite3.Row
    sql_tools = sql_connection_tools.cursor()

#-------------------------------------------------------------------------------
def closeDB_tools():
    global sql_connection_tools
    global sql_tools

    # Check if DB is open
    if sql_connection_tools == None :
        return

    # Log    
    print_log ('Closing DB...')

    # Close DB
    sql_connection_tools.commit()
    sql_connection_tools.close()
    sql_connection_tools = None    

#-------------------------------------------------------------------------------
def openDB():
    global sql_connection
    global sql

    # Check if DB is open
    if sql_connection != None :
        return

    # Log    
    print_log ('Opening DB...')

    # Open DB and Cursor
    sql_connection = sqlite3.connect (DB_PATH, isolation_level=None)
    sql_connection.execute('pragma journal_mode=wal') #
    sql_connection.text_factory = str
    sql_connection.row_factory = sqlite3.Row
    sql = sql_connection.cursor()

#-------------------------------------------------------------------------------
def closeDB():
    global sql_connection
    global sql

    # Check if DB is open
    if sql_connection == None :
        return

    # Log    
    print_log ('Closing DB...')

    # Close DB
    sql_connection.commit()
    sql_connection.close()
    sql_connection = None   

#===============================================================================
# UTIL
#===============================================================================
def print_log (pText):
    global log_timestamp

    # Check LOG actived
    if not PRINT_LOG :
        return

    # Current Time    
    log_timestamp2 = datetime.datetime.now()

    # Print line + time + elapsed time + text
    print('--------------------> ',
        log_timestamp2, ' ',
        log_timestamp2 - log_timestamp, ' ',
        pText)

    # Save current time to calculate elapsed time until next log
    log_timestamp = log_timestamp2

#===============================================================================
# BEGIN
#===============================================================================
if __name__ == '__main__':
    sys.exit(main())       
