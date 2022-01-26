<?php // 01DPX3QG0D0QKMFRST1R1F2DAB : 01DPX3QG0D0QKMFRST1R1F2DAB

namespace App\Mail\User;

use App\Mail\BaseMail;
use App\Services\Helpers\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class Register extends BaseMail
{
    use Queueable, SerializesModels;

    protected $viewName = 'user.verify';

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->data = [
            'user' => $user,
            'app_name' => config('app.name'),
            'url_verify' => route('user.verify', ['code' => $user->confirmation_code]),
        ];

        parent::__construct();
    }
}
