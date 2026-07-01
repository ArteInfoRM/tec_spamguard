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

    function hasResponseValue(form, name) {
        var fields = name ? form.querySelectorAll('[name="' + name + '"]') : [];

        return Array.prototype.some.call(fields, function (field) {
            return String(field.value || field.getAttribute('value') || '').trim() !== '';
        });
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
        var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }

    function setFallbackSolved(form, name, payload) {
        if (name) {
            appendHidden(form, name, payload || '');
        }
        form.setAttribute('data-tec-spamguard-fallback-solved', payload ? '1' : '0');
    }

    function appendSubmitterFallback(form, submitter) {
        var input;

        if (!submitter || !submitter.name) {
            return;
        }

        input = form.querySelector('input[type="hidden"][data-tec-spamguard-submitter="' + submitter.name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = submitter.name;
            input.setAttribute('data-tec-spamguard-submitter', submitter.name);
            form.appendChild(input);
        }
        input.value = submitter.value || '';
    }

    function submitWithToken(form, submitter) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || undefined);
            return;
        }

        appendSubmitterFallback(form, submitter);
        form.submit();
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

    function registerAltchaI18n(config) {
        var i18n = config.altchaI18n || {};
        var language = i18n.language || '';
        var strings = i18n.strings || {};
        var baseStrings;

        if (!language || !window.$altcha || !window.$altcha.i18n || typeof window.$altcha.i18n.set !== 'function') {
            return;
        }

        baseStrings = typeof window.$altcha.i18n.get === 'function' ? window.$altcha.i18n.get('en') : {};
        window.$altcha.i18n.set(language, Object.assign({}, baseStrings || {}, strings));
    }

    function renderRecaptchaV2(form, type, config) {
        var container = ensureContainer(form, type);
        if (container.getAttribute('data-rendered') === '1') {
            return;
        }
        container.className += ' g-recaptcha';
        container.setAttribute('data-sitekey', config.siteKey);
        container.setAttribute('data-rendered', '1');
        if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
            try {
                window.grecaptcha.render(container, {
                    sitekey: config.siteKey
                });
            } catch (error) {
                // The provider may already have auto-rendered this container.
            }
        }
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
        if (window.turnstile && typeof window.turnstile.render === 'function') {
            try {
                window.turnstile.render(container, {
                    sitekey: config.siteKey
                });
            } catch (error) {
                // The provider may already have auto-rendered this container.
            }
        }
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
        var frame;
        var logo;
        if (container.getAttribute('data-rendered') === '1' || container.getAttribute('data-render-pending') === '1') {
            return;
        }
        if (window.customElements && !window.customElements.get('altcha-widget')) {
            container.setAttribute('data-render-pending', '1');
            window.customElements.whenDefined('altcha-widget').then(function () {
                container.setAttribute('data-render-pending', '0');
                renderAltcha(form, type, config);
            });
            return;
        }

        registerAltchaI18n(config);
        container.className += config.provider === 'altcha' ? ' tec-spamguard-altcha-local' : '';

        var widget = document.createElement('altcha-widget');
        widget.setAttribute('challenge', withCacheBuster(config.siteKey, type));
        widget.setAttribute('name', config.responseField);
        if (config.altchaI18n && config.altchaI18n.language) {
            widget.setAttribute('language', config.altchaI18n.language);
        }
        if (config.widgetAttributes) {
            widget.setAttribute('configuration', JSON.stringify(config.widgetAttributes));
        }
        widget.addEventListener('verified', function (event) {
            if (event.detail && event.detail.payload) {
                setFallbackSolved(form, config.responseField, event.detail.payload);
            }
        });
        widget.addEventListener('expired', function () {
            setFallbackSolved(form, config.responseField, '');
            resetAltchaWidget(widget, type, config);
        });
        widget.addEventListener('statechange', function (event) {
            if (!event.detail) {
                return;
            }
            if (event.detail.payload && event.detail.state === 'verified') {
                setFallbackSolved(form, config.responseField, event.detail.payload);
            }
            if (event.detail.state === 'error') {
                setFallbackSolved(form, config.responseField, '');
                window.setTimeout(function () {
                    resetAltchaWidget(widget, type, config);
                }, 500);
            }
        });
        afterFormAttempt(form, function () {
            setFallbackSolved(form, config.responseField, '');
            resetAltchaWidget(widget, type, config);
        });
        if (config.provider === 'altcha' && config.moduleLogoUrl) {
            frame = document.createElement('div');
            frame.className = 'tec-spamguard-altcha-frame';
            logo = document.createElement('img');
            logo.className = 'tec-spamguard-altcha-local-logo';
            logo.src = config.moduleLogoUrl;
            logo.alt = config.moduleLogoAlt || 'Protected by Tec Spam Guard';
            logo.loading = 'lazy';
            frame.appendChild(widget);
            frame.appendChild(logo);
            container.appendChild(frame);
        } else {
            container.appendChild(widget);
        }
        container.setAttribute('data-rendered', '1');
    }

    function renderRecaptchaV3Notice(form, type, config) {
        var container = ensureContainer(form, type);
        var notice;

        if (container.getAttribute('data-rendered') === '1') {
            return;
        }

        notice = document.createElement('p');
        notice.className = 'tec-spamguard-recaptcha-notice';
        notice.textContent = config.recaptchaNotice || 'This site is protected by reCAPTCHA.';

        container.appendChild(notice);
        container.setAttribute('data-rendered', '1');
    }

    function renderFallbackCaptcha(form, type, fallbackConfig) {
        var fallbackType = type + '-fallback';
        var container = ensureContainer(form, fallbackType);
        var message;

        if (!form.querySelector('.tec-spamguard-fallback-message[data-form="' + type + '"]')) {
            message = document.createElement('p');
            message.className = 'tec-spamguard-fallback-message';
            message.setAttribute('data-form', type);
            message.textContent = fallbackConfig.message || 'Complete the additional antispam verification before submitting your request.';
            container.parentNode.insertBefore(message, container);
        }

        if (fallbackConfig.provider === 'recaptcha_v2') {
            renderRecaptchaV2(form, fallbackType, fallbackConfig);
        } else if (fallbackConfig.provider === 'turnstile') {
            renderTurnstile(form, fallbackType, fallbackConfig);
        } else if (fallbackConfig.provider === 'altcha' || fallbackConfig.provider === 'altcha_sentinel') {
            renderAltcha(form, fallbackType, fallbackConfig);
        }

        form.setAttribute('data-tec-spamguard-fallback-required', '1');
    }

    function precheckRecaptchaV3(config, token) {
        var body = 'token=' + encodeURIComponent(token);

        return window.fetch(config.recaptchaPrecheckUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body
        }).then(function (response) {
            if (!response.ok) {
                return {
                    success: false,
                    fallback: false
                };
            }

            return response.json();
        });
    }

    function bindRecaptchaV3(form, type, config) {
        var lastSubmitter = null;

        document.body.classList.add('tec-spamguard-recaptcha-v3');
        renderRecaptchaV3Notice(form, type, config);

        if (form.getAttribute('data-tec-spamguard-v3') === '1') {
            return;
        }
        form.setAttribute('data-tec-spamguard-v3', '1');

        form.addEventListener('click', function (event) {
            var clickedSubmitter = event.target && event.target.closest
                ? event.target.closest('button[type="submit"], input[type="submit"], button:not([type])')
                : null;

            if (clickedSubmitter && form.contains(clickedSubmitter)) {
                lastSubmitter = clickedSubmitter;
            }
        }, true);

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || lastSubmitter || findSubmit(form);

            if (form.getAttribute('data-tec-spamguard-ready') === '1') {
                form.setAttribute('data-tec-spamguard-ready', '0');
                lastSubmitter = null;
                return;
            }

            if (form.getAttribute('data-tec-spamguard-fallback-required') === '1') {
                if (config.fallback && (form.getAttribute('data-tec-spamguard-fallback-solved') === '1'
                    || hasResponseValue(form, config.fallback.responseField))) {
                    form.setAttribute('data-tec-spamguard-ready', '1');
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            if (!window.grecaptcha || !window.grecaptcha.execute) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            window.grecaptcha.ready(function () {
                window.grecaptcha.execute(config.siteKey, {action: config.recaptchaAction || 'tec_spamguard'}).then(function (token) {
                    appendHidden(form, config.recaptchaV3ResponseField || config.responseField, token);

                    if (!config.fallback || !config.recaptchaPrecheckUrl || !window.fetch) {
                        form.setAttribute('data-tec-spamguard-ready', '1');
                        submitWithToken(form, submitter);
                        return;
                    }

                    precheckRecaptchaV3(config, token).then(function (result) {
                        if (result && result.success) {
                            form.setAttribute('data-tec-spamguard-ready', '1');
                            submitWithToken(form, submitter);
                            return;
                        }

                        if (result && result.fallback) {
                            renderFallbackCaptcha(form, type, config.fallback);
                            return;
                        }

                        form.setAttribute('data-tec-spamguard-ready', '1');
                        submitWithToken(form, submitter);
                    });
                });
            });
        }, true);
    }

    function initForm(type, form, config) {
        if (config.provider === 'recaptcha_v2') {
            renderRecaptchaV2(form, type, config);
        } else if (config.provider === 'turnstile') {
            renderTurnstile(form, type, config);
        } else if (config.provider === 'altcha' || config.provider === 'altcha_sentinel') {
            renderAltcha(form, type, config);
        } else if (config.provider === 'recaptcha_v3') {
            bindRecaptchaV3(form, type, config);
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
