<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AuthController as BaseAuthController;

class AuthController extends BaseAuthController
{
    protected $redirectTo = 'admin/dashboard';

    public function __construct()
    {
        $this->guardName = 'admin';
        parent::__construct();
    }
}
