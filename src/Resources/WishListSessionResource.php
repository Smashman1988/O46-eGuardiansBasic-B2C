<?php
namespace GuardiansBasic\Resources;

use IO\Api\Resources\SessionResource;
use IO\Api\ResponseCode;
use IO\Helper\Utils;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
use Plenty\Plugin\Http\Response;

/** Supply owner provenance on the two existing private session reads. */
class WishListSessionResource extends SessionResource
{
    public static function scope($sessionId, int $contact): string
    {
        return $sessionId ? hash('sha256', json_encode([(string)$sessionId, $contact, Utils::getPlentyId(), Utils::getWebstoreId()])) : '';
    }

    public function basket(): Response
    {
        return $this->response->create($this->indexBasket(), ResponseCode::OK);
    }

    protected function indexBasket()
    {
        $this->response->header('Cache-Control', 'private, no-store');
        $migration = pluginApp(\GuardiansBasic\Services\WishListMigration::class);
        try {
            $migration->confirm();
        } catch (\Exception $exception) {
            // IO reads the wishlist again while assembling the basket. Keep the confirmed login and basket usable.
            $session = pluginApp(SessionStorageRepositoryContract::class);
            $session->setSessionValue(SessionStorageRepositoryContract::GUEST_WISHLIST_MIGRATION, false);
        }
        try {
            $basket = parent::indexBasket();
        } finally {
            if (isset($session)) {
                $session->setSessionValue(SessionStorageRepositoryContract::GUEST_WISHLIST_MIGRATION, true);
            }
        }
        $contact = (int)pluginApp(ContactRepositoryContract::class)->getContactId();
        $basket['wishListOwner'] = [
            'guest' => $contact === 0,
            'shop' => Utils::getWebstoreId(),
            'plenty' => Utils::getPlentyId(),
            'scope' => self::scope($basket['sessionId'] ?? '', $contact)
        ];
        if ($contact === 0) {
            $lists = pluginApp(\IO\Repositories\ItemWishListGuestRepository::class)->getItemWishListForAllPlentyIds();
            foreach ($lists[Utils::getPlentyId()] ?? [] as $id => $row) {
                if (!empty($row['o46GuestOnly']) || !empty($row['o46MigrationOwner'])) {
                    $basket['wishListOwner']['imported'][] = $id;
                }
                if (($row['o46GuestOnly'] ?? false) === 'customer') {
                    $basket['wishListOwner']['customerIds'][] = $id;
                }
            }
        }
        return $basket;
    }
}
