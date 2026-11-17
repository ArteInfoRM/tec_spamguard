<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
define('_PS_VERSION_', '1.7.7.0');

/** Minimal module state for an isolated, side-effect-free integration test. */
class Module
{
    public static $enabled = false;
    public static function isEnabled($name)
    {
        if ($name !== 'tec_blockaibots') {
            throw new RuntimeException('Unexpected WAF module identifier.');
        }

        return self::$enabled;
    }
}

/** Record that settings are queried in the current shop. */
class Configuration
{
    public static $enabled = 0;
    public static function get($key, $lang, $group, $shop)
    {
        if ($key !== 'TEC_BLOCKAIBOTS_ABUSEIPDB' || $shop !== 7 || $group !== 3) {
            throw new RuntimeException('Incorrect WAF configuration scope.');
        }

        return self::$enabled;
    }
}
require dirname(__DIR__) . '/src/Protection/ModuleProtectionTrait.php';

/** Expose only the integration decision under test. */
class WafProbe
{
    use \TecSpamGuard\Protection\ModuleProtectionTrait;
    public $context;
}
$probe = new WafProbe();
$probe->context = (object) ['shop' => (object) ['id' => 7, 'id_shop_group' => 3]];
foreach ([[false, 0, false], [false, 1, false], [true, 0, false], [true, 1, true]] as $case) {
    Module::$enabled = $case[0];
    Configuration::$enabled = $case[1];
    if ($probe->isAbuseManagedByWaf() !== $case[2]) {
        throw new RuntimeException('WAF activation decision failed.');
    }
}
echo 'PASS WAF enabled/configured combinations and explicit shop scope' . PHP_EOL;
