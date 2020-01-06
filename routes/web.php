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

$router->get('/demo', function () use ($router) {
    return response()->json([
        'name' => 'Rohin Awale',
        'city' => 'Patan',
        'email' => 'rohinawale331@gmail.com',
        'phone' => '9813982829',
        'blood group' => null,
        'orange' => 'orange',
        'apple' => 'red',
        'banana' => 'yellow'
    ]);
});


$router->get('/', 'myobController@myob_redirect');
$router->get('/accounts', 'myobController@access_token');
$router->get('/accountright', 'myobController@accountright_myob');
$router->get('/refresh', 'myobController@refresh_access_token');
$router->post('/sales/invoice/service', 'myobController@create_service_invoice');
$router->post('/sales/payments','myobController@payment');
$router->post('/sales/payments-with-discount','myobController@payemtWithDiscount');
$router->post('/sales/invoice/item', 'myobController@create_item_invoice');
$router->get('/sales/invoice/services', 'myobController@get_services_invoices');
$router->get('/sales/invoice/items', 'myobController@get_services_invoices');
$router->get('/sales/payments', 'myobController@get_payments');
$router->get('/api/documentation', function ()  {
    return view('index');
});
