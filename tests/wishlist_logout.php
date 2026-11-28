<?php
// Public test inventory name retained for the native logout / customer isolation regression.
namespace Plenty\Plugin\Http { class Response { public function __construct(public $data,public $code) {} function status(){return $this->code;} } }
namespace Plenty\Plugin { class Application { public function getPlentyId() {return $GLOBALS['plenty'];} } }
namespace Plenty\Modules\Webshop\Contracts {
    class SessionStorageRepositoryContract {
        const GUEST_WISHLIST='guest', GUEST_WISHLIST_MIGRATION='migrate';
        public $values=[],$writes=0;
        function getSessionValue($key){return $this->values[$key]??null;}
        function setSessionValue($key,$value){$this->writes++;$this->values[$key]=$value;}
    }
}
namespace IO\Helper { class Utils {static function getPlentyId(){return $GLOBALS['plenty'];} static function getWebstoreId(){return 0;}} }
namespace IO\Api { class ResponseCode {const OK=200;} }
namespace Plenty\Plugin\Log { trait Loggable { function getLogger($name){return new class{function warning($message){}};} } }
namespace IO\Api\Resources {
    class CustomerAuthenticationResource {public function store(): \Plenty\Plugin\Http\Response {global $contact,$loginResponse,$session;if($loginResponse===200){$contact=$GLOBALS['nextContact']??31;\pluginApp(\GuardiansBasic\Services\WishListMigration::class)->beforeNativeMigration($contact);$session->setSessionValue('migrate',true);if(!empty($GLOBALS['eagerLogin']))new \IO\Services\ItemWishListService($session);}return new \Plenty\Plugin\Http\Response(null,$loginResponse);} }
    class ItemWishListResource {
        function __construct(protected $request,protected $response){}
    }
    class SessionResource {
        function __construct(protected $response){}
        protected function indexBasket(){return ['sessionId'=>'session-two','itemWishListIds'=>$GLOBALS['activeIds'],'itemQuantity'=>4];}
        function index(){return $this->response->create(['basket'=>$this->indexBasket()],200);}
    }
}
namespace IO\DBModels {
    class ItemWishList implements \JsonSerializable {
        public $variationId,$contactId,$plentyId,$quantity,$createdAt;
        function jsonSerialize():array{return get_object_vars($this);}
    }
}
namespace {
    use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract as Session;
    use GuardiansBasic\Resources\WishListResource;
    use GuardiansBasic\Resources\WishListSessionResource;
    function check($ok,$message){if(!$ok)throw new \RuntimeException($message);}
    function pluginApp($name){
        if($name===\IO\Services\ItemWishListService::class)return $GLOBALS['requestWishService'] ?? new $name($GLOBALS['session']);
        return $name===\IO\DBModels\ItemWishList::class?new $name():$GLOBALS['services'][$name];}
    $root=dirname(__DIR__);require $root.'/src/Resources/WishListLoginResource.php';require $root.'/src/Services/WishListMigration.php';
    $io=$argv[1]??getenv('O46_IO_ROOT');
    require $root.'/src/Resources/WishListResource.php';require $root.'/src/Resources/WishListSessionResource.php';
    $routes=file_get_contents($root.'/src/Providers/GuardiansBasicRouteServiceProvider.php');
    check(!str_contains($routes,'io/customer/logout') && !is_file($root.'/src/Resources/WishListLogoutResource.php'),'Logout must use IO; browser recovery must use the verified guest route');
    check(str_contains($routes,"'io/session', 'WishListSessionResource@index'") && str_contains($routes,"'io/basket', 'WishListSessionResource@basket'"),'Both existing central reads must identify the server-verified owner');
    check(str_contains($routes,"'io/customer/login', 'WishListLoginResource@store'"),'Confirmed native login hook');
    foreach (['basket' => 'WishListSessionResource@basket', 'session' => 'WishListSessionResource@index',
              'login' => 'WishListLoginResource@store', 'items' => 'WishListResource'] as $path => $handler) {
        check(str_contains($routes, "'guardiansbasic/wishlist/".$path."', '".$handler."'"), 'Theme-owned private route '.$path);
    }
    $provider=file_get_contents($root.'/src/Providers/GuardiansBasicServiceProvider.php');
    check(str_contains($provider,'AfterAccountAuthentication::class') && str_contains($provider,'$event->isSuccessful()') &&
        str_contains($provider,'->beforeNativeMigration('),'Every successful native authentication filters guest-only rows');
    $plenty=17;$contact=0;$activeIds=[11];$session=new Session();
    $account=new class {
        public $rows=[31=>[13],42=>[42]],$fail=0,$silent=0;
        function getContactId(){return $GLOBALS['contact'];}
        public $switchAfterRead = 0;
        function getItemWishList(){
            $result = $this->rows[$this->getContactId()] ?? [];
            if ($this->switchAfterRead) {$GLOBALS['contact']=$this->switchAfterRead;$this->switchAfterRead=0;}
            return $result;
        }
        function removeItemWishListEntry($id){
            if($this->silent===$id)return false;
            $c=$this->getContactId();$this->rows[$c]=array_values(array_diff($this->rows[$c],[$id]));return true;
        }
        function addItemWishListEntry($id){
            $c=$this->getContactId();check($c>0,'customer repository called as guest');
            if($id===$this->fail)throw new \RuntimeException('rejected IO write');
            if($id!==$this->silent && !in_array($id,$this->rows[$c],true))$this->rows[$c][]=$id;
        }
    };
    $guest=new class($session) {function __construct(public $s){} function getItemWishListForAllPlentyIds(){return json_decode($this->s->getSessionValue(Session::GUEST_WISHLIST)??'[]',true);}};
    $api=new class {public $args=[],$headers=[]; function header($key,$value){$this->headers[$key]=$value;} function get($k){return $this->args[$k]??null;}function create($data,$code){return new \Plenty\Plugin\Http\Response($data,$code);} };
    $stock=new class {public $unknown=false,$groups=[],$switchContact=0;function lookup($ids){
        if($this->switchContact)$GLOBALS['contact']=$this->switchContact;
        $this->groups[]=count($ids);return $this->unknown?[]:array_combine($ids,array_map(fn($id)=>$id!==999,$ids));}};
    $services=[\GuardiansBasic\Services\WishListMigration::class=>new \GuardiansBasic\Services\WishListMigration(),
        \IO\Services\BasketService::class=>new class{function getBasket(){return (object)['sessionId'=>'session-two'];}},
        \IO\Repositories\ItemWishListGuestRepository::class=>$guest,\IO\Repositories\ItemWishListRepository::class=>$account,
        \Plenty\Modules\Webshop\Contracts\ContactRepositoryContract::class=>$account,Session::class=>$session,
        \Plenty\Modules\Webshop\Contracts\WebstoreConfigurationRepositoryContract::class=>new class{function getWebstoreConfiguration(){return (object)['sessionLifetime'=>0];}},
        GuardiansBasic\Services\WishListAvailability::class=>$stock];
    $res=new WishListResource($api,$api);$central=new WishListSessionResource($api);
    $api->args=['session'=>'session-two','shop'=>0,'plenty'=>17,'ids'=>[11,11,999]];
    foreach([31,42] as $contact){
        check($res->restoreGuest()->code===409 && $guest->getItemWishListForAllPlentyIds()===[],'Recovery wrote into a customer session');
        check($central->basket()->data['wishListOwner']['guest']===false,'Customer mislabeled as guest');
    }
    $contact=42;$api->args['wishScope']=WishListSessionResource::scope('session-two',31);
    check($res->store()->code===409 && $res->destroy('11')->code===409,'Late writes from A must be refused in B');
    unset($api->args['wishScope']);
    $contact=0;$api->args['session']='expired';check($res->restoreGuest()->code===409,'Old guest session');$api->args['session']='session-two';$api->args['plenty']=18;check($res->restoreGuest()->code===409,'Wrong tenant');$api->args['plenty']=17;
    $api->args['shop']=8;check($res->restoreGuest()->code===409,'Wrong shop');$api->args['shop']=0;
    $api->args['ids']=['11'];check($res->restoreGuest()->code===422,'Malformed IDs');$api->args['ids']=[11,11,999];
    $stock->unknown=true;check($res->restoreGuest()->code===503 && $guest->getItemWishListForAllPlentyIds()===[],'Unconfirmed stock');$stock->unknown=false;
    $stock->switchContact=42;
    check($res->restoreGuest()->code===409 && $guest->getItemWishListForAllPlentyIds()===[],'Owner changed during availability lookup');
    $stock->switchContact=0;$contact=0;
    check($res->restoreGuest()->data===['available'=>[11]],'Guest recovery');
    $res->restoreGuest();check($guest->getItemWishListForAllPlentyIds()[17][11]['quantity']===1,'Idempotent recovery');
    check($central->index()->data['basket']['wishListOwner']===['guest'=>true,'shop'=>0,'plenty'=>17,'scope'=>WishListSessionResource::scope('session-two',0)],'Native session response metadata');
    check($api->headers['Cache-Control']==='private, no-store','Personal central responses must remain private');
    $original=$session->values;
    $api->args['ids']=range(20000,24999);$before=$session->writes;$stock->groups=[];
    check($res->restoreGuest()->data===['available'=>$api->args['ids']],'Large guest restoration lost variants');
    check($stock->groups===array_fill(0,50,100) && $session->writes===$before+1,'Large restoration must batch stock checks and write the IO session once');
    $session->values=$original;
    if($io){
        check(is_file($io.'/src/Services/ItemWishListService.php'),'Configured IO source missing');
        require $io.'/src/Repositories/ItemWishListGuestRepository.php';require $io.'/src/Services/ItemWishListService.php';
        $native=new \IO\Repositories\ItemWishListGuestRepository($session,new \Plenty\Plugin\Application());
        $services[\IO\Repositories\ItemWishListGuestRepository::class]=$native;
        $migration=$services[\GuardiansBasic\Services\WishListMigration::class];
        $owner=fn()=>new \IO\Services\ItemWishListService($session);
        $login=new \GuardiansBasic\Resources\WishListLoginResource();
        $native->addItemWishListEntry(12);$loginResponse=401;
        check($login->store()->status()===401 && $native->getItemWishList()===[11,12] && $contact===0,'Rejected login changed guest favorites');
        $account->fail=12;$loginResponse=200;
        check($login->store()->status()===200 && $contact===31 && $native->getItemWishList()===[11,12],'Transient migration failure did not retain guest list');
        $account->fail=0;$account->silent=12;
        try{$migration->confirm();throw new \LogicException('Missing confirmation');}
        catch(\Exception $e){if($e instanceof \LogicException)throw $e;}
        check($native->getItemWishList()===[12] && $session->getSessionValue(Session::GUEST_WISHLIST_MIGRATION),'Retry unconfirmed entry only');
        check($central->basket()->status()===200 && $session->getSessionValue(Session::GUEST_WISHLIST_MIGRATION),'Basket remains usable during a pending migration');
        $account->silent=0;$migration->confirm();$a=$owner();
        check($a->getItemWishList()===[13,11,12] && $native->getItemWishList()===[],'Confirmed native guest migration');
        $a->addItemWishListEntry(14,1);$contact=0;check($owner()->getItemWishList()===[],'A to guest leakage');
        // Native ApiResponse::create may construct BasketService and migrate before login returns.
        $native->addItemWishListEntry(15);$eagerLogin=true;$account->silent=15;
        check($login->store()->status()===200 && $native->getItemWishList()===[15],'Early native migration lost an unconfirmed ID');
        $account->silent=0;$migration->confirm();$contact=0;$eagerLogin=false;
        $api->args['ids']=[11,12];$api->args['imported']=[13];
        check($res->restoreGuest()->code===422,'Imported metadata must be confined to original guest rows');
        $api->args['imported']=[11,12];
        check($res->restoreGuest()->data['available']===[11,12] && $owner()->getItemWishList()===[11,12],'Original guest recovered after account A');
        check($central->basket()->data['wishListOwner']['imported']===[11,12],'Guest migration history survives clearing browser storage');
        $api->args['ids']=[14];$api->args['imported']=[];$api->args['customerIds']=[15];
        check($res->restoreGuest()->code===422,'Customer provenance outside the requested batch');
        $api->args['customerIds']=[14];
        check($res->restoreGuest()->data['available']===[14],'Customer-only favorite visible after logout');
        check($central->basket()->data['wishListOwner']['customerIds']===[14],'Provenance survives local storage loss');
        $api->args['variationId']=14;$api->args['quantity']=2;
        check($res->store()->code===201 && $native->getItemWishListForAllPlentyIds()[17][14]['o46GuestOnly']==='customer','Duplicate native add must preserve migration protection');
        check($native->getItemWishListForAllPlentyIds()[17][14]['quantity']===3,'Preserve native quantities on repeated adds');
        $eagerLogin=true;
        $loginResponse=401;
        check($login->store()->status()===401 && $owner()->getItemWishList()===[11,12,14],'Failed login lost restored guest rows');
        $loginResponse=200;
        $native->addItemWishListEntry(21);$nextContact=42;check($login->store()->status()===200,'Login of customer B');
        $migration->confirm();
        $bb=$owner();check($bb->getItemWishList()===[42,21],'A to B leakage');$bb->addItemWishListEntry(22,1);
        for($i=0;$i<3;$i++)foreach([31=>[13,11,12,14,15],42=>[42,21,22]] as $contactId=>$expected){
            $contact=0;check($owner()->getItemWishList()===[],'Private list entered guest session');
            $contact=$contactId;$session->setSessionValue(Session::GUEST_WISHLIST_MIGRATION,true);check($owner()->getItemWishList()===$expected,'Repeated login mixed accounts');
        }
        $aOwner=WishListSessionResource::owner('session-two',31);
        $api->args=['ids'=>[14,15],'wishScope'=>$aOwner['scope'],'account'=>$aOwner['account']];
        foreach([0,42] as $contact)check($res->reconcileRemovals()->code===409,'Wrong owner applied pending removals');
        $contact=31;$api->args['wishScope']='expired';
        check($res->reconcileRemovals()->code===409,'Expired scope applied pending removals');
        $api->args['wishScope']=$aOwner['scope'];$api->args['ids']=[14,'15'];
        check($res->reconcileRemovals()->code===422 && in_array(14,$owner()->getItemWishList(),true),'Malformed batch partially modified account');
        $api->args['ids']=range(1,101);
        check($res->reconcileRemovals()->code===422,'Oversized removal batch');
        $api->args['ids']=[14];$account->rows[42][]=14;$account->switchAfterRead=42;
        check($res->reconcileRemovals()->code===409 && in_array(14,$account->rows[42],true),'Account switched after list lookup');
        $account->rows[42]=array_values(array_diff($account->rows[42],[14]));$contact=31;
        $api->args['ids']=[14,15,123];$account->silent=15;
        check($res->reconcileRemovals()->data===['removed'=>[14,123]],'Only confirmed or already absent IDs may be acknowledged');
        $account->silent=0;check($res->reconcileRemovals()->data===['removed'=>[14,15,123]],'Idempotent retry');
        check($owner()->getItemWishList()===[13,11,12] && $account->rows[42]===[42,21,22],'Scoped replay touched a different account');
        echo "Native IO migration: two accounts, duplicate, exception, silent write failure, retry and repeated logout/login passed.\n";

        // IO registers ItemWishListService as a request singleton. A login can
        // reuse a service that the basket already constructed for the guest.
        $contact = 0;
        $eagerLogin = false;
        $requestWishService = $owner();
        $native->addItemWishListEntry(711);
        $api->args = ['shop'=>0, 'plenty'=>17, 'session'=>'session-two', 'ids'=>[11], 'customerIds'=>[11]];
        check($res->restoreGuest()->code === 200, 'Prepare a protected customer copy alongside the new guest entry');
        $nextContact = 42;
        check($login->store()->status() === 200, 'Login after adding a guest item');
        check(in_array(711, $account->rows[42], true), 'Confirmed guest addition missing from the account after login');
        check(!in_array(11, $account->rows[42], true), 'A protected customer copy was imported');
        check($native->getItemWishList() === [] && !$session->getSessionValue(Session::GUEST_WISHLIST_MIGRATION), 'Completed guest transfer was left queued');
        unset($requestWishService);
        $contact = 31;
        echo "Confirmed guest addition visible in the account with a pre-existing IO service; protected copy excluded.\n";

        // Independent IO sessions share this customer's persistent repository. The
        // guest device observed the old entries before the other device edited them.
        $originalDevice = $owner();
        $otherDevice = new \IO\Services\ItemWishListService(new Session());
        foreach ([701, 702] as $id) {
            $originalDevice->addItemWishListEntry($id, 1);
        }
        $otherDevice->removeItemWishListEntry(702);
        $otherDevice->addItemWishListEntry(703, 1);
        $api->args = ['ids' => [701], 'wishScope' => $aOwner['scope'], 'account' => $aOwner['account']];
        check($res->reconcileRemovals()->code === 200, 'Replay after changes on another device');
        $actual = $originalDevice->getItemWishList();
        check(!in_array(701, $actual, true), 'Original entry still present after confirmed removal');
        check(!in_array(702, $actual, true) && in_array(703, $actual, true), 'Unrelated device changes were lost');
        if (in_array('--check-concurrent-resave', $argv, true)) {
            $originalDevice->addItemWishListEntry(704, 1);
            $otherDevice->removeItemWishListEntry(704);
            $otherDevice->addItemWishListEntry(704, 1);
            $api->args['ids'] = [704];
            check($res->reconcileRemovals()->code === 200, 'Replay of the older removal');
            check(in_array(704, $originalDevice->getItemWishList(), true), 'An older guest removal erased the entry explicitly saved again on the other device');
        }
    }
    echo "Native logout routing, private owner metadata, guest recovery, unavailable stock, shop and tenant isolation passed.\n";
}

