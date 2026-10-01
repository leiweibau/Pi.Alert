/* -----------------------------------------------------------------------------
 * Pi.Alert - AdminLTE 4 common browser compatibility layer
 *
 * Keeps the mutation and dialog contracts used by the existing pages while
 * using Bootstrap 5's native component API. jQuery is intentionally retained
 * for the existing AJAX/Deferred contract.
 * -------------------------------------------------------------------------- */
(function (window, document) {
  'use strict';

  var pendingPosts = Object.create(null);
  var modalCallbackFunction = '';
  var toastTimer = null;

  function getJQuery () {
    if (!window.jQuery || typeof window.jQuery.ajax !== 'function') {
      throw new Error('Pi.Alert v4 common requests require jQuery');
    }
    return window.jQuery;
  }

  function getCsrfToken () {
    var tokenElement = document.querySelector('meta[name="csrf-token"]');
    return tokenElement ? tokenElement.getAttribute('content') || '' : '';
  }

  var v4LabelsNode = document.getElementById('pialert-v4-labels');
  var v4Labels = v4LabelsNode ? JSON.parse(v4LabelsNode.textContent || '{}') : {};
  window.pialertV4Text = function (key) { return String(v4Labels[key] || ''); };
  window.pialertV4DataTableLanguage = function (overrides) {
    var base = {
      emptyTable: window.pialertV4Text('V4_No_Data'),
      zeroRecords: window.pialertV4Text('V4_Zero_Records'),
      processing: window.pialertV4Text('V4_Loading'),
      loadingRecords: window.pialertV4Text('V4_Loading'),
      info: window.pialertV4Text('V4_Table_Info'),
      infoEmpty: window.pialertV4Text('V4_Table_Info_Empty'),
      infoFiltered: window.pialertV4Text('V4_Table_Info_Filtered'),
      lengthMenu: window.pialertV4Text('V4_Table_Length_Menu'),
      search: window.pialertV4Text('V4_Table_Search'),
      paginate: { first: window.pialertV4Text('V4_First'), last: window.pialertV4Text('V4_Last'), next: window.pialertV4Text('V4_Next'), previous: window.pialertV4Text('V4_Previous') },
      aria: { sortAscending: window.pialertV4Text('V4_Sort_Ascending'), sortDescending: window.pialertV4Text('V4_Sort_Descending') }
    };
    var custom = overrides || {};
    return Object.assign({}, base, custom, {
      paginate: Object.assign({}, base.paginate, custom.paginate || {}),
      aria: Object.assign({}, base.aria, custom.aria || {})
    });
  };

  function isStateChangingMethod (method) {
    return ['GET', 'HEAD', 'OPTIONS'].indexOf(String(method || 'GET').toUpperCase()) === -1;
  }

  function installAjaxHooks () {
    var $ = getJQuery();
    if (window.__pialertV4AjaxHooksInstalled) {
      return;
    }
    window.__pialertV4AjaxHooksInstalled = true;

    $(document).ajaxError(function (_event, jqXHR) {
      if (jqXHR.status === 403 && jqXHR.getResponseHeader('X-PiAlert-CSRF') === 'invalid') {
        window.alert(window.pialertV4Text('V4_CSRF_Expired'));
        window.location.reload();
      }
    });

    $.ajaxPrefilter(function (options, _originalOptions, jqXHR) {
      var method = options.type || options.method || 'GET';
      var target = new URL(options.url || window.location.href, window.location.href);
      if (isStateChangingMethod(method) && target.origin === window.location.origin) {
        jqXHR.setRequestHeader('X-CSRF-Token', getCsrfToken());
      }
    });
  }

  function clonePayload (data) {
    if (typeof data === 'string') {
      return new URLSearchParams(data);
    }
    if (data instanceof URLSearchParams) {
      return new URLSearchParams(data.toString());
    }
    if (Array.isArray(data)) {
      return data.map(function (entry) {
        return { name: entry.name, value: entry.value };
      });
    }
    return Object.assign({}, data || {});
  }

  function hasPayloadField (payload, name) {
    if (payload instanceof URLSearchParams) {
      return payload.has(name);
    }
    if (Array.isArray(payload)) {
      return payload.some(function (entry) { return entry.name === name; });
    }
    return Object.prototype.hasOwnProperty.call(payload, name);
  }

  function setPayloadField (payload, name, value, overwrite) {
    if (!overwrite && hasPayloadField(payload, name)) {
      return;
    }
    if (payload instanceof URLSearchParams) {
      payload.set(name, value);
    } else if (Array.isArray(payload)) {
      if (overwrite) {
        for (var index = payload.length - 1; index >= 0; index -= 1) {
          if (payload[index].name === name) {
            payload.splice(index, 1);
          }
        }
      }
      payload.push({ name: name, value: value });
    } else {
      payload[name] = value;
    }
  }

  function serializablePayload (payload) {
    return payload instanceof URLSearchParams ? payload.toString() : payload;
  }

  function payloadKey ($, payload) {
    return payload instanceof URLSearchParams ? payload.toString() : $.param(payload);
  }

  function operationId () {
    var bytes = new Uint8Array(16);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      window.crypto.getRandomValues(bytes);
    } else {
      for (var index = 0; index < bytes.length; index += 1) {
        bytes[index] = Math.floor(Math.random() * 256);
      }
    }
    return Array.from(bytes, function (value) {
      return value.toString(16).padStart(2, '0');
    }).join('');
  }

  function pialertPost (url, data, success) {
    var $ = getJQuery();
    if (typeof data === 'function') {
      success = data;
      data = {};
    }

    var target = new URL(url, window.location.href);
    if (target.origin !== window.location.origin) {
      throw new Error('Cross-origin mutation is not permitted');
    }

    var payload = clonePayload(data);
    var requestKey = target.pathname + '?' + target.searchParams.toString() + '|' + payloadKey($, payload);
    if (pendingPosts[requestKey]) {
      return pendingPosts[requestKey];
    }

    setPayloadField(payload, '_operation_id', operationId(), true);
    target.searchParams.forEach(function (value, key) {
      setPayloadField(payload, key, value, false);
    });

    var request = $.ajax({
      url: target.pathname,
      type: 'POST',
      data: serializablePayload(payload),
      success: success
    });
    pendingPosts[requestKey] = request;
    request.always(function () {
      delete pendingPosts[requestKey];
    });
    return request;
  }

  function legacyCopyText (text) {
    var previousFocus = document.activeElement;
    var input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('aria-hidden', 'true');
    input.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
    document.body.appendChild(input);
    var copied = false;
    try {
      input.focus({ preventScroll: true });
      input.select();
      input.setSelectionRange(0, text.length);
      copied = document.execCommand('copy');
    } catch (_error) {
      copied = false;
    } finally {
      input.remove();
      if (previousFocus && document.contains(previousFocus)) {
        try { previousFocus.focus({ preventScroll: true }); } catch (_error) { /* Focus restoration is best effort. */ }
      }
    }
    return copied;
  }

  function copyText (text) {
    if (typeof text !== 'string' || text === '') return Promise.resolve(false);
    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
      return Promise.resolve(legacyCopyText(text));
    }
    try {
      return Promise.resolve(navigator.clipboard.writeText(text)).then(function () { return true; }, function () { return legacyCopyText(text); });
    } catch (_error) {
      return Promise.resolve(legacyCopyText(text));
    }
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('button[data-copy-target]');
    if (!button) return;
    var source = document.getElementById(button.dataset.copyTarget);
    copyText(source ? source.value : '').then(function (copied) {
      window.showMessage(window.pialertV4Text(copied ? 'V4_Copy_Success' : 'V4_Copy_Failed'));
    });
  });

  function appendText (parent, value) {
    parent.appendChild(document.createTextNode(String(value == null ? '' : value)));
  }

  function appendSafeRichNode (source, destination) {
    if (source.nodeType === 3) {
      destination.appendChild(document.createTextNode(source.nodeValue || ''));
      return;
    }
    if (source.nodeType !== 1) {
      return;
    }

    var tag = source.tagName.toLowerCase();
    var allowedTags = ['br', 'span', 'strong', 'b', 'em', 'i', 'code'];
    if (allowedTags.indexOf(tag) === -1) {
      Array.prototype.forEach.call(source.childNodes, function (child) {
        appendSafeRichNode(child, destination);
      });
      return;
    }

    var element = document.createElement(tag);
    if (tag === 'span') {
      var allowedClasses = ['text-danger', 'text-warning', 'text-success', 'text-primary', 'text-secondary', 'text-red'];
      Array.prototype.forEach.call(source.classList, function (className) {
        if (allowedClasses.indexOf(className) !== -1) {
          element.classList.add(className === 'text-red' ? 'text-danger' : className);
        }
      });
    }
    Array.prototype.forEach.call(source.childNodes, function (child) {
      appendSafeRichNode(child, element);
    });
    destination.appendChild(element);
  }

  function setSafeRichContent (element, value) {
    var template = document.createElement('template');
    template.innerHTML = String(value == null ? '' : value);
    element.replaceChildren();
    Array.prototype.forEach.call(template.content.childNodes, function (child) {
      appendSafeRichNode(child, element);
    });
  }

  function makeButton (id, classes, dismiss) {
    var button = document.createElement('button');
    button.id = id;
    button.type = 'button';
    button.className = classes;
    if (dismiss) {
      button.setAttribute('data-bs-dismiss', 'modal');
    }
    return button;
  }

  function ensureModal (kind) {
    var id = 'modal-' + kind;
    var existing = document.getElementById(id);
    if (existing) {
      Array.prototype.forEach.call(existing.querySelectorAll('[data-dismiss="modal"]'), function (control) {
        control.removeAttribute('data-dismiss');
        control.setAttribute('data-bs-dismiss', 'modal');
      });
      return existing;
    }

    var modal = document.createElement('div');
    modal.id = id;
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.setAttribute('aria-labelledby', id + '-title');
    modal.setAttribute('aria-hidden', 'true');

    var dialog = document.createElement('div');
    dialog.className = 'modal-dialog';
    var content = document.createElement('div');
    content.className = 'modal-content';
    var header = document.createElement('div');
    header.className = kind === 'warning' ? 'modal-header text-bg-warning' : 'modal-header';
    var title = document.createElement('h2');
    title.id = id + '-title';
    title.className = 'modal-title fs-5';
    var close = makeButton('', 'btn-close', true);
    close.setAttribute('aria-label', window.pialertV4Text('V4_Close'));
    var body = document.createElement('div');
    body.id = id + '-message';
    body.className = 'modal-body';
    var footer = document.createElement('div');
    footer.className = 'modal-footer';
    var cancel = makeButton(id + '-cancel', 'btn btn-secondary me-auto', true);
    var ok = makeButton(id + '-OK', kind === 'warning' ? 'btn btn-danger' : 'btn btn-primary', false);

    ok.addEventListener('click', kind === 'warning' ? modalWarningOK : modalDefaultOK);
    header.appendChild(title);
    header.appendChild(close);
    footer.appendChild(cancel);
    footer.appendChild(ok);
    content.appendChild(header);
    content.appendChild(body);
    content.appendChild(footer);
    dialog.appendChild(content);
    modal.appendChild(dialog);
    document.body.appendChild(modal);
    return modal;
  }

  function bootstrapModal (element) {
    if (!window.bootstrap || !window.bootstrap.Modal) {
      throw new Error('Pi.Alert v4 dialogs require Bootstrap 5');
    }
    return window.bootstrap.Modal.getOrCreateInstance(element);
  }

  function showModal (kind, title, message, btnCancel, btnOK, callbackFunction) {
    var modal = ensureModal(kind);
    setSafeRichContent(document.getElementById('modal-' + kind + '-title'), title);
    setSafeRichContent(document.getElementById('modal-' + kind + '-message'), message);
    var cancel = document.getElementById('modal-' + kind + '-cancel');
    var ok = document.getElementById('modal-' + kind + '-OK');
    cancel.replaceChildren();
    ok.replaceChildren();
    appendText(cancel, btnCancel);
    appendText(ok, btnOK);
    cancel.hidden = false;
    modalCallbackFunction = callbackFunction;
    window.modalCallbackFunction = callbackFunction;
    bootstrapModal(modal).show();
    return modal;
  }

  function showModalDefault (title, message, btnCancel, btnOK, callbackFunction) {
    return showModal('default', title, message, btnCancel, btnOK, callbackFunction);
  }

  function showModalWarning (title, message, btnCancel, btnOK, callbackFunction) {
    return showModal('warning', title, message, btnCancel, btnOK, callbackFunction);
  }

  function invokeModalCallback () {
    var callback = modalCallbackFunction;
    modalCallbackFunction = '';
    window.modalCallbackFunction = '';
    window.setTimeout(function () {
      if (typeof callback === 'function') {
        callback();
      } else if (callback && typeof window[callback] === 'function') {
        window[callback]();
      }
    }, 100);
  }

  function modalDefaultOK () {
    bootstrapModal(ensureModal('default')).hide();
    invokeModalCallback();
  }

  function modalWarningOK () {
    bootstrapModal(ensureModal('warning')).hide();
    invokeModalCallback();
  }

  function showOkModal (kind, title, message, btnOK, callbackFunction) {
    var modal = showModal(kind, title, message, '', btnOK, callbackFunction);
    document.getElementById('modal-' + kind + '-cancel').hidden = true;
    return modal;
  }

  function showModalOK (title, message, btnOK, callbackFunction) {
    return showOkModal('default', title, message, btnOK, callbackFunction);
  }

  function showModalWarningOK (title, message, btnOK, callbackFunction) {
    return showOkModal('warning', title, message, btnOK, callbackFunction);
  }

  function ensureToast () {
    var existing = document.getElementById('notification');
    if (existing) {
      existing.classList.add('toast', 'text-bg-success', 'border-0');
      existing.setAttribute('role', 'status');
      Array.prototype.forEach.call(existing.querySelectorAll('[data-dismiss]'), function (control) {
        control.removeAttribute('data-dismiss');
        control.setAttribute('data-bs-dismiss', 'toast');
      });
      return existing;
    }
    var container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    container.style.zIndex = '1090';

    var toast = document.createElement('div');
    toast.id = 'notification';
    toast.className = 'toast text-bg-success border-0';
    toast.setAttribute('role', 'status');
    var row = document.createElement('div');
    row.className = 'd-flex';
    var body = document.createElement('div');
    body.id = 'alert-message';
    body.className = 'toast-body';
    var close = makeButton('', 'btn-close btn-close-white me-2 m-auto', false);
    close.setAttribute('data-bs-dismiss', 'toast');
    close.setAttribute('aria-label', window.pialertV4Text('V4_Close'));
    row.appendChild(body);
    row.appendChild(close);
    toast.appendChild(row);
    container.appendChild(toast);
    document.body.appendChild(container);
    return toast;
  }

  function showMessage (textMessage) {
    // Legacy write endpoints append a meta refresh for full-page responses.
    // A v4 toast is text-only and must neither display nor execute that tag.
    var text = String(textMessage == null ? '' : textMessage)
      .replace(/<meta\b(?=[^>]*\bhttp-equiv\s*=\s*(['"]?)refresh\1(?:\s|\/?>))[^>]*>/gi, '')
      .trim();
    if (text.toLowerCase().indexOf('error') !== -1) {
      window.alert(text);
      return null;
    }

    var toast = ensureToast();
    var message = document.getElementById('alert-message');
    message.replaceChildren();
    appendText(message, text);
    if (window.bootstrap && window.bootstrap.Toast) {
      window.bootstrap.Toast.getOrCreateInstance(toast, { delay: 3000 }).show();
    } else {
      toast.classList.add('show');
      window.clearTimeout(toastTimer);
      toastTimer = window.setTimeout(function () { toast.classList.remove('show'); }, 3000);
    }
    return toast;
  }

  window.getCsrfToken = getCsrfToken;
  window.installPialertAjaxHooks = installAjaxHooks;
  window.pialertPost = pialertPost;
  window.pialertCopyText = copyText;
  window.pialertPendingPosts = pendingPosts;
  window.modalCallbackFunction = modalCallbackFunction;
  window.showModalDefault = showModalDefault;
  window.showModalWarning = showModalWarning;
  window.showModalOK = showModalOK;
  window.showModalWarningOK = showModalWarningOK;
  window.modalDefaultOK = modalDefaultOK;
  window.modalWarningOK = modalWarningOK;
  window.showMessage = showMessage;

  installAjaxHooks();
})(window, document);
