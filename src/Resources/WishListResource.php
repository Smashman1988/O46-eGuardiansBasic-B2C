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
        if ($this->request->get('shop') !== $shop || $this->request->get('plenty') !== $plenty || !$this->matchesGuestSession()) {
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
        $requested = array_fill_keys($ids, true);
        $guestOnly = [];
        // Older clients send only imported. Both kinds are ineligible for IO's automatic migration.
        foreach (['imported' => true, 'customerIds' => 'customer'] as $field => $marker) {
            $marked = $this->request->get($field) ?? [];
            if (!is_array($marked) || count($marked) > count($ids)) {
                return $this->response->create(null, 422);
            }
            foreach ($marked as $id) {
                if (!is_int($id) || !isset($requested[$id])) {
                    return $this->response->create(null, 422);
                }
                $guestOnly[$id] = $marker;
            }
        }
        $allowed = [];
        $availability = pluginApp(WishListAvailability::class);
        foreach (array_chunk($ids, 100) as $group) {
            $allowed += $availability->lookup($group);
        }
        if (count($allowed) !== count($ids)) {
            return $this->response->create(null, 503);
        }
        // Authentication or session rotation can finish during a batched stock lookup.
        if (!$this->matchesGuestSession()) {
            return $this->response->create(null, 409);
        }
        $guest = pluginApp(\IO\Repositories\ItemWishListGuestRepository::class);
        $lists = $guest->getItemWishListForAllPlentyIds();
        $changed = false;
        foreach ($ids as $id) {
            if ($allowed[$id] && !isset($lists[$plenty][$id])) {
                $row = pluginApp(\IO\DBModels\ItemWishList::class);
                $row->variationId = $id;
                $row->plentyId = $plenty;
                $row->contactId = 0;
                $row->quantity = 1;
                $row->createdAt = date('Y-m-d H:i:s');
                $lists[$plenty][$id] = $row->jsonSerialize();
                $changed = true;
            }
            $existing = $lists[$plenty][$id]['o46GuestOnly'] ?? false;
            if ($allowed[$id] && isset($guestOnly[$id]) && (!$existing || ($existing !== 'customer' && $guestOnly[$id] === 'customer'))) {
                $lists[$plenty][$id]['o46GuestOnly'] = $guestOnly[$id];
                $changed = true;
            }
        }
        if ($changed && !$this->saveGuestRows($lists)) {
            return $this->response->create(null, 503);
        }
        return $this->response->create(['available' => array_keys(array_filter($allowed))], 200);
    }

    private function matchesGuestSession(): bool
    {
        $session = $this->request->get('session');
        return $session && (int)pluginApp(\Plenty\Modules\Webshop\Contracts\ContactRepositoryContract::class)->getContactId() === 0
            && $session === pluginApp(\IO\Services\BasketService::class)->getBasket()->sessionId;
    }

    private function saveGuestRows(array $lists): bool
    {
        $encoded = json_encode($lists);
        if ($encoded === false) {
            return false;
        }
        pluginApp(\Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract::class)->setSessionValue(
            \Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract::GUEST_WISHLIST, $encoded
        );
        return true;
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
        if ((int)pluginApp(\Plenty\Modules\Webshop\Contracts\ContactRepositoryContract::class)->getContactId() === 0) {
            $lists = pluginApp(\IO\Repositories\ItemWishListGuestRepository::class)->getItemWishListForAllPlentyIds();
            $plenty = \IO\Helper\Utils::getPlentyId();
            $row = $lists[$plenty][$id] ?? [];
            if (!empty($row['o46GuestOnly']) || !empty($row['o46MigrationOwner'])) {
                // IO replaces the row on a duplicate add, which would discard its migration protection.
                $row['quantity'] = ($row['quantity'] ?? 0) + (int)$this->request->get('quantity', 0);
                $row['createdAt'] = date('Y-m-d H:i:s');
                $lists[$plenty][$id] = $row;
                if (!$this->saveGuestRows($lists)) {
                    return $this->response->create(null, 503);
                }
                unset($row['o46GuestOnly'], $row['o46MigrationOwner']);
                return $this->response->create($row, 201);
            }
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
        return $this->response->create($items, ResponseCode::OK, ['Cache-Control' => 'private, no-store']);
    }
}
