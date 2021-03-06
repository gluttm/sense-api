<?php

use Illuminate\Http\Request;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/customers', function (Request $request) {
//     return $request->user();
// });

Route::get('/search_customers', 'CustomersController@search_customers')->middleware('auth:api');
Route::resource('/customers', 'CustomersController')->middleware('auth:api');