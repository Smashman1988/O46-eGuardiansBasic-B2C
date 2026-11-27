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
class GuardRequest { public $value, $uri; function __construct($v){$this->value=$v;} function get($key){return $key==='quickViewItem'?$this->value:null;} function getRequestUri(){return $this->uri ?? '/product/?quickViewItem='.(is_scalar($this->value)?(string)$this->value:'');} }
class GuardServices {
    public $calls=[],$lang,$request,$containers=[],$texts=[],$quantity=0,$sorts=0;
    public $wishListSnapshot = [], $wishListHit = false;
    public $singleVariation = false;
    public $blockedVariation = true;
    function salabilityMap($ids) { return array_fill_keys($ids, !$this->blockedVariation); }
    public $linkedVariations;
    function __construct($lang,$request){$this->lang=$lang;$this->request=$request;}
    function feedbackList() {
        $this->calls[] = 'feedbackList';
        return ['entries'=>[], 'totalsCount'=>3];
    }
    function feedbackAverage() {
        $this->calls[] = 'feedbackAverage';
        return ['ratingsCountTotal'=>3, 'averageValue'=>4, 'ratingsCountOf4'=>3];
    }
    function __call($name,$args){
        $this->calls[]=$name;
        switch($name){
            case 'setType': return null;
            case 'getVariationAttributeMap': return $this->linkedVariations ?? ($this->singleVariation ? [] : [['variationId'=>1011,'attributes'=>[['attributeId'=>1,'attributeValueId'=>1]]],['variationId'=>1012,'attributes'=>[['attributeId'=>1,'attributeValueId'=>2]]]]);
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
            case 'getVariationIsSalable': return $this->blockedVariation;
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
    function languageRoutes($template,$category,$itemId,$variationId) {
        if ($template !== 'tpl.item' || !$itemId || !$variationId) return [];
        $routes = [];
        foreach (['de','en','fr','it','es'] as $code) {
            $routes[$code] = 'https://store.example.test/'.($code === 'de' ? '' : $code.'/').$itemId.'_'.$variationId.'/';
        }
        return $routes;
    }
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
foreach(['Ceres::Widgets.WidgetComponents','Ceres::Widgets.ShopBuilderDataStore','GuardiansBasic::PageDesign.Components.cashinadvance','GuardiansBasic::PageDesign.Components.cookiebar','GuardiansBasic::PageDesign.Components.ContactWidget','GuardiansBasic::PageDesign.Components.NewsletterPopup'] as $name)$stubs[$name]='<!--fixture '.$name.'-->';
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
    foreach(['getPartial'=>'partial','config'=>'config','trans'=>'trans','plugin_path'=>'pluginPath','getStorefrontLanguageRoutes'=>'languageRoutes','createSeoPage'=>'seo','get_additional_styles'=>'styles','get_additional_scripts'=>'scripts','component'=>'component','recordContainer'=>'container','getCardAttributeMaps'=>'maps'] as $name=>$method)$twig->addFunction(new Twig\TwigFunction($name,[$svc,$method]));
    $twig->addFunction(new Twig\TwigFunction('getFeedbackItemList',[$svc,'feedbackList']));
    $twig->addFunction(new Twig\TwigFunction('getListFeedbackAverages',[$svc,'feedbackAverage']));
    $twig->addFunction(new Twig\TwigFunction('getItemPropertiesById',[$propertyExtension,'getItemPropertiesById']));
    $twig->addFunction(new Twig\TwigFunction('getWishChoices',[$propertyExtension,'getWishChoices']));
    $twig->addFunction(new Twig\TwigFunction('getVariationSalabilityMap',[$svc,'salabilityMap']));
    $twig->addFunction(new Twig\TwigFunction('getSalableAttributeValues',[$propertyExtension,'getSalableAttributeValues']));
    foreach(['get_consent_scripts','get_json_data','get_filtered_tags','get_shop_builder_scripts','ssr_entry','csrf_token','prepareSeoContent'] as $name)$twig->addFunction(new Twig\TwigFunction($name,[$svc,'noop']));
    foreach(['filterTabs','filterFields','specialOffer'] as $name)$twig->addFilter(new Twig\TwigFilter($name,[$svc,'identity']));
    $twig->addFilter(new Twig\TwigFilter('orderByKey',[$svc,'sortImages']));
    foreach(['int'=>'integer','itemName'=>'itemName','itemURL'=>'itemURL'] as $name=>$method)$twig->addFilter(new Twig\TwigFilter($name,[$svc,$method]));
    $item=['item'=>['id'=>42,'manufacturer'=>['name'=>'Fixture','externalName'=>'Fixture','logo'=>'','url'=>''],'feedbackCount'=>0,'feedbackDecimal'=>0], 'variation'=>['id'=>1011,'bundleType'=>'','availability'=>['id'=>1]],'images'=>['all'=>[['url'=>'/image.jpg','urlMiddle'=>'/image-middle.jpg','urlPreview'=>'/image-preview.jpg','position'=>0]]],'prices'=>['default'=>['unitPrice'=>['value'=>4.99,'formatted'=>'4.99 EUR'],'currency'=>'EUR'],'rrp'=>['price'=>['value'=>9.99],'unitPrice'=>['value'=>9.99,'formatted'=>'9.99 EUR']]],'texts'=>['name1'=>'Fixture '.$case['lang'],'metaDescription'=>'Description','keywords'=>'Fixture','description'=>'<p>Product</p>','shortDescription'=>'Short','technicalData'=>''],'attributes'=>[],'properties'=>[],'barcodes'=>[['code'=>'123']]];
    $item['filter']['isSalable'] = true;
    $svc->blockedVariation = false;
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
    if ($value !== null && array_intersect(['getBasketForTemplate', 'isItemInWishList', 'getCanonicalURL'], $svc->calls)) {
        throw new RuntimeException('Quickview loaded full-page basket, wishlist or canonical data');
    }
    if ($format === 'prepared') {
        $svc->wishListSnapshot = ['itemWishListIds'=>[1012,987654321]];
        $svc->wishListHit = true;
        $private = $twig->render('SingleItem',$ctx);
        if (str_contains($private,'987654321') || str_contains($private,'data-saved=') ||
            preg_match('/class="[^"]*\bitem-in-wishlist\b[^"]*"/',$private) || in_array('isItemInWishList',$svc->calls,true)) {
            throw new RuntimeException('Wishlist data escaped into cacheable product output');
        }
        preg_match('/data-wish-product="[^"]*"/',$html,$guestControl);
        preg_match('/data-wish-product="[^"]*"/',$private,$customerControl);
        if (!$guestControl || $customerControl !== $guestControl) throw new RuntimeException('Session-dependent heart markup');
    }
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
    if ($value !== null && $format === 'prepared' && $lang === 'fr') {
        file_put_contents(dirname($argv[2]).'/quickview-multi.html', $html);
    }
    if ($value===null && $format==='prepared' && in_array($lang,['de','fr'],true)) {
        file_put_contents(dirname($argv[2]).($lang==='de' ? '/pdp.html' : '/pdp-multi.html'),$html);
    }
    if ($lang === 'de' && $format === 'prepared') {
        foreach ([0, 3, null] as $feedbackCount) {
            $ctx['item']['documents'][0]['data']['item']['feedbackCount'] = $feedbackCount;
            $svc->calls = [];
            $feedbackHtml = $twig->render('SingleItem', $ctx);
            $expected = $value === null && $feedbackCount !== 0 ? 1 : 0;
            $calls = array_count_values($svc->calls);
            if (($calls['feedbackList'] ?? 0) !== $expected || ($calls['feedbackAverage'] ?? 0) !== $expected
                || substr_count($feedbackHtml, 'id="quickrating"') !== $expected
                || substr_count($feedbackHtml, '<section class="feedback-container">') !== $expected) {
                throw new RuntimeException('Unexpected feedback markup or service count: '.json_encode([$value, $feedbackCount, $calls]));
            }
        }
    }
    if ($format === 'prepared' && $value === null) {
        $svc->singleVariation = false;
        $ctx['attributes'] = [['attributeId'=>1,'name'=>'Size','values'=>[
            ['attributeValueId'=>1,'name'=>'S'], ['attributeValueId'=>2,'name'=>'M']]]];
        $colour = ['de'=>'Grau','en'=>'Grey','fr'=>'Gris','it'=>'Grigio','es'=>'Gris'][$lang];
        $ctx['attributes'][] = ['attributeId'=>2,'name'=>'Colour','values'=>[['attributeValueId'=>3,'name'=>$colour]]];
        $svc->linkedVariations = $svc->getVariationAttributeMap(42);
        foreach ($svc->linkedVariations as &$variation) {
            $variation['attributes'][] = ['attributeId'=>2,'attributeValueId'=>3];
        }
        unset($variation);
        $ctx['item']['documents'][0]['data']['variation']['id'] = 1012;
        foreach ([['/42_1012/',false,1,true],['/product/a-42/',false,1,false],
            ['/42_1011/',false,1,false],['/42_1012/',true,1,false],['/42_1012/',false,5,false]] as [$path,$blocked,$availability,$enabled]) {
            $req->uri = '/'.$lang.$path.'?currency=USD';
            $svc->blockedVariation = $blocked;
            $ctx['item']['documents'][0]['data']['variation']['availability']['id'] = $availability;
            $result = new DOMDocument();
            $linkedHtml = $twig->render('SingleItem',$ctx);
            $result->loadHTML($linkedHtml,LIBXML_NOERROR|LIBXML_NOWARNING);
            if (in_array($lang,['de','en'],true) && $enabled) {
                file_put_contents(dirname($argv[2]).'/pdp-linked-'.$lang.'.html',$linkedHtml);
            }
            $selectors = new DOMXPath($result);
            $buy = $selectors->query('//article//button[@data-item-tobasket]')->item(0);
            $marked = $selectors->query('//article//*[@data-eg-value="2" and @data-selected]')->length;
            $label = $selectors->query('//article//*[@data-eg-selected-attribute and normalize-space(.)="M"]')->length;
            $colourMarked = $selectors->query('//article//*[@data-eg-value="3" and @data-selected]')->length;
            $colourLabel = $selectors->query('//article//*[@data-eg-selected-attribute and normalize-space(.)="'.$colour.'"]')->length;
            if ((bool)$colourMarked !== (bool)$marked || (bool)$colourLabel !== (bool)$marked
                || !$buy || (strpos($buy->getAttribute('class'),'inBasketBtnDisabled') === false) !== $enabled
                || (bool)$marked !== ($enabled || $availability === 5) || (bool)$label !== (bool)$marked) {
                throw new RuntimeException('Incorrect linked selection or availability in '.$lang.' '.$path.' blocked='.(int)$blocked.' availability='.$availability);
            }
        }
    }
    if ($value === null) {
        // Validate the same Product block the full page emits, with two distinct variations.
        $schemaContext = $ctx;
        $data =& $schemaContext['item']['documents'][0]['data'];
        $measured = $format === 'prepared';
        $data['variation']['id'] = $measured ? 1011 : 1012;
        $data['variation']['number'] = 'SKU-'.$data['variation']['id'];
        $data['variation']['lengthMM'] = $measured ? 120 : 0;
        $data['variation']['widthMM'] = $measured ? 32 : -1;
        $data['variation']['heightMM'] = null;
        $data['variation']['weightG'] = $measured ? 85 : 0;
        $availability = $measured ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
        $data['variation']['availability']['mappedAvailability'] = $availability;
        $currency = $measured ? 'CHF' : 'USD';
        $data['prices']['default'] = ['currency'=>$currency, 'unitPrice'=>['value'=>$measured ? 19.95 : 24],
            'baseSinglePrice'=>2, 'baseLot'=>1, 'baseUnit'=>'KGM'];
        $data['barcodes'] = [['type'=>'GTIN_13','code'=>'4006381333931']];
        $data['item']['feedbackCount'] = $measured ? 8 : 0;
        $data['item']['feedbackDecimal'] = 4.5;
        $invalidExpiry = ['de'=>'2097-02-29','en'=>'2001-01-01','fr'=>null,'it'=>'','es'=>'2097-99-10'];
        $schemaContext['priceValidUntil'] = $measured ? '2096-02-29' : $invalidExpiry[$lang];
        $schemaContext['manufacturer'] = 'Fixture Manufacturer';
        $schemaContext['brand'] = 'Fixture Brand';
        $schemaContext['conditionOfItem'] = 'https://schema.org/NewCondition';
        $document = new DOMDocument();
        $document->loadHTML($twig->render('SingleItem',$schemaContext),LIBXML_NOERROR|LIBXML_NOWARNING);
        $products = [];
        foreach ((new DOMXPath($document))->query('//script[@type="application/ld+json"]') as $script) {
            $object = json_decode($script->textContent,true,512,JSON_THROW_ON_ERROR);
            if (($object['@type'] ?? '') === 'Product') $products[] = $object;
        }
        if (count($products) !== 1) throw new RuntimeException('Expected one Product');
        $product = $products[0];
        $offer = $product['offers'];
        if ($offer['priceCurrency'] !== $currency || $offer['price'] !== ($measured ? '19.95' : '24.00')
            || $offer['availability'] !== $availability || ($offer['priceValidUntil'] ?? null) !== ($measured ? '2096-02-29' : null)
            || isset($offer['priceSpecification']['priceValidUntil']) || isset($offer['serialNumber'])
            || $offer['itemCondition'] !== $schemaContext['conditionOfItem']
            || $product['sku'] !== $data['variation']['number'] || $product['identifier'] !== (string)$data['variation']['id']
            || $product['manufacturer']['name'] !== 'Fixture Manufacturer' || $product['brand']['name'] !== 'Fixture Brand'
            || $product['gtin13'] !== '4006381333931' || isset($product['height'])
            || ($product['aggregateRating']['ratingValue'] ?? null) !== ($measured ? '4.5' : null)
            || ($product['depth'] ?? null) !== ($measured ? ['@type'=>'QuantitativeValue','value'=>120,'unitCode'=>'MMT'] : null)
            || ($product['width'] ?? null) !== ($measured ? ['@type'=>'QuantitativeValue','value'=>32,'unitCode'=>'MMT'] : null)
            || ($product['weight'] ?? null) !== ($measured ? ['@type'=>'QuantitativeValue','value'=>85,'unitCode'=>'GRM'] : null)) {
            throw new RuntimeException("Incorrect Product metadata for $lang/$format");
        }
        unset($data);
    }
    $rows[]=[$lang,$value,$format];
}
file_put_contents($argv[2],json_encode($rows,JSON_THROW_ON_ERROR));
echo count($rows).' PDP and Quickview renders with prepared and legacy gross prices passed'.PHP_EOL;
