<?php
namespace GuardiansBasic\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;
use Plenty\Plugin\Routing\ApiRouter;

class GuardiansBasicRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router, ApiRouter $api)
    {
        $router->get('loginPreview', 'GuardiansBasic\Controllers\ContentController@dynamicLogin');
        $router->get('basketPreview', 'GuardiansBasic\Controllers\ContentController@dynamicBasketPreview');
        $router->get('basketPreviewSmall', 'GuardiansBasic\Controllers\ContentController@dynamicBasketPreviewSmall');
        $router->get('catPreviewImages', 'GuardiansBasic\Controllers\ContentController@dynamicCatPreviewImages');
        $router->get('getFeedbacks', 'GuardiansBasic\Controllers\ContentController@dynamicGetFeedbacks');
        $router->get('getShopBuilderContent', 'GuardiansBasic\Controllers\ContentController@getShopBuilderContent');      
        $router->get('trackings', 'GuardiansBasic\Controllers\ContentController@getTrackings');
        $api->version(['v1'], ['namespace' => 'GuardiansBasic\Resources'], function ($api) {
            $api->get('guardiansbasic/item/search/autocomplete', 'SearchAutocompleteResource@index');
            $api->resource('io/facet', 'FacetResource');
            $api->resource('io/itemWishList', 'WishListResource');
            $api->get('io/basket', 'WishListSessionResource@basket');
            $api->get('io/session', 'WishListSessionResource@index');
            $api->post('io/customer/login', 'WishListLoginResource@store');
            $api->post('guardiansbasic/wishlist/guest', 'WishListResource@restoreGuest');
        });
    }
}

