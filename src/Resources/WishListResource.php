<?php
namespace GuardiansBasic\Resources;

use GuardiansBasic\Services\WishListAvailability;
use IO\Api\Resources\ItemWishListResource;
use IO\Api\ResponseCode;
use IO\Services\ItemListService;
use IO\Services\ItemWishListService;
use Plenty\Plugin\Http\Response;

/** Keep IO's session, contact repository, HTTP contract and manual removal. */
class WishListResource extends ItemWishListResource
{
    public function store(): Response
    {
        $id = (int)$this->request->get('variationId', 0);
        $status = $id > 0 ? pluginApp(WishListAvailability::class)->lookup([$id]) : [];
        if (($status[$id] ?? null) !== true) {
            return $this->response->create(null, $id < 1 || isset($status[$id]) ? 422 : 503);
        }
        return parent::store();
    }

    public function index(): Response
    {
        $repository = pluginApp(ItemWishListService::class);
        $ids = $repository->getItemWishList();
        $items = pluginApp(ItemListService::class)->getItemList(ItemListService::TYPE_WISH_LIST);
        $status = pluginApp(WishListAvailability::class)->fromResult($ids, $items);
        $blocked = [];
        foreach ($status as $id => $available) {
            if ($available === false) {
                $blocked[$id] = true;
                // IO owns persistence for the current guest/contact and webstore.
                try {
                    if ($repository->removeItemWishListEntry((int)$id)) {
                        $items['wishListPruned'] = true;
                    }
                } catch (\Exception $exception) {
                    // Keep processing other entries. IO can retry this removal at the next visit.
                    continue;
                }
            }
        }
        if ($blocked) {
            $items['documents'] = array_values(array_filter($items['documents'], function ($doc) use ($blocked) {
                return !isset($blocked[$doc['data']['variation']['id'] ?? 0]);
            }));
            $items['inactiveVariationIds'] = array_values(array_diff($items['inactiveVariationIds'] ?? [], array_keys($blocked)));
        }
        return $this->response->create($items, ResponseCode::OK);
    }
}
