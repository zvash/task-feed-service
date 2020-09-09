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

        $router->get('groups/active', 'GroupController@getActiveGroups');

        $router->get('categories/main', 'CategoryController@getMain');

        $router->get('categories/{parentId}/sub', 'CategoryController@getSubCategories');

        $router->get('categories/{categoryId}/tasks', 'CategoryController@tasks');

        $router->get('groups/{groupId}/items', 'GroupController@getItems');

        $router->get('tasks/{taskId}/get', 'TaskController@get');

        $router->get('tasks/search', 'TaskController@searchByText');

        $router->group(['middleware' => 'auth'], function ($router) {

            $router->get('tasks/{taskId}/landing', 'TaskController@getLandingUrl');

        });


        $router->group(['middleware' => 'admin'], function ($router) {

            $router->post('tasks/create', 'TaskController@create');
            $router->get('tasks/{taskId}/tags', 'TaskController@getTags');
            $router->post('tasks/{taskId}/tags/reset', 'TaskController@resetTags');

            $router->post('categories/create', 'CategoryController@create');

            $router->post('groups/create', 'GroupController@create');
            $router->post('groups/{groupId}/tags/reset', 'GroupController@resetTags');
            $router->post('groups/{groupId}/tags/add', 'GroupController@addTags');
            $router->post('groups/{groupId}/tags/remove', 'GroupController@removeTags');
            $router->post('groups/{groupId}/order/change', 'GroupController@changeOrder');
            $router->post('groups/{groupId}/order/move-to-top', 'GroupController@moveToTop');
            $router->post('groups/order/reorder', 'GroupController@reorder');

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
