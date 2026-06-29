<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Front-office form spam protection for PrestaShop.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
 * @version   1.0.0
 */
use TecSpamGuard\Captcha\AltchaProvider;
use TecSpamGuard\Captcha\AltchaSentinelProvider;
use TecSpamGuard\Captcha\CaptchaProviderInterface;
use TecSpamGuard\Captcha\RecaptchaV2Provider;
use TecSpamGuard\Captcha\RecaptchaV3Provider;
use TecSpamGuard\Captcha\TurnstileProvider;
use TecSpamGuard\Form\ContactForm;
use TecSpamGuard\Form\FormInterface;
use TecSpamGuard\Form\LoginForm;
use TecSpamGuard\Form\PasswordForm;
use TecSpamGuard\Form\RegisterForm;
use TecSpamGuard\Validation\EmailValidator;
use TecSpamGuard\Validation\MessageValidator;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tec_spamguard extends Module
{
    public const CONFIG_PREFIX = 'TEC_SPAMGUARD_';

    /**
     * Module constructor.
     */
    public function __construct()
    {
        $this->name = 'tec_spamguard';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Tecnoacquisti.com';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Tec Spam Guard');
        $this->description = $this->l('Protect contact, registration, login, and password reset forms from spam with captcha, email, and message validation.');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        $this->registerAutoload();
    }

    /**
     * Install module hooks and defaults.
     *
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook([
                'displayHeader',
                'actionDispatcher',
                'actionContactFormSubmitBefore',
                'actionSubmitAccountBefore',
            ])
            && $this->installDefaults();
    }

    /**
     * Remove module configuration.
     *
     * @return bool
     */
    public function uninstall()
    {
        foreach (array_keys($this->getDefaultConfiguration()) as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Render back-office configuration.
     *
     * @return string
     */
    public function getContent()
    {
        if ((int) Tools::getValue('ajax') === 1
            && Tools::getValue('action') === 'testCaptchaKeys') {
            $this->ajaxTestCaptchaKeys();
        }

        $this->context->controller->addCSS($this->_path . 'views/css/back.css');

        $output = '';
        if (Tools::isSubmit('submitTecSpamGuardCaptchaService')) {
            $output .= $this->postProcessCaptchaServiceConfiguration();
        }
        if (Tools::isSubmit('submitTecSpamGuardCaptchaForms')) {
            $output .= $this->postProcessSwitchConfiguration([
                'CONTACT_CAPTCHA', 'REGISTER_CAPTCHA', 'LOGIN_CAPTCHA', 'PASSWORD_CAPTCHA',
            ]);
        }
        if (Tools::isSubmit('submitTecSpamGuardEmailValidation')) {
            $output .= $this->postProcessEmailValidationConfiguration();
        }
        if (Tools::isSubmit('submitTecSpamGuardMessageValidation')) {
            $output .= $this->postProcessMessageValidationConfiguration();
        }

        return $output . $this->renderConfigurationTabs();
    }

    /**
     * Load front-office assets for protected pages.
     *
     * @param array $params Hook parameters
     *
     * @return string
     */
    public function hookDisplayHeader($params)
    {
        unset($params);

        if (!$this->isCurrentPageProtectable() || !$this->hasAnyCaptchaProtectedForm()) {
            return '';
        }

        $provider = $this->createCaptchaProvider();
        if ($provider === null) {
            return '';
        }

        $siteKey = $this->getCaptchaSiteKey();
        if ($siteKey === '') {
            return '';
        }

        $this->context->controller->registerJavascript(
            'module-tec-spamguard-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 150]
        );
        $this->context->controller->registerStylesheet(
            'module-tec-spamguard-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 150]
        );

        Media::addJsDef([
            'tecSpamGuard' => [
                'provider' => $provider->getId(),
                'siteKey' => $siteKey,
                'responseField' => $provider->getResponseFieldName(),
                'widgetAttributes' => method_exists($provider, 'getWidgetAttributes') ? $provider->getWidgetAttributes() : [],
                'recaptchaAction' => (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION'),
                'forms' => $this->getProtectedFormConfig(),
            ],
        ]);

        $this->context->smarty->assign([
            'tec_spamguard_script_url' => $this->getCaptchaScriptUrl($provider, $siteKey),
            'tec_spamguard_is_module_script' => in_array($provider->getId(), ['altcha', 'altcha_sentinel'], true),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/header.tpl');
    }

    /**
     * Validate submitted forms before their controllers handle the request.
     *
     * @param array $params Hook parameters
     *
     * @return void
     */
    public function hookActionDispatcher($params)
    {
        unset($params);

        $form = $this->getSubmittedForm();
        if ($form === null) {
            return;
        }

        $error = $this->validateSubmittedForm($form);
        if ($error !== '') {
            $this->rejectRequest($error, $form->getType());
        }
    }

    /**
     * Native contact form hook fallback.
     *
     * @return bool
     */
    public function hookActionContactFormSubmitBefore()
    {
        $form = $this->buildForm('contact');
        $error = $this->validateSubmittedForm($form);
        if ($error !== '') {
            $this->context->controller->errors[] = $error;

            return false;
        }

        return true;
    }

    /**
     * Native account submit hook fallback.
     *
     * @param array $params Hook parameters
     *
     * @return bool
     */
    public function hookActionSubmitAccountBefore($params)
    {
        unset($params);

        $form = $this->buildForm('register');
        $error = $this->validateSubmittedForm($form);
        if ($error !== '') {
            $this->context->controller->errors[] = $error;

            return false;
        }

        return true;
    }

    /**
     * Test the configured captcha provider credentials via ajax.
     *
     * @return void
     */
    private function ajaxTestCaptchaKeys()
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $providerId = (string) Tools::getValue('provider');
        $siteKey = trim((string) Tools::getValue('sitekey'));
        $secret = (string) Tools::getValue('secret');

        if ($secret === '' || preg_match('/^\*{4,}.+$/', $secret)) {
            $secret = $this->getCaptchaStoredSecretForProvider($providerId);
        }
        $secret = trim($secret);

        if (!in_array($providerId, ['recaptcha_v2', 'recaptcha_v3', 'turnstile', 'altcha', 'altcha_sentinel'], true)) {
            exit(json_encode([
                'success' => false,
                'message' => $this->l('Select a captcha provider before testing.'),
            ]));
        }

        if ($providerId !== 'altcha' && ($siteKey === '' || $secret === '')) {
            exit(json_encode([
                'success' => false,
                'message' => $this->l('Site key and secret key must both be set.'),
            ]));
        }

        if ($providerId === 'altcha' && $secret === '') {
            exit(json_encode([
                'success' => false,
                'message' => $this->l('ALTCHA HMAC secret must be set.'),
            ]));
        }

        $result = $this->validateCaptchaProviderKeys($providerId, $siteKey, $secret);

        exit(json_encode([
            'success' => (bool) $result['success'],
            'message' => (string) $result['message'],
        ]));
    }

    /**
     * Validate captcha provider credentials.
     *
     * @param string $providerId Provider identifier
     * @param string $siteKey Public site key or challenge URL
     * @param string $secret Secret credential
     *
     * @return array
     */
    private function validateCaptchaProviderKeys($providerId, $siteKey, $secret)
    {
        $provider = $this->createCaptchaProviderById($providerId, $siteKey, $secret);
        if ($provider === null) {
            return [
                'success' => false,
                'message' => $this->l('Select a captcha provider before testing the keys.'),
            ];
        }

        $result = $provider->testKeys($siteKey, $secret);
        if (empty($result['success'])) {
            return [
                'success' => false,
                'message' => $this->l('Captcha key validation failed: ') . (string) $result['message'],
            ];
        }

        return [
            'success' => true,
            'message' => (string) $result['message'],
        ];
    }

    /**
     * Create a captcha provider by identifier for back-office tests.
     *
     * @param string $providerId Provider identifier
     * @param string $siteKey Public site key or challenge URL
     * @param string $secret Secret credential
     *
     * @return CaptchaProviderInterface|null
     */
    private function createCaptchaProviderById($providerId, $siteKey = '', $secret = '')
    {
        switch ((string) $providerId) {
            case 'recaptcha_v2':
                return new RecaptchaV2Provider();
            case 'recaptcha_v3':
                return new RecaptchaV3Provider(
                    (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION'),
                    (float) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_MIN_SCORE')
                );
            case 'turnstile':
                return new TurnstileProvider();
            case 'altcha':
                return new AltchaProvider();
            case 'altcha_sentinel':
                return new AltchaSentinelProvider($siteKey, $secret);
        }

        return null;
    }

    /**
     * Return the stored secret used for captcha provider tests.
     *
     * @param string $providerId Provider identifier
     *
     * @return string
     */
    private function getCaptchaStoredSecretForProvider($providerId)
    {
        switch ((string) $providerId) {
            case 'recaptcha_v2':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SECRET');
            case 'recaptcha_v3':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SECRET');
            case 'turnstile':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'TURNSTILE_SECRET');
            case 'altcha':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SECRET');
            case 'altcha_sentinel':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY');
        }

        return '';
    }

    /**
     * Return configured captcha provider.
     *
     * @return CaptchaProviderInterface|null
     */
    public function createCaptchaProvider()
    {
        $provider = (string) Configuration::get(self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER');

        switch ($provider) {
            case 'recaptcha_v2':
                return new RecaptchaV2Provider();
            case 'recaptcha_v3':
                return new RecaptchaV3Provider(
                    (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION'),
                    (float) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_MIN_SCORE')
                );
            case 'turnstile':
                return new TurnstileProvider();
            case 'altcha':
                return new AltchaProvider(
                    (int) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_HIDE_FOOTER') === 1,
                    (int) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_HIDE_LOGO') === 1
                );
            case 'altcha_sentinel':
                return new AltchaSentinelProvider(
                    (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_URL'),
                    (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY'),
                    (int) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_HIDE_FOOTER') === 1,
                    (int) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_HIDE_LOGO') === 1
                );
        }

        return null;
    }

    /**
     * Create a local ALTCHA challenge.
     *
     * @return array
     */
    public function createAltchaChallenge()
    {
        $provider = new AltchaProvider();

        return $provider->createChallenge(
            (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SECRET'),
            (int) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_DIFFICULTY'),
            (int) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_EXPIRES_SECONDS')
        );
    }

    /**
     * Check if local ALTCHA is the active captcha provider.
     *
     * @return bool
     */
    public function isLocalAltchaActive()
    {
        return (string) Configuration::get(self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER') === 'altcha';
    }

    /**
     * Register a small PSR-4-like autoloader for production installs.
     *
     * @return void
     */
    private function registerAutoload()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'TecSpamGuard\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = dirname(__FILE__) . '/src/' . $relative . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Install default configuration values.
     *
     * @return bool
     */
    private function installDefaults()
    {
        foreach ($this->getDefaultConfiguration() as $key => $value) {
            if (Configuration::get($key) === false) {
                if (!Configuration::updateValue($key, $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Return default module configuration.
     *
     * @return array
     */
    private function getDefaultConfiguration()
    {
        return [
            self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER' => 'none',
            self::CONFIG_PREFIX . 'CONTACT_CAPTCHA' => 1,
            self::CONFIG_PREFIX . 'REGISTER_CAPTCHA' => 1,
            self::CONFIG_PREFIX . 'LOGIN_CAPTCHA' => 0,
            self::CONFIG_PREFIX . 'PASSWORD_CAPTCHA' => 1,
            self::CONFIG_PREFIX . 'CONTACT_EMAIL' => 1,
            self::CONFIG_PREFIX . 'REGISTER_EMAIL' => 1,
            self::CONFIG_PREFIX . 'LOGIN_EMAIL' => 0,
            self::CONFIG_PREFIX . 'PASSWORD_EMAIL' => 0,
            self::CONFIG_PREFIX . 'CONTACT_MESSAGE' => 1,
            self::CONFIG_PREFIX . 'BLOCK_DISPOSABLE' => 1,
            self::CONFIG_PREFIX . 'BLOCKED_EMAILS' => '',
            self::CONFIG_PREFIX . 'BLOCKED_DOMAINS' => '',
            self::CONFIG_PREFIX . 'BLOCKED_EMAIL_PATTERNS' => '',
            self::CONFIG_PREFIX . 'BLOCKED_MESSAGE_TEXTS' => "viagra\ncasino\nloan\ncrypto investment\nforex trading\nwork from home\nmake money online\nseo services\nguest post",
            self::CONFIG_PREFIX . 'MAX_MESSAGE_LINKS' => 3,
            self::CONFIG_PREFIX . 'RECAPTCHA_V2_SITEKEY' => '',
            self::CONFIG_PREFIX . 'RECAPTCHA_V2_SECRET' => '',
            self::CONFIG_PREFIX . 'RECAPTCHA_V3_SITEKEY' => '',
            self::CONFIG_PREFIX . 'RECAPTCHA_V3_SECRET' => '',
            self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION' => 'tec_spamguard',
            self::CONFIG_PREFIX . 'RECAPTCHA_V3_MIN_SCORE' => '0.50',
            self::CONFIG_PREFIX . 'TURNSTILE_SITEKEY' => '',
            self::CONFIG_PREFIX . 'TURNSTILE_SECRET' => '',
            self::CONFIG_PREFIX . 'ALTCHA_SECRET' => '',
            self::CONFIG_PREFIX . 'ALTCHA_DIFFICULTY' => 1,
            self::CONFIG_PREFIX . 'ALTCHA_EXPIRES_SECONDS' => 300,
            self::CONFIG_PREFIX . 'ALTCHA_HIDE_FOOTER' => 0,
            self::CONFIG_PREFIX . 'ALTCHA_HIDE_LOGO' => 0,
            self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_URL' => '',
            self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY' => '',
        ];
    }

    /**
     * Save and validate configuration.
     *
     * @return string
     */
    private function postProcessCaptchaServiceConfiguration()
    {
        $provider = (string) Tools::getValue(self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER');
        if (!in_array($provider, ['none', 'recaptcha_v2', 'recaptcha_v3', 'turnstile', 'altcha', 'altcha_sentinel'], true)) {
            return $this->displayError($this->l('Invalid captcha provider.'));
        }

        $boolFields = [
            'ALTCHA_HIDE_FOOTER', 'ALTCHA_HIDE_LOGO',
        ];
        foreach ($boolFields as $field) {
            $value = (int) Tools::getValue(self::CONFIG_PREFIX . $field);
            if (!in_array($value, [0, 1], true)) {
                return $this->displayError($this->l('Invalid switch value.'));
            }
        }

        $v3MinScore = (string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_MIN_SCORE');
        if (!is_numeric($v3MinScore) || (float) $v3MinScore < 0.0 || (float) $v3MinScore > 1.0) {
            return $this->displayError($this->l('reCAPTCHA v3 minimum score must be between 0 and 1.'));
        }

        $altchaDifficulty = (int) Tools::getValue(self::CONFIG_PREFIX . 'ALTCHA_DIFFICULTY');
        if ($altchaDifficulty < 1 || $altchaDifficulty > 3) {
            return $this->displayError($this->l('ALTCHA difficulty must be between 1 and 3.'));
        }

        $altchaExpires = (int) Tools::getValue(self::CONFIG_PREFIX . 'ALTCHA_EXPIRES_SECONDS');
        if ($altchaExpires < 60 || $altchaExpires > 3600) {
            return $this->displayError($this->l('ALTCHA challenge lifetime must be between 60 and 3600 seconds.'));
        }

        $sentinelUrl = trim((string) Tools::getValue(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_URL'));
        $sentinelScheme = $sentinelUrl !== '' ? parse_url($sentinelUrl, PHP_URL_SCHEME) : '';
        if ($sentinelUrl !== ''
            && (!filter_var($sentinelUrl, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) $sentinelScheme), ['http', 'https'], true))) {
            return $this->displayError($this->l('ALTCHA Sentinel URL must be a valid absolute URL.'));
        }

        $tokenValidation = $this->validateCaptchaTextInputs($provider);
        if ($tokenValidation !== '') {
            return $this->displayError($tokenValidation);
        }

        Configuration::updateValue(self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER', $provider);
        foreach ($boolFields as $field) {
            Configuration::updateValue(self::CONFIG_PREFIX . $field, (int) Tools::getValue(self::CONFIG_PREFIX . $field));
        }

        Configuration::updateValue(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SITEKEY', trim((string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SITEKEY')));
        Configuration::updateValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SITEKEY', trim((string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SITEKEY')));
        Configuration::updateValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION', $this->normalizeToken((string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION'), 'tec_spamguard'));
        Configuration::updateValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_MIN_SCORE', number_format((float) $v3MinScore, 2, '.', ''));
        Configuration::updateValue(self::CONFIG_PREFIX . 'TURNSTILE_SITEKEY', trim((string) Tools::getValue(self::CONFIG_PREFIX . 'TURNSTILE_SITEKEY')));
        Configuration::updateValue(self::CONFIG_PREFIX . 'ALTCHA_DIFFICULTY', $altchaDifficulty);
        Configuration::updateValue(self::CONFIG_PREFIX . 'ALTCHA_EXPIRES_SECONDS', $altchaExpires);
        Configuration::updateValue(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_URL', rtrim($sentinelUrl, '/'));

        foreach (['RECAPTCHA_V2_SECRET', 'RECAPTCHA_V3_SECRET', 'TURNSTILE_SECRET', 'ALTCHA_SECRET', 'ALTCHA_SENTINEL_API_KEY'] as $field) {
            Configuration::updateValue(
                self::CONFIG_PREFIX . $field,
                $this->getSubmittedSecretValue(self::CONFIG_PREFIX . $field, (string) Configuration::get(self::CONFIG_PREFIX . $field))
            );
        }

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    /**
     * Validate captcha configuration text inputs.
     *
     * @param string $provider Selected provider
     *
     * @return string Error message or empty string
     */
    private function validateCaptchaTextInputs($provider)
    {
        $simpleFields = [
            'RECAPTCHA_V2_SITEKEY' => 512,
            'RECAPTCHA_V3_SITEKEY' => 512,
            'TURNSTILE_SITEKEY' => 512,
        ];

        foreach ($simpleFields as $field => $maxLength) {
            $value = trim((string) Tools::getValue(self::CONFIG_PREFIX . $field));
            if ($value !== '' && !$this->isSafeCredential($value, $maxLength)) {
                return $this->l('Captcha keys may only contain safe credential characters.');
            }
        }

        foreach (['RECAPTCHA_V2_SECRET', 'RECAPTCHA_V3_SECRET', 'TURNSTILE_SECRET', 'ALTCHA_SECRET', 'ALTCHA_SENTINEL_API_KEY'] as $field) {
            $value = $this->getSubmittedSecretValue(
                self::CONFIG_PREFIX . $field,
                (string) Configuration::get(self::CONFIG_PREFIX . $field)
            );
            if ($value !== '' && !$this->isSafeCredential($value, 512)) {
                return $this->l('Captcha secrets may only contain safe credential characters.');
            }
        }

        $action = (string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION');
        if ($action !== '' && !$this->isSafeToken($action, 64)) {
            return $this->l('reCAPTCHA v3 action contains invalid characters.');
        }

        if ($provider === 'recaptcha_v2'
            && (trim((string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SITEKEY')) === ''
                || $this->getSubmittedSecretValue(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SECRET', (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SECRET')) === '')) {
            return $this->l('reCAPTCHA v2 site key and secret key are required.');
        }

        if ($provider === 'recaptcha_v3'
            && (trim((string) Tools::getValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SITEKEY')) === ''
                || $this->getSubmittedSecretValue(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SECRET', (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SECRET')) === '')) {
            return $this->l('reCAPTCHA v3 site key and secret key are required.');
        }

        if ($provider === 'turnstile'
            && (trim((string) Tools::getValue(self::CONFIG_PREFIX . 'TURNSTILE_SITEKEY')) === ''
                || $this->getSubmittedSecretValue(self::CONFIG_PREFIX . 'TURNSTILE_SECRET', (string) Configuration::get(self::CONFIG_PREFIX . 'TURNSTILE_SECRET')) === '')) {
            return $this->l('Turnstile site key and secret key are required.');
        }

        if ($provider === 'altcha'
            && $this->getSubmittedSecretValue(self::CONFIG_PREFIX . 'ALTCHA_SECRET', (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SECRET')) === '') {
            return $this->l('ALTCHA HMAC secret is required.');
        }

        if ($provider === 'altcha_sentinel'
            && ($this->getSubmittedSecretValue(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY', (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY')) === ''
                || trim((string) Tools::getValue(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_URL')) === '')) {
            return $this->l('ALTCHA Sentinel URL and API key are required.');
        }

        return '';
    }

    /**
     * Save boolean fields for a dedicated configuration tab.
     *
     * @param array $fields Boolean field suffixes
     *
     * @return string
     */
    private function postProcessSwitchConfiguration(array $fields)
    {
        foreach ($fields as $field) {
            $value = (int) Tools::getValue(self::CONFIG_PREFIX . $field);
            if (!in_array($value, [0, 1], true)) {
                return $this->displayError($this->l('Invalid switch value.'));
            }
        }

        foreach ($fields as $field) {
            Configuration::updateValue(self::CONFIG_PREFIX . $field, (int) Tools::getValue(self::CONFIG_PREFIX . $field));
        }

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    /**
     * Save email validation configuration.
     *
     * @return string
     */
    private function postProcessEmailValidationConfiguration()
    {
        $result = $this->postProcessSwitchConfiguration([
            'CONTACT_EMAIL', 'REGISTER_EMAIL', 'LOGIN_EMAIL', 'PASSWORD_EMAIL', 'BLOCK_DISPOSABLE',
        ]);
        if (strpos($result, 'alert-danger') !== false) {
            return $result;
        }

        foreach (['BLOCKED_EMAILS', 'BLOCKED_DOMAINS', 'BLOCKED_EMAIL_PATTERNS'] as $field) {
            $error = $this->validateEmailValidationTextarea($field, (string) Tools::getValue(self::CONFIG_PREFIX . $field));
            if ($error !== '') {
                return $this->displayError($error);
            }

            Configuration::updateValue(
                self::CONFIG_PREFIX . $field,
                $this->normalizeTextarea((string) Tools::getValue(self::CONFIG_PREFIX . $field))
            );
        }

        return $result;
    }

    /**
     * Save message validation configuration.
     *
     * @return string
     */
    private function postProcessMessageValidationConfiguration()
    {
        $result = $this->postProcessSwitchConfiguration(['CONTACT_MESSAGE']);
        if (strpos($result, 'alert-danger') !== false) {
            return $result;
        }

        $maxLinks = (int) Tools::getValue(self::CONFIG_PREFIX . 'MAX_MESSAGE_LINKS');
        if ($maxLinks < 0 || $maxLinks > 20) {
            return $this->displayError($this->l('Maximum message links must be between 0 and 20.'));
        }

        $blockedTexts = (string) Tools::getValue(self::CONFIG_PREFIX . 'BLOCKED_MESSAGE_TEXTS');
        if (Tools::strlen($blockedTexts) > 5000) {
            return $this->displayError($this->l('Blocked message text is too long.'));
        }

        Configuration::updateValue(
            self::CONFIG_PREFIX . 'BLOCKED_MESSAGE_TEXTS',
            $this->normalizeTextarea($blockedTexts)
        );
        Configuration::updateValue(self::CONFIG_PREFIX . 'MAX_MESSAGE_LINKS', $maxLinks);

        return $result;
    }

    /**
     * Render the configuration form.
     *
     * @return string
     */
    private function renderConfigurationTabs()
    {
        $activeTabId = $this->getActiveConfigurationTabId();
        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'module_display_name' => html_entity_decode($this->displayName, ENT_QUOTES, 'UTF-8'),
            'module_version' => $this->version,
            'readme_html_file' => $this->getBackOfficeReadmeHtmlFile(),
            'is_ps9' => version_compare(_PS_VERSION_, '9.0.0', '>='),
        ]);

        $tabs = [
            [
                'id' => 'captcha-service',
                'title' => $this->l('Captcha service'),
                'icon' => 'icon-key',
                'active' => $activeTabId === 'captcha-service',
                'form' => $this->renderConfigurationForm(
                    'submitTecSpamGuardCaptchaService',
                    $this->getCaptchaServiceFormDefinition()
                ),
            ],
            [
                'id' => 'captcha-forms',
                'title' => $this->l('Captcha activation'),
                'icon' => 'icon-check-square-o',
                'active' => $activeTabId === 'captcha-forms',
                'form' => $this->renderConfigurationForm(
                    'submitTecSpamGuardCaptchaForms',
                    $this->getCaptchaFormsFormDefinition()
                ),
            ],
            [
                'id' => 'email-validation',
                'title' => $this->l('Email validation'),
                'icon' => 'icon-envelope',
                'active' => $activeTabId === 'email-validation',
                'form' => $this->renderConfigurationForm(
                    'submitTecSpamGuardEmailValidation',
                    $this->getEmailValidationFormDefinition()
                ),
            ],
            [
                'id' => 'message-validation',
                'title' => $this->l('Message validation'),
                'icon' => 'icon-comment',
                'active' => $activeTabId === 'message-validation',
                'form' => $this->renderConfigurationForm(
                    'submitTecSpamGuardMessageValidation',
                    $this->getMessageValidationFormDefinition()
                ),
            ],
            [
                'id' => 'information',
                'title' => $this->l('Information'),
                'icon' => 'icon-info-circle',
                'active' => $activeTabId === 'information',
                'form' => $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl'),
            ],
        ];

        $this->context->smarty->assign([
            'tec_spamguard_config_tabs' => $tabs,
            'tec_spamguard_credits' => $this->context->smarty->fetch(
                $this->local_path . 'views/templates/admin/credits.tpl'
            ),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configuration_tabs.tpl');
    }

    /**
     * Get the README HTML file matching the current back-office language.
     *
     * @return string
     */
    private function getBackOfficeReadmeHtmlFile()
    {
        $isoCode = '';
        if (isset($this->context->language) && Validate::isLoadedObject($this->context->language)) {
            $isoCode = Tools::strtolower((string) $this->context->language->iso_code);
        }

        if ($isoCode === 'it' && is_file($this->local_path . 'documentation/README.it.html')) {
            return 'README.it.html';
        }

        return 'README.html';
    }

    /**
     * Return the active back-office configuration tab.
     *
     * @return string
     */
    private function getActiveConfigurationTabId()
    {
        if (Tools::isSubmit('submitTecSpamGuardCaptchaForms')) {
            return 'captcha-forms';
        }
        if (Tools::isSubmit('submitTecSpamGuardEmailValidation')) {
            return 'email-validation';
        }
        if (Tools::isSubmit('submitTecSpamGuardMessageValidation')) {
            return 'message-validation';
        }

        return 'captcha-service';
    }

    /**
     * Render one independent configuration form.
     *
     * @param string $submitAction Submit action name
     * @param array $form Form definition
     *
     * @return string
     */
    private function renderConfigurationForm($submitAction, array $form)
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = $submitAction;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigurationValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * Return switch values used by configuration forms.
     *
     * @return array
     */
    private function getSwitchValues()
    {
        return [
            ['id' => 'on', 'value' => 1, 'label' => $this->l('Enabled')],
            ['id' => 'off', 'value' => 0, 'label' => $this->l('Disabled')],
        ];
    }

    /**
     * Return captcha service form definition.
     *
     * @return array
     */
    private function getCaptchaServiceFormDefinition()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('Captcha service'), 'icon' => 'icon-key'],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Captcha provider'),
                        'name' => self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER',
                        'desc' => $this->l('Select the captcha service used by protected forms.'),
                        'options' => [
                            'query' => [
                                ['id' => 'none', 'name' => $this->l('None')],
                                ['id' => 'recaptcha_v2', 'name' => 'Google reCAPTCHA v2'],
                                ['id' => 'recaptcha_v3', 'name' => 'Google reCAPTCHA v3'],
                                ['id' => 'turnstile', 'name' => 'Cloudflare Turnstile'],
                                ['id' => 'altcha', 'name' => 'ALTCHA'],
                                ['id' => 'altcha_sentinel', 'name' => 'ALTCHA Sentinel'],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    ['type' => 'text', 'label' => $this->l('reCAPTCHA v2 site key'), 'name' => self::CONFIG_PREFIX . 'RECAPTCHA_V2_SITEKEY', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-recaptcha_v2'],
                    ['type' => 'text', 'label' => $this->l('reCAPTCHA v2 secret key'), 'name' => self::CONFIG_PREFIX . 'RECAPTCHA_V2_SECRET', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-recaptcha_v2'],
                    ['type' => 'text', 'label' => $this->l('reCAPTCHA v3 site key'), 'name' => self::CONFIG_PREFIX . 'RECAPTCHA_V3_SITEKEY', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-recaptcha_v3'],
                    ['type' => 'text', 'label' => $this->l('reCAPTCHA v3 secret key'), 'name' => self::CONFIG_PREFIX . 'RECAPTCHA_V3_SECRET', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-recaptcha_v3'],
                    ['type' => 'text', 'label' => $this->l('reCAPTCHA v3 action'), 'name' => self::CONFIG_PREFIX . 'RECAPTCHA_V3_ACTION', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-recaptcha_v3'],
                    ['type' => 'text', 'label' => $this->l('reCAPTCHA v3 minimum score'), 'name' => self::CONFIG_PREFIX . 'RECAPTCHA_V3_MIN_SCORE', 'class' => 'fixed-width-sm', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-recaptcha_v3'],
                    ['type' => 'text', 'label' => $this->l('Turnstile site key'), 'name' => self::CONFIG_PREFIX . 'TURNSTILE_SITEKEY', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-turnstile'],
                    ['type' => 'text', 'label' => $this->l('Turnstile secret key'), 'name' => self::CONFIG_PREFIX . 'TURNSTILE_SECRET', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-turnstile'],
                    ['type' => 'text', 'label' => $this->l('ALTCHA HMAC secret'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_SECRET', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha'],
                    ['type' => 'text', 'label' => $this->l('ALTCHA difficulty'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_DIFFICULTY', 'class' => 'fixed-width-sm', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha'],
                    ['type' => 'text', 'label' => $this->l('ALTCHA challenge lifetime'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_EXPIRES_SECONDS', 'class' => 'fixed-width-sm', 'suffix' => $this->l('seconds'), 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha'],
                    ['type' => 'switch', 'label' => $this->l('Hide ALTCHA footer'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_HIDE_FOOTER', 'is_bool' => true, 'values' => $this->getSwitchValues(), 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha tec-spamguard-provider-altcha_sentinel'],
                    ['type' => 'switch', 'label' => $this->l('Hide ALTCHA logo'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_HIDE_LOGO', 'is_bool' => true, 'values' => $this->getSwitchValues(), 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha tec-spamguard-provider-altcha_sentinel'],
                    ['type' => 'text', 'label' => $this->l('ALTCHA Sentinel URL'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_URL', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha_sentinel'],
                    ['type' => 'text', 'label' => $this->l('ALTCHA Sentinel API key'), 'name' => self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY', 'form_group_class' => 'tec-spamguard-provider-field tec-spamguard-provider-altcha_sentinel'],
                    ['type' => 'free', 'name' => self::CONFIG_PREFIX . 'CAPTCHA_TEST'],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];
    }

    /**
     * Render the captcha settings test button.
     *
     * @return string
     */
    private function renderCaptchaTestButton()
    {
        $url = AdminController::$currentIndex . '&configure=' . $this->name
            . '&token=' . Tools::getAdminTokenLite('AdminModules')
            . '&ajax=1&action=testCaptchaKeys';

        $this->context->smarty->assign([
            'tec_spamguard_captcha_test_url' => $url,
            'tec_spamguard_captcha_test_label' => $this->l('Test captcha keys'),
            'tec_spamguard_captcha_test_running' => $this->l('Testing...'),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/captcha_test_button.tpl');
    }

    /**
     * Return captcha activation form definition.
     *
     * @return array
     */
    private function getCaptchaFormsFormDefinition()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('Captcha activation'), 'icon' => 'icon-check-square-o'],
                'input' => [
                    ['type' => 'switch', 'label' => $this->l('Captcha on contact form'), 'name' => self::CONFIG_PREFIX . 'CONTACT_CAPTCHA', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Captcha on registration form'), 'name' => self::CONFIG_PREFIX . 'REGISTER_CAPTCHA', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Captcha on login form'), 'name' => self::CONFIG_PREFIX . 'LOGIN_CAPTCHA', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Captcha on password reset form'), 'name' => self::CONFIG_PREFIX . 'PASSWORD_CAPTCHA', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                ],
                'submit' => ['title' => $this->l('Save captcha activation')],
            ],
        ];
    }

    /**
     * Return email validation form definition.
     *
     * @return array
     */
    private function getEmailValidationFormDefinition()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('Email validation'), 'icon' => 'icon-envelope'],
                'input' => [
                    ['type' => 'switch', 'label' => $this->l('Email validation on contact form'), 'name' => self::CONFIG_PREFIX . 'CONTACT_EMAIL', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Email validation on registration form'), 'name' => self::CONFIG_PREFIX . 'REGISTER_EMAIL', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Email validation on login form'), 'name' => self::CONFIG_PREFIX . 'LOGIN_EMAIL', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Email validation on password reset form'), 'name' => self::CONFIG_PREFIX . 'PASSWORD_EMAIL', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'switch', 'label' => $this->l('Block disposable email domains'), 'name' => self::CONFIG_PREFIX . 'BLOCK_DISPOSABLE', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'textarea', 'label' => $this->l('Blocked email addresses'), 'name' => self::CONFIG_PREFIX . 'BLOCKED_EMAILS', 'desc' => $this->l('One email address per line.')],
                    ['type' => 'textarea', 'label' => $this->l('Blocked email domains'), 'name' => self::CONFIG_PREFIX . 'BLOCKED_DOMAINS', 'desc' => $this->l('One domain per line, without @.')],
                    ['type' => 'textarea', 'label' => $this->l('Blocked email patterns'), 'name' => self::CONFIG_PREFIX . 'BLOCKED_EMAIL_PATTERNS', 'desc' => $this->l('One wildcard pattern per line, for example *@example.com.')],
                ],
                'submit' => ['title' => $this->l('Save email validation')],
            ],
        ];
    }

    /**
     * Return message validation form definition.
     *
     * @return array
     */
    private function getMessageValidationFormDefinition()
    {
        return [
            'form' => [
                'legend' => ['title' => $this->l('Message validation'), 'icon' => 'icon-comment'],
                'input' => [
                    ['type' => 'switch', 'label' => $this->l('Message validation on contact form'), 'name' => self::CONFIG_PREFIX . 'CONTACT_MESSAGE', 'is_bool' => true, 'values' => $this->getSwitchValues()],
                    ['type' => 'textarea', 'label' => $this->l('Blocked message text'), 'name' => self::CONFIG_PREFIX . 'BLOCKED_MESSAGE_TEXTS', 'desc' => $this->l('One word or phrase per line.')],
                    ['type' => 'text', 'label' => $this->l('Maximum links in message'), 'name' => self::CONFIG_PREFIX . 'MAX_MESSAGE_LINKS', 'class' => 'fixed-width-sm'],
                ],
                'submit' => ['title' => $this->l('Save message validation')],
            ],
        ];
    }

    /**
     * Return form values.
     *
     * @return array
     */
    private function getConfigurationValues()
    {
        $values = [];
        foreach (array_keys($this->getDefaultConfiguration()) as $key) {
            $values[$key] = Configuration::get($key);
        }
        foreach (['RECAPTCHA_V2_SECRET', 'RECAPTCHA_V3_SECRET', 'TURNSTILE_SECRET', 'ALTCHA_SECRET', 'ALTCHA_SENTINEL_API_KEY'] as $field) {
            $key = self::CONFIG_PREFIX . $field;
            $values[$key] = $this->maskSecret((string) Configuration::get($key));
        }
        $values[self::CONFIG_PREFIX . 'CAPTCHA_TEST'] = $this->renderCaptchaTestButton();

        return $values;
    }

    /**
     * Return the submitted form descriptor.
     *
     * @return FormInterface|null
     */
    private function getSubmittedForm()
    {
        foreach (['contact', 'register', 'login', 'password'] as $type) {
            $form = $this->buildForm($type);
            if ($form->isSubmitted()) {
                return $form;
            }
        }

        return null;
    }

    /**
     * Build a form descriptor.
     *
     * @param string $type Form type
     *
     * @return FormInterface
     */
    private function buildForm($type)
    {
        switch ($type) {
            case 'contact':
                return new ContactForm($this->context);
            case 'register':
                return new RegisterForm($this->context);
            case 'login':
                return new LoginForm($this->context);
        }

        return new PasswordForm($this->context);
    }

    /**
     * Validate a submitted form.
     *
     * @param FormInterface $form Form descriptor
     *
     * @return string Error message or empty string
     */
    private function validateSubmittedForm(FormInterface $form)
    {
        if ($this->isFormCaptchaEnabled($form->getType())) {
            $error = $this->validateCaptcha();
            if ($error !== '') {
                return $error;
            }
        }

        if ($this->isFormEmailValidationEnabled($form->getType()) && $form->getEmail() !== '') {
            $validator = new EmailValidator(
                $this->getLines((string) Configuration::get(self::CONFIG_PREFIX . 'BLOCKED_EMAILS')),
                $this->getLines((string) Configuration::get(self::CONFIG_PREFIX . 'BLOCKED_DOMAINS')),
                $this->getLines((string) Configuration::get(self::CONFIG_PREFIX . 'BLOCKED_EMAIL_PATTERNS')),
                (int) Configuration::get(self::CONFIG_PREFIX . 'BLOCK_DISPOSABLE') === 1,
                dirname(__FILE__) . '/data/disposable_domains.txt'
            );
            if (!$validator->isAllowed($form->getEmail())) {
                return $this->l('Please use another email address.');
            }
        }

        if ($this->isFormMessageValidationEnabled($form->getType()) && $form->getMessage() !== '') {
            $validator = new MessageValidator(
                $this->getLines((string) Configuration::get(self::CONFIG_PREFIX . 'BLOCKED_MESSAGE_TEXTS')),
                (int) Configuration::get(self::CONFIG_PREFIX . 'MAX_MESSAGE_LINKS')
            );
            if (!$validator->isAllowed($form->getMessage())) {
                return $this->l('Please use another message.');
            }
        }

        return '';
    }

    /**
     * Validate captcha token with the configured provider.
     *
     * @return string Error message or empty string
     */
    private function validateCaptcha()
    {
        $provider = $this->createCaptchaProvider();
        if ($provider === null) {
            return '';
        }

        $secret = $this->getCaptchaSecret();
        $token = (string) Tools::getValue($provider->getResponseFieldName());
        $result = $provider->verify($token, $secret, Tools::getRemoteAddr());

        return !empty($result['success']) ? '' : $this->l('Please validate the captcha before submitting your request.');
    }

    /**
     * Reject the current POST request.
     *
     * @param string $error Error message
     * @param string $formType Protected form type
     *
     * @return void
     */
    private function rejectRequest($error, $formType = '')
    {
        $this->context->controller->errors[] = $error;
        $returnUrl = $this->getFormReturnUrl($formType);

        if (method_exists($this->context->controller, 'redirectWithNotifications')) {
            $this->context->controller->redirectWithNotifications($returnUrl);
        }

        Tools::redirect($returnUrl);
    }

    /**
     * Return the canonical page URL for a protected form.
     *
     * @param string $formType Protected form type
     *
     * @return string
     */
    private function getFormReturnUrl($formType)
    {
        $ssl = (bool) Configuration::get('PS_SSL_ENABLED')
            || (bool) Configuration::get('PS_SSL_ENABLED_EVERYWHERE');

        switch ((string) $formType) {
            case 'contact':
                return $this->context->link->getPageLink('contact', $ssl);
            case 'register':
                return $this->context->link->getPageLink('registration', $ssl);
            case 'login':
                return $this->context->link->getPageLink('authentication', $ssl);
            case 'password':
                return $this->context->link->getPageLink('password', $ssl);
        }

        return $this->context->link->getPageLink('index', $ssl);
    }

    /**
     * Check if the current page may need protection assets.
     *
     * @return bool
     */
    private function isCurrentPageProtectable()
    {
        $controller = $this->context->controller;

        return $controller instanceof ContactController
            || $controller instanceof AuthController
            || $controller instanceof PasswordController
            || (class_exists('RegistrationController') && $controller instanceof RegistrationController);
    }

    /**
     * Check if at least one captcha protected form is enabled.
     *
     * @return bool
     */
    private function hasAnyCaptchaProtectedForm()
    {
        foreach (['CONTACT', 'REGISTER', 'LOGIN', 'PASSWORD'] as $form) {
            if ((int) Configuration::get(self::CONFIG_PREFIX . $form . '_CAPTCHA') === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return front JS form config.
     *
     * @return array
     */
    private function getProtectedFormConfig()
    {
        $forms = [];
        $controller = $this->context->controller;

        if ($controller instanceof ContactController && $this->isFormCaptchaEnabled('contact')) {
            $forms['contact'] = true;
        }
        if ($controller instanceof PasswordController && $this->isFormCaptchaEnabled('password')) {
            $forms['password'] = true;
        }
        if (class_exists('RegistrationController') && $controller instanceof RegistrationController && $this->isFormCaptchaEnabled('register')) {
            $forms['register'] = true;
        }
        if ($controller instanceof AuthController) {
            if ($this->isFormCaptchaEnabled('register')) {
                $forms['register'] = true;
            }
            if ($this->isFormCaptchaEnabled('login')) {
                $forms['login'] = true;
            }
        }

        return $forms;
    }

    /**
     * Check if captcha is enabled for a form.
     *
     * @param string $type Form type
     *
     * @return bool
     */
    private function isFormCaptchaEnabled($type)
    {
        return (int) Configuration::get(self::CONFIG_PREFIX . strtoupper((string) $type) . '_CAPTCHA') === 1;
    }

    /**
     * Check if email validation is enabled for a form.
     *
     * @param string $type Form type
     *
     * @return bool
     */
    private function isFormEmailValidationEnabled($type)
    {
        return (int) Configuration::get(self::CONFIG_PREFIX . strtoupper((string) $type) . '_EMAIL') === 1;
    }

    /**
     * Check if message validation is enabled for a form.
     *
     * @param string $type Form type
     *
     * @return bool
     */
    private function isFormMessageValidationEnabled($type)
    {
        return (int) Configuration::get(self::CONFIG_PREFIX . strtoupper((string) $type) . '_MESSAGE') === 1;
    }

    /**
     * Return captcha public site key or challenge URL.
     *
     * @return string
     */
    private function getCaptchaSiteKey()
    {
        switch ((string) Configuration::get(self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER')) {
            case 'recaptcha_v2':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SITEKEY');
            case 'recaptcha_v3':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SITEKEY');
            case 'turnstile':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'TURNSTILE_SITEKEY');
            case 'altcha':
                return $this->context->link->getModuleLink($this->name, 'altchachallenge');
            case 'altcha_sentinel':
                $provider = $this->createCaptchaProvider();

                return $provider instanceof AltchaSentinelProvider ? $provider->getChallengeUrl() : '';
        }

        return '';
    }

    /**
     * Return captcha script URL with provider-specific public parameters.
     *
     * @param CaptchaProviderInterface $provider Captcha provider
     * @param string $siteKey Public site key or challenge URL
     *
     * @return string
     */
    private function getCaptchaScriptUrl(CaptchaProviderInterface $provider, $siteKey)
    {
        if ($provider->getId() === 'recaptcha_v3') {
            return 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode((string) $siteKey);
        }

        return $provider->getScriptUrl($this->context->language->iso_code);
    }

    /**
     * Return captcha secret.
     *
     * @return string
     */
    private function getCaptchaSecret()
    {
        switch ((string) Configuration::get(self::CONFIG_PREFIX . 'CAPTCHA_PROVIDER')) {
            case 'recaptcha_v2':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V2_SECRET');
            case 'recaptcha_v3':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'RECAPTCHA_V3_SECRET');
            case 'turnstile':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'TURNSTILE_SECRET');
            case 'altcha':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SECRET');
            case 'altcha_sentinel':
                return (string) Configuration::get(self::CONFIG_PREFIX . 'ALTCHA_SENTINEL_API_KEY');
        }

        return '';
    }

    /**
     * Return normalized non-empty textarea lines.
     *
     * @param string $value Raw textarea value
     *
     * @return array
     */
    private function getLines($value)
    {
        $lines = preg_split('/\R/', (string) $value);
        $result = [];
        foreach ($lines as $line) {
            $line = Tools::strtolower(trim((string) $line));
            if ($line !== '' && strpos($line, '#') !== 0) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Normalize textarea value.
     *
     * @param string $value Raw textarea value
     *
     * @return string
     */
    private function normalizeTextarea($value)
    {
        return implode("\n", $this->getLines($value));
    }

    /**
     * Normalize token-like config values.
     *
     * @param string $value Submitted value
     * @param string $fallback Fallback value
     *
     * @return string
     */
    private function normalizeToken($value, $fallback)
    {
        $value = trim((string) $value);

        return preg_match('/^[A-Za-z0-9_\/.-]{1,64}$/', $value) ? $value : $fallback;
    }

    /**
     * Validate a safe token-like value.
     *
     * @param string $value Submitted value
     * @param int $maxLength Maximum length
     *
     * @return bool
     */
    private function isSafeToken($value, $maxLength)
    {
        $value = trim((string) $value);

        return $value !== ''
            && Tools::strlen($value) <= (int) $maxLength
            && (bool) preg_match('/^[A-Za-z0-9_\/.-]+$/', $value);
    }

    /**
     * Validate a safe API credential value.
     *
     * @param string $value Submitted value
     * @param int $maxLength Maximum length
     *
     * @return bool
     */
    private function isSafeCredential($value, $maxLength)
    {
        $value = trim((string) $value);

        return $value !== ''
            && Tools::strlen($value) <= (int) $maxLength
            && (bool) preg_match('/^[A-Za-z0-9._~+\/=:;,@-]+$/', $value);
    }

    /**
     * Validate an email validation textarea.
     *
     * @param string $field Field suffix
     * @param string $value Submitted value
     *
     * @return string Error message or empty string
     */
    private function validateEmailValidationTextarea($field, $value)
    {
        if (Tools::strlen((string) $value) > 10000) {
            return $this->l('Email validation list is too long.');
        }

        foreach ($this->getLines($value) as $line) {
            if (Tools::strlen($line) > 255) {
                return $this->l('Email validation entries must be shorter than 255 characters.');
            }

            if ($field === 'BLOCKED_EMAILS' && !Validate::isEmail($line)) {
                return $this->l('Blocked email entries must be valid email addresses.');
            }

            if ($field === 'BLOCKED_DOMAINS' && !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $line)) {
                return $this->l('Blocked domain entries must be valid domain names.');
            }

            if ($field === 'BLOCKED_EMAIL_PATTERNS' && !preg_match('/^[a-z0-9._%+\-*?@-]+$/', $line)) {
                return $this->l('Blocked email patterns contain invalid characters.');
            }
        }

        return '';
    }

    /**
     * Mask a stored secret for display.
     *
     * @param string $value Secret value
     *
     * @return string
     */
    private function maskSecret($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        return str_repeat('*', max(8, Tools::strlen($value) - 4)) . Tools::substr($value, -4);
    }

    /**
     * Preserve masked secret values on save.
     *
     * @param string $key Configuration key
     * @param string $current Current value
     *
     * @return string
     */
    private function getSubmittedSecretValue($key, $current)
    {
        $submitted = trim((string) Tools::getValue($key));
        if ($submitted === '' || preg_match('/^\*{4,}.+$/', $submitted)) {
            return $current;
        }

        return $submitted;
    }
}
