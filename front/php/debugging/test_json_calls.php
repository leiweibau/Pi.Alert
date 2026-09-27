<?php
require_once __DIR__ . "/../server/session.php";
pialert_start_session();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}
require_once __DIR__ . '/../bootstrap.php';
pialert_v4_load_language();
?>

<!DOCTYPE html>
<html lang="<?= h(str_replace('_', '-', pathinfo(pialert_v4_language_file(), PATHINFO_FILENAME))); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pia_lang['V4_Debugging']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 0px;
            margin: 0px;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        .success {
            color: green;
            margin-right: 10px;
        }
        .error {
            color: red;
            margin-right: 10px;
        }
        .heading {
            font-size: 1.2em;
            margin: 0px;
        }
        .info_head {
        	font-size: 1.2em;
        	font-weight: bold;
        }
        .info_box {
            margin-top: 40px;
            margin-bottom: 40px;
            box-shadow: 0px 0px 15px #bbb;
            width: auto;
            margin-left: 20px;
            margin-right: 20px;
            padding: 10px;
        }
        .short {
            width: 300px;
        }
        a {
            color: dodgerblue;
            text-decoration: none;
        }
        a:hover {
            color: deepskyblue; 
        }
        .topheader {
            width: 100%; background-color: #f0f0f0; position: relative; top: 0px; padding-top: 10px; padding-bottom: 10px; margin: 0px; text-align: center;
        }
        #pialert_url {
            margin-top: 10px;
        }
        .resultheader {
            width: 100%; background-color: #f0f0f0; position: relative; top: 0px; padding-top: 10px; padding-bottom: 10px; margin: 0px; text-align: center;
        }
    </style>
</head>
<body>
    <div class="topheader">
        <h2 style="margin: 0px"><?= h($pia_lang['V4_Test_JSON_Calls']); ?></h2>
    </div>

	<div class="info_box short">
		<span class="info_head"><?= h($pia_lang['V4_PiAlert_URL']); ?></span><br>
		<div id="pialert_url"></div>
	</div>

    <div class="resultheader">
        <h2 class="heading"><?= h($pia_lang['V4_Results']); ?></h2>
    </div>

	<div class="info_box">
		<span class="info_head"><?= h($pia_lang['V4_Test_Summary']); ?>:</span>
    	<div id="summary"></div>
    </div>

    <div class="info_box">
        <div id="results"></div>
    </div>


    <script>
        const labels = <?= json_encode(array_filter($pia_lang, static fn($key) => str_starts_with((string) $key, 'V4_'), ARRAY_FILTER_USE_KEY), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
        function getBaseUrl() {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const path = window.location.pathname;

            const scriptDir = path.substring(0, path.lastIndexOf('/') + 1).replace('php/debugging/', '');

            return `${protocol}//${host}${scriptDir}`;
        }

        const baseUrl = getBaseUrl();
        const today = new Date();
        const calendarStart = new Date(today.getTime() - 7 * 86400000).toISOString().slice(0, 10);
        const calendarEnd = new Date(today.getTime() + 7 * 86400000).toISOString().slice(0, 10);

		const pialertDiv = document.getElementById("pialert_url");
		if (pialertDiv) {
		    const baseUrlLink = document.createElement("a");
		    baseUrlLink.href = baseUrl + 'maintenance.php';
		    baseUrlLink.textContent = baseUrl;
		    pialertDiv.appendChild(baseUrlLink);
		}

        // URLs zur Überprüfung
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

        let totalTests = 0;
        let passedTests = 0;
        let failedTests = 0;

        function createList(title) {
            const resultsContainer = document.getElementById("results");
            const section = document.createElement("div");

            // Headline
            const heading = document.createElement("h2");
            heading.classList.add("heading");
            heading.textContent = title;

            // List
            const list = document.createElement("ul");
            section.appendChild(heading);
            section.appendChild(list);

            resultsContainer.appendChild(section);
            return list;
        }

        function appendResult(listElement, success, message) {
            const listItem = document.createElement("li");
            const icon = document.createElement("span");
            icon.className = success ? "success" : "error";
            icon.textContent = success ? "✅" : "❌";
            listItem.append(icon, document.createTextNode(` ${message}`));
            listElement.appendChild(listItem);
        }

        // CheckURL
        async function checkJson(url, listElement) {
            totalTests++;
            try {
                // call URL
                const response = await fetch(url);

                // check HTTP status codes
                if (!response.ok) {
                    failedTests++;
                    appendResult(listElement, false, `${labels.V4_Failed}: ${url} (${labels.V4_HTTP_Code}: ${response.status})`);
                    return;
                }

                // try to parse JSON
                await response.json();
                passedTests++;
                appendResult(listElement, true, `${labels.V4_Passed}: ${url}`);
            } catch (error) {
                failedTests++;
                appendResult(listElement, false, `${labels.V4_Failed}: ${url} (${labels.V4_JSON_Error}: ${error.message})`);
            } finally {
                updateSummary();
            }
        }

        async function checkFirstEntityActions(kind, listUrl, keyIndex, listElement) {
            try {
                const listResponse = await fetch(listUrl);
                if (!listResponse.ok) throw new Error(`HTTP ${listResponse.status}`);
                const payload = await listResponse.json();
                const rows = Array.isArray(payload.data) ? payload.data : [];
                if (rows.length === 0 || !rows[0][keyIndex]) {
                    appendResult(listElement, true, `${labels.V4_Skipped}: ${kind} ${labels.V4_Actions} (${labels.V4_No_Matching_Item})`);
                    return;
                }
                const key = encodeURIComponent(String(rows[0][keyIndex]));
                await checkJson(`${baseUrl}php/server/entity_actions.php?kind=${kind}&key=${key}`, listElement);
            } catch (error) {
                totalTests++;
                failedTests++;
                appendResult(listElement, false, `${labels.V4_Failed}: ${kind} ${labels.V4_Actions} (${error.message})`);
                updateSummary();
            }
        }

        function updateSummary() {
            const summaryDiv = document.getElementById("summary");
            summaryDiv.textContent = `${passedTests} ✅ / ${failedTests} ❌`;
        }

        const deviceList = createList(labels.V4_Debug_Device_List);
        device_urls.forEach(url => checkJson(url, deviceList));

        const deviceDetailList = createList(labels.V4_Debug_Device_Details);
        device_detail_urls.forEach(url => checkJson(url, deviceDetailList));

        const eventList = createList(labels.V4_Debug_Event_List);
        event_urls.forEach(url => checkJson(url, eventList));

        const presenceList = createList(labels.V4_Debug_Presence);
        presence_urls.forEach(url => checkJson(url, presenceList));

        const icmpList = createList(labels.V4_Debug_ICMP);
        icmp_urls.forEach(url => checkJson(url, icmpList));

        const entityActionsList = createList(labels.V4_Debug_Entity_Actions);
        checkFirstEntityActions('device', `${baseUrl}php/server/devices.php?action=getDevicesList&scansource=local&status=all`, 11, entityActionsList);
        checkFirstEntityActions('icmp', `${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=all`, 1, entityActionsList);

        const serviceList = createList(labels.V4_Debug_Services);
        service_urls.forEach(url => checkJson(url, serviceList));

        const dashboardList = createList(labels.V4_Debug_Dashboard);
        dashboard_urls.forEach(url => checkJson(url, dashboardList));

        const parameterList = createList(labels.V4_Debug_Parameters);
        parameter_urls.forEach(url => checkJson(url, parameterList));

        const miscList = createList(labels.V4_Debug_Misc);
        misc_urls.forEach(url => checkJson(url, miscList));
    </script>
</body>
</html>
