<?php
/**
 * 2009-2026 Tecnoacquisti.com
 *
 * @author    Arte e Informatica <helpdesk@tecnoacquisti.com>
 * @copyright 2009-2026 Arte e Informatica
 * @license   One Paid Licence By WebSite Using This Module. No Rent. No Sell. No Share.
 */

namespace TecSpamGuard\Form;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface FormInterface
{
    public function getType();

    public function isSubmitted();

    public function getEmail();

    public function getMessage();
}
