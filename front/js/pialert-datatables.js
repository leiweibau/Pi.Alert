(function () {
  'use strict';

  // DataTables 2 adds an unsorted third click by default. Preserve the
  // established Pi.Alert two-state ordering contract on every table.
  if (window.DataTable && window.DataTable.defaults && window.DataTable.defaults.column) {
    window.DataTable.defaults.column.orderSequence = ['asc', 'desc'];
  }
})();
