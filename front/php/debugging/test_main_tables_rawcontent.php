<?php
require_once __DIR__ . '/debug-layout.php';
pialert_debug_start(
    pialert_debug_label('V4_Raw_Device_Tables', 'Raw device tables'),
    'tables',
    pialert_debug_label('V4_Debug_Tables_Intro', 'Inspect the stored device and ICMP records. Select a table and search its visible rows.')
);
$tables = array('devices' => 'Devices', 'icmp' => 'ICMP_Mon');
$db = new SQLite3(__DIR__ . '/../../../db/pialert.db', SQLITE3_OPEN_READONLY);
?>
<section class="card mb-3" aria-labelledby="table-tools-title">
  <div class="card-header"><h2 class="card-title" id="table-tools-title"><?= h(pialert_debug_label('V4_Select_Table', 'Select table')); ?></h2></div>
  <div class="card-body">
    <div class="debug-toolbar">
      <div>
        <label class="form-label" for="tableSelector"><?= h(pialert_debug_label('V4_Select_Table', 'Select table')); ?></label>
        <select class="form-select" id="tableSelector">
          <option value="devices"><?= h(pialert_debug_label('V4_Devices_Table', 'Devices table')); ?></option>
          <option value="icmp"><?= h(pialert_debug_label('V4_ICMP_Table', 'ICMP table')); ?></option>
        </select>
      </div>
      <div class="debug-search">
        <label class="form-label" for="searchInput"><?= h(pialert_debug_label('V4_Table_Search', 'Search')); ?></label>
        <input class="form-control" type="search" id="searchInput" autocomplete="off" placeholder="<?= h(pialert_debug_label('V4_Debug_Search_Rows', 'Search visible table')); ?>">
      </div>
      <button class="btn btn-outline-secondary" id="resetSearch" type="button"><?= h(pialert_debug_label('V4_Reset', 'Reset')); ?></button>
    </div>
  </div>
</section>
<?php foreach ($tables as $id => $table):
    $query = @$db->query('SELECT * FROM ' . $table);
    $columns = array();
    if ($query) {
        for ($index = 0; $index < $query->numColumns(); $index++) $columns[] = $query->columnName($index);
    }
    $total = $query ? (int) $db->querySingle('SELECT COUNT(*) FROM ' . $table) : 0;
?>
<section class="card mb-3 debug-table-section" id="table_box_<?= h($id); ?>" data-table="<?= h($id); ?>"<?= $id !== 'devices' ? ' hidden' : ''; ?>>
  <div class="card-header">
    <h2 class="card-title"><?= h($table); ?></h2>
    <span class="badge text-bg-secondary ms-auto" id="summary_<?= h($id); ?>" aria-live="polite"><span class="visible-count"><?= $total; ?></span> / <?= $total; ?> <?= h(pialert_debug_label('V4_Rows', 'rows')); ?></span>
  </div>
  <div class="card-body">
    <?php if (!$query): ?>
      <div class="alert alert-danger mb-0"><?= h(pialert_debug_label('V4_Debug_Table_Error', 'The table could not be loaded.')); ?></div>
    <?php else: ?>
      <div class="debug-table-wrap" role="region" tabindex="0" aria-label="<?= h($table . ' ' . pialert_debug_label('V4_Raw_Data', 'raw data')); ?>">
        <table class="table table-striped table-hover table-sm debug-data-table" id="<?= h($id); ?>">
          <thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= h($column); ?></th><?php endforeach; ?></tr></thead>
          <tbody><?php while ($row = $query->fetchArray(SQLITE3_ASSOC)): ?><tr><?php foreach ($columns as $column): ?><td><?= h((string) ($row[$column] ?? '')); ?></td><?php endforeach; ?></tr><?php endwhile; ?></tbody>
        </table>
      </div>
      <p class="small text-body-secondary mt-2 mb-0"><?= h(pialert_debug_label('V4_Debug_Table_Hint', 'Scroll horizontally to inspect all columns.')); ?></p>
      <p class="small text-body-secondary mt-2 mb-0 debug-empty" hidden><?= h(pialert_debug_label('V4_Zero_Records', 'No matching records found')); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php endforeach; $db->close(); ?>
<script>
  const selector = document.getElementById('tableSelector');
  const search = document.getElementById('searchInput');
  const reset = document.getElementById('resetSearch');

  function updateTable() {
    const selected = selector.value;
    document.querySelectorAll('.debug-table-section').forEach(section => {
      section.hidden = section.dataset.table !== selected;
    });
    search.value = '';
    filterRows();
  }

  function filterRows() {
    const section = document.getElementById('table_box_' + selector.value);
    const rows = section.querySelectorAll('tbody tr');
    const value = search.value.trim().toLocaleLowerCase();
    let visible = 0;
    rows.forEach(row => {
      const match = !value || row.textContent.toLocaleLowerCase().includes(value);
      row.hidden = !match;
      if (match) visible++;
    });
    section.querySelector('.visible-count').textContent = String(visible);
    const empty = section.querySelector('.debug-empty');
    if (empty) empty.hidden = visible !== 0;
  }

  selector.addEventListener('change', updateTable);
  search.addEventListener('input', filterRows);
  reset.addEventListener('click', () => { search.value = ''; filterRows(); search.focus(); });
  updateTable();
</script>
<?php pialert_debug_end(); ?>
