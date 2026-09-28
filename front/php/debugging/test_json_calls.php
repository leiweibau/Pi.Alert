<?php
require_once __DIR__ . '/debug-layout.php';
pialert_debug_start(
    pialert_debug_label('V4_Test_JSON_Calls', 'Test JSON calls'),
    'json',
    pialert_debug_label('V4_Debug_JSON_Intro', 'Check the JSON endpoints used by the interface. Results are grouped by feature.')
);
?>
<section class="card mb-3" aria-labelledby="json-summary-title">
  <div class="card-header">
    <h2 class="card-title" id="json-summary-title"><?= h(pialert_debug_label('V4_Test_Summary', 'Test summary')); ?></h2>
    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="run-tests"><i class="fa-solid fa-rotate-right me-2" aria-hidden="true"></i><?= h(pialert_debug_label('V4_Debug_Run_Again', 'Run again')); ?></button>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Debug_Checked', 'Checked')); ?></span><span class="debug-stat-value" id="checked-count">0</span></div></div>
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Passed', 'Passed')); ?></span><span class="debug-stat-value text-success" id="passed-count">0</span></div></div>
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Failed', 'Failed')); ?></span><span class="debug-stat-value text-danger" id="failed-count">0</span></div></div>
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Skipped', 'Skipped')); ?></span><span class="debug-stat-value text-body-secondary" id="skipped-count">0</span></div></div>
    </div>
    <div class="progress mt-3" role="progressbar" aria-label="<?= h(pialert_debug_label('V4_Debug_Progress', 'Test progress')); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="test-progress"><div class="progress-bar" style="width: 0%"></div></div>
    <div class="d-flex flex-wrap justify-content-between gap-2 mt-2">
      <span class="small text-body-secondary" id="test-status" role="status" aria-live="polite"></span>
      <label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="show-failures"><span class="form-check-label"><?= h(pialert_debug_label('V4_Debug_Failures_Only', 'Show failures only')); ?></span></label>
    </div>
  </div>
</section>
<div class="row g-3" id="results" aria-label="<?= h(pialert_debug_label('V4_Results', 'Results')); ?>"></div>
<p class="alert alert-success mt-3" id="no-failures" hidden><?= h(pialert_debug_label('V4_Debug_No_Failures', 'No failed checks.')); ?></p>
<script>
  const labels = <?= json_encode(array_filter($pia_lang, static fn($key) => str_starts_with((string) $key, 'V4_'), ARRAY_FILTER_USE_KEY), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
  const baseUrl = new URL('../../', window.location.href).href;
  const today = new Date();
  const calendarStart = new Date(today.getTime() - 7 * 86400000).toISOString().slice(0, 10);
  const calendarEnd = new Date(today.getTime() + 7 * 86400000).toISOString().slice(0, 10);

        const device_urls = [
            `${baseUrl}php/server/devices.php?action=getDevicesTotals&scansource=local`,
            `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=all`,
            `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=connected`,
            `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=favorites`,
            `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=new`,
            `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=down`,
            `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=archived`
        ];

        const device_detail_urls = [
            `${baseUrl}php/server/devices.php?action=getNetworkNodes`,
            `${baseUrl}php/server/devices.php?action=getOwners`,
            `${baseUrl}php/server/devices.php?action=getDeviceTypes`,
            `${baseUrl}php/server/devices.php?action=getGroups`,
            `${baseUrl}php/server/devices.php?action=getLocations`,
            `${baseUrl}php/server/devices.php?action=getConnectionType`,
            `${baseUrl}php/server/devices.php?action=getLinkSpeed`,
            `${baseUrl}php/server/devices.php?action=getSpeedtestResults`,
            `${baseUrl}php/server/devices.php?action=ListInactiveHosts`
        ];

        const event_urls = [
			`${baseUrl}php/server/events.php?action=getEventsTotals&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=all&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=sessions&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=missing&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=voided&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=new&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=down&period=7%20days`
        ];

		const presence_urls = [
			`${baseUrl}php/server/events.php?action=getEventsCalendar&scansource=local&start=${calendarStart}&end=${calendarEnd}`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=all`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=connected`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=favorites`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=new`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=down`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=archived`
        ];

		const icmp_urls = [
			`${baseUrl}php/server/icmpmonitor.php?action=getICMPHostTotals`,
			`${baseUrl}php/server/icmpmonitor.php?action=getEventsTotalsforICMP&hostip=192.0.2.1`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=all`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=connected`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=favorites`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=down`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=archived`
        ];

        const service_urls = [
            `${baseUrl}php/server/services.php?action=getServicesJournal`,
            `${baseUrl}php/server/services.php?action=getEventsTotals&period=7%20days`,
            `${baseUrl}php/server/services.php?action=getEvents&type=all&period=7%20days`,
            `${baseUrl}php/server/services.php?action=getEventsTotalsforService&url=https%3A%2F%2Fexample.com%2F`
        ];

        const dashboard_urls = [
            `${baseUrl}php/server/dashboard.php?action=getLogfileDatesAsJson&logfile=pialert.1.log`,
            `${baseUrl}php/server/dashboard.php?action=getSpeedtestHistory&days=7`,
            `${baseUrl}php/server/dashboard.php?action=getLocalDeviceStatus`,
            `${baseUrl}php/server/dashboard.php?action=getIcmpDeviceStatus`,
            `${baseUrl}php/server/dashboard.php?action=getReportsCount`,
            `${baseUrl}php/server/dashboard.php?action=getLatestReports`,
            `${baseUrl}php/server/dashboard.php?action=getDeviceHistoryChart&source=main_scan`,
            `${baseUrl}php/server/dashboard.php?action=getDeviceHistoryChart&source=icmp_scan`,
            `${baseUrl}php/server/dashboard.php?action=getServiceStatusSummary`
        ];

        const parameter_urls = [
            `${baseUrl}php/server/parameters.php?action=get&parameter=Front_Devices_Rows`,
            `${baseUrl}php/server/parameters.php?action=getJournalParameter`,
            `${baseUrl}php/server/parameters.php?action=getReportParameter`
        ];

		const misc_urls = [
			`${baseUrl}php/server/services.php?action=getServiceMonTotals`,
			`${baseUrl}lib/http-status-code-1.0/index.json`,
			`${baseUrl}php/server/files.php?action=GetLogfiles`,
			`${baseUrl}php/server/files.php?action=GetAutoBackupStatus`,
			`${baseUrl}php/server/files.php?action=GetARPStatus`,
			`${baseUrl}php/server/files.php?action=GetUpdateStatus`,
			`${baseUrl}php/server/files.php?action=getReportTotals`
		];


  const groups = [
    [labels.V4_Debug_Device_List, device_urls],
    [labels.V4_Debug_Device_Details, device_detail_urls],
    [labels.V4_Debug_Event_List, event_urls],
    [labels.V4_Debug_Presence, presence_urls],
    [labels.V4_Debug_ICMP, icmp_urls],
    [labels.V4_Debug_Services, service_urls],
    [labels.V4_Debug_Dashboard, dashboard_urls],
    [labels.V4_Debug_Parameters, parameter_urls],
    [labels.V4_Debug_Misc, misc_urls]
  ];
  const totalTests = groups.reduce((sum, group) => sum + group[1].length, 2);
  let passedTests = 0;
  let failedTests = 0;
  let skippedTests = 0;
  let running = false;

  function createList(title) {
    const section = document.createElement('section');
    section.className = 'col-12 col-xl-6';
    const card = document.createElement('div');
    card.className = 'card h-100';
    const header = document.createElement('div');
    header.className = 'card-header';
    const heading = document.createElement('h3');
    heading.className = 'card-title';
    heading.textContent = title;
    const count = document.createElement('span');
    count.className = 'debug-section-count badge text-bg-secondary';
    count.textContent = '0';
    const body = document.createElement('div');
    body.className = 'card-body';
    const list = document.createElement('ul');
    list.className = 'debug-result-list';
    list.addEventListener('debug-result', () => {
      count.textContent = String(list.children.length);
      const hasFailure = list.querySelector('[data-status="failed"]') !== null;
      count.className = 'debug-section-count badge ' + (hasFailure ? 'text-bg-danger' : 'text-bg-success');
    });
    body.appendChild(list);
    header.append(heading, count);
    card.append(header, body);
    section.appendChild(card);
    document.getElementById('results').appendChild(section);
    return list;
  }

  function applyFilter() {
    const failuresOnly = document.getElementById('show-failures').checked;
    document.querySelectorAll('.debug-result-list li').forEach(item => {
      item.hidden = failuresOnly && item.dataset.status !== 'failed';
    });
    document.querySelectorAll('#results > section').forEach(section => {
      section.hidden = failuresOnly && section.querySelector('[data-status="failed"]') === null;
    });
    document.getElementById('no-failures').hidden = !failuresOnly || running || failedTests !== 0;
  }

  function appendResult(list, status, message, url) {
    const item = document.createElement('li');
    item.dataset.status = status;
    const icon = document.createElement('i');
    icon.className = 'fa-solid ' + (status === 'passed' ? 'fa-circle-check text-success' : status === 'failed' ? 'fa-circle-xmark text-danger' : 'fa-circle-minus text-body-secondary');
    icon.setAttribute('aria-hidden', 'true');
    const content = document.createElement('div');
    content.className = 'debug-result-text';
    const label = document.createElement('strong');
    label.textContent = message;
    content.appendChild(label);
    if (url) {
      const link = document.createElement('a');
      link.className = 'debug-url d-block mt-1';
      link.href = url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = url.replace(baseUrl, '');
      content.appendChild(link);
    }
    item.append(icon, content);
    list.appendChild(item);
    list.dispatchEvent(new Event('debug-result'));
    applyFilter();
  }

  function updateSummary() {
    const checked = passedTests + failedTests + skippedTests;
    document.getElementById('checked-count').textContent = String(checked);
    document.getElementById('passed-count').textContent = String(passedTests);
    document.getElementById('failed-count').textContent = String(failedTests);
    document.getElementById('skipped-count').textContent = String(skippedTests);
    const progress = Math.round(100 * checked / totalTests);
    const bar = document.getElementById('test-progress');
    bar.setAttribute('aria-valuenow', String(progress));
    bar.firstElementChild.style.width = progress + '%';
    document.getElementById('test-status').textContent = checked + ' / ' + totalTests + ' ' + (labels.V4_Debug_Checked || 'checked');
  }

  async function checkJson(url, list) {
    try {
      const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
      if (!response.ok) {
        failedTests++;
        appendResult(list, 'failed', (labels.V4_HTTP_Code || 'HTTP status') + ': ' + response.status, url);
        return;
      }
      await response.json();
      passedTests++;
      appendResult(list, 'passed', labels.V4_Passed || 'Passed', url);
    } catch (error) {
      failedTests++;
      appendResult(list, 'failed', (labels.V4_JSON_Error || 'JSON error') + ': ' + error.message, url);
    } finally {
      updateSummary();
    }
  }

  async function checkFirstEntityActions(kind, listUrl, keyIndex, list) {
    try {
      const response = await fetch(listUrl, {credentials: 'same-origin', cache: 'no-store'});
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const payload = await response.json();
      const rows = Array.isArray(payload.data) ? payload.data : [];
      if (!rows.length || !rows[0][keyIndex]) {
        skippedTests++;
        appendResult(list, 'skipped', kind + ': ' + (labels.V4_No_Matching_Item || 'no matching item'));
        updateSummary();
        return;
      }
      const key = encodeURIComponent(String(rows[0][keyIndex]));
      await checkJson(baseUrl + 'php/server/entity_actions.php?kind=' + kind + '&key=' + key, list);
    } catch (error) {
      failedTests++;
      appendResult(list, 'failed', kind + ': ' + error.message, listUrl);
      updateSummary();
    }
  }

  async function runTests() {
    if (running) return;
    running = true;
    document.getElementById('run-tests').disabled = true;
    document.getElementById('results').replaceChildren();
    passedTests = failedTests = skippedTests = 0;
    updateSummary();
    const pending = groups.flatMap(([title, urls]) => {
      const list = createList(title);
      return urls.map(url => checkJson(url, list));
    });
    const actions = createList(labels.V4_Debug_Entity_Actions);
    pending.push(checkFirstEntityActions('device', baseUrl + 'php/server/devices.php?action=getDevicesList&scansource=local&status=all', 11, actions));
    pending.push(checkFirstEntityActions('icmp', baseUrl + 'php/server/icmpmonitor.php?action=getDevicesList&status=all', 1, actions));
    await Promise.allSettled(pending);
    running = false;
    applyFilter();
    document.getElementById('run-tests').disabled = false;
  }

  document.getElementById('run-tests').addEventListener('click', runTests);
  document.getElementById('show-failures').addEventListener('change', applyFilter);
  runTests();
</script>
<?php pialert_debug_end(); ?>
