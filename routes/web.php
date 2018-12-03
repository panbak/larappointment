<?php

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

Route::get('/', 'PagesController@index')->name('index');
Route::get('/datepicker', 'PagesController@datePicker');

Auth::routes();

Route::get('/dashboard', 'UserDashboardController@index');

Route::patch('/dashboard',  ['uses' => 'UpdateUserController@update']);

Route::get('/admin', 'PagesController@adminDashboard')->name('adminDashboard');

Route::get('/hierarchy', 'PagesController@hierarchy')->name('hierarchy');

Route::resource('options', 'OptionsController');

Route::post('options/update', 'OptionsController@updateHierarchy');