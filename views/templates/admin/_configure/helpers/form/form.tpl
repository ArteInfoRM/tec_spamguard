{*
 * 2009-2026 Tecnoacquisti.com
 * @license MIT License
 *}
{extends file="helpers/form/form.tpl"}

{block name="legend"}
    {if $submit_action == 'submitTecSpamGuardAdvanced'}
        <div class="panel-heading tec-spamguard-advanced-heading">
            <span><i class="icon-shield"></i> {$field.title|escape:'html':'UTF-8'}</span>
            <div class="tec-spamguard-advanced-actions">
                <button type="button" class="btn btn-default" data-sg-random-field
                    title="{l s='Save the settings to apply your changes.' mod='tec_spamguard'}">
                    {l s='Choose a random field name' mod='tec_spamguard'}
                </button>
                <button type="button" class="btn btn-default" data-sg-reset-defaults
                    data-sg-defaults="{$sg_advanced_defaults_json|escape:'html':'UTF-8'}"
                    title="{l s='Restore this form to installation defaults. Save to apply your changes.' mod='tec_spamguard'}">
                    {l s='Reset to defaults' mod='tec_spamguard'}
                </button>
            </div>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
