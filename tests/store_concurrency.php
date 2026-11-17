<?php
/**
 * 2009-2026 Tecnoacquisti.com
 * @license MIT License
 */
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !preg_match('/^[a-f0-9]{32}$/D', $argv[1])) {
    exit(1);
}
require dirname(__DIR__, 3) . '/config/config.inc.php';
$module = Module::getInstanceByName('tec_spamguard');
$method = new ReflectionMethod($module, 'guardStore');
$method->setAccessible(true);
$store = $method->invoke($module);
echo $store->consume('concurrency-test:' . $argv[1], 60) ? 'accepted' : 'rejected';
