<?php
session_start();

if ($_SESSION["login"] != 1) {
	header('Location: ../../index.php');
	exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debugging</title>
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
        <h2 style="margin: 0px">Test Main JSON Calls</h2>
    </div>

	<div class="info_box short">
		<span class="info_head">Pi.Alert-URL:</span><br>
		<div id="pialert_url"></div>
	</div>

    <div class="resultheader">
        <h2 class="heading">Results</h2>
    </div>

	<div class="info_box">
		<span class="info_head">Test summary:</span>
    	<div id="summary"></div>
    </div>

    <div class="info_box">
        <div id="results"></div>
    </div>


    <script>
        function getBaseUrl() {
            const protocol = window.location.protocol;
            const host = window.location.host;
            const path = window.location.pathname;

            const scriptDir = path.substring(0, path.lastIndexOf('/') + 1).replace('php/debugging/', '');

            return `${protocol}//${host}${scriptDir}`;
        }

        const baseUrl = getBaseUrl();

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

        const event_urls = [
			`${baseUrl}php/server/events.php?action=getEvents&type=all&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=sessions&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=missing&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=voided&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=new&period=7%20days`,
			`${baseUrl}php/server/events.php?action=getEvents&type=down&period=7%20days`
        ];

		const presence_urls = [
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=all`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=connected`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=favorites`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=new`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=down`,
			`${baseUrl}php/server/devices.php?action=getDevicesListCalendar&scansource=local&status=archived`
        ];

		const icmp_urls = [
			`${baseUrl}php/server/icmpmonitor.php?action=getICMPHostTotals`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=all`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=connected`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=favorites`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=down`,
			`${baseUrl}php/server/icmpmonitor.php?action=getDevicesList&status=archived`
        ];

		const misc_urls = [
			`${baseUrl}php/server/services.php?action=getServiceMonTotals`,
			`${baseUrl}lib/http-status-code/index.json`,
			`${baseUrl}php/server/files.php?action=GetLogfiles`,
			`${baseUrl}php/server/files.php?action=GetAutoBackupStatus`,
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

        // CheckURL
        async function checkJson(url, listElement) {
            totalTests++;
            try {
                // call URL
                const response = await fetch(url);

                // check HTTP status codes
                if (!response.ok) {
                    failedTests++;
                    const listItem = document.createElement("li");
                    listItem.innerHTML = `<span class="error">❌</span> Failed: ${url} (HTTP-Code: ${response.status})`;
                    listElement.appendChild(listItem);
                    return;
                }

                // try to parse JSON
                await response.json();
                passedTests++;
                const listItem = document.createElement("li");
                listItem.innerHTML = `<span class="success">✅</span> Passed: ${url}`;
                listElement.appendChild(listItem);
            } catch (error) {
                failedTests++;
                const listItem = document.createElement("li");
                listItem.innerHTML = `<span class="error">❌</span> Failed: ${url} (JSON-Error: ${error.message})`;
                listElement.appendChild(listItem);
            } finally {
                updateSummary();
            }
        }

        function updateSummary() {
            const summaryDiv = document.getElementById("summary");
            summaryDiv.textContent = `${passedTests} ✅ / ${failedTests} ❌`;
        }

        const deviceList = createList("Devicelist - JSON calls");
        device_urls.forEach(url => checkJson(url, deviceList));

        const eventList = createList("Eventlist - JSON calls");
        event_urls.forEach(url => checkJson(url, eventList));

        const presenceList = createList("Presence - JSON calls");
        presence_urls.forEach(url => checkJson(url, presenceList));

        const icmpList = createList("ICMP Monitor - JSON calls");
        icmp_urls.forEach(url => checkJson(url, icmpList));

        const miscList = createList("Miscellaneous JSON calls");
        misc_urls.forEach(url => checkJson(url, miscList));
    </script>
</body>
</html>
