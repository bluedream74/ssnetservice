<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web & admin" middleware group. Now create something great!
|
*/

Route::get('login', 'AuthController@showLoginForm');
Route::post('login', 'AuthController@login')->name('login');

Route::group(['middleware' => 'auth.admin'], function() { //middleware?auth?????
  Route::post('logout', 'AuthController@logout')->name('logout');
  
  // Route::get('/', function () {
  //     return redirect(route('admin.dashboard'));
  // });

  Route::get('/', 'DashboardController@redirect')->name('redirect');

  Route::get('dashboard', 'DashboardController@index')->name('dashboard');

  Route::get('fetch', 'DashboardController@fetch')->name('fetch');
  Route::post('fetch', 'DashboardController@doFetch')->name('do.fetch');

  Route::post('batchCheck', 'DashboardController@batchCheck')->name('batchCheck');
  // Route::post('sendContact', 'DashboardController@sendContact')->name('sendContact');
  

  Route::get('contact', 'DashboardController@contact')->name('contact');
  Route::get('contact/{contact}/show', 'DashboardController@contactShow')->name('contact.show');
  Route::post('contact/delete', 'DashboardController@deleteContact')->name('contact.delete');
  Route::get('contact/{contact}/export/csv', 'DashboardController@exportContactCSV')->name('contact.export');
  Route::post('contact/{contact}/send', 'DashboardController@sendShowContact')->name('contact.show.send');

  Route::get('export/csv', 'DashboardController@exportCSV')->name('companies.export');
  Route::post('contact/send', 'DashboardController@sendContact')->name('contact.send');
  Route::post('import/csv', 'DashboardController@importCSV')->name('companies.import');

  Route::post('email/delete', 'DashboardController@deleteEmail')->name('email.delete');
  Route::post('company/update/status', 'DashboardController@updateCompanyStatus')->name('update.company.status');

  Route::get('/{company}/show', 'CompanyController@show')->name('companies.show');
  Route::post('/{company}/add/email', 'CompanyController@addEmail')->name('company.add.email');
  Route::post('/{company}/remove/email', 'CompanyController@removeEmail')->name('company.remove.email');
  Route::post('/{company}/add/phone', 'CompanyController@addPhone')->name('company.add.phone');
  Route::post('/{company}/remove/phone', 'CompanyController@removePhone')->name('company.remove.phone');
  Route::post('/reset/company', 'DashboardController@reset')->name('reset.company');
  Route::post('/delete/duplicate', 'CompanyController@deleteDuplicate')->name('delete.duplicate');
  Route::post('/delete/email/bulk', 'CompanyController@deleteEmail')->name('delete.email');
  Route::post('/company/delete', 'CompanyController@deleteCompany')->name('company.delete');

  Route::post('/{company}/edit/url', 'CompanyController@editURL')->name('company.edit.url');
  Route::post('/{company}/add/url', 'CompanyController@addURL')->name('company.add.url');

  Route::post('/{company}/edit/name', 'CompanyController@editName')->name('company.edit.name');

  Route::post('/{company}/edit/contacturl', 'CompanyController@editContacturl')->name('company.edit.contacturl');
  Route::post('/{company}/add/contacturl', 'CompanyController@addContacturl')->name('company.add.contacturl');

  Route::post('/{company}/edit/category', 'CompanyController@editcategory')->name('company.edit.category');
  Route::post('/{company}/add/category', 'CompanyController@addcategory')->name('company.add.category');

  Route::post('/{company}/edit/subcategory', 'CompanyController@editsubcategory')->name('company.edit.subcategory');
  Route::post('/{company}/add/subcategory', 'CompanyController@addsubcategory')->name('company.add.subcategory');

  Route::post('/{company}/add/updatearea', 'CompanyController@updatearea')->name('company.update.area');

  Route::get('/config', 'DashboardController@configIndex')->name('config.index');
  Route::post('/config', 'DashboardController@updateConfig')->name('config.update');
});


