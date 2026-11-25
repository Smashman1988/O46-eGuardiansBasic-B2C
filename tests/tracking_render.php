<?php
// Render only synthetic data. No Plenty runtime or external service is used.
// Usage: php tracking_render.php <Twig autoloader> <output.json> [git comparison revision]
require $argv[1];
$root = dirname(__DIR__);
$reference = $argv[3] ?? null;
function readTrackingSource(string $file, ?string $revision): string {
    global $root;
    $file = 'resources/views/PageDesign/'.$file.'.twig';
    if ($revision === null) return file_get_contents($root.'/'.$file);
    $process = proc_open(['git','show',$revision.':'.$file],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    $text = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($error);
    return $text;
}
class TrackingFixture {
    public array $calls = [];
    public array $access = [];
    public $urlRef = '';
    public $orderParam = '';
    public $template = 'tpl.confirmation';
    public $brand = 'Marque & Fils ';
    function getVariation($id) {
        $this->calls[] = $id;
        return ['documents'=>[['data'=>['item'=>['manufacturer'=>['externalName'=>$this->brand.$id]]]]]];
    }
    function get($key) {
        $this->access[] = 'request.get:'.$key;
        return $key === 'ReferrerID' ? $this->urlRef : $this->orderParam;
    }
    function getCurrentTemplate() {
        $this->access[] = 'template.getCurrentTemplate';
        return $this->template;
    }
}
$views = [];
foreach (['AdtractionReferrerTracker','adtractionCOM','adtractionFR','adtractionIT','adtractionES','idealopixel','salenti','Trackings'] as $name) {
    $views['GuardiansBasic::PageDesign.Components.'.$name] = readTrackingSource('Components/'.$name,$reference);
}
$layout = readTrackingSource('PageDesign',$reference);
if (!preg_match('~(\{% if currentTemplate == "tpl.confirmation" %\}\s*\{% set trackingMapping.*?Components\.AdtractionReferrerTracker" %\})~s',$layout,$head)
    || !preg_match('~(\{% if orderId == "" and category.id in salenti %\}.*?\{% endif %\})~s',$layout,$offer)) {
    throw new RuntimeException('PageDesign tracking dispatch changed; review fixture boundaries.');
}
$views['head'] = $head[1];
$views['offer'] = $offer[1];
if (!preg_match('~\{% set salenti = \[([0-9]+)\] %\}~',$layout,$salentiCategory)) throw new RuntimeException('Review Salenti routing');
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader($views),['autoescape'=>'html']);
$svc = new TrackingFixture;
$amount = ['currency'=>'EUR','orderId'=>'T-42','grossTotal'=>65.45,'netTotal'=>56.46,'shippingCostsGross'=>4.95];
$names = ['Été & "Öl" + 50% / 帽子', "Crème d'été", 'Leinen'];
$orderLines = [];
foreach ([101,202,101] as $i=>$id) {
    $orderLines[] = ['typeId'=>$i===1?2:1,'itemVariationId'=>$id,'orderItemName'=>$names[$i],'quantity'=>$i===0?2:1,
        'amounts'=>[['priceGross'=>[19.95,15.60,10.00][$i],'priceNet'=>[16.76,14.58,8.40][$i]]],'variation'=>['number'=>'SKU-'.$id]];
}
$orderLines[] = ['typeId'=>6,'orderItemName'=>'Versand','amounts'=>[['priceGross'=>4.95,'priceNet'=>4.16]],'variation'=>['number'=>''],'quantity'=>1];
$coupon = ['typeId'=>4,'properties'=>[['typeId'=>18,'value'=>'SOMMER-5']],'orderItemName'=>'Gutschein',
    'amounts'=>[['priceGross'=>-5,'priceNet'=>-4.20]],'variation'=>['number'=>''],'quantity'=>1];
$order = ['id'=>42,'createdAt'=>'2026-11-25T12:00:00Z','referrerId'=>0,'amounts'=>[$amount],'orderItems'=>$orderLines];
$context = ['services'=>['item'=>$svc,'template'=>$svc],'request'=>$svc,'data'=>['order'=>$order],
    'currentTemplate'=>'tpl.confirmation','salenti'=>[(int)$salentiCategory[1]],'category'=>['id'=>(int)$salentiCategory[1]],'orderId'=>'','useGross'=>true];
$cases = [];
function renderCase($key,$view,$context) {
    global $cases,$svc,$twig;
    $svc->calls=[];
    $svc->access=[];
    $svc->template=$context['currentTemplate'];
    $html=$twig->render($view,$context);
    $cases[$key]=['html'=>$html,'calls'=>$svc->calls,'access'=>$svc->access];
}
foreach (['EUR','CHF','USD'] as $curr) {
    $ctx=$context;
    $ctx['data']['order']['referrerId']=121;
    $ctx['data']['order']['amounts'][0]['currency']=$curr;
    $ctx['data']['order']['orderItems'][]=$coupon;
    renderCase('idealo-'.$curr,'head',$ctx);
    $clean=$ctx;
    foreach (['Hemd blau','Cotton Shirt','Leinen'] as $i=>$name) {
        $clean['data']['order']['orderItems'][$i]['orderItemName']=$name;
    }
    $svc->brand='Marke ';
    renderCase('idealo-clean-'.$curr,'head',$clean);
    $svc->brand='Marque & Fils ';
}
$ctx['data']['order']['orderItems']=[$coupon];
$ctx['data']['order']['orderItems'][0]['amounts']=[['priceGross'=>0,'priceNet'=>0]];
$ctx['data']['order']['amounts'][0]=array_merge($amount,['grossTotal'=>0,'netTotal'=>0,'shippingCostsGross'=>0]);
renderCase('voucher-only','head',$ctx);
$ctx=$context;
$ctx['data']['order']['referrerId']=121;
$svc->urlRef='34';
renderCase('mixed-networks','head',$ctx);
foreach ([34,46,47,48] as $i=>$partner) {
    $referrers = ['url'=>(string)$partner, 'order'=>'', 'same'=>(string)$partner];
    foreach ([34,46,47,48] as $otherPartner) {
        if ($otherPartner !== $partner) $referrers['different'.$otherPartner]=(string)$otherPartner;
    }
    foreach ($referrers as $mode=>$urlRef) foreach ([false,true] as $discount) {
        $ctx=$context;
        $ctx['currentTemplate']=$mode==='url'?'tpl.home':'tpl.confirmation';
        $svc->urlRef=$urlRef;
        $ctx['data']['order']['referrerId']=$mode==='url'?0:$partner;
        $ctx['data']['order']['amounts'][0]['currency']=['EUR','CHF','USD','EUR'][$i];
        if($discount)$ctx['data']['order']['orderItems'][]=$coupon;
        else $ctx['data']['order']['amounts'][0]['grossTotal']+=5;
        renderCase('adtraction-'.$partner.'-'.$mode.'-'.(int)$discount,'head',$ctx);
    }
}
$svc->urlRef='';
foreach (['simple','unicode','absent','other-category','order-param'] as $mode) {
    $ctx=$context;
    if($mode!=='absent')$ctx['data']['order']['billingAddress']=['name2'=>$mode==='unicode'?"Zoë & D'Art\n試":'Anna',
        'name3'=>$mode==='unicode'?'Muster "Été"':'Muster','address1'=>'Testweg','address2'=>'7','postalCode'=>'00123','town'=>'Köln'];
    if($mode==='other-category')$ctx['category']['id']=1;
    if($mode==='order-param')$ctx['orderId']='fixture';
    renderCase('salenti-'.$mode,'offer',$ctx);
}
foreach (['EUR','CHF','USD'] as $curr) foreach ([true,false] as $gross) {
    $ctx=$context;
    $ctx['data']['order']['referrerId']=19;
    $ctx['data']['order']['orderItems'][]=$coupon;
    $ctx['data']['order']['amounts'][0]['currency']=$curr;
    $ctx['useGross']=$gross;
    renderCase('legacy-'.$curr.'-'.(int)$gross,'GuardiansBasic::PageDesign.Components.Trackings',$ctx);
}
foreach (['outside','referrer','order-param'] as $mode) {
    $ctx=$context;
    $ctx['currentTemplate']=$mode==='outside'?'tpl.home':'tpl.confirmation';
    $ctx['data']['order']['referrerId']=$mode==='referrer'?121:19;
    $svc->orderParam=$mode==='order-param'?'fixture':'';
    renderCase('legacy-'.$mode,'GuardiansBasic::PageDesign.Components.Trackings',$ctx);
}
file_put_contents($argv[2],json_encode(['twig'=>Twig\Environment::VERSION,'cases'=>$cases],JSON_THROW_ON_ERROR));
echo count($cases)." synthetic tracking renders\n";
