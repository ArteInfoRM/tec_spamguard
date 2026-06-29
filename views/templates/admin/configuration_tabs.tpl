{*
* 2009-2026 Tecnoacquisti.com
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
*}

<div class="panel tec-spamguard-config-panel">
    <div class="tec-spamguard-config-header">
        <div class="tec-spamguard-config-title">
            <img
                src="{$module_dir|escape:'html':'UTF-8'}logo.png"
                alt="{$module_display_name|escape:'html':'UTF-8'}"
                class="tec-spamguard-config-logo"
            >
            <h2 class="tec-spamguard-config-name">
                {$module_display_name|escape:'html':'UTF-8'}
            </h2>
        </div>
    </div>

    <ul class="nav nav-tabs tec-spamguard-tabs" role="tablist">
        {foreach from=$tec_spamguard_config_tabs item=tab}
            <li{if $tab.active} class="active"{/if}>
                <a href="#tec-spamguard-{$tab.id|escape:'html':'UTF-8'}" role="tab" data-toggle="tab">
                    <i class="{$tab.icon|escape:'html':'UTF-8'}"></i>
                    {$tab.title|escape:'html':'UTF-8'}
                </a>
            </li>
        {/foreach}
    </ul>

    <div class="tab-content panel tec-spamguard-tab-content">
        {foreach from=$tec_spamguard_config_tabs item=tab}
            <div
                class="tab-pane{if $tab.active} active{/if}"
                id="tec-spamguard-{$tab.id|escape:'html':'UTF-8'}"
            >
                {$tab.form nofilter}
            </div>
        {/foreach}
    </div>
</div>

{$tec_spamguard_credits nofilter}

<script>
(function () {
    'use strict';

    if (document.body) {
        document.body.classList.add('tec-spamguard-config-page');
        {if $is_ps9}
        document.body.classList.add('tec-spamguard-ps9-config-page');
        {/if}
    }

    var provider = document.querySelector('[name=TEC_SPAMGUARD_CAPTCHA_PROVIDER]');
    var altchaSecret = document.querySelector('[name=TEC_SPAMGUARD_ALTCHA_SECRET]');
    var groups = {
        recaptcha_v2: [
            'TEC_SPAMGUARD_RECAPTCHA_V2_SITEKEY',
            'TEC_SPAMGUARD_RECAPTCHA_V2_SECRET'
        ],
        recaptcha_v3: [
            'TEC_SPAMGUARD_RECAPTCHA_V3_SITEKEY',
            'TEC_SPAMGUARD_RECAPTCHA_V3_SECRET',
            'TEC_SPAMGUARD_RECAPTCHA_V3_ACTION',
            'TEC_SPAMGUARD_RECAPTCHA_V3_MIN_SCORE'
        ],
        turnstile: [
            'TEC_SPAMGUARD_TURNSTILE_SITEKEY',
            'TEC_SPAMGUARD_TURNSTILE_SECRET'
        ],
        altcha: [
            'TEC_SPAMGUARD_ALTCHA_SECRET',
            'TEC_SPAMGUARD_ALTCHA_DIFFICULTY',
            'TEC_SPAMGUARD_ALTCHA_EXPIRES_SECONDS',
            'TEC_SPAMGUARD_ALTCHA_HIDE_FOOTER',
            'TEC_SPAMGUARD_ALTCHA_HIDE_LOGO'
        ],
        altcha_sentinel: [
            'TEC_SPAMGUARD_ALTCHA_SENTINEL_URL',
            'TEC_SPAMGUARD_ALTCHA_SENTINEL_API_KEY',
            'TEC_SPAMGUARD_ALTCHA_HIDE_FOOTER',
            'TEC_SPAMGUARD_ALTCHA_HIDE_LOGO'
        ]
    };

    function setRow(name, visible) {
        var el = document.querySelector('[name=' + name + ']');
        var row = el ? el.closest('.form-group') : null;
        if (row) {
            row.style.display = visible ? '' : 'none';
        }
    }

    function randomHex(bytes) {
        var values = [];
        var i;

        if (window.crypto && window.crypto.getRandomValues) {
            var random = new Uint8Array(bytes);
            window.crypto.getRandomValues(random);
            for (i = 0; i < random.length; i += 1) {
                values.push(('0' + random[i].toString(16)).slice(-2));
            }

            return values.join('');
        }

        for (i = 0; i < bytes; i += 1) {
            values.push(('0' + Math.floor(Math.random() * 256).toString(16)).slice(-2));
        }

        return values.join('');
    }

    function addAltchaSecretButton() {
        var wrapper;
        var buttonWrapper;
        var generateBtn;

        if (!altchaSecret || document.getElementById('tec-spamguard-altcha-generate-secret-btn')) {
            return;
        }

        wrapper = document.createElement('div');
        wrapper.className = 'input-group';
        altchaSecret.parentNode.insertBefore(wrapper, altchaSecret);
        wrapper.appendChild(altchaSecret);

        buttonWrapper = document.createElement('span');
        buttonWrapper.className = 'input-group-btn';

        generateBtn = document.createElement('button');
        generateBtn.type = 'button';
        generateBtn.id = 'tec-spamguard-altcha-generate-secret-btn';
        generateBtn.className = 'btn btn-default';
        generateBtn.textContent = '{l s='Generate secret' mod='tec_spamguard' js=1}';
        generateBtn.addEventListener('click', function () {
            altchaSecret.value = randomHex(32);
        });

        buttonWrapper.appendChild(generateBtn);
        wrapper.appendChild(buttonWrapper);
    }

    function toggleProviderFields() {
        var providerValue = provider ? provider.value : 'none';
        var visibility = {};

        Object.keys(groups).forEach(function (key) {
            groups[key].forEach(function (name) {
                visibility[name] = visibility[name] || key === providerValue;
            });
        });
        Object.keys(visibility).forEach(function (name) {
            setRow(name, visibility[name]);
        });
    }

    if (provider) {
        addAltchaSecretButton();
        provider.addEventListener('change', toggleProviderFields);
        toggleProviderFields();
    }
}());
</script>
