(function () {
  'use strict';

  var activeRequest = null;
  var geoDbUpdateActive = false;
  var results = document.getElementById('updatecheck_result');

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function setLoading(isLoading) {
    var container = document.getElementById('updatecheck');
    var button = document.getElementById('rewwejwejpjo');
    var results = document.getElementById('updatecheck_result');
    var spinner = container ? container.querySelector('.pialert-update-spinner') : null;
    if (container) container.classList.toggle('ajax_scripts_loading', isLoading);
    if (results) results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    if (button) {
      button.hidden = isLoading;
      button.disabled = isLoading;
    }
    if (spinner) spinner.hidden = !isLoading;
  }

  function adaptLegacyFragment(container) {
    // Scripts inserted with innerHTML do not run; actions in the fragment are
    // handled by this page's delegated listeners instead.
    container.querySelectorAll('script').forEach(function (element) {
      element.remove();
    });
    container.querySelectorAll('.box').forEach(function (element) {
      element.classList.add('card');
    });
    container.querySelectorAll('.box-body').forEach(function (element) {
      element.classList.add('card-body');
    });
    container.querySelectorAll('.box-footer').forEach(function (element) {
      element.classList.add('card-footer');
    });
    container.querySelectorAll('.btn-default').forEach(function (element) {
      element.classList.add('btn-outline-secondary');
    });
    container.querySelectorAll('.pull-left').forEach(function (element) {
      element.classList.add('float-start');
    });
    container.querySelectorAll('a[target="_blank"]').forEach(function (element) {
      element.setAttribute('rel', 'noopener noreferrer');
    });
  }

  function renderError(container) {
    var message = container.getAttribute('data-error-message') || window.pialertV4Text('V4_Update_Check_Failed');
    container.replaceChildren();
    var alert = document.createElement('div');
    alert.className = 'alert alert-danger';
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    container.appendChild(alert);
  }

  if (results) results.addEventListener('click', function (event) {
    var button = event.target.closest('#updateDB-button');
    if (!button || !results.contains(button) || geoDbUpdateActive) return;
    event.preventDefault();

    geoDbUpdateActive = true;
    button.disabled = true;
    button.hidden = true;
    button.setAttribute('aria-busy', 'true');
    var spinner = results.querySelector('#downloader');
    if (spinner) spinner.hidden = false;

    window.pialertPost('php/server/services.php', { action: 'updateGeoDB' }, function () {
      geoDbUpdateActive = false;
      window.check_github_for_updates();
    }).fail(function () {
      geoDbUpdateActive = false;
      button.disabled = false;
      button.hidden = false;
      button.removeAttribute('aria-busy');
      if (spinner) spinner.hidden = true;
      window.showMessage(results.getAttribute('data-geodb-error-message') || window.pialertV4Text('V4_GeoDB_Update_Failed'));
    });
  });

  window.check_github_for_updates = function () {
    var automaticNotes = document.getElementById('auto_update_releasenotes');
    if (!results || activeRequest) return;

    results.replaceChildren();
    if (automaticNotes) automaticNotes.hidden = true;
    setLoading(true);

    var headers = {
      'Cache-Control': 'no-cache',
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'X-Requested-With': 'XMLHttpRequest'
    };
    var token = csrfToken();
    if (token) headers['X-CSRF-Token'] = token;

    activeRequest = fetch('./php/server/updatecheck_v2.php', {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: headers,
      body: ''
    }).then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.text();
    }).then(function (html) {
      results.innerHTML = html;
      adaptLegacyFragment(results);
    }).catch(function () {
      renderError(results);
    }).finally(function () {
      activeRequest = null;
      setLoading(false);
    });
  };
}());
