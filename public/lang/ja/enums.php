<?php

use App\Enums\CategoryStatus;
use App\Enums\ReservationStatus;
use App\Enums\RequestCancelPlanStatus;
use App\Enums\BlogCategoryStatus;

return [
    CategoryStatus::class => [
        CategoryStatus::REQUESTED => '申請中',
        CategoryStatus::CONFIRMED => '承認',
        CategoryStatus::CANCELED => '却下',
    ],
    ReservationStatus::class => [
        ReservationStatus::PENDING => '未入金',
        ReservationStatus::CONFIRMED => '入金済み',
    ],
    RequestCancelPlanStatus::class => [
        RequestCancelPlanStatus::REQUESTED => '申請中',
        RequestCancelPlanStatus::CONFIRMED => '承認',
    ],
    BlogCategoryStatus::class => [
        BlogCategoryStatus::ACTIVE => '有効',
        BlogCategoryStatus::INACTIVE => '無効',
    ],
];
