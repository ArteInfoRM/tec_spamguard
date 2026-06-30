{*
* 2009-2026 Tecnoacquisti.com
*
* Captcha provider script loader.
*
* @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
* @copyright 2009-2026 Arte e Informatica
* @license   MIT License
*}
{if $tec_spamguard_script_url}
    {if $tec_spamguard_is_module_script}
        <script src="{$tec_spamguard_script_url|escape:'html':'UTF-8'}" async defer type="module"></script>
    {else}
        <script src="{$tec_spamguard_script_url|escape:'html':'UTF-8'}" async defer></script>
    {/if}
{/if}
