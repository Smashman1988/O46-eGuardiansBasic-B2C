<?php
// Actual PDP/Header/PageDesign renders with explicit platform and navigation boundaries.
// php tests/pdp_render_contract.php <Twig autoload> <report.json> <before-dir>
require $argv[1];
$root = dirname(__DIR__);
require $root . '/src/Helper/SeoPage.php';
class GuardTwigExtensionStub {}
class_alias(GuardTwigExtensionStub::class, 'Plenty\\Plugin\\Templates\\Extensions\\Twig_Extension');
require $root . '/src/Extensions/Base_Extension.php';
$propertyExtension = (new ReflectionClass(\GuardiansBasic\Extensions\Base_Extension::class))->newInstanceWithoutConstructor();
$notices = [];
set_error_handler(function ($level,$message,$file,$line) use (&$notices) {
    if (in_array($level,[E_DEPRECATED,E_USER_DEPRECATED],true)) { $notices[$message] = [$file,$line]; return true; }
    throw new ErrorException($message,0,$level,$file,$line);
});
class GuardRequest { public $value; function __construct($v){$this->value=$v;} function get($key){return $key==='quickViewItem'?$this->value:null;} function getRequestUri(){return '/product/?quickViewItem='.(is_scalar($this->value)?(string)$this->value:'');} }
class GuardServices {
    public $calls=[],$lang,$request,$containers=[],$texts=[],$quantity=0,$sorts=0;
    public $wishListSnapshot = [], $wishListHit = false;
    public $singleVariation = false;
    function __construct($lang,$request){$this->lang=$lang;$this->request=$request;}
    function __call($name,$args){
        $this->calls[]=$name;
        switch($name){
            case 'setType': return null;
            case 'getVariationAttributeMap': return $this->singleVariation ? [] : [['variationId'=>1011,'attributes'=>[['attributeId'=>1,'attributeValueId'=>1]]],['variationId'=>1012,'attributes'=>[['attributeId'=>1,'attributeValueId'=>2]]]];
            case 'getBasketForTemplate': return ['itemQuantity'=>$this->quantity,'currency'=>'EUR'] + $this->wishListSnapshot;
            case 'getBasketQuantity': return $this->quantity;
            case 'getURLById': return '/'.$this->lang.'/category-'.$args[0].'/';
            case 'getItemCrossSellingList': return ['documents'=>[['data'=>['item'=>['id'=>99]]]],'contextProbe'=>'cross-'.$this->lang];
            case 'getAttributeNameMap': return ['attributes'=>[1=>['id'=>1,'name'=>'size-'.$this->lang,'values'=>[1=>'S',2=>'M']]],'units'=>[],'contextProbe'=>'attrs-'.$this->lang];
            case 'getLanguage': return $this->lang;
            case 'getCurrentTemplate': return 'tpl.item';
            case 'isItem': return true;
            case 'isItemInWishList': return $this->wishListHit;
            case 'isCheckout': case 'isCategory': case 'isSearch': case 'isMyAccount': case 'isCheapestSorting': return false;
            case 'shouldBeCached': return true;
            case 'getCanonicalURL': return '/'.($args[0]??$this->lang).'/product/a-42/';
            case 'getLanguageURLs': return array_combine(['de','en','fr','it','es'],array_map(function($l){return '/'.$l.'/product/a-42/';},['de','en','fr','it','es']));
            case 'getVariationIsSalable': return true;
            case 'getVariationURL': return new class { function getPath(){return '/product/a-42/';} };
            case 'getCategoryURL': return new class { function toAbsoluteUrl($v){return '/category/';} };
            case 'getCurrentCategory': return null;
            case 'getCurrencyData': return ['name'=>'EUR','symbol'=>'EUR'];
            case 'getCurrencyPattern': return '#,##0.00';
            case 'getShippingCountryId': return 1;
            case 'getActiveCountriesList': return [['id'=>1,'currLangName'=>'Germany','isoCode2'=>'DE']];
            case 'getPropertyFileUrl': return '/properties';
            case 'getOrderItemPrefix': return '';
            case 'getWebstoreConfig': return ['domainSsl'=>'https://store.example.test'];
        }
        throw new RuntimeException('Unexpected platform service '.$name);
    }
    function config($key){$this->calls[]='config:'.$key;return $key==='Ceres.currency.format'?'symbol':($key==='IO.routing.enabled_routes'?'home, category, item':false);}
    function partial($key){return ['page-design'=>'PageDesign','head'=>'Head','page-metadata'=>'Metadata','header'=>'Header','footer'=>'fixture-footer'][$key];}
    function trans($key,$params=[]){$this->texts[]=$key;return $this->lang.':'.$key;}
    function pluginPath($name){return '/plugins/'.$name;}
    function styles(){ $this->calls[]='additionalStyles';return [['path'=>'fixture-external-style','params'=>[]]]; }
    function scripts(){ $this->calls[]='additionalScripts';return [['path'=>'fixture-external-script','params'=>[]]]; }
    function component($name){$this->calls[]='component:'.$name;return '';}
    function noop(...$args){return '';}
    function container($name,$object=null){$this->containers[]=['name'=>$name,'object'=>$object];return '<!--container:'.$name.'-->';}
    function seo($template,$max=null,$page=null,$total=null){return new \GuardiansBasic\Helper\SeoPage([], $template,$max,$page,$total);}
    function maps($ids){if($ids)throw new RuntimeException('Unexpected recommendations query');return [];}
    function identity($v){return $v;}
    function sortImages($v){$this->sorts++;usort($v, fn($a,$b)=>$a['position']<=>$b['position']);return $v;}
    function integer($v){return (int)$v;}
    function itemName($v){return $v['texts']['name1']??'Product';}
    function itemURL($v){return '/product/a-42/';}
}
function repositoryTemplates($root){$result=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views')) as $file){if($file->getExtension()!=='twig')continue;$relative=substr(str_replace(DIRECTORY_SEPARATOR,'/',$file->getPathname()),strlen(str_replace(DIRECTORY_SEPARATOR,'/',$root.'/resources/views/')));$name='GuardiansBasic::'.str_replace('/','.',substr($relative,0,-5));$result[$name]=file_get_contents($file->getPathname());$result[$name.'.twig']=$result[$name];}return $result;}
function assertPriceUi($html,$full,$price,$rrp,$special) {
    $dom = new DOMDocument(); $dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING); $xpath=new DOMXPath($dom);
    $root='//article[@data-mainitemid="42"]';
    $q=function($attr)use($xpath,$root){return $xpath->query($root.'//*[@'.$attr.']');};
    $groups=$q('data-eg-item-discount'); $hasPrice=is_numeric($price)&&$price>=0;
    $sale=$hasPrice && is_numeric($rrp) && $rrp>$price;
    if ($groups->length!==($full?3:0)) throw new RuntimeException('Expected 3 PDP sale sections and no Quickview sale block');
    if (!$full) return;
    foreach ($groups as $el) if ((strpos(' '.$el->getAttribute('class').' ',' d-none ')===false)!==$sale) throw new RuntimeException('Invalid visible sale UI');
    $specialNodes=$q('data-eg-item-special');
    if($specialNodes->length!==($special?1:0) || ($special && (trim($specialNodes[0]->textContent)!==$special || (strpos($specialNodes[0]->getAttribute('class'),'d-none')!==false)!==$sale))) throw new RuntimeException('Special badge corrupted');
    foreach (['data-eg-item-price'=>$hasPrice?number_format($price,2,',','.'):'',
              'data-eg-item-rrp'=>$sale?number_format($rrp,2,',','.'):'',
              'data-eg-item-rrp-spar'=>$sale?number_format($rrp-$price,2,',','.'):'',
              'data-eg-item-rrp-percent'=>$sale?number_format((1-$price/$rrp)*-100,0,',','.'):''] as $attr=>$expected) {
        $nodes=$q($attr);
        if ($nodes->length!==1 || trim($nodes[0]->textContent)!==$expected) throw new RuntimeException('Unexpected '.$attr.' for '.json_encode([$price,$rrp]));
        if ($attr==='data-eg-item-price' && (strpos($nodes[0]->parentNode->getAttribute('class'),'d-none')===false)!==$hasPrice) throw new RuntimeException('Invalid default price visibility');
    }
}
$actual=[];
foreach(['PageDesign'=>'PageDesign/PageDesign','Head'=>'PageDesign/Partials/Head','Metadata'=>'PageDesign/Partials/PageMetadata'] as $key=>$relative)$actual[$key]=file_get_contents($root.'/resources/views/'.$relative.'.twig');
// Framework macros/components are boundaries; own rendered main templates remain actual source.
$stubs=[
 'Ceres::PageDesign.Macros.LayoutContainer'=>'{% macro show(name,object) %}{{ recordContainer(name,object)|raw }}{% endmacro %}',
 'Ceres::PageDesign.Macros.ItemName'=>'{% macro get(name,bundle) %}{{ name }}{% endmacro %}',
 'Ceres::PageDesign.Macros.IncludeComponents'=>'{% macro include_components() %}{% endmacro %}',
 'Ceres::PageDesign.Macros.Translations'=>'{% macro add(plugin,type) %}{% endmacro %}',
 'fixture-header'=>'<header>HEADER</header>', 'fixture-footer'=>'<footer>FOOTER</footer>',
 'fixture-external-style'=>'<style data-probe>{{ [crossSellingList,itemAttributes]|json_encode|raw }}</style>',
 'fixture-external-script'=>'<script data-probe>{{ [crossSellingList,itemAttributes]|json_encode|raw }}</script>'
];
foreach(['Ceres::Widgets.WidgetComponents','Ceres::Widgets.ShopBuilderDataStore','GuardiansBasic::PageDesign.Components.cashinadvance','GuardiansBasic::PageDesign.Components.cookiebar','GuardiansBasic::PageDesign.Components.ContactWidget','GuardiansBasic::PageDesign.Components.NewsletterPopup','GuardiansBasic::Containers.FeedbackContainer'] as $name)$stubs[$name]='<!--fixture '.$name.'-->';
foreach(['DE','EN','FR','IT','ES'] as $code)foreach(['nl-element','gutschein'] as $prefix)$stubs['GuardiansBasic::PageDesign.Components.'.$prefix.$code]='<!--fixture '.$prefix.$code.'-->';
foreach(['','DE','EN','FR','IT','ES'] as $language)$stubs['GuardiansBasic::Category.Macros.CategoryTree'.$language.'.twig']='{% macro get_nav_bar(categories,ignored) %}<li>NAVIGATION BOUNDARY</li>{% endmacro %}';
$source=file_get_contents($root.'/resources/views/Item/SingleItem.twig');
$headers=['after'=>file_get_contents($root.'/resources/views/PageDesign/Partials/Header/Header.twig')];
$version='after';
$catalog = repositoryTemplates($root);
$rows=[];
foreach (['de','en','fr','it','es'] as $lang) foreach ([null,'true'] as $value) foreach (['prepared','legacy'] as $format) {
    $case=['lang'=>$lang,'value'=>$value,'builder'=>false];
    $req=new GuardRequest($case['value']);$svc=new GuardServices($case['lang'],$req);$svc->quantity=$case['quantity']??0;
    if (array_key_exists('snapshot', $case)) {
        $svc->wishListSnapshot = ['itemWishListIds' => $case['snapshot']];
        $svc->wishListHit = $case['hit'];
    }
    $twig=new Twig\Environment(new Twig\Loader\ArrayLoader(['SingleItem'=>$source,'Header'=>$headers[$version]]+$actual+$stubs+$catalog),['cache'=>false,'autoescape'=>'html','strict_variables'=>false]);
    foreach(['getPartial'=>'partial','config'=>'config','trans'=>'trans','plugin_path'=>'pluginPath','createSeoPage'=>'seo','get_additional_styles'=>'styles','get_additional_scripts'=>'scripts','component'=>'component','recordContainer'=>'container','getCardAttributeMaps'=>'maps'] as $name=>$method)$twig->addFunction(new Twig\TwigFunction($name,[$svc,$method]));
    $twig->addFunction(new Twig\TwigFunction('getItemPropertiesById',[$propertyExtension,'getItemPropertiesById']));
    foreach(['get_consent_scripts','get_json_data','get_filtered_tags','get_shop_builder_scripts','ssr_entry','csrf_token','prepareSeoContent'] as $name)$twig->addFunction(new Twig\TwigFunction($name,[$svc,'noop']));
    foreach(['filterTabs','filterFields','specialOffer'] as $name)$twig->addFilter(new Twig\TwigFilter($name,[$svc,'identity']));
    $twig->addFilter(new Twig\TwigFilter('orderByKey',[$svc,'sortImages']));
    foreach(['int'=>'integer','itemName'=>'itemName','itemURL'=>'itemURL'] as $name=>$method)$twig->addFilter(new Twig\TwigFilter($name,[$svc,$method]));
    $item=['item'=>['id'=>42,'manufacturer'=>['name'=>'Fixture','externalName'=>'Fixture','logo'=>'','url'=>''],'feedbackCount'=>0,'feedbackDecimal'=>0], 'variation'=>['id'=>1011,'bundleType'=>'','availability'=>['id'=>1]],'images'=>['all'=>[['url'=>'/image.jpg','urlMiddle'=>'/image-middle.jpg','urlPreview'=>'/image-preview.jpg','position'=>0]]],'prices'=>['default'=>['unitPrice'=>['value'=>4.99,'formatted'=>'4.99 EUR'],'currency'=>'EUR'],'rrp'=>['price'=>['value'=>9.99],'unitPrice'=>['value'=>9.99,'formatted'=>'9.99 EUR']]],'texts'=>['name1'=>'Fixture '.$case['lang'],'metaDescription'=>'Description','keywords'=>'Fixture','description'=>'<p>Product</p>','shortDescription'=>'Short','technicalData'=>''],'attributes'=>[],'properties'=>[],'barcodes'=>[['code'=>'123']]];
    $item['variation']['id'] = $case['variation'] ?? 1011;
    for($image=1;$image<($case['images']??3);$image++)$item['images']['all'][]=['url'=>'/image-'.$image.'.jpg','urlMiddle'=>'/image-middle-'.$image.'.jpg','urlPreview'=>'/image-preview-'.$image.'.jpg','position'=>$image];
    $ctx=['request'=>$req,'item'=>['documents'=>[['data'=>$item]]],'services'=>array_fill_keys(['ItemCrossSellingService','item','url','basket','localizationRepository','wishList','template','checkout','propertyFile','order','country','webstoreConfig','category','itemList'],$svc),'isShopBuilder'=>$case['builder'],'category'=>null,'defaultCategory'=>['details'=>[['name'=>'Category']]],'buildHash'=>'fixture','webstoreConfig'=>['defaultLanguage'=>'de','domainSsl'=>'https://store.example.test','name'=>'Fixture','browserLanguage'=>[]],'ceresConfig'=>['global'=>['blockCookies'=>false],'log'=>['performanceLevel'=>'live','checkSyntax'=>false],'header'=>['companyLogo'=>'/logo.png']],'context'=>new class {function renderState(){return '';}},'urls'=>['templateType'=>'item','appendTrailingSlash'=>true,'search'=>'/search/'],'currencyData'=>['symbol'=>'EUR'],'locale'=>$case['lang'],'assetName'=>'ceres-base','showNetPrices'=>true,'notifications'=>[]];
    $default= $format==='legacy' ? ['data'=>['unitPrice'=>54]] : ['unitPrice'=>['value'=>54]];
    $rrp= $format==='legacy' ? ['data'=>['unitPrice'=>60]] : ['unitPrice'=>['value'=>60]];
    $ctx['item']['documents'][0]['data']['prices']=['default'=>$default,'rrp'=>$rrp];
    $ctx['item']['documents'][0]['data']['item']['storeSpecial']=[];
    $svc->singleVariation = $lang === 'de' || $lang === 'es';
    if ($svc->singleVariation && $format === 'legacy') {
        $ctx['item']['documents'][0]['data']['stock']['net'] = $lang === 'de' ? 2 : 7;
    }
    $html=$twig->render('SingleItem',$ctx);
    assertPriceUi($html,$value===null,54,60,'');
    $dom=new DOMDocument(); $dom->loadHTML($html,LIBXML_NOERROR|LIBXML_NOWARNING); $xp=new DOMXPath($dom);
    if ($svc->singleVariation) {
        $warning = trim($xp->evaluate('string(//article//p[contains(@class,"data-stock-warn")])'));
        $expected = $lang === 'de' && $format === 'legacy'
            ? 'de:GuardiansBasic::Template.bpss1 2 de:GuardiansBasic::Template.bpss2'
            : $lang.':GuardiansBasic::Template.bpss';
        if ($warning !== $expected) throw new RuntimeException('Unexpected single-variation availability message: '.$warning);
    }
    if ($xp->query('//article[@data-mainitemid="42"]//*[@data-item-tobasket]')->length!==1) throw new RuntimeException('B2C buy button missing');
    if ($xp->query('//*[@id="eg-wish-picker"]')->length!==($value===null?1:0)) throw new RuntimeException('Wishlist dialog scope changed');
    if ($value===null && $xp->query('//*[@data-eg-gallery-count="3"]')->length!==1) throw new RuntimeException('Gallery image count');
    if ($value===null && $xp->query('//article//div[contains(concat(" ",@class," ")," property-box ") and not(*) and normalize-space(.)=""]')->length) {
        throw new RuntimeException('Empty property section reserves space');
    }
    if ($value===null && $lang==='de' && $format==='prepared') file_put_contents(dirname($argv[2]).'/pdp.html',$html);
    $rows[]=[$lang,$value,$format];
}
file_put_contents($argv[2],json_encode($rows,JSON_THROW_ON_ERROR));
echo count($rows).' PDP and Quickview renders with prepared and legacy gross prices passed'.PHP_EOL;
