<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

namespace TecSpamGuard\Protection {
    /** Deterministic API transport: these tests never contact AbuseIPDB. */
    function curl_init($url) { ++$GLOBALS['sg_api_calls']; return fopen('php://memory', 'r+'); }
    function curl_setopt_array($handle, $options) { $GLOBALS['sg_api_options'] = $options; return true; }
    function curl_exec($handle) {
        $options = $GLOBALS['sg_api_options'];
        $body = json_encode(['data' => ['ipAddress' => '8.8.8.8', 'abuseConfidenceScore' => $GLOBALS['sg_api_score']]]);
        $options[CURLOPT_WRITEFUNCTION]($handle, $body);
        $options[CURLOPT_HEADERFUNCTION]($handle, "Retry-After: 120\r\n");
        return true;
    }
    function curl_getinfo($handle, $option) { return $GLOBALS['sg_api_status']; }
    function curl_close($handle) { fclose($handle); }
}
namespace {
    if (PHP_SAPI !== 'cli') { exit; }
    require dirname(__DIR__, 3) . '/config/config.inc.php';
    $module = Module::getInstanceByName('tec_spamguard');
    $method = new ReflectionMethod($module, 'guardStore');
    $method->setAccessible(true);
    $store = $method->invoke($module);
    $client = new \TecSpamGuard\Protection\AbuseClient($store);
    $GLOBALS['sg_api_calls'] = 0;
    $GLOBALS['sg_api_status'] = 200;
    $GLOBALS['sg_api_score'] = 95;
    $key = bin2hex(random_bytes(32));
    $result = $client->check('8.8.8.8', $key, 1);
    if ($result !== ['status' => 'checked', 'score' => 95]) { throw new RuntimeException('Score parsing failed.'); }
    $client->check('8.8.8.8', $key, 1);
    if ($GLOBALS['sg_api_calls'] !== 1) { throw new RuntimeException('Cache failed.'); }
    if ($client->check('1.1.1.1', $key, 1)['status'] !== 'budget' || $GLOBALS['sg_api_calls'] !== 1) { throw new RuntimeException('Budget failed.'); }
    $GLOBALS['sg_api_status'] = 429;
    $key = bin2hex(random_bytes(32));
    if ($client->check('8.8.8.8', $key, 10)['status'] !== 'unavailable') { throw new RuntimeException('Quota handling failed.'); }
    $before = $GLOBALS['sg_api_calls'];
    $client->check('1.1.1.1', $key, 10);
    if ($GLOBALS['sg_api_calls'] !== $before) { throw new RuntimeException('Retry-After pause failed.'); }
    $GLOBALS['sg_api_status'] = 200;
    $GLOBALS['sg_api_score'] = 101;
    if ($client->check('8.8.8.8', bin2hex(random_bytes(32)), 10)['status'] !== 'unavailable') { throw new RuntimeException('Invalid score accepted.'); }
    echo 'PASS reputation cache, budget, 429 backoff and invalid score; API transport mocked' . PHP_EOL;
}
