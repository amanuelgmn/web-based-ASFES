document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;

  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      body.classList.toggle('sidebar-open');
    });
  });

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.querySelector(button.getAttribute('data-password-toggle'));
      if (!target) return;
      target.type = target.type === 'password' ? 'text' : 'password';
      button.querySelector('[data-icon]')?.textContent =
        target.type === 'password' ? 'visibility' : 'visibility_off';
    });
  });

  document.querySelectorAll('[data-radio-strip]').forEach((group) => {
    const options = group.querySelectorAll('.radio-option');
    const sync = () => {
      options.forEach((option) => {
        const input = option.querySelector('input');
        option.classList.toggle('is-active', input.checked);
      });
    };

    options.forEach((option) => {
      option.addEventListener('click', () => {
        const input = option.querySelector('input');
        if (input) {
          input.checked = true;
          sync();
        }
      });
      option.querySelector('input')?.addEventListener('change', sync);
    });

    sync();
  });

  document.querySelectorAll('[data-feedback-category]').forEach((select) => {
    const targetSelector = select.getAttribute('data-feedback-target');
    const output = targetSelector ? document.querySelector(targetSelector) : null;

    const map = {
      course: 'Instructor',
      instructor: 'Instructor',
      assessment: 'Department',
      department: 'Department',
      harassment: 'Student Affairs',
    };

    const sync = () => {
      if (output) {
        output.textContent = map[select.value] || 'Department';
      }
    };

    select.addEventListener('change', sync);
    sync();
  });

  document.querySelectorAll('[data-autofocus-first]').forEach((section) => {
    const input = section.querySelector('input, textarea, select');
    input?.focus({ preventScroll: true });
  });
});


// Prevent double form submission
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', function () {
    const btn = this.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      setTimeout(() => btn.disabled = false, 2000);
    }
  });
});


// Persist sidebar state across refresh
(function () {
  const body = document.body;

  try {
    const saved = localStorage.getItem('sidebar-open');
    if (saved === 'true') {
      body.classList.add('sidebar-open');
    }
  } catch (e) {}

  document.querySelectorAll('[data-sidebar-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      try {
        localStorage.setItem(
          'sidebar-open',
          body.classList.contains('sidebar-open')
        );
      } catch (e) {}
    });
  });
})();


// Close sidebar when clicking outside
document.addEventListener('click', function (e) {
  const sidebar = document.querySelector('.sidebar');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  if (!sidebar || !toggle) return;

  const inside = sidebar.contains(e.target) || toggle.contains(e.target);

  if (!inside && window.innerWidth <= 1180) {
    document.body.classList.remove('sidebar-open');
  }
});