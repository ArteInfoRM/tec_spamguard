{*
* 2009-2026 Tecnoacquisti.com
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
*}

<div class="panel">
    <h3><i class="icon-code"></i> {l s='Information' mod='tec_spamguard'}</h3>

    <p>
        <strong>{$module_display_name|escape:'html':'UTF-8'}</strong>
        {l s='version' mod='tec_spamguard'} {$module_version|escape:'html':'UTF-8'}
    </p>

    <p>
        {l s='This module protects contact, registration, login, and password reset forms from spam with configurable captcha, email validation, disposable email checks, and message validation rules.' mod='tec_spamguard'}
    </p>

    <ul>
        <li>{l s='Captcha service settings choose and configure the provider used by protected forms.' mod='tec_spamguard'}</li>
        <li>{l s='Captcha activation controls which front-office forms require a captcha challenge.' mod='tec_spamguard'}</li>
        <li>{l s='Email validation blocks invalid addresses, forbidden domains, and disposable email providers when enabled.' mod='tec_spamguard'}</li>
        <li>{l s='Message validation checks contact messages for blocked phrases and excessive links.' mod='tec_spamguard'}</li>
    </ul>

    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
        <a
            class="btn btn-default"
            href="{$module_dir|escape:'html':'UTF-8'}documentation/{$readme_html_file|escape:'html':'UTF-8'}"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="icon-book"></i>
            {l s='Open README' mod='tec_spamguard'}
        </a>
        <a
            class="btn btn-default"
            href="{$module_dir|escape:'html':'UTF-8'}documentation/CHANGELOG.html"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="icon-list-alt"></i>
            {l s='Open changelog' mod='tec_spamguard'}
        </a>
    </div>
</div>
