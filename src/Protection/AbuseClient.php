<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

declare(strict_types=1);

namespace TecSpamGuard\Protection;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Read-only reputation lookup with bounded latency, cache and account budget. */
final class AbuseClient
{
    /** @var Store Shared expiring storage. */
    private $store;

    /** Inject persistence so API requests are deduplicated across forms. */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /** Return a validated score or an explicit unavailable result; never report IPs. */
    public function check(string $ip, string $key, int $budget): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            || !preg_match('/^[a-zA-Z0-9]{20,256}$/D', $key) || !function_exists('curl_init')) {
            return ['status' => 'unavailable'];
        }
        $account = hash('sha256', $key);
        return $this->store->locked('abuse:' . $account . ':' . $ip, function () use ($ip, $key, $account, $budget) {
            $cacheKey = 'abuse-cache:' . $account . ':' . $ip;
            $cached = $this->store->get($cacheKey);
            if ($cached) {
                return $cached;
            }
            if ($this->store->get('abuse-stop:' . $account)) {
                return ['status' => 'quota_or_service'];
            }
            if (!$this->store->allow('abuse-budget:' . $account . ':' . gmdate('Y-m-d'), $budget, 86400, 60)) {
                return ['status' => 'budget'];
            }
            $body = '';
            $retry = 300;
            $ch = curl_init('https://api.abuseipdb.com/api/v2/check?' . http_build_query([
                'ipAddress' => $ip, 'maxAgeInDays' => 30,
            ]));
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Key: ' . $key, 'Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT => 1,
                CURLOPT_TIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use (&$body) {
                    if (strlen($body) + strlen($chunk) > 32768) {
                        return 0;
                    }
                    $body .= $chunk;

                    return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$retry) {
                    if (preg_match('/^Retry-After:\s*(\d+)\s*$/i', trim($line), $match)) {
                        $retry = max(60, min(86400, (int) $match[1]));
                    }

                    return strlen($line);
                },
            ]);
            $ok = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($ok === false || $http !== 200) {
                $this->store->put('abuse-stop:' . $account, ['stopped' => true], $http === 429 ? $retry : 60);

                return ['status' => 'unavailable'];
            }
            $decoded = json_decode($body, true);
            $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
            if (!isset($data['ipAddress'], $data['abuseConfidenceScore'])
                || !is_string($data['ipAddress']) || @inet_pton($data['ipAddress']) !== inet_pton($ip)
                || !is_int($data['abuseConfidenceScore']) || $data['abuseConfidenceScore'] < 0 || $data['abuseConfidenceScore'] > 100) {
                return ['status' => 'unavailable'];
            }
            $result = ['status' => 'checked', 'score' => $data['abuseConfidenceScore']];
            $this->store->put($cacheKey, $result, 3600);

            return $result;
        });
    }
}
