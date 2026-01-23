  <!-- Main Footer -->
  <footer class="main-footer">
    <!-- Default to the left -->
    <a class="leiweibau-hp-link" href="https://leiweibau.net/" target="_blank">leiweibau</a>
    <!-- To the right -->
    <div class="pull-right no-hidden-xs">
<?php
echo 'Version: ' . $conf_data['VERSION_DATE'];
?>
    </div>
  </footer>

</div>
<!-- ./wrapper -->

  <script src="js/hotkeys.js"></script>
  <script src="lib/AdminLTE/bower_components/jquery/dist/jquery.min.js"></script>
  <script src="lib/AdminLTE/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
  <script src="lib/AdminLTE/dist/js/adminlte.min.js"></script>
  <script src="js/pialert_common.js"></script>

  <script>
    setDefaultPageTitle();
    
    function getDevicesTotalsBadge(scansource) {
      // get totals and put in boxes
      $.get('php/server/devices.php?action=getDevicesTotals&scansource='+scansource, function(data) {
        var totalsDevicesbadge = "";
        var totalsDevicesbadge = JSON.parse(data);
        var unsetbadge = "";
        if (totalsDevicesbadge[1] > 0) {$('#header_' + scansource + '_count_on').html(totalsDevicesbadge[1].toLocaleString());} else {$('#header_' + scansource + '_count_on').html(unsetbadge.toLocaleString());}
        if (totalsDevicesbadge[3] > 0) {$('#header_' + scansource + '_count_new').html(totalsDevicesbadge[3].toLocaleString());} else {$('#header_' + scansource + '_count_new').html(unsetbadge.toLocaleString());}
        if (totalsDevicesbadge[4] > 0) {$('#header_' + scansource + '_count_down').html(totalsDevicesbadge[4].toLocaleString());} else {$('#header_' + scansource + '_count_down').html(unsetbadge.toLocaleString());}
        if (totalsDevicesbadge[0] > 0) {
          var notpresent = totalsDevicesbadge[0] - totalsDevicesbadge[6];
          $('#header_' + scansource + '_presence').html(totalsDevicesbadge[0].toLocaleString() + '/' + notpresent.toLocaleString());
        } else {
          $('#header_' + scansource + '_presence').html(unsetbadge.toLocaleString());
        }
      } );
    }
    function getICMPTotalsBadge() {
      // get totals and put in boxes
      $.get('php/server/icmpmonitor.php?action=getICMPHostTotals', function(data) {
        var totalsICMPbadge = JSON.parse(data);
        var unsetbadge = "";
        if (totalsICMPbadge[2] > 0) {$('#header_icmp_count_on').html(totalsICMPbadge[2].toLocaleString());} else {$('#header_icmp_count_on').html(unsetbadge.toLocaleString());}
        if (totalsICMPbadge[1] > 0) {$('#header_icmp_count_down').html(totalsICMPbadge[1].toLocaleString());} else {$('#header_icmp_count_down').html(unsetbadge.toLocaleString());}
      } );
    }
    function getServicesTotalsBadge() {
      // get totals and put in boxes
      $.get('php/server/services.php?action=getServiceMonTotals', function(data) {
        var totalsServicesbadge = JSON.parse(data);
        var unsetbadge = "";
        if (totalsServicesbadge[2] > 0) {$('#header_services_count_on').html(totalsServicesbadge[2].toLocaleString());} else {$('#header_services_count_on').html(unsetbadge.toLocaleString());}
        if (totalsServicesbadge[1] > 0) {$('#header_services_count_down').html(totalsServicesbadge[1].toLocaleString());} else {$('#header_services_count_down').html(unsetbadge.toLocaleString());}
        if (totalsServicesbadge[3] > 0) {$('#header_services_count_warning').html(totalsServicesbadge[3].toLocaleString());} else {$('#header_services_count_warning').html(unsetbadge.toLocaleString());}
      } );
    }
    function GetUpdateStatus() {
      // get totals and put in boxes
      $.get('php/server/files.php?action=GetUpdateStatus', function(data) {
        var UpdateCheckbadge = JSON.parse(data);
        $('#header_updatecheck_notification').html(UpdateCheckbadge[0].toLocaleString());
      } );
    }
    function getReportTotalsBadge() {
      // get totals and put in boxes
      $.get('php/server/files.php?action=getReportTotals', function(data) {
        var totalsReportbadge = JSON.parse(data);
        var unsetbadge = "";
        if (totalsReportbadge[0] > 0) {
          $('#Menu_Report_Counter_Badge').html(totalsReportbadge[0].toLocaleString());
          $('#Menu_Report_Envelope_Icon' ).addClass("text-red");
        } else {
          $('#Menu_Report_Counter_Badge').html(unsetbadge.toLocaleString());
          $('#Menu_Report_Envelope_Icon' ).removeClass("text-red");
        }
        document.title = document.title.replace(/\(\d*\)/, `(${totalsReportbadge[0].toLocaleString()})`);
      });
    }

    var pia_servertime;
    var TopServerClock;

    var countdownMinutes = 0;
    var countdownSeconds = 0;
    var serverClockRunning = false;

    // Drift protection
    var serverStartTime = 0;  // Server time in ms
    var clientStartTime = 0;  // Client time in ms

    // get Servertime
    function GetPiAlertServerTime() {

        if (serverClockRunning) return; // Timer already running
        serverClockRunning = true;

        clearTimeout(TopServerClock);

        $.get('php/server/files.php?action=GetServerTime', function(data) {
            var d = data.split(',').map(Number);
            pia_servertime = new Date(d[0], d[1]-1, d[2], d[3], d[4], d[5]);

            // Drift protection: Save reference times
            serverStartTime = pia_servertime.getTime();
            clientStartTime = Date.now();

            initCountdownFromServerTime();
            ShowPiAlertServerTime(); // Start 1-Sekund-Tick
        });
    }

    // Countdown initialisieren
    function initCountdownFromServerTime() {
        var minutes = pia_servertime.getMinutes();
        var seconds = pia_servertime.getSeconds();

        countdownMinutes = 4 - (minutes % 5);
        countdownSeconds = 60 - seconds;
        if (countdownSeconds === 60) {
            countdownSeconds = 0;
            countdownMinutes += 1;
        }
    }

    // 1-Second-Tick: Time + Countdown, driftfree
    function ShowPiAlertServerTime() {
        if (!document.getElementById || !pia_servertime) return;

        // Drift correction: actual elapsed time since server query
        var elapsed = Date.now() - clientStartTime;
        pia_servertime = new Date(serverStartTime + elapsed);

        // Time format HH:MM:SS
        var h = String(pia_servertime.getHours()).padStart(2,'0');
        var m = String(pia_servertime.getMinutes()).padStart(2,'0');
        // var s = String(pia_servertime.getSeconds()).padStart(2,'0');

        document.getElementById("PIA_Servertime_place").innerHTML =
            "- " + h + ":" + m;
            // "- " + h + ":" + m + ":" + s;

        // Countdown
        var minutes = pia_servertime.getMinutes();
        var seconds = pia_servertime.getSeconds();

        countdownMinutes = 4 - (minutes % 5);
        countdownSeconds = 60 - seconds;
        if (countdownSeconds === 60) {
            countdownSeconds = 0;
            countdownMinutes += 1;
        }

        // Update countdown element
        document.getElementById('nextscancountdown').textContent =
            'next Scan in: ' + formatTime(countdownMinutes) + ':' + formatTime(countdownSeconds);

        // Countdown reaches 0:00 → Re-sync
        if (countdownMinutes < 0) {
            serverClockRunning = false; // Timer kann neu starten
            GetPiAlertServerTime();
            return;
        }

        TopServerClock = setTimeout(ShowPiAlertServerTime, 1000);
    }

    function formatTime(time) {
        return time < 10 ? '0' + time : time;
    }

    function initializeiCheck() {
       // Blue
       $('input[type="checkbox"].blue').iCheck({
         checkboxClass: 'icheckbox_flat-blue',
         radioClass:    'iradio_flat-blue',
         increaseArea:  '20%'
       });
    }

    function updateTotals() {
      getDevicesTotalsBadge('local');
<?php create_satellite_badges(); ?>
      getICMPTotalsBadge();
      getServicesTotalsBadge();
      GetUpdateStatus();

      if (serverClockRunning && serverStartTime && clientStartTime) {
          // Tatsächlich verstrichene Zeit seit Serverabfrage
          var elapsed = Date.now() - clientStartTime;
          pia_servertime = new Date(serverStartTime + elapsed);
      }
    }

    // Init functions
    initCPUtemp();
    getReportTotalsBadge();
    GetPiAlertServerTime();
    updateTotals();

    // Start function timers
    setInterval(updateTotals, 30000);
    setInterval(getReportTotalsBadge, 15000);
  </script>

  <script>
    var timeoutId; // Declare the timeoutId variable globally

    // Function to reload the page every 60 seconds
    function reloadPage() {
      timeoutId = setTimeout(function () {
        location.reload();
      }, 120000); // 120 seconds
    }

    // Function to handle checkbox state changes
    function handleCheckboxChange() {
      var autoReloadCheckbox = document.getElementById('autoReloadCheckbox');

      if (autoReloadCheckbox.checked) {
        // Start auto-reload if checked
        reloadPage();
        // Save checkbox state to localStorage
        localStorage.setItem('autoReloadChecked', 'true');
      } else {
        // Stop auto-reload if unchecked
        clearTimeout(timeoutId);
        // Remove checkbox state from localStorage
        localStorage.removeItem('autoReloadChecked');
      }
    }

    // Attach the event listener to the checkbox
    document.getElementById('autoReloadCheckbox').addEventListener('change', handleCheckboxChange);

    // Check localStorage for the saved state
    var savedState = localStorage.getItem('autoReloadChecked');
    if (savedState === 'true') {
      document.getElementById('autoReloadCheckbox').checked = true;
      // Start auto-reload
      reloadPage();
    }

  </script>

</body>
</html>
