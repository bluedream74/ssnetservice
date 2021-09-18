<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CRUDController extends Controller
{
    protected $service;

    protected $_modelName = null;
    protected $_serviceName = null;
    protected $_guardName = '';

    protected $_title = '';
    protected $_color = '';

    protected $_idColumn = 'id';

    protected $_routeList = [
        'index' => '',
        'create' => '',
        'edit' => '',
        'confirm' => '',
        'complete' => '',
        'detail' => '',
    ];

    protected $_viewList = [
        'index' => '',
        'create' => '',
        'edit' => '',
        'confirm' => '',
        'complete' => '',
        'detail' => '',
    ];

    protected $_indexButtonList = [
        /*
        [
            'label' => 'LABEL',
            'action' => 'ACTION',
        ],
        */
    ];

    protected $_indexSearchList = [
        // 'id' => [
        //     'label' => 'ID',
        //     'type' => 'text',
        // ],
    ];

    protected $_fixedSearchList = [];

    protected $_indexColumnList = [
        // 'id' => [
        //     'label' => 'ID',
        //     'sort' => true,
        //     'link' => true,
        // ],
    ];

    protected $_formList = [];

    protected $_formTabList = [];

    public function __construct()
    {
        $this->init();
    }

    protected function init($service)
    {
        $this->service = new $this->_serviceName();
    }

    private function makeSearchAttributes($attributes)
    {
        $searchAttributes = [];
        $searchConditionAttributes = [];
        $orderBy = 'created_at';
        $orderDirection = 'desc';

        foreach ($attributes as $key => $item) {
            $searchAttributes[$key] = request()->get($key);
            if (isset($item['operator'])) {
                $searchConditionAttributes[$key] = $item['operator'];
            }

            if (isset($item['order_by'])) {
                $orderBy = $item['order_by'];
            }
            if (isset($item['order_sort'])) {
                $orderDirection = $item['order_sort'];
            }
        }

        return [
            $searchAttributes,
            $searchConditionAttributes,
            $orderBy,
            $orderDirection
        ];
    }

    public function index()
    {
        list ($searchAttributes, $searchConditionAttributes, $orderBy, $orderDirection) = $this->makeSearchAttributes(array_merge($this->_indexSearchList, $this->_fixedSearchList));

        $items = $this->service->search($searchAttributes, $searchConditionAttributes, $orderBy, $orderDirection);

        return view($this->_viewList['index'], [
            'title' => $this->_title . '一覧',
            'items' => $items,
            'idColumn' => $this->_idColumn,
            'routeList' => $this->_routeList,
        ]);
    }
}
