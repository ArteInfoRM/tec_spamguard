<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Front controller that pre-checks reCAPTCHA v3 tokens before final submit.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Tec_SpamguardrecaptchaprecheckModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $ssl = true;

    /**
     * Return the reCAPTCHA v3 pre-check result as JSON.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow', true);

        if (!Tools::getIsset('token')) {
            http_response_code(400);
            echo json_encode(['success' => false, 'fallback' => false]);
            exit;
        }

        $module = Module::getInstanceByName('tec_spamguard');
        if (!$module instanceof Tec_spamguard) {
            http_response_code(404);
            echo json_encode(['success' => false, 'fallback' => false]);
            exit;
        }

        echo json_encode($module->precheckRecaptchaV3Token((string) Tools::getValue('token')));
        exit;
    }
}
