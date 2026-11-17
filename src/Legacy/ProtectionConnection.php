<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   MIT License
 */

namespace TecSpamGuard\Legacy;

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Legacy database adapter isolated from the modern DBAL path. */
final class ProtectionConnection
{
    /** Execute a statement and surface persistence failure. */
    public function execute($sql)
    {
        if (!\Db::getInstance()->execute($sql)) {
            throw new \RuntimeException('Protection storage unavailable.');
        }
    }

    /** Read a scalar without application query caching. */
    public function value($sql)
    {
        return \Db::getInstance()->getValue($sql, false);
    }

    /** Quote a string for the legacy SQL connection. */
    public function quote($value)
    {
        return "'" . pSQL($value, true) . "'";
    }
}
