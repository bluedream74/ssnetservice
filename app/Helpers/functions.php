<?php

use Illuminate\Support\Str;

function generate_rand_password($length = 8)
{
    return str_random($length);
}

function truncate($str, $limit, $postfix)
{
    $str = preg_replace("/<img[^>]+\>/i", "", $str);

    return Str::limit($str, $limit, $postfix);
}

function getSources($needAll = false)
{
    if ($needAll) return \App\Models\Source::orderBy('sort_no')->pluck('name', 'sort_no');
    
    return \App\Models\Source::whereNotNull('url')->orderBy('sort_no')->pluck('name', 'sort_no');
}

function getUrls($needAll = false)
{
    if ($needAll) return \App\Models\Source::orderBy('sort_no')->pluck('url', 'sort_no');

    return \App\Models\Source::whereNotNull('url')->orderBy('sort_no')->pluck('url', 'sort_no');
}

function getMailLimits()
{
    return [
        40 => 40,
        41 => 41,
        42 => 42,
        43 => 43,
        44 => 44,
        45 => 45,
        46 => 46,
        47 => 47,
        48 => 48,
        49 => 49,
        50 => 50,
        51 => 51,
        52 => 52,
        53 => 53, 
        54 => 54, 
        55 => 55,
        56 => 56,
        57 => 57,
        58 => 58,
        59 => 59,
        60 => 60,
    ];
}

function getLimits()
{
    return [
        1 => 391,
        2 => 382,
        3 => 278,
        4 => 10000,
        5 => 10000,
        6 => 1
    ];
}

function getCompanyStatuses()
{
    return [
        '未対応'            => '未対応',
        '送信済み'           => '送信済み',
        '送信失敗'           => '送信失敗',
        '荷電済み'           => '荷電済み',
        '進行中'             => '進行中',
        '拒絶'              => '拒絶',
        '制約'              => '制約'
    ];
}