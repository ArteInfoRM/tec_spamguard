<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

/** Run only from CLI inside an authorized local PrestaShop test installation. */
if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__, 3) . '/config/config.inc.php';

/** Assert a security invariant without exposing input data. */
function guardAssert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . PHP_EOL;
}

/** Invoke internal hooks under the real PrestaShop runtime. */
function guardCall($module, $method, array $arguments = [])
{
    $reflection = new ReflectionMethod($module, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($module, $arguments);
}

$module = Module::getInstanceByName('tec_spamguard');
guardAssert($module->installAdvancedProtection(), 'advanced schema and hooks available');
$store = guardCall($module, 'guardStore');
$session = bin2hex(random_bytes(32));
$secret = bin2hex(random_bytes(32));
$names = \TecSpamGuard\Protection\FormProof::names();
guardAssert(count(array_unique($names)) === 50, 'fifty distinct allowlisted field names');
$token = \TecSpamGuard\Protection\FormProof::issue($secret, $session, 1, 'register', $names[0]);
$verify = ['TecSpamGuard\\Protection\\FormProof', 'verify'];
$proof = $verify($token, $secret, $session, 1, 'register');
guardAssert(!empty($proof), 'valid proof accepted');
guardAssert(!$verify($token, $secret, $session, 2, 'register'), 'cross-shop proof rejected');
guardAssert(!$verify($token, $secret, $session, 1, 'contact'), 'cross-form proof rejected');
guardAssert(!$verify($token, $secret, 'other', 1, 'register'), 'cross-session proof rejected');
guardAssert(!$verify($token . 'a', $secret, $session, 1, 'register'), 'tampered proof rejected');
guardAssert(!$verify([], $secret, $session, 1, 'register'), 'array proof rejected');
$expiredData = [1, 'register', $names[0], time() - 1900, bin2hex(random_bytes(16)), hash_hmac('sha256', $session, $secret)];
$expiredPayload = base64_encode(json_encode($expiredData));
$expiredToken = $expiredPayload . '.' . hash_hmac('sha256', $expiredPayload, $secret);
guardAssert(!$verify($expiredToken, $secret, $session, 1, 'register'), 'expired signed proof rejected');
guardAssert($proof['field'] === $names[0], 'signed field retained independently of current setting');

guardAssert($store->consume($proof['nonce'], 60), 'first use reserved');
guardAssert(!$store->consume($proof['nonce'], 60), 'replayed proof rejected');
$key = 'test:' . bin2hex(random_bytes(16));
guardAssert($store->allow($key, 2, 60, 60), 'first admission allowed');
guardAssert($store->allow($key, 2, 60, 60), 'second admission allowed');
guardAssert(!$store->allow($key, 2, 60, 60), 'third admission rejected');
$store->failure($key . ':fail', 2, 60, 60);
guardAssert(!$store->blocked($key . ':fail'), 'first failure does not suspend');
$store->failure($key . ':fail', 2, 60, 60);
guardAssert($store->blocked($key . ':fail'), 'second failure suspends');
$otherShop = new \TecSpamGuard\Protection\Store(new \TecSpamGuard\Legacy\ProtectionConnection(), _DB_PREFIX_, 999999);
guardAssert(!$otherShop->get($key), 'counter isolation across shops');
$client = new \TecSpamGuard\Protection\AbuseClient($store);
guardAssert($client->check('127.0.0.1', str_repeat('a', 80), 10)['status'] === 'unavailable', 'private IP never sent to API');
$store->put('expired-test:' . $key, ['test' => true], 1);

$context = Context::getContext();
$context->controller = new ContactController();
$fields = ['GUARD_HONEYPOT', 'GUARD_CONTACT', 'GUARD_LIMITS', 'ABUSE_ENABLED'];
$original = [];
foreach ($fields as $field) {
    $original[$field] = Configuration::get('TEC_SPAMGUARD_' . $field);
}
try {
    Configuration::updateValue('TEC_SPAMGUARD_GUARD_HONEYPOT', 1);
    Configuration::updateValue('TEC_SPAMGUARD_GUARD_CONTACT', 1);
    Configuration::updateValue('TEC_SPAMGUARD_GUARD_LIMITS', 0);
    Configuration::updateValue('TEC_SPAMGUARD_ABUSE_ENABLED', 0);
    $html = '<form method="post"><input name="from"><button name="submitMessage">Send</button></form>';
    $module->hookActionOutputHTMLBefore(['html' => &$html]);
    guardAssert(strpos($html, 'name="tec_sg_proof"') !== false, 'server-rendered contact proof without JavaScript');
    preg_match('/name="tec_sg_proof" value="([^"]+)"/', $html, $matches);
    $formToken = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    $field = Configuration::get('TEC_SPAMGUARD_GUARD_FIELD');
    $_POST = ['tec_sg_proof' => $formToken, $field => ''];
    guardAssert(guardCall($module, 'guardBefore', ['contact']) === '', 'empty trap accepted');
    guardAssert(guardCall($module, 'guardBefore', ['contact']) === '', 'fallback hook does not consume twice');
    $module = new Tec_spamguard();
    $_POST = ['tec_sg_proof' => $formToken, $field => ''];
    guardAssert(guardCall($module, 'guardBefore', ['contact']) === 'form_replay', 'replay rejected across module requests');
    $html = '<form><button name="submitMessage">Send</button></form>';
    $module->hookActionOutputHTMLBefore(['html' => &$html]);
    preg_match('/name="tec_sg_proof" value="([^"]+)"/', $html, $matches);
    $_POST = ['tec_sg_proof' => html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), $field => 'bot'];
    guardAssert(guardCall($module, 'guardBefore', ['contact']) === 'honeypot', 'filled trap rejected before CAPTCHA');
    $_POST = ['tec_sg_proof' => html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8')];
    guardAssert(guardCall($module, 'guardBefore', ['contact']) === 'form_invalid', 'omitted trap rejected');
    $_POST[$field] = [];
    guardAssert(guardCall($module, 'guardBefore', ['contact']) === 'form_invalid', 'array trap rejected');
    $rules = guardCall($module, 'guardRules', [false]);
    guardAssert(count($rules) === 30, 'per-form settings complete');
} finally {
    foreach ($original as $field => $value) {
        Configuration::updateValue('TEC_SPAMGUARD_' . $field, $value);
    }
}
echo 'Advanced protection checks completed on ' . _PS_VERSION_ . PHP_EOL;
