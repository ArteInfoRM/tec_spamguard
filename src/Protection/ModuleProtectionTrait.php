<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

namespace TecSpamGuard\Protection;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Bridge the existing module hooks and configuration to isolated protection services. */
trait ModuleProtectionTrait
{
    /** @var Store|null Request-local store. */
    private $protectionStore;
    /** @var array Results prevent dispatcher and fallback hooks counting twice. */
    private $protectionResults = [];
    /** @var array Failure counters already recorded during this request. */
    private $protectionFailures = [];
    /** @var array CAPTCHA transport outcomes, which must not penalize users. */
    private $captchaTechnicalFailures = [];

    /** Return configuration defaults without generating new random values on reads. */
    private function protectionDefaults()
    {
        $defaults = [
            'GUARD_HONEYPOT' => 1, 'GUARD_FIELD' => '', 'GUARD_LIMITS' => 1,
            'ABUSE_ENABLED' => 0, 'ABUSE_API_KEY' => '', 'ABUSE_MODE' => 'monitor',
            'ABUSE_SCORE' => 90, 'ABUSE_BUDGET' => 900,
        ];
        foreach (['REGISTER', 'CONTACT', 'PASSWORD'] as $form) {
            $defaults['GUARD_' . $form] = 1;
            $defaults['ABUSE_' . $form] = 1;
            $defaults['GUARD_' . $form . '_CAPTCHA'] = 10;
            $defaults['GUARD_' . $form . '_TRAP'] = 2;
            $defaults['GUARD_' . $form . '_WINDOW'] = 15;
            $defaults['GUARD_' . $form . '_PAUSE'] = 15;
            $defaults['GUARD_' . $form . '_ADMISSIONS'] = $form === 'PASSWORD' ? 5 : 3;
            $defaults['GUARD_' . $form . '_PERIOD'] = $form === 'CONTACT' ? 10 : 60;
            $defaults['GUARD_' . $form . '_IP'] = $form === 'REGISTER' ? 10 : 30;
            $defaults['GUARD_' . $form . '_COOLDOWN'] = $form === 'PASSWORD' ? 120 : 0;
        }
        $result = [];
        foreach ($defaults as $key => $value) {
            $result[self::CONFIG_PREFIX . $key] = $value;
        }

        return $result;
    }

    /** Select the isolated legacy or modern connection explicitly. */
    private function guardStore()
    {
        if ($this->protectionStore === null) {
            if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
                if ($container !== null) {
                    $connection = $container->get('doctrine.dbal.default_connection');
                } else {
                    // Native front controllers and CLI bootstraps may have no Symfony kernel.
                    $parameters = require _PS_ROOT_DIR_ . '/app/config/parameters.php';
                    $database = $parameters['parameters'];
                    $connection = \Doctrine\DBAL\DriverManager::getConnection([
                        'driver' => 'pdo_mysql', 'host' => $database['database_host'],
                        'port' => !empty($database['database_port']) ? (int) $database['database_port'] : 3306,
                        'dbname' => $database['database_name'], 'user' => $database['database_user'],
                        'password' => $database['database_password'], 'charset' => 'utf8mb4',
                    ]);
                }
                $adapter = new \TecSpamGuard\Modern\ProtectionConnection($connection);
            } else {
                $adapter = new \TecSpamGuard\Legacy\ProtectionConnection();
            }
            $this->protectionStore = new Store($adapter, _DB_PREFIX_, max(1, (int) $this->context->shop->id));
        }

        return $this->protectionStore;
    }

    /** Install or upgrade protection without overwriting merchant choices. */
    public function installAdvancedProtection()
    {
        foreach ($this->protectionDefaults() as $key => $value) {
            if (\Configuration::get($key) === false && !\Configuration::updateValue($key, $value)) {
                return false;
            }
        }
        if ((string) \Configuration::get(self::CONFIG_PREFIX . 'GUARD_FIELD') === '') {
            $names = FormProof::names();
            if (!\Configuration::updateValue(self::CONFIG_PREFIX . 'GUARD_FIELD', $names[random_int(0, count($names) - 1)])) {
                return false;
            }
        }

        return $this->guardStore()->install() && $this->registerHook('actionOutputHTMLBefore');
    }

    /** Read a setting with explicit shop scope. */
    private function guardConfig($suffix)
    {
        $key = self::CONFIG_PREFIX . $suffix;
        $value = \Configuration::get($key, null, (int) $this->context->shop->id_shop_group, (int) $this->context->shop->id);
        $defaults = $this->protectionDefaults();

        return $value === false && isset($defaults[$key]) ? $defaults[$key] : $value;
    }

    /** Determine effective WAF integration without loading or calling the WAF. */
    public function isAbuseManagedByWaf()
    {
        return \Module::isEnabled('tec_blockaibots')
            && (int) \Configuration::get('TEC_BLOCKAIBOTS_ABUSEIPDB', null,
                (int) $this->context->shop->id_shop_group, (int) $this->context->shop->id) === 1;
    }

    /** Create an opaque identity in the encrypted PrestaShop cookie before output. */
    private function guardSession($create = false)
    {
        $value = (string) $this->context->cookie->tec_spamguard_session;
        if (!preg_match('/^[a-f0-9]{64}$/D', $value)) {
            if (!$create) {
                return '';
            }
            $value = bin2hex(random_bytes(32));
            $this->context->cookie->tec_spamguard_session = $value;
            $this->context->cookie->write();
        }

        return $value;
    }

    /** Limit this first implementation to supported native form controllers. */
    private function guardApplies($type)
    {
        if (defined('_PS_ADMIN_DIR_') || !in_array($type, ['register', 'contact', 'password'], true)) {
            return false;
        }
        $controller = $this->context->controller;
        if ($type === 'contact') {
            return $controller instanceof \ContactController;
        }
        if ($type === 'password') {
            return $controller instanceof \PasswordController;
        }

        return $controller instanceof \AuthController || $controller instanceof \OrderController
            || (class_exists('RegistrationController') && $controller instanceof \RegistrationController);
    }

    /** Establish source identity even when only attempt limits are enabled. */
    private function prepareAdvancedProtectionSession()
    {
        if ((int) $this->guardConfig('GUARD_HONEYPOT') !== 1 && (int) $this->guardConfig('GUARD_LIMITS') !== 1) {
            return;
        }
        foreach (['register', 'contact', 'password'] as $type) {
            if ($this->guardApplies($type) && (int) $this->guardConfig('GUARD_' . strtoupper($type)) === 1) {
                $this->guardSession(true);
                if (!headers_sent()) {
                    header('Cache-Control: private, no-store');
                }
                return;
            }
        }
    }

    /** Render Smarty protection fields into native HTML forms without a JS dependency. */
    public function hookActionOutputHTMLBefore($params)
    {
        if (empty($params['html']) || !is_string($params['html']) || defined('_PS_ADMIN_DIR_')
            || (int) $this->guardConfig('GUARD_HONEYPOT') !== 1) {
            return;
        }
        $field = (string) $this->guardConfig('GUARD_FIELD');
        if (!in_array($field, FormProof::names(), true)) {
            return;
        }
        $changed = false;
        $html = preg_replace_callback('~<form\b[^>]*>.*?</form\s*>~is', function ($match) use ($field, &$changed) {
            $form = $match[0];
            if (preg_match('~\baction\s*=\s*["\x27]([^"\x27]*)["\x27]~i', $form, $action)) {
                $host = parse_url(html_entity_decode($action[1], ENT_QUOTES, 'UTF-8'), PHP_URL_HOST);
                $shopHost = parse_url($this->context->link->getBaseLink(), PHP_URL_HOST);
                if ($host && strcasecmp($host, (string) $shopHost) !== 0) {
                    return $form;
                }
            }
            $type = '';
            if (preg_match('~name\s*=\s*["\x27](?:submitCreate|submitAccount)["\x27]~i', $form)) {
                $type = 'register';
            } elseif (preg_match('~name\s*=\s*["\x27]submitMessage["\x27]~i', $form)) {
                $type = 'contact';
            } elseif ($this->guardApplies('password')
                && preg_match('~name\s*=\s*["\x27]email["\x27]~i', $form)
                && !preg_match('~name\s*=\s*["\x27](?:passwd|password|reset_token|submitNewsletter|submitLogin)["\x27]~i', $form)) {
                $type = 'password';
            }
            if (!$this->guardApplies($type) || (int) $this->guardConfig('GUARD_' . strtoupper($type)) !== 1) {
                return $form;
            }
            if (strpos($form, 'name="tec_sg_proof"') !== false) {
                return $form;
            }
            $this->context->smarty->assign([
                'sg_guard_field' => $field,
                'sg_guard_proof' => FormProof::issue((string) _COOKIE_KEY_, $this->guardSession(true),
                    (int) $this->context->shop->id, $type, $field),
            ]);
            $fields = $this->context->smarty->fetch($this->local_path . 'views/templates/hook/protection_fields.tpl');
            $changed = true;

            return preg_replace_callback('~</form\s*>$~i', function ($closing) use ($fields) {
                return $fields . $closing[0];
            }, $form);
        }, $params['html']);
        if (is_string($html) && $changed) {
            $params['html'] = $html;
            if (!headers_sent()) {
                header('Cache-Control: private, no-store');
            }
        }
    }

    /** Use the server-established client address, never an untrusted forwarding header. */
    private function guardIp()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
    }

    /** Return a source identifier; email must never identify an error offender. */
    private function guardSource()
    {
        $session = $this->guardSession();

        return $session !== '' ? 'session:' . $session : 'ip:' . $this->guardIp();
    }

    /** Log only a bounded reason, never the honeypot content, token or API response. */
    private function guardLog($type, $reason, $passed = false)
    {
        $this->logValidationAttempt($type, $reason, $passed);
    }

    /** Count user validation errors once, without touching the supplied email identity. */
    private function guardFailure($type, $kind)
    {
        if (!$this->guardApplies($type) || (int) $this->guardConfig('GUARD_LIMITS') !== 1
            || (int) $this->guardConfig('GUARD_' . strtoupper($type)) !== 1
            || isset($this->protectionFailures[$type . ':' . $kind])) {
            return;
        }
        $this->protectionFailures[$type . ':' . $kind] = true;
        $prefix = 'GUARD_' . strtoupper($type) . '_';
        $limit = (int) $this->guardConfig($prefix . ($kind === 'honeypot' ? 'TRAP' : 'CAPTCHA'));
        $window = (int) $this->guardConfig($prefix . 'WINDOW') * 60;
        $pause = (int) $this->guardConfig($prefix . 'PAUSE') * 60;
        $this->guardStore()->failure('failure:' . $type . ':' . $kind . ':' . $this->guardSource(), $limit, $window, $pause);
        $this->guardStore()->failure('failure-ip:' . $type . ':' . $kind . ':' . $this->guardIp(), $limit * 5, $window, $pause);
    }

    /** Check proof and current suspensions before CAPTCHA or provider network access. */
    private function guardBefore($type)
    {
        if (!$this->guardApplies($type) || (int) $this->guardConfig('GUARD_' . strtoupper($type)) !== 1) {
            return '';
        }
        if (isset($this->protectionResults[$type])) {
            return $this->protectionResults[$type];
        }
        if (((int) $this->guardConfig('GUARD_LIMITS') === 1 || (int) $this->guardConfig('GUARD_HONEYPOT') === 1)
            && random_int(1, 100) === 1) {
            $this->guardStore()->cleanup();
        }
        if ((int) $this->guardConfig('GUARD_LIMITS') === 1) {
            foreach (['captcha', 'honeypot'] as $kind) {
                if ($this->guardStore()->blocked('failure:' . $type . ':' . $kind . ':' . $this->guardSource())
                    || $this->guardStore()->blocked('failure-ip:' . $type . ':' . $kind . ':' . $this->guardIp())) {
                    $this->guardLog($type, 'rate_limit');

                    return 'rate_limit';
                }
            }
        }
        if ((int) $this->guardConfig('GUARD_HONEYPOT') === 1) {
            $proof = FormProof::verify(isset($_POST['tec_sg_proof']) ? $_POST['tec_sg_proof'] : null,
                (string) _COOKIE_KEY_, $this->guardSession(), (int) $this->context->shop->id, $type);
            if (!$proof || !array_key_exists($proof['field'], $_POST) || !is_string($_POST[$proof['field']])) {
                $this->guardLog($type, 'form_invalid');

                return 'form_invalid';
            }
            if ($_POST[$proof['field']] !== '') {
                $this->guardFailure($type, 'honeypot');
                $this->guardLog($type, 'honeypot');

                return 'honeypot';
            }
            if (!$this->guardStore()->consume($proof['nonce'], $proof['remaining'])) {
                $this->guardLog($type, 'form_replay');

                return 'form_replay';
            }
        }
        // Mark pre-validation separately so fallback hooks do not consume the proof twice.
        $this->protectionResults[$type] = '';

        return '';
    }

    /** Reserve admitted submissions and check reputation after other local validation. */
    private function guardAfter($type, $email)
    {
        if (!$this->guardApplies($type) || isset($this->protectionResults[$type . ':done'])) {
            return '';
        }
        if (!is_string($email) || strlen($email) > 254 || !\Validate::isEmail($email)) {
            return '';
        }
        if ((int) $this->guardConfig('GUARD_LIMITS') === 1 && (int) $this->guardConfig('GUARD_' . strtoupper($type)) === 1) {
            $prefix = 'GUARD_' . strtoupper($type) . '_';
            $period = (int) $this->guardConfig($prefix . 'PERIOD') * 60;
            $identity = $type === 'register' ? $this->guardSource() : 'email:' . strtolower(trim($email));
            $limits = [
                ['admit:' . $type . ':' . $identity, (int) $this->guardConfig($prefix . 'ADMISSIONS'), $period],
                ['admit-ip:' . $type . ':' . $this->guardIp(), (int) $this->guardConfig($prefix . 'IP'), $period],
            ];
            $cooldown = (int) $this->guardConfig($prefix . 'COOLDOWN');
            if ($cooldown > 0) {
                array_unshift($limits, ['cooldown:' . $type . ':' . $identity, 1, $cooldown]);
            }
            foreach ($limits as $limit) {
                if (!$this->guardStore()->allow($limit[0], $limit[1], $limit[2], 1)) {
                    $this->guardLog($type, 'rate_limit');

                    return 'rate_limit';
                }
            }
        }
        if ((int) $this->guardConfig('ABUSE_ENABLED') === 1 && (int) $this->guardConfig('ABUSE_' . strtoupper($type)) === 1) {
            if ($this->isAbuseManagedByWaf()) {
                $this->guardLog($type, 'abuse_waf', true);
            } else {
                try {
                    if (random_int(1, 100) === 1) {
                        $this->guardStore()->cleanup();
                    }
                    $client = new AbuseClient($this->guardStore());
                    $result = $client->check($this->guardIp(), (string) $this->guardConfig('ABUSE_API_KEY'), (int) $this->guardConfig('ABUSE_BUDGET'));
                } catch (\Throwable $error) {
                    $result = ['status' => 'unavailable'];
                }
                if ($result['status'] !== 'checked') {
                    $this->guardLog($type, 'abuse_unavailable', true);
                } elseif ($result['score'] >= (int) $this->guardConfig('ABUSE_SCORE')) {
                    if ($this->guardConfig('ABUSE_MODE') === 'block') {
                        $this->guardLog($type, 'abuse_block');

                        return 'abuse_block';
                    }
                    $this->guardLog($type, 'abuse_monitor', true);
                }
            }
        }
        $this->protectionResults[$type . ':done'] = true;

        return '';
    }
}
