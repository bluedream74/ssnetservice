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
         6 => 6,
        7 => 7,
        8 => 8,
        9 => 9,
        10 => 10,
        11 => 11,
        12 => 12,
        13 => 13,
        14 => 14,
        15 => 15,
        16 => 16,
        17 => 17,
        18 => 18,
        19 => 19,
        20 => 20,
        21 => 21,
        22 => 22,
        23 => 23, 
        24 => 24, 
        25 => 25
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