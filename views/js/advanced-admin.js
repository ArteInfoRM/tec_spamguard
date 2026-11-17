/**
 * 2009-2026 Tecnoacquisti.com
 * @license MIT License
 */
(() => {
  const initialize = () => {
    const bounds = {
      CAPTCHA: [1, 100],
      TRAP: [1, 20],
      WINDOW: [1, 1440],
      PAUSE: [1, 1440],
      ADMISSIONS: [1, 1000],
      PERIOD: [1, 1440],
      IP: [1, 10000],
      COOLDOWN: [0, 3600],
    };
    const applyBounds = (name, range) => {
      const input = document.querySelector(`[name="TEC_SPAMGUARD_${name}"]`);
      if (input) {
        input.type = 'number';
        [input.min, input.max] = range.map(String);
        input.step = '1';
        input.required = true;
      }
    };
    ['REGISTER', 'CONTACT', 'PASSWORD'].forEach((form) => {
      Object.keys(bounds).forEach((suffix) => {
        applyBounds(`GUARD_${form}_${suffix}`, bounds[suffix]);
      });
    });
    applyBounds('ABUSE_SCORE', [1, 100]);
    applyBounds('ABUSE_BUDGET', [1, 100000]);
    const resetButton = document.querySelector('[data-sg-reset-defaults]');
    if (resetButton) {
      resetButton.addEventListener('click', () => {
        const form = resetButton.closest('form');
        const defaults = JSON.parse(resetButton.dataset.sgDefaults);
        if (!form) {
          return;
        }
        Object.keys(defaults).forEach((name) => {
          const inputs = Array.from(form.elements).filter((input) => input.name === name);
          inputs.forEach((input) => {
            if (input.type === 'radio') {
              input.checked = input.value === String(defaults[name]);
            } else {
              input.value = String(defaults[name]);
            }
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (input.tagName === 'SELECT' && window.jQuery) {
              window.jQuery(input).trigger('chosen:updated');
            }
          });
        });
      });
    }
    const button = document.querySelector('[data-sg-random-field]');
    const select = document.querySelector('[name="TEC_SPAMGUARD_GUARD_FIELD"]');
    if (button && select && window.crypto) {
      button.addEventListener('click', () => {
        const values = new Uint32Array(1);
        window.crypto.getRandomValues(values);
        select.selectedIndex = values[0] % select.options.length;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
