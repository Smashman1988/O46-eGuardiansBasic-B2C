<?php
// Render the application's own Twig with storefront services replaced by deterministic inputs.
require $argv[1];
$root = dirname(__DIR__, 2);
$templates = [];
$views = $root.'/resources/views/';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views)) as $file) {
    if ($file->getExtension() !== 'twig') continue;
    $name = 'GuardiansBasic::'.str_replace(['/', '\\'], '.', substr($file->getPathname(), strlen($views), -5));
    $templates[$name] = $templates[$name.'.twig'] = file_get_contents($file->getPathname());
}
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader($templates));
$service = new class {
    public $lang='de';
    function getLanguage() { return $this->lang; }
    function getCurrencyData() { return ['name'=>'EUR','symbol'=>'€']; }
    public $checkout = false;
    function isCheckout() { return $this->checkout; }
    function shouldBeCached() { return true; }
    function getCanonicalURL() { return '/scroll/'; }
    function getURLById($id) { return '/category-'.$id.'/'; }
    function get($id, $lang) { return ['id'=>$id,'details'=>[['name'=>'Information '.$id]]]; }
    function getURL($category) { return '/category-'.$category['id'].'/'; }
};
$labels = parse_ini_file($root.'/resources/lang/de/Template.properties', false, INI_SCANNER_RAW);
$twig->addFunction(new Twig\TwigFunction('trans', function ($key) use (&$labels) {return $labels[substr(strrchr($key,'.'),1)] ?? $key;}));
$twig->addFunction(new Twig\TwigFunction('plugin_path', fn($name) => '/plugin/'.$name));
$settings=[];
foreach ([1,2] as $i) {
    foreach (['ImageDesktop','ImageMobile'] as $size) $settings['GuardiansBasic.homeSlider'.$size.'_'.$i]='/image/slide-'.$i.'.svg';
    $settings['GuardiansBasic.homeSliderLink_'.$i]='/category-'.$i.'/';
    $settings['GuardiansBasic.homeSliderLinkTxt_'.$i]='Kategorie '.$i;
}
$twig->addFunction(new Twig\TwigFunction('config', fn($key) => $settings[$key] ?? ''));
$twig->addFunction(new Twig\TwigFunction('queryString',fn($params) => ($query=http_build_query($params)) !== '' ? '?'.$query : ''));
$twig->addFilter(new Twig\TwigFilter('itemName', fn($item) => 'Laufschuh'));
$context = ['services'=>array_fill_keys(['localizationRepository','checkout','template','category','url'], $service),
    'webstoreConfig'=>['domainSsl'=>'https://www.outlet46.de','defaultLanguage'=>'de'],
    'lang'=>'de','pdpBasketForHeader'=>[],'vuejs'=>[],'category'=>['id'=>1],'newsletterUrl'=>'/newsletter/','consentRequired'=>false];
$twig->addGlobal('services', $context['services']);
function fragment($source, $start, $end) {
    $a=strpos($source,$start);
    $b=$a===false ? false : strpos($source,$end,$a+strlen($start));
    if ($a===false || $b===false) throw new RuntimeException('Theme fragment changed: '.$start);
    return substr($source,$a,$b-$a);
}
$result=[];
foreach (['header'=>'PageDesign.Partials.Header.Header','footer'=>'PageDesign.Partials.Footer'] as $key=>$name)
    $result[$key]=$twig->render('GuardiansBasic::'.$name,$context);
$category=$templates['GuardiansBasic::Category.Item.CategoryItemContent'];
$result['controls']=$twig->createTemplate(fragment($category,'<div class="row d-lg-none">','{% include'))->render($context);
$layout=$templates['GuardiansBasic::PageDesign.PageDesign'];
// Include the complete nested modal body up to the matching closing div.
function divSource($source, $opening) {
    $start = strpos($source, $opening);
    if ($start === false) throw new RuntimeException($opening);
    $offset = $start;
    $depth = 0;
    do {
        if (!preg_match('~</?div\b[^>]*>~i', $source, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            throw new RuntimeException('Unbalanced div in '.$opening);
        }
        [$text, $position] = $tag[0];
        $depth += str_starts_with($text, '</') ? -1 : 1;
        $offset = $position + strlen($text);
    } while ($depth > 0);
    return substr($source, $start, $offset-$start);
}
foreach (['modal-cross'=>'modal fade modal-cross','dynamicModal'=>'modal dynamicModal'] as $dialog=>$classes) {
    $source = divSource($layout, '<div class="'.$classes.'"');
    $result[$dialog] = $twig->createTemplate($source)->render($context);
}
preg_match('~<div class="eg-backdrop transition"[^>]+></div>~',$layout,$match);
$result['backdrop']=$match[0];
$pdp=$templates['GuardiansBasic::Item.SingleItem'];
$gallery=fragment($pdp,'<div class="col-12 col-md-6 stickyarticle','{% endif %}' . "\n" . '           {% if isQuickView');
$images=[];
foreach (['blue','green','red'] as $color) {
    $url='/image/'.$color.'.svg';
    $images[]=array_fill_keys(['url','urlMiddle','urlSecondPreview','urlPreview'],$url);
}
$result['gallery']=$twig->createTemplate($gallery)->render(['sortedItemImages'=>$images,'mainImg'=>$images[0],'galleryCount'=>count($images),'itemData'=>[]]);
$variants = [['variationId'=>101,'attributes'=>[['attributeId'=>9,'attributeValueId'=>1]],'isSalable'=>true],
    ['variationId'=>102,'attributes'=>[['attributeId'=>9,'attributeValueId'=>2]],'isSalable'=>true]];
$attributes = [['attributeId'=>9,'name'=>'Farbe','type'=>'text','values'=>[
    ['attributeValueId'=>1,'name'=>'Grün'], ['attributeValueId'=>2,'name'=>'Rot']]]];
$result['selection'] = $twig->createTemplate(divSource($pdp,'<div data-item-variation class='))->render([
    'attributes'=>$attributes, 'variationsWithAttributes'=>$variants, 'variationsWithAttributesWithFilter'=>$variants]);
$result['singleGallery']=$twig->createTemplate($gallery)->render(['sortedItemImages'=>[$images[0]],'mainImg'=>$images[0],'galleryCount'=>1,'itemData'=>[]]);
$homepage=$templates['GuardiansBasic::Homepage.Homepage'];
$result['slider']=$twig->createTemplate(fragment($homepage,'<section class="section-slider-wrap">','<section class="section-banner-first">'))->render($context);
$result['pager']=[];
foreach ([1,2,3] as $page) $result['pager'][$page]=$twig->render('GuardiansBasic::Category.Item.Partials.Pagination',
    $context+['pageMax'=>3,'currentPage'=>$page,'compactPagination'=>true]);
$sortMenu=divSource($category,'<div class="eg-dropdown eg-dropdown-sort');
$sortOptions=['relevance'=>'Relevanz','name_desc'=>'Name Z–A'];
foreach (array_keys($sortOptions) as $sort) {
    $result['sorting'][$sort]=$twig->createTemplate($sortMenu)->render(['query'=>['sorting'=>$sort],'canonicalURL'=>'/search/',
        'searchOptions'=>['defaultSorting'=>'relevance','sorting'=>$sortOptions]]);
}
$newsletter = $twig->createTemplate(fragment($layout, '{% set newsletterPaths', '{% if currentTemplate in vuePages')
    .fragment($layout, '{% if showNewsletter %}', '{% endif %}').'{% endif %}');
foreach (['de','en','fr','it','es'] as $lang) {
    $service->lang=$lang;
    $context['lang']=$lang;
    $labels=parse_ini_file($root.'/resources/lang/'.$lang.'/Template.properties',false,INI_SCANNER_RAW);
    $result['languages'][$lang]=[
        'header'=>$twig->render('GuardiansBasic::PageDesign.Partials.Header.Header',$context),
        'languageOptions'=>$twig->createTemplate(fragment($layout, '<template id="eg-language-options"', '</template>').'</template>')->render($context),
        'newsletter'=>$newsletter->render($context+['wkbkasse'=>[5482,5484],'currentRequestUri'=>'/']),
        'controls'=>$twig->createTemplate(fragment($category,'<div class="row d-lg-none">','{% include'))->render($context)
    ];
}
$excluded = [
    ['isBasketPage'=>true], ['currentTemplate'=>'tpl.confirmation'], ['isShopBuilder'=>true],
    ['category'=>['id'=>5482]], ['category'=>['id'=>5484]], ['currentRequestUri'=>'/amazon'],
    ['currentRequestUri'=>'/expressCheckoutReview'], ['lang'=>'nl']
];
foreach ($result['languages'] as $lang => $parts) {
    if (!preg_match('~<a[^>]+href="([^"]+)"~', $parts['newsletter'], $match)) throw new RuntimeException('Missing newsletter in '.$lang);
    $excluded[] = ['lang'=>$lang, 'currentRequestUri'=>$match[1].'?utm_source=test'];
}
foreach ($excluded as $settings) {
    if (trim($newsletter->render($settings + $context + ['wkbkasse'=>[5482,5484]])) !== '') throw new RuntimeException('Newsletter exclusion: '.json_encode($settings));
}
$service->checkout = true;
if (trim($newsletter->render($context+['wkbkasse'=>[5482,5484]])) !== '') throw new RuntimeException('Newsletter inside checkout');
echo json_encode($result,JSON_THROW_ON_ERROR);

