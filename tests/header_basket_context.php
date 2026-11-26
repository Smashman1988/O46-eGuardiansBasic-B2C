<?php
// php tests/header_basket_context.php <Twig autoloader> [historical Header.twig]
require $argv[1];
$root = dirname(__DIR__);
$source = file_get_contents($root.'/resources/views/PageDesign/Partials/Header/Header.twig');
$templates = ['header' => $source];
if (isset($argv[2])) $templates['before'] = file_get_contents($argv[2]);
foreach (['DE', 'EN', 'FR', 'IT', 'ES', ''] as $lang) {
    $templates['GuardiansBasic::Category.Macros.CategoryTree'.$lang.'.twig'] = '{% macro get_nav_bar(categories,scope) %}<li>Menu</li>{% endmacro %}';
}
class HeaderBasketFixture {
    public $lang, $currency, $cacheable, $quantity, $calls;
    function getLanguage() { return $this->lang; }
    function getCurrencyData() { return ['name'=>$this->currency,'symbol'=>$this->currency]; }
    function shouldBeCached() { return $this->cacheable; }
    function getBasketForTemplate() { $this->calls[] = 'basket'; return ['itemQuantity'=>$this->quantity,'shippingCountryId'=>17]; }
    function getBasketQuantity() { $this->calls[] = 'quantity'; return $this->quantity; }
    function getURLById($id) { return '/category-'.$id.'/'; }
    function translate($key) { return $this->lang.'.'.$key; }
    function pluginPath($name) { return '/plugin/'.$name; }
}
$fixture = new HeaderBasketFixture;
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader($templates));
$twig->addFunction(new Twig\TwigFunction('trans', [$fixture,'translate']));
$twig->addFunction(new Twig\TwigFunction('plugin_path', [$fixture,'pluginPath']));
$cases = 0;
foreach (['de','en','fr','it','es'] as $lang) foreach (['EUR','USD','CHF'] as $currency)
foreach ([0,9,9.5,10,25] as $quantity) foreach ([false,true] as $cacheable) foreach ([false,true] as $snapshot) {
    $fixture->lang = $lang;
    $fixture->currency = $currency;
    $fixture->quantity = $quantity;
    $fixture->cacheable = $cacheable;
    $fixture->calls = [];
    $context = ['lang'=>$lang,'vuejs'=>[],'services'=>array_fill_keys(['basket','category','checkout','template','localizationRepository'], $fixture)];
    if ($snapshot) $context['pdpBasketForHeader'] = ['itemQuantity'=>$quantity,'shippingCountryId'=>23];
    $html = $twig->render('header',$context);
    $expected = $snapshot || $cacheable ? [] : ['basket'];
    if ($fixture->calls !== $expected) throw new RuntimeException('Unexpected header basket read');
    $country = strpos($html,' data-eg-basket-country=') !== false;
    if ($country === $cacheable || ($country && strpos($html,' data-eg-basket-country="'.($snapshot ? 23 : 17).'"') === false)) {
        throw new RuntimeException('Incorrect shipping-country visibility or source');
    }
    if (strpos($html,'data-eg-basket-quantity class="header-quant-info'.($cacheable || $quantity < 10 ? '' : '2').'"') === false) {
        throw new RuntimeException('Incorrect quantity badge');
    }
    if (!preg_match('~data-eg-basket-quantity class="header-quant-info2?"></span>~', $html)
        || !preg_match('~data-basket-sum=""[^>]*></span>~', $html)) {
        throw new RuntimeException('Unconfirmed basket quantity or sum in SSR');
    }
    if (isset($argv[2])) {
        $old = $twig->render('before', $context);
        $old = preg_replace('~(data-eg-basket-quantity class="header-quant-info2?">)0</span>~', '$1</span>', $old);
        $old = preg_replace('~(data-basket-sum=""[^>]*>)0,00</span>~', '$1</span>', $old);
        if ($cacheable && $quantity >= 10) {
            $old = str_replace('data-eg-basket-quantity class="header-quant-info2"', 'data-eg-basket-quantity class="header-quant-info"', $old);
        }
        if ($html !== $old) throw new RuntimeException('Unexpected header difference');
    }
    $cases++;
}
echo $cases.' header basket contexts passed with Twig '.Twig\Environment::VERSION.PHP_EOL;
