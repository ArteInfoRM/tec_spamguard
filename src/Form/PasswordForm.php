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

class PasswordForm extends AbstractForm
{
    public function getType()
    {
        return 'password';
    }

    public function isSubmitted()
    {
        return $this->context->controller instanceof \PasswordController
            && ($this->hasSubmit('email') || $this->hasSubmit('submit'));
    }

    public function getEmail()
    {
        return $this->input('email');
    }
}
