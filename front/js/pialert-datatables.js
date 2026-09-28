(function () {
  'use strict';

  // DataTables 3 adds an unsorted third click by default. Preserve the
  // established Pi.Alert two-state ordering contract on every table.
  if (window.DataTable && window.DataTable.defaults && window.DataTable.defaults.column) {
    window.DataTable.defaults.column.orderSequence = ['asc', 'desc'];
  }

  // DataTables 3 marks the hidden data column as sorted when orderData points
  // away from the visible header (for example Last IP -> numeric IP order).
  // Mirror that state onto the header the user actually clicked.
  if (window.DataTable && window.jQuery) {
    window.jQuery(document).on('draw.dt', function (_event, settings) {
      if (!settings || !Array.isArray(settings.columns)) return;
      var api = new window.DataTable.Api(settings);
      var order = api.order();
      settings.columns.forEach(function (column, index) {
        if (!column.visible || !Array.isArray(column.orderData) || column.orderData.includes(index)) return;
        var header = api.column(index).header();
        if (!header) return;
        header.classList.remove('dt-ordering-asc', 'dt-ordering-desc');
        var position = order.findIndex(function (part) { return part[0] === index; });
        if (position < 0) {
          header.removeAttribute('aria-sort');
          return;
        }
        var direction = order[position][1];
        if (direction !== 'asc' && direction !== 'desc') return;
        header.classList.add('dt-ordering-' + direction);
        if (position === 0) header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
        else header.removeAttribute('aria-sort');
      });
    });
  }
})();
