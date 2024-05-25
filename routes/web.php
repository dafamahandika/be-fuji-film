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
    $router->post('/register', 'AuthController@register');
    $router->post('/send_otp', 'AuthController@sendOtp');
    $router->post('/verify_otp', 'AuthController@verifyOtp');
    $router->post('/logout', 'AuthController@logout');
    $router->get('/profile', 'AuthController@profile');
    
    $router->post('/directory/create/{id}', 'DirectoryController@createDirectory');
    
    $router->get('/wilayah/province', 'storeController@getProvince');
    $router->get('/wilayah/regency/{id}', 'storeController@getRegency');
    $router->get('/wilayah/subdistrict/{id}', 'storeController@getSubdistrict');
    $router->get('/wilayah/village/{id}', 'storeController@getVillage');

    $router->get('/store/get', 'storeController@getStore');
    $router->get('/store/get/{id}', 'storeController@getOneStore');
    $router->post('/store/create', 'storeController@createStore');
    $router->delete('/store/delete/{id}', 'storeController@deleteStore');

    // home
    $router->get('/home', 'UserController@home');
});
