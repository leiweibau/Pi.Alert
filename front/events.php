<!-- ---------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector 
#
#  events.php - Front module. Events page
#-------------------------------------------------------------------------------
#  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
#--------------------------------------------------------------------------- -->

<?php
  require 'php/templates/header.php';
?>

<!-- Page ------------------------------------------------------------------ -->
  <div class="content-wrapper">

<!-- Main content ---------------------------------------------------------- -->
    <section class="content">

<!-- top small box --------------------------------------------------------- -->
      <div class="row">

        <div class="col-lg-2 col-sm-4 col-xs-6">
          <a href="#" onclick="javascript: getEvents('all');">
            <div class="small-box bg-aqua pa-small-box-aqua pa-small-box-2">
              <div class="inner"> <h3 id="eventsAll"> -- </h3> </div>
              <div class="icon"> <i class="fa fa-bolt text-aqua-20"></i> </div>
              <div class="small-box-footer pa-small-box-footer"> All events <i class="fa fa-arrow-circle-right"></i> </div>
            </div>
          </a>
        </div>

<!-- top small box --------------------------------------------------------- -->
        <div class="col-lg-2 col-sm-4 col-xs-6">
          <a href="#" onclick="javascript: getEvents('sessions');">
            <div class="small-box bg-green pa-small-box-green pa-small-box-2">
              <div class="inner"> <h3 id="eventsSessions"> -- </h3> </div>
              <div class="icon"> <i class="fa fa-plug text-green-20"></i> </div>
              <div class="small-box-footer pa-small-box-footer"> Sessions <i class="fa fa-arrow-circle-right"></i> </div>
            </div>
          </a>
        </div>

<!-- top small box --------------------------------------------------------- -->
        <div class="col-lg-2 col-sm-4 col-xs-6">
          <a href="#" onclick="javascript: getEvents('missing');">
            <div  class="small-box bg-yellow pa-small-box-yellow pa-small-box-2">
              <div class="inner"> <h3 id="eventsMissing"> -- </h3> </div>
              <div class="icon"> <i class="fa fa-exchange text-yellow-20"></i> </div>
              <div class="small-box-footer pa-small-box-footer"> Missing Sessions <i class="fa fa-arrow-circle-right"></i> </div>
            </div>
          </a>
        </div>

<!-- top small box --------------------------------------------------------- -->
        <div class="col-lg-2 col-sm-4 col-xs-6">
          <a href="#" onclick="javascript: getEvents('voided');">
            <div  class="small-box bg-yellow pa-small-box-yellow pa-small-box-2">
              <div class="inner"> <h3 id="eventsVoided"> -- </h3> </div>
              <div class="icon text-aqua-20"> <i class="fa fa-exclamation-circle text-yellow-20"></i> </div>
              <div class="small-box-footer pa-small-box-footer"> Voided Sessions <i class="fa fa-arrow-circle-right"></i> </div>
            </div>
          </a>
        </div>

<!-- top small box --------------------------------------------------------- -->
        <div class="col-lg-2 col-sm-4 col-xs-6">
          <a href="#" onclick="javascript: getEvents('new');">
            <div  class="small-box bg-yellow pa-small-box-yellow pa-small-box-2">
              <div class="inner"> <h3 id="eventsNewDevices"> -- </h3> </div>
              <div class="icon"> <i class="ion ion-plus-round text-yellow-20"></i> </div>
              <div class="small-box-footer pa-small-box-footer"> New Devices <i class="fa fa-arrow-circle-right"></i> </div>
            </div>
          </a>
        </div>

<!-- top small box --------------------------------------------------------- -->
        <div class="col-lg-2 col-sm-4 col-xs-6">
          <a href="#" onclick="javascript: getEvents('down');">
            <div  class="small-box bg-red pa-small-box-red pa-small-box-2">
              <div class="inner"> <h3 id="eventsDown"> -- </h3> </div>
              <div class="icon"> <i class="fa fa-warning text-red-20"></i> </div>
              <div class="small-box-footer pa-small-box-footer"> Down Alerts <i class="fa fa-arrow-circle-right"></i> </div>
            </div>
          </a>
        </div>

      </div>
      <!-- /.row -->

<!-- datatable ------------------------------------------------------------- -->
      <div class="row">
        <div class="col-xs-12">
          <div id="tableEventsBox" class="box">

            <!-- box-header -->
            <div class="box-header">
              <h3 id="tableEventsTitle" class="box-title text-gray">Events</h3>
                <span class="content" style="top: 0px;">
                  <select class="form-group" id="period" onchange="javascript: periodChanged();">
                    <option value="1 day">Today</option>
                    <option value="7 days">Last Week</option>
                    <option value="1 month" selected>Last Month</option>
                    <option value="1 year">Last Year</option>
                    <option value="100 years">All info</option>
                  </select>
                </span>														
            </div>

            <!-- table -->
            <div class="box-body table-responsive">
              <table id="tableEvents" class="table table-bordered table-hover table-striped ">
                <thead>
                <tr>
                  <th>Order</th>
                  <th>Device</th>
                  <th>Owner</th>
                  <th>Date</th>
                  <th>Event Type</th>
                  <th>Connection</th>
                  <th>Disconnection</th>
                  <th>Duration</th>
                  <th>Duration Order</th>
                  <th>IP</th>
                  <th>IP Order</th>
                  <th>Additional Info</th>
                </tr>
                </thead>
              </table>
            </div>
            <!-- /.box-body -->

          </div>
          <!-- /.box -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->

<!-- ----------------------------------------------------------------------- -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->


<!-- ----------------------------------------------------------------------- -->
<?php
  require 'php/templates/footer.php';
?>


<!-- ----------------------------------------------------------------------- -->
<!-- Datatable -->
  <link rel="stylesheet" href="lib/AdminLTE/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
  <script src="lib/AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
  <script src="lib/AdminLTE/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>


<!-- page script ----------------------------------------------------------- -->
<script>
  var parPeriod       = 'Front_Events_Period';
  var parTableRows    = 'Front_Events_Rows';

  var eventsType      = 'all';
  var period          = '';
  var tableRows       = 10;
  
  // Read parameters & Initialize components
  main();


// -----------------------------------------------------------------------------
function main () {
  // get parameter value
  $.get('php/server/parameters.php?action=get&parameter='+ parPeriod, function(data) {
    var result = JSON.parse(data);
    if (result) {
      period = result;
      $('#period').val(period);
    }

    // get parameter value
    $.get('php/server/parameters.php?action=get&parameter='+ parTableRows, function(data) {
      var result = JSON.parse(data);
      if (Number.isInteger (result) ) {
          tableRows = result;
      }

      // Initialize components
      initializeDatatable();

      // query data
      getEventsTotals();
      getEvents (eventsType);
    });
  });
}


// -----------------------------------------------------------------------------
function initializeDatatable () {
  $('#tableEvents').DataTable({
    'paging'       : true,
    'lengthChange' : true,
    'lengthMenu'   : [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
    'searching'    : true,
    'ordering'     : true,
    'info'         : true,
    'autoWidth'    : false,
    'order'       : [[0,"desc"], [3,"desc"], [5,"desc"]],

    // Parameters
    'pageLength'   : tableRows,

    'columnDefs'  : [
      {visible:   false,         targets: [0,5,6,7,8,10] },
      {className: 'text-center', targets: [] },
      {orderData: [8],           targets: 7 },
      {orderData: [10],          targets: 9 },

      // Device Name
      {targets: [1],
        "createdCell": function (td, cellData, rowData, row, col) {
          $(td).html ('<b><a href="deviceDetails.php?mac='+ rowData[13] +'" class="">'+ cellData +'</a></b>');
      } },

      // Replace HTML codes
      {targets: [3,4,5,6,7],
        "createdCell": function (td, cellData, rowData, row, col) {
          $(td).html (translateHTMLcodes (cellData));
      } }
    ],

    // Processing
    'processing'  : true,
    'language'    : {
      processing: '<table><td width="130px" align="middle">Loading...</td><td><i class="ion ion-ios-loop-strong fa-spin fa-2x fa-fw"></td></table>',
      emptyTable: 'No data'
    }
  });

  // Save Parameter rows when changed
  $('#tableEvents').on( 'length.dt', function ( e, settings, len ) {
    setParameter (parTableRows, len);
  } );
};


// -----------------------------------------------------------------------------
function periodChanged () {
  // Save Parameter Period
  period = $('#period').val();
  setParameter (parPeriod, period);

  // Requery totals and events
  getEventsTotals();
  getEvents (eventsType);
}


// -----------------------------------------------------------------------------
function getEventsTotals () {
  // stop timer
  stopTimerRefreshData();

  // get totals and put in boxes
  $.get('php/server/events.php?action=getEventsTotals&period='+ period, function(data) {
    var totalsEvents = JSON.parse(data);

    $('#eventsAll').html        (totalsEvents[0].toLocaleString());
    $('#eventsSessions').html   (totalsEvents[1].toLocaleString());
    $('#eventsMissing').html    (totalsEvents[2].toLocaleString());
    $('#eventsVoided').html     (totalsEvents[3].toLocaleString());
    $('#eventsNewDevices').html (totalsEvents[4].toLocaleString());
    $('#eventsDown').html       (totalsEvents[5].toLocaleString());

    // Timer for refresh data
    newTimerRefreshData (getEventsTotals);
  });
}


// -----------------------------------------------------------------------------
function getEvents (p_eventsType) {
  // Save status selected
  eventsType = p_eventsType;

  // Define color & title for the status selected
  switch (eventsType) {
    case 'all':       tableTitle = 'All Events';          color = 'aqua';    sesionCols = false;  break;
    case 'sessions':  tableTitle = 'Sessions';            color = 'green';   sesionCols = true;   break;
    case 'missing':   tableTitle = 'Missing Events';      color = 'yellow';  sesionCols = true;   break;
    case 'voided':    tableTitle = 'Voided Events';       color = 'yellow';  sesionCols = false;  break;
    case 'new':       tableTitle = 'New Devices Events';  color = 'yellow';  sesionCols = false;  break;
    case 'down':      tableTitle = 'Down Alerts';         color = 'red';     sesionCols = false;  break;
    default:          tableTitle = 'Events';              boxClass = '';     sesionCols = false;  break;
  } 

  // Set title and color
  $('#tableEventsTitle')[0].className = 'box-title text-' + color;
  $('#tableEventsBox')[0].className = 'box box-' + color;
  $('#tableEventsTitle').html (tableTitle);

  // Coluumns Visibility
  $('#tableEvents').DataTable().column(3).visible (!sesionCols);
  $('#tableEvents').DataTable().column(4).visible (!sesionCols);
  $('#tableEvents').DataTable().column(5).visible (sesionCols);
  $('#tableEvents').DataTable().column(6).visible (sesionCols);
  $('#tableEvents').DataTable().column(7).visible (sesionCols);

  // Define new datasource URL and reload
  $('#tableEvents').DataTable().clear();
  $('#tableEvents').DataTable().draw();
  $('#tableEvents').DataTable().order ([0,"desc"], [3,"desc"], [5,"desc"]);
  $('#tableEvents').DataTable().ajax.url('php/server/events.php?action=getEvents&type=' + eventsType +'&period='+ period ).load();
};

</script>
