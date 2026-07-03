<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Back-office controller for failed validation logs.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminTecSpamGuardValidationLogsController extends ModuleAdminController
{
    private const VALIDATION_LOG_TABLE = 'tec_spamguard_validation_log';

    /**
     * Admin controller constructor.
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = self::VALIDATION_LOG_TABLE;
        $this->identifier = 'id_tec_spamguard_validation_log';
        $this->list_id = self::VALIDATION_LOG_TABLE;
        $this->_orderBy = $this->identifier;
        $this->_orderWay = 'DESC';

        parent::__construct();
        $module = $this->getTecSpamGuardModule();

        $this->fields_list = [
            'id_tec_spamguard_validation_log' => [
                'title' => $module->l('ID', 'AdminTecSpamGuardValidationLogs'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'orderby' => true,
            ],
            'ip_address' => [
                'title' => $module->l('IP', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!ip_address',
                'orderby' => false,
            ],
            'location' => [
                'title' => $module->l('Location', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!location',
                'orderby' => false,
            ],
            'validation_result' => [
                'title' => $module->l('Result', 'AdminTecSpamGuardValidationLogs'),
                'type' => 'select',
                'list' => [
                    'failed' => $module->l('Failed', 'AdminTecSpamGuardValidationLogs'),
                    'issued' => $module->l('Issued', 'AdminTecSpamGuardValidationLogs'),
                    'passed' => $module->l('Passed', 'AdminTecSpamGuardValidationLogs'),
                ],
                'filter_key' => 'a!validation_result',
                'filter_type' => 'string',
                'orderby' => false,
            ],
            'issued_location' => [
                'title' => $module->l('Captcha issued at', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!issued_location',
                'orderby' => false,
            ],
            'passed_location' => [
                'title' => $module->l('Passed at', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!passed_location',
                'orderby' => false,
            ],
            'captcha_location' => [
                'title' => $module->l('Captcha failed at', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!captcha_location',
                'orderby' => false,
            ],
            'email_location' => [
                'title' => $module->l('Email validation failed at', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!email_location',
                'orderby' => false,
            ],
            'attempted_email' => [
                'title' => $module->l('Attempted email', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!attempted_email',
                'orderby' => false,
            ],
            'message_location' => [
                'title' => $module->l('Message validation failed at', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!message_location',
                'orderby' => false,
            ],
            'user_agent' => [
                'title' => $module->l('User Agent', 'AdminTecSpamGuardValidationLogs'),
                'filter_key' => 'a!user_agent',
                'orderby' => false,
            ],
            'date_add' => [
                'title' => $module->l('Date', 'AdminTecSpamGuardValidationLogs'),
                'type' => 'datetime',
                'filter_key' => 'a!date_add',
                'orderby' => true,
            ],
        ];

        $this->actions = [];
        $this->bulk_actions = [];
    }

    /**
     * Add toolbar links.
     *
     * @return void
     */
    public function initPageHeaderToolbar()
    {
        $module = $this->getTecSpamGuardModule();
        $configureButton = $this->getConfigureToolbarButton($module);

        parent::initPageHeaderToolbar();
        unset($this->page_header_toolbar_btn['new']);

        $this->page_header_toolbar_btn['configure'] = $configureButton;
    }

    /**
     * Remove default creation toolbar buttons from the read-only log list.
     *
     * @return void
     */
    public function initToolbar()
    {
        $module = $this->getTecSpamGuardModule();
        $configureButton = $this->getConfigureToolbarButton($module);

        parent::initToolbar();
        unset($this->toolbar_btn['new']);

        $this->toolbar_btn['configure'] = $configureButton;
    }

    /**
     * Return the configuration toolbar button definition.
     *
     * @param Module $module Module instance
     *
     * @return array
     */
    private function getConfigureToolbarButton(Module $module)
    {
        return [
            'href' => $this->context->link->getAdminLink('AdminModules')
                . '&configure=' . $module->name
                . '&tab_module=' . $module->tab
                . '&module_name=' . $module->name,
            'desc' => $module->l('Configure module', 'AdminTecSpamGuardValidationLogs'),
            'icon' => 'process-icon-configure',
        ];
    }

    /**
     * Return the loaded Tec Spam Guard module instance.
     *
     * @return Module
     */
    private function getTecSpamGuardModule()
    {
        if ($this->module instanceof Module && $this->module->name === 'tec_spamguard') {
            return $this->module;
        }

        $module = Module::getInstanceByName('tec_spamguard');
        if ($module instanceof Module && $module->name === 'tec_spamguard') {
            $this->module = $module;

            return $module;
        }

        throw new PrestaShopException('Tec Spam Guard module is not available.');
    }
}
