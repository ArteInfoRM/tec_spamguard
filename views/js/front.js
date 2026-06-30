/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Front-office captcha rendering and spam protection helpers.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

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

    function getUniqueValue() {
        return String(Date.now()) + String(Math.floor(Math.random() * 1000000));
    }

    function withCacheBuster(url, type) {
        var challengeUrl;

        try {
            challengeUrl = new URL(url, window.location.href);
        } catch (error) {
            return url;
        }

        challengeUrl.searchParams.set('_tec_spamguard_form', type);
        challengeUrl.searchParams.set('_tec_spamguard_challenge', getUniqueValue());

        return challengeUrl.toString();
    }

    function isInside(form, selector) {
        return !!form.closest(selector);
    }

    function isVisible(form) {
        return !!(form.offsetWidth || form.offsetHeight || form.getClientRects().length);
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
            if (!isVisible(form)) {
                return false;
            }
            if (type === 'contact') {
                return !!form.querySelector('[name="submitMessage"]');
            }
            if (type === 'register') {
                return !!form.querySelector('[name="submitCreate"], [name="submitAccount"]')
                    || form.id === 'customer-form'
                    || isInside(form, 'section.register-form');
            }
            if (type === 'login') {
                return !!form.querySelector('[name="submitLogin"], [name="SubmitLogin"]')
                    || form.id === 'login-form'
                    || isInside(form, 'section.login-form');
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

    function getEmailField(form, type) {
        if (type === 'contact') {
            return form.querySelector('input[name="from"]');
        }

        return form.querySelector('input[type="email"][name="email"], input[name="email"]');
    }

    function getEmailDomain(value) {
        var parts = String(value || '').trim().toLowerCase().split('@');

        return parts.length === 2 ? parts[1] : '';
    }

    function buildDomainMap(domains) {
        var map = {};

        (domains || []).forEach(function (domain) {
            domain = String(domain || '').trim().toLowerCase();
            if (domain) {
                map[domain] = true;
            }
        });

        return map;
    }

    function afterFormAttempt(form, callback) {
        var submit = findSubmit(form);

        form.addEventListener('submit', function () {
            window.setTimeout(callback, 1000);
        });
        if (submit) {
            submit.addEventListener('click', function () {
                window.setTimeout(callback, 1000);
            });
        }
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

    function resetAltchaWidget(widget, type, config) {
        if (!widget) {
            return;
        }

        widget.setAttribute('challenge', withCacheBuster(config.siteKey, type));
        if (typeof widget.configure === 'function') {
            widget.configure({
                challenge: widget.getAttribute('challenge')
            });
        }
        if (typeof widget.reset === 'function') {
            widget.reset();
        }
    }

    function renderRecaptchaV2(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }
        container.className += ' g-recaptcha';
        container.setAttribute('data-sitekey', config.siteKey);
        container.setAttribute('data-rendered', '1');
        afterFormAttempt(form, function () {
            try {
                if (window.grecaptcha && typeof window.grecaptcha.reset === 'function') {
                    window.grecaptcha.reset();
                }
            } catch (error) {
                // Provider reset is best-effort after an AJAX submit attempt.
            }
        });
    }

    function renderTurnstile(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }
        container.className += ' cf-turnstile';
        container.setAttribute('data-sitekey', config.siteKey);
        container.setAttribute('data-rendered', '1');
        afterFormAttempt(form, function () {
            try {
                if (window.turnstile && typeof window.turnstile.reset === 'function') {
                    window.turnstile.reset(container);
                }
            } catch (error) {
                // Provider reset is best-effort after an AJAX submit attempt.
            }
        });
    }

    function renderAltcha(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }

        var widget = document.createElement('altcha-widget');
        widget.setAttribute('challenge', withCacheBuster(config.siteKey, type));
        widget.setAttribute('name', config.responseField);
        if (config.widgetAttributes) {
            widget.setAttribute('configuration', JSON.stringify(config.widgetAttributes));
        }
        widget.addEventListener('expired', function () {
            resetAltchaWidget(widget, type, config);
        });
        widget.addEventListener('statechange', function (event) {
            if (event.detail && event.detail.state === 'error') {
                window.setTimeout(function () {
                    resetAltchaWidget(widget, type, config);
                }, 500);
            }
        });
        afterFormAttempt(form, function () {
            resetAltchaWidget(widget, type, config);
        });
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

    function bindEmailAdvisory(type, form, config, domainMap) {
        var emailField;

        if (form.getAttribute('data-tec-spamguard-email-advisory') === '1') {
            return;
        }

        emailField = getEmailField(form, type);
        if (!emailField) {
            return;
        }

        form.setAttribute('data-tec-spamguard-email-advisory', '1');
        form.addEventListener('submit', function (event) {
            var email = emailField.value || '';
            var domain = getEmailDomain(email);
            var confirmedValue = form.getAttribute('data-tec-spamguard-advisory-confirmed') || '';

            if (!domain || !domainMap[domain] || confirmedValue === email) {
                return;
            }

            var message = config.emailAdvisoryMessages && config.emailAdvisoryMessages[type]
                ? config.emailAdvisoryMessages[type]
                : config.emailAdvisoryMessage;

            if (window.confirm(message)) {
                form.setAttribute('data-tec-spamguard-advisory-confirmed', email);
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
        });
    }

    function initProtectedForms() {
        var config = getConfig();
        if (!config) {
            return;
        }

        if (config.emailAdvisoryForms && config.emailAdvisoryDomains && config.emailAdvisoryMessage) {
            var domainMap = buildDomainMap(config.emailAdvisoryDomains);
            Object.keys(config.emailAdvisoryForms).forEach(function (type) {
                getForms(type).forEach(function (form) {
                    bindEmailAdvisory(type, form, config, domainMap);
                });
            });
        }

        if (config.provider && config.forms && config.siteKey) {
            Object.keys(config.forms).forEach(function (type) {
                getForms(type).forEach(function (form) {
                    initForm(type, form, config);
                });
            });
        }
    }

    ready(function () {
        initProtectedForms();
        if (window.prestashop && typeof window.prestashop.on === 'function') {
            window.prestashop.on('updatedCheckout', initProtectedForms);
            window.prestashop.on('changedCheckoutStep', initProtectedForms);
        }
    });
}());
