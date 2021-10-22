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

function getSubSources($sourceId = "all")
{
    if ($sourceId=="all") return \App\Models\SubSource::orderBy('sort_no')->pluck('name', 'sort_no');
    
    return \App\Models\SubSource::whereNotNull('name')->where('source_id',$sourceId)->orderBy('sort_no')->pluck('name', 'sort_no');
}

function getUrls($needAll = false)
{
    if ($needAll) return \App\Models\Source::orderBy('sort_no')->pluck('url', 'sort_no');

    return \App\Models\Source::whereNotNull('url')->orderBy('sort_no')->pluck('url', 'sort_no');
}

function getMailLimits()
{
    return [
        70 => 70,
        71 => 71,
        77 => 77,
        73 => 73, 
        74 => 74, 
        75 => 75,
        76 => 76,
        77 => 77,
        78 => 78,
        79 => 79,
        80 => 80,
    ];
}

function getLimits()
{
    return [
        1 => 391,
        2 => 382,
        3 => 278,
        4 => 10000,
        2 => 10000,
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