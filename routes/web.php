<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});


$router->group(['prefix' => 'api'], function () use ($router) {
    // Auth
    $router->post('/register', 'AuthController@register');
    $router->post('/send_otp', 'AuthController@sendOtp');
    $router->post('/verify_otp', 'AuthController@verifyOtp');
    $router->post('/logout', 'AuthController@logout');
    
    // API Wilayah
    $router->get('/wilayah/province', 'WilayahController@getProvince');
    $router->get('/wilayah/regency/{id}', 'WilayahController@getRegency');
    $router->get('/wilayah/subdistrict/{id}', 'WilayahController@getSubdistrict');
    $router->get('/wilayah/village/{id}', 'WilayahController@getVillage');

    // Create Directory Photo
    $router->post('/directory/create/{id}', 'DirectoryController@createDirectory');

    // CRUD Store
    $router->get('/store/get', 'StoreController@getStore');
    // $router->get('/store/get/{id}', 'StoreController@getOneStore');
    $router->post('/store/create', 'StoreController@createStore');
    $router->put('/store/edit/{id}', 'StoreController@editStore');
    $router->delete('/store/delete/{id}', 'StoreController@deleteStore');

    // CRUD Layanan
    $router->get('/layanan/get', 'LayananController@getLayanan');
    // $router->get('/layanan/get/{id}', 'LayananController@getOneLayanan');
    // $router->post('/layanan/create', 'LayananController@createLayanan');
    // $router->put('/layanan/edit/{id}', 'LayananController@editLayanan');
    // $router->delete('/layanan/delete/{id}', 'LayananController@deleteLayanan');
    
    // CRUD Paket
    $router->get('/paket/get', 'PaketController@getPaket');
    $router->get('/paket/get/{id}', 'PaketController@getOnePaket');
    $router->post('/paket/create', 'PaketController@createPaket');
    $router->delete('/paket/delete/{id}', 'PaketController@deletePaket');
    // $router->put('/paket/edit/{id}', 'PaketController@editLayanan');
    
    // CRUD Voucher
    // $router->get('/layanan/get', 'LayananController@getLayanan');
    // $router->get('/layanan/get/{id}', 'LayananController@getOneLayanan');
    $router->post('/voucher/create', 'VoucherController@creatVoucher');
    // $router->put('/layanan/edit/{id}', 'LayananController@editLayanan');
    // $router->delete('/layanan/delete/{id}', 'LayananController@deleteLayanan');

    // $router->group(['middleware' => 'check.token.expired'], function () use ($router) {
        // CRUD Order
        $router->get('/order/get', 'OrderController@getOrder');
        $router->get('/order/get/{id}', 'OrderController@getOneOrder');
        $router->post('/order/create', 'OrderController@createOrder');
        $router->post('/send/transfer/{id}', 'OrderController@sendTransfer');
        // $router->delete('/paket/delete/{id}', 'PaketController@deletePaket')

        $router->get('/profile', 'AuthController@profile');
    // });
});
