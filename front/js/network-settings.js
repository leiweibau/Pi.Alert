(function () {
  'use strict';

  const page = document.getElementById('network-settings-page');
  if (!page) return;
  const configNode = document.getElementById('network-settings-data');
  let config = { managed: {}, unmanaged: {} };
  try { config = JSON.parse(configNode.textContent || '{}'); } catch (_) { /* leave empty */ }
  const byId = (id) => document.getElementById(id);
  const value = (id) => byId(id).value;
  const endpoint = 'php/server/network.php';

  function fill(id, nextValue) {
    const input = byId(id);
    if (input) {
      input.value = nextValue == null ? '' : String(nextValue);
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function showResponse(response) {
    const html = String(response == null ? '' : response);
    const successful = /<meta\s+[^>]*http-equiv\s*=\s*['"]?refresh\b/i.test(html);
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const message = (doc.body.textContent || '').trim();
    if (message) window.showMessage(message);
    if (successful) window.setTimeout(() => window.location.reload(), 2000);
  }

  function submit(formId, action, fields, confirmDelete) {
    const form = byId(formId);
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;
      if (confirmDelete && !window.confirm(form.querySelector('button[type="submit"]').textContent.trim() + '?')) return;
      const payload = {};
      for (const [name, inputId] of Object.entries(fields)) payload[name] = value(inputId);
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      window.pialertPost(endpoint + '?action=' + encodeURIComponent(action), payload, showResponse)
        .fail(function (xhr) { window.showMessage(xhr.responseText || xhr.statusText || window.pialertV4Text('V4_Request_Failed')); })
        .always(function () { button.disabled = false; });
    });
  }

  function renderOptions(containerId, html) {
    const container = byId(containerId);
    const parsed = new DOMParser().parseFromString(String(html), 'text/html');
    container.replaceChildren();
    for (const source of parsed.querySelectorAll('a.network-value-option')) {
      const targetId = source.getAttribute('data-target');
      if (!targetId || !byId(targetId)) continue;
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'dropdown-item';
      item.textContent = source.textContent || '';
      item.addEventListener('click', function () {
        const selected = source.getAttribute('data-value') || '';
        const next = source.getAttribute('data-action') === 'append' ? value(targetId) + selected : selected;
        fill(targetId, next);
      });
      container.appendChild(item);
    }
  }

  function loadList(action, containerId, params = {}) {
    const url = new URL(endpoint, window.location.href);
    url.searchParams.set('action', action);
    for (const [key, entry] of Object.entries(params)) url.searchParams.set(key, entry);
    window.fetch(url, { credentials: 'same-origin' })
      .then(function (response) { if (!response.ok) throw new Error('HTTP ' + response.status); return response.text(); })
      .then(function (html) { renderOptions(containerId, html); })
      .catch(function (error) { console.error('Network list:', error); });
  }

  function loadDownlinks(type) {
    const input = byId('txtNetworkDeviceDownlinkMac');
    input.placeholder = ['3_WLAN', '4_Powerline', '5_Hypervisor'].includes(type)
      ? page.dataset.downlinkAltPlaceholder : page.dataset.downlinkPlaceholder;
    if (type) loadList('network_device_downlink', 'dropdownNetworkDeviceDownlinkMac', { nodetyp: type });
    else byId('dropdownNetworkDeviceDownlinkMac').replaceChildren();
  }

  byId('UpdNetworkDeviceID').addEventListener('change', function () {
    const record = config.managed[this.value];
    if (!record) return;
    fill('NewNetworkDeviceName', record[0]);
    fill('txtNewNetworkDeviceTyp', record[1]);
    fill('txtNetworkDeviceDownlinkMac', record[2]);
    fill('NewNetworkDevicePort', record[3]);
    fill('txtNewNetworkGroupName', record[4]);
  });
  byId('NetworkUnmanagedDevID').addEventListener('change', function () {
    const record = config.unmanaged[this.value];
    if (!record) return;
    fill('NewNetworkUnmanagedDevName', record[0]);
    fill('NewNetworkUnmanagedDevConnect', record[1]);
    fill('NewNetworkUnmanagedDevPort', record[2]);
  });
  byId('txtNewNetworkDeviceTyp').addEventListener('change', function () { loadDownlinks(this.value); });

  loadList('NetworkInfrastructure_list', 'dropdownNetworkNodeMac');
  loadList('NetworkDeviceTyp_list', 'dropdownNetworkDeviceTyp', { mode: 'add' });
  loadList('NetworkDeviceTyp_list', 'dropdownNewNetworkDeviceTyp', { mode: 'edit' });
  loadList('NetworkGroupName_list', 'dropdownNetworkGroupName', { mode: 'add' });
  loadList('NetworkGroupName_list', 'dropdownNewNetworkGroupName', { mode: 'edit' });

  submit('network-managed-add', 'addManagedDev', {
    NetworkDeviceName: 'txtNetworkDeviceName', NetworkDeviceTyp: 'txtNetworkDeviceTyp',
    NetworkDevicePort: 'NetworkDevicePort', NetworkGroupName: 'txtNetworkGroupName'
  }, false);
  submit('network-managed-edit', 'updManagedDev', {
    NetworkDeviceID: 'UpdNetworkDeviceID', NewNetworkDeviceName: 'NewNetworkDeviceName',
    NewNetworkDeviceTyp: 'txtNewNetworkDeviceTyp', NewNetworkDevicePort: 'NewNetworkDevicePort',
    NewNetworkGroupName: 'txtNewNetworkGroupName', NetworkDeviceDownlink: 'txtNetworkDeviceDownlinkMac'
  }, false);
  submit('network-managed-delete', 'delManagedDev', { NetworkDeviceID: 'DelNetworkDeviceID' }, true);
  submit('network-unmanaged-add', 'addUnManagedDev', {
    NetworkUnmanagedDevName: 'txtNetworkUnmanagedDevName',
    NetworkUnmanagedDevConnect: 'txtNetworkUnmanagedDevConnect',
    NetworkUnmanagedDevPort: 'NetworkUnmanagedDevPort'
  }, false);
  submit('network-unmanaged-edit', 'updUnManagedDev', {
    NetworkUnmanagedDevID: 'NetworkUnmanagedDevID',
    NewNetworkUnmanagedDevName: 'NewNetworkUnmanagedDevName',
    NewNetworkUnmanagedDevConnect: 'NewNetworkUnmanagedDevConnect',
    NewNetworkUnmanagedDevPort: 'NewNetworkUnmanagedDevPort'
  }, false);
  submit('network-unmanaged-delete', 'delUnManagedDev', {
    NetworkUnmanagedDevID: 'DelNetworkUnmanagedDevID'
  }, true);
}());
