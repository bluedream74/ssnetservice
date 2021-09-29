<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Services\OrderService;

class OrderExport implements FromCollection, WithHeadings, WithMapping
{
    protected $params = array();

    /**
    * Optional headers
    */
    private $headers = [
        'Content-Type' => 'text/csv',
    ];

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function headings(): array
    {
        return ["注文ID", "カウンセラー", "お客様", "住所", "注文商品", "商品売上", "カウンセラーの報酬", "注文日時"];
    }

    /**
    * @var Order $order
    */
    public function map($order): array
    {
        $productStr = "";
        foreach ($order->items as $item) {
            $productStr = "{$productStr}{$item->product->title} x {$item->tax_price}円 x {$item->amount}個\n";
        }

        return [
            $order->id, 
            $order->tutor ? $order->tutor->user->name : '',
            $order->student ? $order->student->user->name : '',
            $order->full_address,
            $productStr,
            $order->total_price + $order->delivery_fee,
            $order->tutor_return * getTutorTax(),
            \Carbon\Carbon::parse($order->created_at)->format("Y-m-d H:i")
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $service = new OrderService();
        $condition = [];
        $compare = [];

        if (!empty($value = Arr::get($this->params, 'month'))) {
            $condition['created_at'] = $value;
            $compare['created_at'] = 'like';
        }

        if (!empty($value = Arr::get($this->params, 'tutor'))) {
            $user = \App\Models\User::find($value);
            if (isset($user)) {
                $condition['tutor_id'] = $user->tutor->id;
            }
        }

        if (!empty($value = Arr::get($this->params, 'user'))) {
            $user = \App\Models\User::find($value);
            if (isset($user)) {
                $condition['student_id'] = $user->student->id;
            }
        }

        return $service->search($condition, $compare, request()->get('sort') ?? 'created_at', request()->get('direction') ?? 'desc', true);
    }
}