<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade module to 1.2.0.
 *
 * @param Tec_spamguard $module Module instance
 *
 * @return bool
 */
function upgrade_module_1_2_0($module)
{
    unset($module);

    $success = Configuration::get('TEC_SPAMGUARD_B2BREQUEST_PROTECTION') !== false
        || Configuration::updateValue('TEC_SPAMGUARD_B2BREQUEST_PROTECTION', 0);
    $success = (Configuration::get('TEC_SPAMGUARD_NEWSLETTER_CAPTCHA') !== false
        || Configuration::updateValue('TEC_SPAMGUARD_NEWSLETTER_CAPTCHA', 0)) && $success;
    $success = (Configuration::get('TEC_SPAMGUARD_NEWSLETTER_EMAIL') !== false
        || Configuration::updateValue('TEC_SPAMGUARD_NEWSLETTER_EMAIL', 0)) && $success;

    Configuration::deleteByName('TEC_SPAMGUARD_B2BREQUEST_REGISTER_PROTECTION');
    Configuration::deleteByName('TEC_SPAMGUARD_B2BREQUEST_LOGIN_PROTECTION');

    return $success;
}
