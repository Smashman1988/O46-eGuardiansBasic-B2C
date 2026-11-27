<?php
namespace GuardiansBasic\Resources;

use GuardiansBasic\Services\WishListMigration;
use IO\Api\Resources\CustomerAuthenticationResource;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;

class WishListLoginResource extends CustomerAuthenticationResource
{
    use Loggable;

    public function store(): Response
    {
        $migration = pluginApp(WishListMigration::class);
        // IO can run its migration while constructing the native login response.
        $guest = (int)pluginApp(ContactRepositoryContract::class)->getContactId() === 0 ? $migration->guestRows() : [];
        try {
            $response = parent::store();
        } catch (\Exception $exception) {
            if ((int)pluginApp(ContactRepositoryContract::class)->getContactId() > 0) {
                $migration->retry($guest);
            }
            throw $exception;
        }
        if ($response->status() === 200) {
            try {
                $migration->confirm($guest);
            } catch (\Exception $exception) {
                // Keep the native authentication result. The next private session read retries.
                $this->getLogger(self::class)->warning('Guest wishlist migration will be retried.');
            }
        }
        return $response;
    }
}
