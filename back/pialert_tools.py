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
import sys, subprocess, os, re, datetime, sqlite3, socket, io, requests, time, pwd, glob, ipaddress, ssl, json, tzlocal, asyncio, aiohttp, threading
import logging
from logging.handlers import RotatingFileHandler

#===============================================================================
# CONFIG CONSTANTS
#===============================================================================
PIALERT_BACK_PATH = os.path.dirname(os.path.abspath(__file__))
PIALERT_PATH = PIALERT_BACK_PATH + "/.."
PIALERT_DBTOOLS_FILE = PIALERT_PATH + "/db/pialert_tools.db"
STATUS_FILE_SCAN = PIALERT_BACK_PATH + "/.scanning_tools"

if (sys.version_info > (3,0)):
    exec(open(PIALERT_PATH + "/config/version.conf").read())
    exec(open(PIALERT_PATH + "/config/pialert.conf").read())
else:
    execfile(PIALERT_PATH + "/config/version.conf")
    execfile(PIALERT_PATH + "/config/pialert.conf")

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
        print('usage pialert_tools speedtest | nmap | cleanup' )
        return
    cycle = str(sys.argv[1])

    if cycle == 'speedtest':
        res = speedtest()
    elif cycle == 'nmap':
        res = nmap_scan()
        # res = nmap_scan()
    elif cycle == 'cleanup':
        res = cleanup_database_tools()
    else:
        return 0

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
