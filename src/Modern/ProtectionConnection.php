<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

declare(strict_types=1);

namespace TecSpamGuard\Modern;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Doctrine DBAL adapter for PrestaShop 8 and later. */
final class ProtectionConnection
{
    /** @var \Doctrine\DBAL\Connection Injected connection. */
    private $connection;

    /** Keep the modern connection dependency out of legacy loading paths. */
    public function __construct(\Doctrine\DBAL\Connection $connection)
    {
        $this->connection = $connection;
    }

    /** Execute a statement on DBAL 2 and 3. */
    public function execute(string $sql): void
    {
        if (method_exists($this->connection, 'executeStatement')) {
            $this->connection->executeStatement($sql);
        } else {
            $this->connection->executeUpdate($sql);
        }
    }

    /** Read a scalar on DBAL 2 and 3. */
    public function value(string $sql)
    {
        return $this->connection->fetchOne($sql);
    }

    /** Quote with the actual database driver. */
    public function quote(string $value): string
    {
        return $this->connection->quote($value);
    }
}
