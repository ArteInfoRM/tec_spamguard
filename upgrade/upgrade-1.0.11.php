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
 * Upgrade module to 1.0.11.
 *
 * @param Tec_spamguard $module Module instance
 *
 * @return bool
 */
function upgrade_module_1_0_11($module)
{
    return $module->installAltchaReplaySchema();
}
