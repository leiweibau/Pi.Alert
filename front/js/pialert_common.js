/* -----------------------------------------------------------------------------
*  Pi.Alert
*  Open Source Network Guard / WIFI & LAN intrusion detector 
*
*  pialert_common.js - Front module. Common Javascript functions
*-------------------------------------------------------------------------------
*  Puche 2021        pi.alert.application@gmail.com        GNU GPLv3
----------------------------------------------------------------------------- */

// -----------------------------------------------------------------------------
var timerRefreshData = ''
var modalCallbackFunction = '';


// -----------------------------------------------------------------------------
function setCookie (cookie, value, expirationHours='') {
  // Calc expiration date
  var expires = '';
  if (typeof expirationHours === 'number') {
    expires = ';expires=' + new Date(Date.now() + expirationHours *60*60*1000).toUTCString();
  }

  // Save Cookie
//  document.cookie = cookie + "=" + value + expires;
  document.cookie = cookie + "=" + value + expires + "; SameSite=Strict; path=/";
}

// -----------------------------------------------------------------------------
function getCookie (cookie) {
  // Array of cookies
  var allCookies = document.cookie.split(';');

  // For each cookie
  for (var i = 0; i < allCookies.length; i++) {
    var currentCookie = allCookies[i].trim();

    // If the current cookie is the correct cookie
    if (currentCookie.indexOf (cookie +'=') == 0) {
      // Return value
      return currentCookie.substring (cookie.length+1);
    }
  }

  // Return empty (not found)
  return "";
}


// -----------------------------------------------------------------------------
function deleteCookie (cookie) {
  document.cookie = cookie + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC';
}

// -----------------------------------------------------------------------------
function deleteAllCookies() {
  // Array of cookies
  var allCookies = document.cookie.split(";");

  // For each cookie
  for (var i = 0; i < allCookies.length; i++) {
    var cookie = allCookies[i].trim();
    var eqPos = cookie.indexOf("=");
    var name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
    document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 UTC";
    }
}



// -----------------------------------------------------------------------------
function showModalDefault (title, message, btnCancel, btnOK, callbackFunction) {
  // set captions
  $('#modal-default-title').html   (title);
  $('#modal-default-message').html (message);
  $('#modal-default-cancel').html  (btnCancel);
  $('#modal-default-OK').html      (btnOK);
  modalCallbackFunction =          callbackFunction;

  // Show modal
  $('#modal-default').modal('show');
}

// -----------------------------------------------------------------------------
function showModalWarning (title, message, btnCancel, btnOK, callbackFunction) {
  // set captions
  $('#modal-warning-title').html   (title);
  $('#modal-warning-message').html (message);
  $('#modal-warning-cancel').html  (btnCancel);
  $('#modal-warning-OK').html      (btnOK);
  modalCallbackFunction =          callbackFunction;

  // Show modal
  $('#modal-warning').modal('show');
}

// -----------------------------------------------------------------------------
function modalDefaultOK () {
  // Hide modal
  $('#modal-default').modal('hide');

  // timer to execute function
  window.setTimeout( function() {
    window[modalCallbackFunction]();
  }, 100);
}

// -----------------------------------------------------------------------------
function modalWarningOK () {
  // Hide modal
  $('#modal-warning').modal('hide');

  // timer to execute function
  window.setTimeout( function() {
    window[modalCallbackFunction]();
  }, 100);
}

// -----------------------------------------------------------------------------
function showMessage (textMessage="") {
  if (textMessage.toLowerCase().includes("error")  ) {
    // show error
    alert (textMessage);
  } else {
    // show temporal notification
    $("#alert-message").html (textMessage);
    $("#notification").fadeIn(1, function () {
      window.setTimeout( function() {
        $("#notification").fadeOut(500)
      }, 3000);
    } );
  }
}


// -----------------------------------------------------------------------------
function setParameter (parameter, value) {
  // Retry
  $.get('php/server/parameters.php?action=set&parameter=' + parameter +
    '&value='+ value,
  function(data) {
    if (data != "OK") {
      // Retry
      sleep (200);
      $.get('php/server/parameters.php?action=set&parameter=' + parameter +
        '&value='+ value,
      function(data) {
        if (data != "OK") {
         // alert (data);
        } else {
        // alert ("OK. Second attempt");
        };
      } );
    };
  } );
}


// -----------------------------------------------------------------------------
function sleep(milliseconds) {
  const date = Date.now();
  let currentDate = null;
  do {
    currentDate = Date.now();
  } while (currentDate - date < milliseconds);
}


// -----------------------------------------------------------------------------
function escapeHtmlText(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function translateHTMLcodes (text) {
  if (text == null) {
    return null;
  }
  return escapeHtmlText(text).replace(/ /g, '&nbsp;');
}


// -----------------------------------------------------------------------------
// Build dropdown entries without parsing database values as HTML or JavaScript.
function clearElement(element) {
  if (!element) {
    return;
  }
  while (element.firstChild) {
    element.removeChild(element.firstChild);
  }
}

function appendDropdownDivider(menuElement) {
  const divider = document.createElement('li');
  divider.className = 'divider';
  menuElement.appendChild(divider);
}

function appendSafeDropdownItem(menuElement, label, value, onSelect) {
  const item = document.createElement('li');
  const link = document.createElement('a');

  link.href = '#';
  link.textContent = String(label ?? '');
  link.addEventListener('click', function(event) {
    event.preventDefault();
    onSelect(String(value ?? ''));
  });

  item.appendChild(link);
  menuElement.appendChild(item);
}


// -----------------------------------------------------------------------------
function stopTimerRefreshData () {
  try {
    clearTimeout (timerRefreshData); 
  } catch (e) {}
}


// -----------------------------------------------------------------------------
function newTimerRefreshData (refeshFunction) {
  timerRefreshData = setTimeout (function() {
    refeshFunction();
  }, 60000);
}


// -----------------------------------------------------------------------------
function debugTimer () {
  $('#pageTitle').html (new Date().getSeconds());
}

// -----------------------------------------------------------------------------

function initCPUtemp() {
  function setCPUtemp(unit) {
    if (localStorage) {
      localStorage.setItem("tempunit", tempunit);
    }

    var temperature = parseFloat($("#rawtemp").text());
    var displaytemp = $("#tempdisplay");
    if (!isNaN(temperature)) {
      switch (unit) {
        case "K":
          temperature += 273.15;
          displaytemp.html(temperature.toFixed(1) + "&nbsp;K");
          break;

        case "F":
          temperature = (temperature * 9) / 5 + 32;
          displaytemp.html(temperature.toFixed(1) + "&nbsp;&deg;F");
          break;

        default:
          displaytemp.html(temperature.toFixed(1) + "&nbsp;&deg;C");
          break;
      }
    }
  }

  // Read from local storage, initialize if needed
  var tempunit = localStorage ? localStorage.getItem("tempunit") : null;
  if (tempunit === null) {
    tempunit = "C";
  }

  setCPUtemp(tempunit);

  // Add handler when on settings page
  var tempunitSelector = $("#tempunit-selector");
  if (tempunitSelector !== null) {
    tempunitSelector.val(tempunit);
    tempunitSelector.change(function () {
      tempunit = $(this).val();
      setCPUtemp(tempunit);
    });
  }
}

if (window.matchMedia("(max-width: 767px)").matches) {
   $("#sidebar_systeminfobox").addClass("collapse");
}

function setDefaultPageTitle() {
  document.title += " (0)";
}

function copymactoclipboard() {
    var copyText = document.getElementById("txtMAC");

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(copyText.value)
            .then(function() {
                alert("MAC-Adresse copied.");
            })
            .catch(function() {
                alert("Copy failed.");
            });
    } else {
        copyText.select();
        copyText.setSelectionRange(0, copyText.value.length);

        try {
            if (document.execCommand("copy")) {
                alert("MAC-Adresse copied.");
            } else {
                alert("Copy failed.");
            }
        } finally {
            copyText.blur();
        }
    }
}

function copyiptoclipboard() {
    var copyText = document.getElementById("txtLastIP");

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(copyText.value)
            .then(function() {
                alert("IP-Adresse copied.");
            })
            .catch(function() {
                alert("Copy failed.");
            });
    } else {
        copyText.select();
        copyText.setSelectionRange(0, copyText.value.length);

        try {
            if (document.execCommand("copy")) {
                alert("IP-Adresse copied.");
            } else {
                alert("Copy failed.");
            }
        } finally {
            copyText.blur();
        }
    }
}

function setCellText(td, value, suffix = "") {
  td.textContent = String(value ?? "") + suffix;
}

function setCellStrongText(td, value) {
  const strong = document.createElement("strong");
  strong.textContent = String(value ?? "");
  td.replaceChildren(strong);
}

function setCellLink(td, href, value, className = "", warningSuffix = "") {
  const link = document.createElement("a");
  link.href = href;
  link.className = className;

  const text = String(value ?? "");
  if (warningSuffix && text.endsWith(warningSuffix)) {
    link.append(document.createTextNode(text.slice(0, -warningSuffix.length)));
    const warning = document.createElement("span");
    warning.className = "text-warning";
    warning.textContent = warningSuffix;
    link.append(warning);
  } else {
    link.textContent = text;
  }

  const strong = document.createElement("strong");
  strong.append(link);
  td.replaceChildren(strong);
}

function setCellColoredText(td, value, color, emphasize = false) {
  const element = document.createElement(emphasize ? "strong" : "span");
  element.textContent = String(value ?? "");
  element.style.color = String(color ?? "");
  td.replaceChildren(element);
}


function sanitizeJournalMarkup(value) {
  const source = document.createElement("template");
  source.innerHTML = String(value ?? "");

  const output = document.createElement("div");
  const allowedSpanClasses = new Set(["text-danger"]);

  function appendSafeNode(node, parent) {
    if (node.nodeType === Node.TEXT_NODE) {
      parent.appendChild(document.createTextNode(node.nodeValue ?? ""));
      return;
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
      return;
    }

    const tagName = node.tagName.toLowerCase();
    if (tagName === "br") {
      parent.appendChild(document.createElement("br"));
      return;
    }

    if (tagName === "span") {
      const span = document.createElement("span");
      for (const className of node.classList) {
        if (allowedSpanClasses.has(className)) {
          span.classList.add(className);
        }
      }
      for (const child of node.childNodes) {
        appendSafeNode(child, span);
      }
      parent.appendChild(span);
      return;
    }

    for (const child of node.childNodes) {
      appendSafeNode(child, parent);
    }
  }

  for (const child of source.content.childNodes) {
    appendSafeNode(child, output);
  }

  return output.innerHTML;
}

function journalMarkupText(value) {
  const source = document.createElement("template");
  source.innerHTML = sanitizeJournalMarkup(value);

  let text = "";
  function appendText(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      text += node.nodeValue ?? "";
      return;
    }
    if (node.nodeType === Node.ELEMENT_NODE && node.tagName.toLowerCase() === "br") {
      text += "\n";
      return;
    }
    for (const child of node.childNodes) {
      appendText(child);
    }
  }

  for (const child of source.content.childNodes) {
    appendText(child);
  }
  return text;
}

document.addEventListener("click", function (event) {
  const source = event.target instanceof Element ? event.target : null;
  if (!source) {
    return;
  }

  const reloadLink = source.closest(".nmap-reload");
  if (reloadLink && typeof showmanualnmapscan === "function") {
    event.preventDefault();
    showmanualnmapscan(String(reloadLink.dataset.target ?? ""));
    return;
  }

  const ignoreLink = source.closest(".ignore-list-delete");
  if (ignoreLink) {
    event.preventDefault();
    const value = String(ignoreLink.dataset.value ?? "");
    if (ignoreLink.dataset.kind === "mac" && typeof askDeleteBlockDeviceMAC === "function") {
      askDeleteBlockDeviceMAC(value);
    } else if (ignoreLink.dataset.kind === "ip" && typeof askDeleteBlockDeviceIP === "function") {
      askDeleteBlockDeviceIP(value);
    }
    return;
  }

  const satelliteButton = source.closest(".satellite-action");
  if (!satelliteButton) {
    return;
  }

  const action = satelliteButton.dataset.action;
  if (action === "install" && typeof InstallSatellite === "function") {
    InstallSatellite(String(satelliteButton.dataset.token ?? ""), String(satelliteButton.dataset.password ?? ""));
  } else if (action === "save" && typeof SaveSatellite === "function") {
    SaveSatellite(String(satelliteButton.dataset.name ?? ""), String(satelliteButton.dataset.rowId ?? ""));
  } else if (action === "delete" && typeof DeleteSatellite === "function") {
    DeleteSatellite(String(satelliteButton.dataset.name ?? ""), String(satelliteButton.dataset.rowId ?? ""));
  }
});