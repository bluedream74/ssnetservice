<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;
use Illuminate\Contracts\Events\Dispatcher;

class BaseController extends Controller
{
    protected $authUser;

    public function __construct(
        Dispatcher $events
    )
    {
        $this->renderSidemenus($events);

        $this->authUser = \Auth::guard('admin');
    }

    protected function renderSidemenus($events)
    {
        $events->listen(BuildingMenu::class, function (BuildingMenu $event) {
            $menus = [
                [
                    'text' => '会社一覧',
                    'url'  => '/admin',
                    'icon' => 'fas fa-tachometer-alt',
                    'role' => 'admin',
                ],
                // [
                //     'text' => '会社検索',
                //     'url'  => '/admin/fetch',
                //     'icon' => 'fas fa-tachometer-alt',
                //     'role' => 'admin',
                // ],
                [
                    'text' => 'フォーム一覧',
                    'url'  => '/admin/contact',
                    'icon' => 'fas fa-tachometer-alt',
                    'role' => 'admin',
                ],
                [
                    'text' => '設定',
                    'url'  => '/admin/config',
                    'icon' => 'fas fa-cog',
                    'role' => 'admin',
                ]
            ];
            
            foreach ($menus as $menu) {
                $event->menu->add($menu);
            }
        });
    }
}
