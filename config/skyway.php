<?php

return [
    'api' => [
        'key' => env('SKYWAY_API_KEY', '4f0e1b76-2bbd-4771-b00b-09a4de1b0847'),
    ],
    'message' => [
        'cam' => [
            'no_reservation' => '予約がありません。',
            'no_access' => 'アクセスできません。',
            'no_student' => '予約した生徒はヒットしませんでした。',
            'already_finished' => '予約はすでに終了されました。',
            'not_started' => 'まだ予約時間になっていません。'
        ]
    ]
];
