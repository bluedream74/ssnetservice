<?php // 01DPX3QG0D0QKMFRST1R1F2DAB : 01DPX3QG0D0QKMFRST1R1F2DAB

namespace App\Mail;

use App\Services\Helpers\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

class BaseMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $data = [];

    protected $viewName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        $data = [];
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $viewPath = EmailService::getLayoutPath($this->viewName);

        if (View::exists($viewPath)) {
            $view = View::make($viewPath, $this->data)->renderSections();
            $subject = trim($view['subject']);
            $html = (!isset($view['html']) || trim($view['html']) == false) ? null : new HtmlString($view['html']);
            $text = (!isset($view['text']) || trim($view['text']) == false) ? null : new HtmlString($view['text']);
            $raw = (!isset($view['raw']) || trim($view['raw']) == false) ? null : $view['raw'];

            return $this->subject($subject)->view(compact('html', 'text', 'raw'));
        }
    }
}
