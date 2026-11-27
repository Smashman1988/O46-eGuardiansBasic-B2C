<?php
// php tests/card_attributes.php <Twig autoloader> <report.json> [before-tag-template-dir]
namespace Plenty\Plugin\Templates\Extensions { class Twig_Extension {} }
namespace Plenty\Modules\Webshop\ItemSearch\SearchPresets {
    class VariationAttributeMap {
        public static function getSearchFactory($options) {
            if (array_keys($options) !== ['itemId','afterKey'] || ($options['afterKey'] !== null && !is_array($options['afterKey']))) {
                throw new \RuntimeException('Changed attribute search options');
            }
            return $options;
        }
    }
}
namespace {
// Twig 2 on PHP 8.4 cannot compile a closure name as a PHP expression.
class CardCallback {
    private $fn;
    public function __construct($fn) { $this->fn = $fn; }
    public function invoke(...$args) { return ($this->fn)(...$args); }
}
function callback($fn) { return [new CardCallback($fn),'invoke']; }
require $argv[1];
require __DIR__ . '/../src/Extensions/Base_Extension.php';
function check($ok, $msg) { if (!$ok) { throw new \RuntimeException($msg); } }
function pluginApp($class, $params = []) {
    global $service;
    if ($class === \GuardiansBasic\Services\WishListAvailability::class) {
        require_once __DIR__.'/fixtures/WishStock.php';
        return fixtureWishStock($class);
    }
    if ($class === \UnexpectedValueException::class) { return new \UnexpectedValueException($params['message']); }
    check($class === \Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService::class, $class);
    return $service;
}
class AttributeService {
    public $batches = [], $singles = [], $omit = false, $lang = 'de';
    private function result($id) {
        $values = [['attributeValueId'=>10,'position'=>2,'name'=>$this->lang . '-L-' . $id], ['attributeValueId'=>11,'position'=>1,'name'=>$this->lang . '-M-' . $id]];
        $attributes = [['type'=>'image','values'=>[['position'=>1,'name'=>'blue']]]];
        if ($id % 3 === 1) { $attributes[] = ['attributeId'=>1,'name'=>'Größe','type'=>'box','values'=>$values]; }
        if ($id % 3 === 2) { $attributes = []; }
        $rows = [];
        foreach ($values as $i => $value) {
            $rows[] = ['variationId'=>$id * 10 + $i, 'isSalable'=>true, 'attributes'=>[['attributeId'=>1,'attributeValueId'=>10+$i]]];
        }
        return ['attributes'=>$attributes, 'variations'=>$rows, 'documents'=>[['id'=>$id]]];
    }
    public function getResult($factory) {
        $this->singles[] = $factory['itemId'];
        return $this->result($factory['itemId']);
    }
    public function getResults($factories) {
        $ids = array_column($factories,'itemId');
        $this->batches[] = $ids;
        $results = [];
        // The documented contract keys results by factory name, independently of order.
        foreach (array_reverse($factories, true) as $key=>$factory) {
            if (!$this->omit) { $results[$key] = $this->result($factory['itemId']); }
        }
        return $results;
    }
}
$service = new AttributeService();
$ext = (new \ReflectionClass(\GuardiansBasic\Extensions\Base_Extension::class))->newInstanceWithoutConstructor();
$view = dirname(__DIR__) . '/resources/views/Category/Item/';
$prefix = 'GuardiansBasic::Category.Item.Partials.';
$templates = ['layout'=>'{% block PageBody %}{% endblock %}', 'category'=>file_get_contents($view.'CategoryItem.twig'),
    'content'=>file_get_contents($view.'CategoryItemContent.twig'), 'simple'=>file_get_contents($view.'Partials/CategoryGridOnly.twig')];
foreach (['CategoryListItem','CategoryListItem2','TagListCards','CategoryAttributes','AllItemsLink'] as $name) {
    $templates[$prefix.$name] = file_get_contents($view.'Partials/'.$name.'.twig');
}
foreach (['Ceres::PageDesign.Macros.LayoutContainer','Ceres::PageDesign.Macros.ItemName',
    'GuardiansBasic::Category.Macros.render-rating',$prefix.'Pagination',$prefix.'FacetFilter2',$prefix.'FacetFilter3'] as $name) {
    $templates[$name] = '';
}
$twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader($templates));
foreach (['config','plugin_path','queryString','getStorefrontLanguageRoutes','createSeoPage'] as $name) {
    $twig->addFunction(new \Twig\TwigFunction($name, callback(function () { return ''; })));
}
$twig->addFunction(new \Twig\TwigFunction('getPartial', callback(function () { return 'layout'; })));
$translationCalls = [];
$twig->addFunction(new \Twig\TwigFunction('trans', callback(function ($key) use (&$translationCalls) {
    $translationCalls[] = $key;
    return $key;
})));
$twig->addFunction(new \Twig\TwigFunction('getAttributesWithImages', [$ext,'getAttributesWithImages']));
$batch = false;
foreach (['CHF'=>'?ref=x%2By&currency=CHF','EUR'=>'?ref=x%2By'] as $currency=>$query) {
    $links=$ext->getCardLinks('/art/?Currency=USD&ref=x%2By&currency=CHF&quickViewItem=false#sizes',$currency);
    check($links===['product'=>'/art/'.$query.'#sizes','quickview'=>'/art/'.$query.'&quickViewItem=true#sizes'],'B2C card URLs for '.$currency);
}
$twig->addFunction(new \Twig\TwigFunction('getCardLinks',[$ext,'getCardLinks']));
$twig->addFunction(new \Twig\TwigFunction('getCardAttributeMaps', callback(function ($ids, $firstBoxOnly = false) use ($ext,&$batch) {
    check($firstBoxOnly, 'Theme list must request the card projection');
    return $batch ? $ext->getCardAttributeMaps($ids, $firstBoxOnly) : [];
})));
$twig->addFilter(new \Twig\TwigFilter('itemURL', callback(function ($data) { return '/product/'.($data['variation']['itemId'] ?? ''); })));
$twig->addFilter(new \Twig\TwigFilter('itemName', callback(function ($data) { return 'Product '.($data['variation']['itemId'] ?? ''); })));
$twig->addFilter(new \Twig\TwigFilter('orderByKey', callback(function ($rows,$key) {
    usort($rows,function ($a,$b) use ($key) { return $a[$key] <=> $b[$key]; }); return $rows;
})));
$report = [];
if (getenv('O46_WISH_CACHE_FIXTURES')) {
    $picker = file_get_contents(dirname(__DIR__).'/resources/views/Item/Partials/WishListPicker.twig');
    file_put_contents(getenv('O46_WISH_CACHE_FIXTURES').'/controls.html', $twig->createTemplate($picker)->render([]));
}
foreach (['category','content','simple'] as $template) {
    foreach ([1,2] as $page) {
        foreach ([0,1,6,12,24,48,50,60,66,100,500,'duplicates'] as $n) {
            $ids = $n === 'duplicates' ? [100,101,102,100] : ($n ? range(100,99+$n) : []);
            $items = [];
            foreach ($ids as $id) {
                $items[] = ['data'=>['variation'=>['id'=>10000+$id,'itemId'=>$id],'item'=>['id'=>$id],
                    'images'=>['all'=>[]], 'prices'=>['default'=>['unitPrice'=>['value'=>5,'formatted'=>'5 EUR']]]]];
            }
            $ctx = ['itemList'=>$items,'currentPage'=>$page,'itemCountTotal'=>count($ids),'category'=>['id'=>100]];
            foreach ([false,true] as $batch) {
                $service->batches = $service->singles = [];
                $html = $twig->render($template,$ctx);
                if (!$batch) {
                    check($service->singles === $ids && !$service->batches,'Single-card reference');
                    $reference = $html;
                    continue;
                }
                check($html === $reference,'Different markup in '.$template);
                check(substr_count($html,'data-eg-item="') === count($ids),'Rendered card count');
                if ($ids) {
                    check(strpos($html,'>de-M-100<') < strpos($html,'>de-L-100<') && strpos($html,'>de-M-100<') !== false,'Size order');
                    check(strpos($html,'5 EUR') !== false && strpos($html,'/product/100') !== false,'Price or product link');
                }
                if (count($ids) > 1) {
                    check(strpos($html,'Template.availsizeonesize') !== false,'Missing translated one-size fallback');
                    check(strpos($html,'>blue</span>') === false,'Image or color attribute displayed as a size');
                }
                check(!$service->singles && $service->batches === array_chunk(array_unique($ids),24),'Unexpected calls');
                if ($template === 'category' && $page === 1 && in_array($n,[12,24,66,100,500],true)) {
                    $forbidden = new class {
                        public function __call($name, $args) { throw new \RuntimeException('Personal service called on category: '.$name); }
                    };
                    foreach ([[],[1000,1030,1090],range(1000,5999)] as $savedIds) {
                        $visitor = ['services'=>['wishList'=>$forbidden,'basket'=>$forbidden],
                            'savedWishIds'=>$savedIds,'basketItems'=>['itemWishListIds'=>$savedIds]];
                        check($html === $twig->render($template,$visitor + $ctx),'Personal category HTML for '.$n.' cards');
                    }
                    $directory = getenv('O46_WISH_CACHE_FIXTURES');
                    if ($directory) file_put_contents($directory.'/cards-'.$n.'.html',$html);
                }
                $report[] = ['template'=>$template,'page'=>$page,'cards'=>count($ids),'before'=>count($ids),
                    'after'=>count($service->batches),'factories'=>count(array_unique($ids)),'sha256'=>hash('sha256',$html)];
            }
        }
    }
}
// Exercise the actual recommendation selection and card include, without the
// unrelated product/matrix data loaders. Compare to each card's existing fallback.
$single = file_get_contents(dirname(__DIR__).'/resources/views/Item/SingleItem.twig');
check(preg_match('/\{% set currentCategory =.*?(?=\n\s*<\/div>\n\s*(?:<div class="container">|\{% if pdpFeedbackCount is null))/s', $single, $match) === 1, 'Recommendation region');
$twig->getLoader()->setTemplate('recommendations', '{% if isQuickView %}'.$match[0].'{% endif %}');
$recommendations = [];
foreach (['de','en','fr','it','es'] as $language) {
    $service->lang = $language;
    foreach ([[],[100],[100,101,102,103,104],[101,102,103,104,105],[101,101,102,103,104]] as $ids) {
        foreach ([false,true] as $fullPage) {
            $documents = array_map(function ($id) { return ['data'=>['item'=>['id'=>$id],
                'variation'=>['id'=>10000+$id,'itemId'=>$id], 'images'=>['all'=>[]],
                'prices'=>['default'=>['unitPrice'=>['value'=>5,'formatted'=>'5 EUR']]]]]; }, $ids);
            $list = new class($documents) {
                public $calls = [], $documents;
                function __construct($documents) { $this->documents=$documents; }
                function getItemList(...$args) { $this->calls[]=$args; return ['documents'=>$this->documents]; }
            };
            $categoryService = new class { function getCurrentCategory() { return ['id'=>55]; } };
            $urlService = new class($language) {
                public $lang;
                function __construct($lang) { $this->lang=$lang; }
                function getCategoryURL($id) { check($id===55,'Category ID'); return $this; }
                function toAbsoluteUrl($prefix) { return 'https://example.test/'.($prefix?$this->lang.'/':'').'category/'; }
            };
            $context = ['isQuickView'=>$fullPage,'lang'=>$language,'itemData'=>['item'=>['id'=>100]],
                'services'=>['category'=>$categoryService,'itemList'=>$list,'url'=>$urlService]];
            $visible = $fullPage ? array_slice(array_values(array_filter($ids,function($id){return $id!==100;})),0,4) : [];
            foreach ([false,true] as $batch) {
                $service->batches=$service->singles=$list->calls=[];
                $html=preg_replace('/>\s+</','><',trim($twig->render('recommendations',$context)));
                check(count($list->calls)===($fullPage?1:0),'Recommendation query count');
                if ($fullPage) check($list->calls[0]===['category',55,'variation.position_desc',5],'Recommendation query changed');
                if (!$batch) { $reference=$html; check($service->singles===$visible,'Reference visible cards'); continue; }
                check($html===$reference,'Recommendation markup changed');
                check(!$service->singles,'Unexpected per-card attribute call');
                check($service->batches===($visible?[array_values(array_unique($visible))]:[]),'Batch must cover only visible unique cards');
                check(substr_count($html,'data-eg-item="')===count($visible),'Recommendation count');
                $recommendations[]=['lang'=>$language,'fullPage'=>$fullPage,'input'=>$ids,'visible'=>$visible,
                    'beforeCalls'=>count($visible),'afterCalls'=>count($service->batches),'sha256'=>hash('sha256',$html)];
            }
        }
    }
}
$service->lang = 'it';
check($ext->getCardAttributeMaps([100])[100]['attributes'][1]['values'][0]['name'] === 'it-L-100','Leaked another render language');
$tagLists = [];
foreach (['Homepage/Homepage'=>['12',15], 'StaticPages/Topseller'=>['8',50],
          'StaticPages/Neuheiten'=>['7',50], 'StaticPages/Abverkauf'=>['9',50]] as $path => [$tag, $limit]) {
    $source = file_get_contents(dirname(__DIR__).'/resources/views/'.$path.'.twig');
    $beforeTagSource = isset($argv[3]) ? file_get_contents($argv[3].'/'.basename($path).'.twig') : $source;
    $twig->getLoader()->setTemplate($path, $source);
    $twig->getLoader()->setTemplate($path.'.before', $beforeTagSource);
    foreach ([[], range(100, 99+$limit), [100,101,100]] as $ids) {
        $list = new class {
            public $documents = [], $calls = [];
            function getItemList(...$arguments) {
                $this->calls[] = $arguments;
                return ['documents' => $this->documents];
            }
        };
        foreach ($ids as $id) {
            $list->documents[] = ['data'=>['variation'=>['itemId'=>$id], 'item'=>['id'=>$id],
                'prices'=>['default'=>['unitPrice'=>['value'=>5,'formatted'=>'5 EUR']]]]];
        }
        foreach ([false, true] as $inherited) {
            $checkout = new class {
                public $reads = 0;
                function getCurrencyData() { ++$this->reads; return ['name'=>'CHF']; }
            };
            $context = ['services'=>['itemList'=>$list, 'checkout'=>$checkout]];
            if ($inherited) {
                $context['currencycheck'] = 'USD';
                $context['translations'] = ['tInBasket'=>'Inherited label'];
                $context['cardAttributeMap'] = ['attributes'=>[['type'=>'box',
                    'values'=>[['position'=>1, 'name'=>'Supplied size']]]]];
            }
            foreach ([false, true] as $batch) {
                $checkout->reads = 0;
                $translationCalls = [];
                $list->calls = $service->batches = $service->singles = [];
                $renderContext = $context;
                if (!$batch && isset($argv[3])) {
                    // Older tag loops expected their caller to supply these labels.
                    $renderContext['translations'] = ($context['translations'] ?? []) + [
                        'tInBasket'=>'GuardiansBasic::Template.inbasket', 'tStatt'=>'GuardiansBasic::Template.statt',
                        'tAvailSizes'=>'GuardiansBasic::Template.availsizes', 'tSustainability'=>'GuardiansBasic::Template.sustainability'
                    ];
                    $renderContext['transLabels'] = ['availsizeonesize'=>'GuardiansBasic::Template.availsizeonesize'];
                }
                $html = $twig->render($batch ? $path : $path.'.before', $renderContext);
                if (isset($argv[3])) {
                    // The shared loop changes indentation between tags in historical templates.
                    $html = preg_replace('/>\s*\n\s*</', '><', trim($html));
                }
                check($list->calls === [['tag_list',$tag,'sorting.price.avg_asc',$limit]], 'Tag query changed');
                check(substr_count($html, 'data-eg-item="') === count($ids), 'Tag card count changed');
                if (!$batch) {
                    $reference = $html;
                    check($service->singles === ($inherited ? [] : $ids), 'Tag card fallback changed');
                    continue;
                }
                check($checkout->reads === ($ids && !$inherited ? 1 : 0), 'Tag list currency lookup count: '.$path);
                $label = $inherited ? 'Inherited label' : 'GuardiansBasic::Template.inbasket';
                check(substr_count($html, $label.'</button>') === count($ids), 'Unlabelled tag card: '.$path);
                $lookups = array_count_values($translationCalls);
                foreach (['sustainability','availsizes','statt','inbasket','availsizeonesize'] as $key) {
                    $expected = $ids && ($key !== 'inbasket' || !$inherited) ? 1 : 0;
                    check(($lookups['GuardiansBasic::Template.'.$key] ?? 0) === $expected, 'Unexpected tag translation count: '.$key);
                }
                $expectedBatches = $inherited ? [] : array_chunk(array_unique($ids), 24);
                check($html === $reference && $service->batches === $expectedBatches && !$service->singles,
                    'Tag batching changed cards or search calls for '.$path);
                $tagLists[] = ['template'=>$path, 'cards'=>count($ids), 'inheritedMap'=>$inherited,
                    'callsBefore'=>$inherited ? 0 : count($ids), 'callsAfter'=>count($expectedBatches),
                    'sha256'=>hash('sha256',$html)];
            }
        }
    }
}
$service->omit = true;
try { $ext->getCardAttributeMaps([100]); throw new \RuntimeException('Missing map silently accepted'); }
catch (\UnexpectedValueException $expected) { check(strpos($expected->getMessage(),'100') !== false,'Wrong missing key'); }
$service = new class {
    public $attributes;
    public function getResults($factories) { return ['item100'=>['attributes'=>$this->attributes]]; }
};
$emptyBox = ['type'=>'box', 'values'=>[]];
$otherBox = ['type'=>'box', 'values'=>[['position'=>2, 'name'=>'L']]];
$image = ['type'=>'image', 'values'=>[['position'=>1, 'name'=>'blue']]];
$object = (object)$otherBox;
foreach ([
    [[$image,$emptyBox,$otherBox], [$emptyBox]],
    [[$image,$otherBox,$emptyBox], [$otherBox]],
    [[$image], []],
    [[$object,$otherBox], [$object,$otherBox]],
] as [$full,$expected]) {
    $service->attributes = $full;
    check($ext->getCardAttributeMaps([100])[100]['attributes'] === $full, 'Full map contract changed');
    check($ext->getCardAttributeMaps([100], true)[100]['attributes'] === $expected, 'Wrong first box or fallback');
}
// A continuation can add the only saved or salable ID. Preserve its labels and
// never resolve a truncated map to the first variant.
$service = new class {
    public $calls = [];
    function getResults($factories) {
        $this->calls[] = array_values($factories);
        $result = [];
        foreach ($factories as $key => $options) {
            $page = $options['afterKey']['page'] ?? 1;
            $id = $options['itemId'] * 10 + $page;
            $result[$key] = ['attributes'=>[['attributeId'=>9,'name'=>'Größe','type'=>'box',
                'values'=>[['attributeValueId'=>$page,'name'=>$page===1?'Grün':'Rot']]]],
                'variations'=>[['variationId'=>$id,'isSalable'=>$page===2,'unitName'=>'Paar',
                    'attributes'=>[['attributeId'=>9,'attributeValueId'=>$page]]]]];
            if ($page === 1) $result[$key]['afterKey'] = ['page'=>2];
        }
        return $result;
    }
};
require_once __DIR__.'/fixtures/WishStock.php';
$stock = fixtureWishStock(\GuardiansBasic\Services\WishListAvailability::class);
$stock->groups = [];
$stock->blocked = [212];
$maps = $ext->getCardAttributeMaps([20,21,20],true);
check($stock->groups === [[202,212]], 'Both cards must share one availability check');
check(array_map('count',$service->calls) === [2,2], 'Continuation must batch distinct items');
foreach ([20,21] as $itemId) {
    $choice = $maps[$itemId]['wishChoices'];
    check(array_column($maps[$itemId]['attributes'][0]['values'], 'attributeValueId') === [1,2], 'Card size from the continuation is missing');
    check($maps[$itemId]['availableSizes']['any'] === ($itemId===20), 'Card sizes must use picker availability');
    check($choice['rows'] === [$itemId*10+1=>[false,[9=>1],'Paar'],$itemId*10+2=>[$itemId===20,[9=>2],'Paar']], 'Incomplete stock flags or variant IDs');
    check($choice['names'][9]['values'] === [1=>'Grün',2=>'Rot'] && !isset($choice['afterKey']), 'Incomplete labels or cursor');
}
file_put_contents($argv[2],json_encode(['renders'=>$report,'recommendations'=>$recommendations,'tagLists'=>$tagLists,'contextIsolation'=>true,'missingResult'=>true],JSON_PRETTY_PRINT));
echo count($report)." list, ".count($recommendations)." recommendation and ".count($tagLists)." tag-list matching renders, language isolation and missing-result check passed.\n";
}
