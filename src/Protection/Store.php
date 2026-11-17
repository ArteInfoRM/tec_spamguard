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

/** Scoped, expiring storage for counters, consumed tokens and reputation results. */
final class Store
{
    /** @var object Database adapter selected by the module entry point. */
    private $connection;
    /** @var string Validated table identifier. */
    private $table;
    /** @var string Shop-specific key namespace. */
    private $scope;

    /** Construct with an explicit database adapter and shop. */
    public function __construct($connection, string $prefix, int $shop)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $prefix) || $shop < 1) {
            throw new \InvalidArgumentException('Invalid protection storage scope.');
        }
        $this->connection = $connection;
        $this->table = '`' . $prefix . 'tec_spamguard_guard`';
        $this->scope = (string) $shop;
    }

    /** Create the shared expiring key store during installation or upgrade. */
    public function install(): bool
    {
        $this->connection->execute('CREATE TABLE IF NOT EXISTS ' . $this->table . ' (
            key_hash CHAR(64) NOT NULL PRIMARY KEY,
            payload TEXT NOT NULL,
            expires_at BIGINT UNSIGNED NOT NULL,
            INDEX expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        return true;
    }

    /** Remove only this module table during uninstall. */
    public function uninstall(): bool
    {
        $this->connection->execute('DROP TABLE IF EXISTS ' . $this->table);

        return true;
    }

    /** Hash key material so identifiers are never persisted in clear text. */
    private function key(string $key): string
    {
        return hash_hmac('sha256', $this->scope . ':' . $key, (string) _COOKIE_KEY_);
    }

    /** Read a live value. */
    public function get(string $key): array
    {
        $value = $this->connection->value('SELECT payload FROM ' . $this->table
            . ' WHERE key_hash=' . $this->connection->quote($this->key($key))
            . ' AND expires_at>' . time());
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /** Persist bounded metadata with expiry, never raw request contents. */
    public function put(string $key, array $value, int $seconds): void
    {
        $payload = json_encode($value);
        if (!is_string($payload) || strlen($payload) > 4096) {
            throw new \InvalidArgumentException('Invalid protection payload.');
        }
        $this->connection->execute('INSERT INTO ' . $this->table . ' (key_hash,payload,expires_at) VALUES ('
            . $this->connection->quote($this->key($key)) . ',' . $this->connection->quote($payload)
            . ',' . (time() + max(1, $seconds)) . ') ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)');
    }

    /** Serialize changes to a specific counter or token across concurrent requests. */
    public function locked(string $key, callable $operation)
    {
        $lock = 'sg:' . substr($this->key($key), 0, 60);
        if ((int) $this->connection->value('SELECT GET_LOCK(' . $this->connection->quote($lock) . ',2)') !== 1) {
            throw new \RuntimeException('Protection storage busy.');
        }
        try {
            return $operation();
        } finally {
            $this->connection->value('SELECT RELEASE_LOCK(' . $this->connection->quote($lock) . ')');
        }
    }

    /** Atomically reserve an operation; rejected requests do not extend the pause. */
    public function allow(string $key, int $limit, int $window, int $pause): bool
    {
        return $this->locked($key, function () use ($key, $limit, $window, $pause) {
            $state = $this->get($key);
            $now = time();
            if (!empty($state['blocked']) && $state['blocked'] > $now) {
                return false;
            }
            if (empty($state['until']) || $state['until'] <= $now) {
                $state = ['count' => 0, 'until' => $now + $window];
            }
            if ($state['count'] >= $limit) {
                $state['blocked'] = $now + $pause;
                $this->put($key, $state, max($window, $pause));

                return false;
            }
            ++$state['count'];
            $this->put($key, $state, max(1, $state['until'] - $now));

            return true;
        });
    }

    /** Count failures and start a pause on reaching the threshold. */
    public function failure(string $key, int $limit, int $window, int $pause): void
    {
        $this->locked($key, function () use ($key, $limit, $window, $pause) {
            $state = $this->get($key);
            $now = time();
            if (!empty($state['blocked']) && $state['blocked'] > $now) {
                return;
            }
            if (empty($state['until']) || $state['until'] <= $now) {
                $state = ['count' => 0, 'until' => $now + $window];
            }
            ++$state['count'];
            if ($state['count'] >= $limit) {
                $state['blocked'] = $now + $pause;
            }
            $this->put($key, $state, max($window, $pause));
        });
    }

    /** Check an existing pause without incrementing a counter. */
    public function blocked(string $key): bool
    {
        $state = $this->get($key);

        return isset($state['blocked']) && $state['blocked'] > time();
    }

    /** Consume a token once, including concurrent submissions. */
    public function consume(string $nonce, int $seconds): bool
    {
        return $this->locked('token:' . $nonce, function () use ($nonce, $seconds) {
            if ($this->get('token:' . $nonce)) {
                return false;
            }
            $this->put('token:' . $nonce, ['used' => true], $seconds);

            return true;
        });
    }

    /** Bound retention cleanup work per request. */
    public function cleanup(): void
    {
        $this->connection->execute('DELETE FROM ' . $this->table . ' WHERE expires_at<' . time() . ' LIMIT 200');
    }
}
