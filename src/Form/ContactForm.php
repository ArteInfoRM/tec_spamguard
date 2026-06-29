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

class ContactForm extends AbstractForm
{
    public function getType()
    {
        return 'contact';
    }

    public function isSubmitted()
    {
        return $this->context->controller instanceof \ContactController
            && $this->hasSubmit('submitMessage');
    }

    public function getEmail()
    {
        return $this->input('from');
    }

    public function getMessage()
    {
        return $this->input('message');
    }
}
