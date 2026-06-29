(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }
        document.addEventListener('DOMContentLoaded', callback);
    }

    function getConfig() {
        return window.tecSpamGuard || null;
    }

    function formActionMatchesCurrentPage(form) {
        var action = form.getAttribute('action') || '';
        var actionUrl;

        if (!action) {
            return true;
        }

        try {
            actionUrl = new URL(action, window.location.href);
        } catch (error) {
            return false;
        }

        return actionUrl.pathname === window.location.pathname;
    }

    function getForms(type) {
        return Array.prototype.filter.call(document.querySelectorAll('form'), function (form) {
            if (type === 'contact') {
                return !!form.querySelector('[name="submitMessage"]');
            }
            if (type === 'register') {
                return !!form.querySelector('[name="submitCreate"], [name="submitAccount"]');
            }
            if (type === 'login') {
                return !!form.querySelector('[name="submitLogin"], [name="SubmitLogin"]');
            }
            if (type === 'password') {
                if (form.querySelector('#send-reset-link')) {
                    return true;
                }
                if (form.querySelector('[name="submitLogin"], [name="SubmitLogin"], [name="password"], [name="submitNewsletter"], [name="s"]')) {
                    return false;
                }
                if (!form.querySelector('input[type="email"][name="email"], input[name="email"]')) {
                    return false;
                }

                return form.classList.contains('forgotten-password')
                    || (document.body.id === 'password' && formActionMatchesCurrentPage(form));
            }

            return false;
        });
    }

    function findSubmit(form) {
        return form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
    }

    function ensureContainer(form, type) {
        var existing = form.querySelector('.tec-spamguard-widget[data-form="' + type + '"]');
        if (existing) {
            return existing;
        }

        var container = document.createElement('div');
        container.className = 'tec-spamguard-widget';
        container.setAttribute('data-form', type);

        var submit = findSubmit(form);
        if (submit && submit.parentNode) {
            submit.parentNode.insertBefore(container, submit);
            return container;
        }

        form.appendChild(container);
        return container;
    }

    function appendHidden(form, name, value) {
        var input = form.querySelector('[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    function renderRecaptchaV2(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }
        container.className += ' g-recaptcha';
        container.setAttribute('data-sitekey', config.siteKey);
        container.setAttribute('data-rendered', '1');
    }

    function renderTurnstile(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }
        container.className += ' cf-turnstile';
        container.setAttribute('data-sitekey', config.siteKey);
        container.setAttribute('data-rendered', '1');
    }

    function renderAltcha(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }

        var widget = document.createElement('altcha-widget');
        widget.setAttribute('challenge', config.siteKey);
        widget.setAttribute('name', config.responseField);
        if (config.widgetAttributes) {
            widget.setAttribute('configuration', JSON.stringify(config.widgetAttributes));
        }
        container.appendChild(widget);
        container.setAttribute('data-rendered', '1');
    }

    function bindRecaptchaV3(form, config) {
        if (form.getAttribute('data-tec-spamguard-v3') === '1') {
            return;
        }
        form.setAttribute('data-tec-spamguard-v3', '1');
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-tec-spamguard-ready') === '1') {
                form.setAttribute('data-tec-spamguard-ready', '0');
                return;
            }

            if (!window.grecaptcha || !window.grecaptcha.execute) {
                return;
            }

            event.preventDefault();
            window.grecaptcha.ready(function () {
                window.grecaptcha.execute(config.siteKey, {action: config.recaptchaAction || 'tec_spamguard'}).then(function (token) {
                    appendHidden(form, config.responseField, token);
                    form.setAttribute('data-tec-spamguard-ready', '1');
                    form.submit();
                });
            });
        });
    }

    function initForm(type, form, config) {
        if (config.provider === 'recaptcha_v2') {
            renderRecaptchaV2(form, type, config);
        } else if (config.provider === 'turnstile') {
            renderTurnstile(form, type, config);
        } else if (config.provider === 'altcha' || config.provider === 'altcha_sentinel') {
            renderAltcha(form, type, config);
        } else if (config.provider === 'recaptcha_v3') {
            bindRecaptchaV3(form, config);
        }
    }

    ready(function () {
        var config = getConfig();
        if (!config || !config.provider || !config.forms || !config.siteKey) {
            return;
        }

        Object.keys(config.forms).forEach(function (type) {
            getForms(type).forEach(function (form) {
                initForm(type, form, config);
            });
        });
    });
}());
