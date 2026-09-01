#!/usr/bin/env python
#
#-------------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector and Web service monitor
#
#  pialert.py - Back module. Network scanner, Web service monitor
#-------------------------------------------------------------------------------
#  Puche 2021                                              GNU GPLv3
#  leiweibau 2023                                          GNU GPLv3
#-------------------------------------------------------------------------------

#===============================================================================
# IMPORTS
#===============================================================================
from __future__ import print_function
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from requests.packages.urllib3.exceptions import InsecureRequestWarning
from time import sleep, time, strftime
from base64 import b64encode
try:
  from urlparse import urlparse
except ImportError:
  from urllib.parse import urlparse
from config_validation import ConfigValidationError, load_pialert_config, load_version_config
from notification_http import send_pushsafer_notification, send_pushover_notification
from telegram_notification import send_telegram_message
import sys, os, re, datetime, socket, io, smtplib, requests, time, pwd, glob, sqlite3, html

#===============================================================================
# CONFIG CONSTANTS
#===============================================================================
PIALERT_BACK_PATH = os.path.dirname(os.path.abspath(__file__))
PIALERT_PATH = f"{PIALERT_BACK_PATH}/.."
PIALERT_WEBSERVICES_LOG = f"{PIALERT_PATH}/log/pialert.webservices.log"
STOPPIALERT = f"{PIALERT_PATH}/config/setting_stoppialert"
PIALERT_DB_FILE = f"{PIALERT_PATH}/db/pialert.db"
PIALERT_DBTOOLS_FILE = f"{PIALERT_PATH}/db/pialert_tools.db"
REPORTPATH_WEBGUI = f"{PIALERT_PATH}/front/reports/"

try:
  globals().update(load_version_config(f"{PIALERT_PATH}/config/version.conf"))
  globals().update(load_pialert_config(
      f"{PIALERT_PATH}/config/pialert.conf", PIALERT_PATH))
except ConfigValidationError as exc:
  print("[Config] Invalid configuration: {}".format(exc), file=sys.stderr)
  raise SystemExit(1)

#===============================================================================
# MAIN
#===============================================================================
def main():
    global startTime
    global cycle
    global log_timestamp

    # Header
    print('\nPi.Alert v'+ VERSION_DATE)
    print('---------------------------------------------------------')
    print(f"Executing user: {get_username()}\n")
    
    # Initialize global variables
    log_timestamp  = datetime.datetime.now()

    # Timestamp
    startTime = datetime.datetime.now()
    startTime = startTime.replace (second=0, microsecond=0)

    # Check parameters
    if len(sys.argv) < 2 :
        print ('usage pialert_reporting_test.py reporting_test | nmap_scan_complete <queue-id>' )
        return
    cycle = str(sys.argv[1])

    if cycle != 'nmap_scan_complete' and len(sys.argv) != 2:
        print('Unsupported reporting arguments.', file=sys.stderr)
        return 1

    ## Main Commands
    if cycle == 'reporting_test':
        res = sending_notifications_test('Test')
    elif cycle == 'update_notification':
        res = sending_notifications_test('Update')
    elif cycle == 'reporting_starttimer':
        res = sending_notifications_test('noti_Timerstart')
    elif cycle == 'reporting_stoptimer':
        res = sending_notifications_test('noti_Timerstop')
    elif cycle == 'nmap_scan_complete' and len(sys.argv) == 3 and sys.argv[2].isdigit():
        notification = load_nmap_scan_notification(int(sys.argv[2]))
        if notification is None:
            print('Nmap queue entry not found.', file=sys.stderr)
            return 1
        res = sending_notifications_test('Nmap', notification)
    else:
        print('Unsupported reporting mode or arguments.', file=sys.stderr)
        return 1

    # Final menssage
    print ('\nDONE!!!\n\n')
    return 0    

#===============================================================================
# Set Env (Userpermissions DB-file)
#===============================================================================
def get_username():
    return pwd.getpwuid(os.getuid())[0]

# ------------------------------------------------------------------------------
def set_reports_file_permissions():
  os.system(f"sudo chown -R {get_username()}:www-data {REPORTPATH_WEBGUI}")
  os.system(f"sudo chmod -R 775 {REPORTPATH_WEBGUI}")

#===============================================================================
# Sending Notifications
#===============================================================================
def format_nmap_notification_timestamp(value):
    if value is None or str(value).strip() == '':
        return '-'
    return re.sub(r'(\d{2}:\d{2}:\d{2})\.\d+', r'\1', str(value).strip())


def load_nmap_scan_notification(queue_id):
    tools_connection = sqlite3.connect(PIALERT_DBTOOLS_FILE, timeout=5)
    tools_connection.row_factory = sqlite3.Row
    try:
        row = tools_connection.execute(
            """SELECT q.queue_id, q.device_mac, q.target_ip, q.status,
                      q.started_at, q.completed_at, q.last_error,
                      r.scan_result, r.reserve_a
               FROM Tools_Nmap_Queue q
               LEFT JOIN Tools_Nmap_ManScan r ON r.ID = q.result_id
               WHERE q.queue_id = ? LIMIT 1""",
            (queue_id,)
        ).fetchone()
    finally:
        tools_connection.close()
    if row is None:
        return None

    device_name = ''
    main_connection = sqlite3.connect(PIALERT_DB_FILE, timeout=5)
    try:
        device = main_connection.execute(
            'SELECT dev_Name FROM Devices WHERE dev_MAC = ? COLLATE NOCASE LIMIT 1',
            (row['device_mac'],)
        ).fetchone()
        if device is not None and device[0] is not None:
            device_name = str(device[0]).strip()
    finally:
        main_connection.close()

    tcp_count = 0
    udp_count = 0
    for line in str(row['scan_result'] or '').splitlines():
        fields = line.split('###', 3)
        if len(fields) < 2:
            continue
        if fields[1] == 'tcp':
            tcp_count += 1
        elif fields[1] == 'udp':
            udp_count += 1

    success = row['status'] == 'completed'
    lines = [
        'Detailed Nmap scan completed.' if success else 'Detailed Nmap scan failed.',
        '',
        'Name: {}'.format(device_name or row['device_mac']),
        '\tMAC: {}'.format(row['device_mac']),
        '\tIP: {}'.format(row['target_ip']),
        '\tStarted: {}'.format(format_nmap_notification_timestamp(row['started_at'])),
        '\tCompleted: {}'.format(format_nmap_notification_timestamp(row['completed_at'])),
    ]
    if success:
        lines.extend([
            '\tDuration: {} seconds'.format(row['reserve_a'] if row['reserve_a'] is not None else '-'),
            '\tTCP findings: {}'.format(tcp_count),
            '\tUDP findings: {}'.format(udp_count),
        ])
    else:
        lines.append('\tError: {}'.format(str(row['last_error'] or 'Unknown error')[:500]))
    return '\n'.join(lines)


def sending_notifications_test(_Mode, custom_message=None):
    if _Mode == 'Test' :
        notiMessage = "Test-Notification"
    elif _Mode == 'Update' :
        notiMessage = "Update-Notification\n\nA new Version of Pi.Alert is available."
    elif _Mode == 'noti_Timerstart' :
        notiMessage = "Pi.Alert is paused"
    elif _Mode == 'noti_Timerstop' :
        notiMessage = "Pi.Alert reactivated"
    elif _Mode == 'Nmap' and custom_message is not None:
        notiMessage = custom_message
    else:
        return 1

    channels = [
        (REPORT_MAIL or REPORT_MAIL_WEBMON, 'email',
         lambda: send_email(
             notiMessage, html.escape(notiMessage).replace('\n', '<br>'))),
        (REPORT_PUSHSAFER or REPORT_PUSHSAFER_WEBMON, 'PUSHSAFER',
         lambda: send_pushsafer_test(notiMessage)),
        (REPORT_PUSHOVER or REPORT_PUSHOVER_WEBMON, 'PUSHOVER',
         lambda: send_pushover_test(notiMessage)),
        (REPORT_TELEGRAM or REPORT_TELEGRAM_WEBMON, 'Telegram',
         lambda: send_telegram_test(notiMessage)),
        (REPORT_NTFY or REPORT_NTFY_WEBMON, 'NTFY',
         lambda: send_ntfy_test(notiMessage)),
        (REPORT_DISCORD or REPORT_DISCORD_WEBMON, 'Discord',
         lambda: send_discord_test(notiMessage)),
        (REPORT_WEBGUI or REPORT_WEBGUI_WEBMON, 'WebGUI',
         lambda: send_webgui_test(
             notiMessage, 'Nmap' if _Mode == 'Nmap' else 'Test')),
    ]

    print ('\nTest Reporting...')
    for enabled, name, action in channels:
        if not enabled:
            print('    Skip {}...'.format(name))
            continue
        print('    Sending report by {}...'.format(name))
        try:
            action()
            print('    {} sent successfully'.format(name))
        except Exception as exc:
            print('    ERROR sending via {}: {}'.format(name, exc))
    return 0

#-------------------------------------------------------------------------------
def send_ntfy_test(_notiMessage):
    headers = {
        "Title": "Pi.Alert Notification",
        "Priority": NTFY_PRIORITY,
        "Tags": "warning"
    }

    if NTFY_CLICKABLE == True:
        headers["Click"] = REPORT_DASHBOARD_URL
    #if NTFY_USER != "" and NTFY_PASSWORD != "":
    if NTFY_PASSWORD != "":
    # Generate hash for basic auth
        usernamepassword = f"{NTFY_USER}:{NTFY_PASSWORD}"
        basichash = b64encode(bytes(f'{NTFY_USER}:{NTFY_PASSWORD}',
                                "utf-8")).decode("ascii")

    # add authorization header with hash
        headers["Authorization"] = f"Basic {basichash}"

    requests.post(f"{NTFY_HOST}/{NTFY_TOPIC}", data=_notiMessage, headers=headers)

#-------------------------------------------------------------------------------
def send_pushsafer_test(_notiMessage):
    return send_pushsafer_notification(
        _notiMessage, 'Pi.Alert Message', REPORT_DASHBOARD_URL,
        PUSHSAFER_TOKEN, PUSHSAFER_DEVICE, PUSHSAFER_PRIO, PUSHSAFER_SOUND)

#-------------------------------------------------------------------------------
def send_pushover_test(_notiMessage):
    return send_pushover_notification(
        _notiMessage, 'Pi.Alert Message', PUSHOVER_TOKEN, PUSHOVER_USER,
        PUSHOVER_PRIO, PUSHOVER_SOUND, PUSHOVER_RETRY, PUSHOVER_EXPIRE)
#-------------------------------------------------------------------------------
def send_discord_test (_notiMessage):
    # block = _Text.replace('\n\n\n', '\n\n')
    # event_type = next((line.split(":")[1].strip() for line in block.splitlines() if line.startswith("Event:")), "Alert")
    # color_map = {"Connected": 65280, "Disconnected": 16711680, "Alert": 16753920}
    # color = color_map.get(event_type, 3447003)

    payload = {
        "embeds": [
            {
                "title": 'Pi.Alert Message',
                "description": _notiMessage,
                # "color": color
            }
        ]
    }

    requests.post(DISCORD_BOT_TOKEN_URL, json=payload)

#-------------------------------------------------------------------------------
def send_telegram_test(_notiMessage):
  return send_telegram_message(
      TELEGRAM_BOT_TOKEN,
      TELEGRAM_CHAT_IDS,
      _notiMessage,
      title='Pi.Alert',
      legacy_url=TELEGRAM_BOT_TOKEN_URL,
  )

#-------------------------------------------------------------------------------
def send_webgui_test(_notiMessage, filename_tag='Test'):
  _webgui_filename = time.strftime("%Y%m%d-%H%M%S") + "_" + filename_tag + ".txt"
  if (os.path.exists(REPORTPATH_WEBGUI + _webgui_filename) == False):
    with open(REPORTPATH_WEBGUI + _webgui_filename, "w") as f:
      f.write(_notiMessage)
  set_reports_file_permissions()

#-------------------------------------------------------------------------------
def remove_tag(pText, pTag):
    # return text without the tag
  return pText.replace(f'<{pTag}>', '').replace(f'</{pTag}>', '')

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
def send_email(pText, pHTML):
    # Compose email
    msg = MIMEMultipart('alternative')
    msg['Subject'] = 'Pi.Alert Report'
    msg['From'] = REPORT_FROM
    msg['To'] = REPORT_TO
    msg.attach (MIMEText (pText, 'plain'))
    msg.attach (MIMEText (pHTML, 'html'))

    # Send mail
    if SafeParseGlobalBool("SMTP_SSL"):
        smtp_connection = smtplib.SMTP_SSL (SMTP_SERVER, SMTP_PORT)
        smtp_connection.ehlo()
    else:
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
  return globals().get(boolVariable, False) is True

#===============================================================================
# UTIL
#===============================================================================
def print_log(pText):
    global log_timestamp

    # Check LOG actived
    if not PRINT_LOG :
        return

    # Current Time    
    log_timestamp2 = datetime.datetime.now()

    # Print line + time + elapsed time + text
    print ('--------------------> ',
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
