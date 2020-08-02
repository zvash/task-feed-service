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

$router->get('/who', function () use ($router) {
    return "Task Feed Service";
});


$router->group(['prefix' => 'api/v1'], function ($router) {

    $router->group(['namespace' => 'Api\V1'], function ($router) {

        $router->post('upload', 'TaskController@upload');

        $router->group(['middleware' => 'auth'], function ($router) {

//            $router->get('tasks/create', 'TaskController@create');
            $router->get('categories/{categoryId}/tasks', 'CategoryController@tasks');

        });


        $router->group(['middleware' => 'admin'], function ($router) {

            $router->post('tasks/create', 'TaskController@create');

            $router->post('categories/create', 'CategoryController@create');

            $router->get('tags/all', 'TagController@getAll');
            $router->post('tags/create', 'TagController@create');
            $router->post('tags/bulk-create', 'TagController@createMultiple');
        });
    });

});

$router->group(['prefix' => 'storage'], function ($router) {

    $router->group(['namespace' => 'Resource'], function ($router) {

        $router->group(['prefix' => 'images'], function ($router) {

            $router->get('/{fileName}', 'ImagesController@download');

        });
    });

});