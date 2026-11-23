<?php
// php tests/ceres-list-query.php /path/to/Ceres/src
namespace {
    define('CERES_FIXTURE_SRC', realpath($argv[1]));
    require __DIR__.'/list-fixtures.php';
}
namespace Plenty\Plugin\Log { trait Loggable {} }
namespace IO\Helper { interface ContextInterface {} }
namespace Plenty\Modules\Webshop\ItemSearch\SearchPresets {
    class TagItems extends CategoryItems {}
    class SearchItems extends CategoryItems {}
}
namespace Ceres\Helper {
    class ExternalSearchOptions {
        public static function getExternalSearchOptions() { return new self; }
        public function hasOptions() { return false; }
    }
    class ExternalSearch { public static function hasExternalSearch() { return false; } }
}
namespace Ceres\Contexts {
    class CategoryContext {
        protected $category, $request, $webstoreConfig, $params;
        public function init($params) {
            $this->params=$params;
            $this->category=(object)['id'=>101];
            $this->request=pluginApp(\Plenty\Plugin\Http\Request::class);
            $this->webstoreConfig=(object)['itemSearchOperator'=>'and'];
        }
        protected function getParam($key,$default=null) { return $this->params[$key] ?? $default; }
    }
}
namespace {
    use IO\Services\TemplateService;
    use GuardiansBasic\Extensions\Base_Extension;
    require CERES_FIXTURE_SRC.'/Helper/SearchOptions.php';
    foreach (['ItemListContext','CategoryItemContext','ItemSearchContext','TagSearchContext'] as $file) require CERES_FIXTURE_SRC.'/Contexts/'.$file.'.php';
    require __DIR__.'/../src/Helper/SeoPage.php';
    require __DIR__.'/../src/Extensions/Base_Extension.php';
    require __DIR__.'/../src/Providers/GuardiansBasicServiceProvider.php';
    function verify($ok,$case) { if (!$ok) throw new \RuntimeException($case); }
    $registry = [
        Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService::class=>$search=new Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService,
        Plenty\Plugin\Http\Request::class=>$request=new Plenty\Plugin\Http\Request,
        Plenty\Modules\ContentCache\CacheBlocks\Contracts\CacheTagRepositoryContract::class=>new class {
            public function makeTaggable($tag,$callback,$type){return $callback();}
        },
        Ceres\Config\CeresConfig::class=>(object)['pagination'=>(object)['rowsPerPage'=>[6,12],'columnsPerPage'=>4,'itemsPerPage'=>24],
            'sorting'=>(object)['defaultSorting'=>'item.score','defaultSortingSearch'=>'item.score','data'=>['item.score']]],
        IO\Services\ItemService::class=>new class { public function getAdditionalItemSorting(){ return []; } },
        Plenty\Plugin\Translation\Translator::class=>new class { public function trans($key){ return $key; } }
    ];
    $request->params=['priceMin'=>'7','priceMax'=>'70'];
    $search->total=25;
    $events=new Plenty\Plugin\Events\Dispatcher;
    $twig=new Plenty\Plugin\Templates\Twig;
    (new GuardiansBasic\Providers\GuardiansBasicServiceProvider)->boot($twig,$events,new Plenty\Plugin\ConfigRepository);
    $cases=0;
    foreach (['category.item'=>Ceres\Contexts\CategoryItemContext::class,'search'=>Ceres\Contexts\ItemSearchContext::class,'tags'=>Ceres\Contexts\TagSearchContext::class] as $route=>$class) {
        foreach ([12,24,48] as $arg) {
            $all=[];
            foreach ([1,2,3,4] as $page) {
                $search->requests=[];
                TemplateService::$currentTemplateData=['page'=>(string)$page,'itemsPerPage'=>$arg,'facets'=>'3','sorting'=>'sorting.price.avg_asc',
                    'query'=>'schuhe','tagId'=>42,'tagName'=>'summer'];
                $listeners = $events->listeners['IO.ctx.'.$route];
                krsort($listeners);
                foreach (reset($listeners) as $prepare) { $prepare(); }
                $context=new $class;
                $context->init(TemplateService::$currentTemplateData);
                verify(count($search->requests)===1,$route.' query count');
                foreach ($search->requests[0] as $factory) {
                    verify($factory['itemsPerPage']===12 && $factory['page']===$page && $factory['facets']==='3' &&
                        $factory['sorting']==='sorting.price.avg_asc' && $factory['priceMin']==='7' && $factory['priceMax']==='70',$route.' validated options');
                }
                verify((int)$context->pageMax===3 && $context->itemCountTotal===25 && $context->itemCountPage===[12,12,1,0][$page-1],$route.' page boundaries');
                $all=array_merge($all,array_map(fn($doc)=>$doc['data']['variation']['id'],$context->itemList));
                ++$cases;
            }
            verify($all===range(1,25),$route.' sequence');
        }
    }
    $extension=new Base_Extension(new Plenty\Plugin\Templates\Factories\TwigFactory,$twig,$request);
    $twig->render=function($template,$data){ return json_encode($data,JSON_THROW_ON_ERROR); };
    foreach ([12,24,48] as $arg) {
        $request->params=['page'=>'2','facets'=>'3','sorting'=>'unconfigured','priceMin'=>'7','priceMax'=>'70'];
        $result=json_decode($extension->getItemsFromCat(60,$arg,[]),true);
        $factory=end($search->requests)['itemList'];
        verify($factory['itemsPerPage']===12 && $factory['sorting']==='item.score' && $factory['categoryId']===60 &&
            $factory['facets']===[3] && $factory['priceMin']===7.0 && $factory['priceMax']===70.0,'ShopBuilder validated factory');
        verify($result['pageMax']===3 && $result['itemCountPage']===12 && count($result['itemList'])===12,'ShopBuilder public contract');
        $teaser=json_decode($extension->getItemsFromCatSimple(60,$arg,[]),true);
        verify(count($teaser['itemList'])===min($arg,25) && end($search->requests)['itemList']['itemsPerPage']===$arg,'simple teaser');
    }
    echo "$cases native category/search/tag cases and 6 ShopBuilder/teaser cases passed with reference Ceres validation\n";
}
