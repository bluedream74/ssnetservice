<?php

namespace App\Http\Controllers;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Response;
use App\Models\NotificationLog;
use App\Http\Controllers\Controller;
use Aws\Sns\Exception\InvalidSnsMessageException;

class SnsController extends Controller
{
    public function store()
    {
        // Only needed first time to get the subscription url to confirm the webhook
        // \Log::info(file_get_contents('php://input'));

        $message = Message::fromRawPostData();

        $validator = new MessageValidator();

        try {
            $validator->validate($message);
        } catch (InvalidSnsMessageException $e) {
            \Log::error('SNS Message Validation Error: '.$e->getMessage());
        }

        $messageBody = json_decode($message->offsetGet('Message'), true);

        $uniqueId = $this->getUniqueIdFromHeader($messageBody);
        $notificationLog = NotificationLog::where('message_id', $uniqueId)->first();

        if ($notificationLog === null) {
            return response()->json([], Response::HTTP_OK);
        }

        $notificationLog->update([
            'status' => $messageBody['eventType'],
        ]);

        if ($messageBody['eventType'] === 'Bounce') {
            \App\Models\CompanyEmail::where('email', $notificationLog->email)
                    ->update([
                        'is_valid'      => 0
                    ]);
        }

        return response()->json([], Response::HTTP_OK);
    }

    private function getUniqueIdFromHeader($messageBody)
    {
        return collect($messageBody['mail']['headers'])->filter(function ($header) {
            return $header['name'] === 'unique-id';
        })->map(function ($header) {
            return $header['value'];
        })->first();
    }

    public function openEmail()
    {
        NotificationLog::where('email', request()->get('email'))
                        ->where('contact_id', request()->get('contact_id'))
                        ->update(['status' => 'Open']);

        return response()->json(['data' => 'success']);
    }
}