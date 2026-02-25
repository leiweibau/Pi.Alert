#!/usr/bin/env python
#
#-------------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector and Web service monitor
#
#  pialert.py - Back module. Network scanner, Web service monitor
#-------------------------------------------------------------------------------
#  Puche 2021                                              GNU GPLv3
#  leiweibau 2024                                          GNU GPLv3
#  piapiacz, hspindel
#-------------------------------------------------------------------------------

#===============================================================================
# IMPORTS
#===============================================================================
from __future__ import print_function
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from requests.packages.urllib3.exceptions import InsecureRequestWarning
from mac_vendor_lookup import MacLookup
from time import sleep, time, strftime
from base64 import b64encode
from urllib.parse import urlparse
from cryptography import x509
from cryptography.hazmat.backends import default_backend
from pathlib import Path
from datetime import datetime, timedelta, timezone
from paho.mqtt.client import Client, MQTTv311, CallbackAPIVersion, MQTT_ERR_SUCCESS
import sys, subprocess, os, re, datetime, sqlite3, socket, io, smtplib, csv, requests, time, pwd, glob, ipaddress, ssl, json, tzlocal, asyncio, aiohttp, threading

#===============================================================================
# CONFIG CONSTANTS
#===============================================================================
PIALERT_BACK_PATH = os.path.dirname(os.path.abspath(__file__))
PIALERT_PATH = PIALERT_BACK_PATH + "/.."
PIALERT_WEBSERVICES_LOG = PIALERT_PATH + "/log/pialert.webservices.log"
STOPPIALERT = PIALERT_PATH + "/config/setting_stoppialert"
PIALERT_DB_FILE = PIALERT_PATH + "/db/pialert.db"
PIALERT_DBTOOLS_FILE = PIALERT_PATH + "/db/pialert_tools.db"
PIALERT_DB_PATH = PIALERT_PATH + "/db"
REPORTPATH_WEBGUI = PIALERT_PATH + "/front/reports/"
STATUS_FILE_SCAN = PIALERT_BACK_PATH + "/.scanning"
STATUS_FILE_BACKUP = PIALERT_BACK_PATH + "/.backup"

PIHOLE6_SES_VALID = ""
PIHOLE6_SES_SID = ""
PIHOLE6_SES_CSRF = ""

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
    global cycle
    global log_timestamp
    global sql_connection
    global sql
    global sql_connection_tools
    global sql_tools

    # Header
    print('\nPi.Alert v'+ VERSION_DATE)
    print('---------------------------------------------------------')
    print(f"Executing user: {get_username()}")

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

    print('Timestamp:', startTime )

    # Check parameters
    if len(sys.argv) != 2 :
        print('usage pialert [scan_cycle] | internet_IP | update_vendors | cleanup' )
        return
    cycle = str(sys.argv[1])

    if os.path.exists(STOPPIALERT) == True :
        res = check_pialert_countdown()
    else :
        if cycle == 'internet_IP':
            res = check_internet_IP()
        elif cycle == 'cleanup':
            res = cleanup_database()
        elif cycle == 'update_vendors':
            res = update_devices_MAC_vendors()
        elif cycle == 'update_vendors_silent':
            res = update_devices_MAC_vendors('-s')
        else:
            res = scan_network()

    # Check error
    if res != 0 :
        closeDB()
        return res

    # Reporting
    if cycle not in ['internet_IP', 'cleanup', 'update_vendors', 'update_vendors_silent']:
        email_reporting()

    # # Close SQL
    # closeDB()

    # Final menssage
    print('\nDONE!!!\n\n')

    # Remove scan status file created in scan_network()
    if cycle not in ['internet_IP', 'cleanup', 'update_vendors', 'update_vendors_silent']:
        if os.path.exists(STATUS_FILE_SCAN):
            os.remove(STATUS_FILE_SCAN)

        # Perform shutdown or rebppt
        process_webgui_tokens()

    sys.stdout.flush()
    sys.stderr.flush()

    # Save Log to ToolsDB
    openDB_tools()
    write_cycle_logs_to_tables()
    closeDB_tools()

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
    print_log(f"\nPrepare Scan...")
    print_log(f"    Force file permissions on Pi.Alert db...")

    # Set permissions
    os.system("sudo /usr/bin/chown " + get_username() + ":www-data " + PIALERT_DB_FILE + "*")
    os.system("sudo /usr/bin/chmod 775 " + PIALERT_DB_FILE + "*")

    # Get permissions
    fileinfo = Path(PIALERT_DB_FILE)
    file_stat = fileinfo.stat()
    print_log(f"        DB permission mask: {oct(file_stat.st_mode)[-3:]}")
    print_log(f"        DB Owner and Group: {fileinfo.owner()}:{fileinfo.group()}")

# ------------------------------------------------------------------------------
def set_reports_file_permissions():
    os.system("sudo chown -R " + get_username() + ":www-data " + REPORTPATH_WEBGUI)
    os.system("sudo chmod -R 775 " + REPORTPATH_WEBGUI)

# ------------------------------------------------------------------------------
def export_user_crontab_to_file():
    output_file = os.path.join(PIALERT_PATH + "/log", "usercron.log")
    try:
        result = subprocess.run(
            ["crontab", "-l"],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False
        )

        cleaned_lines = []

        if result.returncode == 0:
            for line in result.stdout.splitlines():
                line = line.strip()

                if not line or line.startswith("#"):
                    continue

                if "#" in line:
                    line = line.split("#", 1)[0].rstrip()

                if line:
                    cleaned_lines.append(line)

        with open(output_file, "w", encoding="utf-8") as f:
            if cleaned_lines:
                f.write("\n".join(cleaned_lines) + "\n")
            else:
                f.write("")

        return

    except Exception as e:
        print(f"Fehler beim Exportieren des User-Crontabs: {e}")
        return

#===============================================================================
# Countdown
#===============================================================================
def check_pialert_countdown():

    openDB()
    if os.path.exists(STOPPIALERT):
        # get timer from file
        with open(STOPPIALERT, 'r') as file:
            data = int(file.read().rstrip())
            # print("Timer in min: %s" % data)

        FILETIME = int(os.path.getctime(STOPPIALERT))
        ACTUALTIME = int(time.time())
        STOPTIME = FILETIME+(data*60)-60

        if ( ACTUALTIME > STOPTIME ):
            print("The file \"setting_stoppialert\" will be deleted")
            os.remove(STOPPIALERT)
            os.system('/usr/bin/python3 ' + PIALERT_BACK_PATH + '/pialert_reporting_test.py reporting_stoptimer')

            sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                           VALUES (?, 'c_002', 'cronjob', 'LogStr_0513', '', '') """, (startTime,))

            sql_connection.commit()
        else:
            print(f"Timer Start: {time.ctime(FILETIME)}")
            # Check 1min before countdown ends
            # Delete stop file 1 min before countdown ends
            print(f"Timer End : {time.ctime(STOPTIME+60)}")
            print("----------------------------------------")
            print("Timer still running")

    closeDB()


#===============================================================================
# Reboot / Shutdown
#===============================================================================

def process_webgui_tokens_execute(cmd: str):
    try:
        subprocess.run(cmd, shell=True, check=True)
    except subprocess.CalledProcessError:
        sys.exit(1)


def process_webgui_tokens():
    TMP_DIR = os.path.join(PIALERT_PATH, "front/php/tmp")
    SHUTDOWN_TOKEN = os.path.join(TMP_DIR, "shutdown_token")
    REBOOT_TOKEN   = os.path.join(TMP_DIR, "reboot_token")

    shutdown_exists = os.path.isfile(SHUTDOWN_TOKEN)
    reboot_exists   = os.path.isfile(REBOOT_TOKEN)

    if not shutdown_exists and not reboot_exists:
        print_log("no_token")
        return

    if shutdown_exists:
        try:
            os.remove(SHUTDOWN_TOKEN)
        except Exception:
            print_log("delete_failed")

        process_webgui_tokens_execute("sleep 5 && sudo /usr/sbin/shutdown -h now")
        print_log("shutdown_executed")

    elif reboot_exists:
        try:
            os.remove(REBOOT_TOKEN)
        except Exception:
            print_log("delete_failed")

        process_webgui_tokens_execute("sleep 5 && sudo /usr/sbin/shutdown -r now")
        print_log("reboot_executed")

    return

#===============================================================================
# Save logs
#===============================================================================

def write_cycle_logs_to_tables(log_dir=PIALERT_PATH + "/log"):
    global sql_connection_tools
    global sql_tools
    global startTime

    print_log("Save Log in Tools-DB")
    LOGFILE_TABLE_MAP = {
        "pialert.1.log": "Log_History_Scan",
        "pialert.webservices.log": "Log_History_WebServices",
        "pialert.cleanup.log": "Log_History_Cleanup",
        "pialert.IP.log": "Log_History_InternetIP",
        "pialert.vendors.log": "Log_History_Vendors",
    }

    CYCLE_LOGFILES = {
        "1": [
            "pialert.1.log",
            "pialert.webservices.log"
        ],
        "cleanup": [
            "pialert.cleanup.log"
        ],
        "internet_IP": [
            "pialert.IP.log"
        ],
        "update_vendors": [
            "pialert.vendors.log"
        ],
    }

    # print(cycle)

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
# INTERNET IP CHANGE
#===============================================================================
def check_internet_IP():

    if not OFFLINE_MODE :
        print('\nRetrieving Internet IP...')
        internet_IP = get_internet_IP()

        # Check result = IP
        if internet_IP == "" :
            print('    Error retrieving Internet IP')
            print('    Exiting...\n')
            return 1
        print('   ', internet_IP)

        # Get previous stored IP
        print('\nRetrieving previous IP...')
        openDB()
        previous_IP = get_previous_internet_IP()
        print('   ', previous_IP)

        # Check IP Change
        if internet_IP != previous_IP :
            print('    Saving new IP')
            save_new_internet_IP (internet_IP)
            print('        IP updated')
        else :
            print('    No changes to perform')
        closeDB()

        # Get Dynamic DNS IP
        if DDNS_ACTIVE and internet_IP:
            print('\nRetrieving Dynamic DNS IP...')
            dns_IP = get_dynamic_DNS_IP()

            # Check Dynamic DNS IP
            if dns_IP == "" :
                print('    Error retrieving Dynamic DNS IP')
                print('    Exiting...\n')
                return 1
            print('   ', dns_IP)

            # Check DNS Change
            if dns_IP != internet_IP :
                print('    Updating Dynamic DNS IP...')
                message = set_dynamic_DNS_IP()
                print('       ', message)
            else :
                print('    No changes to perform')
        else :
            print('\nSkipping Dynamic DNS update...')
    else :
        print('\nOffline Mode...')

    # Run continuous New Device Notification
    print(f"\nContinuous New Device Notification...")
    if REPORT_NEW_CONTINUOUS :
        print(f"    Crontab: {REPORT_NEW_CONTINUOUS_CRON}")
        continuously_new_email_reporting(startTime, REPORT_NEW_CONTINUOUS_CRON)
    else:
        print(f"    Skipping... Not activated!")

    if not OFFLINE_MODE :
        # Run automated Speedtest
        print(f"\nAuto Speedtest...")
        if SPEEDTEST_TASK_ACTIVE :
            # Check if Speedtest is installed
            speedtest_binary = PIALERT_BACK_PATH + '/speedtest/speedtest'
            if os.path.exists(speedtest_binary):
                print(f"    Crontab: {SPEEDTEST_TASK_CRON}")
                run_speedtest_task(startTime, SPEEDTEST_TASK_CRON)
                # run_speedtest_task(startTime, "*/1 * * * *")
            else:
                print('    Skipping Speedtest... Not installed!')
        else :
            print('    Skipping Speedtest... Not activated!')

        # Run automated UpdateCheck
        print(f"\nAuto Update-Check...")
        if AUTO_UPDATE_CHECK :
            print(f"    Crontab: {AUTO_UPDATE_CHECK_CRON}")
            checkNewVersion(startTime, AUTO_UPDATE_CHECK_CRON)
        else:
            NewVersion_FrontendNotification(False,"")
            print(f"    Skipping Auto Update-Check... Not activated!")

    # Run automated Backup
    print(f"\nAuto Backup...")
    if AUTO_DB_BACKUP :
        print(f"    Crontab: {AUTO_DB_BACKUP_CRON}")
        if not os.path.exists(STATUS_FILE_BACKUP):
            create_autobackup(startTime, AUTO_DB_BACKUP_CRON)
        else:
            print("    Backup function pending.")
    else:
        print(f"    Skipping Auto Backup... Not activated!")

    # Move Reports
    print(f"\nCleanup Reports...")
    if REPORT_TO_ARCHIVE > 0:
        rep_counter = 0
        archive_threshold = startTime - timedelta(hours=REPORT_TO_ARCHIVE)
        report_path = PIALERT_PATH + "/front/reports"
        archive_path = report_path + "/archived"
        
        for filename in os.listdir(report_path):
            if filename.endswith(".txt"):
                file_path = os.path.join(report_path, filename)
                file_creation_time = datetime.datetime.fromtimestamp(os.path.getmtime(file_path))
                
                if file_creation_time < archive_threshold:
                    new_path = os.path.join(archive_path, filename)
                    os.rename(file_path, new_path)
                    rep_counter += 1
        
        if rep_counter > 0:
            infostring = 'Archived Reports: ' + str(rep_counter)
            openDB()
            sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                           VALUES (?, 'c_050', 'cronjob', 'LogStr_0508', '', ?) """, (startTime,infostring))
            sql_connection.commit()
            closeDB()
    else:
        print(f"    Skipping Cleanup Reports... Not activated!")

    return 0

# ------------------------------------------------------------------------------
def create_autobackup(start_time, crontab_string):
    # create status file
    with open(STATUS_FILE_BACKUP, "w") as f:
        f.write("")

    # convert cron string
    crontab_parts = crontab_string.split()
    minute = parse_cron_part(crontab_parts[0], start_time.minute, 0, 60) # last value is the exit value, meaning the 1. invalid value
    hour = parse_cron_part(crontab_parts[1], start_time.hour, 0, 60)
    day_of_month = parse_cron_part(crontab_parts[2], start_time.day, 1, 32)
    month = parse_cron_part(crontab_parts[3], start_time.month, 1, 13)
    day_of_week = parse_cron_part(crontab_parts[4], start_time.weekday(), 0, 7)

    # Compare cron
    if (start_time.minute in minute) and (start_time.hour in hour) and (start_time.day in day_of_month) and \
       (start_time.month in month) and (start_time.weekday() in day_of_week):

        while os.path.exists(STATUS_FILE_SCAN):
            if time.time() - start_time.timestamp() >= 300:  # Check whether 5 minutes have passed
                print_log("The status file has not been deleted after 5 minutes. The script is terminated.")
                if os.path.exists(STATUS_FILE_BACKUP):
                    os.remove(STATUS_FILE_BACKUP)
                return
            time.sleep(1)  # wait 1 second
        else:
            print("    Backup is started...")
            BACKUP_FILE_DATE = str(start_time)
            BACKUP_FILE = PIALERT_DB_PATH + "/pialertdb_" + BACKUP_FILE_DATE.replace("-", "").replace(" ", "_").replace(":", "") + ".zip"
            time.sleep(20)  # wait 20s to finish the reporting

            # Backup DB (no further checks)
            # sqlite_command = ['sqlite3', PIALERT_DB_PATH + '/pialert.db', '.backup ' + PIALERT_DB_PATH + '/temp/pialert.db']
            # subprocess.check_output(sqlite_command, universal_newlines=True)
            # subprocess.check_output(['zip', '-j', '-qq', BACKUP_FILE, PIALERT_PATH + '/db/temp/pialert.db'], universal_newlines=True)

            # Backup pialert.db (no further checks)
            subprocess.check_output(
                ['sqlite3', PIALERT_DB_PATH + '/pialert.db',
                 '.backup ' + PIALERT_DB_PATH + '/temp/pialert.db'],
                universal_newlines=True
            )

            # Backup pialert-tools.db (no further checks)
            subprocess.check_output(
                ['sqlite3', PIALERT_DB_PATH + '/pialert_tools.db',
                 '.backup ' + PIALERT_DB_PATH + '/temp/pialert_tools.db'],
                universal_newlines=True
            )

            # Compress DB (both)
            subprocess.check_output(
                ['zip', '-j', '-qq', BACKUP_FILE,
                 PIALERT_PATH + '/db/temp/pialert.db',
                 PIALERT_PATH + '/db/temp/pialert_tools.db'],
                universal_newlines=True
            )

            time.sleep(4)
            os.remove(PIALERT_DB_PATH + '/temp/pialert.db')
            os.remove(PIALERT_DB_PATH + '/temp/pialert_tools.db')
            # Set Permissions for www-data (testing)
            os.system("sudo chown www-data:www-data " + BACKUP_FILE)
            os.system("sudo chmod 644 " + BACKUP_FILE)
            # Cleanup
            bak_files = glob.glob(os.path.join(PIALERT_DB_PATH, "pialertdb_20*.zip"))
            bak_files.sort(key=os.path.getmtime, reverse=True)
            for file in bak_files[AUTO_DB_BACKUP_KEEP:]:
                os.remove(file)
            print(f"    Cleanup DB Backups")

            # Backup config file
            BACKUP_CONF_FILE = PIALERT_PATH + "/config/pialert-" + BACKUP_FILE_DATE.replace("-", "").replace(" ", "_").replace(":", "") + ".bak"
            subprocess.check_output('cp ' + PIALERT_PATH + '/config/pialert.conf ' + BACKUP_CONF_FILE, shell=True)
            # Set Permissions for www-data (testing)
            os.system("sudo chown www-data:www-data " + BACKUP_CONF_FILE)
            os.system("sudo chmod 644 " + BACKUP_CONF_FILE)
            # Cleanup
            bak_files = glob.glob(os.path.join(PIALERT_PATH + "/config", "pialert-20*.bak"))
            bak_files.sort(key=os.path.getmtime, reverse=True)
            for file in bak_files[AUTO_DB_BACKUP_KEEP:]:
                os.remove(file)
            print(f"    Cleanup Config Backups")

            openDB()
            sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                           VALUES (?, 'c_010', 'cronjob', 'LogStr_0011', '', '') """, (startTime,))
            sql_connection.commit()
            sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                           VALUES (?, 'c_000', 'cronjob', 'LogStr_0007', '', '') """, (startTime,))
            sql_connection.commit()
            closeDB()

    else:
        print(f"    Backup function was NOT executed.")

    # remove status file
    if os.path.exists(STATUS_FILE_BACKUP):
        os.remove(STATUS_FILE_BACKUP)

# ------------------------------------------------------------------------------
def parse_cron_part(cron_part, current_value, cron_min_value, cron_max_value):
    if cron_part == '*':
        return set(range(cron_min_value, cron_max_value))
    elif '/' in cron_part:
        step = int(cron_part.split('/')[1])
        return set(range(cron_min_value, cron_max_value, step))
    elif '-' in cron_part:
        start, end = map(int, cron_part.split('-'))
        return set(range(start, end + 1))
    elif ',' in cron_part:
        values = cron_part.split(',')
        return set(int(value) for value in values)
    else:
        return {int(cron_part)}

# ------------------------------------------------------------------------------
def NewVersion_FrontendNotification(newVersion,update_notes):
    file_path = PIALERT_PATH + "/front/auto_Update.info"
    if newVersion == True:
        if not os.path.exists(file_path):
            print("    Create Frontend Notification.")
            os.system('/usr/bin/python3 ' + PIALERT_BACK_PATH + '/pialert_reporting_test.py update_notification')
        else:
            print("    Update Frontend Notification.")
        with open(file_path, 'w') as file:
            file.write(update_notes)
    else:
        if os.path.exists(file_path):
            os.remove(file_path)
            print("    Remove Frontend Notification.")

# ------------------------------------------------------------------------------
def checkNewVersion(start_time, crontab_string):

    # convert cron string
    crontab_parts = crontab_string.split()
    minute = parse_cron_part(crontab_parts[0], start_time.minute, 0, 60) # last value is the exit value, meaning the 1. invalid value
    hour = parse_cron_part(crontab_parts[1], start_time.hour, 0, 60)
    day_of_month = parse_cron_part(crontab_parts[2], start_time.day, 1, 32)
    month = parse_cron_part(crontab_parts[3], start_time.month, 1, 13)
    day_of_week = parse_cron_part(crontab_parts[4], start_time.weekday(), 0, 7)

    # Compare cron
    if (start_time.minute in minute) and (start_time.hour in hour) and (start_time.day in day_of_month) and \
       (start_time.month in month) and (start_time.weekday() in day_of_week):

        newVersion = False
        currentversion = VERSION_DATE

        print(f"    Current Version: {currentversion}")

        UPDATE_CHECK_URL = "https://api.github.com/repos/leiweibau/Pi.Alert/commits?path=tar%2Fpialert_latest.tar&page=1&per_page=1"
        #UPDATE_CHECK_URL = "https://api.github.com/repos/leiweibau/Pi.Alert/commits?path=tar%2Fpialert_latest.tar&sha=next_update&page=1&per_page=1"
        data = ""
        update_notes = ""

        try:
            url = requests.get(UPDATE_CHECK_URL)
            text = url.text
            data = json.loads(text)
        except (requests.exceptions.ConnectionError, json.decoder.JSONDecodeError) as e:
            print("    ERROR: Couldn't check for new release.")
            data = ""

        openDB()
        if data != "" and len(data) > 0 and isinstance(data, list) and "commit" in data[0]:
            dateTimeStr = data[0]['commit']['author']['date']
            update_notes = data[0]['commit']['message']
            date_obj = datetime.datetime.strptime(dateTimeStr, '%Y-%m-%dT%H:%M:%SZ')
            latestversion = date_obj.strftime('%Y-%m-%d')

            if latestversion > currentversion:
                print(f"    New version {latestversion} is available!")
                newVersion = True
                NewVersion_FrontendNotification(newVersion,update_notes)
                sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                               VALUES (?, 'c_060', 'cronjob', 'LogStr_0061', '', '') """, (startTime,))
            else:
                print("    Running the latest version.")
                # newVersion is still FALSE
                NewVersion_FrontendNotification(newVersion,update_notes)
                sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                               VALUES (?, 'c_060', 'cronjob', 'LogStr_0067', '', '') """, (startTime,))
        else:
            # newVersion is still FALSE
            NewVersion_FrontendNotification(newVersion,update_notes)
            sql.execute ("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                   VALUES (?, 'c_060', 'cronjob', 'LogStr_0066', '', '') """, (startTime,))
        closeDB()

    else:
        print(f"    Version Check function was NOT executed.")

#-------------------------------------------------------------------------------
def run_speedtest_task(start_time, crontab_string):
    # convert cron string
    crontab_parts = crontab_string.split()
    minute = parse_cron_part(crontab_parts[0], start_time.minute, 0, 60) # last value is the exit value, meaning the 1. invalid value
    hour = parse_cron_part(crontab_parts[1], start_time.hour, 0, 60)
    day_of_month = parse_cron_part(crontab_parts[2], start_time.day, 1, 32)
    month = parse_cron_part(crontab_parts[3], start_time.month, 1, 13)
    day_of_week = parse_cron_part(crontab_parts[4], start_time.weekday(), 0, 7)

    # Define the command and arguments
    # Compare cron
    if (start_time.minute in minute) and (start_time.hour in hour) and (start_time.day in day_of_month) and \
       (start_time.month in month) and (start_time.weekday() in day_of_week):

        command = ["python3", PIALERT_BACK_PATH + "/pialert_tools.py", "speedtest"]
        subprocess.Popen(command, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

    else:
        print("    Speedtest function was NOT executed.")
    return 0

#-------------------------------------------------------------------------------
def get_internet_IP():
    # dig_args = ['dig', '+short', '-4', 'myip.opendns.com', '@resolver1.opendns.com']
    # cmd_output = subprocess.check_output (dig_args, universal_newlines=True)
    curl_args = ['curl', '-s', QUERY_MYIP_SERVER]
    cmd_output = subprocess.check_output (curl_args, universal_newlines=True)
    return check_IP_format (cmd_output)

#-------------------------------------------------------------------------------
def get_dynamic_DNS_IP():
    # Using default or OpenDNS DNS server
    dig_args = ['dig', '+short', DDNS_DOMAIN]
    # dig_args = ['dig', '+short', DDNS_DOMAIN, '@resolver1.opendns.com']
    dig_output = subprocess.check_output (dig_args, universal_newlines=True)
    return check_IP_format (dig_output)

#-------------------------------------------------------------------------------
def set_dynamic_DNS_IP():
    # Update Dynamic IP
    curl_output = subprocess.check_output (['curl', '-s',
        DDNS_UPDATE_URL +
        'username='  + DDNS_USER +
        '&password=' + DDNS_PASSWORD +
        '&hostname=' + DDNS_DOMAIN],
        universal_newlines=True)
    return curl_output
    
#-------------------------------------------------------------------------------
def get_previous_internet_IP():
    # get previos internet IP stored in DB
    sql.execute ("SELECT dev_LastIP FROM Devices WHERE dev_MAC = 'Internet' ")
    return sql.fetchone()[0]

#-------------------------------------------------------------------------------
def save_new_internet_IP(pNewIP):
    # Log new IP into logfile
    append_line_to_file (LOG_PATH + '/IP_changes.log',
        str(startTime) +'\t'+ pNewIP +'\n')
    # Save event
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    VALUES ('Internet', ?, ?, 'Internet IP Changed',
                        'Previous Internet IP: '|| ?, 1) """,
                    (pNewIP, startTime, get_previous_internet_IP() ) )
    # Save new IP
    sql.execute ("""UPDATE Devices SET dev_LastIP = ?
                    WHERE dev_MAC = 'Internet' """,
                    (pNewIP,) )
    sql_connection.commit()
    
#-------------------------------------------------------------------------------
def check_IP_format(pIP):
    if pIP is None:
        return ""
    # Check IP format
    IPv4SEG  = r'(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])'
    IPv4ADDR = r'(?:(?:' + IPv4SEG + r'\.){3,3}' + IPv4SEG + r')'
    IP = re.search(IPv4ADDR, pIP)
    # Return error if not IP
    if IP is None:
        return ""
    return IP.group(0)

#===============================================================================
# Cleanup Tasks
#===============================================================================
def cleanup_database():
    openDB()
    print('\nCleanup tables, up to the lastest ' + str(DAYS_TO_KEEP_EVENTS) + ' days:')
    print('    Devices Events')

    sql.execute("SELECT COUNT(*) FROM Events WHERE eve_DateTime <= date('now', '-" + str(DAYS_TO_KEEP_EVENTS) + " day')")
    count = sql.fetchone()[0]

    if count > 0:
        sql.execute("DELETE FROM Events WHERE eve_DateTime <= date('now', '-" + str(DAYS_TO_KEEP_EVENTS) + " day')")
        sql_connection.commit()
        RepairedEventTime = startTime - timedelta(days=DAYS_TO_KEEP_EVENTS)
        #sql.execute("DELETE FROM Events WHERE eve_EventType LIKE 'VOIDED%'")
        sql.execute("SELECT dev_MAC, dev_LastIP FROM Devices WHERE dev_PresentLastScan = 1")
        repair_devices = sql.fetchall()
        for device in repair_devices:
            dev_mac, dev_lastip = device
            
            sql.execute("SELECT 1 FROM Events WHERE eve_MAC = ? AND eve_EventType='Connected'", (dev_mac,))
            event_exists = sql.fetchone()
            
            if not event_exists:
                sql.execute(
                    "INSERT INTO Events (eve_MAC, eve_EventType, eve_IP, eve_DateTime, eve_PendingAlertEmail) VALUES (?, ?, ?, ?, ?)",
                    (dev_mac, "Connected", dev_lastip, str(RepairedEventTime), 0)
                )

    print('\nCleanup tables, up to the lastest ' + str(DAYS_TO_KEEP_ONLINEHISTORY) + ' days:')
    print('    Online_History')
    sql.execute("DELETE FROM Online_History WHERE Scan_Date <= date('now', '-" + str(DAYS_TO_KEEP_ONLINEHISTORY) + " day')")

    print('\nCleanup tables, up to the lastest hard-coded days:')
    print('    Services_Events (30)')
    sql.execute("DELETE FROM Services_Events WHERE moneve_DateTime <= date('now', '-" + str(30) + " day')")
    print('    ICMP_Mon_Events (14)')
    sql.execute("DELETE FROM ICMP_Mon_Events WHERE icmpeve_DateTime <= date('now', '-" + str(14) + " day')")

    print('\nTrim Journal to the lastest 1000 entries')
    sql.execute("DELETE FROM pialert_journal WHERE journal_id NOT IN (SELECT journal_id FROM pialert_journal ORDER BY journal_id DESC LIMIT 1000) AND (SELECT COUNT(*) FROM pialert_journal) > 1000")

    print('\nTrim Services Events Journal to the lastest 500 entries')
    sql.execute("DELETE FROM Services_Events_Journal WHERE monevj_DateTime NOT IN (SELECT monevj_DateTime FROM Services_Events_Journal ORDER BY monevj_DateTime DESC LIMIT 500) AND (SELECT COUNT(*) FROM Services_Events_Journal) > 500")

    print('\nShrink Database...')
    sql.execute("VACUUM;")
    sql.execute("""INSERT INTO pialert_journal (Journal_DateTime, LogClass, Trigger, LogString, Hash, Additional_Info)
                    VALUES (?, 'c_010', 'cronjob', 'LogStr_0101', '', 'Cleanup') """, (startTime,))
    closeDB()

    print('\nCleanup DB_Tools...')
    sys.stdout.flush()
    sys.stderr.flush()
    command = ["python3", PIALERT_BACK_PATH + "/pialert_tools.py", "cleanup"]
    subprocess.run(command, stdout=sys.stdout, stderr=sys.stderr, text=True, check=True)

    return 0

#===============================================================================
# UPDATE DEVICE MAC VENDORS
#===============================================================================
def update_devices_MAC_vendors (pArg = ''):

    if not OFFLINE_MODE :
        # Update vendors DB (oui)
        print('\nUpdating vendors DB...')
        update_args = ['sh', PIALERT_BACK_PATH + '/update_vendors.sh', pArg]
        update_output = subprocess.check_output (update_args)

        # Initialize variables
        recordsToUpdate = []
        ignored = 0
        notFound = 0

        # All devices loop
        print('\nSearching devices vendor', end='')
        openDB()
        # Only the devices for which no vendor has yet been entered are attempted to be updated.
        for device in sql.execute ("SELECT * FROM Devices WHERE dev_Vendor = ''") :
            # Search vendor in HW Vendors DB
            vendor = query_MAC_vendor (device['dev_MAC'])
            if vendor == -1 :
                notFound += 1
            elif vendor == -2 :
                ignored += 1
            else :
                recordsToUpdate.append ([vendor, device['dev_MAC']])
            # progress bar
            print('.', end='')
            sys.stdout.flush()

        print('')
        print("    Devices Ignored:  ", ignored)
        print("    Vendors Not Found:", notFound)
        print("    Vendors updated:  ", len(recordsToUpdate) )

        # mac-vendor-lookup update
        try:
            print('\nTry build in mac-vendor-lookup update')
            mac = MacLookup()
            mac.update_vendors()
            print('    Update successful')
        except:
            print('\nFallback')
            print('    Backup old mac-vendors.txt for mac-vendor-lookup')
            p = subprocess.call(["cp $HOME/.cache/mac-vendors.txt $HOME/.cache/mac-vendors.bak"], shell=True)
            print('    Create mac-vendors.txt for mac-vendor-lookup')
            p = subprocess.call(["/usr/bin/sed -e 's/\t/:/g' -e 's/Ã¼/ü/g' -e 's/Ã¶/ö/g' -e 's/Ã¤/ä/g' -e 's/Ã³/ó/g' -e 's/Ã©/é/g' -e 's/â/–/g' -e 's/Â//g' -e '/^#/d' /usr/share/arp-scan/ieee-oui.txt > $HOME/.cache/mac-vendors.txt"], shell=True)

        # update devices
        sql.executemany ("UPDATE Devices SET dev_Vendor = ? WHERE dev_MAC = ? ",
            recordsToUpdate )

        closeDB()
    else :
        print('\nOffline Mode...\n')

    return 0

#-------------------------------------------------------------------------------
def query_MAC_vendor(pMAC):
    try:
        pMACstr = str(pMAC)

        # Check MAC parameter
        mac = pMACstr.replace(':', '')
        if len(pMACstr) != 17 or len(mac) != 12:
            return -2

        # Custom Vendor List
        mac_prefix = mac[0:8]
        user_vendors_file = PIALERT_DB_PATH + '/user_vendors.txt'
        if os.path.exists(user_vendors_file):
            grep_args = ['grep', '-i', mac_prefix, user_vendors_file]
            result = subprocess.run(grep_args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        else:
            result = subprocess.CompletedProcess(args=None, returncode=1, stdout=b'', stderr=b'')

        if result.returncode != 0:
            # Query public Vendor list
            mac_prefix = mac[0:6]
            grep_args = ['grep', '-i', mac_prefix, VENDORS_DB]
            result = subprocess.run(grep_args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            if result.returncode != 0:
                grep_output = []  # no results
            else:
                grep_output = result.stdout.decode().splitlines()
                vendor_start_index = 7

            # Additional query for multiple hits
            if len(grep_output) > 1:
                mac_prefix = mac[0:7]
                grep_args = ['grep', '-i', mac_prefix, VENDORS_DB]
                result = subprocess.run(grep_args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                if result.returncode != 0:
                    grep_output = []  # no results
                else:
                    grep_output = result.stdout.decode().splitlines()
                    vendor_start_index = 8

            # Further specification 1
            if len(grep_output) > 1:
                mac_prefix = mac[0:8]
                grep_args = ['grep', '-i', mac_prefix, VENDORS_DB]
                result = subprocess.run(grep_args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                if result.returncode != 0:
                    grep_output = []  # no results
                else:
                    grep_output = result.stdout.decode().splitlines()
                    vendor_start_index = 9

            # Further specification 2
            if len(grep_output) > 1:
                mac_prefix = mac[0:9]
                grep_args = ['grep', '-i', mac_prefix, VENDORS_DB]
                result = subprocess.run(grep_args, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                if result.returncode != 0:
                    grep_output = []  # no results
                else:
                    grep_output = result.stdout.decode().splitlines()
                    vendor_start_index = 10

        else:
            grep_output = result.stdout.decode().splitlines()
            vendor_start_index = 9

        # no results
        if not grep_output:
            return -1

        # return Vendor
        vendor_line = grep_output[0]
        vendor = vendor_line[vendor_start_index:].strip()
        return vendor

    except subprocess.CalledProcessError:
        return -1

#===============================================================================
# SCAN NETWORK
#===============================================================================
def scan_network():
    global PIHOLE6_SES_VALID

    # Create scan status file
    with open(STATUS_FILE_SCAN, "w") as f:
        f.write("")

    # correct db permission every scan (user must/should be sudoer)
    set_db_file_permissions()
    get_local_sys_timezone()
    export_user_crontab_to_file()

    # Query ScanCycle properties
    print_log ('Query ScanCycle confinguration...')
    scanCycle_data = query_ScanCycle_Data (True)
    if scanCycle_data is None:
        print('\n*************** ERROR ***************')
        print('ScanCycle %s not found' % cycle )
        print('    Exiting...\n')
        return 1

    # ScanCycle data
    cycle_interval  = scanCycle_data['cic_EveryXmin']
    #arpscan_retries = scanCycle_data['cic_arpscanCycles']
    # arp-scan command
    print('\nScanning...')
    print_log ('arp-scan starts...')
    arpscan_devices = execute_arpscan()
    print_log ('arp-scan ends')
    # Pi-hole
    openDB()
    print_log ('Pi-hole copy starts...')
    copy_pihole_network()
    # DHCP Leases
    read_DHCP_leases()
    if PIHOLE6_SES_VALID==True:
        pihole_six_api_deauth()
    # Fritzbox
    openDB()
    print_log ('Fritzbox copy starts...')
    read_fritzbox_active_hosts()
    # Mikrotik
    openDB()
    print_log ('Mikrotik copy starts...')
    read_mikrotik_leases()
    # UniFi
    openDB()
    print_log ('UniFi copy starts...')
    read_unifi_clients()
    # OpenWRT
    openDB()
    print_log ('OpenWRT copy starts...')
    read_openwrt_clients()
    # AsusWRT
    openDB()
    print_log ('AsusWRT copy starts...')
    read_asuswrt_clients()
    openDB()
    print_log ('pfsense copy starts...')
    read_pfsense_clients()
    # Import Satellites Scans
    openDB()
    get_satellite_scans()
    # Load current scan data 1/2
    print('\nProcessing scan results...')
    # Load current scan data 2/2
    print_log ('Save scanned devices')
    save_scanned_devices (arpscan_devices, cycle_interval)
    print_log ('Dump all results to Log')
    dump_all_resulttables()
    # Process Ignore list
    print('    Processing ignore list 1/2...')
    remove_entries_from_table()
        # Print stats
    print_log ('Print Stats')
    print_scan_stats()
    print_log ('Stats end')
    # Create Events
    print('\nUpdating DB Info...')
    print('    Sessions Events (connect / discconnect) ...')
    insert_events()
    # Create New Devices
    # after create events -> avoid 'connection' event
    print('    Creating new devices...')
    create_new_devices()
    # Update devices info
    print('    Updating Devices Info...')
    update_devices_data_from_scan()
    # Resolve devices names
    print_log ('   Resolve devices names...')
    update_devices_names()
    # Process Ignore list
    print('    Processing ignore list 2/2...')
    #### Remove Devices from devicelist and event
    remove_entries_from_devicelist()
    # Void false connection - disconnections
    print('    Voiding false (ghost) disconnections...')
    void_ghost_disconnections()
    # Pair session events (Connection / Disconnection)
    print('    Pairing session events (connection / disconnection) ...')
    pair_sessions_events()
    # Sessions snapshot
    print('    Creating sessions snapshot...')
    create_sessions_snapshot()
    # Skip repeated notifications
    print('    Skipping repeated notifications...')
    skip_repeated_notifications()
    # Calc Activity History
    print('    Calculate Activity History...')
    calc_activity_history_main_scan()
    sql_connection.commit()
    closeDB()

    # Web Service Monitoring
    if SCAN_WEBSERVICES:
        if str(startTime)[15] == "0":
            service_monitoring()
    # ICMP Monitoring
    if ICMPSCAN_ACTIVE:
        icmp_monitoring()
    # Check Rogue DHCP
    if SCAN_ROGUE_DHCP:
        print('\nLooking for Rogue DHCP Servers...')
        rogue_dhcp_detection()

    # Publish MQTT
    if REPORT_TO_MQTT:
        print('\nSending MQTT...')
        report_to_mqtt()

    return 0



#-------------------------------------------------------------------------------
def get_local_sys_timezone():
    openDB()

    sat_os_timezone = str(tzlocal.get_localzone())
    sql.execute('SELECT par_ID FROM Parameters WHERE par_ID = ?', ('Local_System_TZ',))
    result = sql.fetchone()

    if result:
        sql.execute('UPDATE Parameters SET par_Value = ? WHERE par_ID = ?', (sat_os_timezone, 'Local_System_TZ'))
    else:
        sql.execute('INSERT INTO Parameters (par_ID, par_Value) VALUES (?, ?)', ('Local_System_TZ', sat_os_timezone))

    sql_connection.commit()
    closeDB()            

    return 0

#-------------------------------------------------------------------------------
def query_ScanCycle_Data(pOpenCloseDB = False):
    # Check if is necesary open DB
    if pOpenCloseDB :
        openDB()

    # Query Data
    sql.execute ("""SELECT cic_arpscanCycles, cic_EveryXmin
                    FROM ScanCycles
                    WHERE cic_ID = ? """, (cycle,))
    sqlRow = sql.fetchone()

    # Check if is necesary close DB
    if pOpenCloseDB :
        closeDB()

    return sqlRow

#-------------------------------------------------------------------------------
def execute_arpscan():

    # check if arp-scan is active
    if not ARPSCAN_ACTIVE:
        unique_devices = []
        return unique_devices

    print('    arp-scan Method...')

    # output of possible multiple interfaces
    arpscan_output = ""

    # multiple interfaces
    if type(SCAN_SUBNETS) is list:
        print("        ...arp-scan: Multiple interfaces")
        for interface in SCAN_SUBNETS :
            arpscan_output += execute_arpscan_on_interface (interface)
    # one interface only
    else:
        print("        ...arp-scan: One interface")
        arpscan_output += execute_arpscan_on_interface (SCAN_SUBNETS)

    # Search IP + MAC + Vendor as regular expresion
    re_ip = r'(?P<ip>((2[0-5]|1[0-9]|[0-9])?[0-9]\.){3}((2[0-5]|1[0-9]|[0-9])?[0-9]))'
    re_mac = r'(?P<mac>([0-9a-fA-F]{2}[:-]){5}([0-9a-fA-F]{2}))'
    re_hw = r'(?P<hw>.*)'
    re_pattern = re.compile(r'' + re_ip + r'\s+' + re_mac + r'\s' + re_hw)

    # Create Userdict of devices
    devices_list = [device.groupdict()
        for device in re.finditer (re_pattern, arpscan_output)]

    # Delete duplicate MAC
    unique_mac = []
    unique_devices = []

    for device in devices_list :
        if device['mac'] not in unique_mac:
            unique_mac.append(device['mac'])
            unique_devices.append(device)

    return unique_devices

#-------------------------------------------------------------------------------
def execute_arpscan_on_interface(SCAN_SUBNETS):
    # Prepare command arguments
    subnets = SCAN_SUBNETS.strip().split()
    # Retry is 3 to avoid false offline devices
    arpscan_args = ['sudo', 'arp-scan', '--ignoredups', '--bandwidth=256k', '--retry=6'] + subnets

    # Execute command
    try:
        # try runnning a subprocess
        result = subprocess.check_output (arpscan_args, universal_newlines=True)
    except subprocess.CalledProcessError as e:
        # An error occured, handle it
        print(e.output)
        result = ""

    return result

#-------------------------------------------------------------------------------
def copy_pihole_network():
    # empty Fritzbox Network table
    sql.execute ("DELETE FROM PiHole_Network")

    # check if Pi-hole is active
    if not PIHOLE_ACTIVE :
        return

    print(f"    Pi-hole {PIHOLE_VERSION} Client List Method...")

    if PIHOLE_VERSION in (None, 5):
        copy_pihole_network_five()
    elif PIHOLE_VERSION == 6:
        pihole_six_api_auth()
        copy_pihole_network_six()
    else:
        print('        ...Unsupported Version')

#-------------------------------------------------------------------------------
def copy_pihole_network_five():
    # Open Pi-hole DB
    sql.execute ("ATTACH DATABASE '"+ PIHOLE_DB +"' AS PH")

    # Copy Pi-hole Network table
    sql.execute ("""INSERT INTO PiHole_Network (PH_MAC, PH_Vendor, PH_LastQuery,
                        PH_Name, PH_IP)
                    SELECT hwaddr, macVendor, lastQuery,
                        (SELECT name FROM PH.network_addresses
                         WHERE network_id = id ORDER BY lastseen DESC, ip),
                        (SELECT ip FROM PH.network_addresses
                         WHERE network_id = id ORDER BY lastseen DESC, ip)
                    FROM PH.network
                    WHERE hwaddr NOT LIKE 'ip-%'
                      AND hwaddr <> '00:00:00:00:00:00' """)
    sql.execute ("""UPDATE PiHole_Network SET PH_Name = '(unknown)'
                    WHERE PH_Name IS NULL OR PH_Name = '' """)

    # Close Pi-hole DB
    sql.execute ("DETACH PH")

#-------------------------------------------------------------------------------
def pihole_six_api_auth():
    global PIHOLE6_URL
    global PIHOLE6_PASSWORD
    global PIHOLE6_SES_VALID
    global PIHOLE6_SES_SID
    global PIHOLE6_SES_CSRF

    if not PIHOLE6_URL :
        print('        ...Skipped (Config Error)')
        return

    if not PIHOLE6_URL.endswith('/'):
        PIHOLE6_URL += '/'

    requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
    headers = {
        "accept": "application/json",
        "content-type": "application/json",
        "User-Agent": "Pi.Alert/"+ VERSION_DATE
    }
    data = {
        "password": PIHOLE6_PASSWORD
    }
    try:
        response = requests.post(PIHOLE6_URL+'api/auth', headers=headers, json=data, verify=False, timeout=15)
    except requests.exceptions.Timeout:
        print(f"        Request timed out after 15 seconds")
        return
    except requests.exceptions.ConnectionError as e:
        print(f"        Connection error occurred")
        return
    except Exception as e:
        print(f"        An unexpected error occurred")
        return

    response_json = response.json()
    
    try:
        session_data = response_json.get('session', {})
        if session_data.get('valid', False):  # Default False, if 'valid' is missing
            PIHOLE6_SES_VALID = session_data['valid']
            PIHOLE6_SES_SID = session_data['sid']
            # to prevent key error if pihole has no password
            if PIHOLE6_PASSWORD:
                PIHOLE6_SES_CSRF = session_data['csrf']
        else:
            print("        Auth required")
            return
    except KeyError as e:
        print(f"        Invalid response. Check Pi-hole URL")
        return


#-------------------------------------------------------------------------------
def pihole_six_api_deauth():
    global PIHOLE6_URL
    global PIHOLE6_SES_VALID
    global PIHOLE6_SES_SID
    global PIHOLE6_SES_CSRF

    if not PIHOLE6_URL.endswith('/'):
        PIHOLE6_URL += '/'

    requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
    headers = {
        "X-FTL-SID": PIHOLE6_SES_SID
    }
    try:
        response = requests.delete(PIHOLE6_URL+'api/auth', headers=headers, verify=False, timeout=15)
    except requests.exceptions.Timeout:
        print(f"        Request timed out after 15 seconds")
        return
    except requests.exceptions.ConnectionError as e:
        print(f"        Connection error occurred")
        return
    except Exception as e:
        print(f"        An unexpected error occurred")
        return

    #print("        Pi-hole Logout")

#-------------------------------------------------------------------------------
def copy_pihole_network_six():
    global PIHOLE6_URL
    global PIHOLE6_SES_VALID
    global PIHOLE6_SES_SID
    global PIHOLE6_SES_CSRF
    global PIHOLE6_API_MAXCLIENTS

    if PIHOLE6_SES_VALID == True:
        headers = {
            "X-FTL-SID": PIHOLE6_SES_SID,
            "X-FTL-CSRF": PIHOLE6_SES_CSRF
        }
        #max_addresses=2 IPs per host
        raw_deviceslist = requests.get(PIHOLE6_URL+'api/network/devices?max_devices=' + str(PIHOLE6_API_MAXCLIENTS) + '&max_addresses=2', headers=headers, verify=False)

        result = {}
        deviceslist = raw_deviceslist.json()

        # If pi-hole is outside the local Pi.Alert network and cannot be found with arp.
        interfaces = get_pihole_interface_data()

        for device in deviceslist['devices']:
            hwaddr = device['hwaddr']
            lastQuery = device['lastQuery']
            macVendor = device['macVendor']

            # skip lo interface
            if hwaddr == "00:00:00:00:00:00":
                continue

            for ip_info in device['ips']:
                ip = ip_info['ip']
                name = ip_info['name'] if ip_info['name'] not in [None, ""] else "(unknown)"

                # Check whether the IP could be a IPv4 address
                if '.' in ip:
                    # Change the “lastQuery” variable to mark the Pi-hole host as “active”
                    for mac, localips in interfaces.items():
                        if ip in localips:
                            lastQuery = str(int(datetime.datetime.now().timestamp()))
                    # Create dict of all entries
                    result[hwaddr] = {
                        "ip": ip,
                        "name": name,
                        "macVendor": macVendor,
                        "lastQuery": lastQuery
                    }

        # print(result)
        for hwaddr, details in result.items():
            sql.execute("""
                INSERT INTO PiHole_Network (PH_MAC, PH_Vendor, PH_LastQuery, PH_Name, PH_IP)
                VALUES (?, ?, ?, ?, ?)
            """, (hwaddr, details['macVendor'], details['lastQuery'], details['name'], details['ip']))

        deviceslist = raw_deviceslist.json()
    else:
        print(f"        ...Skipped")
        return

#-------------------------------------------------------------------------------
def get_pihole_interface_data():
    global PIHOLE6_URL
    global PIHOLE6_SES_VALID
    global PIHOLE6_SES_SID
    global PIHOLE6_SES_CSRF

    result = {}
    
    if PIHOLE6_SES_VALID == True:
        headers = {
            "X-FTL-SID": PIHOLE6_SES_SID,
            "X-FTL-CSRF": PIHOLE6_SES_CSRF
        }
        raw_interfacelist = requests.get(PIHOLE6_URL+'api/network/interfaces', headers=headers, verify=False)
        data = raw_interfacelist.json()

        for interface in data['interfaces']:
            mac_address = interface.get('address')
            
            if mac_address == "00:00:00:00:00:00":
                continue
            
            if 'addresses' in interface:
                ips = [addr['address'] for addr in interface['addresses'] if addr['family'] == 'inet']
                if mac_address and ips:
                    result[mac_address] = ips

    return result


#-------------------------------------------------------------------------------
def read_fritzbox_active_hosts():

    sql.execute ("DELETE FROM Fritzbox_Network")

    # check if Pi-hole is active
    if not FRITZBOX_ACTIVE :
        return

    print('    Fritzbox Method...')

    try:
        from fritzconnection.lib.fritzhosts import FritzHosts
    except:
        print('        Missing python package')
        return

    try:
        # copy Fritzbox Network list
        fh = FritzHosts(address=FRITZBOX_IP, user=FRITZBOX_USER, password=FRITZBOX_PASS)
        hosts = fh.get_hosts_info()
        for index, host in enumerate(hosts, start=1):
            if host['status'] :
                # status = 'active' if host['status'] else  '-'
                ip = host['ip'] if host['ip'] else 'no IP'
                mac = host['mac'].lower() if host['mac'] else '-'
                hostname = host['name']
                try:
                    vendor = MacLookup().lookup(host['mac'])
                except:
                    vendor = "Prefix is not registered"

                sql.execute ("INSERT INTO Fritzbox_Network (FB_MAC, FB_IP, FB_Name, FB_Vendor) "+
                             "VALUES (?, ?, ?, ?) ", (mac, ip, hostname, vendor) )
    except Exception as e:
        print('        ...Skipped. Could not connect to FritzBox')
        print_log(f"{e}")

#-------------------------------------------------------------------------------
def read_mikrotik_leases():

    sql.execute ("DELETE FROM Mikrotik_Network")

    if not MIKROTIK_ACTIVE:
        return

    print('    Mikrotik Method...')

    try:
        import routeros_api
    except:
        print('        Missing python package')
        return

    try:
        data = []
        conn = routeros_api.RouterOsApiPool(MIKROTIK_IP, MIKROTIK_USER, MIKROTIK_PASS, plaintext_login=True)
        api = conn.get_api()
        ret = api.get_resource('/ip/dhcp-server/lease').get()
        conn.disconnect()
        for row in ret:
            if 'active-mac-address' in row:
                mac = row['active-mac-address'].lower()
                ip = row['active-address']
                hostname = row.get('host-name','')
                try:
                    vendor = MacLookup().lookup(mac)
                except:
                    vendor = "Prefix is not registered"

                sql.execute ("INSERT INTO Mikrotik_Network (MT_MAC, MT_IP, MT_Name, MT_Vendor) "+
                             "VALUES (?, ?, ?, ?) ", (mac, ip, hostname, vendor) )
    except Exception as e:
        print('        ...Skipped. Could not connect to Mikrotik Router')
        print_log(f"{e}")

#-------------------------------------------------------------------------------
def read_unifi_clients():

    sql.execute ("DELETE FROM Unifi_Network")

    if not UNIFI_ACTIVE:
        return

    print('    UniFi Method...')

    try:
        from pyunifi.controller import Controller
    except:
        print('        Missing python package')
        return

    # Enable self signed SSL / no warnings
    requests.packages.urllib3.disable_warnings(InsecureRequestWarning)

    try:
        UNIFI_API_VERSION = UNIFI_API
    except NameError: # variable not defined, use a default
        UNIFI_API_VERSION = 'v5'

    try:
        data = []
        c = Controller(UNIFI_IP,UNIFI_USER,UNIFI_PASS,8443,UNIFI_API_VERSION,'default',ssl_verify=False)
        clients = c.get_clients()
        for row in clients:
            mac = row['mac'].lower()
            ip = row.get('ip','no IP')
            hostname = row.get('hostname',row.get('name',''))
            vendor = row.get('oui',None)
            if not vendor:
                try:
                    vendor = MacLookup().lookup(mac)
                except:
                    vendor = "(unknown)"

            sql.execute ("INSERT INTO Unifi_Network (UF_MAC, UF_IP, UF_Name, UF_Vendor) "+
                         "VALUES (?, ?, ?, ?) ", (mac, ip, hostname, vendor) )

    except Exception as e:
        print('        ...Skipped. Could not connect to UniFi Controller')
        print_log(f"{e}")

#-------------------------------------------------------------------------------
def read_openwrt_clients():

    sql.execute ("DELETE FROM Openwrt_Network")

    if not OPENWRT_ACTIVE:
        return

    print('    OpenWRT Method...')

    try:
        from openwrt_luci_rpc import OpenWrtRpc
    except:
        print('        Missing python package')
        return

    try:
        router = OpenWrtRpc(str(OPENWRT_IP), str(OPENWRT_USER), str(OPENWRT_PASS))
        result = router.get_all_connected_devices(only_reachable=True)

        for device in result:
            if str(device.hostname) == 'None':
                hostname = '(unknown)'
            else:
                hostname = device.hostname

            sql.execute ("INSERT INTO Openwrt_Network (OWRT_MAC, OWRT_IP, OWRT_Name, OWRT_Vendor) "+
                         "VALUES (?, ?, ?, ?) ", (device.mac.lower(), device.ip, hostname, '(unknown)') )

    except Exception as e:
        print(f"        ...Skipped. Could not connect to OpenWRT device")
        print_log(f"{e}")

#-------------------------------------------------------------------------------
def read_asuswrt_clients():

    sql.execute ("DELETE FROM Asuswrt_Network")

    if not ASUSWRT_ACTIVE:
        return

    print('    AsusWRT Method...')

    try:
        from asusrouter import AsusRouter
        from asusrouter.modules.data import AsusData
    except:
        print('        Missing python package')
        return

    try:
        attempt = 0
        max_attempts = 5

        result = None
        while not result and attempt < max_attempts:
            result = asyncio.run(collect_asuswrt_data(AsusRouter, AsusData))
            attempt += 1
            if not result:
                asyncio.run(asyncio.sleep(5))  # 5 sec delay

        if not result:
            print(f"        No results received after {max_attempts} attempts")

        for client in result.values():
            hostname = client["name"] or "(unknown)"
            ip_address = client["ip_address"]
            mac = client["mac"]
            vendor = client["vendor"]
            if vendor == "None" or vendor is None:
                vendor = "(unknown)"
            ip_method = client["ip_method"]

            # print(f"{hostname} - {ip_address} - {mac} - {vendor}")

            sql.execute ("INSERT INTO Asuswrt_Network (ASUS_MAC, ASUS_IP, ASUS_Name, ASUS_Vendor, ASUS_Method) "+
                        "VALUES (?, ?, ?, ?, ?) ", (mac.lower(), ip_address, hostname, vendor, ip_method))

    except Exception as e:
        print(f"        ...Skipped. Could not connect to Asus Router")
        print_log(f"{e}")

#-------------------------------------------------------------------------------
async def collect_asuswrt_data(AsusRouter,AsusData):
    async with aiohttp.ClientSession() as session:
        router = AsusRouter(
            hostname=ASUSWRT_IP,
            username=ASUSWRT_USER,
            password=ASUSWRT_PASS,
            use_ssl=ASUSWRT_SSL,
            cache_time=2, 
            session=session,
        )

        connected = await router.async_connect()
        # print(f"Verbindung erfolgreich: {connected}")
        if not connected:
            return

        try:
            clients_data = await router.async_get_data(AsusData.CLIENTS)
            
            filtered_clients = {
                mac: {
                    'name': client.description.name,
                    'ip_address': client.connection.ip_address,
                    'mac': mac,
                    'vendor': client.description.vendor,
                    'ip_method': client.connection.ip_method.name
                }
                for mac, client in clients_data.items() if client.connection.online
            }

            if filtered_clients:
                return filtered_clients
            else:
                return {}
        
        except Exception as e:
            print(f"        Connection error occurred: {e}")
            print_log(f"{e}")

        await router.async_disconnect()
        # print("\nVerbindung sauber getrennt.")

#-------------------------------------------------------------------------------
def pfsense_connect(endpoint,topic):
    global PFSENSE_PORT

    try:
        PFSENSE_PORT = int(PFSENSE_PORT)
    except (TypeError, ValueError):
        print(f"        ...{topic} Request canceled: Incorrect Port.")
        return None

    protocol = "https" if PFSENSE_SSL else "http"
    port = str(PFSENSE_PORT)

    url = f"{protocol}://{PFSENSE_IP}:{port}{endpoint}"
    headers = {
        "X-API-Key": PFSENSE_APIKEY,
        "Accept": "application/json"
    }

    try:
        response = requests.get(url, headers=headers, verify=False, timeout=10)
        if response.status_code == 200:
            return response.json()
        else:
            print(f"        ...❌ Error {response.status_code}: {response.text}")
            return None

    except requests.Timeout:
        print(f"        ...{topic} Request canceled: Timeout reached.")
        return None

    except requests.RequestException as e:
        print(f"        ...{topic} Skipped - Connection error")
        #DEBUG
        # if topic == "DHCP":
        #     with open("dhcp.json", "r", encoding="utf-8") as f:
        #         response = f.read()
        # if topic == "ARP":
        #     with open("arp.json", "r", encoding="utf-8") as f:
        #         response = f.read()
        # return json.loads(response)
        return None

#-------------------------------------------------------------------------------
def read_pfsense_clients():

    requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
    pfsense_dhcpleases = ""
    pfsense_arptable = ""
    pfsense_local_interfaces = ""

    if PFSENSE_ACTIVE:
        # empty Table
        sql.execute ("DELETE FROM pfsense_Network")

        print(f"    pfSense Method...")
        endpoint = "/api/v2/status/dhcp_server/leases?limit=0&offset=0&sort_order=SORT_ASC&sort_flags=SORT_STRING"
        result = pfsense_connect(endpoint,"DHCP")
        print_log(result)
        if result:
            pfsense_dhcpleases = json.dumps(result, indent=4)

        endpoint = "/api/v2/diagnostics/arp_table?limit=0&offset=0"
        result = pfsense_connect(endpoint,"ARP")
        print_log(result)
        if result:
            pfsense_arptable = json.dumps(result, indent=4)

        endpoint = "/api/v2/interface/available_interfaces"
        result = pfsense_connect(endpoint,"Interfaces")
        print_log(result)
        if result:
            pfsense_local_interfaces = json.dumps(result, indent=4)

        pfsense_save_dhcp_data(pfsense_dhcpleases)
        pfsense_save_arp_data(pfsense_arptable, pfsense_local_interfaces)
        pfsense_mark_local_interfaces(pfsense_local_interfaces)

        closeDB()
    else:
        return

#-------------------------------------------------------------------------------
def pfsense_mark_local_interfaces(interfaces):

    if isinstance(interfaces, str):
        try:
            interfaces = json.loads(interfaces)
        except json.JSONDecodeError:
            print_log("        ...❌ Error: invalid JSON-format (interfaces)")
            return [], []

    local_interfaces = []
    if not interfaces or "data" not in interfaces:
        print_log("⚠️ no local interfaces were found")
        return local_interfaces

    for entry in interfaces["data"]:
        mac = (entry.get("mac") or "").strip().lower()
        in_use_by = (entry.get("in_use_by") or "").strip()

        if not mac:
            continue

        local_interfaces.append({
            "MAC": mac,
            "in_use_by": in_use_by
        })

    for entry in local_interfaces:
        mac = entry['MAC']
        in_use_by = entry['in_use_by']

        # Update pfsense inferfaces
        sql_update = """UPDATE pfsense_Network
                        SET PF_Name = ?
                        WHERE PF_MAC = ? AND PF_Name = '(unknown)';"""

        new_name = f"pfSense {in_use_by}"
        sql.execute(sql_update, (new_name, mac))

    print_log(local_interfaces)

#-------------------------------------------------------------------------------
def pfsense_save_dhcp_data(pfsense_dhcpleases):

    if isinstance(pfsense_dhcpleases, str):
        try:
            pfsense_dhcpleases = json.loads(pfsense_dhcpleases)
        except json.JSONDecodeError:
            print_log("        ...❌ Error: invalid JSON-format (pfsense_dhcpleases)")
            return [], []

    pfsense_network_dhcp = []

    # Check if "data" exists
    if not pfsense_dhcpleases or "data" not in pfsense_dhcpleases:
        print_log("⚠️ no DHCP-Leases were found")
        return pfsense_network_dhcp

    for entry in pfsense_dhcpleases["data"]:
        mac = entry.get("mac", "").strip().lower()
        ip = entry.get("ip", "").strip()
        hostname = entry.get("hostname") or "(unknown)"
        ends_str = entry.get("ends")

        # convert "ends" in UNIX-Timestamp
        try:
            ends_ts = int(datetime.datetime.strptime(ends_str, "%Y/%m/%d %H:%M:%S").timestamp())
        except (ValueError, TypeError):
            ends_ts = 0

        pf_connected = False
        # Only active hosts for current scan
        if entry.get("online_status") == "active/online":
            pf_connected = True

        # All hosts für dhcp list
        pfsense_network_dhcp.append({
            "MAC": mac,
            "IP": ip,
            "Name": hostname,
            "Vendor": "",
            "Method": "pfSense",
            "Interface": "",
            "Custom_a": "",
            "Custom_b": "",
            "Connected": pf_connected,
            "Datetime": ends_ts
        })

    sql_insert = """INSERT INTO pfsense_Network
                    (PF_MAC, PF_IP, PF_Name, PF_Vendor, PF_Method, PF_Interface,
                     PF_Custom_a, PF_Custom_b, PF_Connected, PF_Datetime)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);"""

    for entry in pfsense_network_dhcp:
        sql.execute(sql_insert, (
            entry["MAC"],
            entry["IP"],
            entry["Name"],
            entry["Vendor"],
            entry["Method"],
            entry["Interface"],
            entry["Custom_a"],
            entry["Custom_b"],
            entry["Connected"],
            entry["Datetime"]
        ))

    sql_connection.commit()

    print_log(pfsense_network_dhcp)

#-------------------------------------------------------------------------------
def pfsense_save_arp_data(pfsense_arptable, interfaces):

    if isinstance(pfsense_arptable, str):
        try:
            pfsense_arptable = json.loads(pfsense_arptable)
        except json.JSONDecodeError:
            print_log("        ...❌ Error: invalid JSON-format (pfsense_arptable)")
            return [], []

    if isinstance(interfaces, str):
        try:
            interfaces = json.loads(interfaces)
        except json.JSONDecodeError:
            print_log("        ...❌ Error: invalid JSON-format (interfaces)")
            return [], []

    pfsense_arp_list = []
    # Check if "data" exists
    if not pfsense_arptable or "data" not in pfsense_arptable:
        print_log("⚠️ no valid ARP-data found.")
        return pfsense_arp_list

    local_interfaces = []
    if not interfaces or "data" not in interfaces:
        return local_interfaces

    for entry in interfaces["data"]:
        mac = (entry.get("mac") or "").strip().lower()
        in_use_by = (entry.get("in_use_by") or "").strip()

        if not mac:
            continue

        local_interfaces.append({
            "MAC": mac
        })

    for entry in pfsense_arptable["data"]:
        mac = entry.get("mac_address", "").strip().lower()
        ip = entry.get("ip_address", "").strip()
        hostname = entry.get("hostname", "").strip()
        dnsresolve = entry.get("dnsresolve", "").strip()
        interface = entry.get("interface", "").strip()
        arpexpires = entry.get("expires", "").strip()

        if interface.lower() in (i.lower() for i in PFSENSE_EXCLUDE_INT) and all(mac != entry["MAC"] for entry in local_interfaces):
            continue

        # Get Arp exp. seconds
        match = re.search(r"Expires\s+in\s+(\d+)\s+seconds", arpexpires, flags=re.I)

        if match:
            seconds = match.group(1)
        else:
            seconds = ""

        # set Connected-Status
        if arpexpires.lower() == "permanent" or (seconds != "" and int(seconds) > 0):
            connected = True
        else:
            connected = False

        # Hostname-Regeln
        if hostname == "" or hostname == "?":
            if dnsresolve != "" and dnsresolve != "?":
                hostname = dnsresolve
            else:
                hostname = "(unknown)"

        # All hosts für arp list
        pfsense_arp_list.append({
            "MAC": mac,
            "IP": ip,
            "Name": hostname,
            "Vendor": "",
            "Method": "pfSense",
            "Interface": interface,
            "Custom_a": seconds,
            "Custom_b": "",
            "Connected": connected,
            "Datetime": ""
        })

    # SQL-queries
    sql_select = "SELECT PF_Name, PF_Interface, PF_Connected FROM pfsense_Network WHERE PF_MAC = ? COLLATE NOCASE;"
    sql_insert = """INSERT INTO pfsense_Network
                    (PF_MAC, PF_IP, PF_Name, PF_Vendor, PF_Method, PF_Interface,
                     PF_Custom_a, PF_Custom_b, PF_Connected, PF_Datetime)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);"""
    sql_update_name = "UPDATE pfsense_Network SET PF_Name = ? WHERE PF_MAC = ? COLLATE NOCASE;"
    sql_update_interface = "UPDATE pfsense_Network SET PF_Interface = ? WHERE PF_MAC = ? COLLATE NOCASE;"
    sql_update_connected = "UPDATE pfsense_Network SET PF_Connected = ? WHERE PF_MAC = ? COLLATE NOCASE;"

    for entry in pfsense_arp_list:
        mac = entry["MAC"]
        sql.execute(sql_select, (mac,))
        row = sql.fetchone()

        if row:
            # Record exists → update selectively
            db_name, db_interface, db_connected = row

            # update Interface, if empty in table and set in dict
            if (not db_interface or db_interface.strip() == "") and entry["Interface"]:
                sql.execute(sql_update_interface, (entry["Interface"], mac))

            # update Name, if empty in table and set in dict
            if (not db_name or db_name.strip() == "") and entry["Name"]:
                sql.execute(sql_update_name, (entry["Name"], mac))

            # update Connected, if empty in table and set in dict
            if (not db_connected) and entry["Connected"]:
                sql.execute(sql_update_connected, (1, mac))

        else:
            # arp Device not in table
            sql.execute(sql_insert, (
                entry["MAC"],
                entry["IP"],
                entry["Name"],
                entry["Vendor"],
                entry["Method"],
                entry["Interface"],
                entry["Custom_a"],
                entry["Custom_b"],
                entry["Connected"],
                entry["Datetime"]
            ))

        sql_connection.commit()

    print_log(pfsense_arp_list)

#-------------------------------------------------------------------------------
def read_DHCP_leases():
    # check DHCP Leases is active
    if not DHCP_ACTIVE :
        return

    print(f"    Pi-hole {PIHOLE_VERSION} DHCP Leases Method...")

    if PIHOLE_VERSION in (None, 5):
        read_DHCP_leases_five()
    elif PIHOLE_VERSION == 6:
        if not PIHOLE6_SES_VALID == True:
            pihole_six_api_auth()
        read_DHCP_leases_six()
    else:
        print('        ...Unsupported Version')

#-------------------------------------------------------------------------------
def read_DHCP_leases_five():
    # Read DHCP Leases
    data = []
    with open(DHCP_LEASES, 'r') as f:
        for line in f:
            row = line.rstrip().split()
            if len(row) == 5 :
                data.append (row)

    # Insert into PiAlert table
    sql.execute ("DELETE FROM DHCP_Leases")
    sql.executemany ("""INSERT INTO DHCP_Leases (DHCP_DateTime, DHCP_MAC,
                            DHCP_IP, DHCP_Name, DHCP_MAC2)
                        VALUES (?, ?, ?, ?, ?)
                     """, data)

#-------------------------------------------------------------------------------
def read_DHCP_leases_six():
    global PIHOLE6_URL
    global PIHOLE6_PASSWORD
    global PIHOLE6_SES_VALID
    global PIHOLE6_SES_SID
    global PIHOLE6_SES_CSRF

    if PIHOLE6_SES_VALID == True:

        sql.execute ("DELETE FROM DHCP_Leases")

        headers = {
            "X-FTL-SID": PIHOLE6_SES_SID,
            "X-FTL-CSRF": PIHOLE6_SES_CSRF
        }
        raw_deviceslist = requests.get(PIHOLE6_URL+'api/dhcp/leases', headers=headers, verify=False)

        result = {}
        deviceslist = raw_deviceslist.json()

        # Get Pi-hole local MAC-Adresses an IPs
        interfaces = get_pihole_interface_data()
        # Generate a theoretical lease period of +30min
        current_time = datetime.datetime.now()
        future_time = current_time + datetime.timedelta(minutes=30)
        dnsmasq_timestamp = int(future_time.timestamp()) 

        for device in deviceslist['leases']:
            # skip lo interface if present
            if device['hwaddr'] == "00:00:00:00:00:00":
                continue

            sql.execute("""INSERT INTO DHCP_Leases (DHCP_DateTime, DHCP_MAC,
                                DHCP_IP, DHCP_Name, DHCP_MAC2)
                                    VALUES (?, ?, ?, ?, ?)
                                 """, (device['expires'], device['hwaddr'], device['ip'], device['name'], device['clientid']))

        sql_connection.commit()

        if DHCP_INCL_SELF_TO_LEASES:
            # Add the Pi-hole interface data to the DHCP leases to have the possibility to import Pi-hole 
            # itself as well, even if it is not in the local Pi.Alert network.
            for mac, localips in interfaces.items():
                sql.execute("SELECT COUNT(*) FROM DHCP_Leases WHERE DHCP_MAC = ?", (mac,))
                mac_exists = sql.fetchone()[0]
                
                if mac_exists == 0:
                    sql.execute("""
                        INSERT INTO DHCP_Leases (DHCP_DateTime, DHCP_MAC, DHCP_IP, DHCP_Name, DHCP_MAC2)
                        VALUES (?, ?, ?, ?, ?)
                    """, (dnsmasq_timestamp, mac, localips[0], "Pi-hole", "*"))

            sql_connection.commit()

    else:
        print(f"        ...Skipped")
        return

#-------------------------------------------------------------------------------
def get_satellite_scans():
    sql.execute ("DELETE FROM Satellites_Network")
    if not SATELLITES_ACTIVE:
        return

    print('    Satellite Import...')

    print_log('        ...Mode detection')
    get_satellites = "SELECT sat_password, sat_token FROM Satellites"
    sql.execute(get_satellites)

    rows = sql.fetchall()
    satellite_list = {}
    for row in rows:
        sat_password, sat_token = row
        satellite_list[sat_token] = sat_password

    if SATELLITE_PROXY_MODE:
        print_log('        ...Proxy Mode')
        get_satellite_proxy_scans(satellite_list)
        decrypt_satellite_proxy_scans(satellite_list)

    process_satellites(satellite_list)
    cleanup_satellite_scans(satellite_list)

#-------------------------------------------------------------------------------
def process_satellites(satellite_list):
    print("        ...Processing satellite scans")

    WORKING_DIR = PIALERT_PATH + "/front/satellites/"
    for token, password in satellite_list.items():
        satellite_scanresult = WORKING_DIR+token+".json"
        if os.path.exists(satellite_scanresult):
            with open(satellite_scanresult, 'r') as file:
                data = json.load(file)

                try:
                    satellite_meta_data = data['satellite_meta_data'][0]
                    satellite_version = satellite_meta_data.get('satellite_version', "-")
                    satellite_meta_data_json = json.dumps(satellite_meta_data)
                except (KeyError, IndexError, TypeError):
                    satellite_version = "-"
                    satellite_meta_data_json = {}

                try:
                    config = data['satellite_scan_config'][0]
                except (KeyError, IndexError, TypeError):
                    config = {}

                scan_arp         = 1 if config.get('scan_arp') else 0
                scan_fritzbox    = 1 if config.get('scan_fritzbox') else 0
                scan_mikrotik    = 1 if config.get('scan_mikrotik') else 0
                scan_unifi       = 1 if config.get('scan_unifi') else 0
                scan_openwrt     = 1 if config.get('scan_openwrt') else 0
                scan_asuswrt     = 1 if config.get('scan_asuswrt') else 0
                scan_pihole_net  = 1 if config.get('scan_pihole_net') else 0
                scan_pihole_dhcp = 1 if config.get('scan_pihole_dhcp') else 0
                scan_pfsense     = 1 if config.get('scan_pfsense') else 0

                for result in data['scan_results']:
                    if result['cur_ScanMethod'] != 'Internet Check' and result['cur_ScanMethod'] != 'Pi-hole DHCP':
                        sat_MAC = result['cur_MAC'].lower()
                        sat_IP = result['cur_IP']
                        sat_hostname = result['cur_hostname']
                        sat_Vendor = result['cur_Vendor']
                        sat_ScanMethod = result['cur_ScanMethod']
                        sat_ScanSource = result['cur_SatelliteID']                

                        sql.execute("""SELECT 1 FROM Satellites_Network WHERE Sat_MAC = ?""", (sat_MAC,))
                        if sql.fetchone() is None:
                            sql.execute("""INSERT INTO Satellites_Network (
                                              Sat_MAC, Sat_IP, Sat_Name, Sat_Vendor, Sat_ScanMethod, Sat_Token)
                                              VALUES (?, ?, ?, ?, ?, ?)""",
                                              (sat_MAC, sat_IP, sat_hostname, sat_Vendor, sat_ScanMethod, sat_ScanSource))

                    # Satellite WAN IP
                    if result['cur_ScanMethod'] == 'Internet Check':
                        sat_MAC = result['cur_MAC']
                        sat_IP = result['cur_IP']
                        sat_hostname = "Satellite - Internet (" + satellite_meta_data['hostname'] + ")"
                        sat_Vendor = ""
                        sat_ScanMethod = result['cur_ScanMethod']
                        sat_ScanSource = result['cur_SatelliteID']                

                        sql.execute("""SELECT 1 FROM Satellites_Network WHERE Sat_MAC = ?""", (sat_MAC,))
                        if sql.fetchone() is None:
                            sql.execute("""INSERT INTO Satellites_Network (
                                              Sat_MAC, Sat_IP, Sat_Name, Sat_Vendor, Sat_ScanMethod, Sat_Token)
                                              VALUES (?, ?, ?, ?, ?, ?)""",
                                              (sat_MAC, sat_IP, sat_hostname, sat_Vendor, sat_ScanMethod, sat_ScanSource))


                    # DHCP results are saved in another table
                    if result['cur_ScanMethod'] == 'Pi-hole DHCP':
                        # skip lo interface if present
                        if result['cur_hwaddr'] == "00:00:00:00:00:00":
                            continue

                        sat_MAC = result['cur_hwaddr'].lower()
                        sat_IP = result['cur_ip']
                        sat_hostname = result['cur_name']

                        sql.execute("""SELECT 1 FROM DHCP_Leases WHERE DHCP_MAC = ?""", (sat_MAC,))
                        if sql.fetchone() is None:
                            sql.execute("""INSERT INTO DHCP_Leases (DHCP_DateTime, DHCP_MAC,
                                                DHCP_IP, DHCP_Name, DHCP_MAC2)
                                                    VALUES (?, ?, ?, ?, ?)
                                                 """, (result['cur_expires'], result['cur_hwaddr'], result['cur_ip'], result['cur_name'], result['cur_clientid']))
                # Save Satellite Configuration
                satUpdateTime = datetime.datetime.now()
                satUpdateTime = satUpdateTime.replace(microsecond=0)
                sql.execute("""UPDATE Satellites 
                                SET 
                                    sat_lastupdate = ?, 
                                    sat_remote_version = ?,
                                    sat_conf_scan_arp = ?,
                                    sat_conf_scan_fritzbox = ?,
                                    sat_conf_scan_mikrotik = ?,
                                    sat_conf_scan_unifi = ?,
                                    sat_conf_scan_openwrt = ?,
                                    sat_conf_scan_asuswrt = ?,
                                    sat_conf_scan_pihole_net = ?,
                                    sat_conf_scan_pihole_dhcp = ?,
                                    sat_conf_scan_pfsense = ?,
                                    sat_host_data = ?
                                WHERE sat_token = ?""", (satUpdateTime, satellite_version, scan_arp, scan_fritzbox, scan_mikrotik, scan_unifi, scan_openwrt, scan_asuswrt, scan_pihole_net, scan_pihole_dhcp, scan_pfsense, satellite_meta_data_json, token))

#-------------------------------------------------------------------------------
def get_satellite_proxy_scans(satellite_list):
    SAVE_DIR = PIALERT_PATH + "/front/satellites/"
    received_results = 0

    for token, password in satellite_list.items():
        url = f"{SATELLITE_PROXY_URL}?mode=get&token={token}"
        try:
            requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
            response = requests.get(url, verify=False)  # Accept insecure SSL connections
            if response.status_code == 200:
                received_results += 1
                file_name = f"encrypted_{token}"
                save_path = os.path.join(SAVE_DIR, file_name)
                with open(save_path, 'wb') as file:
                    file.write(response.content)

        except requests.exceptions.SSLError:
            pass
        except requests.RequestException as e:
            print_log(f"        Download failed for token {token}")
            pass

    print(f"        ...Get data from proxy ({received_results})")

#-------------------------------------------------------------------------------
def decrypt_satellite_proxy_scans(satellite_list):
    print_log('        ...decrypt data from proxy')
    WORKING_DIR = PIALERT_PATH + "/front/satellites/"

    for token, password in satellite_list.items():
        
        if os.path.exists(WORKING_DIR+"encrypted_"+token):
            openssl_command = [
                "openssl", "enc", "-d", "-aes-256-cbc", "-in", WORKING_DIR+"encrypted_"+token,
                "-pbkdf2", "-pass", "pass:{}".format(password)
            ]

            with subprocess.Popen(openssl_command, stdout=subprocess.PIPE) as proc:
                decrypted_data = proc.stdout.read()

            decrypted_dict = json.loads(decrypted_data.decode('utf-8'))

            with open(WORKING_DIR+token+'.json', 'w') as outfile:
                json.dump(decrypted_dict, outfile, indent=4)

            os.remove(WORKING_DIR+"encrypted_"+token) 

#-------------------------------------------------------------------------------
def cleanup_satellite_scans(satellite_list):
    print_log('        ...cleanup satellite scans')
    WORKING_DIR = PIALERT_PATH + "/front/satellites/"

    for token, password in satellite_list.items():
        if os.path.exists(WORKING_DIR+"encrypted_"+token):
            os.remove(WORKING_DIR+"encrypted_"+token)

        if os.path.exists(WORKING_DIR+token+".json"):
            os.remove(WORKING_DIR+token+".json") 

#-------------------------------------------------------------------------------
def update_scan_validation():
    print('    Update Scan Validation...')
    # 1. set dev_Scan_Validation_State to 0 for devices that are in CurrentScan and have dev_Scan_Validation > 0
    sql.execute("""
        UPDATE Devices
        SET dev_Scan_Validation_State = 0
        WHERE dev_Scan_Validation > 0
        AND dev_MAC IN (SELECT cur_MAC FROM CurrentScan)
    """)
    
    # 2. find devices to be inserted in CurrentScan and save them in a list
    sql.execute("""
        SELECT dev_ScanCycle, dev_MAC, dev_LastIP, dev_Vendor, dev_ScanSource
        FROM Devices
        WHERE dev_Scan_Validation > 0
        AND dev_Scan_Validation_State < dev_Scan_Validation
        AND dev_MAC NOT IN (SELECT cur_MAC FROM CurrentScan)
    """)
    devices_to_insert = sql.fetchall()
    if PRINT_LOG:
        print('----------> Dump: Query (Validation)')
        for row in devices_to_insert:
            print(row['dev_MAC'], row['dev_LastIP'])
        print('----------> Dump: End')

    # 3. Add the devices to CurrentScan
    sql.executemany("""
        INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod, cur_ScanSource)
        VALUES (?, ?, ?, ?, '(Validation pending)', ?)
    """, devices_to_insert)
    
    # 4. increase dev_Scan_Validation_State by 1 for the devices saved in point 2
    mac_addresses = [device[1] for device in devices_to_insert]
    if mac_addresses:
        sql.executemany("""
            UPDATE Devices
            SET dev_Scan_Validation_State = dev_Scan_Validation_State + 1
            WHERE dev_MAC = ?
        """, [(mac,) for mac in mac_addresses])

#-------------------------------------------------------------------------------
def save_scanned_devices(p_arpscan_devices, p_cycle_interval):
    # Delete previous scan data
    sql.execute ("DELETE FROM CurrentScan WHERE cur_ScanCycle = ?", (cycle,))

    # Insert new arp-scan devices
    sql.executemany ("INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, "+
                     "    cur_IP, cur_Vendor, cur_ScanMethod) "+
                     "VALUES ("+ cycle + ", :mac, :ip, :hw, 'arp-scan')",
                     p_arpscan_devices) 

    # Insert Pi-hole devices
    sql.execute ("""
        INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod)
        SELECT ?, PH_MAC, PH_IP, PH_Vendor, 'Pi-hole'
        FROM PiHole_Network
        WHERE PH_LastQuery >= ?
          AND NOT EXISTS (SELECT 'X' FROM CurrentScan
                          WHERE cur_MAC = PH_MAC
                            AND cur_ScanCycle = ? )""",
        (cycle, (int(startTime.strftime('%s')) - 60 * p_cycle_interval), cycle) )
    
    # External source import
    insert_ext_sources(sql, cycle, 'Fritzbox_Network', 'FB_MAC', 'FB_IP', 'FB_Vendor', 'Fritzbox')
    insert_ext_sources(sql, cycle, 'Mikrotik_Network', 'MT_MAC', 'MT_IP', 'MT_Vendor', 'Mikrotik')
    insert_ext_sources(sql, cycle, 'Unifi_Network', 'UF_MAC', 'UF_IP', 'UF_Vendor', 'UniFi')
    insert_ext_sources(sql, cycle, 'Openwrt_Network', 'OWRT_MAC', 'OWRT_IP', 'OWRT_Vendor', 'OpenWRT')
    insert_ext_sources(sql, cycle, 'Asuswrt_Network', 'ASUS_MAC', 'ASUS_IP', 'ASUS_Vendor', 'AsusWRT')

    # Insert pfSense import
    sql.execute("""
        INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod)
        SELECT ?, PF_MAC, PF_IP, PF_Vendor, PF_Method
        FROM pfsense_Network
        WHERE PF_Connected = 1
          AND NOT EXISTS (
              SELECT 1 FROM CurrentScan
              WHERE cur_MAC = PF_MAC COLLATE NOCASE
                AND cur_ScanCycle = ?
          )
    """, (cycle, cycle))


    # Insert Satellite devices
    sql.execute ("""
        INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod, cur_ScanSource)
        SELECT ?, Sat_MAC, Sat_IP, Sat_Vendor, Sat_ScanMethod, Sat_Token
        FROM Satellites_Network
        WHERE NOT EXISTS (SELECT 'X' FROM CurrentScan
                          WHERE cur_MAC = Sat_MAC )""",
        (cycle) )

    # Scan Validation
    update_scan_validation()

    if not OFFLINE_MODE :
        # Check Internet connectivity
        internet_IP = get_internet_IP()
    else :
        internet_IP = "Offline Mode"
        # TESTING - Force IP
        # internet_IP = ""
    if internet_IP != "" :
        sql.execute ("""INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod)
                        VALUES (?, 'Internet', ?, Null, 'queryDNS') """, (cycle, internet_IP) )

    local_mac_cmd = ["/sbin/ifconfig `ip -o route get 1 | sed 's/^.*dev \\([^ ]*\\).*$/\\1/;q'` | grep ether | awk '{print $2}'"]
    local_mac = subprocess.Popen (local_mac_cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT).communicate()[0].decode().strip()
    
    print_log(f"        ...Local Interfaces")
    print_log(f"{local_mac}")

    # local_ip_cmd = ["ip route list default | awk {'print $7'}"]
    local_ip_cmd = ["ip -o route get 1 | sed 's/^.*src \\([^ ]*\\).*$/\\1/;q'"]
    local_ip = subprocess.Popen (local_ip_cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT).communicate()[0].decode().strip()

    print_log(f"{local_ip}\n")

    # Check if local mac has been detected with other methods

    sql.execute("SELECT COUNT(*) FROM CurrentScan WHERE cur_ScanCycle = ? AND cur_MAC = ? ", (cycle, local_mac) )
    # if sql.fetchone()[0] == 0 :
    #     sql.execute ("INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod) "+
    #                  "VALUES ( ?, ?, ?, Null, 'local_MAC') ", (cycle, local_mac, local_ip) )
    
    if sql.fetchone()[0] == 0:
        sql.execute(
            "INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod) "
            "VALUES (?, ?, ?, NULL, 'local_MAC')",
            (cycle, local_mac, local_ip)
        )
    else:
        sql.execute(
            "UPDATE CurrentScan "
            "SET cur_IP = ? "
            "WHERE cur_ScanCycle = ? AND cur_MAC = ?",
            (local_ip, cycle, local_mac)
        )

#-------------------------------------------------------------------------------
# def insert_ext_sources(sql, cycle, network_table, mac_column, ip_column, vendor_column, scan_method):
#     sql.execute(f"""INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, 
#                         cur_IP, cur_Vendor, cur_ScanMethod)
#                     SELECT ?, {mac_column}, {ip_column}, {vendor_column}, ?
#                     FROM {network_table}
#                     WHERE NOT EXISTS (SELECT 'X' FROM CurrentScan
#                                       WHERE cur_MAC = {mac_column} )""",
#                 (cycle, scan_method))

def insert_ext_sources(sql, cycle, network_table, mac_column, ip_column, vendor_column, scan_method):
    sql.execute("BEGIN TRANSACTION")

    try:
        # INSERT for all MACs that do not yet exist
        sql.execute(f"""
            INSERT INTO CurrentScan (cur_ScanCycle, cur_MAC, cur_IP, cur_Vendor, cur_ScanMethod)
            SELECT ?, {mac_column}, {ip_column}, {vendor_column}, ?
            FROM {network_table} nt
            WHERE NOT EXISTS (
                SELECT 1 FROM CurrentScan cs WHERE cs.cur_MAC = nt.{mac_column}
            )
        """, (cycle, scan_method))

        # UPDATE for all existing MACs with cur_IP = 'no IP'
        sql.execute(f"""
            UPDATE CurrentScan
            SET cur_IP = (
                SELECT {ip_column} FROM {network_table} nt
                WHERE nt.{mac_column} = CurrentScan.cur_MAC
            )
            WHERE cur_IP = 'no IP'
            AND EXISTS (
                SELECT 1 FROM {network_table} nt
                WHERE nt.{mac_column} = CurrentScan.cur_MAC
            )
        """)

        # Commit
        sql.execute("COMMIT")
    except Exception as e:
        # Rollback
        sql.execute("ROLLBACK")
        # raise e


#-------------------------------------------------------------------------------
def dump_all_resulttables():
    if PRINT_LOG:
        sql.execute('SELECT cur_MAC, cur_IP FROM CurrentScan')
        rows = sql.fetchall()
        print('----------> Dump: Table (CurrentScan)')
        for row in rows:
            print(f"MAC: {row[0]}, IP: {row[1]}")
        print('----------> Dump: End')
        sql.execute('SELECT DHCP_MAC, DHCP_IP, DHCP_Name FROM DHCP_Leases')
        rows = sql.fetchall()
        print('----------> Dump: Table (DHCP Leases)')
        for row in rows:
            print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
        print('----------> Dump: End')
        sql.execute('SELECT PH_MAC, PH_IP, PH_Name FROM PiHole_Network')
        print('----------> Dump: Table (PiHole Network)')
        for row in rows:
            print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
        print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='Fritzbox_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT FB_MAC, FB_IP, FB_Name FROM Fritzbox_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (Fritzbox Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
            print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='Mikrotik_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT MT_MAC, MT_IP, MT_Name FROM Mikrotik_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (Mikrotik Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
            print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='Unifi_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT UF_MAC, UF_IP, UF_Name FROM Unifi_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (UniFi Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
            print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='Asuswrt_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT ASUS_MAC, ASUS_IP, ASUS_Name FROM Asuswrt_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (AsusWRT Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
            print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='Openwrt_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT OWRT_MAC, OWRT_IP, OWRT_Name FROM Openwrt_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (OpenWRT Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
            print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='pfsense_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT PF_MAC, PF_IP, PF_Name FROM pfsense_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (pfSense Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}")
            print('----------> Dump: End')
        sql.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='Satellites_Network';")
        table_exists = sql.fetchone()
        if table_exists:
            sql.execute('SELECT Sat_MAC, Sat_IP, Sat_Name, Sat_Token FROM Satellites_Network')
            rows = sql.fetchall()
            print('----------> Dump: Table (Satellites Network)')
            for row in rows:
                print(f"MAC: {row[0]}, IP: {row[1]}, Name: {row[2]}, Token: {row[3]}")
            print('----------> Dump: End')

#-------------------------------------------------------------------------------
def remove_entries_from_devicelist():
    try:
        if HOSTNAME_IGNORE_LIST:
            print_log(f'        {len(HOSTNAME_IGNORE_LIST)} Hostnames are ignored during the scan')

            matched_macs = set()

            query = f"SELECT dev_MAC, dev_Name FROM Devices"
            sql.execute(query)
            for mac, name in sql.fetchall():
                if hostname_ignorelist_filter(name):
                    matched_macs.add(mac)

            print_log(f'        Matches after hostname resolving')
            print_log('\n'.join(matched_macs))

            work_tables = {
                'CurrentScan': 'cur_MAC',
                'Devices': 'dev_MAC',
                'Events': 'eve_MAC'
            }

            for tabelle, mac_spalte in work_tables.items():
                for mac in matched_macs:
                    sql.execute(
                        f"DELETE FROM {tabelle} WHERE {mac_spalte} = ? COLLATE NOCASE",
                        (mac,)
                    )

        else:
            print_log('        Hostname-Ignore list is empty')

    except NameError:
        print_log("        No Hostname-Ignore list defined")


#-------------------------------------------------------------------------------
def remove_entries_from_table():
    try:
        if MAC_IGNORE_LIST:
            print_log(f'        {len(MAC_IGNORE_LIST)} MACs/MAC ranges are ignored during the scan')

            table_column_map = {
                'CurrentScan': 'cur_MAC',
                'PiHole_Network': 'PH_MAC',
                'DHCP_Leases': 'DHCP_MAC',
                'Fritzbox_Network': 'FB_MAC',
                'Mikrotik_Network': 'MT_MAC',
                'Unifi_Network': 'UF_MAC',
                'Openwrt_Network': 'OWRT_MAC',
                'Asuswrt_Network': 'ASUS_MAC',
                'pfsense_Network': 'PF_MAC'
            }

            for table, column in table_column_map.items():
                if table not in table_column_map or column != table_column_map[table]:
                    continue

                # Bedingung als LIKE mit Platzhaltern
                conditions = [f"{column} LIKE ?" for _ in MAC_IGNORE_LIST]
                where_clause = ' OR '.join(conditions)
                query = f'DELETE FROM {table} WHERE {where_clause}'

                # Parameter vorbereiten
                like_params = [f"{mac}%" for mac in MAC_IGNORE_LIST]
                sql.execute(query, like_params)
        else:
            print_log('        MAC-Ignore list is empty')

    except NameError:
        print_log("        No MAC-Ignore list defined")

    try:
        if IP_IGNORE_LIST:
            print_log(f'        {len(IP_IGNORE_LIST)} IPs/IP ranges are ignored during the scan')

            table_column_map = {
                'CurrentScan': 'cur_IP',
                'PiHole_Network': 'PH_IP',
                'DHCP_Leases': 'DHCP_IP',
                'Fritzbox_Network': 'FB_IP',
                'Mikrotik_Network': 'MT_IP',
                'Unifi_Network': 'UF_IP',
                'Openwrt_Network': 'OWRT_IP',
                'Asuswrt_Network': 'ASUS_IP',
                'pfsense_Network': 'PF_IP'
            }

            for table, column in table_column_map.items():
                if table not in table_column_map or column != table_column_map[table]:
                    continue

                conditions = [f"{column} LIKE ?" for _ in IP_IGNORE_LIST]
                where_clause = ' OR '.join(conditions)
                query = f'DELETE FROM {table} WHERE {where_clause}'

                like_params = [f"{ip}%" for ip in IP_IGNORE_LIST]
                sql.execute(query, like_params)
        else:
            print_log('        IP-Ignore list is empty')

    except NameError:
        print_log("        No IP-Ignore list defined")

    try:
        if HOSTNAME_IGNORE_LIST:
            print_log(f'        {len(HOSTNAME_IGNORE_LIST)} Hostnames are ignored during the scan')
            source_tables = [
                ("PiHole_Network", "PH_MAC", "PH_Name"),
                ("DHCP_Leases", "DHCP_MAC", "DHCP_Name"),
                ("Fritzbox_Network", "FB_MAC", "FB_Name"),
                ("Mikrotik_Network", "MT_MAC", "MT_Name"),
                ("Unifi_Network", "UF_MAC", "UF_Name"),
                ("Openwrt_Network", "OWRT_MAC", "OWRT_Name"),
                ("Asuswrt_Network", "ASUS_MAC", "ASUS_Name"),
                ("pfsense_Network", "PF_MAC", "PF_Name"),
            ]

            matched_macs = set()

            for map_tablename, map_mac, map_name in source_tables:
                query = f"SELECT {map_mac}, {map_name} FROM {map_tablename}"
                sql.execute(query)
                for mac, name in sql.fetchall():
                    if hostname_ignorelist_filter(name):
                        matched_macs.add(mac)

            work_tables = {
                'CurrentScan': 'cur_MAC',
                'PiHole_Network': 'PH_MAC',
                'DHCP_Leases': 'DHCP_MAC',
                'Fritzbox_Network': 'FB_MAC',
                'Mikrotik_Network': 'MT_MAC',
                'Unifi_Network': 'UF_MAC',
                'Openwrt_Network': 'OWRT_MAC',
                'Asuswrt_Network': 'ASUS_MAC',
                'pfsense_Network': 'PF_MAC'
            }

            for tabelle, mac_spalte in work_tables.items():
                for mac in matched_macs:
                    sql.execute(
                        f"DELETE FROM {tabelle} WHERE {mac_spalte} = ? COLLATE NOCASE",
                        (mac,)
                    )

        else:
            print_log('        Hostname-Ignore list is empty')

    except NameError:
        print_log("        No Hostname-Ignore list defined")

#-------------------------------------------------------------------------------
def hostname_ignorelist_filter(hostname: str) -> bool:
    hostname = hostname.lower()
    for f in HOSTNAME_IGNORE_LIST:
        f = f.lower()
        if f.startswith("*") and f.endswith("*"):
            if f.strip("*") in hostname:
                return True
        elif f.startswith("*"):
            if hostname.endswith(f[1:]):
                return True
        elif f.endswith("*"):
            if hostname.startswith(f[:-1]):
                return True
        else:
            if hostname == f:
                return True
    return False

#-------------------------------------------------------------------------------
def print_scan_stats():
    # Devices Detected
    sql.execute ("""SELECT COUNT(*) FROM CurrentScan
                    WHERE cur_ScanCycle = ? """,
                    (cycle,))
    print('    Devices Detected.......:', str (sql.fetchone()[0]) )

    scan_methods = [
        "arp-scan",
        "Pi-hole",
        "Fritzbox",
        "Mikrotik",
        "UniFi",
        "OpenWRT",
        "AsusWRT",
        "pfSense",
        "Pi-hole DHCP"
    ]

    # Count devices for each method and output if count > 0
    for method in scan_methods:
        sql.execute("""SELECT COUNT(*) FROM CurrentScan
                       WHERE cur_ScanMethod = ? AND cur_ScanCycle = ?""",
                    (method, cycle))
        count = sql.fetchone()[0]
        if count > 0:
            print(f'        {method} Method: +{count}')

    # New Devices
    sql.execute ("""SELECT COUNT(*) FROM CurrentScan
                    WHERE cur_ScanCycle = ? 
                      AND NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = cur_MAC) """,
                    (cycle,))
    print('        New Devices........: ' + str (sql.fetchone()[0]) )
    # Devices in this ScanCycle
    sql.execute ("""SELECT COUNT(*) FROM Devices, CurrentScan
                    WHERE dev_MAC = cur_MAC AND dev_ScanCycle = cur_ScanCycle
                      AND dev_ScanCycle = ? """,
                    (cycle,))
    print('')
    print('    Devices in this scan...: ' + str (sql.fetchone()[0]) )
    # Down Alerts
    sql.execute ("""SELECT COUNT(*) FROM Devices
                    WHERE dev_AlertDeviceDown = 1
                      AND dev_ScanCycle = ?
                      AND NOT EXISTS (SELECT 1 FROM CurrentScan
                                      WHERE dev_MAC = cur_MAC
                                        AND dev_ScanCycle = cur_ScanCycle) """,
                    (cycle,))
    print('        Down Alerts........: ' + str (sql.fetchone()[0]) )
    # New Down Alerts
    sql.execute ("""SELECT COUNT(*) FROM Devices
                    WHERE dev_AlertDeviceDown = 1
                      AND dev_PresentLastScan = 1
                      AND dev_ScanCycle = ?
                      AND NOT EXISTS (SELECT 1 FROM CurrentScan
                                      WHERE dev_MAC = cur_MAC
                                        AND dev_ScanCycle = cur_ScanCycle) """,
                    (cycle,))
    print('        New Down Alerts....: ' + str (sql.fetchone()[0]) )
    # New Connections
    sql.execute ("""SELECT COUNT(*) FROM Devices, CurrentScan
                    WHERE dev_MAC = cur_MAC AND dev_ScanCycle = cur_ScanCycle
                      AND dev_PresentLastScan = 0
                      AND dev_ScanCycle = ? """,
                    (cycle,))
    print('        New Connections....: ' + str ( sql.fetchone()[0]) )
    # Disconnections
    sql.execute ("""SELECT COUNT(*) FROM Devices
                    WHERE dev_PresentLastScan = 1
                      AND dev_ScanCycle = ?
                      AND NOT EXISTS (SELECT 1 FROM CurrentScan
                                      WHERE dev_MAC = cur_MAC
                                        AND dev_ScanCycle = cur_ScanCycle) """,
                    (cycle,))
    print('        Disconnections.....: ' + str ( sql.fetchone()[0]) )
    # IP Changes
    sql.execute ("""SELECT COUNT(*) FROM Devices, CurrentScan
                    WHERE dev_MAC = cur_MAC AND dev_ScanCycle = cur_ScanCycle
                      AND dev_ScanCycle = ?
                      AND dev_LastIP <> cur_IP """,
                    (cycle,))
    print('        IP Changes.........: ' + str ( sql.fetchone()[0]) )
    # Inter-Satellite Movments
    sql.execute ("""SELECT COUNT(*) FROM Devices, CurrentScan
                    WHERE dev_MAC = cur_MAC AND dev_ScanSource <> cur_ScanSource
                      AND dev_ScanCycle = ?""",
                    (cycle,))
    print('        Relocating.........: ' + str ( sql.fetchone()[0]) )

#------------------------------------------------------------------------------
def calc_activity_history_main_scan():
    # Add to History
    sql.execute("SELECT * FROM Devices WHERE dev_Archived = 0 AND dev_PresentLastScan = 1 AND dev_ScanSource = 'local'")
    Querry_Online_Devices = sql.fetchall()
    History_Online_Devices  = len(Querry_Online_Devices)
    sql.execute("SELECT * FROM Devices WHERE dev_Archived = 0 AND dev_PresentLastScan = 0 AND dev_ScanSource = 'local'")
    Querry_Offline_Devices = sql.fetchall()
    History_Offline_Devices  = len(Querry_Offline_Devices)
    sql.execute("SELECT * FROM Devices WHERE dev_Archived = 1 AND dev_ScanSource = 'local'")
    Querry_Archived_Devices = sql.fetchall()
    History_Archived_Devices  = len(Querry_Archived_Devices)
    History_ALL_Devices = History_Online_Devices + History_Offline_Devices + History_Archived_Devices
    sql.execute ("INSERT INTO Online_History (Scan_Date, Online_Devices, Down_Devices, All_Devices, Archived_Devices, Data_Source) "+
                 "VALUES ( ?, ?, ?, ?, ?, ?)", (startTime, History_Online_Devices, History_Offline_Devices, History_ALL_Devices, History_Archived_Devices, 'main_scan_local') )

    sql.execute("SELECT sat_token FROM Satellites")
    tokens = sql.fetchall()
    for token in tokens:
        sat_token = token[0]
        query = f"SELECT * FROM Devices WHERE dev_Archived = 0 AND dev_PresentLastScan = 1 AND dev_ScanSource = '{sat_token}'"
        sql.execute(query)
        Querry_Online_Devices = sql.fetchall()
        History_Online_Devices  = len(Querry_Online_Devices)
        query = f"SELECT * FROM Devices WHERE dev_Archived = 0 AND dev_PresentLastScan = 0 AND dev_ScanSource = '{sat_token}'"
        sql.execute(query)
        Querry_Offline_Devices = sql.fetchall()
        History_Offline_Devices  = len(Querry_Offline_Devices)
        query = f"SELECT * FROM Devices WHERE dev_Archived = 1 AND dev_ScanSource = '{sat_token}'"
        sql.execute(query)
        Querry_Archived_Devices = sql.fetchall()
        History_Archived_Devices  = len(Querry_Archived_Devices)
        History_ALL_Devices = History_Online_Devices + History_Offline_Devices + History_Archived_Devices
        sql.execute ("INSERT INTO Online_History (Scan_Date, Online_Devices, Down_Devices, All_Devices, Archived_Devices, Data_Source) "+
                     "VALUES ( ?, ?, ?, ?, ?, ?)", (startTime, History_Online_Devices, History_Offline_Devices, History_ALL_Devices, History_Archived_Devices, 'main_scan_' + sat_token) )

#-------------------------------------------------------------------------------
def create_new_devices():
    # arpscan - Insert events for new devices
    print_log ('New devices - 1 Events')
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    SELECT cur_MAC, cur_IP, ?, 'New Device', '(' || cur_ScanMethod || ') ' || cur_Vendor, 1
                    FROM CurrentScan
                    WHERE cur_ScanCycle = ? 
                      AND NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = cur_MAC) """,
                    (startTime, cycle) ) 

    # arpscan - Create new devices
    print_log ('New devices - 2 Create devices')
    sql.execute ("""INSERT INTO Devices (dev_MAC, dev_name, dev_Vendor,
                        dev_LastIP, dev_FirstConnection, dev_LastConnection,
                        dev_ScanCycle, dev_AlertEvents, dev_AlertDeviceDown,
                        dev_PresentLastScan)
                    SELECT cur_MAC, '(unknown)', cur_Vendor, cur_IP, ?, ?,
                        1, 1, 0, 1
                    FROM CurrentScan
                    WHERE cur_ScanCycle = ? 
                      AND NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = cur_MAC) """,
                    (startTime, startTime, cycle) )


    # Pi-hole - Insert events for new devices
    print_log ('New devices - 3 Pi-hole Events')
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    SELECT PH_MAC, IFNULL (PH_IP,'-'), ?, 'New Device',
                        '(Pi-hole) ' || PH_Vendor, 1
                    FROM PiHole_Network
                    WHERE NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = PH_MAC) """,
                    (startTime, ) ) 

    # Pi-hole - Create New Devices
    print_log ('New devices - 4 Pi-hole Create devices')
    sql.execute ("""INSERT INTO Devices (dev_MAC, dev_name, dev_Vendor,
                        dev_LastIP, dev_FirstConnection, dev_LastConnection,
                        dev_ScanCycle, dev_AlertEvents, dev_AlertDeviceDown,
                        dev_PresentLastScan)
                    SELECT PH_MAC, PH_Name, PH_Vendor, IFNULL (PH_IP,'-'),
                        ?, ?, 1, 1, 0, 1
                    FROM PiHole_Network
                    WHERE NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = PH_MAC) """,
                    (startTime, startTime) ) 

    # DHCP Leases - Insert events for new devices
    print_log ('New devices - 5 DHCP Leases Events')
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    SELECT DHCP_MAC, DHCP_IP, ?, 'New Device', '(Pi-hole DHCP)',1
                    FROM DHCP_Leases
                    WHERE NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = DHCP_MAC) """,
                    (startTime, ) ) 

    # DHCP Leases - Create New Devices
    print_log ('New devices - 6 DHCP Leases Create devices')
    sql.execute ("""INSERT INTO Devices (dev_MAC, dev_name, dev_LastIP, 
                        dev_Vendor, dev_FirstConnection, dev_LastConnection,
                        dev_ScanCycle, dev_AlertEvents, dev_AlertDeviceDown,
                        dev_PresentLastScan)
                    SELECT DISTINCT DHCP_MAC,
                        (SELECT DHCP_Name FROM DHCP_Leases AS D2
                         WHERE D2.DHCP_MAC = D1.DHCP_MAC
                         ORDER BY DHCP_DateTime DESC LIMIT 1),
                        (SELECT DHCP_IP FROM DHCP_Leases AS D2
                         WHERE D2.DHCP_MAC = D1.DHCP_MAC
                         ORDER BY DHCP_DateTime DESC LIMIT 1),
                        '(unknown)', ?, ?, 1, 1, 0, 1
                    FROM DHCP_Leases AS D1
                    WHERE NOT EXISTS (SELECT 1 FROM Devices
                                      WHERE dev_MAC = DHCP_MAC) """,
                    (startTime, startTime) )

    print_log ('New Devices end')

#-------------------------------------------------------------------------------
def insert_events():
    # Check device down
    print_log ('Events 1 - Devices down')
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    SELECT dev_MAC, dev_LastIP, ?, 'Device Down', '', 1
                    FROM Devices
                    WHERE dev_AlertDeviceDown = 1
                      AND dev_PresentLastScan = 1
                      AND dev_ScanCycle = ?
                      AND NOT EXISTS (SELECT 1 FROM CurrentScan
                                      WHERE dev_MAC = cur_MAC
                                        AND dev_ScanCycle = cur_ScanCycle) """,
                    (startTime, cycle) )

    # Check new connections
    print_log ('Events 2 - New Connections')
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    SELECT cur_MAC, cur_IP, ?, 'Connected', '', dev_AlertEvents
                    FROM Devices, CurrentScan
                    WHERE dev_MAC = cur_MAC AND dev_ScanCycle = cur_ScanCycle
                      AND dev_PresentLastScan = 0
                      AND dev_ScanCycle = ? """,
                    (startTime, cycle) )

    # Check disconnections
    print_log ('Events 3 - Disconnections')
    sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                        eve_EventType, eve_AdditionalInfo,
                        eve_PendingAlertEmail)
                    SELECT dev_MAC, dev_LastIP, ?, 'Disconnected', '',
                        dev_AlertEvents
                    FROM Devices
                    WHERE dev_AlertDeviceDown = 0
                      AND dev_PresentLastScan = 1
                      AND dev_ScanCycle = ?
                      AND NOT EXISTS (SELECT 1 FROM CurrentScan
                                      WHERE dev_MAC = cur_MAC
                                        AND dev_ScanCycle = cur_ScanCycle) """,
                    (startTime, cycle) )

    # Check IP Changed
    print_log ('Events 4 - IP Changes')
    # sql.execute ("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
    #                     eve_EventType, eve_AdditionalInfo,
    #                     eve_PendingAlertEmail)
    #                 SELECT cur_MAC, cur_IP, ?, 'IP Changed',
    #                     'Previous IP: '|| dev_LastIP, dev_AlertEvents
    #                 FROM Devices, CurrentScan
    #                 WHERE dev_MAC = cur_MAC AND dev_ScanCycle = cur_ScanCycle
    #                   AND dev_ScanCycle = ?
    #                   AND dev_LastIP <> cur_IP """,
    #                 (startTime, cycle) )

    # WHEN cur_MAC = 'Internet'

    sql.execute ("""
        INSERT INTO Events (
            eve_MAC, eve_IP, eve_DateTime,
            eve_EventType, eve_AdditionalInfo,
            eve_PendingAlertEmail
        )
        SELECT
            cur_MAC,
            cur_IP,
            ?,
            CASE
                WHEN cur_MAC LIKE 'Internet%'
                    THEN 'Internet IP Changed'
                ELSE 'IP Changed'
            END,
            CASE
                WHEN cur_MAC LIKE 'Internet%'
                    THEN 'Previous Internet IP: ' || dev_LastIP
                ELSE 'Previous IP: ' || dev_LastIP
            END,
            dev_AlertEvents
        FROM Devices, CurrentScan
        WHERE dev_MAC = cur_MAC
          AND dev_ScanCycle = cur_ScanCycle
          AND dev_ScanCycle = ?
          AND dev_LastIP <> cur_IP
    """,
    (startTime, cycle))


    # Inter-Satellite movement
    print_log ('Events 5 - Inter-Satellite movement')
    sql.execute("""INSERT INTO Events (eve_MAC, eve_IP, eve_DateTime,
                                       eve_EventType, eve_AdditionalInfo,
                                       eve_PendingAlertEmail)
                   SELECT cur_MAC, cur_IP, ?, 'Inter-Satellite movement', 'previous Satellite: ' || COALESCE(sat_name, 'local'), dev_AlertEvents
                   FROM Devices
                   JOIN CurrentScan ON dev_MAC = cur_MAC AND dev_ScanCycle = cur_ScanCycle
                   LEFT JOIN Satellites ON (dev_ScanSource = sat_token AND dev_ScanSource != 'local')
                   WHERE dev_ScanSource != cur_ScanSource
                     AND dev_ScanCycle = ?""",
                (startTime, cycle))

    print_log ('Events end')

#-------------------------------------------------------------------------------
def update_devices_data_from_scan():
    # Update Last Connection
    print_log ('Update devices - 1 Last Connection')
    sql.execute("""UPDATE Devices SET dev_LastConnection = ?, dev_PresentLastScan = 1
                    WHERE dev_ScanCycle = ?
                      AND dev_PresentLastScan = 0
                      AND EXISTS (SELECT 1 FROM CurrentScan 
                                  WHERE dev_MAC = cur_MAC
                                    AND dev_ScanCycle = cur_ScanCycle) """,
                    (startTime, cycle))

    # Clean no active devices
    print_log ('Update devices - 2 Clean no active devices')
    sql.execute ("""UPDATE Devices SET dev_PresentLastScan = 0
                    WHERE dev_ScanCycle = ?
                      AND NOT EXISTS (SELECT 1 FROM CurrentScan 
                                      WHERE dev_MAC = cur_MAC
                                        AND dev_ScanCycle = cur_ScanCycle) """,
                    (cycle,))

    # Update IP & Vendor
    print_log('Update devices - 3 LastIP & Vendor')
    sql.execute("""UPDATE Devices
                   SET dev_LastIP = (SELECT cur_IP FROM CurrentScan
                                     WHERE dev_MAC = cur_MAC
                                       AND dev_ScanCycle = cur_ScanCycle),
                       dev_Vendor = CASE
                                      WHEN dev_Vendor IS NULL OR dev_Vendor = ''
                                      THEN (SELECT cur_Vendor FROM CurrentScan
                                            WHERE dev_MAC = cur_MAC
                                              AND dev_ScanCycle = cur_ScanCycle)
                                      ELSE dev_Vendor
                                    END
                   WHERE dev_ScanCycle = ?
                     AND EXISTS (SELECT 1 FROM CurrentScan
                                 WHERE dev_MAC = cur_MAC
                                   AND dev_ScanCycle = cur_ScanCycle)""",
                (cycle,))

    # Pi-hole Network - Update (unknown) Name
    print_log ('Update devices - 4 Unknown Name')
    sql.execute ("""UPDATE Devices
                    SET dev_NAME = (SELECT PH_Name FROM PiHole_Network
                                    WHERE PH_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM PiHole_Network
                                  WHERE PH_MAC = dev_MAC
                                    AND PH_NAME IS NOT NULL
                                    AND PH_NAME <> '') """)

    # DHCP Leases - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_NAME = (SELECT DHCP_Name FROM DHCP_Leases
                                    WHERE DHCP_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM DHCP_Leases
                                  WHERE DHCP_MAC = dev_MAC)""")

    # Fritzbox - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT FB_Name FROM Fritzbox_Network
                                    WHERE FB_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM Fritzbox_Network
                                  WHERE FB_MAC = dev_MAC
                                    AND FB_NAME IS NOT NULL
                                    AND FB_NAME <> '') """)

    # Mikrotik - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT MT_Name FROM Mikrotik_Network
                                    WHERE MT_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM Mikrotik_Network
                                  WHERE MT_MAC = dev_MAC
                                    AND MT_NAME IS NOT NULL
                                    AND MT_NAME <> '') """)

    # Unifi - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT UF_Name FROM Unifi_Network
                                    WHERE UF_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM Unifi_Network
                                  WHERE UF_MAC = dev_MAC
                                    AND UF_Name IS NOT NULL
                                    AND UF_Name <> '') """)

    # OpenWRT - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT OWRT_Name FROM Openwrt_Network
                                    WHERE OWRT_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM Openwrt_Network
                                  WHERE OWRT_MAC = dev_MAC
                                    AND OWRT_Name IS NOT NULL
                                    AND OWRT_Name <> '') """)

    # AsusWRT - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT ASUS_Name FROM Asuswrt_Network
                                    WHERE ASUS_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM Asuswrt_Network
                                  WHERE ASUS_MAC = dev_MAC
                                    AND ASUS_Name IS NOT NULL
                                    AND ASUS_Name <> '') """)

    # pfSense - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT PF_Name FROM pfsense_Network
                                    WHERE PF_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM pfsense_Network
                                  WHERE PF_MAC = dev_MAC
                                    AND PF_Name IS NOT NULL
                                    AND PF_Name <> '') """)

    # Satellite - Update (unknown) Name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = (SELECT Sat_Name FROM Satellites_Network
                                    WHERE Sat_MAC = dev_MAC)
                    WHERE (dev_Name = "(unknown)"
                           OR dev_Name = ""
                           OR dev_Name IS NULL)
                      AND EXISTS (SELECT 1 FROM Satellites_Network
                                  WHERE Sat_MAC = dev_MAC
                                    AND Sat_NAME IS NOT NULL
                                    AND Sat_NAME <> '') """)

    # DHCP Leases - Vendor
    print_log ('Update devices - 5 Vendor')

    recordsToUpdate = []
    query = """SELECT * FROM Devices
               WHERE (
                   dev_Vendor = '(unknown)' OR
                   dev_Vendor = '' OR
                   dev_Vendor = '(Unknown: locally administered)' OR
                   dev_Vendor IS NULL
               )
               AND dev_ScanSource = 'local'"""

    for device in sql.execute (query) :
        vendor = query_MAC_vendor (device['dev_MAC'])
        if vendor != -1 and vendor != -2 :
            recordsToUpdate.append ([vendor, device['dev_MAC']])

    sql.executemany ("UPDATE Devices SET dev_Vendor = ? WHERE dev_MAC = ? ",
        recordsToUpdate )

    # Update inter-satellite movements
    print_log ('Update devices - 6 inter-satellite movements')
    sql.execute("""UPDATE Devices 
                   SET dev_ScanSource = (SELECT cur_ScanSource 
                                         FROM CurrentScan 
                                         WHERE cur_MAC = dev_MAC)
                   WHERE EXISTS (SELECT 1 
                                 FROM CurrentScan 
                                 WHERE cur_MAC = dev_MAC)""")

    # Remove * as device name
    sql.execute ("""UPDATE Devices
                    SET dev_Name = '(unknown)'
                    WHERE dev_Name = '*'""")

    print_log ('Update devices end')

#-------------------------------------------------------------------------------
def update_devices_names():
    # Initialize variables
    recordsToUpdate = []
    ignored = 0
    notFound = 0

    # Devices without name
    print('        Trying to resolve devices without name...', end='')
    for device in sql.execute ("SELECT * FROM Devices WHERE dev_Name IN ('(unknown)','') AND dev_LastIP <> '-'") :
        # Resolve device name
        # print(device['dev_MAC'])
        newName = resolve_device_name (device['dev_MAC'], device['dev_LastIP'])
       
        if newName == -1 :
            notFound += 1
        elif newName == -2 :
            ignored += 1
        else :
            recordsToUpdate.append ([newName, device['dev_MAC']])
        # progress bar
        print('.', end='')
        sys.stdout.flush()

        if device['dev_MAC'] == "Internet":
            newName = "Internet"
            recordsToUpdate.append ([newName, device['dev_MAC']])
            
    # Print log
    print('')
    print("        Names updated:  ", len(recordsToUpdate) )

    # update devices
    sql.executemany ("UPDATE Devices SET dev_Name = ? WHERE dev_MAC = ? ", recordsToUpdate )

#-------------------------------------------------------------------------------
def resolve_device_name_netbios(pIP):
    try:
        nbtscan_args =['nbtscan', '-v', '-s', ':', pIP+'/32']
        newName = subprocess.run(nbtscan_args, capture_output=True, text=True, timeout=5)
        if newName.returncode == 0 and newName.stdout:
            lines = newName.stdout.strip().split('\n')
            for line in lines:
                if "00U" in line:
                    segments = line.split(':')
                    newName = segments[1].strip()
        else:
            newName = ""
        return newName
    # Error handling
    except subprocess.TimeoutExpired:
        newName = ""
        return newName

#-------------------------------------------------------------------------------
def resolve_device_name_avahi(pIP):
    try:
        avahi_args = ['avahi-resolve', '-a', pIP]
        newName = subprocess.run(avahi_args, capture_output=True, text=True, timeout=5)
        if newName.returncode == 0 and newName.stdout:
                ip_regex = re.compile(r'\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b')
                newName = re.sub(ip_regex, '', newName.stdout)
        else:
            newName = ""
        return newName.strip()
    # Error handling
    except subprocess.TimeoutExpired:
        newName = ""
        return newName
    except subprocess.CalledProcessError:
        newName = ""
        return newName

#-------------------------------------------------------------------------------
def resolve_device_name_dig(pIP):
    # DNS Server Fallback
    try:
        dnsserver = str(NETWORK_DNS_SERVER)
    except NameError:
        dnsserver = "localhost"

    try: 
        dig_args = ['dig', '+short', '-x', pIP, '@'+dnsserver]
        newName = subprocess.check_output (dig_args, universal_newlines=True, timeout=5)
        if ";; communications error to" in newName:
            newName = ""
        return newName.strip()
    # Error handling
    except subprocess.TimeoutExpired:
        newName = ""
        return newName
    except subprocess.CalledProcessError:
        newName = ""
        return newName

#-------------------------------------------------------------------------------
def resolve_device_name(pMAC, pIP):
    pMACstr = str(pMAC)
    
    # Check MAC parameter
    mac = pMACstr.replace (':','')
    if len(pMACstr) != 17 or len(mac) != 12 :
        return -2

    newName = resolve_device_name_avahi(pIP)
    if newName == "":
        newName = resolve_device_name_dig(pIP)
    if newName == "":
        newName = resolve_device_name_netbios(pIP)

    # Check returns
    newName = newName.strip()
    if len(newName) == 0 :
        return -2
        
    # Eliminate local domain
    suffixes = ['.', '.lan', '.local', '.home']

    for suffix in suffixes:
        if newName.endswith(suffix):
            newName = newName[:-len(suffix)]
            break

    return newName

#-------------------------------------------------------------------------------
def void_ghost_disconnections():
    # Void connect ghost events (disconnect event exists in last X min.) 
    print_log ('Void - 1 Connect ghost events')
    sql.execute ("""UPDATE Events SET eve_PairEventRowid = Null,
                        eve_EventType ='VOIDED - ' || eve_EventType
                    WHERE eve_MAC != 'Internet'
                      AND eve_EventType = 'Connected'
                      AND eve_DateTime = ?
                      AND eve_MAC IN (
                          SELECT Events.eve_MAC
                          FROM CurrentScan, Devices, ScanCycles, Events 
                          WHERE cur_ScanCycle = ?
                            AND dev_MAC = cur_MAC
                            AND dev_ScanCycle = cic_ID
                            AND cic_ID = cur_ScanCycle
                            AND eve_MAC = cur_MAC
                            AND eve_EventType = 'Disconnected'
                            AND eve_DateTime >=
                                DATETIME (?, '-' || cic_EveryXmin ||' minutes')
                          ) """,
                    (startTime, cycle, startTime)   )

    # Void connect paired events
    print_log ('Void - 2 Paired events')
    sql.execute ("""UPDATE Events SET eve_PairEventRowid = Null 
                    WHERE eve_MAC != 'Internet'
                      AND eve_PairEventRowid IN (
                          SELECT Events.RowID
                          FROM CurrentScan, Devices, ScanCycles, Events 
                          WHERE cur_ScanCycle = ?
                            AND dev_MAC = cur_MAC
                            AND dev_ScanCycle = cic_ID
                            AND cic_ID = cur_ScanCycle
                            AND eve_MAC = cur_MAC
                            AND eve_EventType = 'Disconnected'
                            AND eve_DateTime >=
                                DATETIME (?, '-' || cic_EveryXmin ||' minutes')
                          ) """,
                    (cycle, startTime)   )

    # Void disconnect ghost events 
    print_log ('Void - 3 Disconnect ghost events')
    sql.execute ("""UPDATE Events SET eve_PairEventRowid = Null, 
                        eve_EventType = 'VOIDED - '|| eve_EventType
                    WHERE eve_MAC != 'Internet'
                      AND ROWID IN (
                          SELECT Events.RowID
                          FROM CurrentScan, Devices, ScanCycles, Events 
                          WHERE cur_ScanCycle = ?
                            AND dev_MAC = cur_MAC
                            AND dev_ScanCycle = cic_ID
                            AND cic_ID = cur_ScanCycle
                            AND eve_MAC = cur_MAC
                            AND eve_EventType = 'Disconnected'
                            AND eve_DateTime >=
                                DATETIME (?, '-' || cic_EveryXmin ||' minutes')
                          ) """,
                    (cycle, startTime)   )
    print_log ('Void end')

#-------------------------------------------------------------------------------
def pair_sessions_events():
    # Pair Connection / New Device events
    print_log ('Pair session - 1 Connections / New Devices')
    sql.execute ("""UPDATE Events
                    SET eve_PairEventRowid =
                       (SELECT ROWID
                        FROM Events AS EVE2
                        WHERE EVE2.eve_EventType IN ('New Device', 'Connected',
                            'Device Down', 'Disconnected')
                           AND EVE2.eve_MAC = Events.eve_MAC
                           AND EVE2.eve_Datetime > Events.eve_DateTime
                        ORDER BY EVE2.eve_DateTime ASC LIMIT 1)
                    WHERE eve_EventType IN ('New Device', 'Connected')
                    AND eve_PairEventRowid IS NULL
                 """ )

    # Pair Disconnection / Device Down
    print_log ('Pair session - 2 Disconnections')
    sql.execute ("""UPDATE Events
                    SET eve_PairEventRowid =
                        (SELECT ROWID
                         FROM Events AS EVE2
                         WHERE EVE2.eve_PairEventRowid = Events.ROWID)
                    WHERE eve_EventType IN ('Device Down', 'Disconnected')
                      AND eve_PairEventRowid IS NULL
                 """ )
    print_log ('Pair session end')

#-------------------------------------------------------------------------------
def create_sessions_snapshot():
    # Clean sessions snapshot
    print_log ('Sessions Snapshot - 1 Clean')
    sql.execute ("DELETE FROM SESSIONS" )

    # Insert sessions
    print_log ('Sessions Snapshot - 2 Insert')
    sql.execute ("""INSERT INTO Sessions
                    SELECT * FROM Convert_Events_to_Sessions""" )
    print_log ('Sessions end')

#-------------------------------------------------------------------------------
def skip_repeated_notifications():
    # Skip repeated notifications
    # due strfime : Overflow --> use  "strftime / 60"
    print_log ('Skip Repeated')
    sql.execute ("""UPDATE Events SET eve_PendingAlertEmail = 0
                    WHERE eve_PendingAlertEmail = 1 AND eve_MAC IN
                        (
                        SELECT dev_MAC FROM Devices
                        WHERE dev_LastNotification IS NOT NULL
                          AND dev_LastNotification <>""
                          AND (strftime("%s", dev_LastNotification)/60 +
                                dev_SkipRepeated * 60) >
                              (strftime('%s','now','localtime')/60 )
                        )
                 """ )
    print_log ('Skip Repeated end')

#===============================================================================
# nmap Scan - DHCP Detection
#===============================================================================
def validate_dhcp_address(ip_string):
   try:
       ip_object = ipaddress.ip_address(ip_string)
       return True
   except ValueError:
       return False

# -----------------------------------------------------------------------------------
def rogue_dhcp_detection():
    openDB()

    sql.execute("DELETE FROM Nmap_DHCP_Server")
    sql_connection.commit()
    closeDB()

    # Execute 10 probes and insert in list
    dhcp_probes = 10
    dhcp_server_list = []
    dhcp_server_list.append(strftime("%Y-%m-%d %H:%M:%S"))
    for _ in range(dhcp_probes):
        stream = os.popen('sudo nmap --script broadcast-dhcp-discover 2>/dev/null | grep "Server Identifier" | awk \'{ print $4 }\'')
        output = stream.read()

        multiple_dhcp_ips = output.split("\n")

        if multiple_dhcp_ips:
            dhcp_server_list.append(multiple_dhcp_ips[0])

        for multiple_dhcp in multiple_dhcp_ips[1:]:
            # Condition to prevent empty entries in the database
            if len(multiple_dhcp) >= 7:
                dhcp_server_list.append(multiple_dhcp)

    openDB()
    for i in range(len(dhcp_server_list)):
        # Insert list in database
        sqlite_insert = """INSERT INTO Nmap_DHCP_Server
                         (scan_num, dhcp_server) 
                         VALUES (?, ?);"""

        # Redundant condition to prevent empty entries in the database
        if len(dhcp_server_list[i]) >= 7:
            table_data = (i, dhcp_server_list[i])
            sql.execute(sqlite_insert, table_data)
    
    sql_connection.commit()

    rogue_dhcp_notification()

    sql_connection.commit()
    closeDB()

# -----------------------------------------------------------------------------------
def rogue_dhcp_notification():
    sql.execute("SELECT DISTINCT dhcp_server FROM Nmap_DHCP_Server")
    rows = sql.fetchall()

    rogue_dhcp_server_list = []

    if len(rows) == 1:
        print('    No DHCP Server detected.')

    valid_dhcp_server_list = [DHCP_SERVER_ADDRESS] if not type(DHCP_SERVER_ADDRESS) is list else DHCP_SERVER_ADDRESS

    if len(rows) == 2:
        if validate_dhcp_address(rows[1][0]):
            if rows[1][0] in valid_dhcp_server_list :
                print('    One DHCP Server detected......: ' + rows[1][0] + ' (valid)')
            else:
                print('    One DHCP Server detected......: ' + rows[1][0] + ' (invalid)')
                rogue_dhcp_server_list.append(rows[1][0])
        else:
            print('    Detection Error')

    if len(rows) > 2:
        print('    Multiple DHCP Servers detected:')
        for i in range(1,len(rows),1):
            if validate_dhcp_address(rows[i][0]):
                if rows[i][0] in valid_dhcp_server_list :
                    print('        ' + rows[i][0] + ' (valid)' )
                else:
                    print('        ' + rows[i][0] + ' (rogue)' )
                    rogue_dhcp_server_list.append(rows[i][0])
            else:
                print('    Detection Error')

    rogue_dhcp_reports = glob.glob(REPORTPATH_WEBGUI + "*Rogue DHCP Server*.txt")    

    if rogue_dhcp_server_list and not rogue_dhcp_reports:
        rogue_dhcp_server_string = "Report Date: " + rows[0][0] + "\nServer: " + socket.gethostname() + "\n\nRogue DHCP Server\nDetected Server(s): "
        rogue_dhcp_server_string += ', '.join(rogue_dhcp_server_list)

        # Send Mail
        sending_notifications ('rogue_dhcp', rogue_dhcp_server_string, rogue_dhcp_server_string)

#===============================================================================
# Services Monitoring
#===============================================================================
def set_service_update(_mon_URL, _mon_lastScan, _mon_lastStatus, _mon_lastLatence, _mon_TargetIP, _mon_Redirect, _mon_ssl_info, _mon_ssl_fc):

    # SSL Info change
    if len(_mon_ssl_info) == 4 :
        _mon_ssl_subject = _mon_ssl_info['Subject']
        _mon_ssl_issuer = _mon_ssl_info['Issuer']
        _mon_ssl_valid_from = _mon_ssl_info['Valid_from']
        _mon_ssl_valid_to = _mon_ssl_info['Valid_to']
    else :
        _mon_ssl_subject = ""
        _mon_ssl_issuer = ""
        _mon_ssl_valid_from = ""
        _mon_ssl_valid_to = ""

    ssl_fc = str(_mon_ssl_fc)

    if _mon_Redirect != 200 and _mon_lastStatus == 200:
        _mon_Redirect_Text = "Redirected by " + str(_mon_Redirect)
    else:
        _mon_Redirect_Text = ""

    sqlite_insert = """UPDATE Services SET mon_LastScan=?, mon_LastStatus=?, mon_LastLatency=?, mon_TargetIP=?, mon_Notes=?, mon_ssl_subject=?, mon_ssl_issuer=?, mon_ssl_valid_from=?, mon_ssl_valid_to=?, mon_ssl_fc=? WHERE mon_URL=?;"""

    table_data = (_mon_lastScan, _mon_lastStatus, _mon_lastLatence, _mon_TargetIP, _mon_Redirect_Text, _mon_ssl_subject, _mon_ssl_issuer, _mon_ssl_valid_from, _mon_ssl_valid_to, ssl_fc, _mon_URL)
    sql.execute(sqlite_insert, table_data)
    sql_connection.commit()

# -----------------------------------------------------------------------------
def set_services_events(_moneve_URL, _moneve_DateTime, _moneve_StatusCode, _moneve_Latency, _moneve_TargetIP, _moneve_ssl_fc):

    sqlite_insert = """INSERT INTO Services_Events
                     (moneve_URL, moneve_DateTime, moneve_StatusCode, moneve_Latency, moneve_TargetIP, moneve_ssl_fc) 
                     VALUES (?, ?, ?, ?, ?, ?);"""

    table_data = (_moneve_URL, _moneve_DateTime, _moneve_StatusCode, _moneve_Latency, _moneve_TargetIP, _moneve_ssl_fc)
    sql.execute(sqlite_insert, table_data)
    sql_connection.commit()

# -----------------------------------------------------------------------------
def set_services_events_journal(_monevj_URL, _monevj_DateTime, _monevj_StatusCode, _monevj_Latency, _monevj_TargetIP, _monevj_ssl_fc):

    # Neues Services Journal
    sql.execute("""
        SELECT mon_LastStatus,
               mon_LastLatency,
               mon_TargetIP
        FROM Services
        WHERE mon_URL = ?
    """, (_monevj_URL,))

    service = sql.fetchone()

    # Wenn URL nicht existiert → abbrechen
    if service is None:
        return

    mon_LastStatus, mon_LastLatency, mon_TargetIP = service

    conditions_met = False
    additional_info = []

    # Latenz-Vergleich (FLOAT mit Sonderlogik)
    if str(_monevj_Latency) != str(mon_LastLatency):
        try:
            current_latency = float(_monevj_Latency)
            last_latency = float(mon_LastLatency)
        except (TypeError, ValueError):
            current_latency = None
            last_latency = None

        if current_latency is not None:
            if current_latency == 99999999:
                conditions_met = True
                additional_info.append("Service unreachable")

            elif last_latency == 99999999:
                conditions_met = True
                additional_info.append("Service reachable again")

            elif last_latency is not None and last_latency > 0:
                if current_latency >= (last_latency * 10):
                    conditions_met = True
                    additional_info.append(
                        f"High latency: {current_latency:.6f}s "
                        f"(>10x previous {last_latency:.6f}s)"
                    )

    # Status-Code Vergleich
    if _monevj_StatusCode != mon_LastStatus:

        if {mon_LastStatus, _monevj_StatusCode} != {0, 200}:
            conditions_met = True
            additional_info.append(
                f"Status changed: {mon_LastStatus} → {_monevj_StatusCode}"
            )

    # Target-IP Vergleich
    if _monevj_TargetIP != mon_TargetIP:

        if (mon_TargetIP is None) != (_monevj_TargetIP is None):
            conditions_met = True
            additional_info.append(
                f"IP changed: {mon_TargetIP} → {_monevj_TargetIP}"
            )

    # SSL-Flag Vergleich (Bitmask auflösen)
    if int(_monevj_ssl_fc) > 0:
        conditions_met = True
        ssl_code = int(_monevj_ssl_fc)

        if ssl_code & 8:
            additional_info.append("SSL Subject changed")
        if ssl_code & 4:
            additional_info.append("SSL Issuer changed")
        if ssl_code & 2:
            additional_info.append("SSL Valid_from changed")
        if ssl_code & 1:
            additional_info.append("SSL Valid_to changed")

    if conditions_met:

        sqlite_insert = """
            INSERT INTO Services_Events_Journal
            (monevj_URL,
             monevj_DateTime,
             monevj_Additional_Info)
            VALUES (?, ?, ?);
        """

        table_data = (
            _monevj_URL,
            _monevj_DateTime,
            " | ".join(additional_info)
        )

        sql.execute(sqlite_insert, table_data)
        sql_connection.commit()

# -----------------------------------------------------------------------------
def set_services_current_scan(_cur_URL, _cur_DateTime, _cur_StatusCode, _cur_Latency, _cur_TargetIP, _cur_ssl_info):

    _cur_StatusChanged = 0

    sql.execute("SELECT * FROM Services WHERE mon_URL = ?", [_cur_URL])
    rows = sql.fetchall()
    for row in rows:
        _mon_AlertEvents     = row["mon_AlertEvents"]
        _mon_AlertDown       = row["mon_AlertDown"]
        _mon_AlertUp         = row["mon_AlertUp"]
        _mon_StatusCode      = row["mon_LastStatus"]
        _mon_Latency         = row["mon_LastLatency"]
        _mon_TargetIP        = row["mon_TargetIP"]
        _mon_ssl_subject     = row["mon_ssl_subject"]
        _mon_ssl_issuer      = row["mon_ssl_issuer"]
        _mon_ssl_valid_from  = row["mon_ssl_valid_from"]
        _mon_ssl_valid_to    = row["mon_ssl_valid_to"]
        _mon_ssl_fc          = row["mon_ssl_fc"]

    # SSL Info change - Calc FC
    if len(_cur_ssl_info) == 4:
        _cur_ssl_fc = 0
        if _cur_ssl_info['Subject'] != _mon_ssl_subject :
            _cur_ssl_fc = _cur_ssl_fc + 8
        _cur_ssl_subject = _cur_ssl_info['Subject']
        if _cur_ssl_info['Issuer'] != _mon_ssl_issuer :
            _cur_ssl_fc = _cur_ssl_fc + 4
        _cur_ssl_issuer = _cur_ssl_info['Issuer']
        if _cur_ssl_info['Valid_from'] != _mon_ssl_valid_from :
            _cur_ssl_fc = _cur_ssl_fc + 2
        _cur_ssl_valid_from = _cur_ssl_info['Valid_from']
        if _cur_ssl_info['Valid_to'] != _mon_ssl_valid_to :
            _cur_ssl_fc = _cur_ssl_fc + 1
        _cur_ssl_valid_to = _cur_ssl_info['Valid_to']
    else:
        _cur_ssl_fc = 0
        _cur_ssl_subject = ""
        _cur_ssl_issuer = ""
        _cur_ssl_valid_from = ""
        _cur_ssl_valid_to = ""

    # SSL Info change - Compare FC
    if _cur_ssl_fc > 0:
        _cur_StatusChanged += 1

    # IP or Status Code change
    if _mon_TargetIP != _cur_TargetIP or _mon_StatusCode != _cur_StatusCode:
        _cur_StatusChanged += 1

    # Down or Online
    if _mon_Latency == "99999999" and _mon_Latency != _cur_Latency:
        _cur_LatencyChanged = 0
        _cur_StatusChanged += 1
    elif _cur_Latency == "99999999" and _mon_Latency != _cur_Latency:
        _cur_LatencyChanged = 1
    else:
        _cur_LatencyChanged = 0 

    # Merge Changes from all Events to 1 or 0
    StatusChanged = 1 if _cur_StatusChanged > 0 else 0

    sqlite_insert = """INSERT INTO Services_CurrentScan
                     (cur_URL, cur_DateTime, cur_StatusCode, cur_Latency, cur_AlertEvents, cur_AlertDown, cur_AlertUp, cur_StatusChanged, cur_LatencyChanged, cur_TargetIP, cur_StatusCode_prev, cur_TargetIP_prev, cur_ssl_subject, cur_ssl_issuer, cur_ssl_valid_from, cur_ssl_valid_to, cur_ssl_fc) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);"""

    table_data = (_cur_URL, _cur_DateTime, _cur_StatusCode, _cur_Latency, _mon_AlertEvents, _mon_AlertDown, _mon_AlertUp, StatusChanged, _cur_LatencyChanged, _cur_TargetIP, _mon_StatusCode, _mon_TargetIP, _cur_ssl_subject, _cur_ssl_issuer, _cur_ssl_valid_from, _cur_ssl_valid_to, _cur_ssl_fc)
    sql.execute(sqlite_insert, table_data)
    sql_connection.commit()

    # Save Journal and Events
    set_services_events_journal(_cur_URL, _cur_DateTime, _cur_StatusCode, _cur_Latency, _cur_TargetIP, _cur_ssl_fc)
    set_services_events(_cur_URL, _cur_DateTime, _cur_StatusCode, _cur_Latency, _cur_TargetIP, _cur_ssl_fc)
    
# -----------------------------------------------------------------------------
def service_monitoring_log(site, status, latency):
    status_str = str(status)

    # Log status message to log file
    with open(PIALERT_WEBSERVICES_LOG, 'a') as monitor_logfile:
        monitor_logfile.write("{} |        {} |     {} | {}\n".format(strftime("%Y-%m-%d %H:%M:%S"),
                                status_str.zfill(3), latency, site)
        )

# -----------------------------------------------------------------------------
def check_services_health(site):
    # Enable self signed SSL / no warning
    requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
    try:
        resp = requests.get(site, verify=False, timeout=10)
        latency = resp.elapsed
        latency_str = str(latency)
        latency_str_seconds = latency_str.split(":")
        format_latency_str = latency_str_seconds[2]
        if format_latency_str[0] == "0" and format_latency_str[1] != "." :
            format_latency_str = format_latency_str[1:]
        return resp.status_code, format_latency_str
    except requests.exceptions.SSLError:
        # Fallback for SSL-errors - necessary after Debian 13 update
        latency = "99999999"
        return 0, latency
    except:
        # Latency for offline services
        latency = "99999999"
        # HTTP Status Code for offline services
        return 0, latency

# -----------------------------------------------------------------------------
def check_services_redirect(site):
    # Enable self signed SSL
    requests.packages.urllib3.disable_warnings(InsecureRequestWarning)
    try:
        resp = requests.get(site, verify=False, timeout=10, allow_redirects=False)
        return resp.status_code
    except requests.exceptions.SSLError:
        pass
    except:
        # HTTP Status Code for offline services
        return 0

# -----------------------------------------------------------------------------
def get_ssl_cert_info(url, timeout=10):
    
    try:
        parsed_url = urlparse(url)
        hostname = parsed_url.hostname
        port = parsed_url.port or 443

        socket.setdefaulttimeout(timeout)

        #with socket.create_connection((hostname, 443)) as sock:
        with socket.create_connection((hostname, port)) as sock:
            context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
            context.check_hostname = False
            context.verify_mode = ssl.CERT_NONE  # Disable certificate verification
            with context.wrap_socket(sock, server_hostname=hostname, do_handshake_on_connect=False) as ssock:
                ssock.do_handshake()  # Perform the SSL handshake

                cert_data = ssock.getpeercert(binary_form=True)
                cert = x509.load_der_x509_certificate(cert_data, default_backend())

                ssl_info = dict()
                ssl_info['Subject'] = f"""{cert.subject}"""
                ssl_info['Issuer'] = f"""{cert.issuer}"""

                # Compatibility with new and old cryptography versions
                if hasattr(cert, 'not_valid_before_utc'):
                    ssl_info['Valid_from'] = f"""{cert.not_valid_before_utc} (UTC)"""
                else:
                    ssl_info['Valid_from'] = f"""{cert.not_valid_before}"""

                if hasattr(cert, 'not_valid_after_utc'):
                    ssl_info['Valid_to'] = f"""{cert.not_valid_after_utc} (UTC)"""
                else:
                    ssl_info['Valid_to'] = f"""{cert.not_valid_after}"""

                return ssl_info

    except socket.timeout:
        return "SSL certificate could not be found (Timeout)"
    except socket.gaierror:
        return "SSL certificate could not be found (Host down or does not exists)"
        # return 0
    except ConnectionRefusedError:
        return "SSL certificate could not be found (Connection Refused)"
        # return 0
    except Exception as e:
        return "SSL certificate could not be found (General Error)"
        # print(e)

# -----------------------------------------------------------------------------
def get_services_list():

    with open(PIALERT_WEBSERVICES_LOG, 'a') as monitor_logfile:
        monitor_logfile.write("    Get Services List\n")
        monitor_logfile.close()

    sql.execute("SELECT mon_URL FROM Services")
    rows = sql.fetchall()

    return [row[0] for row in rows]

# -----------------------------------------------------------------------------
def flush_services_current_scan():

    with open(PIALERT_WEBSERVICES_LOG, 'a') as monitor_logfile:
        monitor_logfile.write("    Flush previous scan results\n")
        monitor_logfile.close()

    sql.execute("DELETE FROM Services_CurrentScan")
    sql_connection.commit()

# -----------------------------------------------------------------------------
def print_service_monitoring_changes():

    print("    Services Monitoring Changes...")
    changedStatusCode = sql.execute("SELECT COUNT() FROM Services_CurrentScan WHERE cur_StatusChanged = 1 AND cur_Latency != 99999999").fetchone()[0]
    print("        StatusCodes...:", str(changedStatusCode))
    changeddown = sql.execute("SELECT COUNT() FROM Services_CurrentScan WHERE cur_LatencyChanged = 1").fetchone()[0]
    print("        Down..........:", str(changeddown))
    changedup = sql.execute("SELECT COUNT() FROM Services_CurrentScan WHERE cur_StatusChanged = 1 AND cur_StatusCode_prev = 0 AND cur_StatusCode = 200").fetchone()[0]
    print("        Up............:", str(changedup))
    with open(PIALERT_WEBSERVICES_LOG, 'a') as monitor_logfile:
        monitor_logfile.write("\nServices Monitoring Changes:\n")
        monitor_logfile.write("    StatusCodes...: " + str(changedStatusCode))
        monitor_logfile.write("\n    Down..........: " + str(changeddown))
        monitor_logfile.write("\n    Up............: " + str(changedup))
        monitor_logfile.write("\n")
        monitor_logfile.write("\nDONE!!!")
        monitor_logfile.write("\n")
        monitor_logfile.close()

# -----------------------------------------------------------------------------
def service_monitoring_notification():
    global mail_text_webservice
    global mail_html_webservice
    
    # Reporting section
    print('\nReporting (Web Services) ...')

    # Open Templates
    with open(f'{PIALERT_BACK_PATH}/report_template_webservice.txt', 'r') as template_file:
        mail_text_webservice = template_file.read()
    with open(f'{PIALERT_BACK_PATH}/report_template_webservice.html', 'r') as template_file:
        mail_html_webservice = template_file.read()

    # Report Header & footer
    timeFormated = startTime.strftime ('%Y-%m-%d %H:%M')
    mail_text_webservice = mail_text_webservice.replace ('<REPORT_DATE>', timeFormated)
    mail_html_webservice = mail_html_webservice.replace ('<REPORT_DATE>', timeFormated)

    mail_text_webservice = mail_text_webservice.replace ('<SERVER_NAME>', socket.gethostname() )
    mail_html_webservice = mail_html_webservice.replace ('<SERVER_NAME>', socket.gethostname() )

    # Compose Devices Down Section
    mail_section_services_down = False
    mail_text_services_down = ''
    mail_html_services_down = ''
    text_line_template = '{}{}\n\t{}\t\t\t{}\n\t{}\t\t\t{}\n\t{}\t{}\n\t{}\t{}\n\n'
    html_line_template = '<tr bgcolor=#909090 style="color:#F0F0F0;"><td colspan="2" style="width:50%; font-size:1.2em;"><b>URL:</b> {} </td><td colspan="2" style="width:50%; font-size:1.2em;"><b>Tag:</b> {} </td></tr>\n'+ \
                         '<tr><td colspan="2" style="width:50%"><b>ScanTime:</b> {} </td><td style="width:25%"><b>IP:</b>  {} </td><td style="width:25%"><b>prev. IP:</b> {} </td></tr>\n'

    sql.execute ("""SELECT Services_CurrentScan.*, Services.mon_Tags
                    FROM Services_CurrentScan
                    JOIN Services ON Services_CurrentScan.cur_URL = Services.mon_URL
                    WHERE Services_CurrentScan.cur_AlertDown = 1 
                    AND Services_CurrentScan.cur_LatencyChanged = 1
                    ORDER BY Services_CurrentScan.cur_DateTime""")

    for eventAlert in sql :
        if eventAlert['cur_TargetIP'] == '':
            _func_cur_TargetIP = 'n.a.'
        else:
            _func_cur_TargetIP = eventAlert['cur_TargetIP']
        if eventAlert['cur_TargetIP_prev'] == '':
            _func_cur_TargetIP_prev = 'n.a.'
        else:
            _func_cur_TargetIP_prev = eventAlert['cur_TargetIP_prev']

        mail_section_services_down = True
        mail_text_services_down += text_line_template.format (
            'Service: ', eventAlert['cur_URL'],
            'Tag: ', eventAlert['mon_Tags'], 
            'Time: ', eventAlert['cur_DateTime'], 
            'Destination IP: ', _func_cur_TargetIP,
            'prev. Destination IP: ', _func_cur_TargetIP_prev)
        mail_html_services_down += html_line_template.format (
            eventAlert['cur_URL'], eventAlert['mon_Tags'], eventAlert['cur_DateTime'], _func_cur_TargetIP, _func_cur_TargetIP_prev)

    format_report_section_services (mail_section_services_down, 'SECTION_DEVICES_DOWN',
        'TABLE_DEVICES_DOWN', mail_text_services_down, mail_html_services_down)


    # # Compose Devices Up Section
    mail_section_services_up = False
    mail_text_services_up = ''
    mail_html_services_up = ''
    text_line_template = '{}{}\n\t{}\t\t\t{}\n\t{}\t\t\t{}\n\t{}\t{}\n\n'
    html_line_template = '<tr bgcolor=#909090 style="color:#F0F0F0;"><td colspan="2" style="width:50%; font-size:1.2em;"><b>URL:</b> {} </td><td colspan="2" style="width:50%; font-size:1.2em;"><b>Tag:</b> {} </td></tr>\n'+ \
                         '<tr><td colspan="2" style="width:50%"><b>ScanTime:</b> {} </td><td style="width:25%"><b>IP:</b>  {} </td></tr>\n'

    sql.execute ("""SELECT Services_CurrentScan.*, Services.mon_Tags
                    FROM Services_CurrentScan
                    JOIN Services ON Services_CurrentScan.cur_URL = Services.mon_URL
                    WHERE Services_CurrentScan.cur_AlertUp = 1 
                    AND Services_CurrentScan.cur_StatusChanged = 1
                    AND Services_CurrentScan.cur_StatusCode = 200
                    AND Services_CurrentScan.cur_StatusCode_prev = 0
                    ORDER BY Services_CurrentScan.cur_DateTime""")

    for eventAlert in sql :
        mail_section_services_up = True
        mail_text_services_up += text_line_template.format (
            'Service: ', eventAlert['cur_URL'],
            'Tag: ', eventAlert['mon_Tags'], 
            'Time: ', eventAlert['cur_DateTime'], 
            'Destination IP: ', eventAlert['cur_TargetIP'])
        mail_html_services_up += html_line_template.format (
            eventAlert['cur_URL'], eventAlert['mon_Tags'], eventAlert['cur_DateTime'], eventAlert['cur_TargetIP'])

        print(mail_text_services_up)

    format_report_section_services (mail_section_services_up, 'SECTION_DEVICES_UP',
        'TABLE_DEVICES_UP', mail_text_services_up, mail_html_services_up)


    # Compose Events Section (includes Down as an Event)
    mail_section_events = False
    mail_text_events   = ''
    mail_html_events   = ''
    text_line_template = '{}{}\n\t{}\t\t\t{}\n\t{}\t\t\t{}\n\t{}\t{}\n\t{}\t{}\n\t{}\t{}\n\t{}{}\n\t{}\t\t{}\n\n'
    html_line_template = '<tr bgcolor=#909090 style="color:#F0F0F0"><td colspan="2" style="width:50%; font-size:1.2em;"><b>URL:</b> {} </td><td colspan="2" style="width:50%; font-size:1.2em;"><b>Tag:</b> {} </td></tr>\n'+ \
                         '<tr><td style="width:25%"><b>ScanTime:</b> {} </td>  <td style="width:25%"><b>IP:</b> {} </td>          <td style="width:25%"><b>prev. IP:</b> {} </td>          <td style="width:25%"><b>Latency:</b> {} </td>    <tr>\n'+ \
                         '<tr><td style="width:25%">&nbsp;</td>                <td style="width:25%"><b>StatusCode:</b> {} </td>  <td style="width:25%"><b>prev. StatusCode:</b> {} </td>  <td style="width:25%"><b>SSL Code:</b> {} </td>  </tr>\n'

    sql.execute ("""SELECT Services_CurrentScan.*, Services.mon_Tags
                    FROM Services_CurrentScan
                    JOIN Services ON Services_CurrentScan.cur_URL = Services.mon_URL
                    WHERE Services_CurrentScan.cur_AlertEvents = 1 
                    AND Services_CurrentScan.cur_StatusChanged = 1
                    ORDER BY Services_CurrentScan.cur_DateTime""")

    for eventAlert in sql :
        if eventAlert['cur_TargetIP'] == '':
            _func_cur_TargetIP = 'n.a.'
        else:
            _func_cur_TargetIP = eventAlert['cur_TargetIP']
        if eventAlert['cur_TargetIP_prev'] == '':
            _func_cur_TargetIP_prev = 'n.a.'
        else:
            _func_cur_TargetIP_prev = eventAlert['cur_TargetIP_prev']

        mail_section_events = True
        mail_text_events += text_line_template.format (
            'Service: ', eventAlert['cur_URL'],
            'Tag: ', eventAlert['mon_Tags'],
            'Time: ', eventAlert['cur_DateTime'],
            'Destination IP: ', _func_cur_TargetIP,
            'prev. Destination IP: ', _func_cur_TargetIP_prev,
            'HTTP Status Code: ', eventAlert['cur_StatusCode'],
            'prev. HTTP Status Code: ', eventAlert['cur_StatusCode_prev'],
            'SSL Status: ', eventAlert['cur_ssl_fc'])
        mail_html_events += html_line_template.format (
            eventAlert['cur_URL'], eventAlert['mon_Tags'], 
            eventAlert['cur_DateTime'], _func_cur_TargetIP, _func_cur_TargetIP_prev, eventAlert['cur_Latency'], 
            eventAlert['cur_StatusCode'], eventAlert['cur_StatusCode_prev'], eventAlert['cur_ssl_fc'])

    format_report_section_services (mail_section_events, 'SECTION_EVENTS',
        'TABLE_EVENTS', mail_text_events, mail_html_events)

    # # Send Mail
    if mail_section_services_down == True or mail_section_events == True or mail_section_services_up == True:
        sending_notifications ('webservice', mail_html_webservice, mail_text_webservice)
    else :
        print('    No changes to report...')

    sql_connection.commit()

# -----------------------------------------------------------------------------
def service_monitoring():
    global VERSION
    global VERSION_DATE

    # Empty Log and write new header
    print("\nStart Services Monitoring...")
    print("    Prepare Logfile...")
    with open(PIALERT_WEBSERVICES_LOG, 'w') as monitor_logfile:
        monitor_logfile.write("\nPi.Alert v" + VERSION_DATE + ":\n---------------------------------------------------------\n")
        monitor_logfile.write("Current User: %s \n\n" % get_username())
        monitor_logfile.write("Monitor Web-Services\n")
        monitor_logfile.write("    Timestamp: " + strftime("%Y-%m-%d %H:%M:%S") + "\n")
        monitor_logfile.close()

    # Open DB for Web Service Monitoring
    openDB()
    
    print("    Get Services List...")
    sites = get_services_list()
    print_log('\n' + '\n'.join(sites))

    print("    Flush previous scan results...")
    flush_services_current_scan()

    # Close DB
    sql_connection.commit()
    closeDB()
    
    print("    Check Services...")
    with open(PIALERT_WEBSERVICES_LOG, 'a') as monitor_logfile:
        monitor_logfile.write("\nStart Services Monitoring\n\n Timestamp          | StatusCode | ResponseTime | URL \n-----------------------------------------------------------------\n") 
        monitor_logfile.close()

    scantime = startTime.strftime("%Y-%m-%d %H:%M")

    results_log = []
    scan_data = []
    event_data = []
    update_data = []

    while sites:
        for site in sites:
            status, latency = check_services_health(site)
            site_retry = ''
            if latency == "99999999":
                # 2. Attempt in case of error in the first run
                status, latency = check_services_health(site)
                site_retry = '*'
                if latency == "99999999":
                    # 3. Attempt in case of error in the second run
                    status, latency = check_services_health(site)
                    site_retry = '**'

            # Hole IP aus der Domain
            if latency != "99999999":
                redirect_state = check_services_redirect(site)
                domain = urlparse(site).netloc
                domain = domain.split(":")[0]
                domain_ip = socket.gethostbyname(domain)
                # Hole SSL-Informationen
                ssl_info = get_ssl_cert_info(site)
            else:
                domain_ip = ""
                redirect_state = ""
                ssl_info = ""

            # Speicherung der Ergebnisse in Listen/Dictionaries
            results_log.append((site + ' ' + site_retry, status, latency))
            scan_data.append((site, scantime, status, latency, domain_ip, ssl_info))
            update_data.append((site, scantime, status, latency, domain_ip, redirect_state, ssl_info, ""))

        # OpenDB to save Scan Results
        openDB()

        # Log-File
        for log_entry in results_log:
            service_monitoring_log(*log_entry)

        # Storing the current status and determining the change since the last scan
        # Saving the current status as a separate event for the history
        # Write the journal from the changes identified
        for scan_entry in scan_data:
            set_services_current_scan(*scan_entry)

        # Update the services list with the current status
        for update_entry in update_data:
            set_service_update(*update_entry)

        # Close DB after saving
        sql_connection.commit()
        closeDB()

        break

    else:
        print("    No site(s) to monitor!")
        with open(PIALERT_WEBSERVICES_LOG, 'a') as monitor_logfile:
            monitor_logfile.write("\n**************** No site(s) to monitor!! ****************\n")
            monitor_logfile.close()

    openDB()
    # Print to log file
    print_service_monitoring_changes()

    sql_connection.commit()
    closeDB()

#===============================================================================
# ICMP Monitoring
#===============================================================================
def icmp_monitoring():

    openDB()
    print("\nStart ICMP Monitoring...")
    print("    Get Host/Domain List...")
    icmphosts = get_icmphost_list()
    icmphostscount = len(icmphosts)
    print("        List contains " + str(icmphostscount) + " entries")
    print("    Flush previous ping results...")
    flush_icmphost_current_scan()
    print("    Ping Hosts...")

    closeDB()
    scantime = startTime.strftime("%Y-%m-%d %H:%M")
    icmp_scan_results = {}
    icmphosts_all = len(icmphosts)
    print_log('\n' + '\n'.join(icmphosts))

    try:
        ping_retries = ICMP_ONLINE_TEST
    except NameError: # variable not defined, use a default
        ping_retries = 1 # 1

    icmphosts_index = 0

    if icmphosts_all > 0 :
        while icmphosts_index < icmphosts_all:
            host_ip = icmphosts[icmphosts_index]
            for i in range(ping_retries):
                icmp_status = ping(host_ip)
                if icmp_status == "1":
                    break;

            if icmp_status == "1":
                icmp_rtt = ping_avg(host_ip)
            else:
                icmp_rtt = "99999"

            current_data = {
                "host_ip": host_ip,
                "scantime": scantime,
                "icmp_status": icmp_status,
                "icmp_rtt": icmp_rtt
            }

            print_log(current_data)

            icmp_scan_results[host_ip] = current_data
            sys.stdout.flush()

            icmphosts_index += 1

        openDB()
        # Save Scan Results
        icmp_save_scandata(icmp_scan_results)

        update_icmp_validation()
        online, offline = get_online_offline_hosts()
        print("        Online Host(s)  : " + str(online))
        print("        Offline Host(s) : " + str(offline))

        print("    Create Events...")
        icmp_create_events()

        print("    Calculate Activity History...")
        calc_activity_history_icmp(online, offline)

        sql_connection.commit()
        closeDB()

    else:
        # openDB()
        print("    No Hosts(s) to monitor!")


#-------------------------------------------------------------------------------
def get_online_offline_hosts():
    sql.execute("""
        SELECT COUNT(*) 
        FROM ICMP_Mon_CurrentScan 
        WHERE cur_Present = 1
    """)
    icmphosts_online = sql.fetchone()[0]

    sql.execute("""
        SELECT COUNT(*) 
        FROM ICMP_Mon_CurrentScan 
        WHERE cur_Present = 0
    """)
    icmphosts_offline = sql.fetchone()[0]

    return icmphosts_online, icmphosts_offline

#-------------------------------------------------------------------------------
def update_icmp_validation():
    print('    Update ICMP Validation...')
    # 1. Set dev_Scan_Validation_State to 0 for devices that are in Present in CurrentScan and have dev_Scan_Validation > 0
    sql.execute("""
        UPDATE ICMP_Mon
        SET icmp_Scan_Validation_State = 0
        WHERE icmp_Scan_Validation > 0
        AND icmp_ip IN (
            SELECT cur_ip FROM ICMP_Mon_CurrentScan WHERE cur_Present = 1
        );
    """)
    # 2. Find devices in CurrentScan that have activated Scan_Validation and are not currently active
    sql.execute("""
        SELECT cur_ip 
        FROM ICMP_Mon_CurrentScan 
        WHERE cur_Present = 0 
        AND cur_ip IN (
            SELECT icmp_ip 
            FROM ICMP_Mon 
            WHERE icmp_Scan_Validation > 0 
            AND icmp_Scan_Validation_State < icmp_Scan_Validation
        )
    """)
    host_ips = [(row[0],) for row in sql.fetchall()]
    print_log(f"Scan Validation: \n{host_ips}")
    # 3. Set the relevant devices as online
    sql.executemany("""
        UPDATE ICMP_Mon_CurrentScan 
        SET cur_Present = 1, cur_PresentChanged = 0, cur_avgrrt = 999
        WHERE cur_ip = ?
    """, host_ips)
    # 4. increase dev_Scan_Validation_State by 1 for the devices saved in point 2
    sql.executemany("""
        UPDATE ICMP_Mon
        SET icmp_Scan_Validation_State = icmp_Scan_Validation_State + 1,
            icmp_PresentLastScan = 1,
            icmp_avgrtt = 999
        WHERE icmp_Scan_Validation > 0 AND icmp_ip = ?
    """, host_ips)

    sql_connection.commit()

# -----------------------------------------------------------------------------
def icmp_save_scandata(data):
    print("    Save scan results...")
    for host_ip, scan_data in data.items():
        set_icmphost_events(host_ip, scan_data['scantime'], scan_data['icmp_status'], scan_data['icmp_rtt'])
        set_icmphost_current_scan(host_ip, scan_data['scantime'], scan_data['icmp_status'], scan_data['icmp_rtt'])
        set_icmphost_update(host_ip, scan_data['scantime'], scan_data['icmp_status'], scan_data['icmp_rtt'])

# -----------------------------------------------------------------------------
def icmp_create_events():
    # Check new connections
    print_log ('Events - New Connections')
    sql.execute ("""INSERT INTO ICMP_Mon_Connections (icmpeve_ip, icmpeve_DateTime, icmpeve_Present, icmpeve_EventType)
                        SELECT 
                            cur.cur_ip AS icmpeve_ip,
                            cur.cur_LastScan AS icmpeve_DateTime,
                            cur.cur_Present AS icmpeve_Present,
                            'Connected' AS icmpeve_EventType
                        FROM 
                            ICMP_Mon_CurrentScan cur
                        WHERE 
                            cur.cur_Present = 1
                            AND cur.cur_PresentChanged = 1;""")

    print_log ('Events - Disconnections')
    sql.execute ("""INSERT INTO ICMP_Mon_Connections (icmpeve_ip, icmpeve_DateTime, icmpeve_Present, icmpeve_EventType)
                        SELECT 
                            cur.cur_ip AS icmpeve_ip,
                            cur.cur_LastScan AS icmpeve_DateTime,
                            cur.cur_Present AS icmpeve_Present,
                            'Disconnected' AS icmpeve_EventType
                        FROM 
                            ICMP_Mon_CurrentScan cur
                        JOIN 
                            ICMP_Mon mon
                        ON 
                            cur.cur_ip = mon.icmp_ip
                        WHERE 
                            cur.cur_Present = 0
                            AND cur.cur_PresentChanged = 1
                            AND mon.icmp_AlertDown = 0;""")

    print_log ('Events - Down')
    sql.execute ("""INSERT INTO ICMP_Mon_Connections (icmpeve_ip, icmpeve_DateTime, icmpeve_Present, icmpeve_EventType)
                        SELECT 
                            cur.cur_ip AS icmpeve_ip,
                            cur.cur_LastScan AS icmpeve_DateTime,
                            cur.cur_Present AS icmpeve_Present,
                            'Down' AS icmpeve_EventType
                        FROM 
                            ICMP_Mon_CurrentScan cur
                        JOIN 
                            ICMP_Mon mon
                        ON 
                            cur.cur_ip = mon.icmp_ip
                        WHERE 
                            cur.cur_Present = 0
                            AND cur.cur_PresentChanged = 1
                            AND mon.icmp_AlertDown = 1;""")

# -----------------------------------------------------------------------------
def get_icmphost_list():
    sql.execute("SELECT icmp_ip FROM ICMP_Mon  WHERE icmp_Archived = 0 ")
    rows = sql.fetchall()

    return [row[0] for row in rows]

# -----------------------------------------------------------------------------
def ping(host):
    print_log(f"Check {host}")
    command = ['fping', '-c', '1', host]
    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    output = result.stdout.decode('utf-8').strip()

    # Search for statistics line at the end
    match = re.search(r'xmt/rcv/%loss\s*=\s*(\d+)/(\d+)/(\d+)%', output)
    if match:
        rcv = int(match.group(2))
        status = "1" if rcv > 0 else "0"
    else:
        status = "0"  # failsafe

    return status

# -----------------------------------------------------------------------------
def ping_avg(host):
    try:
        ping_count = str(ICMP_GET_AVG_RTT)
    except NameError:
        ping_count = str(2)

    command = ['fping', '-c', ping_count, host]
    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    output = result.stdout.decode('utf-8')

    # Suche nach avg mit Regex
    match = re.search(r'min/avg/max\s*=\s*[\d.]+/([\d.]+)/[\d.]+', output)
    if match:
        return match.group(1)
    else:
        return ""

# -----------------------------------------------------------------------------
def set_icmphost_events(_icmpeve_ip, _icmpeve_DateTime, _icmpeve_Present, _icmpeve_avgrtt):
    #print_log(_icmpeve_ip, _icmpeve_DateTime, _icmpeve_Present, _icmpeve_avgrtt)
    sqlite_insert = """INSERT INTO ICMP_Mon_Events
                     (icmpeve_ip, icmpeve_DateTime, icmpeve_Present, icmpeve_avgrtt) 
                     VALUES (?, ?, ?, ?);"""

    table_data = (_icmpeve_ip, _icmpeve_DateTime, _icmpeve_Present, _icmpeve_avgrtt)
    sql.execute(sqlite_insert, table_data)
    sql_connection.commit()

# -----------------------------------------------------------------------------
def set_icmphost_current_scan(_cur_ip, _cur_DateTime, _cur_Present, _cur_avgrrt):
    sql.execute("SELECT * FROM ICMP_Mon WHERE icmp_ip = ?", [_cur_ip])
    rows = sql.fetchall()
    for row in rows:
        _icmp_PresentLastScan = row[3]
        _icmp_AlertEvents = row[5]
        _icmp_AlertDown = row[6]

    if str(_icmp_PresentLastScan) != str(_cur_Present):
        _cur_PresentChanged = 1
    else:
        _cur_PresentChanged = 0 

    sqlite_insert = """INSERT INTO ICMP_Mon_CurrentScan
                     (cur_ip, cur_LastScan, cur_Present, cur_PresentChanged, cur_avgrrt, cur_AlertEvents, cur_AlertDown) 
                     VALUES (?, ?, ?, ?, ?, ?, ?);"""

    table_data = (_cur_ip, _cur_DateTime, _cur_Present, _cur_PresentChanged, _cur_avgrrt, _icmp_AlertEvents, _icmp_AlertDown)
    sql.execute(sqlite_insert, table_data)
    sql_connection.commit()

# -----------------------------------------------------------------------------
def set_icmphost_update(_icmp_ip, _icmp_LastScan, _icmp_PresentLastScan, _icmp_avgrtt):
    sqlite_insert = """UPDATE ICMP_Mon SET icmp_LastScan=?, icmp_PresentLastScan=?, icmp_avgrtt=? WHERE icmp_ip=?;"""
    table_data = (_icmp_LastScan, _icmp_PresentLastScan, _icmp_avgrtt, _icmp_ip)
    sql.execute(sqlite_insert, table_data)
    sql_connection.commit()

# -----------------------------------------------------------------------------
def flush_icmphost_current_scan():
    sql.execute("DELETE FROM ICMP_Mon_CurrentScan")
    sql_connection.commit()

# -----------------------------------------------------------------------------
def get_icmphost_name(_icmp_ip):
    query = "SELECT icmp_hostname FROM ICMP_Mon WHERE icmp_ip = ?"
    sql.execute(query, (_icmp_ip,))
    result_hostname = sql.fetchone()

    if result_hostname:
        hostname = result_hostname[0]
    else:
        hostname = 'No Hostname set'

    return hostname

# -----------------------------------------------------------------------------
def calc_activity_history_icmp(History_Online_Devices, History_Offline_Devices):
    sql.execute("SELECT * FROM ICMP_Mon WHERE icmp_Archived = 1")
    Querry_Archived_Devices = sql.fetchall()
    History_Archived_Devices  = len(Querry_Archived_Devices)

    History_ALL_Devices = History_Online_Devices + History_Offline_Devices + History_Archived_Devices
    sql.execute ("INSERT INTO Online_History (Scan_Date, Online_Devices, Down_Devices, All_Devices, Archived_Devices, Data_Source) "+
                 "VALUES ( ?, ?, ?, ?, ?, ?)", (startTime, History_Online_Devices, History_Offline_Devices, History_ALL_Devices, History_Archived_Devices, 'icmp_scan') )
    sql_connection.commit()

# -----------------------------------------------------------------------------
def icmphost_monitoring_notification():
    global mail_text_icmphost
    global mail_html_icmphost
    
    # Reporting section
    print('\nReporting (ICMP Monitoring) ...')
    # Open text Templates
    with open(f'{PIALERT_BACK_PATH}/report_template_icmpmon.txt', 'r') as template_file:
        mail_text_icmphost = template_file.read()
    with open(f'{PIALERT_BACK_PATH}/report_template_icmpmon.html', 'r') as template_file:
        mail_html_icmphost = template_file.read()

    # Report Header & footer
    timeFormated = startTime.strftime ('%Y-%m-%d %H:%M')
    mail_text_icmphost = mail_text_icmphost.replace ('<REPORT_DATE>', timeFormated)
    mail_html_icmphost = mail_html_icmphost.replace ('<REPORT_DATE>', timeFormated)

    mail_text_icmphost = mail_text_icmphost.replace ('<SERVER_NAME>', socket.gethostname() )
    mail_html_icmphost = mail_html_icmphost.replace ('<SERVER_NAME>', socket.gethostname() )

    # Compose Devices Down Section
    mail_section_icmphost_down = False
    mail_text_icmphost_down = ''
    mail_html_icmphost_down = ''
    text_line_template = '{}{}\n\t{}\t{}\n\t{}\t\t{}\n\t{}\t{}\n\n'
    html_line_template     = '<tr>\n'+ \
        '  <td> {} </td>\n  <td> {} </td> <td> {} </td>\n'+ \
        '  <td> {} </td>\n</tr>\n'

    sql.execute ("""SELECT * FROM ICMP_Mon_CurrentScan
                    WHERE cur_AlertDown = 1 AND cur_Present = 0 AND cur_PresentChanged = 1
                    ORDER BY cur_LastScan""")

    for eventAlert in sql :

        hostname = get_icmphost_name(eventAlert['cur_ip'])
        print(hostname)
        
        mail_section_icmphost_down = True
        mail_text_icmphost_down += text_line_template.format (
            'IP: ', eventAlert['cur_ip'],
            'Hostname: ', hostname,
            'Time: ', eventAlert['cur_LastScan'], 
            'Status: ', 'Down')
        mail_html_icmphost_down += html_line_template.format (
            eventAlert['cur_ip'], hostname, eventAlert['cur_LastScan'], 'Down')

    format_report_section_icmp (mail_section_icmphost_down, 'SECTION_DEVICES_DOWN',
        'TABLE_DEVICES_DOWN', mail_text_icmphost_down, mail_html_icmphost_down)

    # Compose Events Section (includes Down as an Event)
    mail_section_events = False
    mail_text_events   = ''
    mail_html_events   = ''
    text_line_template = '{}{}\n\t{}\t{}\n\t{}\t\t{}\n\t{}\t\t{} ms\n\t{}\t{}\n\n'
    html_line_template = '<tr>\n  <td>'+ \
            '  {} </td>\n  <td> {} </td> <td> {} </td>\n'+ \
            '  <td> {} </td>\n <td> {} </td>\n'+ \
            '  </tr>\n'

    sql.execute ("""SELECT * FROM ICMP_Mon_CurrentScan
                    WHERE cur_AlertEvents = 1 AND cur_PresentChanged = 1
                    ORDER BY cur_LastScan""")

    for eventAlert in sql :
        mail_section_events = True

        hostname = get_icmphost_name(eventAlert['cur_ip'])
        print(hostname)

        if eventAlert['cur_Present'] == 1 :
            icmp_online_status = 'Up'
        else :
            icmp_online_status = 'Down'

        mail_text_events += text_line_template.format (
            'IP: ', eventAlert['cur_ip'],
            'Hostname:', hostname,
            'Time: ', eventAlert['cur_LastScan'], 
            'RTT: ', eventAlert['cur_avgrrt'], 
            'Status: ', icmp_online_status)
        mail_html_events += html_line_template.format (
            eventAlert['cur_ip'], hostname, eventAlert['cur_LastScan'], eventAlert['cur_avgrrt'], icmp_online_status)

    format_report_section_icmp (mail_section_events, 'SECTION_EVENTS',
        'TABLE_EVENTS', mail_text_events, mail_html_events)

    # # Send Mail
    if mail_section_icmphost_down == True or mail_section_events == True :
        sending_notifications ('icmp_mon', mail_html_icmphost, mail_text_icmphost)
    else :
        print('    No changes to report...')

    sql_connection.commit()


#===============================================================================
# Publish to MQTT
#===============================================================================
def report_to_mqtt():

    # General System Data
    if PUBLISH_MQTT_STATUS:
        print('    Pi.Alert Status')
        daten = mqtt_get_system_status()

        for group, values in daten.items():
            device_id = f"pialert_{group}"
            device_name = f"Pi.Alert {group.capitalize()}"
            base_topic = f"pi_alert/{group}"

            publish_sensor_group(device_id, device_name, base_topic, values)

    # Per Device Data
    sync_mqtt_devices_stateless()

#-------------------------------------------------------------------------------
def publish_sensor_group(device_id: str, device_name: str, base_topic: str, values: dict):

    for key, value in values.items():
        topic = f"{base_topic}/{key}"
        object_id = key.lower()

        # Automatic assignment of unit and device_class
        unit = "" if isinstance(value, int) else None
        device_class = "timestamp" if "time" in key.lower() else None

        # Send Discovery
        publish_discovery_sensor(
            device_id=device_id,
            device_name=device_name,
            object_id=object_id,
            name=f"{device_name}: {key.capitalize()}",
            topic=topic,
            unit=unit,
            device_class=device_class
        )

        # Publish
        send_mqtt_message(topic, value, retain=True)

#-------------------------------------------------------------------------------
def publish_discovery_sensor(device_id, device_name, object_id, name, topic, unit=None, device_class=None):
    is_binary = object_id.lower() == "status"  # automatically detect

    sensor_type = "binary_sensor" if is_binary else "sensor"
    discovery_topic = f"homeassistant/{sensor_type}/{device_id}_{object_id}/config"

    payload = {
        "name": name,
        "state_topic": topic,
        "unique_id": f"{device_id}_{object_id}",
        "device": {
            "identifiers": [device_id],
            "name": device_name,
            "manufacturer": "Pi.Alert",
            "model": "MQTT Export"
        }
    }

    # Additional fields depending on type
    if is_binary:
        payload["payload_on"] = "on"
        payload["payload_off"] = "off"
        #payload["device_class"] = device_class or "connectivity"
    else:
        if unit:
            payload["unit_of_measurement"] = unit
        if device_class:
            payload["device_class"] = device_class

    send_mqtt_message(discovery_topic, payload, retain=True)

#-------------------------------------------------------------------------------
def mqtt_get_system_status():
    openDB()

    mqttstartTime = datetime.datetime.now(timezone.utc).replace(second=0, microsecond=0)
    formatted_time = mqttstartTime.strftime("%Y-%m-%dT%H:%M:%SZ")

    data = {}
    status_data = {}
    local_data = {}
    status_data["status"] = "off" if os.path.exists("../../db/setting_stoparpscan") else "on"
    status_data["time"] = formatted_time

    # General stats
    sql.execute('''
        SELECT
            (SELECT COUNT(*) FROM Devices WHERE dev_Archived=0),
            (SELECT COUNT(*) FROM Devices WHERE dev_Archived=0 AND dev_PresentLastScan=1),
            (SELECT COUNT(*) FROM Devices WHERE dev_Archived=0 AND dev_NewDevice=1),
            (SELECT COUNT(*) FROM Devices WHERE dev_Archived=0 AND dev_AlertDeviceDown=1 AND dev_PresentLastScan=0),
            (SELECT COUNT(*) FROM Devices WHERE dev_Archived=0 AND dev_AlertDeviceDown=0 AND dev_PresentLastScan=0),
            (SELECT COUNT(*) FROM Devices WHERE dev_Archived=1)
    ''')
    row = sql.fetchone()
    keys = ["all", "online", "new", "down", "offline", "archive"]
    if row:
        status_data.update(dict(zip(keys, row)))

    data["status"] = status_data

    # local section
    sql.execute('''
        SELECT
            (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource='local' AND dev_Archived=0),
            (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource='local' AND dev_Archived=0 AND dev_PresentLastScan=1),
            (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource='local' AND dev_Archived=0 AND dev_NewDevice=1),
            (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource='local' AND dev_Archived=0 AND dev_AlertDeviceDown=1 AND dev_PresentLastScan=0),
            (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource='local' AND dev_Archived=0 AND dev_AlertDeviceDown=0 AND dev_PresentLastScan=0),
            (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource='local' AND dev_Archived=1)
    ''')
    row = sql.fetchone()
    keys = ["all", "online", "new", "down", "offline", "archive"]
    if row:
        local_data.update(dict(zip(keys, row)))

    sql.execute("SELECT All_Devices, Down_Devices, Online_Devices FROM Online_History WHERE data_source='icmp_scan' ORDER BY Scan_Date DESC LIMIT 1")
    row = sql.fetchone()
    if row:
        local_data["icmp_all"] = row[0]
        local_data["icmp_offline"] = row[1]
        local_data["icmp_online"] = row[2]

    data["local"] = local_data

    # Satellites
    sql.execute("SELECT sat_token, sat_name FROM Satellites")
    satellites = sql.fetchall()
    for sat_token, sat_name in satellites:
        sql.execute('''
            SELECT
                (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource=? AND dev_Archived=0),
                (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource=? AND dev_Archived=0 AND dev_PresentLastScan=1),
                (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource=? AND dev_Archived=0 AND dev_NewDevice=1),
                (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource=? AND dev_Archived=0 AND dev_AlertDeviceDown=1 AND dev_PresentLastScan=0),
                (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource=? AND dev_Archived=0 AND dev_AlertDeviceDown=0 AND dev_PresentLastScan=0),
                (SELECT COUNT(*) FROM Devices WHERE dev_ScanSource=? AND dev_Archived=1)
        ''', (sat_token,) * 6)
        row = sql.fetchone()
        keys = ["all", "online", "new", "down", "offline", "archive"]
        if row:
            data[sat_name] = dict(zip(keys, row))

    closeDB()

    return data

#-------------------------------------------------------------------------------
def normalize_mac(mac: str) -> str:
    return mac.lower().replace(":", "")

#-------------------------------------------------------------------------------
def publish_ha_device_entities(device_row):
    mac = device_row["dev_MAC"]
    mac_clean = normalize_mac(mac)

    name = device_row["dev_Name"] or "Unknown"
    vendor = device_row["dev_Vendor"] or "Unknown"
    model = device_row["dev_Model"] or "Network Device"
    location = device_row["dev_Location"] or "Unknown"
    online_state = "ON" if device_row["dev_PresentLastScan"] else "OFF"
    ip_address = device_row["dev_LastIP"] or "0.0.0.0"
    scan_source_value = mqtt_resolve_scan_source(device_row["dev_ScanSource"])

    localhostlist = ['local', '']
    if device_row["dev_ScanSource"] in localhostlist and mac.startswith('Internet'):
        device_identifier = f"pialert_internet_local"
    elif device_row["dev_ScanSource"] not in localhostlist and mac.startswith('Internet'):
        device_identifier = f"pialert_internet_{scan_source_value}"
    else:
        device_identifier = f"pialert_{mac_clean}"

    device_info = {
        "identifiers": [f"{device_identifier}"],
        "name": name,
        "manufacturer": vendor,
        "model": model,
        "connections": [["mac", mac]]
    }

    # Binary Sensor: Online / Offline
    binary_topic = f"homeassistant/binary_sensor/{device_identifier}_online/config"
    binary_payload = {
        "name": f"Online",
        "unique_id": f"{device_identifier}_online",
        "state_topic": f"pi_alert/device/{device_identifier}/online",
        "payload_on": "ON",
        "payload_off": "OFF",
        "device_class": "connectivity",
        "device": device_info
    }
    send_mqtt_message(binary_topic, binary_payload, retain=True)
    send_mqtt_message(f"pi_alert/device/{device_identifier}/online", online_state, retain=True)

    # IP
    ip_topic = f"homeassistant/sensor/{device_identifier}_ip/config"
    ip_payload = {
        "name": f"IP",
        "unique_id": f"{device_identifier}_ip",
        "state_topic": f"pi_alert/device/{device_identifier}/ip",
        "icon": "mdi:ip",
        "device": device_info
    }
    send_mqtt_message(ip_topic, ip_payload, retain=True)
    send_mqtt_message(f"pi_alert/device/{device_identifier}/ip", ip_address, retain=True)

    # Location
    loc_topic = f"homeassistant/sensor/{device_identifier}_location/config"
    loc_payload = {
        "name": f"Standort",
        "unique_id": f"{device_identifier}_location",
        "state_topic": f"pi_alert/device/{device_identifier}/location",
        "icon": "mdi:home",
        "device": device_info
    }
    send_mqtt_message(loc_topic, loc_payload, retain=True)
    send_mqtt_message(f"pi_alert/device/{device_identifier}/location", location, retain=True)

    # ScanSource
    scan_topic = f"homeassistant/sensor/{device_identifier}_scansource/config"
    scan_payload = {
        "name": "Scan Source",
        "unique_id": f"{device_identifier}_scansource",
        "state_topic": f"pi_alert/device/{device_identifier}/scansource",
        "icon": "mdi:radar",
        "device": device_info
    }
    send_mqtt_message(scan_topic, scan_payload, retain=True)
    send_mqtt_message(f"pi_alert/device/{device_identifier}/scansource", scan_source_value ,retain=True)

#-------------------------------------------------------------------------------
def remove_ha_entities(mac: str, source):
    mac_clean = normalize_mac(mac)

    scan_source_value = mqtt_resolve_scan_source(source)

    localhostlist = ['local', '']
    if source in localhostlist and mac.startswith('Internet'):
        device_identifier = f"pialert_internet_local"
    elif source not in localhostlist and mac.startswith('Internet'):
        device_identifier = f"pialert_internet_{scan_source_value}"
    else:
        device_identifier = f"pialert_{mac_clean}"

    # if device_identifier.startswith("pialert_internet"):
    #     print(f"DEBUG: {device_identifier}")

    topics = [
        f"homeassistant/binary_sensor/{device_identifier}_online/config",
        f"homeassistant/sensor/{device_identifier}_ip/config",
        f"homeassistant/sensor/{device_identifier}_location/config",
        f"homeassistant/sensor/{device_identifier}_scansource/config"
    ]

    for topic in topics:
        send_mqtt_message(topic, "", retain=True)

    print_log(f"HA Device fully removed: {mac}")

#-------------------------------------------------------------------------------
def sync_mqtt_devices_stateless():
    openDB()
    
    # Publish all enabled devices
    sql.execute("SELECT * FROM Devices WHERE dev_MQTTDevice=1")
    activeMQTT = sql.fetchall()
    for device in activeMQTT:
        publish_ha_device_entities(device)
    print(f"    Published MQTT-Devices: {len(activeMQTT)}")

    # Remove all disabled devices
    sql.execute("SELECT dev_MAC, dev_ScanSource FROM Devices WHERE dev_MQTTDevice=0")
    inactiveMQTT = sql.fetchall()
    for row in inactiveMQTT:
        remove_ha_entities(row["dev_MAC"],row["dev_ScanSource"])
    print(f"    Remove disabled MQTT-Devices")

    closeDB()

#-------------------------------------------------------------------------------
def send_mqtt_message(topic: str, value, retain: bool = False):
    client = Client(protocol=MQTTv311, callback_api_version=CallbackAPIVersion.VERSION2)

    if REPORT_MQTT_USERNAME and REPORT_MQTT_PASSWORD:
        client.username_pw_set(REPORT_MQTT_USERNAME, REPORT_MQTT_PASSWORD)

    if REPORT_MQTT_TLS:
        client.tls_set()

    done = threading.Event()

    def on_connect(client, userdata, flags, reason_code, properties):
        print_log(f"✅ Connected to MQTT – send to {topic}")
        payload = value if isinstance(value, str) else json.dumps(value)
        result = client.publish(topic, payload, retain=retain)
        if result.rc != MQTT_ERR_SUCCESS:
            print_log(f"   ❌ Error during transmission ({result.rc})")
        client.disconnect()

    def on_disconnect(client, userdata, reason_code, properties, reason_string=None):
        print_log("🔌 MQTT-Connection closed")
        done.set()

    client.on_connect = on_connect
    client.on_disconnect = on_disconnect

    try:
        client.connect(REPORT_MQTT_BROKER, REPORT_MQTT_PORT, 60)
        client.loop_start()
        done.wait(timeout=5)
        client.loop_stop()
    except Exception as e:
        print_log(f"❌ MQTT-Connection: {e}")

#-------------------------------------------------------------------------------
def mqtt_resolve_scan_source(source):
    scan_source = source

    if not scan_source:
        return "unknown"

    if scan_source == "local":
        return "local"

    sql.execute(
        "SELECT sat_name FROM Satellites WHERE sat_token = ?",
        (scan_source,)
    )
    row = sql.fetchone()

    if row:
        return row[0]
    else:
        return "unknown"

#===============================================================================
# REPORTING
#===============================================================================
def continuously_new_email_reporting(start_time, crontab_string):
    global mail_text
    global mail_html

    # convert cron string
    crontab_parts = crontab_string.split()
    minute = parse_cron_part(crontab_parts[0], start_time.minute, 0, 60) # last value is the exit value, meaning the 1. invalid value
    hour = parse_cron_part(crontab_parts[1], start_time.hour, 0, 60)
    day_of_month = parse_cron_part(crontab_parts[2], start_time.day, 1, 32)
    month = parse_cron_part(crontab_parts[3], start_time.month, 1, 13)
    day_of_week = parse_cron_part(crontab_parts[4], start_time.weekday(), 0, 7)

    # Compare cron
    if (start_time.minute in minute) and (start_time.hour in hour) and (start_time.day in day_of_month) and \
       (start_time.month in month) and (start_time.weekday() in day_of_week):

        # Reporting section
        openDB()

        sql.execute("""SELECT sat_name, sat_token FROM Satellites""")
        rows = sql.fetchall()

        # create Dictionary
        satellite_dict = {}
        for row in rows:
            sat_name = row[0]
            sat_token = row[1]
            satellite_dict[sat_token] = sat_name

        # Open text Templates
        with open(f'{PIALERT_BACK_PATH}/report_template.txt', 'r') as template_file:
            mail_text = template_file.read()
        with open(f'{PIALERT_BACK_PATH}/report_template.html', 'r') as template_file:
            mail_html = template_file.read()

        # Report Header & footer
        timeFormated = startTime.strftime ('%Y-%m-%d %H:%M')
        mail_text = mail_text.replace ('<REPORT_DATE>', timeFormated)
        mail_html = mail_html.replace ('<REPORT_DATE>', timeFormated)

        mail_text = mail_text.replace ('<SERVER_NAME>', socket.gethostname() )
        mail_html = mail_html.replace ('<SERVER_NAME>', socket.gethostname() )

        format_report_section (False, 'SECTION_INTERNET',
            'TABLE_INTERNET', '', '')
        format_report_section (False, 'SECTION_DEVICES_DOWN',
            'TABLE_DEVICES_DOWN', '', '')
        format_report_section (False, 'SECTION_EVENTS',
            'TABLE_EVENTS', '', '')

        # Compose New Devices Section
        mail_section_new_devices = False
        mail_text_new_devices = ''
        mail_html_new_devices = ''
        text_line_template = '{}\t{}\n\t{}\t\t{}\n\t{}\t{}\n\t{}\t\t{}\n\t{}\t{}\n\t{}\t{}\n\n'
        html_line_template    = '<tr>\n'+ \
            '  <td> <a href="{}{}"> {} </a></td><td> {} </td>'+\
            '  <td> {} </td><td> {} </td><td> {} </td><td> {} </td></tr>\n'
        
        sql.execute ("""SELECT * FROM Devices
                        WHERE dev_NewDevice = 1
                        ORDER BY dev_Name""")

        for eventAlert in sql :
            # Get currentvSatellite Name
            dev_scan_source = eventAlert["dev_ScanSource"]
            if dev_scan_source != 'local':
                if dev_scan_source in satellite_dict:
                    sat_name = satellite_dict[dev_scan_source]
                else:
                    sat_name = dev_scan_source
            else:
                sat_name = 'local'

            mail_section_new_devices = True
            mail_text_new_devices += text_line_template.format (
                'Name: ', eventAlert['dev_Name'],
                'MAC: ', eventAlert['dev_MAC'],
                'LastIP: ', eventAlert['dev_LastIP'],
                'Time: ', eventAlert['dev_FirstConnection'],
                'Source: ', sat_name,
                'Comments: ', eventAlert['dev_Comments'])
            mail_html_new_devices += html_line_template.format (
                REPORT_DEVICE_URL, eventAlert['dev_MAC'], eventAlert['dev_MAC'],
                eventAlert['dev_FirstConnection'], eventAlert['dev_LastIP'],
                eventAlert['dev_Name'], eventAlert['dev_Comments'], sat_name)

        format_report_section (mail_section_new_devices, 'SECTION_NEW_DEVICES',
            'TABLE_NEW_DEVICES', mail_text_new_devices, mail_html_new_devices)

        # Send Mail
        if mail_section_new_devices == True :
            # Send Mail
            sending_notifications ('pialert', mail_html, mail_text)

        closeDB()
    else:
        print("    Notification NOT executed.")
    return 0

#-------------------------------------------------------------------------------
def email_reporting():
    global mail_text
    global mail_html

    # Get Notification Preset Configuration from config file
    try:
        preset_events = NEW_DEVICE_PRESET_EVENTS
    except NameError:
        preset_events = True

    try:
        preset_down = NEW_DEVICE_PRESET_DOWN
    except NameError:
        preset_down = False

    # Reporting section
    print('\nReporting...')
    openDB()

    sql.execute("""SELECT sat_name, sat_token FROM Satellites""")
    rows = sql.fetchall()

    # create Dictionary
    satellite_dict = {}
    for row in rows:
        sat_name = row[0]
        sat_token = row[1]
        satellite_dict[sat_token] = sat_name

    # Disable reporting on events for devices where reporting is disabled based on the MAC address
    sql.execute ("""UPDATE Events SET eve_PendingAlertEmail = 0
                    WHERE eve_PendingAlertEmail = 1 AND eve_EventType != 'Device Down' AND eve_MAC IN
                        (
                            SELECT dev_MAC FROM Devices WHERE dev_AlertEvents = 0 
                        )""")
    sql.execute ("""UPDATE Events SET eve_PendingAlertEmail = 0
                    WHERE eve_PendingAlertEmail = 1 AND eve_EventType = 'Device Down' AND eve_MAC IN
                        (
                            SELECT dev_MAC FROM Devices WHERE dev_AlertDeviceDown = 0 
                        )""")

    # Open text Templates
    with open(f'{PIALERT_BACK_PATH}/report_template.txt', 'r') as template_file:
        mail_text = template_file.read()
    with open(f'{PIALERT_BACK_PATH}/report_template.html', 'r') as template_file:
        mail_html = template_file.read()

    # Report Header & footer
    timeFormated = startTime.strftime ('%Y-%m-%d %H:%M')
    mail_text = mail_text.replace ('<REPORT_DATE>', timeFormated)
    mail_html = mail_html.replace ('<REPORT_DATE>', timeFormated)

    mail_text = mail_text.replace ('<SERVER_NAME>', socket.gethostname() )
    mail_html = mail_html.replace ('<SERVER_NAME>', socket.gethostname() )

    # Compose Internet Section
    print('    Formating report...')
    mail_section_Internet = False
    mail_text_Internet = ''
    mail_html_Internet = ''
    text_line_template = '{}\n\t{}\n\t{}\n\t{}\n\t{}\n'
    html_line_template = '<tr>\n'+ \
        '  <td> <a href="{}{}"> {} </a> </td><td> {} </td><td> {} </td>'+ \
        '  <td style="font-size: 24px; color:#D02020"> {} </td>'+ \
        '  <td> {} </td></tr>\n'

    # WHERE eve_PendingAlertEmail = 1 AND eve_MAC = 'Internet'
    sql.execute ("""SELECT * FROM Events
                    WHERE eve_PendingAlertEmail = 1 AND eve_MAC LIKE 'Internet%'
                    ORDER BY eve_DateTime""")

    for eventAlert in sql :
        # Trim internet device name
        event_mac = eventAlert['eve_MAC']
        if len(event_mac) > 20:
            event_mac = event_mac[:20] + '...'

        mail_section_Internet = True
        mail_text_Internet += text_line_template.format (
            event_mac, eventAlert['eve_EventType'], eventAlert['eve_DateTime'],
            eventAlert['eve_IP'], eventAlert['eve_AdditionalInfo'])
        mail_html_Internet += html_line_template.format (
            REPORT_DEVICE_URL, eventAlert['eve_MAC'], event_mac,
            eventAlert['eve_EventType'], eventAlert['eve_DateTime'],
            eventAlert['eve_IP'], eventAlert['eve_AdditionalInfo'])

    format_report_section (mail_section_Internet, 'SECTION_INTERNET',
        'TABLE_INTERNET', mail_text_Internet, mail_html_Internet)

    # Compose New Devices Section
    mail_section_new_devices = False
    mail_text_new_devices = ''
    mail_html_new_devices = ''
    text_line_template = '{}\t{}\n\t{}\t\t{}\n\t{}\t\t{}\n\t{}\t\t{}\n\t{}\t{}\n\t{}\t{}\n\n'
    html_line_template    = '<tr>\n'+ \
        '  <td> <a href="{}{}"> {} </a></td><td> {} </td>'+\
        '  <td> {} </td><td> {} </td><td> {} </td><td> {} </td></tr>\n'
    
    sql.execute ("""SELECT * FROM Events_Devices
                    WHERE eve_PendingAlertEmail = 1
                      AND eve_EventType = 'New Device'
                    ORDER BY eve_DateTime""")

    for eventAlert in sql :
        # Get currentvSatellite Name
        dev_scan_source = eventAlert["dev_ScanSource"]
        if dev_scan_source != 'local':
            if dev_scan_source in satellite_dict:
                sat_name = satellite_dict[dev_scan_source]
            else:
                sat_name = dev_scan_source
        else:
            sat_name = 'local'

        mail_section_new_devices = True
        mail_text_new_devices += text_line_template.format (
            'Name: ', eventAlert['dev_Name'],
            'MAC: ', eventAlert['eve_MAC'],
            'IP: ', eventAlert['eve_IP'],
            'Time: ', eventAlert['eve_DateTime'],
            'Source: ', sat_name,
            'More Info: ', eventAlert['eve_AdditionalInfo'])
        mail_html_new_devices += html_line_template.format (
            REPORT_DEVICE_URL, eventAlert['eve_MAC'], eventAlert['dev_Name'],
            eventAlert['eve_DateTime'], eventAlert['eve_IP'],
            eventAlert['eve_MAC'], eventAlert['eve_AdditionalInfo'], sat_name)

    format_report_section (mail_section_new_devices, 'SECTION_NEW_DEVICES',
        'TABLE_NEW_DEVICES', mail_text_new_devices, mail_html_new_devices)

    # Compose Devices Down Section
    mail_section_devices_down = False
    mail_text_devices_down = ''
    mail_html_devices_down = ''
    text_line_template = '{}\t{}\n\t{}\t\t{}\n\t{}\t\t{}\n\t{}\t{}\n\t{}\t\t{}\n\n'
    html_line_template     = '<tr>\n'+ \
        '  <td> <a href="{}{}"> {} </a>  </td><td> {} </td>'+ \
        '  <td> {} </td><td> {} </td><td> {} </td></tr>\n'

    sql.execute ("""SELECT * FROM Events_Devices
                    WHERE eve_PendingAlertEmail = 1
                      AND eve_EventType = 'Device Down'
                    ORDER BY eve_DateTime""")

    for eventAlert in sql :
        # Get currentvSatellite Name
        dev_scan_source = eventAlert["dev_ScanSource"]
        if dev_scan_source != 'local':
            if dev_scan_source in satellite_dict:
                sat_name = satellite_dict[dev_scan_source]
            else:
                sat_name = dev_scan_source
        else:
            sat_name = 'local'

        mail_section_devices_down = True
        mail_text_devices_down += text_line_template.format (
            'Name: ', eventAlert['dev_Name'],
            'MAC: ', eventAlert['eve_MAC'],
            'Time: ', eventAlert['eve_DateTime'],
            'Source: ', sat_name,               
            'IP: ', eventAlert['eve_IP'])
        mail_html_devices_down += html_line_template.format (
            REPORT_DEVICE_URL, eventAlert['eve_MAC'], eventAlert['dev_Name'],
            eventAlert['eve_DateTime'], eventAlert['eve_IP'],
            eventAlert['eve_MAC'], sat_name)

    format_report_section (mail_section_devices_down, 'SECTION_DEVICES_DOWN',
        'TABLE_DEVICES_DOWN', mail_text_devices_down, mail_html_devices_down)

    # Compose Events Section
    mail_section_events = False
    mail_text_events   = ''
    mail_html_events   = ''
    text_line_template = '{}\t{}\n\t{}\t\t{}\n\t{}\t\t{}\n\t{}\t\t{}\n\t{}\t\t{}\n\t{}\t{}\n\t{}\t{}\n\n'
    html_line_template = '<tr>\n '+ \
            '  <td><a href="{}{}"> {} </a></td><td> {} </td>'+ \
            '  <td> {} </td><td> {} </td><td> {} </td>'+ \
            '  <td> {} </td><td> {} </td></tr>\n'

    sql.execute ("""SELECT * FROM Events_Devices
                    WHERE eve_PendingAlertEmail = 1
                      AND eve_EventType IN ('Connected', 'Disconnected', 'IP Changed', 'Internet IP Changed', 'Inter-Satellite movement')
                    ORDER BY eve_DateTime""")

    for eventAlert in sql :
        # Get currentvSatellite Name
        dev_scan_source = eventAlert["dev_ScanSource"]
        if dev_scan_source != 'local':
            if dev_scan_source in satellite_dict:
                sat_name = satellite_dict[dev_scan_source]
            else:
                sat_name = dev_scan_source
        else:
            sat_name = 'local'

        mail_section_events = True
        mail_text_events += text_line_template.format (
            'Name: ', eventAlert['dev_Name'], 
            'MAC: ', eventAlert['eve_MAC'], 
            'IP: ', eventAlert['eve_IP'],
            'Time: ', eventAlert['eve_DateTime'],
            'Event: ', eventAlert['eve_EventType'],
            'Source: ', sat_name,
            'More Info: ', eventAlert['eve_AdditionalInfo'])
        mail_html_events += html_line_template.format (
            REPORT_DEVICE_URL, eventAlert['eve_MAC'], eventAlert['dev_Name'],
            eventAlert['eve_DateTime'], eventAlert['eve_IP'],
            eventAlert['eve_EventType'], eventAlert['eve_MAC'], sat_name,
            eventAlert['eve_AdditionalInfo'])

    format_report_section (mail_section_events, 'SECTION_EVENTS',
        'TABLE_EVENTS', mail_text_events, mail_html_events)

    # Send Mail
    if mail_section_Internet == True or mail_section_new_devices == True \
    or mail_section_devices_down == True or mail_section_events == True :
        # Send Mail
        sending_notifications ('pialert', mail_html, mail_text)
    else :
        print('    No changes to report...')

    # Clean Pending Alert Events
    sql.execute("""UPDATE Devices SET dev_LastNotification = ?
                   WHERE dev_MAC IN (SELECT eve_MAC FROM Events
                                      WHERE eve_PendingAlertEmail = 1)
                """, (datetime.datetime.now(),))
    sql.execute("""UPDATE Events SET eve_PendingAlertEmail = 0
                   WHERE eve_PendingAlertEmail = 1""")

    # Set Notification Presets
    sql.execute("""UPDATE Devices SET dev_AlertEvents = ?, dev_AlertDeviceDown = ?
                   WHERE dev_NewDevice = 1
                """,(preset_events, preset_down,))

    # print('    Notifications:', sql.rowcount)

    sql_connection.commit()

    if SCAN_WEBSERVICES:
        if str(startTime)[15] == "0":
            service_monitoring_notification()

    if ICMPSCAN_ACTIVE:
        icmphost_monitoring_notification()

    closeDB()

#-------------------------------------------------------------------------------
def send_pushsafer(_Text):
    try:
        notification_target = PUSHSAFER_DEVICE
    except NameError:
        notification_target = "a"

    try:
        result = PUSHSAFER_PRIO
    except NameError:
        PUSHSAFER_PRIO = 0

    try:
        notification_sound = PUSHSAFER_SOUND
    except NameError:
        notification_sound = 22

    # Remove one linebrake between "Server" and the headline of the event type
    _pushsafer_Text = _Text.replace('\n\n\n', '\n\n')
    # extract event type headline to use it in the notification headline
    findsubheadline = _pushsafer_Text.split('\n')
    subheadline = findsubheadline[3]
    url = 'https://www.pushsafer.com/api'
    post_fields = {
        "t" : 'Pi.Alert Message - '+subheadline,
        "m" : _pushsafer_Text,
        "s" : notification_sound,
        "v" : 3,
        "i" : 148,
        "c" : '#ef7f7f',
        "d" : notification_target,
        "u" : REPORT_DASHBOARD_URL,
        "ut" : 'Open Pi.Alert',
        "k" : PUSHSAFER_TOKEN,
        "pr" : PUSHSAFER_PRIO,
        }
    requests.post(url, data=post_fields)

#-------------------------------------------------------------------------------
def send_pushover (_Text):
    # Remove one linebrake between "Server" and the headline of the event type
    _pushover_Text = _Text.replace('\n\n\n', '\n\n')
    # Text-layout tweak
    _pushover_Text = _pushover_Text.replace('IP: \t\t', 'IP: \t\t\t')
    # extract event type headline to use it in the notification headline
    findsubheadline = _pushover_Text.split('\n')
    subheadline = findsubheadline[3]

    try:
        result = PUSHOVER_PRIO
    except NameError:
        PUSHOVER_PRIO = 0

    try:
        notification_sound = PUSHOVER_SOUND
    except NameError:
        notification_sound = 'siren'

    url = 'https://api.pushover.net/1/messages.json'
    post_fields = {
        "token": PUSHOVER_TOKEN,
        "user": PUSHOVER_USER,
        "title" : 'Pi.Alert Message - '+subheadline,
        "message" : _pushover_Text,
        "priority" : PUSHOVER_PRIO,
        "sound" : notification_sound,
        }
    requests.post(url, data=post_fields)

#-------------------------------------------------------------------------------
def send_ntfy (_Text):
    # Prepare header
    headers = {
        "Title": "Pi.Alert Notification",
        "Priority": NTFY_PRIORITY,
        "Tags": "warning"
    }

    if NTFY_CLICKABLE == True:
        headers["Click"] = REPORT_DASHBOARD_URL
    # if username and password are set generate hash and update header
    if NTFY_PASSWORD != "":
    # Generate hash for basic auth
        usernamepassword = "{}:{}".format(NTFY_USER,NTFY_PASSWORD)
        basichash = b64encode(bytes(NTFY_USER + ':' + NTFY_PASSWORD, "utf-8")).decode("ascii")

    # add authorization header with hash
        headers["Authorization"] = "Basic {}".format(basichash)

    requests.post("{}/{}".format( NTFY_HOST, NTFY_TOPIC),
    data=_Text,
    headers=headers)

#-------------------------------------------------------------------------------
def send_telegram (_Text):
    # Remove one linebrake between "Server" and the headline of the event type
    _telegram_Text = _Text.replace('\n\n\n', '\n\n')
    # extract event type headline to use it in the notification headline
    findsubheadline = _telegram_Text.split('\n')
    subheadline = findsubheadline[3]
    runningpath = os.path.abspath(os.path.dirname(__file__))
    stream = os.popen(runningpath+'/shoutrrr/'+SHOUTRRR_BINARY+'/shoutrrr send --url "'+TELEGRAM_BOT_TOKEN_URL+'" --message "'+_telegram_Text+'" --title "Pi.Alert - '+subheadline+'"')

#-------------------------------------------------------------------------------
def send_discord (_Text):
    block = _Text.replace('\n\n\n', '\n\n')
    event_type = next((line.split(":", 1)[1].strip() for line in block.splitlines() if line.strip().lower().startswith("event:")), "Alert")
    # event_type = next((line.split(":")[1].strip() for line in block.splitlines() if line.startswith("Event:")), "Alert")
    color_map = {"Connected": 65280, "Disconnected": 16711680, "Alert": 16753920}
    color = color_map.get(event_type, 3447003)

    payload = {
        "embeds": [
            {
                "title": f"Pi.Alert - {event_type}",
                "description": block,
                "color": color
            }
        ]
    }

    requests.post(DISCORD_BOT_TOKEN_URL, json=payload)

#-------------------------------------------------------------------------------
def send_webgui (_Text):
    # Remove one linebrake between "Server" and the headline of the event type
    _webgui_Text = _Text.replace('\n\n\n', '\n\n')
    # extract event type headline to use it in the notification headline
    findsubheadline = _webgui_Text.split('\n')
    subheadline = findsubheadline[3]
    _webgui_filename = time.strftime("%Y%m%d-%H%M%S") + "_" + subheadline + ".txt"
    if (os.path.exists(REPORTPATH_WEBGUI + _webgui_filename) == False):
        f = open(REPORTPATH_WEBGUI + _webgui_filename, "w")
        f.write(_webgui_Text)
        f.close()
    set_reports_file_permissions()

#===============================================================================
# Sending Notifications
#===============================================================================
def sending_notifications (_type, _html_text, _txt_text):
    if _type in ['webservice']:
        REPORT_CHANNELS_WEBMON = [
            (REPORT_MAIL_WEBMON,      "email",     lambda: send_email(_txt_text, _html_text)),
            (REPORT_PUSHSAFER_WEBMON, "PUSHSAFER", lambda: send_pushsafer(_txt_text)),
            (REPORT_PUSHOVER_WEBMON,  "PUSHOVER",  lambda: send_pushover(_txt_text)),
            (REPORT_TELEGRAM_WEBMON,  "Telegram",  lambda: send_telegram(_txt_text)),
            (REPORT_NTFY_WEBMON,      "NTFY",      lambda: send_ntfy(_txt_text)),
            (REPORT_DISCORD_WEBMON,   "Discord",   lambda: send_discord(_txt_text)),
            (REPORT_WEBGUI_WEBMON,    "WebUI",     lambda: send_webgui(_txt_text)),
        ]

        for enabled, name, action in REPORT_CHANNELS_WEBMON:
            if not enabled:
                print(f"    Skip {name}...")
                continue

            print(f"    Sending report by {name}...")

            try:
                action()
                print(f"    {name} sent successfully")
            except Exception as e:
                print(f"    ERROR sending via {name}: {e}")

    elif _type in ['pialert', 'icmp_mon', 'rogue_dhcp']:
        REPORT_CHANNELS = [
            (REPORT_MAIL,      "email",     lambda: send_email(_txt_text, _html_text)),
            (REPORT_PUSHSAFER, "PUSHSAFER", lambda: send_pushsafer(_txt_text)),
            (REPORT_PUSHOVER,  "PUSHOVER",  lambda: send_pushover(_txt_text)),
            (REPORT_TELEGRAM,  "Telegram",  lambda: send_telegram(_txt_text)),
            (REPORT_NTFY,      "NTFY",      lambda: send_ntfy(_txt_text)),
            (REPORT_DISCORD,   "Discord",   lambda: send_discord(_txt_text)),
            (REPORT_WEBGUI,    "WebUI",     lambda: send_webgui(_txt_text)),
        ]

        for enabled, name, action in REPORT_CHANNELS:
            if not enabled:
                print(f"    Skip {name}...")
                continue

            print(f"    Sending report by {name}...")

            try:
                action()
                print(f"    {name} sent successfully")
            except Exception as e:
                print(f"    ERROR sending via {name}: {e}")

#-------------------------------------------------------------------------------
def format_report_section (pActive, pSection, pTable, pText, pHTML):
    global mail_text
    global mail_html

    # Replace section text
    if pActive :
        mail_text = mail_text.replace ('<'+ pTable +'>', pText)
        mail_html = mail_html.replace ('<'+ pTable +'>', pHTML)       

        mail_text = remove_tag (mail_text, pSection)       
        mail_html = remove_tag (mail_html, pSection)
    else:
        mail_text = remove_section (mail_text, pSection)
        mail_html = remove_section (mail_html, pSection)

#-------------------------------------------------------------------------------
def format_report_section_services (pActive, pSection, pTable, pText, pHTML):
    global mail_text_webservice
    global mail_html_webservice

    # Replace section text
    if pActive :
        mail_text_webservice = mail_text_webservice.replace ('<'+ pTable +'>', pText)
        mail_html_webservice = mail_html_webservice.replace ('<'+ pTable +'>', pHTML)       

        mail_text_webservice = remove_tag (mail_text_webservice, pSection)       
        mail_html_webservice = remove_tag (mail_html_webservice, pSection)
    else:
        mail_text_webservice = remove_section (mail_text_webservice, pSection)
        mail_html_webservice = remove_section (mail_html_webservice, pSection)

#-------------------------------------------------------------------------------
def format_report_section_icmp (pActive, pSection, pTable, pText, pHTML):
    global mail_html_icmphost
    global mail_text_icmphost

    # Replace section text
    if pActive :
        mail_text_icmphost = mail_text_icmphost.replace ('<'+ pTable +'>', pText)
        mail_html_icmphost = mail_html_icmphost.replace ('<'+ pTable +'>', pHTML)       

        mail_text_icmphost = remove_tag (mail_text_icmphost, pSection)       
        mail_html_icmphost = remove_tag (mail_html_icmphost, pSection)
    else:
        mail_text_icmphost = remove_section (mail_text_icmphost, pSection)
        mail_html_icmphost = remove_section (mail_html_icmphost, pSection)

#-------------------------------------------------------------------------------
def remove_section (pText, pSection):
    # Search section into the text
    if pText.find ('<'+ pSection +'>') >=0 \
    and pText.find ('</'+ pSection +'>') >=0 : 
        # return text without the section
        return pText[:pText.find ('<'+ pSection+'>')] + \
               pText[pText.find ('</'+ pSection +'>') + len (pSection) +3:]
    else :
        # return all text
        return pText

#-------------------------------------------------------------------------------
def remove_tag (pText, pTag):
    # return text without the tag
    return pText.replace ('<'+ pTag +'>','').replace ('</'+ pTag +'>','')

#-------------------------------------------------------------------------------
def write_file(pPath, pText):
    # Write the text depending using the correct python version
    if sys.version_info < (3, 0):
        file = io.open (pPath , mode='w', encoding='utf-8')
        file.write ( pText.decode('unicode_escape') )
    else:
        file = open (pPath, 'w', encoding='utf-8')
        file.write (pText) 

    file.close() 

#-------------------------------------------------------------------------------
def append_line_to_file(pPath, pText):
    # append the line depending using the correct python version
    if sys.version_info < (3, 0):
        file = io.open (pPath , mode='a', encoding='utf-8')
        file.write ( pText.decode('unicode_escape') )
    else:
        file = open (pPath, 'a', encoding='utf-8')
        file.write (pText) 

    file.close() 

#-------------------------------------------------------------------------------
def send_email (pText, pHTML):
    # Compose email
    msg = MIMEMultipart('alternative')
    msg['Subject'] = 'Pi.Alert Report'
    msg['From'] = REPORT_FROM
    msg['To'] = REPORT_TO
    msg.attach (MIMEText (pText, 'plain'))
    msg.attach (MIMEText (pHTML, 'html'))

    # Send mail
    smtp_connection = smtplib.SMTP (SMTP_SERVER, SMTP_PORT)
    smtp_connection.ehlo()
    if not SafeParseGlobalBool("SMTP_SKIP_TLS"):
        smtp_connection.starttls()
        smtp_connection.ehlo()
    if not SafeParseGlobalBool("SMTP_SKIP_LOGIN"):
        escaped_password = repr(SMTP_PASS)[1:-1]
        smtp_connection.login (SMTP_USER, escaped_password)
    smtp_connection.sendmail (REPORT_FROM, REPORT_TO, msg.as_string())
    smtp_connection.quit()

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
