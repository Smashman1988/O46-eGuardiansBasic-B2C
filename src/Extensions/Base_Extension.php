<?php

namespace GuardiansBasic\Extensions;

use Plenty\Plugin\Templates\Extensions\Twig_Extension;
use Plenty\Plugin\Templates\Factories\TwigFactory;
use Plenty\Modules\Item\Attribute\Contracts\AttributeValueImageRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract;
use Plenty\Modules\Feedback\Contracts\FeedbackRepositoryContract;
use Plenty\Modules\Feedback\Contracts\FeedbackAverageRepositoryContract;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\CategoryItems;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\Facets;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\CrossSellingItems;
use IO\Helper\RouteConfig;
use IO\Services\UrlService;
use Plenty\Modules\Webshop\Contracts\WebstoreConfigurationRepositoryContract;
use Plenty\Modules\Webshop\Helpers\UrlQuery;
use IO\Services\BasketService;
use Plenty\Plugin\Http\Request;
use IO\Services\CategoryService;
use Ceres\Helper\SearchOptions;
use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Webshop\ItemSearch\SearchPresets\VariationAttributeMap;
use GuardiansBasic\Helper\SeoPage;
use IO\Builder\Item\Fields\VariationBaseFields;
use IO\Builder\Item\Fields\VariationRetailPriceFields;
use IO\Builder\Item\Fields\VariationStockFields;
use IO\Builder\Item\ItemColumnBuilder;
use IO\Builder\Item\ItemFilterBuilder;
use IO\Builder\Item\ItemParamsBuilder;
use IO\Builder\Item\Params\ItemColumnsParams;
use IO\Helper\Utils;
use IO\Services\ItemService;
use Plenty\Modules\Item\DataLayer\Contracts\ItemDataLayerRepositoryContract;
use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
use Plenty\Plugin\Application;

class Base_Extension extends Twig_Extension
{
    public const SHOPBUILDER_ITEMS_PER_PAGE = 12;

    /**
     * @var TwigFactory
     */
    private $factory;
    private $req;
    private $twig;
    /**
     * BlogTwigExtension constructor.
     * @param TwigFactory $factory
     */
    public function __construct(TwigFactory $factory, Twig $twig, Request $request)
    {
        $this->factory = $factory;
        $this->twig = $twig;
        $this->req = $request;
    }
    /**
     * @return string
     */
    public function getName():string
    {
        return "base_extension";
    }
    /**
     * @return array
     */
    public function getFunctions():array
    {
        return [
            $this->factory->createSimpleFunction('getImageAttributeList', [$this, 'getImageAttributeList']),
            $this->factory->createSimpleFunction('getFeedbackItemList', [$this, 'getFeedbackItemList']),
            $this->factory->createSimpleFunction('getListFeedbackAverages', [$this, 'getListFeedbackAverages']),
            $this->factory->createSimpleFunction('getFacetView', [$this, 'getFacetView']),
            $this->factory->createSimpleFunction('sortCatByKey', [$this, 'sortCatByKey']),
            $this->factory->createSimpleFunction('getCrossSeller', [$this, 'getCrossSeller']),
            $this->factory->createSimpleFunction('restGetContent', [$this, 'restGetContent']),
            $this->factory->createSimpleFunction('getItemsFromCat', [$this, 'getItemsFromCat']),
            $this->factory->createSimpleFunction('getItemsFromCatSimple', [$this, 'getItemsFromCatSimple']),
            $this->factory->createSimpleFunction('getStorefrontLanguageRoutes', [$this, 'getStorefrontLanguageRoutes']),
            $this->factory->createSimpleFunction('createSeoPage', [$this, 'createSeoPage']),
            $this->factory->createSimpleFunction('prepareSeoContent', [$this, 'prepareSeoContent']),
            $this->factory->createSimpleFunction('sortSizes', [$this, 'sortSizes']),
            $this->factory->createSimpleFunction('getAttributesWithImages', [$this, 'getAttributesWithImages']),
            $this->factory->createSimpleFunction('getBrandGroups', [$this, 'getBrandGroups']),
            $this->factory->createSimpleFunction('getCardLinks', [$this, 'getCardLinks']),
            $this->factory->createSimpleFunction('getWishChoices', [$this, 'getWishChoices']),
            $this->factory->createSimpleFunction('getCardAttributeMaps', [$this, 'getCardAttributeMaps']),
            $this->factory->createSimpleFunction('getItemPropertiesById', [$this, 'getItemPropertiesById']),
            $this->factory->createSimpleFunction('getVariationSalabilityMap', [$this, 'getVariationSalabilityMap']),
            $this->factory->createSimpleFunction('getSalableAttributeValues', [$this, 'getSalableAttributeValues']),
        ];
    }

    /** Resolve navigable destinations from IO route configuration, independently of indexing policy. */
    public function getStorefrontLanguageRoutes($template, $category, $itemId, $variationId): array
    {
        $config = pluginApp(WebstoreConfigurationRepositoryContract::class);
        $defaultLanguage = $config->getWebstoreConfiguration()->defaultLanguage;
        $urls = pluginApp(UrlService::class);
        $renderedCategoryId = is_array($category) ? ($category['id'] ?? null) : ($category->id ?? null);
        $categoryId = $renderedCategoryId;
        $isCategory = in_array($template, ['tpl.category.item', 'tpl.category.content', 'tpl.category.blog'], true);
        $isHome = in_array($template, ['tpl.home', 'tpl.home.category'], true);
        $system = [
            'tpl.login' => 'login', 'tpl.register' => 'register', 'tpl.my-account' => 'my-account',
            'tpl.wish-list' => 'wish-list', 'tpl.basket' => 'basket', 'tpl.checkout' => 'checkout',
            'tpl.contact' => 'contact', 'tpl.privacy-policy' => 'privacy-policy',
            'tpl.legal-disclosure' => 'legal-disclosure', 'tpl.terms-conditions' => 'gtc',
            'tpl.cancellation-rights' => 'cancellation-rights', 'tpl.cancellation-form' => 'cancellation-form',
            'tpl.declaration-of-accessibility' => 'declaration-of-accessibility',
            'tpl.newsletter.opt-out' => 'newsletter-opt-out', 'tpl.search' => 'search'
        ];
        $route = $system[$template] ?? null;
        $tagPath = null;
        if ($template === 'tpl.tags' && RouteConfig::isActive('tags')) {
            // IO identifies tag results by the terminal _t<ID> segment, independently of the language prefix.
            $path = explode('?', $this->req->getRequestUri(), 2)[0];
            if (preg_match('~(?:^|/)([^/?#]*_t[0-9]+)/?$~D', $path, $matches)) {
                $tagPath = $matches[1];
            }
        }
        if ($route !== null) {
            $categoryId = RouteConfig::getCategoryId($route);
            if (!$categoryId && !RouteConfig::isActive($route)) {
                return [];
            }
        } elseif (!$isCategory) {
            $categoryId = null;
        } elseif (!$categoryId) {
            $category = pluginApp(CategoryService::class)->getCurrentCategory();
            $renderedCategoryId = is_array($category) ? ($category['id'] ?? null) : ($category->id ?? null);
            $categoryId = $renderedCategoryId;
        }

        $categories = $categoryId ? pluginApp(CategoryService::class) : null;
        $routes = [];
        foreach ($config->getActiveLanguageList() as $language) {
            if (!in_array($language, ['de', 'en', 'fr', 'it', 'es'], true)) {
                continue;
            }
            if ($categoryId) {
                $alreadyLoaded = (string)$renderedCategoryId === (string)$categoryId && $this->categoryHasLanguage($category, $language);
                if (!$alreadyLoaded && !$this->categoryHasLanguage($categories->get($categoryId, $language), $language)) {
                    continue;
                }
                $url = $urls->getCategoryURL($categoryId, $language);
            } elseif ($template === 'tpl.item' && $itemId && $variationId) {
                $url = $urls->getVariationURL($itemId, $variationId, $language);
            } elseif ($tagPath !== null) {
                $url = pluginApp(UrlQuery::class, ['path' => $tagPath, 'lang' => $language]);
            } elseif ($route !== null) {
                // IO uses this alias for its unlinked newsletter opt-out route.
                $path = $route === 'newsletter-opt-out' ? 'newsletter/unsubscribe' : $route;
                $url = pluginApp(UrlQuery::class, ['path' => $path, 'lang' => $language]);
            } elseif ($isHome) {
                $home = $urls->getCanonicalURL($language, true);
                if ($home) {
                    $routes[$language] = $home;
                }
                continue;
            } else {
                continue;
            }
            if ($url && $url->getPath()) {
                $routes[$language] = $url->toAbsoluteUrl($language !== $defaultLanguage);
            }
        }
        return $routes;
    }

    private function categoryHasLanguage($category, $language): bool
    {
        $details = is_array($category) ? ($category['details'] ?? []) : ($category->details ?? []);
        foreach ($details as $detail) {
            if ((is_array($detail) ? ($detail['lang'] ?? null) : ($detail->lang ?? null)) === $language) {
                return true;
            }
        }
        return false;
    }

    private function requestedFacetIds(): array
    {
        $raw = $this->req->get('facets');
        $values = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : [$raw]);
        $ids = [];
        foreach ($values as $value) {
            $id = SeoPage::positiveInteger($value, true);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /** Presentation only: reuse this request's IO result, without another search or cache. */
    public function getFacetView(array $facets, array $selectedFacets = []): array
    {
        $selected = [];
        // Only active filter chips consume a name lookup; other values stay in rows.
        $names = [];
        foreach (array_merge($this->requestedFacetIds(), $selectedFacets) as $value) {
            $id = SeoPage::positiveInteger($value);
            if ($id !== null && !in_array((string)$id, $selected, true)) {
                $selected[] = (string)$id;
                $names[$id] = null;
            }
        }
        $values = [];
        foreach ($facets as $facet) {
            $rows = [];
            foreach ($facet['values'] ?? [] as $value) {
                $id = (string)$value['id'];
                $rows[] = [$id, $value['name'], (int)$value['count']];
                if (array_key_exists($id, $names)) {
                    $names[$id] = $value['name'];
                }
            }
            $values[(string)$facet['id']] = $rows;
        }
        $active = [];
        // Selected IDs belong to the URL/configuration, not to the result buckets.
        // An absent bucket is not proof of an invalid ID or an untranslated facet.
        foreach ($selected as $id) {
            $active[] = ['id' => $id, 'name' => $names[$id] ?? null,
                'remaining' => implode(',', array_diff($selected, [$id]))];
        }
        return ['values' => $values, 'selected' => $selected, 'active' => $active];
    }

    public function getAttributesWithImages($itemId){
        $result = pluginApp(ItemSearchService::class)->getResult($this->cardAttributeFactory($itemId));
        $result['wishChoices'] = $this->cardWishChoices($result);
        $maps = [$itemId => ['wishChoices' => $result['wishChoices']]];
        $this->completeWishChoices($maps);
        $result['wishChoices'] = $maps[$itemId]['wishChoices'];
        return $result;
    }

    /** Index the existing item result once; the first property for an ID wins. */
    public function getItemPropertiesById(iterable $properties): array
    {
        $indexed = [];
        foreach ($properties as $property) {
            $id = $property['property']['id'] ?? null;
            // Result-field property IDs are integers; also retain numeric-string IDs.
            if (!is_int($id) && !is_numeric($id)) {
                continue;
            }
            $key = (int)$id;
            if ($id == $key && !array_key_exists($key, $indexed)) {
                $indexed[$key] = $property;
            }
        }
        return $indexed;
    }

    private function cardAttributeFactory($itemId)
    {
        return VariationAttributeMap::getSearchFactory(['itemId' => $itemId, 'afterKey' => null]);
    }

    /** Use the same virtual rows, retail-price filter and customer context as IO's single-ID check. */
    public function getVariationSalabilityMap(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        $salable = [];
        if (count($ids) > 1) {
            $columns = pluginApp(ItemColumnBuilder::class)
                ->withVariationStock([VariationStockFields::STOCK_PHYSICAL])
                ->withVariationBase([VariationBaseFields::ID, VariationBaseFields::LIMIT_ORDER_BY_STOCK_SELECT])
                ->withVariationRetailPrice([VariationRetailPriceFields::BASE_PRICE])
                ->build();
            $filter = pluginApp(ItemFilterBuilder::class)->variationHasId($ids)->variationHasRetailPrice()->build();
            $params = pluginApp(ItemParamsBuilder::class)
                ->withParam(ItemColumnsParams::TYPE, 'virtual')
                ->withParam(ItemColumnsParams::LANGUAGE, Utils::getLang())
                ->withParam(ItemColumnsParams::PLENTY_ID, pluginApp(Application::class)->getPlentyId())
                ->withParam(ItemColumnsParams::CUSTOMER_CLASS, pluginApp(ContactRepositoryContract::class)->getContactClassId())
                ->build();
            $records = pluginApp(ItemDataLayerRepositoryContract::class)->search($columns, $filter, $params);
            $seen = [];
            foreach ($records as $record) {
                $id = $record['variationBase']['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                // IO reads current() for a single ID. Delegate ambiguous or incomplete rows to it.
                if (isset($seen[$id]) || !isset($record['variationBase']['limitOrderByStockSelect'], $record['variationStock']['stockPhysical'])) {
                    unset($salable[$id]);
                } else {
                    $salable[$id] = !($record['variationBase']['limitOrderByStockSelect'] == 1 && $record['variationStock']['stockPhysical'] <= 0);
                }
                $seen[$id] = true;
            }
        }
        $itemService = null;
        foreach ($ids as $id) {
            if (!isset($salable[$id])) {
                // IO returns true for a blocked variation. Preserve the historical Twig inversion.
                $itemService = $itemService ?? pluginApp(ItemService::class);
                $salable[$id] = !$itemService->getVariationIsSalable($id);
            }
        }
        return $salable;
    }

    /** Each visible value is available if at least one salable variation contains the pair. */
    public function getSalableAttributeValues(iterable $variations, array $salableByVariation): array
    {
        $values = [];
        foreach ($variations as $variation) {
            if (empty($salableByVariation[$variation['variationId']])) {
                continue;
            }
            foreach ($variation['attributes'] ?? [] as $attribute) {
                if (is_array($attribute) || $attribute instanceof \ArrayAccess) {
                    $attributeId = $attribute['attributeId'] ?? null;
                    $valueId = $attribute['attributeValueId'] ?? null;
                } else {
                    $attributeId = $attribute->attributeId ?? null;
                    $valueId = $attribute->attributeValueId ?? null;
                }
                if ($attributeId !== null && $valueId !== null) {
                    $values[(int)$attributeId][(int)$valueId] = true;
                }
            }
        }
        return $values;
    }

    public function getCardAttributeMaps(array $itemIds, bool $firstBoxOnly = false): array
    {
        $mapsByItem = [];
        // Attribute maps can include documents and images. Keep only the attributes
        // after each group, even when a ShopBuilder list has a large page size.
        foreach (array_chunk(array_unique(array_filter($itemIds)), 24) as $ids) {
            $factories = [];
            foreach ($ids as $id) {
                $factories['item' . $id] = $this->cardAttributeFactory($id);
            }
            $results = pluginApp(ItemSearchService::class)->getResults($factories);
            foreach ($ids as $id) {
                $key = 'item' . $id;
                if (!isset($results[$key])) {
                    throw pluginApp(\UnexpectedValueException::class, ['message' => 'Missing card attribute result for ' . $id]);
                }
                $attributes = $results[$key]['attributes'] ?? [];
                if ($firstBoxOnly && is_array($attributes)) {
                    $attributes = $this->firstCardBox($attributes);
                }
                $mapsByItem[$id] = ['attributes' => $attributes, 'wishChoices' => $this->cardWishChoices($results[$key])];
            }
            unset($results);
        }
        $this->completeWishChoices($mapsByItem);
        return $mapsByItem;
    }

    /** IO paginates very large attribute maps. Batch only their outstanding pages. */
    private function completeWishChoices(array &$maps): void
    {
        $pending = [];
        foreach ($maps as $id => $map) {
            if (isset($map['wishChoices']['afterKey'])) {
                $pending[$id] = $map['wishChoices']['afterKey'];
            }
        }
        $seen = [];
        while ($pending) {
            $next = [];
            foreach (array_chunk($pending, 24, true) as $group) {
                $factories = [];
                foreach ($group as $id => $cursor) {
                    $key = json_encode($cursor);
                    if (isset($seen[$id][$key])) {
                        throw pluginApp(\UnexpectedValueException::class, ['message' => 'Repeated variation map cursor for ' . $id]);
                    }
                    $seen[$id][$key] = true;
                    $factories['item' . $id] = VariationAttributeMap::getSearchFactory(['itemId' => $id, 'afterKey' => $cursor]);
                }
                $results = pluginApp(ItemSearchService::class)->getResults($factories);
                foreach ($group as $id => $cursor) {
                    if (!isset($results['item' . $id]['variations'])) {
                        throw pluginApp(\UnexpectedValueException::class, ['message' => 'Missing variation map page for ' . $id]);
                    }
                    $page = $this->cardWishChoices($results['item' . $id]);
                    $choices = &$maps[$id]['wishChoices'];
                    $choices['rows'] += $page['rows'];
                    foreach ($page['names'] as $attributeId => $field) {
                        if (isset($choices['names'][$attributeId])) {
                            $choices['names'][$attributeId]['values'] += $field['values'];
                        } else {
                            $choices['names'][$attributeId] = $field;
                        }
                    }
                    unset($choices['afterKey'], $choices);
                    if (isset($page['afterKey'])) {
                        $next[$id] = $page['afterKey'];
                    }
                }
            }
            $pending = $next;
        }
    }

    /** Send only the IDs, sale flags and labels used by the shared picker. */
    private function cardWishChoices(array $result): array
    {
        $names = [];
        foreach ($result['attributes'] ?? [] as $field) {
            if (!is_array($field) || !isset($field['attributeId'], $field['name'])) {
                continue;
            }
            $values = [];
            foreach ($field['values'] ?? [] as $value) {
                $values[$value['attributeValueId']] = $value['name'];
            }
            $names[$field['attributeId']] = ['name' => $field['name'], 'values' => $values];
        }
        $choices = $this->getWishChoices($result['variations'] ?? [], $names);
        if (!empty($result['afterKey'])) {
            $choices['afterKey'] = $result['afterKey'];
        }
        return $choices;
    }

    public function getWishChoices(iterable $variations, array $names, array $salability = [], array $units = [], $itemData = null, bool $selectorLabels = false): array
    {
        if ($selectorLabels) {
            $labels = [];
            foreach ($names as $field) {
                $labels[$field['attributeId']] = ['name' => $field['name'],
                    'values' => array_column($field['values'], 'name', 'attributeValueId')];
            }
            $names = $labels;
        }
        $rows = [];
        foreach ($variations as $variation) {
            $id = $variation['variationId'] ?? null;
            if (!ctype_digit((string)$id) || (int)$id < 1) {
                continue;
            }
            $attributes = [];
            foreach ($variation['attributes'] ?? [] as $pair) {
                if (is_object($pair) && !($pair instanceof \ArrayAccess)) {
                    $attributes[$pair->attributeId] = $pair->attributeValueId;
                } else {
                    $attributes[$pair['attributeId']] = $pair['attributeValueId'];
                }
            }
            $salable = $salability[$id] ?? $variation['isSalable'] ??
                (($itemData['item']['flagTwo'] ?? null) == 3 ||
                ($id == ($itemData['variation']['id'] ?? null) && ($itemData['filter']['isSalable'] ?? false)));
            if ($id == ($itemData['variation']['id'] ?? null) && isset($itemData['filter']['isSalable']) && !$itemData['filter']['isSalable']) {
                $salable = false;
            }
            $rows[$id] = [$salable === true || $salable === 1, $attributes];
            $unit = $variation['unitName'] ?? $units[$variation['unitCombinationId'] ?? ''] ?? '';
            if ($unit !== '') {
                $rows[$id][] = $unit;
            }
        }
        return ['rows' => $rows, 'names' => $names];
    }

    private function firstCardBox(array $attributes): array
    {
        foreach ($attributes as $attribute) {
            // Let the template resolve object-backed platform values as before.
            if (!is_array($attribute) || (isset($attribute['type']) && !is_string($attribute['type']))) {
                return $attributes;
            }
            if (($attribute['type'] ?? null) === 'box') {
                return [$attribute];
            }
        }
        return [];
    }
    public function getBrandGroups($children): array
    {
        $letters = [];
        $other = [];
        $numbers = [];
        foreach ($children ?? [] as $child) {
            $details = is_array($child) ? ($child['details'] ?? []) : ($child->details ?? []);
            $detail = $details[0] ?? null;
            $name = trim(is_array($detail) ? ($detail['name'] ?? '') : ($detail->name ?? ''));
            if ($name === '') {
                continue;
            }
            $initial = strtoupper(mb_strtolower(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8'));
            if (preg_match('/^[0-9]$/D', $initial)) {
                $numbers[] = $child;
            } elseif (preg_match('/^[A-Z]$/D', $initial)) {
                $letters[$initial][] = $child;
            } else {
                $other[$initial][] = $child;
            }
        }
        ksort($letters);
        ksort($other);
        return $letters + $other + ($numbers ? ['0–9' => $numbers] : []);
    }

    public function getCardLinks($itemUrl, $currency): array
    {
        $parts = explode('#', (string)$itemUrl, 2);
        $fragment = isset($parts[1]) ? '#'.$parts[1] : '';
        $pathQuery = explode('?', $parts[0], 2);
        $params = [];
        foreach (explode('&', $pathQuery[1] ?? '') as $pair) {
            $name = strtolower(urldecode(explode('=', $pair, 2)[0]));
            if ($pair !== '' && $name !== 'quickviewitem' && $name !== 'currency') {
                $params[] = $pair;
            }
        }
        if ($currency && $currency !== 'EUR') {
            $params[] = 'currency='.rawurlencode($currency);
        }
        $url = $pathQuery[0].($params ? '?'.implode('&', $params) : '');
        return ['product' => $url.$fragment, 'quickview' => $url.($params ? '&' : '?').'quickViewItem=true'.$fragment];
    }

    public function getImageAttributeList($itemId,$attributeNameMap)
    {
        $attributeImageList = null;
        
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        $authHelper = pluginApp(AuthHelper::class);
        
        /** @var AttributeValueImageRepositoryContract $AttributeValueImageRepositoryContract */
        $AttributeValueImageRepositoryContract = pluginApp(AttributeValueImageRepositoryContract::class);
        
        /** @var AttributeValueImageRepositoryContract $AttributeValueImageRepositoryContract */
        $ItemImageRepositoryContract = pluginApp(ItemImageRepositoryContract::class);
      
        $attributeImageList = $authHelper->processUnguarded(
            function () use ($AttributeValueImageRepositoryContract, $itemId, $attributeNameMap, $ItemImageRepositoryContract) {
                $associativeArray = [];
                $imagesById = [];
                
                
                foreach ($attributeNameMap as $attrGroupKey => $attrGroupValue){
                    $attrValuesWithImages = array(); 
                    foreach ($attrGroupValue["values"] as $attrKey => $attrValue){
                        $filters = [
                            "itemId" => $itemId,
                            "attributeId" => $attrGroupKey,
                            "valueId" => $attrKey
                        ];
                        $AttributeValueImageRepositoryContract->setFilters($filters);
                        $attributeValueImages = $AttributeValueImageRepositoryContract->search();
                        
                        if(sizeof($attributeValueImages) > 0){
                            foreach ($attributeValueImages as $attributeValueImage) {
                                //$attrImg = array_first($attributeValueImage);//[0];
                                //$attrValuesWithImages[$attrKey] = $attributeValueImage;
                                $imageId = $attributeValueImage["imageId"];
                                if ($imageId != null) {
                                    if (!array_key_exists($imageId, $imagesById)) {
                                        $imagesById[$imageId] = $ItemImageRepositoryContract->show($imageId);
                                    }
                                    $attrValuesWithImages[$attrKey] = $imagesById[$imageId];
                                }else{
                                    $attrValuesWithImages[$attrKey] = "";
                                }
                            }
                        }else{
                            $attrValuesWithImages[$attrKey] = "";
                        }
                        
                        
                    }
                    $attrGroupValue["images"] = $attrValuesWithImages;
                    $associativeArray[$attrGroupKey] = $attrGroupValue;
                }
                return $associativeArray;
                
            }
        );
        
        return $attributeImageList;
    }
    public function getFeedbackItemList($itemId, $page, $maxCount)
    {
        /** @var \Plenty\Modules\Authorization\Services\AuthHelper $authHelper */
        //$authHelper = pluginApp(AuthHelper::class);
        
        /** @var FeedbackRepositoryContract $FeedbackRepositoryContract */
        $FeedbackRepositoryContract = pluginApp(FeedbackRepositoryContract::class);
                        
        $filters = [
            "itemId" => $itemId,
            "ratingValue" => 5 
            /*,
            "isVisible" => false*/
        ];

        $feedbacksItem = $FeedbackRepositoryContract->listFeedbacks($page, $maxCount, [], $filters);
        $feedbacksReturn = null;
        if($feedbacksItem == null){
            $feedbacksReturn = null;
        }else{
            $feedbacksReturn = $feedbacksItem->toArray();
        }
        return $feedbacksReturn;
    }
    public function getListFeedbackAverages($itemId)
    {
        /** @var FeedbackAverageRepositoryContract $FeedbackAverageRepositoryContract */
        $FeedbackAverageRepositoryContract = pluginApp(FeedbackAverageRepositoryContract::class);

        $feedbacksItem = $FeedbackAverageRepositoryContract->getFeedbackAverage($itemId);
        $feedbacksReturn = null;
        if($feedbacksItem == null){
            $feedbacksReturn = null;
        }else{
            $feedbacksReturn = $feedbacksItem->toArray();
        }
        
        return $feedbacksReturn;
    }

    /** Kept for stored Twig callers. Its result is always the current basket. */
    public function restGetContent($query_url)
    {
        return pluginApp(BasketService::class)->getBasketForTemplate();
    }
    public function sortCatByKey($array, $sortKey)
    {
        $tmpt = $array->toarray();
        $sorted = $this->arrayOrderBy($tmpt, $sortKey);
        return $sorted;
    }

    private function arrayOrderBy(array &$arr, $order = null) {
        if (is_null($order)) {
            return $arr;
        }
        $orders = explode(',', $order);
        usort($arr, function($a, $b) use($orders) {

            $result = array();
            foreach ($orders as $value) {

                list($field, $sort) = array_map('trim', explode(' ', trim($value)));
                if (!(isset($a["details"][0][$field]) && isset($b["details"][0][$field]))) {
                    continue;
                }
                if (strnatcmp($sort, 'desc') === 0) {
                    $tmp = $a;
                    $a = $b;
                    $b = $tmp;
                }
                if (is_numeric($a["details"][0][$field]) && is_numeric($b["details"][0][$field]) ) {
                    $result[] = $a["details"][0][$field] - $b["details"][0][$field];
                } else {
                    $result[] = strnatcmp($a["details"][0][$field], $b["details"][0][$field]);
                }
            }
            return implode('', $result);
        });
        return $arr;
    }

    public function getCrossSeller($itemId, $crossSellerType, $maxItems){
        $options = array(
            "itemId" => $itemId,
            "relation" => $crossSellerType      // Nutze die Liste Zubehoer
        );
        $searchfactory = CrossSellingItems::getSearchFactory( $options );
        $searchfactory->setPage(1, $maxItems); // Begrenze auf 4 Artikel
        $result = pluginApp(ItemSearchService::class)->getResult($searchfactory);
        return $result['documents'];
    }

    // =========================
    // NEU: Preisbereichs-Resolver
    // Priorität: URL > Funktions-Parameter > 0/0
    // =========================
    private function resolvePriceRange(array $priceFilter = [0,0]): array
    {
        $minParam = $this->req->get('priceMin', null);
        $maxParam = $this->req->get('priceMax', null);

        $priceMin = is_numeric($minParam) ? (float)$minParam
                  : (isset($priceFilter[0]) ? (float)$priceFilter[0] : 0.0);

        $priceMax = is_numeric($maxParam) ? (float)$maxParam
                  : (isset($priceFilter[1]) ? (float)$priceFilter[1] : 0.0);

        // Sanity
        if ($priceMin < 0) $priceMin = 0.0;
        if ($priceMax < 0) $priceMax = 0.0;
        if ($priceMax > 0 && $priceMin > $priceMax) {
            $t = $priceMin; $priceMin = $priceMax; $priceMax = $t;
        }

        return [(float)$priceMin, (float)$priceMax];
    }

    public function createSeoPage($template, $pageMax = null, $currentPage = null, $total = null)
    {
        // SeoPage is not a shared container binding: constructor data belongs
        // to this render only, including when another page is rendered later.
        return pluginApp(SeoPage::class, [
            'query' => $this->req->query(),
            'requestUri' => $this->req->getRequestUri(),
            'template' => $template,
            'pageMax' => $pageMax,
            'currentPage' => $currentPage,
            'total' => $total
        ]);
    }

    public function prepareSeoContent(SeoPage $page, $categoryId): string
    {
        if ($page->shopBuilderHtml !== null) {
            return '';
        }
        $page->shopBuilderHtml = $this->twig->render(
            'GuardiansBasic::Category.Content.PreparedShopBuilder',
            ['seoCategoryId' => $categoryId]
        );
        // ShopBuilder can return an already rendered widget or use another Twig instance.
        // Read the bounds from the same markup the visitor receives, including empty lists.
        $offset = 0;
        while (preg_match('~ data-eg-list-page="([1-9][0-9]*):([0-9]+):([0-9]+):([0-9]+|-)"~', $page->shopBuilderHtml, $list, PREG_OFFSET_CAPTURE, $offset)) {
            $page->addList((int)$list[1][0], (int)$list[2][0], (int)$list[3][0], $list[4][0] === '-' ? null : (int)$list[4][0]);
            $offset = $list[4][1] + 1; // Resume past the start of the last numeric field.
        }
        return '';
    }

    private function categoryDocumentTotal($search, array $options, int $estimate, int $count): int
    {
        $size = $options['itemsPerPage'];
        $start = ($options['page'] - 1) * $size;
        if ($count < $size && ($count > 0 || $start === 0)) {
            return $start + $count;
        }

        $known = $count > 0 ? $start + $count : 0;
        $window = $size;
        if ($estimate > $size * 4 && $estimate > $known) {
            // setPage has no independent offset. Of two nearly equal windows, use the
            // one with fewer rows before the estimated last hit. The hit count below
            // still proves the boundary when the estimate is wrong in either direction.
            $window = $size * 4;
            if (($estimate - 1) % ($window + 1) < ($estimate - 1) % $window) {
                $window++;
            }
        }
        $low = (int)floor($known / $window); // blocks known to be full
        $high = $count === 0 ? (int)ceil($start / $window) : null; // first known empty block (zero-based)
        $block = (int)floor($estimate / $window);
        if ($block < $low) {
            $block = $low;
        }
        $step = 1;
        $end = null;
        while ($high === null || $low < $high) {
            if ($high !== null && $block >= $high) {
                $block = $high - 1;
            }
            $options['page'] = $block + 1;
            $options['itemsPerPage'] = $window;
            // Only count hits. Keep the platform preset's grouping and visibility filters.
            $factory = CategoryItems::getSearchFactory($options);
            $factory->withResultFields(['variation.id']);
            $probe = $search->getResults(['boundary' => $factory]);
            $documents = $probe['boundary']['documents'] ?? null;
            if (!is_array($documents)) {
                throw pluginApp(\UnexpectedValueException::class, ['message' => 'Missing category boundary documents']);
            }
            $found = count($documents);
            if ($found > 0 && $found < $window) {
                $end = $block * $window + $found;
                break;
            }
            if ($found === 0 && $high === null) {
                // Exact multiples and small overestimates often end in the preceding block.
                $high = $block;
                $block--;
                continue;
            }
            if ($found === 0) {
                $high = $block;
            } else {
                $low = $block + 1;
            }
            if ($high !== null) {
                $block = $low + (int)floor(($high - $low) / 2);
            } else {
                $block += $step;
                $step *= 2;
            }
        }
        $end = $end ?? ($low * $window);
        // Concurrent updates can make the probe disagree with the already rendered page.
        if ($count === 0 && $end > $start) {
            return $start;
        }
        return $end < $known ? $known : $end;
    }

    /** The second argument is retained for compatibility with saved ShopBuilder widgets. */
    public function getItemsFromCat($categoryId, $maxItemsPerPage, $ownFacets, $priceFilter = [0,0])
    {
        $categoryService = pluginApp(CategoryService::class);
        $activeCategory = $categoryService->get($categoryId);
        // The source category belongs to the list only. Never re-initialize the
        // routed page's shared CategoryContext (metadata, assets, notifications).

        $facets = array_values(array_unique(array_merge($ownFacets ?: [], $this->requestedFacetIds())));
        $sorting = $this->req->get('sorting', '');
        $sorting = is_string($sorting) ? $sorting : '';

        // --- NEU: Preisspanne flexibel auflösen (URL > Param > 0/0)
        [$priceMin, $priceMax] = $this->resolvePriceRange($priceFilter);

        $itemListOptions = [
            'page'          => SeoPage::positiveInteger($this->req->get('page', 1)) ?? 1,
            'itemsPerPage'  => self::SHOPBUILDER_ITEMS_PER_PAGE,
            'sorting'       => $sorting,
            'facets'        => $facets,
            'categoryId'    => $categoryId,
            'priceMin'      => $priceMin,
            'priceMax'      => $priceMax
        ];

        $itemListOptions = SearchOptions::validateItemListOptions($itemListOptions, SearchOptions::SCOPE_CATEGORY);

        $effectivePage = SeoPage::positiveInteger($itemListOptions['page']);
        $effectiveSize = SeoPage::positiveInteger($itemListOptions['itemsPerPage']);
        if ($effectivePage === null || $effectiveSize !== self::SHOPBUILDER_ITEMS_PER_PAGE) {
            throw pluginApp(\UnexpectedValueException::class, [
                'message' => 'Invalid effective category pagination after SearchOptions validation'
            ]);
        }
        $itemListOptions['page'] = $effectivePage;
        $itemListOptions['itemsPerPage'] = $effectiveSize;

        // The infinite-scroll fragment renders cards and pagination without filter controls.
        $listFragment = $this->req->get('quickViewItem') === 'true';
        $searchParams = [];
        if (!$listFragment) {
            $searchParams['facets'] = Facets::getSearchFactory($itemListOptions);
        }
        $searchParams['itemList'] = CategoryItems::getSearchFactory($itemListOptions);
        /** @var ItemSearchService $itemSearchService */
        $itemSearchService = pluginApp( ItemSearchService::class );

        $resultItems = $itemSearchService->getResults($searchParams);
        $total = $resultItems['itemList']['total'];
        $resultItems['itemList'] = $resultItems['itemList']['documents'] ?? null;
        $documentCount = is_array($resultItems['itemList']) ? count($resultItems['itemList']) : null;
        // The grouped search reports an estimated cardinality. A short document
        // page proves the end. On a full page, verify the estimate against the same list.
        if ($documentCount !== null) {
            $total = $this->categoryDocumentTotal($itemSearchService, $itemListOptions, (int)$total, $documentCount);
        }
        $pageMax = ceil($total / $effectiveSize);
        $resultItems['currentPage'] = $effectivePage;
        $resultItems['itemCountTotal'] = $total;
        $resultItems['itemCountPage'] = $effectiveSize;
        $resultItems['pageMax'] = $pageMax;
        $resultItems['pageLimit'] = $resultItems['pageMax'];
        $resultItems['categoryId'] = $categoryId;
        $resultItems['category'] = $activeCategory;
        if (!$listFragment) {
            $resultItems['searchOptions'] = SearchOptions::get(SearchOptions::SCOPE_CATEGORY);
        }
        $resultItems['itemSorting'] = $sorting;
        $resultItems['query'] = ['items' => $effectiveSize, 'sorting' => $itemListOptions['sorting']];
        $resultItems['selectedFacets'] = $ownFacets;

        return  $this->twig->render('GuardiansBasic::Category.Item.CategoryItemContent', $resultItems);
    }

    public function getItemsFromCatSimple($categoryId, $maxItemsPerPage, $ownFacets, $priceFilter = [0,0])
    {
        // --- NEU: Preisspanne flexibel auflösen (URL > Param > 0/0)
        [$priceMin, $priceMax] = $this->resolvePriceRange($priceFilter);

        $itemListOptions = [
            'page'          => 1,
            'itemsPerPage'  => $maxItemsPerPage,
            'sorting'       => 'variation.createdAt_desc',
            'facets'        => $ownFacets,
            'categoryId'    => $categoryId,
            'priceMin'      => $priceMin,
            'priceMax'      => $priceMax
        ];

        $itemListOptions = SearchOptions::validateItemListOptions($itemListOptions, SearchOptions::SCOPE_CATEGORY);

        $searchParams = [
            'itemList' => CategoryItems::getSearchFactory($itemListOptions)
        ];

        /** @var ItemSearchService $itemSearchService */
        $itemSearchService = pluginApp(ItemSearchService::class);
        $results = $itemSearchService->getResults($searchParams);

        return $this->twig->render('GuardiansBasic::Category.Item.Partials.CategoryGridOnly', [
            'itemList' => $results['itemList']['documents']
        ]);
    }

    public function sortSizes($attrValues){
        if($attrValues != null){
            usort($attrValues, array($this,'cmp'));
        }
        return $attrValues;
    }

    private function cmp($a, $b)
{
    static $lookup = null;

    if ($lookup === null) {
        $sizeList = [
            "XXS","XXS (47-51)","XXS/XS","XS","XS/34","XS/36","XS/AB","XS/S",
            "S","S (42/44)","S (85 cm)","S-M","S/34","S/36","S/38","S/42","S/CD","S/L","S/M","SM",
            "M","M (46/48)","M (53-56)","M (95 cm)","M/36","M/38","M/40","M/46","M/L",
            "L","L (105 cm)","L/38","L/40","L/42","L/CD","L/XL",
            "XL","XL (54/56)","XL-XXL","XL/40","XL/42","XL/XXL","XLT",
            "XXL","XXL/42","XXL/44","2XL","2XL2","2XLL","2XLT","2XT",
            "XXXL","3XL","3XL2","3XLT","3XT","4XL","4XLL","4XLT","5XL",
            "27","27 1/3","27.5","27 1/2","27 2/3","28","28 1/3","28.5","28 1/2","28 2/3",
            "29","29 1/3","29.5","29 1/2","29 2/3","30","30 1/3","30.5","30 1/2","30 2/3",
            "31","31 1/3","31.5","31 1/2","31 2/3","32","32 1/3","32.5","32 1/2","32 2/3",
            "33","33 1/3","33.5","33 1/2","33 2/3","34","34 1/3","34.5","34 1/2","34 2/3",
            "35","35 1/3","35.5","35 1/2","35 2/3","36","36 1/3","36.5","36 1/2","36 2/3",
            "37","37 1/3","37.5","37 1/2","37 2/3","38","38 1/3","38.5","38 1/2","38 2/3",
            "39","39 1/3","39.5","39 1/2","39 2/3","40","40 1/3","40.5","40 1/2","40 2/3",
            "41","41 1/3","41.5","41 1/2","41 2/3","42","42 1/3","42.5","42 1/2","42 2/3",
            "43","43 1/3","43.5","43 1/2","43 2/3","44","44 1/3","44.5","44 1/2","44 2/3",
            "45","45 1/3","45.5","45 1/2","45 2/3","46","46 1/3","46.5","46 1/2","46 2/3",
            "47","47 1/3","47.5","47 1/2","47 2/3","48","48 1/3","48.5","48 1/2","48 2/3",
            "49","49 1/3","49.5","49 1/2","49 2/3","50","50 1/3","50.5","50 1/2","50 2/3",
            "51","51 1/3","51.5","51 1/2","51 2/3","52","52 1/3","52.5","52 1/2","52 2/3",
            "52-54","53","53 1/3","53.5","53 1/2","53 2/3","54","54 1/3","54.5","54 1/2","54 2/3",
            "55","55 1/3","55.5","55 1/2","55 2/3","56","57","58","58-60","60","61","62"
        ];

        $lookup = array_flip($sizeList); // z. B. ['XS' => 3, 'S' => 8, ...]
    }

    $aKey = $lookup[$a] ?? PHP_INT_MAX; // alles Unbekannte ans Ende
    $bKey = $lookup[$b] ?? PHP_INT_MAX;

    return $aKey <=> $bKey;
}

}
