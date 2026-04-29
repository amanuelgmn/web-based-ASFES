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
      button.querySelector('[data-icon]')?.textContent = target.type === 'password' ? 'visibility' : 'visibility_off';
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
    const output = document.querySelector(select.getAttribute('data-feedback-target'));
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
