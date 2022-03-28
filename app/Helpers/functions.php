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
        45 => 45,
        50 => 50,
        55 => 55,
        60 => 60,
        65 => 65,
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

function getPlans()
{
    return [
        'price_1K3wuGLwiZkAtY2DIUb3WrRN' => '44,000円',
        'price_1K3wuGLwiZkAtY2Depoa7i5t' => '66,000円',
        'price_1K3wuGLwiZkAtY2DTn5E65de' => '77,000円',
        'price_1K3wuGLwiZkAtY2DqNAWPUrT' => '88,000円',
        'price_1K3wuGLwiZkAtY2D7v22VAUM' => '132,000円',
        'price_1K3wuGLwiZkAtY2D6XRAFbA0' => '176,000円',
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
	'フォームなし'       => 'フォームなし',
        '制約'              => '制約'
    ];
}

function getOptionsUnsubscribe()
{
    return [
        '非表示する',
        '表示する',
    ];
}
