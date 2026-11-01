// Small progressive enhancements. The site works without JavaScript; this just makes it nicer.
// No inline scripts anywhere (the Content-Security-Policy forbids them).
(function () {
  document.documentElement.classList.add('js');

  // Mobile menu
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Ask before forms that have data-confirm="message" (booking, cancelling, deactivating...)
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      event.preventDefault();
    }
  });

  // Close buttons on flash messages
  document.addEventListener('click', function (event) {
    var btn = event.target.closest ? event.target.closest('.flash-close') : null;
    if (btn && btn.parentElement) {
      btn.parentElement.remove();
    }
  });

  // Show / hide password
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.togglePassword);
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? btn.dataset.hide : btn.dataset.show;
    });
  });

  // User form: only show the fields that belong to the chosen role
  var roleSelect = document.querySelector('[data-role-select]');
  var roleInput = document.getElementById('role');
  function syncRoleFields() {
    var role = roleSelect ? roleSelect.value : (roleInput ? roleInput.value : '');
    document.querySelectorAll('.role-only').forEach(function (el) {
      el.classList.toggle('show', el.classList.contains('role-' + role));
    });
  }
  var roleRadios = document.querySelectorAll('[data-role-radio]');
  if (roleRadios.length) {
    var syncRadios = function () {
      var checked = document.querySelector('[data-role-radio]:checked');
      var role = checked ? checked.value : '';
      document.querySelectorAll('.role-only').forEach(function (el) {
        el.classList.toggle('show', el.classList.contains('role-' + role));
      });
    };
    roleRadios.forEach(function (r) { r.addEventListener('change', syncRadios); });
    syncRadios();
  } else if (roleSelect || (roleInput && document.getElementById('user-form'))) {
    syncRoleFields();
    if (roleSelect) { roleSelect.addEventListener('change', syncRoleFields); }
  }
})();
