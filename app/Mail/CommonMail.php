<?php

namespace App\Mail;

use App\Mail\BaseMail;

class CommonMail extends BaseMail
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data, $viewName)
    {
        $this->data = $data;
        $this->viewName = $viewName;

        parent::__construct();
    }
}
