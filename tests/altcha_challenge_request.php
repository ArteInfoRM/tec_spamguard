<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * Standalone controller regression checks without a PrestaShop installation.
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */
if (PHP_SAPI !== 'cli') {
    exit;
}

$parameter = '_tec_spamguard_challenge';
$cases = [
    ['missing parameter', [], [], 200],
    ['unrelated parameters', ['other' => 'value'], [], 200],
    ['zero', [$parameter => '0'], [], 200],
    ['browser cache buster', [$parameter => '1794307287123123456'], [], 200],
    ['maximum length', [$parameter => str_repeat('9', 32)], [], 200],
    ['leading zeroes', [$parameter => '000123'], [], 200],
    ['body value', [], [$parameter => '123'], 200],
    ['empty string', [$parameter => ''], [], 400],
    ['oversized value', [$parameter => str_repeat('1', 33)], [], 400],
    ['very large value', [$parameter => str_repeat('1', 65536)], [], 400],
    ['query array', [$parameter => ['123']], [], 400],
    ['nested body array', [], [$parameter => [['123']]], 400],
    ['null query value', [$parameter => null], [], 400],
    ['null body value', [], [$parameter => null], 400],
    ['integer value', [$parameter => 123], [], 400],
    ['boolean value', [$parameter => true], [], 400],
    ['nul byte', [$parameter => "12\0"], [], 400],
    ['body nul byte', [], [$parameter => "\0"], 400],
    ['encoded nul', [$parameter => '12%00'], [], 400],
    ['trailing newline', [$parameter => "123\n"], [], 400],
    ['carriage return', [$parameter => "123\r"], [], 400],
    ['tab', [$parameter => "12\t3"], [], 400],
    ['leading space', [$parameter => ' 123'], [], 400],
    ['trailing space', [$parameter => '123 '], [], 400],
    ['negative value', [$parameter => '-123'], [], 400],
    ['positive sign', [$parameter => '+123'], [], 400],
    ['decimal value', [$parameter => '12.3'], [], 400],
    ['unicode digits', [$parameter => "\xD9\xA1\xD9\xA2"], [], 400],
    ['invalid UTF-8', [$parameter => "\xFF"], [], 400],
    ['conflicting sources', [$parameter => '123'], [$parameter => '456'], 400],
    ['duplicate sources', [$parameter => '123'], [$parameter => '123'], 400],
];

if (isset($argv[1]) && $argv[1] === '--case') {
    $case = $cases[(int) $argv[2]];
    $_GET = $case[1];
    $_POST = $case[2];
    $GLOBALS['controller_initializations'] = 0;
    $GLOBALS['challenges_generated'] = 0;
    define('_PS_VERSION_', '1.7.8.11');

    /**
     * Record whether invalid input reaches parent content initialization.
     */
    class ModuleFrontController
    {
        public function initContent()
        {
            ++$GLOBALS['controller_initializations'];
        }
    }

    /**
     * Supply a local module fixture without bootstrapping the application.
     */
    class Module
    {
        public static function getInstanceByName($name)
        {
            return new Tec_spamguard();
        }
    }

    /**
     * Return a deterministic challenge fixture without keys or database access.
     */
    class Tec_spamguard extends Module
    {
        public function isLocalAltchaActive()
        {
            return true;
        }

        public function createAltchaChallenge()
        {
            ++$GLOBALS['challenges_generated'];

            return ['challenge' => 'fixture-challenge'];
        }
    }

    register_shutdown_function(function () {
        fwrite(STDERR, json_encode([
            'status' => http_response_code(),
            'initializations' => $GLOBALS['controller_initializations'],
            'challenges' => $GLOBALS['challenges_generated'],
        ]));
    });
    require dirname(__DIR__) . '/controllers/front/altchachallenge.php';
    $controller = new Tec_SpamguardaltchachallengeModuleFrontController();
    $controller->initContent();
    exit;
}

foreach ($cases as $index => $case) {
    $command = escapeshellarg(PHP_BINARY) . ' -d xdebug.mode=off ' . escapeshellarg(__FILE__) . ' --case ' . $index;
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start controller test process.');
    }
    fclose($pipes[0]);
    $body = json_decode(stream_get_contents($pipes[1]), true);
    fclose($pipes[1]);
    $metadata = json_decode(stream_get_contents($pipes[2]), true);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $accepted = $case[3] === 200;
    $expectedBody = $accepted ? ['challenge' => 'fixture-challenge'] : ['error' => 'Invalid challenge request'];
    $expectedMetadata = ['status' => $case[3], 'initializations' => (int) $accepted, 'challenges' => (int) $accepted];
    if ($exitCode !== 0 || $body !== $expectedBody || $metadata !== $expectedMetadata) {
        fwrite(STDERR, 'FAIL: ' . $case[0] . PHP_EOL);
        exit(1);
    }
}

echo count($cases) . ' ALTCHA controller request checks passed.' . PHP_EOL;
