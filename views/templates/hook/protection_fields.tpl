{*
 * 2009-2026 Tecnoacquisti.com
 * @license MIT License
 *}
<div hidden aria-hidden="true" style="display:none!important">
    <label>{l s='Leave this field empty' mod='tec_spamguard'}
        <input type="text" name="{$sg_guard_field|escape:'html':'UTF-8'}" value="" tabindex="-1" autocomplete="off" maxlength="255">
    </label>
</div>
<input type="hidden" name="tec_sg_proof" value="{$sg_guard_proof|escape:'html':'UTF-8'}">
