document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const sidebarToggleButtons = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));
  const passwordToggleButtons = Array.from(document.querySelectorAll('[data-password-toggle]'));

  const syncSidebarState = () => {
    const isOpen = body.classList.contains('sidebar-open');

    sidebarToggleButtons.forEach((button) => {
      button.setAttribute('aria-expanded', String(isOpen));
    });

    try {
      localStorage.setItem('sidebar-open', isOpen ? 'true' : 'false');
    } catch (error) {
      // Local storage may be unavailable in private browsing modes.
    }
  };

  const closeSidebar = () => {
    if (!body.classList.contains('sidebar-open')) return;
    body.classList.remove('sidebar-open');
    syncSidebarState();
  };

  try {
    if (localStorage.getItem('sidebar-open') === 'true') {
      body.classList.add('sidebar-open');
    }
  } catch (error) {
    // Ignore storage access failures and fall back to default closed state.
  }

  sidebarToggleButtons.forEach((button) => {
    button.setAttribute('aria-expanded', String(body.classList.contains('sidebar-open')));
    button.addEventListener('click', () => {
      body.classList.toggle('sidebar-open');
      syncSidebarState();
    });
  });

  passwordToggleButtons.forEach((button) => {
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
      const target = document.querySelector(button.getAttribute('data-password-toggle'));
      if (!target) return;

      target.type = target.type === 'password' ? 'text' : 'password';
      const visible = target.type === 'text';
      const icon = button.querySelector('[data-icon]');
      if (icon) {
        icon.textContent = visible ? 'visibility_off' : 'visibility';
      }
      button.setAttribute('aria-pressed', String(visible));
    });
  });

  document.querySelectorAll('[data-radio-strip]').forEach((group) => {
    const options = group.querySelectorAll('.radio-option');
    const sync = () => {
      options.forEach((option) => {
        const input = option.querySelector('input');
        option.classList.toggle('is-active', Boolean(input?.checked));
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

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', function () {
      const submitButton = this.querySelector('button[type="submit"]');
      if (!submitButton) {
        return;
      }

      submitButton.disabled = true;
      setTimeout(() => {
        submitButton.disabled = false;
      }, 2000);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  document.addEventListener('click', (event) => {
    if (!body.classList.contains('sidebar-open')) {
      return;
    }

    if (event.target.closest('.sidebar') || event.target.closest('[data-sidebar-toggle]')) {
      return;
    }

    closeSidebar();
  });
});
