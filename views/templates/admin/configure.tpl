{*
* 2009-2026 Tecnoacquisti.com
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   MIT License
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
        <a
            class="btn btn-default"
            href="{$module_dir|escape:'html':'UTF-8'}data/disposable_domains.txt"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="icon-list"></i>
            {l s='Open disposable email domain list' mod='tec_spamguard'}
        </a>
        <form
            method="post"
            action="{$disposable_domains_update_url|escape:'html':'UTF-8'}"
            style="display:inline;"
        >
            <button
                type="submit"
                name="submitTecSpamGuardDisposableDomainsUpdate"
                class="btn btn-default"
            >
                <i class="icon-refresh"></i>
                {l s='Update disposable email domain list' mod='tec_spamguard'}
            </button>
        </form>
    </div>

    <p class="help-block" style="margin-top:10px;">
        {l s='Disposable email domain source' mod='tec_spamguard'}:
        <code>{$disposable_domains_source_url|escape:'html':'UTF-8'}</code><br>
        {l s='Local disposable email domains' mod='tec_spamguard'}:
        {$disposable_domains_count|intval}<br>
        {l s='Disposable email domain backups' mod='tec_spamguard'}:
        {$disposable_domains_backup_count|intval} / {$disposable_domains_backup_limit|intval}
    </p>
    <p class="help-block">
        {l s='Download and replace the local disposable email domain list from the configured public source. A timestamped backup is created before writing the new file.' mod='tec_spamguard'}
    </p>
</div>
