<?php
// php tests/card_image_loading.php <Twig autoload> <report.json>
// Real Twig renders: media selection and image fallbacks may change; all other
// card content, prices, URLs, order, attributes and service calls must not.
require $argv[1];
require_once __DIR__.'/card_markup_contract.php';
require_once __DIR__.'/list-fixtures.php';
require_once __DIR__.'/../src/Extensions/Base_Extension.php';
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
function imageCheck($condition, $message) { if (!$condition) throw new RuntimeException($message); }
class ImageCardFixture {
    public $lang, $dictionary=[], $calls=[], $itemUrl;
    function __construct($lang) {
        $this->lang=$lang;
        foreach (['de',$lang] as $language) foreach (file(__DIR__.'/../resources/lang/'.$language.'/Template.properties') as $line) {
            if (preg_match('/^([\w.-]+)\s*=\s*(.*)$/u',trim($line),$m)) $this->dictionary[$m[1]]=$m[2];
        }
    }
    function count($key) { $this->calls[$key]=($this->calls[$key]??0)+1; }
    function trans($key) { $this->count('trans'); $parts=explode('::Template.',$key);return $this->dictionary[end($parts)]??$key; }
    function path($name) { $this->count('path');return '/fixture/'.$name; }
    function partial($name) { return 'layout'; }
    function noop() { return ''; }
    function url($data) { $this->count('itemURL');return $this->itemUrl ?? '/'.$this->lang.'/product-'.$data['variation']['itemId'].'/'; }
    function name($data) { $this->count('itemName');return $data['texts']['name1']; }
    function attributes($id) { $this->count('attributes');return ['attributes'=>[['type'=>'box','values'=>[['position'=>1,'name'=>'M'],['position'=>2,'name'=>'L & XL']]]]]; }
    function batch($ids) { $this->count('batch');$maps=[];foreach($ids as $id)$maps[$id]=$this->attributes($id);return $maps; }
    function order($rows,$key) { usort($rows,function($a,$b)use($key){return $a[$key]<=>$b[$key];});return $rows; }
}
class ImageCardServices {
    public $checkout,$localizationRepository;
    function __construct($lang) {
        $this->checkout=new class { function getCurrencyData(){return ['name'=>'EUR'];} };
        $this->localizationRepository=new class($lang) { private $lang;function __construct($lang){$this->lang=$lang;}function getLanguage(){return $this->lang;} };
    }
}
function imageEnvironment($baseline, $fixture) {
    $names=['Category.Item.CategoryItem','Category.Item.CategoryItemContent','Category.Item.Partials.CategoryListItem','Category.Item.Partials.CategoryListItem2','Category.Item.Partials.CategoryGridOnly','Category.Item.Partials.CategoryAttributes','Category.Item.Partials.AllItemsLink','Category.Macros.render-rating'];
    $templates=['layout'=>'{% block PageBody %}{% endblock %}'];
    foreach($names as $name){$relative=str_replace('.','/',$name).'.twig';$file=$baseline.'/'.$relative;if(!is_file($file))$file=__DIR__.'/../resources/views/'.$relative;$templates['GuardiansBasic::'.$name]=file_get_contents($file);}
    foreach(['Category.Item.Partials.Pagination','Category.Item.Partials.FacetFilter2','Category.Item.Partials.FacetFilter3'] as $name)$templates['GuardiansBasic::'.$name]='';
    foreach(['Ceres::PageDesign.Macros.LayoutContainer','Ceres::PageDesign.Macros.ItemName'] as $name)$templates[$name]='';
    $twig=new Twig\Environment(new Twig\Loader\ArrayLoader($templates));
    $extension = (new ReflectionClass(GuardiansBasic\Extensions\Base_Extension::class))->newInstanceWithoutConstructor();
    $twig->addFunction(new Twig\TwigFunction('getCardLinks',[$extension,'getCardLinks']));
    foreach(['trans'=>'trans','plugin_path'=>'path','getPartial'=>'partial','config'=>'noop','queryString'=>'noop','createSeoPage'=>'noop','getAttributesWithImages'=>'attributes','getCardAttributeMaps'=>'batch'] as $name=>$method)$twig->addFunction(new Twig\TwigFunction($name,[$fixture,$method]));
    foreach(['itemURL'=>'url','itemName'=>'name','orderByKey'=>'order'] as $name=>$method)$twig->addFilter(new Twig\TwigFilter($name,[$fixture,$method]));
    return $twig;
}
function imageItems($count,$lang,$missingThird=false,$offset=0) {
    $items=[];
    for($i=0;$i<$count;$i++){$id=100+$i+$offset;$items[]=['data'=>['item'=>['id'=>$id,'flagTwo'=>0,'manufacturer'=>['name'=>'11 project','logo'=>$i%3?'https://cdn02.outlet46.de/frontend/vendor/11-project.jpg':''],'feedbackCount'=>$i%2,'feedbackDecimal'=>3.7],'variation'=>['id'=>10000+$id,'itemId'=>$id],'texts'=>['name1'=>$lang.' Product "'.$id.'" & sizes'],'images'=>['all'=>$missingThird&&$i===2?[]:[['urlPreview'=>'/images/product-'.$id.'.jpg']]],'prices'=>['default'=>['unitPrice'=>['value'=>4.99,'formatted'=>'4,99 €']],'rrp'=>['unitPrice'=>['value'=>$i%4?19.99:null,'formatted'=>'19,99 €']]],'tags'=>$i%2?[['id'=>284]]:[]]];}
    return $items;
}
function imageContractWithoutResponsiveSources($html) {
    // libxml's HTML4 parser nests HTML5 <source> elements. Remove only the
    // intended opening tags before parsing, so removing a source cannot also
    // remove its following image or fallback from the invariant comparison.
    return cardMarkupContract(withoutCardResponsiveSources($html));
}
$rows=[]; $views=__DIR__.'/../resources/views';
foreach (['de','en','fr','it','es'] as $lang) {
    foreach (['category'=>'Category.Item.CategoryItem','content'=>'Category.Item.CategoryItemContent','home'=>'Category.Item.Partials.CategoryGridOnly'] as $kind=>$name) {
        foreach ([1,2] as $page) {
            $count=$kind==='home'?60:12;
            $offset=($page-1)*$count;
            $items=imageItems($count,$lang,false,$offset);
            $fixture=new ImageCardFixture($lang);
            $twig=imageEnvironment($views,$fixture);
            $ctx=['lang'=>$lang,'itemList'=>$items,'services'=>new ImageCardServices($lang),'category'=>['id'=>44,'parentCategoryId'=>0,'details'=>[['name'=>'Fixture']]],
                'currentPage'=>$page,'pageMax'=>3,'itemCountTotal'=>120,'request'=>new class {function get($key){return null;}},'prioritizeFirstCardImage'=>true,'isQuickView'=>true];
            $html=$twig->render('GuardiansBasic::'.$name,$ctx);
            $dom=cardMarkupDocument($html); $xp=new DOMXPath($dom); $cards=$xp->query('//*[@data-eg-item]');
            imageCheck($cards->length===$count,'Card count for '.$kind);
            foreach ($cards as $index=>$card) {
                imageCheck($card->getAttribute('data-eg-item')===(string)(100+$index+$offset),'Article order');
                $sources=$xp->query('.//picture//source[@media]',$card);
                $eligible=$kind!=='home' && $index>=2 && $index<=5 && !empty($items[$index]['data']['images']['all'][0]['urlPreview']);
                imageCheck($sources->length===($eligible?2:0),'Responsive candidates for '.$kind.'/'.$index);
                if ($eligible) imageCheck($sources->item(0)->getAttribute('media')==='(min-width: '.($index<4?768:1200).'px)','Breakpoint');
                $a=$xp->query('.//a[@href]',$card)->item(0); $button=$xp->query('.//button[@data-href]',$card)->item(0);
                imageCheck($a && $button && $button->getAttribute('data-href')===$a->getAttribute('href').'?quickViewItem=true','Quickview target');
            }
            if ($lang==='de' && $kind==='category') {
                $list=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," category-product-list ")]')->item(0);
                imageCheck($list!==null,'Active grid missing');
                if ($page===1 && isset($argv[3])) {
                    $baseline=imageEnvironment($argv[3],new ImageCardFixture($lang));
                    $original=$baseline->render('GuardiansBasic::'.$name,$ctx);
                    $oldDom=cardMarkupDocument($original);
                    $before=(new DOMXPath($oldDom))->query('//*[contains(concat(" ",normalize-space(@class)," ")," category-product-list ")]')->item(0);
                    imageCheck($before!==null,'Baseline grid missing');
                    file_put_contents(dirname($argv[2]).'/cards-de-before.html',$oldDom->saveHTML($before));
                }
                file_put_contents(dirname($argv[2]).'/cards-de-'.($page===1?'after':'next').'.html',$dom->saveHTML($list));
            }
            $rows[]=[$lang,$kind,$page,$count];
        }
    }
}
$probe = new ImageCardFixture('fr');
$probe->itemUrl='/fr/art/?ref=x%2By&Currency=USD&%63urrency=GBP&QuickViewItem=false#sizes';
$twig=imageEnvironment($views,$probe);
foreach (['CHF'=>'&currency=CHF','EUR'=>'',''=>''] as $currency=>$parameter) {
    $html=$twig->render('GuardiansBasic::Category.Item.Partials.CategoryListItem',[
        'item'=>imageItems(1,'fr',false,0)[0], 'currencycheck'=>$currency, 'services'=>new ImageCardServices('fr')]);
    $xp=new DOMXPath(cardMarkupDocument($html));
    foreach ($xp->query('//*[@data-eg-item]//a[@href]') as $link)
        imageCheck($link->getAttribute('href')==='/fr/art/?ref=x%2By'.$parameter.'#sizes','Currency on product link');
    imageCheck($xp->evaluate('string(//button/@data-href)')==='/fr/art/?ref=x%2By'.$parameter.'&quickViewItem=true#sizes','Currency on Quickview link');
}
file_put_contents($argv[2],json_encode($rows,JSON_THROW_ON_ERROR));
echo count($rows)." card grids rendered and checked\n";
