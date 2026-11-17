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

/** Install optional advanced protection without resetting existing configuration. */
function upgrade_module_1_3_0($module)
{
    // Existing installations opt in after checking their theme integrations.
    if (Configuration::get('TEC_SPAMGUARD_GUARD_HONEYPOT') === false
        && !Configuration::updateValue('TEC_SPAMGUARD_GUARD_HONEYPOT', 0)) {
        return false;
    }

    return $module->installAdvancedProtection();
}
