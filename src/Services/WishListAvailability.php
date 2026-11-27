<?php
namespace GuardiansBasic\Services;

use Plenty\Modules\Webshop\ItemSearch\Factories\VariationSearchFactory;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;

/** Availability in the current storefront context. Missing keys mean unknown. */
class WishListAvailability
{
    private $pickerStatus = [];

    public function forPicker(array $ids, iterable $documents = []): array
    {
        $requested = array_fill_keys($ids, true);
        foreach ($documents as $doc) {
            $data = $doc['data'] ?? [];
            $id = $data['variation']['id'] ?? null;
            if ((is_int($id) || is_string($id)) && isset($requested[$id])) {
                $value = $this->stockStatus($data);
                if ($value !== null) {
                    $this->pickerStatus[$id] = $value;
                }
            }
        }
        $missing = array_diff_key($requested, $this->pickerStatus);
        foreach (array_chunk(array_keys($missing), 100) as $group) {
            $this->pickerStatus += $this->lookup($group) + array_fill_keys($group, false);
        }
        return array_intersect_key($this->pickerStatus, $requested);
    }

    public function lookup(array $ids): array
    {
        try {
            $factory = pluginApp(VariationSearchFactory::class)
                ->isVisibleForClient()->isActive()->hasPriceForCustomer()->hasVariationIds($ids)
                ->setPage(1, count($ids))->withResultFields(['variation.id', 'filter.isSalable', 'stock.net']);
            $result = pluginApp(ItemSearchService::class)->getResult($factory);
        } catch (\Exception $exception) {
            return [];
        }
        return $this->fromResult($ids, $result);
    }

    public function fromResult(array $ids, $result): array
    {
        if (!empty($result['error']) || !empty($result['errors']) || !empty($result['timed_out'])
            || !empty($result['_shards']['failed']) || ($result['success'] ?? true) === false
            || !is_array($result['documents'] ?? null) || !is_array($result['inactiveVariationIds'] ?? [])) {
            return [];
        }
        $requested = array_fill_keys($ids, true);
        $inactive = [];
        foreach ($result['inactiveVariationIds'] ?? [] as $id) {
            if ((is_int($id) || is_string($id)) && isset($requested[$id])) {
                $inactive[$id] = false;
            }
        }
        $status = $inactive;
        $returned = [];
        foreach ($result['documents'] as $document) {
            $data = $document['data'] ?? [];
            $id = $data['variation']['id'] ?? null;
            if ((!is_int($id) && !is_string($id)) || !isset($requested[$id])) {
                continue;
            }
            if (isset($returned[$id]) || isset($inactive[$id])) {
                $returned[$id] = true;
                unset($status[$id]);
                continue;
            }
            $returned[$id] = true;
            $value = $this->stockStatus($data);
            if ($value !== null) {
                $status[$id] = $value;
            }
        }
        // An absent ID is conclusive only in a complete response to this exact search.
        $total = $result['total'] ?? null;
        if (count($returned) === count($result['documents']) && (is_int($total) || is_string($total))
            && (string)$total === (string)count($returned)) {
            $status += array_fill_keys(array_keys(array_diff_key($requested, $returned)), false);
        }
        return $status;
    }

    private function stockStatus($data)
    {
        $salable = $data['filter']['isSalable'] ?? null;
        $net = $data['stock']['net'] ?? null;
        if ($salable === false || $salable === 0 || (is_numeric($net) && $net <= 0)) {
            return false;
        }
        if (($salable === true || $salable === 1) && is_numeric($net)) {
            return true;
        }
        return null;
    }
}
