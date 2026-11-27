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
    /** Recover a browser-only guest session in bounded batches. Never target a customer account. */
    public function restoreGuest(): Response
    {
        $plenty = \IO\Helper\Utils::getPlentyId();
        $shop = \IO\Helper\Utils::getWebstoreId();
        if ((int)pluginApp(\Plenty\Modules\Webshop\Contracts\ContactRepositoryContract::class)->getContactId() !== 0
            || $this->request->get('shop') !== $shop || $this->request->get('plenty') !== $plenty
            || !$this->request->get('session') || $this->request->get('session') !== pluginApp(\IO\Services\BasketService::class)->getBasket()->sessionId) {
            return $this->response->create(null, 409);
        }
        $ids = $this->request->get('ids');
        if (!is_array($ids) || !$ids || count($ids) > 5000) {
            return $this->response->create(null, 422);
        }
        foreach ($ids as $id) {
            if (!is_int($id) || $id <= 0) {
                return $this->response->create(null, 422);
            }
        }
        $ids = array_unique($ids);
        $allowed = [];
        $availability = pluginApp(WishListAvailability::class);
        foreach (array_chunk($ids, 100) as $group) {
            $allowed += $availability->lookup($group);
        }
        if (count($allowed) !== count($ids)) {
            return $this->response->create(null, 503);
        }
        $guest = pluginApp(\IO\Repositories\ItemWishListGuestRepository::class);
        $lists = $guest->getItemWishListForAllPlentyIds();
        $added = [];
        foreach ($ids as $id) {
            if ($allowed[$id] && !isset($lists[$plenty][$id])) {
                $row = pluginApp(\IO\DBModels\ItemWishList::class);
                $row->variationId = $id;
                $row->plentyId = $plenty;
                $row->contactId = 0;
                $row->quantity = 1;
                $row->createdAt = date('Y-m-d H:i:s');
                $lists[$plenty][$id] = $row->jsonSerialize();
                $added[] = $id;
            }
        }
        if ($added) {
            $encoded = json_encode($lists);
            if ($encoded === false) {
                return $this->response->create(null, 503);
            }
            pluginApp(\Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract::class)->setSessionValue(
                \Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract::GUEST_WISHLIST, $encoded
            );
        }
        return $this->response->create(['available' => array_keys(array_filter($allowed))], 200);
    }

    private function matchesOwner(): bool
    {
        $expected = $this->request->get('wishScope', null);
        if ($expected === null) {
            return true; // Native IO callers identify their owner through the session.
        }
        $contact = (int)pluginApp(\Plenty\Modules\Webshop\Contracts\ContactRepositoryContract::class)->getContactId();
        $session = pluginApp(\IO\Services\BasketService::class)->getBasket()->sessionId;
        return is_string($expected) && $expected !== '' && $expected === WishListSessionResource::scope($session, $contact);
    }

    public function destroy(string $selector): Response
    {
        return $this->matchesOwner() ? parent::destroy($selector) : $this->response->create(null, 409);
    }

    public function store(): Response
    {
        if (!$this->matchesOwner()) {
            return $this->response->create(null, 409);
        }
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
