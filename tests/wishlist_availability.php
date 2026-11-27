<?php
namespace Plenty\Plugin\Http {
    class Response { public function __construct(public $data, public $code) {} }
}
namespace IO\Api {
    class ResponseCode { const OK = 200; }
}
namespace IO\Api\Resources {
    use Plenty\Plugin\Http\Response;
    class ItemWishListResource {
        public function __construct(protected $request, protected $response) {}
        public function store(): Response {
            $GLOBALS['writes'][] = [$this->request->get('variationId'),$this->request->get('quantity')];
            return $this->response->create(['variationId'=>$this->request->get('variationId')],201);
        }
    }
}
namespace IO\Services {
    class ItemListService { const TYPE_WISH_LIST = 'wish_list'; }
}
namespace Plenty\Modules\Webshop\ItemSearch\Factories {
    class VariationSearchFactory {
        private $ids, $scopes;
        public function isVisibleForClient() { $this->scopes = ['client']; return $this; }
        public function isActive() { $this->scopes[] = 'active'; return $this; }
        public function hasPriceForCustomer() { $this->scopes[] = 'price'; return $this; }
        public function hasVariationIds($ids) { $this->ids = $ids; $GLOBALS['groups'][] = $ids; return $this; }
        public function setPage($page, $size) {
            same(['client','active','price'], $this->scopes);
            same(1,$page); same(count($this->ids),$size);
            return $this;
        }
        public function withResultFields($fields) {
            same(['variation.id','filter.isSalable','stock.net'], $fields);
            return $this;
        }
    }
}
namespace {
    use GuardiansBasic\Resources\WishListResource;
    use GuardiansBasic\Services\WishListAvailability;
    use IO\Services\ItemListService;
    use IO\Services\ItemWishListService;
    use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
    use Plenty\Plugin\Http\Response;
    require __DIR__.'/../src/Resources/WishListResource.php';
    require __DIR__.'/../src/Services/WishListAvailability.php';
    function same($expected, $actual) {
        if ($expected !== $actual) throw new RuntimeException(json_encode([$expected,$actual]));
    }
    function pluginApp($class) { return $GLOBALS['services'][$class]; }
    function row($id, $salable, $stock = 2) {
        return ['id'=>$id,'data'=>['variation'=>['id'=>$id],'filter'=>['isSalable'=>$salable],'stock'=>['net'=>$stock]]];
    }
    $owner = new class {
        public $saved = [], $removed = [], $locked = 0;
        function getItemWishList() { return $this->saved; }
        function removeItemWishListEntry($id) {
            if ($this->locked === $id) throw new RuntimeException('Persistence unavailable');
            $this->removed[] = $id;
            $this->saved = array_values(array_filter($this->saved, fn($entry) => (int)$entry !== $id));
            return true;
        }
    };
    $list = new class {
        public $reads = 0;
        function getItemList($type) { same('wish_list',$type); ++$this->reads; return $GLOBALS['result']; }
    };
    $request = new class {
        public $id = 11;
        function get($key, $default = null) { return $key === 'quantity' ? 3 : $this->id; }
        function create($data, $code) { return new Response($data, $code); }
    };
    $services = [\Plenty\Modules\Webshop\ItemSearch\Factories\VariationSearchFactory::class=>new \Plenty\Modules\Webshop\ItemSearch\Factories\VariationSearchFactory(),
        ItemWishListService::class=>$owner, ItemListService::class=>$list, WishListAvailability::class=>new WishListAvailability(),
        ItemSearchService::class=>new class {
            function getResult($factory) {
                if ($GLOBALS['result'] === null) throw new RuntimeException('Search unavailable');
                return $GLOBALS['result'];
            }
        }];
    $resource = new WishListResource($request,$request);
    $groups = $writes = [];
    $owner->saved = ['15',11,12,13,14,16];
    $result = ['documents'=>[row(11,1),row(12,false),row(15,true),row(16,true,0)],'total'=>4,'inactiveVariationIds'=>[13]];
    $reply = $resource->index();
    same(200,$reply->code);
    same(true,$reply->data['wishListPruned']);
    same([11,15],array_column($reply->data['documents'],'id'));
    same([],$reply->data['inactiveVariationIds']);
    same(['15',11],$owner->saved);
    same([13,12,16,14],$owner->removed);
    same(1,$list->reads);
    same([],$groups);
    same(201,$resource->store()->code);
    foreach ([12,13,16,-1] as $request->id) same(422,$resource->store()->code);
    $result = ['documents'=>[],'total'=>0];
    $request->id=14; same(422,$resource->store()->code);
    same([[11,3]],$writes);

    $request->id=11;
    foreach ([null,['documents'=>[],'error'=>'timeout','total'=>0],['documents'=>[],'total'=>3],
        ['documents'=>[],'total'=>0,'timed_out'=>true],['documents'=>[],'total'=>0,'_shards'=>['failed'=>1]],
        ['documents'=>[],'total'=>['value'=>0]],['documents'=>[],'total'=>0,'success'=>false],
        ['documents'=>[row(999,true)],'total'=>1],['documents'=>[row(11,true,null)],'total'=>1],
        ['documents'=>[row(11,true)],'total'=>1,'inactiveVariationIds'=>[11]],
        ['documents'=>[row(11,true),row(11,false)],'total'=>2]] as $result) {
        $owner->saved = [11]; $owner->removed = [];
        same(503,$resource->store()->code);
        $reply = $resource->index();
        same(false,isset($reply->data['wishListPruned']));
        same([11],$owner->saved); same([],$owner->removed);
    }
    $result = ['documents'=>[row(11,true,-1)],'total'=>1];
    $owner->locked=11;
    same(false,isset($resource->index()->data['wishListPruned']));
    same([11],$owner->saved);
    $owner->locked=0;
    same(true,$resource->index()->data['wishListPruned']);
    same([],$owner->saved);

    $picker = new WishListAvailability();
    $groups = [];
    $result = ['documents'=>[row(11,true),row(16,true,0)],'total'=>2];
    same([11=>true,16=>false,14=>false],$picker->forPicker([11,16,14,11]));
    same([16=>false,14=>false],$picker->forPicker([14,16]));
    same([[11,16,14]],$groups);
    $docs = array_map(fn($id) => row($id,true), range(21,86));
    $picker->forPicker(range(21,86), $docs);
    same([[11,16,14]],$groups); // 66 single-variation cards already contain all stock fields.
    $picker->forPicker([21,22,23,90,91], $docs);
    same([90,91], $groups[1]);
    $groups = [];
    $result = ['documents'=>[],'total'=>0];
    $picker->forPicker(range(101,305));
    same([100,100,5],array_map('count',$groups));
    same([[11,3]],$writes);
    echo "Wishlist: cached picker, fresh POST, persistent cleanup in one native list read, partial failures and retry passed.\n";
}
