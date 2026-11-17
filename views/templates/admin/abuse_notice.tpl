{*
 * 2009-2026 Tecnoacquisti.com
 * @license MIT License
 *}
{if $sg_waf_abuse_active}
    <div class="alert alert-info">
        {l s='AbuseIPDB is enabled in the Tec Block AI Bots WAF. Tec Spam Guard will not make its own AbuseIPDB requests while this WAF option is active for the current shop.' mod='tec_spamguard'}
    </div>
{/if}
