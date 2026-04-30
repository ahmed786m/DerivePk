(function initAuthState() {
  const guard = document.body.dataset.guard || '';
  const allowedRoles = guard ? guard.split(',').map(role => role.trim()) : [];

  fetch('php/auth.php?action=status', { cache: 'no-store' })
    .then(response => response.json())
    .then(session => {
      if (allowedRoles.length) {
        const ok = session.logged_in && allowedRoles.includes(session.role);
        if (!ok) {
          const role = allowedRoles.includes('admin') ? 'admin' : 'customer';
          window.location.href = `login.html?role=${role}&error=${encodeURIComponent('Please login with the correct account type.')}`;
          return;
        }
      }

      document.querySelectorAll('[data-user-name]').forEach(item => {
        item.textContent = session.name || 'Guest';
      });

      document.querySelectorAll('[data-user-role]').forEach(item => {
        item.textContent = session.role ? session.role.replace('-', ' ') : 'guest';
      });

      document.querySelectorAll('[data-show-role]').forEach(item => {
        const roles = item.dataset.showRole.split(',').map(role => role.trim());
        item.hidden = !roles.includes(session.role);
      });
    })
    .catch(() => {
      if (allowedRoles.length) {
        const box = document.getElementById('alertBox');
        if (box) {
          box.innerHTML = '<div class="alert alert-error">Start XAMPP Apache and open the site through http://localhost/drivedpk/.</div>';
        }
      }
    });
})();
