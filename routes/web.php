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

        $router->get('categories/{categoryId}/search', 'CategoryController@search');

        $router->get('groups/{groupId}/items', 'GroupController@getItems');

        $router->get('groups/{groupId}/search', 'GroupController@search');

        $router->get('tasks/{taskId}/get', 'TaskController@get');

        $router->get('tasks/search', 'TaskController@searchByText');

        $router->get('filters/actives', 'FilterController@getActives');

        $router->get('banners/{bannerId}/tasks', 'BannerController@getTasks');


        $router->group(['middleware' => 'auth'], function ($router) {

            $router->get('tasks/history', 'TaskController@history');
            $router->get('tasks/{taskId}/landing', 'TaskController@getLandingUrl');

            $router->get('tasks/{clickId}/claim', 'TaskController@claim');
            $router->post('tasks/claim', 'TaskController@claimByToken');

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

            $router->post('filters/create', 'FilterController@create');
            $router->post('filters/{filterId}/activate', 'FilterController@activate');
            $router->post('filters/{filterId}/deactivate', 'FilterController@deactivate');
            $router->get('filters/all', 'FilterController@getAll');
            $router->get('filters/actives', 'FilterController@getActives');

            $router->get('filterables/all', 'FilterableController@getAll');

            $router->post('banners/create', 'BannerController@create');
            $router->post('banners/{bannerId}/tags/reset', 'BannerController@resetTags');
            $router->post('banners/{bannerId}/tags/add', 'BannerController@addTags');
            $router->post('banners/{bannerId}/tags/remove', 'BannerController@removeTags');

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
