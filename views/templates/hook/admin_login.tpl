{*
* 2009-2026 Tecnoacquisti.com
*
* Back-office login captcha renderer.
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   MIT License
*}
{foreach from=$tec_spamguard_admin_script_urls item=tec_spamguard_admin_script}
    {if $tec_spamguard_admin_script.is_module}
        <script src="{$tec_spamguard_admin_script.url|escape:'html':'UTF-8'}" async defer type="module"></script>
    {else}
        <script src="{$tec_spamguard_admin_script.url|escape:'html':'UTF-8'}" async defer></script>
    {/if}
{/foreach}

<div
    id="tec-spamguard-admin-login-captcha"
    class="tec-spamguard-admin-login-captcha"
    data-provider="{$tec_spamguard_admin_provider|escape:'html':'UTF-8'}"
    data-site-key="{$tec_spamguard_admin_site_key|escape:'html':'UTF-8'}"
    data-response-field="{$tec_spamguard_admin_response_field|escape:'html':'UTF-8'}"
    data-recaptcha-v3-response-field="{$tec_spamguard_admin_recaptcha_v3_response_field|escape:'html':'UTF-8'}"
    data-recaptcha-action="{$tec_spamguard_admin_recaptcha_action|escape:'html':'UTF-8'}"
    data-recaptcha-precheck-url="{$tec_spamguard_admin_recaptcha_precheck_url|escape:'html':'UTF-8'}"
    data-widget-attributes="{$tec_spamguard_admin_widget_attributes_json|escape:'html':'UTF-8'}"
    data-fallback="{$tec_spamguard_admin_fallback_json|escape:'html':'UTF-8'}"
    data-logo-url="{$tec_spamguard_admin_logo_url|escape:'html':'UTF-8'}"
    data-logo-alt="{$tec_spamguard_admin_logo_alt|escape:'html':'UTF-8'}"
    data-altcha-i18n="{$tec_spamguard_admin_altcha_i18n_json|escape:'html':'UTF-8'}"
    data-message="{$tec_spamguard_admin_message|escape:'html':'UTF-8'}"
></div>

{literal}
<style>
    .tec-spamguard-admin-login-captcha {
        margin: 16px auto;
        text-align: center;
        width: 100%;
    }

    .tec-spamguard-admin-login-captcha .tec-spamguard-admin-message {
        color: #666;
        font-size: 12px;
        line-height: 1.4;
        margin: 0 0 8px;
    }

    .tec-spamguard-admin-login-captcha .tec-spamguard-altcha-frame {
        align-items: center;
        background: #f8f8f8;
        border: 2px solid #cccccc;
        border-radius: 6px;
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-left: auto;
        margin-right: auto;
        max-width: 390px;
        padding: 10px 12px;
        width: 100%;
    }

    .tec-spamguard-admin-login-captcha .tec-spamguard-altcha-frame > altcha-widget {
        flex: 1 1 auto;
        min-width: 0;
        --altcha-border-color: transparent;
        --altcha-border-width: 0;
        --altcha-checkbox-border-color: #111;
        --altcha-checkbox-border-width: 2px;
        --altcha-checkbox-size: 24px;
        --altcha-color-base: transparent;
        --altcha-padding: 0;
    }

    .tec-spamguard-admin-login-captcha .tec-spamguard-altcha-local-logo {
        flex: 0 0 auto;
        height: 64px;
        object-fit: contain;
        width: 64px;
    }

    @media (max-width: 420px) {
        .tec-spamguard-admin-login-captcha .tec-spamguard-altcha-frame {
            gap: 8px;
            max-width: 100%;
        }
    }

    .tec-spamguard-admin-login-captcha[data-fallback-required="1"] {
        margin-top: 10px;
    }
</style>

<script>
(function () {
    'use strict';

    var mount = document.getElementById('tec-spamguard-admin-login-captcha');
    var form;
    var submit;

    if (!mount) {
        return;
    }

    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function appendHidden(name, value) {
        var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value || '';
    }

    function getResponseValue(name) {
        var input = name ? form.querySelector('input[name="' + name + '"], textarea[name="' + name + '"], select[name="' + name + '"]') : null;

        return input ? String(input.value || input.getAttribute('value') || '') : '';
    }

    function dataHasAdminLoginSubmit(data) {
        if (!data) {
            return false;
        }
        if (typeof data === 'string') {
            return data.indexOf('controller=AdminLogin') !== -1 && data.indexOf('submitLogin=') !== -1;
        }

        return data.controller === 'AdminLogin' && data.submitLogin !== undefined;
    }

    function appendCaptchaToAjaxData(data, name, value) {
        var separator;

        if (!name || !value) {
            return data;
        }
        if (typeof data === 'string') {
            separator = data === '' ? '' : '&';

            return data + separator + encodeURIComponent(name) + '=' + encodeURIComponent(value);
        }
        if (data && typeof data === 'object') {
            data[name] = value;
        }

        return data;
    }

    function getCaptchaAjaxFields(responseField, recaptchaV3ResponseField) {
        var fallback = parseJson(mount.getAttribute('data-fallback'), null);
        var fields = [responseField, recaptchaV3ResponseField];

        if (fallback && fallback.responseField) {
            fields.push(fallback.responseField);
        }

        return fields.filter(function (field, index) {
            return field && fields.indexOf(field) === index;
        });
    }

    function bindAdminLoginAjaxPayload(responseField) {
        var recaptchaV3ResponseField = mount.getAttribute('data-recaptcha-v3-response-field') || '';
        var originalAjax;

        if (!window.jQuery || !window.jQuery.ajax || window.jQuery.ajax.tecSpamGuardAdminCaptcha === true) {
            bindAdminLoginFunction(responseField, recaptchaV3ResponseField);

            return;
        }

        originalAjax = window.jQuery.ajax;
        window.jQuery.ajax = function (options) {
            var fields = getCaptchaAjaxFields(responseField, recaptchaV3ResponseField);

            if (options && dataHasAdminLoginSubmit(options.data)) {
                fields.forEach(function (field) {
                    options.data = appendCaptchaToAjaxData(options.data, field, getResponseValue(field));
                });
            }

            return originalAjax.apply(this, arguments);
        };
        window.jQuery.ajax.tecSpamGuardAdminCaptcha = true;
        bindAdminLoginFunction(responseField, recaptchaV3ResponseField);
    }

    function bindAdminLoginFunction(responseField, recaptchaV3ResponseField) {
        if (!window.jQuery || typeof window.doAjaxLogin !== 'function' || window.doAjaxLogin.tecSpamGuardAdminCaptcha === true) {
            return;
        }

        window.doAjaxLogin = function (redirect) {
            var fields = getCaptchaAjaxFields(responseField, recaptchaV3ResponseField);
            var data = {
                ajax: '1',
                token: '',
                controller: 'AdminLogin',
                submitLogin: '1',
                passwd: window.jQuery('#passwd').val(),
                email: window.jQuery('#email').val(),
                redirect: redirect,
                stay_logged_in: window.jQuery('#stay_logged_in:checked').val()
            };

            fields.forEach(function (field) {
                data = appendCaptchaToAjaxData(data, field, getResponseValue(field));
            });
            window.jQuery('#error').hide();
            window.jQuery('#login_form').fadeIn('slow', function () {
                window.jQuery.ajax({
                    type: 'POST',
                    headers: {'cache-control': 'no-cache'},
                    url: 'index.php?rand=' + new Date().getTime(),
                    async: true,
                    dataType: 'json',
                    data: data,
                    beforeSend: function () {
                        if (typeof window.feedbackSubmit === 'function') {
                            window.feedbackSubmit();
                        }
                        if (window.l && typeof window.l.start === 'function') {
                            window.l.start();
                        }
                    },
                    success: function (jsonData) {
                        if (jsonData.hasErrors) {
                            if (typeof window.displayErrors === 'function') {
                                window.displayErrors(jsonData.errors);
                            }
                            if (window.l && typeof window.l.stop === 'function') {
                                window.l.stop();
                            }
                        } else {
                            window.location.assign(jsonData.redirect);
                        }
                    },
                    error: function (XMLHttpRequest, textStatus) {
                        if (window.l && typeof window.l.stop === 'function') {
                            window.l.stop();
                        }
                        window.jQuery('#error')
                            .html('<h3>TECHNICAL ERROR:</h3><p>Details: Error thrown: ' + XMLHttpRequest + '</p><p>Text status: ' + textStatus + '</p>')
                            .removeClass('hide');
                        window.jQuery('#login_form').fadeOut('slow');
                    }
                });
            });
        };
        window.doAjaxLogin.tecSpamGuardAdminCaptcha = true;
    }

    function setAltchaSolved(name, payload) {
        appendHidden(name, payload || '');
        form.setAttribute('data-tec-spamguard-admin-captcha-solved', payload ? '1' : '0');
    }

    function refreshAltchaChallenge(widget, siteKey) {
        var challengeUrl;

        try {
            challengeUrl = new URL(siteKey, window.location.href);
            challengeUrl.searchParams.set('_tec_spamguard_challenge', String(Date.now()) + String(Math.floor(Math.random() * 1000000)));
            widget.setAttribute('challenge', challengeUrl.toString());
        } catch (error) {
            widget.setAttribute('challenge', siteKey);
        }
    }

    function resetAltchaWidget(widget, siteKey) {
        refreshAltchaChallenge(widget, siteKey);
        if (typeof widget.reset === 'function') {
            try {
                widget.reset();
            } catch (error) {
                // Provider reset is best-effort after an invalid attempt.
            }
        }
    }

    function findSubmit() {
        return form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
    }

    function moveMountIntoForm() {
        form = document.getElementById('login_form') || document.querySelector('form[action][method="post"]');
        if (!form) {
            return false;
        }

        submit = findSubmit();
        if (submit && submit.parentNode) {
            submit.parentNode.insertBefore(mount, submit);
        } else {
            form.appendChild(mount);
        }

        return true;
    }

    function registerAltchaI18n() {
        var config = parseJson(mount.getAttribute('data-altcha-i18n'), {});
        var language = config.language || '';
        var strings = config.strings || {};
        var baseStrings;

        if (!language || !window.$altcha || !window.$altcha.i18n || typeof window.$altcha.i18n.set !== 'function') {
            return;
        }

        baseStrings = typeof window.$altcha.i18n.get === 'function' ? window.$altcha.i18n.get('en') : {};
        window.$altcha.i18n.set(language, Object.assign({}, baseStrings || {}, strings));
    }

    function renderRecaptchaV2(siteKey) {
        var widget = document.createElement('div');
        widget.className = 'g-recaptcha';
        widget.setAttribute('data-sitekey', siteKey);
        mount.appendChild(widget);
        if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
            try {
                window.grecaptcha.render(widget, {sitekey: siteKey});
            } catch (error) {
                // Provider may already have rendered this element.
            }
        }
    }

    function renderTurnstile(siteKey) {
        var widget = document.createElement('div');
        widget.className = 'cf-turnstile';
        widget.setAttribute('data-sitekey', siteKey);
        mount.appendChild(widget);
        if (window.turnstile && typeof window.turnstile.render === 'function') {
            try {
                window.turnstile.render(widget, {sitekey: siteKey});
            } catch (error) {
                // Provider may already have rendered this element.
            }
        }
    }

    function renderAltcha(provider, siteKey, responseField) {
        var frame;
        var widget;
        var logo;
        var attributes = parseJson(mount.getAttribute('data-widget-attributes'), {});

        if (window.customElements && !window.customElements.get('altcha-widget')) {
            window.customElements.whenDefined('altcha-widget').then(function () {
                renderAltcha(provider, siteKey, responseField);
            });
            return;
        }

        registerAltchaI18n();
        widget = document.createElement('altcha-widget');
        refreshAltchaChallenge(widget, siteKey);
        widget.setAttribute('name', responseField);
        if (parseJson(mount.getAttribute('data-altcha-i18n'), {}).language) {
            widget.setAttribute('language', parseJson(mount.getAttribute('data-altcha-i18n'), {}).language);
        }
        widget.setAttribute('configuration', JSON.stringify(attributes || {}));
        widget.addEventListener('verified', function (event) {
            if (event.detail && event.detail.payload) {
                setAltchaSolved(responseField, event.detail.payload);
            }
        });
        widget.addEventListener('expired', function () {
            setAltchaSolved(responseField, '');
            resetAltchaWidget(widget, siteKey);
        });
        widget.addEventListener('statechange', function (event) {
            if (!event.detail) {
                return;
            }
            if (event.detail.payload && event.detail.state === 'verified') {
                setAltchaSolved(responseField, event.detail.payload);
            }
            if (event.detail.state === 'error') {
                setAltchaSolved(responseField, '');
                window.setTimeout(function () {
                    resetAltchaWidget(widget, siteKey);
                }, 500);
            }
        });
        form.addEventListener('submit', function () {
            if (form.getAttribute('data-tec-spamguard-admin-captcha-solved') !== '1') {
                setAltchaSolved(responseField, '');
            }
        });

        if (provider === 'altcha') {
            frame = document.createElement('div');
            frame.className = 'tec-spamguard-altcha-frame';
            logo = document.createElement('img');
            logo.className = 'tec-spamguard-altcha-local-logo';
            logo.src = mount.getAttribute('data-logo-url') || '';
            logo.alt = mount.getAttribute('data-logo-alt') || 'Protected by Tec Spam Guard';
            logo.loading = 'lazy';
            frame.appendChild(widget);
            frame.appendChild(logo);
            mount.appendChild(frame);
            return;
        }

        mount.appendChild(widget);
    }

    function renderFallbackCaptcha(fallback) {
        var messageNode;

        if (!fallback || !fallback.provider || !fallback.siteKey || !fallback.responseField) {
            return;
        }
        if (mount.getAttribute('data-fallback-required') === '1') {
            return;
        }

        mount.setAttribute('data-fallback-required', '1');
        if (fallback.message) {
            messageNode = document.createElement('p');
            messageNode.className = 'tec-spamguard-admin-message';
            messageNode.textContent = fallback.message;
            mount.appendChild(messageNode);
        }

        if (fallback.provider === 'recaptcha_v2') {
            renderRecaptchaV2(fallback.siteKey);
        } else if (fallback.provider === 'turnstile') {
            renderTurnstile(fallback.siteKey);
        } else if (fallback.provider === 'altcha' || fallback.provider === 'altcha_sentinel') {
            mount.setAttribute('data-widget-attributes', JSON.stringify(fallback.widgetAttributes || {}));
            mount.setAttribute('data-logo-url', fallback.moduleLogoUrl || mount.getAttribute('data-logo-url') || '');
            mount.setAttribute('data-logo-alt', fallback.moduleLogoAlt || mount.getAttribute('data-logo-alt') || '');
            mount.setAttribute('data-altcha-i18n', JSON.stringify(fallback.altchaI18n || {}));
            renderAltcha(fallback.provider, fallback.siteKey, fallback.responseField);
        }
    }

    function precheckRecaptchaV3(token) {
        var precheckUrl = mount.getAttribute('data-recaptcha-precheck-url') || '';
        var body = 'token=' + encodeURIComponent(token || '');

        if (!precheckUrl || !window.fetch) {
            return Promise.resolve({
                success: false,
                fallback: false
            });
        }

        return window.fetch(precheckUrl, {
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

    function bindRecaptchaV3(siteKey, responseField) {
        var action = mount.getAttribute('data-recaptcha-action') || 'tec_spamguard';
        var ready = false;
        var fallback = parseJson(mount.getAttribute('data-fallback'), null);

        function submitWithFreshToken() {
            window.grecaptcha.execute(siteKey, {action: action}).then(function (freshToken) {
                appendHidden(responseField, freshToken);
                ready = true;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submit || undefined);
                } else {
                    form.submit();
                }
            });
        }

        function submitWithCurrentToken() {
            ready = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submit || undefined);
            } else {
                form.submit();
            }
        }

        form.addEventListener('submit', function (event) {
            if (ready) {
                ready = false;
                return;
            }
            if (mount.getAttribute('data-fallback-required') === '1') {
                if (fallback && getResponseValue(fallback.responseField)) {
                    ready = true;
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
                window.grecaptcha.execute(siteKey, {action: action}).then(function (token) {
                    appendHidden(responseField, token);
                    if (!fallback) {
                        submitWithCurrentToken();

                        return;
                    }

                    precheckRecaptchaV3(token).then(function (result) {
                        if (result && result.success) {
                            submitWithFreshToken();

                            return;
                        }

                        if (result && result.fallback) {
                            renderFallbackCaptcha(fallback);

                            return;
                        }

                        submitWithFreshToken();
                    });
                });
            });
        }, true);
    }

    function render() {
        var provider = mount.getAttribute('data-provider') || '';
        var siteKey = mount.getAttribute('data-site-key') || '';
        var responseField = mount.getAttribute('data-response-field') || '';
        var message = mount.getAttribute('data-message') || '';
        var messageNode;

        if (!moveMountIntoForm() || !siteKey || !responseField) {
            return;
        }

        bindAdminLoginAjaxPayload(responseField);

        if (message && provider !== 'recaptcha_v3') {
            messageNode = document.createElement('p');
            messageNode.className = 'tec-spamguard-admin-message';
            messageNode.textContent = message;
            mount.appendChild(messageNode);
        }

        if (provider === 'recaptcha_v2') {
            renderRecaptchaV2(siteKey);
        } else if (provider === 'turnstile') {
            renderTurnstile(siteKey);
        } else if (provider === 'altcha' || provider === 'altcha_sentinel') {
            renderAltcha(provider, siteKey, responseField);
        } else if (provider === 'recaptcha_v3') {
            bindRecaptchaV3(siteKey, mount.getAttribute('data-recaptcha-v3-response-field') || responseField);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', render);
    } else {
        render();
    }
}());
</script>
{/literal}
