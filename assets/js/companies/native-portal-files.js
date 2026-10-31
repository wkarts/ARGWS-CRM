(function () {
  'use strict';
  var configNode = document.getElementById('native-portal-files-config');
  if (!configNode) return;
  var config = JSON.parse(configNode.textContent), form = document.getElementById('files-upload');
  var failures = false;
  function requestKey() { var bytes = new Uint8Array(32); crypto.getRandomValues(bytes); return Array.from(bytes, function (v) { return v.toString(16).padStart(2, '0'); }).join(''); }
  function failure() { failures = true; document.getElementById('native-portal-files-error').hidden = false; }
  function fields(data, key) { data.append('_native_portal_csrf', config.csrf); data.append('issuer_id', String(config.issuer)); data.append('native_file_request', key); }
  function accepted(value) { return value && value.success === true && Number.isSafeInteger(value.file_id) && value.file_id > 0 && value.notification === 'recorded' && value.mail === 'pending'; }
  Dropzone.autoDiscover = false;
  var zone = new Dropzone(form, {url: form.action, paramName: 'file', maxFilesize: config.maximum / 1048576, acceptedFiles: config.allowed, addRemoveLinks: true, parallelUploads: 1});
  zone.on('sending', function (file, xhr, data) { file.nativeRequestKey = file.nativeRequestKey || requestKey(); fields(data, file.nativeRequestKey); });
  zone.on('success', function (file, response) { if (typeof response === 'string') { try { response = JSON.parse(response); } catch (_) { failure(); return; } } if (!accepted(response)) failure(); });
  zone.on('error', failure);
  zone.on('queuecomplete', function () { if (!failures && zone.getAcceptedFiles().length) window.location.assign(config.returnUrl); });
  window.customerFileGoogleDriveSave = function (items) { external(items, 'gdrive'); };
  async function external(items, provider) {
    if (!Array.isArray(items) || !items.length) return;
    try {
      for (var i = 0; i < items.length; i++) {
        var item = items[i]; if (!item || typeof item.name !== 'string' || typeof item.link !== 'string') throw new Error('invalid file');
        item.nativeRequestKey = item.nativeRequestKey || requestKey();
        var data = new FormData(); fields(data, item.nativeRequestKey); data.append('external', provider); data.append('files[0][name]', item.name); data.append('files[0][link]', item.link);
        var response = await fetch(form.action, {method: 'POST', credentials: 'same-origin', body: data, redirect: 'error'});
        if (!response.ok || !accepted(await response.json())) throw new Error('upload rejected');
      }
      window.location.assign(config.returnUrl);
    } catch (_) { failure(); }
  }
  if (config.dropbox && window.Dropbox) document.getElementById('dropbox-chooser-files').appendChild(Dropbox.createChooseButton({success: function (files) { external(files, 'dropbox'); }, linkType: 'preview', extensions: config.allowed.split(',')}));
  if (config.google && window.gapi && window.google && jQuery.fn.googleDrivePicker) jQuery('.gpicker').googleDrivePicker({clientId: config.googleClient, developerKey: config.googleKey, onPick: window.customerFileGoogleDriveSave});
  jQuery('.table-files').DataTable({order: [[1, 'desc']], responsive: true});
  document.querySelectorAll('.img-table-loading').forEach(function (img) { img.src = img.dataset.orig; img.addEventListener('load', function () { var spinner = img.parentNode.querySelector('.fa-spinner'); if (spinner) spinner.remove(); }); });
  document.querySelectorAll('.native-portal-file-delete').forEach(function (f) { f.addEventListener('submit', function (event) { if (!window.confirm(config.confirmDelete)) event.preventDefault(); }); });
})();
