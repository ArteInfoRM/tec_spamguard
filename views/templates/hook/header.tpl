{*
* 2009-2026 Tecnoacquisti.com
*
* Captcha provider script loader.
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   MIT License
*}
{foreach from=$tec_spamguard_script_urls item=tec_spamguard_script}
    {if $tec_spamguard_script.is_module}
        <script src="{$tec_spamguard_script.url|escape:'html':'UTF-8'}" async defer type="module"></script>
    {else}
        <script src="{$tec_spamguard_script.url|escape:'html':'UTF-8'}" async defer></script>
    {/if}
{/foreach}
<script>
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }
        document.addEventListener('DOMContentLoaded', callback);
    }

    function shouldRepair(container, config) {
        var formType = container.getAttribute('data-form') || '';
        var widget = getDirectAltchaWidget(container);
        if (container.querySelector('.tec-spamguard-altcha-frame')) {
            return false;
        }
        if (!widget) {
            return false;
        }
        if (config.provider === 'altcha' && formType.indexOf('-fallback') === -1) {
            return true;
        }

        return !!(config.fallback
            && config.fallback.provider === 'altcha'
            && formType.indexOf('-fallback') !== -1);
    }

    function getDirectAltchaWidget(container) {
        var children = container.children || [];
        var i;

        for (i = 0; i < children.length; i += 1) {
            if (String(children[i].tagName || '').toLowerCase() === 'altcha-widget') {
                return children[i];
            }
        }

        return null;
    }

    function repairAltchaFrame(container, config) {
        var widget = getDirectAltchaWidget(container);
        var logoUrl = config.moduleLogoUrl || (config.fallback && config.fallback.moduleLogoUrl) || '';
        var logoAlt = config.moduleLogoAlt || (config.fallback && config.fallback.moduleLogoAlt) || 'Protected by Tec Spam Guard';
        var frame;
        var logo;

        if (!widget || !logoUrl) {
            return;
        }

        container.classList.add('tec-spamguard-altcha-local');
        frame = document.createElement('div');
        frame.className = 'tec-spamguard-altcha-frame';
        logo = document.createElement('img');
        logo.className = 'tec-spamguard-altcha-local-logo';
        logo.src = logoUrl;
        logo.alt = logoAlt;
        logo.loading = 'lazy';
        container.insertBefore(frame, widget);
        frame.appendChild(widget);
        frame.appendChild(logo);
    }

    function repairRenderedAltcha() {
        var config = window.tecSpamGuard || null;
        if (!config) {
            return;
        }

        Array.prototype.forEach.call(document.querySelectorAll('.tec-spamguard-widget'), function (container) {
            if (shouldRepair(container, config)) {
                repairAltchaFrame(container, config);
            }
        });
    }

    ready(function () {
        window.setTimeout(repairRenderedAltcha, 0);
        window.setTimeout(repairRenderedAltcha, 500);
        window.setTimeout(repairRenderedAltcha, 1500);
    });
}());
</script>
