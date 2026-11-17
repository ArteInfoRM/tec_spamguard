<?php
/**
 * 2009-2026 Tecnoacquisti.com
 * @license MIT License
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
define('_PS_VERSION_', '1.7.7.0');
require dirname(__DIR__) . '/src/Protection/FormProof.php';
require dirname(__DIR__) . '/src/Protection/ConfigurationTrait.php';

/** Isolated request input. */
class Tools
{
    public static $input = [];
    public static function getValue($key) { return isset(self::$input[$key]) ? self::$input[$key] : false; }
}

/** Record configuration writes without a database. */
class Configuration
{
    public static $writes = [];
    public static function updateValue($key, $value, $html, $group, $shop)
    {
        if ($group !== 3 || $shop !== 7) { throw new RuntimeException('Wrong configuration scope.'); }
        self::$writes[$key] = $value;
        return true;
    }
}

/** Select an explicit shop or all-shop context. */
class Shop
{
    public const CONTEXT_SHOP = 1;
    public static $scope = 1;
    public static function getContext() { return self::$scope; }
}

/** Exercise the actual form validation without rendering templates. */
class ProtectionConfigurationProbe
{
    use \TecSpamGuard\Protection\ConfigurationTrait;
    public const CONFIG_PREFIX = 'TEC_SPAMGUARD_';
    public $context;
    public function rules($abuse) { return $this->guardRules($abuse); }
    public function save($abuse) { return $this->saveProtectionConfiguration($abuse); }
    public function l($message) { return $message; }
    public function displayError($message) { return 'error'; }
    public function displayConfirmation($message) { return 'saved'; }
    public function isAbuseManagedByWaf() { return false; }
    private function guardConfig($key) { return str_repeat('a', 32); }
}
$probe = new ProtectionConfigurationProbe();
$probe->context = (object) ['shop' => (object) ['id' => 7, 'id_shop_group' => 3]];
foreach ([false, true] as $abuse) {
    $valid = [];
    foreach ($probe->rules($abuse) as $key => $rule) {
        $valid['TEC_SPAMGUARD_' . $key] = $rule === 'secret' ? '********aaaa' : (string) $rule[0];
    }
    Tools::$input = $valid;
    Configuration::$writes = [];
    if ($probe->save($abuse) !== 'saved') { throw new RuntimeException('Valid configuration rejected.'); }
    if ($abuse && Configuration::$writes['TEC_SPAMGUARD_ABUSE_API_KEY'] !== str_repeat('a', 32)) {
        throw new RuntimeException('Masked secret was overwritten.');
    }
    foreach ($valid as $key => $value) {
        Tools::$input = $valid;
        Tools::$input[$key] = [];
        Configuration::$writes = [];
        if ($probe->save($abuse) !== 'error' || Configuration::$writes) { throw new RuntimeException('Array input caused writes.'); }
    }
    Tools::$input = $valid;
    Shop::$scope = 0;
    Configuration::$writes = [];
    if ($probe->save($abuse) !== 'error' || Configuration::$writes) { throw new RuntimeException('All-shop save accepted.'); }
    Shop::$scope = 1;
}
echo 'PASS configuration allowlists, array rejection, secret masking and shop scope' . PHP_EOL;
