<?php

return [
    'limit' => [
        'home' => [
            'per_page' => 12,
            'new_list_time' => 1, // month # a month ago
        ],
        'admin' => [
            'suggest' => 20,
        ],
    ],
    'tiny_key' => env('TinyMCE_API_KEY', '4bbprqrisanhhxgxv0dahywoqfmz49zw2vfsjb8vtk6k72tt'),
    'skyway_key' => env('SKYWAY_API_KEY', '')
];
