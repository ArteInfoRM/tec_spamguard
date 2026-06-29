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

abstract class AbstractForm implements FormInterface
{
    protected $context;

    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    public function getEmail()
    {
        return '';
    }

    public function getMessage()
    {
        return '';
    }

    protected function input($name)
    {
        return trim((string) \Tools::getValue($name));
    }

    protected function hasSubmit($name)
    {
        return \Tools::isSubmit($name);
    }
}
