<?php // 01DPX3QG0D0QKMFRST1R1F2DAB : 01DPX3QG0D0QKMFRST1R1F2DAB

namespace App\Services\Helpers;

use Illuminate\Support\Facades\Mail;

class EmailService
{
    public static function send($mail, $emailTo, $cc = [], $bcc = [])
    {
        Mail::to($emailTo)
            ->cc($cc)
            ->bcc($bcc)
            ->send($mail);
    }

    public static function getLayoutPath($layout)
    {
        return 'emails.' . $layout;
    }
}
