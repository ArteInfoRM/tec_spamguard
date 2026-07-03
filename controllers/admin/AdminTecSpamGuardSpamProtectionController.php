<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Back-office parent controller for Spam Protection menu.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Redirect the parent menu entry to the module configuration page.
 */
class AdminTecSpamGuardSpamProtectionController extends ModuleAdminController
{
    /**
     * Redirect to module configuration.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules') . '&configure=tec_spamguard'
        );
    }
}
