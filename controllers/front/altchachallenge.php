<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Front controller that returns signed ALTCHA proof-of-work challenges.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Tec_SpamguardaltchachallengeModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow', true);
        http_response_code(200);

        $module = Module::getInstanceByName('tec_spamguard');
        if (!$module instanceof Tec_spamguard || !$module->isLocalAltchaActive()) {
            http_response_code(404);
            echo json_encode(['error' => 'ALTCHA provider is not active']);
            exit;
        }

        echo json_encode($module->createAltchaChallenge());
        exit;
    }
}
