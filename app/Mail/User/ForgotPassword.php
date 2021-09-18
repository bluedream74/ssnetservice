<?php // 01DPX3QG0D0QKMFRST1R1F2DAB : 01DPX3QG0D0QKMFRST1R1F2DAB

namespace App\Mail\User;

use App\Mail\BaseMail;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends BaseMail
{
    use Queueable, SerializesModels;

    protected $viewName = 'user.forgot_password';

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $token)
    {
        $this->data = [
            'user' => $user,
            'app_name' => config('app.name'),
            'url_reset' => url(route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ], false))
        ];

        parent::__construct();
    }
}
