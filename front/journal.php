<!-- ---------------------------------------------------------------------------
#  Pi.Alert
#  Open Source Network Guard / WIFI & LAN intrusion detector
#
#  journal.php - Front module. Journal page
#-------------------------------------------------------------------------------
#  leiweibau 2024                                          GNU GPLv3
#--------------------------------------------------------------------------- -->

<?php
session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ./index.php');
	exit;
}
require 'php/templates/header.php';
require 'php/server/journal.php';

# Init DB Connection
$db_file = '../db/pialert.db';
$db = new SQLite3($db_file);
$db->exec('PRAGMA journal_mode = wal;');

?>

<!-- Page ------------------------------------------------------------------ -->
  <div class="content-wrapper">

<!-- Content header--------------------------------------------------------- -->
    <section class="content-header">
        <?php require 'php/templates/notification.php';?>
      <h1 id="pageTitle">
         <?=$pia_journ_lang['Title']?>
      </h1>
    </section>

<!-- Main content ---------------------------------------------------------- -->
    <section class="content">

        <div class="modal fade" id="modal-set-journal-colors">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                        <h4 class="modal-title"><?=$pia_journ_lang['Journal_CustomColor_Head'];?></h4>
                    </div>
                    <div class="modal-body">

                        <style>
                            .filter-group-trigger {
                                display: flex;
                                align-items: center;
                                margin-bottom: 10px;
                            }
                            .filter-group-trigger label, .filter-group-trigger input {
                                margin-right: 10px;
                            }
                            .remove-btn {
                                border: none;
                                padding: 5px 10px;
                                cursor: pointer;
                            }
                            .add-btn {
                                border: none;
                                padding: 5px 10px;
                                cursor: pointer;
                            }
                        </style>

                        <link rel="stylesheet" href="lib/Coloris/dist/coloris.min.css"/>
                        <script src="lib/Coloris/dist/coloris.min.js"></script>

                        <h4><?=$pia_lang['Gen_column'];?>: <?=$pia_journ_lang['Journal_TableHead_Class'];?></h4>
                        <div id="methodContainer">

                        </div>
                        <button type="button" id="addMethod" class="btn btn-success" style="margin-top:5px; margin-right:10px;">+</button>
                        <input type="submit" class="btn btn-danger" value="<?=$pia_lang['Gen_Save']?> (<?=$pia_journ_lang['Journal_TableHead_Class'];?>)" style="margin-top:5px; margin-right:10px;" onclick="SetMethodColors()">

                        <h4><?=$pia_lang['Gen_column'];?>: <?=$pia_journ_lang['Journal_TableHead_Trigger'];?></h4>
                        <div id="triggerContainer">

                        </div>
                        <button type="button" id="addTrigger" class="btn btn-success" style="margin-top:5px; margin-right:10px;">+</button>
                        <input type="submit" class="btn btn-danger" value="<?=$pia_lang['Gen_Save']?> (<?=$pia_journ_lang['Journal_TableHead_Trigger'];?>)" style="margin-top:5px; margin-right:10px;" onclick="SetTriggerColors()">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" onclick="JournalReload()"><?=$pia_lang['Gen_Close']?></button>
                    </div>
                </div>
            </div>
        </div>

<!-- datatable ------------------------------------------------------------- -->
      <div class="row">
        <div class="col-xs-12">
          <div id="tableJournalBox" class="box">

            <div class="box-header">
              <h3 id="tableJournalTitle" class="box-title text-aqua">Journal</h3>
              <a href="#" class="btn btn-xs btn-link" role="button" data-toggle="modal" data-target="#modal-set-journal-colors" style="display: inline-block; margin-top: -5px; margin-left: 15px;"><i class="fa-solid fa-paintbrush text-green" style="font-size:1.5rem"></i></a>
              <a href="#" onclick="clearInput();"><span id="reset_joursearch" class="text-red pull-right"><i class="fa-solid fa-filter-circle-xmark"></i></span></a>
            </div>

<script>
function clearInput() {
   var table = $('#tableJournal').DataTable(); 
   table.search(''); //clear search item 
   table.draw(); //redraw table
}
</script>

            <div class="box-body table-responsive">
              <table id="tableJournal" class="table table-bordered table-hover table-striped ">
                <thead>
                <tr>
                  <th style="min-width: 120px;"><?=$pia_lang['Events_TableHead_Date'];?></th>
                  <th>LogClass</th>
                  <th style="min-width: 80px;">LogCode</th>
                  <th style="min-width: 90px;"><?=$pia_journ_lang['Journal_TableHead_Class'];?></th>
                  <th style="min-width: 100px;"><?=$pia_journ_lang['Journal_TableHead_Trigger'];?></th>
                  <th>Hash</th>
                  <th style="min-width: 500px;"><?=$pia_lang['Events_TableHead_AdditionalInfo'];?></th>
                </tr>
                </thead>
                  <tbody>
<?php
get_pialert_journal();
?>
                  </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
<!-- ----------------------------------------------------------------------- -->
    </section>

  </div>
<!-- ----------------------------------------------------------------------- -->
<?php
require 'php/templates/footer.php';

function get_pialert_journal() {
	global $db;
	global $pia_journ_lang;

	$pia_journal = $db->query('SELECT * FROM pialert_journal ORDER BY Journal_DateTime DESC Limit 500');
	while ($row = $pia_journal->fetchArray()) {
		if ($row['LogClass'] == "a_000") {$full_additional_info = $pia_journ_lang[$row['LogString']] . '<br>' . $pia_journ_lang['File_hash'] . ': <span class="text-danger">' . $row['Hash'] . '</span>';} else { $full_additional_info = $pia_journ_lang[$row['LogString']];}
		$full_additional_info = $full_additional_info . '<br>' . $row['Additional_Info'];

		$logcode = "";
		$logclass = "";

		echo '<tr>
              <td>' . $row['Journal_DateTime'] . '</td>
              <td>' . $logclass . '</td>
              <td>' . $logcode . '</td>
              <td style="white-space: nowrap;">' . $pia_journ_lang[$row['LogClass']] . '</td>
              <td>' . $row['Trigger'] . '</td>
              <td>' . $row['Hash'] . '</td>
              <td>' . $full_additional_info . '</td>
          </tr>';
	}
}
?>
<script src="lib/AdminLTE/bower_components/jquery/dist/jquery.min.js"></script>
<link rel="stylesheet" href="lib/AdminLTE/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css">
<script src="lib/AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="lib/AdminLTE/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>


<!-- page script ----------------------------------------------------------- -->
<script>
    var devicesList         = [];
    var pos                 = -1;
    var parPeriod           = 'Front_Journal_Period';
    var parEventsRows       = 'Front_Journal_Rows';
    var period              = '1 month';

    // Custom colors
    let date_time_colors = ["#3468ff", "#ff644d"];
    let journalTriggerFilter = [];
    let journalTriggerFilterColor = [];
    let journalMethodFilter = [];
    let journalMethodFilterColor = [];

    let triggerIndex = 0;
    let methodIndex = 0;

    $('#addTrigger').click(function () {
        triggerIndex++;
        $('#triggerContainer').append(
            `<div id="trigger_${triggerIndex}" style="margin-bottom: 5px">
                <input type="text" name="triggerNames[]" class="journal_custom_colors_input" placeholder="Trigger Name ${triggerIndex}">
                <input type="text" name="triggerColors[]" class="journal_custom_colors_input" placeholder="Trigger Color ${triggerIndex}" data-coloris>
                <button type="button" onclick="removeField('#trigger_${triggerIndex}')" class="btn btn-danger">-</button>
            </div>`
        );
    });

    $('#addMethod').click(function () {
        methodIndex++;
        $('#methodContainer').append(
            `<div id="method_${methodIndex}" style="margin-bottom: 5px">
                <input type="text" name="methodNames[]" class="journal_custom_colors_input" placeholder="Method Name ${methodIndex}">
                <input type="text" name="methodColors[]" class="journal_custom_colors_input" placeholder="Method Color ${methodIndex}" data-coloris>
                <button type="button" onclick="removeField('#method_${methodIndex}')" class="btn btn-danger">-</button>
            </div>`
        );
    });

    window.removeField = function (selector) {
        $(selector).remove();
    };

    initializeCustomColors();
    Coloris({
        themeMode: 'dark',
        alpha: false,
        closeButton: true,
        closeLabel: '<?=$pia_lang['Gen_Close']?>',
    });

function JournalReload() {
    setTimeout(function() {
        location.reload();
    }, 1000)
};

function initializeCustomColors() {
    $.get('php/server/parameters.php?action=getJournalParameter', function(data) {
        var customColors = JSON.parse(data);
        if (customColors && typeof customColors === 'object') {
            journalTriggerFilter = customColors.journal_trigger_filter ? customColors.journal_trigger_filter.split(',').map(item => item.trim()) : [];
            journalTriggerFilterColor = customColors.journal_trigger_filter_color ? customColors.journal_trigger_filter_color.split(',').map(item => item.trim()) : [];
            journalMethodFilter = customColors.journal_method_filter ? customColors.journal_method_filter.split(',').map(item => item.trim()) : [];
            journalMethodFilterColor = customColors.journal_method_filter_color ? customColors.journal_method_filter_color.split(',').map(item => item.trim()) : [];
        }
        addTriggerRows();
        addMethodRows();
        initializeDatatable();
  });
}

function addTriggerRows() {
    for (var i = 0; i < journalTriggerFilter.length; i++) {
        addTriggerRow(journalTriggerFilter[i], journalTriggerFilterColor[i]);
    }
}
function addTriggerRow(Name, Color) {
    triggerIndex++;
    var newFields = `
        <div id="trigger_${triggerIndex}" style="margin-bottom: 5px">
                <input type="text" name="triggerNames[]" class="journal_custom_colors_input" placeholder="Trigger Name ${triggerIndex}" value="${Name}">
                <input type="text" name="triggerColors[]" class="journal_custom_colors_input" placeholder="Trigger Color ${triggerIndex}" value="${Color}" data-coloris>
                <button type="button" onclick="removeField('#trigger_${triggerIndex}')" class="btn btn-danger">-</button>
        </div>
    `;
    $('#triggerContainer').append(newFields);
}

function addMethodRows() {
    for (var i = 0; i < journalMethodFilter.length; i++) {
        addMethodRow(journalMethodFilter[i], journalMethodFilterColor[i]);
    }
}
function addMethodRow(Name, Color) {
    methodIndex++;
    var newFields = `
        <div id="method_${methodIndex}" style="margin-bottom: 5px">
                <input type="text" name="methodNames[]" class="journal_custom_colors_input" placeholder="Method Name ${methodIndex}" value="${Name}">
                <input type="text" name="methodColors[]" class="journal_custom_colors_input" placeholder="Method Color ${methodIndex}" value="${Color}" data-coloris>
                <button type="button" onclick="removeField('#method_${methodIndex}')" class="btn btn-danger">-</button>
        </div>
    `;
    $('#methodContainer').append(newFields);
}

function initializeDatatable () {
  $('#tableJournal').DataTable({
    'paging'       : true,
    'lengthChange' : true,
    'lengthMenu'   : [[10, 25, 50, 100, 500, -1], [10, 25, 50, 100, 500, 'All']],
    //'bLengthChange': false,
    'searching'    : true,
    'ordering'     : true,
    'info'         : true,
    'autoWidth'    : false,
    'pageLength'   : 25,
    'order'        : [[0, 'desc']],
    'columns': [
        { "data": 0 },
        { "data": 1 },
        { "data": 2 },
        { "data": 3 },
        { "data": 4 },
        { "data": 5 },
        { "data": 6 }
      ],

    'columnDefs'  : [
      {className: 'text-center', targets: [1,2] },
      { "width": "120px", "targets": [0] },
      { "width": "90px", "targets": [2] },

      {targets: [0],
        "createdCell": function (td, cellData, rowData, row, col) {
            custom_journal_color_datetime(cellData, td);
        }
      },
      {targets: [3],
        "createdCell": function (td, cellData, rowData, row, col) {
            color_scheme = custom_journal_color_method(cellData);
            $(td).html('<span style="' + color_scheme + '">' + cellData + '</span>');
        }
      },
      {targets: [4],
        "createdCell": function (td, cellData, rowData, row, col) {
            color_scheme = custom_journal_color_trigger(cellData);
            $(td).html('<span style="' + color_scheme + '">' + cellData + '</span>');
        }
      },
      {targets: [1,2,5],
          visible: false
      },
    ],

    // Processing
    'processing'  : true,
    'language'    : {
      processing: '<table><td width="130px" align="middle">Loading...</td><td><i class="ion ion-ios-sync fa-spin fa-2x fa-fw"></td></table>',
      emptyTable: 'No data',
      "lengthMenu": "<?=$pia_lang['Events_Tablelenght'];?>",
      "search":     "<?=$pia_lang['Events_Searchbox'];?>: ",
      "paginate": {
          "next":       "<?=$pia_lang['Events_Table_nav_next'];?>",
          "previous":   "<?=$pia_lang['Events_Table_nav_prev'];?>"
      },
      "info":           "<?=$pia_lang['Events_Table_info'];?>",
    },
  });
};

function custom_journal_color_trigger(trigger) {
    for (let i = 0; i < journalTriggerFilter.length; i++) {
        if (journalTriggerFilter[i] === trigger) {
            return 'color:' + journalTriggerFilterColor[i] + ';';
        }
    }
    return " ";
}

function custom_journal_color_datetime(cellData, td) {
    var createdAtValue = new Date(cellData);
    var currentTime = new Date();
    var oneHourAgo = new Date(currentTime.getTime() - (60 * 60 * 1000));
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    if (createdAtValue.getTime() >= today.getTime() && oneHourAgo > createdAtValue) {
        $(td).html('<b style="color:' + date_time_colors[0] + ';">' + cellData.replace(/ /g, '&nbsp;&nbsp;&nbsp;&nbsp;') + '</b>');
    } else if (createdAtValue >= oneHourAgo) {
        $(td).html('<b style="color:' + date_time_colors[1] + ';">' + cellData.replace(/ /g, '&nbsp;&nbsp;&nbsp;&nbsp;') + '</b>');
    } else {
        $(td).html('<b style="">' + cellData.replace(/ /g, '&nbsp;&nbsp;&nbsp;&nbsp;') + '</b>');
    }
}

function custom_journal_color_method(method) {
    for (let i = 0; i < journalMethodFilter.length; i++) {
        if (journalMethodFilter[i] === method) {
            return 'color:' + journalMethodFilterColor[i] + ';';
        }
    }
    return " ";
}

function SetTriggerColors() {
    let triggerNames = $('input[name="triggerNames[]"]').map(function () { return $(this).val(); }).get();
    let triggerColors = $('input[name="triggerColors[]"]').map(function () { return $(this).val(); }).get();

    $.post('php/server/parameters.php', {
        action: 'setJournalParameter',
        column: 'trigger',
        triggerNames: triggerNames,
        triggerColors: triggerColors
    }, function(msg) {
    showMessage (msg);
  });
}

function SetMethodColors() {
    let methodNames = $('input[name="methodNames[]"]').map(function () { return $(this).val(); }).get();
    let methodColors = $('input[name="methodColors[]"]').map(function () { return $(this).val(); }).get();

    $.post('php/server/parameters.php', {
        action: 'setJournalParameter',
        column: 'method',
        methodNames: methodNames,
        methodColors: methodColors
    }, function(msg) {
    showMessage (msg);
  });
}

</script>
