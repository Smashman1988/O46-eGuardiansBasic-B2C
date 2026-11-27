<?php
namespace GuardiansBasic\Services;

use IO\Helper\Utils;
use IO\Repositories\ItemWishListGuestRepository;
use IO\Repositories\ItemWishListRepository;
use IO\Services\ItemWishListService;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;

class WishListMigration
{
    public function guestRows(): array
    {
        $guest = pluginApp(ItemWishListGuestRepository::class);
        $rows = $guest->getItemWishListForAllPlentyIds()[Utils::getPlentyId()] ?? [];
        return $this->eligible($rows, (int)pluginApp(ContactRepositoryContract::class)->getContactId());
    }

    private function eligible(array $rows, int $contact): array
    {
        return array_filter($rows, function ($row) use ($contact) {
            return empty($row['o46GuestOnly']) && (empty($row['o46MigrationOwner']) || $row['o46MigrationOwner'] === $contact);
        });
    }

    /** IO migrates every current guest row after authentication, including on native login routes. */
    public function beforeNativeMigration(int $contact)
    {
        $plenty = Utils::getPlentyId();
        $lists = pluginApp(ItemWishListGuestRepository::class)->getItemWishListForAllPlentyIds();
        $rows = $lists[$plenty] ?? [];
        $eligible = $this->eligible($rows, $contact);
        if (count($eligible) !== count($rows)) {
            $lists[$plenty] = $eligible;
            pluginApp(SessionStorageRepositoryContract::class)->setSessionValue(
                SessionStorageRepositoryContract::GUEST_WISHLIST, json_encode($lists)
            );
        }
    }

    public function confirm(array $before = [])
    {
        $session = pluginApp(SessionStorageRepositoryContract::class);
        if ((!$before && !$session->getSessionValue(SessionStorageRepositoryContract::GUEST_WISHLIST_MIGRATION))
            || (int)pluginApp(ContactRepositoryContract::class)->getContactId() <= 0) {
            return;
        }
        $before = $before ?: $this->guestRows();
        if (!$before) {
            return;
        }
        // IO's constructor runs the existing, idempotent migration and clears the guest scope.
        try {
            pluginApp(ItemWishListService::class);
            $saved = pluginApp(ItemWishListRepository::class)->getItemWishList();
            $pending = array_diff_key($before, array_fill_keys($saved, true));
        } catch (\Exception $exception) {
            $pending = $before;
        }
        if ($pending) {
            $this->retry($pending);
            throw new \Exception('Guest wishlist migration is incomplete.');
        }
    }

    public function retry(array $pending)
    {
        if (!$pending) {
            return;
        }
        $session = pluginApp(SessionStorageRepositoryContract::class);
        $plenty = Utils::getPlentyId();
        $lists = pluginApp(ItemWishListGuestRepository::class)->getItemWishListForAllPlentyIds();
        $owner = (int)pluginApp(ContactRepositoryContract::class)->getContactId();
        foreach ($pending as $id => $row) {
            $row['o46MigrationOwner'] = $owner;
            $lists[$plenty][$id] = $row;
        }
        $session->setSessionValue(SessionStorageRepositoryContract::GUEST_WISHLIST, json_encode($lists));
        $session->setSessionValue(SessionStorageRepositoryContract::GUEST_WISHLIST_MIGRATION, true);
    }
}
