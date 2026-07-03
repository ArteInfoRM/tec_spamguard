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
 * Upgrade module to 1.0.8.
 *
 * @param Tec_spamguard $module Module instance
 *
 * @return bool
 */
function upgrade_module_1_0_8($module)
{
    if (Configuration::get('TEC_SPAMGUARD_LOG_FAILED_VALIDATIONS') === false) {
        Configuration::updateValue('TEC_SPAMGUARD_LOG_FAILED_VALIDATIONS', 0);
    }
    if (Configuration::get('TEC_SPAMGUARD_LOG_ISSUED_CAPTCHAS') === false) {
        Configuration::updateValue('TEC_SPAMGUARD_LOG_ISSUED_CAPTCHAS', 0);
    }
    if (Configuration::get('TEC_SPAMGUARD_LOG_PASSED_VALIDATIONS') === false) {
        Configuration::updateValue('TEC_SPAMGUARD_LOG_PASSED_VALIDATIONS', 0);
    }
    if (Configuration::get('TEC_SPAMGUARD_LOG_RETENTION_DAYS') === false) {
        Configuration::updateValue('TEC_SPAMGUARD_LOG_RETENTION_DAYS', Tec_spamguard::VALIDATION_LOG_RETENTION_DEFAULT_DAYS);
    }

    return $module->installValidationLogSchema()
        && $module->installAdminTab();
}
