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
 * Upgrade module to 1.0.4.
 *
 * @param Tec_spamguard $module Module instance
 *
 * @return bool
 */
function upgrade_module_1_0_4($module)
{
    if (Configuration::get('TEC_SPAMGUARD_ADMIN_LOGIN_CAPTCHA') === false) {
        Configuration::updateValue('TEC_SPAMGUARD_ADMIN_LOGIN_CAPTCHA', 0);
    }

    return tec_spamguard_104_register_hook($module, 'displayAdminLogin')
        && tec_spamguard_104_register_hook($module, 'actionAdminLoginControllerLoginBefore');
}

/**
 * Register a hook and ensure the hook_module mapping exists.
 *
 * @param Tec_spamguard $module Module instance
 * @param string $hookName Hook name
 *
 * @return bool
 */
function tec_spamguard_104_register_hook($module, $hookName)
{
    $module->registerHook((string) $hookName);

    $idHook = (int) Hook::getIdByName((string) $hookName);
    $idModule = (int) Module::getModuleIdByName($module->name);
    if ($idHook <= 0 || $idModule <= 0) {
        return false;
    }

    $shopIds = Shop::getShops(false, null, true);
    if (!is_array($shopIds) || empty($shopIds)) {
        $shopIds = [1];
    }

    $ok = true;
    foreach ($shopIds as $idShop) {
        $exists = (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module`
             WHERE `id_hook` = ' . $idHook . '
               AND `id_module` = ' . $idModule . '
               AND `id_shop` = ' . (int) $idShop
        );
        if ($exists) {
            continue;
        }

        $ok = $ok && Db::getInstance()->insert('hook_module', [
            'id_module' => $idModule,
            'id_hook' => $idHook,
            'id_shop' => (int) $idShop,
            'position' => 1,
        ], false, true, Db::INSERT_IGNORE);
    }

    return (bool) $ok;
}
