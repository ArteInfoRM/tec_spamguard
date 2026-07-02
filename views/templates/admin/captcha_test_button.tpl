{*
* 2009-2026 Tecnoacquisti.com
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   MIT License
*}

<div class="form-group">
    <div class="col-lg-9 col-lg-offset-3">
        <button
            type="button"
            id="tec-spamguard-captcha-test-btn"
            class="btn btn-default"
            data-url="{$tec_spamguard_captcha_test_url|escape:'html':'UTF-8'}"
            data-running="{$tec_spamguard_captcha_test_running|escape:'html':'UTF-8'}"
        >
            <i class="icon icon-key"></i>
            {$tec_spamguard_captcha_test_label|escape:'html':'UTF-8'}
        </button>
        <span id="tec-spamguard-captcha-test-result" class="tec-spamguard-captcha-test-result"></span>
    </div>
</div>

<script>
(function () {
    'use strict';

    var btn = document.getElementById('tec-spamguard-captcha-test-btn');
    if (!btn) {
        return;
    }

    var res = document.getElementById('tec-spamguard-captcha-test-result');
    var form = btn.closest('form');
    var provider = form ? form.querySelector('[name=TEC_SPAMGUARD_CAPTCHA_PROVIDER]') : null;

    function fieldValue(name) {
        var field = form ? form.querySelector('[name=' + name + ']') : null;

        return field ? field.value : '';
    }

    function toggleButton() {
        var providerValue = provider ? provider.value : 'none';
        var btnRow = btn.closest('.form-group');
        if (btnRow) {
            btnRow.style.display = providerValue === 'none' ? 'none' : '';
        }
    }

    function buildAjaxUrl(baseUrl) {
        var url = new URL(baseUrl || window.location.href, window.location.href);
        url.hash = '';
        url.searchParams.set('ajax', '1');
        url.searchParams.set('action', 'testCaptchaKeys');

        return url.toString();
    }

    function getAjaxUrls() {
        var urls = [];
        var sources = [
            window.location.href,
            form ? form.getAttribute('action') : '',
            btn.dataset.url
        ];

        sources.forEach(function (source) {
            var url;
            if (!source) {
                return;
            }
            url = buildAjaxUrl(source);
            if (urls.indexOf(url) === -1) {
                urls.push(url);
            }
        });

        return urls;
    }

    function parseJsonResponse(response, text, urls, index, payload) {
        var data;
        try {
            data = JSON.parse(text);
        } catch (error) {
            if (index + 1 < urls.length) {
                return requestJson(urls, index + 1, payload);
            }
            var message = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            throw new Error(message ? message.substring(0, 180) : error.message);
        }

        if (!response.ok) {
            if (index + 1 < urls.length) {
                return requestJson(urls, index + 1, payload);
            }
            throw new Error(data && data.message ? data.message : 'HTTP ' + response.status);
        }

        return data;
    }

    function requestJson(urls, index, payload) {
        return fetch(urls[index], {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.text().then(function (text) {
                return parseJsonResponse(response, text, urls, index, payload);
            });
        });
    }

    if (provider) {
        provider.addEventListener('change', toggleButton);
        toggleButton();
    }

    btn.addEventListener('click', function () {
        var originalHtml = btn.innerHTML;
        var providerValue = provider ? provider.value : 'none';
        var siteName = {
            recaptcha_v2: 'TEC_SPAMGUARD_RECAPTCHA_V2_SITEKEY',
            recaptcha_v3: 'TEC_SPAMGUARD_RECAPTCHA_V3_SITEKEY',
            turnstile: 'TEC_SPAMGUARD_TURNSTILE_SITEKEY',
            altcha_sentinel: 'TEC_SPAMGUARD_ALTCHA_SENTINEL_URL'
        }[providerValue];
        var secretName = {
            recaptcha_v2: 'TEC_SPAMGUARD_RECAPTCHA_V2_SECRET',
            recaptcha_v3: 'TEC_SPAMGUARD_RECAPTCHA_V3_SECRET',
            turnstile: 'TEC_SPAMGUARD_TURNSTILE_SECRET',
            altcha: 'TEC_SPAMGUARD_ALTCHA_SECRET',
            altcha_sentinel: 'TEC_SPAMGUARD_ALTCHA_SENTINEL_API_KEY'
        }[providerValue];
        var body = new URLSearchParams();
        var payload;

        btn.disabled = true;
        btn.innerHTML = btn.dataset.running;
        res.innerHTML = '';

        body.append('provider', providerValue);
        body.append('ajax', '1');
        body.append('action', 'testCaptchaKeys');
        if (siteName) {
            body.append('sitekey', fieldValue(siteName));
        }
        if (secretName) {
            body.append('secret', fieldValue(secretName));
        }
        payload = body.toString();

        requestJson(getAjaxUrls(), 0, payload).then(function (data) {
            var colorClass = data && data.success ? 'tec-spamguard-captcha-test-success' : 'tec-spamguard-captcha-test-error';
            var message = data && data.message ? data.message : 'Error';
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            res.innerHTML = '<span class="' + colorClass + '"></span>';
            res.firstChild.textContent = message;
        }).catch(function (error) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            res.innerHTML = '<span class="tec-spamguard-captcha-test-error"></span>';
            res.firstChild.textContent = error.message;
        });
    });
}());
</script>
