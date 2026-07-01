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
