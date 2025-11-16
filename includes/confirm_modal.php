<?php
// Reusable confirmation modal include (shared between admin and public pages)
?>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Please confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmModalBody">Are you sure?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmModalOk">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
// confirmModal(message) returns a Promise that resolves true if OK pressed, false otherwise
(function(){
  if (window._nacos_confirm_modal_initialized) return;
  window._nacos_confirm_modal_initialized = true;

  function initConfirmModal() {
    var modalEl = document.getElementById('confirmModal');
    if (!modalEl) return;
    var bsModal = new bootstrap.Modal(modalEl);
    var bodyEl = document.getElementById('confirmModalBody');
    var okBtn = document.getElementById('confirmModalOk');
    var pendingResolve = null;

    window.confirmModal = function(message, options) {
      // options: { title: string, okLabel: string }
      return new Promise(function(resolve){
        bodyEl.textContent = message || 'Are you sure?';
        // set title and OK label when provided
        if (options && options.title) modalEl.querySelector('.modal-title').textContent = options.title;
        else modalEl.querySelector('.modal-title').textContent = 'Please confirm';
        if (options && options.okLabel) okBtn.textContent = options.okLabel;
        else okBtn.textContent = 'OK';
        pendingResolve = resolve;
        bsModal.show();
      });
    };

    okBtn.addEventListener('click', function(){
      if (typeof pendingResolve === 'function') pendingResolve(true);
      pendingResolve = null;
      bsModal.hide();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (typeof pendingResolve === 'function') pendingResolve(false);
      pendingResolve = null;
    });

    // Intercept links, forms and special buttons marked for confirmation
    document.addEventListener('DOMContentLoaded', function(){
      // forms
      document.querySelectorAll('.confirm-action-form').forEach(function(form){
        if (form._confirm_bound) return; form._confirm_bound = true;
        form.addEventListener('submit', function(e){
          e.preventDefault();
          var msg = form.dataset.message || 'Are you sure?';
          var opts = {};
          if (form.dataset.title) opts.title = form.dataset.title;
          if (form.dataset.okLabel) opts.okLabel = form.dataset.okLabel;
          window.confirmModal(msg, opts).then(function(ok){ if (ok) form.submit(); });
        });
      });

      // links
      document.querySelectorAll('.confirm-action-link').forEach(function(link){
        if (link._confirm_bound) return; link._confirm_bound = true;
        link.addEventListener('click', function(e){
          e.preventDefault();
          var msg = link.dataset.message || 'Are you sure?';
          var href = link.href;
          var opts = {};
          if (link.dataset.title) opts.title = link.dataset.title;
          if (link.dataset.okLabel) opts.okLabel = link.dataset.okLabel;
          window.confirmModal(msg, opts).then(function(ok){ if (ok) window.location.href = href; });
        });
      });

      // buttons inside forms that trigger confirmation (e.g., reject/delete buttons)
      document.querySelectorAll('.confirm-action-btn').forEach(function(btn){
        if (btn._confirm_bound) return; btn._confirm_bound = true;
        btn.addEventListener('click', function(e){
          e.preventDefault();
          var msg = btn.dataset.message || 'Are you sure?';
          var actionVal = btn.dataset.action || null;
          var form = btn.closest('form');
          var opts = {};
          if (btn.dataset.title) opts.title = btn.dataset.title;
          if (btn.dataset.okLabel) opts.okLabel = btn.dataset.okLabel;
          window.confirmModal(msg, opts).then(function(ok){
            if (!ok) return;
            if (form) {
              // if button declares a data-action, set the form's action input if present
              if (actionVal) {
                var actionInput = form.querySelector('input[name="action"]');
                if (actionInput) actionInput.value = actionVal;
                else {
                  var hidden = document.createElement('input');
                  hidden.type = 'hidden'; hidden.name = 'action'; hidden.value = actionVal;
                  form.appendChild(hidden);
                }
              }
              form.submit();
            }
          });
        });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initConfirmModal);
  else initConfirmModal();
})();
</script>
