<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Front controller that records captcha widgets displayed in visible forms.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Tec_SpamguardcaptchadisplayModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $ssl = true;

    /**
     * Record a captcha display event.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow', true);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false]);
            exit;
        }

        $module = Module::getInstanceByName('tec_spamguard');
        if (!$module instanceof Tec_spamguard) {
            http_response_code(404);
            echo json_encode(['success' => false]);
            exit;
        }

        $success = $module->logDisplayedCaptcha(
            (string) Tools::getValue('location'),
            (string) Tools::getValue('token')
        );
        http_response_code($success ? 200 : 400);
        echo json_encode(['success' => $success]);
        exit;
    }
}
