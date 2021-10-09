<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// })->name('home');



Route::get('/quit/{company}/done', '\App\Http\Controllers\Admin\DashboardController@stopReceive')->name('stop.receive');

Route::get('/{contactId}/{companyId}/read', '\App\Http\Controllers\Admin\DashboardController@read')->name('read');

Route::post('/webhooks/sns', 'SnsController@store');

Route::get('/image/open', 'SnsController@openEmail')->name('image.open');