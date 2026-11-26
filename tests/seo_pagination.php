<?php
// php tests/seo_pagination.php <external-twig-autoload.php> <report.json> [--compare=<before-fix.json>]
// Actual theme PHP/Twig; Plenty dependencies below are explicit local fixtures.
namespace Plenty\Plugin\Templates\Extensions { class Twig_Extension {} }
namespace Plenty\Plugin\Templates\Factories {
    class TwigFactory { public function createSimpleFunction($name, $callback) { return [$name, $callback]; } }
}
namespace Plenty\Plugin\Http {
    class Request {
        public $params = [];
        public $rawUri;
        public function query() { return $this->params; }
        public function get($key, $default = null) { return $this->params[$key] ?? $default; }
        public function getRequestUri() { return $this->rawUri ?? '/damen/' . ($this->params ? '?' . http_build_query($this->params) : ''); }
    }
}
namespace Plenty\Plugin\Templates {
    class Twig {
        public $environment;
        public $beforeRender;
        public $params;
        public function render($name, $params = []) {
            if ($this->beforeRender) { ($this->beforeRender)($name, $params); }
            if ($name === 'GuardiansBasic::Category.Item.CategoryItemContent') { $this->params = $params; }
            return $this->environment->render($name, $params);
        }
    }
}
namespace Ceres\Contexts {
    class CategoryContext {
        public $category = ['id' => 100, 'details' => [['metaRobots' => 'NOINDEX_NOFOLLOW', 'metaTitle' => 'Route title', 'metaDescription' => 'Route description', 'metaKeywords' => 'Route keywords']]];
        public $metaRobots = 'NOINDEX,NOFOLLOW';
        public $assetName = 'route-assets';
        public $bodyClasses = ['route-category'];
        public $initializations = 0;
        public function init($params) {
            $this->initializations++;
            $this->category = $params['category'];
            $this->metaRobots = 'SOURCE'; $this->assetName = 'source-assets'; $this->bodyClasses = ['source-category'];
        }
    }
}
namespace IO\Services {
    class CategoryService {
        public $route;
        public $reads = [];
        public function get($id, $lang = null) { $this->reads[] = [$id,$lang]; return ['id' => $id, 'details' => in_array($lang ?? 'de', $this->languages, true) ? [['lang'=>$lang ?? 'de', 'name'=>'Category ' . $id, 'description'=>'', 'description2'=>'']] : []]; }
        public function getCurrentCategory() { return $this->route; }
        public $languages = ['de','en','fr','it','es'];
        public function getDetails($category, $lang) {
            foreach ($category['details'] ?? [] as $detail) {
                if (($detail['lang'] ?? null) === $lang) { return $detail; }
            }
            return null;
        }
    }
}
namespace IO\Helper {
    class RouteConfig {
        public static $links = [];
        public static $disabled = [];
        public static function getCategoryId($route) { return self::$links[$route] ?? 0; }
        public static function isActive($route) { return !in_array($route,self::$disabled,true); }
    }
}
namespace Ceres\Helper {
    class SearchOptions {
        const SCOPE_CATEGORY = 'category';
        public static $overrideSize = null;
        public static $overridePage = null;
        public static $menuReads = 0;
        public static function validateItemListOptions($options, $scope) {
            if ((int)$options['page'] <= 0) { $options['page'] = 1; }
            if ((int)$options['itemsPerPage'] <= 0) { $options['itemsPerPage'] = 24; }
            if (self::$overrideSize !== null) { $options['itemsPerPage'] = self::$overrideSize; }
            if (self::$overridePage !== null) { $options['page'] = self::$overridePage; }
            if (!$options['sorting']) { $options['sorting'] = 'variation.createdAt_desc'; }
            return $options;
        }
        public static function get($scope) {
            self::$menuReads++;
            return (object)['defaultSorting' => 'variation.createdAt_desc', 'defaultItemsPerPage' => 24,
                'sorting' => ['variation.createdAt_desc' => 'Newest', 'texts.name1_asc' => 'Name']];
        }
    }
}
namespace Plenty\Modules\Webshop\ItemSearch\SearchPresets {
    require_once __DIR__ . '/fixtures/CategoryListSearch.php';
    class Facets { public static function getSearchFactory($options) { return $options; } }
}
namespace Plenty\Modules\Webshop\ItemSearch\Services {
    class ItemSearchService {
        public $calls = [];
        public $boundaryCalls = [];
        public $total = 1654;
        public $documentTotal = null;
        public $boundaryDocumentTotal = null;
        public $omitDocuments = false;
        public $totalResolver = null;
        public function getResults($factories) {
            $key = isset($factories['boundary']) ? 'boundary' : 'itemList';
            $factory = $factories[$key];
            if ($key === 'boundary' && $factory->fields !== ['variation.id']) {
                throw new \RuntimeException('Boundary must request only variation.id');
            }
            $factories[$key] = $factory->options;
            if ($key === 'boundary') {
                $this->boundaryCalls[] = $factories;
            } else {
                $this->calls[] = $factories;
            }
            $options = $factories[$key];
            $total = $this->totalResolver ? ($this->totalResolver)($options, $key) : $this->total;
            $result = isset($factories['facets']) ? ['facets'=>[]] : [];
            if ($this->omitDocuments) { return $result + [$key=>['total'=>$total]]; }
            $first = ((int)$options['page'] - 1) * (int)$options['itemsPerPage'];
            $items = [];
            $available = $key === 'boundary' ? ($this->boundaryDocumentTotal ?? $this->documentTotal) : $this->documentTotal;
            for ($id = $first + 1; $id <= min($available ?? $total, $first + (int)$options['itemsPerPage']); $id++) {
                $items[] = ['data' => ['variation' => ['id' => $id], 'images' => ['all' => []],
                    'item' => ['manufacturer' => ['logo' => '']]]];
            }
            return $result + [$key => ['total' => $total, 'documents' => $items]];
        }
    }
}
namespace {
    require __DIR__ . '/asset_manifest.php';
    use GuardiansBasic\Helper\SeoPage;
    use GuardiansBasic\Extensions\Base_Extension;
    use Ceres\Helper\SearchOptions;
    const SEO_BASELINE = '259a6c0';

    if (count($argv) < 3 || count($argv) > 4) { throw new RuntimeException('Expected external Twig autoloader, JSON report path and optional --compare'); }
    require $argv[1];
    require __DIR__ . '/../src/Helper/SeoPage.php';
    require __DIR__ . '/../src/Extensions/Base_Extension.php';
    $root = dirname(__DIR__);
    $deprecations = [];
    set_error_handler(function ($level, $message) use (&$deprecations) {
        if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) { $deprecations[$message] = true; return true; }
        throw new ErrorException($message, 0, $level);
    });
    function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
    $factoryCalls = [];
    function pluginApp($class, array $parameters = []) {
        global $services, $factoryCalls;
        if ($class === Plenty\Modules\Webshop\Helpers\UrlQuery::class) {
            return new class($parameters) {
                private $p;
                public function __construct($p) { $this->p = $p; }
                public function getPath() { return $this->p['path']; }
                public function toAbsoluteUrl($include) { return 'https://example.test/' . ($include ? $this->p['lang'].'/' : '') . $this->getPath() . '/'; }
            };
        }
        if ($class === SeoPage::class) {
            $factoryCalls[] = ['class'=>$class,'parameters'=>$parameters];
            // Explicit transient-container fixture, not the real Plenty container.
            return new SeoPage($parameters['query'], $parameters['template'], $parameters['pageMax'], $parameters['currentPage'], $parameters['total'], $parameters['requestUri']);
        }
        if ($class === UnexpectedValueException::class) {
            $factoryCalls[] = ['class'=>$class,'parameters'=>$parameters];
            return new UnexpectedValueException($parameters['message']);
        }
        check(isset($services[$class]), 'Unexpected service: ' . $class);
        return $services[$class];
    }
    class Callback {
        private $callback;
        public function __construct($callback) { $this->callback = $callback; }
        public function invoke(...$args) { return ($this->callback)(...$args); }
    }
    class TemplateService {
        public $template = 'tpl.category.content';
        public function getCurrentTemplate() { return $this->template; }
        public function isItem() { return $this->template === 'tpl.item'; }
        public function isCheckout() { return false; }
        public function isSearch() { return strpos($this->template, 'tpl.search') === 0; }
        public function isCategory() { return strpos($this->template, 'tpl.category') === 0; }
        public function isMyAccount() { return false; }
        public function shouldBeCached() { return false; }
        public function isCheapestSorting() { return false; }
    }
    class UrlService {
        public $configured = null;
        public $base = 'https://example.test/damen/';
        public function getCanonicalURL($lang = null, $ignore = false) {
            if (!$ignore && $this->configured !== null) { return $this->configured; }
            if ($this->base === null) { return null; }
            return $lang && $lang !== 'de' ? 'https://example.test/' . $lang . '/damen/' : $this->base;
        }
        public function getCategoryURL($id, $lang) { $url = clone $this; $url->base = $this->getCanonicalURL($lang,true); return $url; }
        public function toAbsoluteUrl($includeLanguage) { return $this->base; }
        public function getVariationURL($itemId, $variationId, $lang) { return $this->getCategoryURL($itemId,$lang); }
        public function getPath() { return '/entity/'; }
        public function getLanguageURLs() {
            $urls = [];
            foreach (['de','en','fr','it','es'] as $lang) { $urls[$lang] = $this->getCanonicalURL($lang); }
            return $urls;
        }
    }
    class EmptyService { public function __call($name, $args) { return null; } }
    class LocalizationRepository {
        public function __construct(private $language = 'de') {}
        public function getLanguage() { return $this->language; }
    }
    function localizedContext($base, $language) {
        $base['services']['localizationRepository'] = new LocalizationRepository($language);
        return $base;
    }
    function readView($name) { global $root; return file_get_contents($root . '/resources/views/' . $name . '.twig'); }
    function gitSource($file, $reference = SEO_BASELINE) {
        global $root;
        $process = proc_open(['git','show',$reference . ':' . $file], [1=>['pipe','w'],2=>['pipe','w']], $pipes, $root);
        $source = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0, $error);
        return $source;
    }
    function compactHtml($html) { return preg_replace('/>\s+</', '><', trim($html)); }
    function checkThemeAssetOrder($html, $page, $isItem) {
        $base = '/plugins/GuardiansBasic/';
        preg_match_all('~<script\s+src="([^"]*/js/(?:core|magiczoom|cat|main|vue|item)\\.min\\.js\\?[^"]*)"([^>]*)>~', $html, $tags, PREG_SET_ORDER);
        $expected = [$base . verifiedThemeAssetPath('js/core.min.js'), $base . verifiedThemeAssetPath('js/' . $page . '.min.js')];
        if ($isItem) { $expected[] = $base . verifiedThemeAssetPath('js/magiczoom.min.js'); }
        check(array_map(function ($tag) { return html_entity_decode($tag[1], ENT_QUOTES, 'UTF-8'); }, $tags) === $expected,
            'Core/page/MagicZoom script count, URL or execution order changed');
        foreach ($tags as $tag) { check(strpos($tag[2], 'defer') !== false, 'Theme script lost defer'); }
        check(strpos($tags[0][2], 'data-eg-magiczoom-src="' . $base . verifiedThemeAssetPath('js/magiczoom.min.js') . '"') !== false,
            'Core lost the versioned on-demand MagicZoom URL');
    }
    // Targeted source guard, NOT the PlentyONE compiler/validator. Native names
    // checked against the official NativePHPFunctionsAndClasses list, 2026-11-09.
    function checkPluginSource($source): array {
        $allowed = ['array_chunk','array_diff','array_filter','array_key_exists','array_keys','array_merge','array_unique','array_values',
            'ceil','count','explode','filter_var','floor','implode','in_array','intval','is_array','is_float','is_int',
            'is_string','preg_match','preg_split','strpos','strtolower','strtoupper','trim','pluginapp'];
        $tokens = array_values(array_filter(token_get_all($source), function ($token) {
            return !is_array($token) || !in_array($token[0],[T_OPEN_TAG,T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true);
        }));
        $issues = []; $functions = [];
        foreach ($tokens as $index=>$token) {
            if (!is_array($token)) { continue; }
            if (in_array($token[0],[T_NEW,T_CLONE,T_EVAL,T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE],true)) {
                $issues[] = ['kind'=>'restricted-construct','name'=>trim($token[1])];
            }
            if ($token[0] === T_VARIABLE && ($tokens[$index+1] ?? null) === '(') {
                $issues[] = ['kind'=>'dynamic-function','name'=>$token[1]];
            }
            if (!in_array($token[0],[T_STRING,T_NAME_FULLY_QUALIFIED,T_NAME_QUALIFIED],true) || ($tokens[$index+1] ?? null) !== '(') { continue; }
            $previous = $tokens[$index-1] ?? null;
            if (is_array($previous) && in_array($previous[0],[T_FUNCTION,T_OBJECT_OPERATOR,T_NULLSAFE_OBJECT_OPERATOR,T_DOUBLE_COLON,T_NEW],true)) { continue; }
            $name = strtolower(ltrim($token[1],'\\')); $functions[] = $name;
            if (!in_array($name,$allowed,true)) { $issues[] = ['kind'=>'unlisted-function','name'=>$name]; }
        }
        $functions = array_values(array_unique($functions)); sort($functions);
        return ['issues'=>$issues,'functions'=>$functions];
    }
    function meta($html, $name) {
        preg_match_all('~<meta name="' . $name . '" content="([^"]*)"~', $html, $matches);
        check(count($matches[1]) === 1, 'Expected exactly one ' . $name . ' tag');
        return html_entity_decode($matches[1][0], ENT_QUOTES, 'UTF-8');
    }
    function canonical($html) {
        preg_match_all('~<link rel="canonical" href="([^"]*)"~', $html, $matches);
        check(count($matches[1]) <= 1, 'Duplicate canonical');
        return isset($matches[1][0]) ? html_entity_decode($matches[1][0], ENT_QUOTES, 'UTF-8') : null;
    }
    $report = ['php'=>PHP_VERSION, 'twig'=>Twig\Environment::VERSION, 'cases'=>[], 'failures'=>[],
        'runtime'=>'Local dependency fixtures; PlentyONE NOT_RUN'];
    function test($name, $run) {
        global $report;
        try { $report['cases'][$name] = $run() ?? 'PASS'; }
        catch (Throwable $e) { $report['failures'][$name] = $e->getMessage(); }
    }

    $request = new Plenty\Plugin\Http\Request();
    $renderer = new Plenty\Plugin\Templates\Twig();
    $factory = new Plenty\Plugin\Templates\Factories\TwigFactory();
    $context = new Ceres\Contexts\CategoryContext();
    $categoryService = new IO\Services\CategoryService(); $categoryService->route = $context->category;
    $search = new Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService();
    $services = [Ceres\Contexts\CategoryContext::class=>$context, IO\Services\CategoryService::class=>$categoryService,
        Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService::class=>$search];
    $extension = new Base_Extension($factory, $renderer, $request);
    // Execute the actual previous method, rather than reconstructing its result.
    $baselineSource = gitSource('src/Extensions/Base_Extension.php');
    $baselineSource = str_replace('class Base_Extension extends', 'class BaselineBaseExtension extends', $baselineSource);
    $baselinePath = tempnam(dirname($argv[2]), 'seo-baseline-');
    file_put_contents($baselinePath, $baselineSource);
    try { require $baselinePath; }
    finally { unlink($baselinePath); }
    $baselineExtension = new GuardiansBasic\Extensions\BaselineBaseExtension($factory,$renderer,$request);
    $templates = [
        'layout'=>readView('PageDesign/PageDesign'),
        'GuardiansBasic::PageDesign.Components.NewsletterPopup'=>readView('PageDesign/Components/NewsletterPopup'),
        'head'=>readView('PageDesign/Partials/Head'),
        'metadata'=>readView('PageDesign/Partials/PageMetadata'),
        'metadata-block-title'=>'{% embed "metadata" %}{% block title fixtureTitle %}{% endembed %}',
        'content'=>readView('Category/Content/CategoryContent'),
        'GuardiansBasic::Category.Content.PreparedShopBuilder'=>readView('Category/Content/PreparedShopBuilder'),
        'GuardiansBasic::Category.Item.CategoryItemContent'=>readView('Category/Item/CategoryItemContent'),
        'GuardiansBasic::Category.Item.Partials.AllItemsLink'=>readView('Category/Item/Partials/AllItemsLink'),
        'GuardiansBasic::Category.Item.Partials.CategoryGridOnly'=>'<div class="simple">{% for item in itemList %}{{ item.data.variation.id }},{% endfor %}</div>',
        'GuardiansBasic::Category.Item.Partials.CategoryListItem'=>'<span class="item">{{ item.data.variation.id }}</span>',
        'GuardiansBasic::Category.Item.Partials.CategoryListItem2'=>'<span class="item-first">{{ item.data.variation.id }}</span>',
        'GuardiansBasic::Category.Item.Partials.FacetFilter2'=>readView('Category/Item/Partials/FacetFilter2'),
        'GuardiansBasic::Category.Item.Partials.FacetFilter3'=>readView('Category/Item/Partials/FacetFilter3'),
        'GuardiansBasic::Category.Item.Partials.Pagination'=>readView('Category/Item/Partials/Pagination'),
        'empty'=>'',
        'home'=>readView('Homepage/Homepage'),
        'not-found'=>readView('StaticPages/PageNotFound'),
        'regular'=>'{% extends "layout" %}{% block PartialHead %}{% embed "metadata" %}{% endembed %}{% endblock %}',
        'actual-regular'=>readView('Category/Item/CategoryItem'),
        'GuardiansBasic::Category.Item.CategoryItemBrands'=>readView('Category/Item/CategoryItemBrands'),
        'regular-head'=>'{% extends "actual-regular" %}{% block PageBody %}{% endblock %}',
        'alternate-layout'=>'<!DOCTYPE html><html><head>{% include "head" %}{% block PartialHead %}{% endblock %}</head><body>{% block PageBody %}{% endblock %}</body></html>',
        'block-robots'=>'{% extends "layout" %}{% block PartialHead %}{% embed "metadata" %}{% block robots %}{{ fixtureRobots }}{% endblock %}{% endembed %}{% endblock %}',
        'Ceres::PageDesign.Macros.LayoutContainer'=>'{% macro show(name) %}{% endmacro %}',
        'Ceres::PageDesign.Macros.IncludeComponents'=>'{% macro include_components() %}{% endmacro %}',
        'Ceres::PageDesign.Macros.Translations'=>'{% macro add(plugin, file) %}{% endmacro %}'
    ];
    // The surrounding layout is real. Unrelated UI components are inert fixtures.
    preg_match_all('/\{%\s*(?:include|import)\s+"([^"]+)"/', $templates['layout'], $includes);
    foreach ($includes[1] as $name) { $templates[$name] = $templates[$name] ?? ''; }
    foreach (['DE','EN','FR','IT','ES'] as $lang) { $templates['GuardiansBasic::PageDesign.Components.nl-element' . $lang] = ''; }
    $loader = new Twig\Loader\ArrayLoader($templates);
    $twig = new Twig\Environment($loader, ['cache'=>false, 'autoescape'=>'html']);
    $renderer->environment = $twig;
    function addFunction($twig, $name, $callback) { $twig->addFunction(new Twig\TwigFunction($name, [new Callback($callback),'invoke'])); }
    foreach (['getBrandGroups','getFacetView','getStorefrontLanguageRoutes','createSeoPage','prepareSeoContent','getItemsFromCat','getItemsFromCatSimple','getCardAttributeMaps'] as $name) { addFunction($twig, $name, [$extension,$name]); }
    foreach (['get_consent_scripts','get_filtered_tags','get_json_data','get_shop_builder_scripts','component','ssr_entry','csrf_token'] as $name) { addFunction($twig,$name,function () { return ''; }); }
    foreach (['get_additional_styles','get_additional_scripts'] as $name) { addFunction($twig,$name,function () { return []; }); }
    addFunction($twig,'getPartial',function ($name) { return ['page-design'=>'layout','head'=>'head','page-metadata'=>'metadata'][$name] ?? 'empty'; });
    addFunction($twig,'plugin_path',function ($name) { return '/plugins/' . $name; });
    addFunction($twig,'config',function ($key) {
        if (strpos($key, 'IO.routing.category_') === 0) {
            return \IO\Helper\RouteConfig::getCategoryId(substr($key, strlen('IO.routing.category_')));
        }
        return $key === 'Ceres.meta.robots_home' ? 'NOINDEX,NOFOLLOW' : '';
    });
    $searchLabels = [];
    foreach (['de','en','fr','it','es'] as $language) {
        $translations = file_get_contents($root . '/resources/lang/' . $language . '/Template.properties');
        check(preg_match('/^searchresultfor\s*=\s*(.+)$/m', $translations, $label) === 1, 'Missing search heading in ' . $language);
        $searchLabels[$language] = trim($label[1]);
    }
    $translate = new Callback(function ($context, $key) use ($searchLabels) {
        if ($key === 'GuardiansBasic::Template.searchresultfor') {
            return $searchLabels[$context['services']['localizationRepository']->getLanguage()];
        }
        return $key;
    });
    $twig->addFunction(new Twig\TwigFunction('trans', [$translate,'invoke'], ['needs_context'=>true]));
    addFunction($twig,'queryString',function ($replace = []) use ($request) { $params = array_replace($request->params,$replace); return $params ? '?' . http_build_query($params) : ''; });
    foreach (['filterFields','itemName','specialOffer','formatMonetary','itemURL'] as $name) { $twig->addFilter(new Twig\TwigFilter($name,[new Callback(function ($value) { return $value; }),'invoke'])); }
    $builderCalls = 0; $builderMode = 'single'; $builderSpecs = [];
    addFunction($twig,'shop_builder_category_template',function ($id) use (&$builderCalls,&$builderMode,&$builderSpecs,$extension) {
        $builderCalls++;
        if ($builderMode === 'rendered-widget') { return $builderSpecs['html']; }
        if ($builderMode === 'none') { return '<section class="editorial">Editorial</section>'; }
        if ($builderMode === 'empty') { return ''; }
        if ($builderMode === 'throw') { throw new RuntimeException('fixture builder failure'); }
        if ($builderMode === 'configured') {
            $html = '';
            foreach ($builderSpecs as $arguments) { $html .= $extension->getItemsFromCat(...$arguments); }
            return '<section class="builder">' . $html . '</section>';
        }
        if ($builderMode === 'simple-only') { return $extension->getItemsFromCatSimple(600,8,[123]); }
        $html = $extension->getItemsFromCat(200,24,[7],[10,80]);
        if ($builderMode === 'multiple') { $html .= $extension->getItemsFromCat(300,24,[]); }
        if ($builderMode === 'simple') { $html .= $extension->getItemsFromCatSimple(300,4,[]); }
        return '<section class="builder">' . $html . '</section>';
    });
    $templateService = new TemplateService(); $urls = new UrlService(); $emptyService = new EmptyService();
    $services[IO\Services\UrlService::class] = $urls;
    $services[Plenty\Modules\Webshop\Contracts\WebstoreConfigurationRepositoryContract::class] = new class {
        public function getWebstoreConfiguration() { return (object)['defaultLanguage'=>'de']; }
        public function getActiveLanguageList() { return ['de','en','fr','it','es']; }
    };
    $base = ['request'=>$request, 'category'=>$context->category, 'lang'=>'de', 'isShopBuilder'=>false,
        'services'=>['template'=>$templateService,'category'=>$categoryService,'url'=>$urls,'localizationRepository'=>new LocalizationRepository(),'checkout'=>$emptyService,'customer'=>$emptyService,'webstoreConfig'=>$emptyService],
        'context'=>new class { public function renderState() { return ''; } },
        'webstoreConfig'=>['domainSsl'=>'https://example.test','name'=>'Outlet46','defaultLanguage'=>'de'],
        'ceresConfig'=>['global'=>['blockCookies'=>false],'header'=>['companyLogo'=>'/logo.png']],
        'buildHash'=>'fixture', 'forceNoIndex'=>false, 'currencyData'=>['name'=>'EUR'], 'notifications'=>[]];
    // Plenty globals are supplied to nested renders too, as in the shop.
    foreach ($base as $key=>$value) { $twig->addGlobal($key,$value); }

    test('head/rendered-basket-style-and-canonical', function () use ($twig, $base) {
        $html = $twig->render('head', ['isQuickView'=>true, 'currentTemplate'=>'tpl.category.item'] + $base);
        $document = new \DOMDocument();
        $document->loadHTML('<!doctype html><html><head>'.$html.'</head><body></body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $styles = $xpath->query('//head/style[@data-eg-basket-summary-critical]');
        check($styles->length === 1, 'Missing basket summary CSS');
        $css = $styles->item(0)->textContent;
        check(preg_match('~#page-header\s+\.header-sum-info\s*\{\s*min-inline-size:\s*calc\(9ch \+ 3em\)~', $css) === 1, 'Desktop basket reservation lost during Twig rendering');
        check(strpos($css, '<') === false, 'Basket style swallowed subsequent head markup');
        check($xpath->query('//head/link[@rel="canonical"]')->length === 1, 'Canonical missing from parsed document head');
        return 'Basket reservation and canonical present in rendered HTML';
    });
    foreach (['basket','checkout','login','register','my-account','wish-list','confirmation'] as $route) {
        foreach (['template','body-class','configured-category'] as $mode) {
            test("robots/system/$route/$mode", function () use ($route,$mode,$twig,$base,$request,$templateService) {
                $saved = \IO\Helper\RouteConfig::$links;
                $previous = $templateService->template;
                try {
                    $request->params = [];
                    $templateService->template = $mode === 'template' ? 'tpl.'.$route : 'tpl.category.content';
                    $ctx = $base;
                    $ctx['category']['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
                    $ctx['bodyClasses'] = $mode === 'body-class' ? ['page-category','category-'.$route] : ['page-category'];
                    if ($mode === 'configured-category') {
                        \IO\Helper\RouteConfig::$links[$route] = $ctx['category']['id'];
                    }
                    $html = $twig->render('regular',$ctx);
                    check(meta($html,'robots') === 'NOINDEX,FOLLOW' && canonical($html) !== null,'System metadata');
                    if ($mode !== 'template') {
                        check(strpos($html,'name="o46-language-route"') !== false,'Missing functional category routes');
                    }
                    $ctx['category']['details'][0]['metaRobots'] = 'NOFOLLOW,NOARCHIVE';
                    check(meta($twig->render('regular',$ctx),'robots') === 'NOINDEX,NOFOLLOW,NOARCHIVE','Stricter directive lost');
                } finally {
                    \IO\Helper\RouteConfig::$links = $saved;
                    $templateService->template = $previous;
                }
            });
        }
    }
    foreach (['basket'=>'Basket/Basket','checkout'=>'Checkout/CheckoutView','login'=>'Customer/Login',
              'register'=>'Customer/Register','confirmation'=>'Checkout/OrderConfirmation'] as $route=>$view) {
        test("robots/system/$route/theme-head", function () use ($route,$view,$loader,$twig,$base,$request,$templateService) {
            $previous = $templateService->template;
            try {
                $request->params = [];
                $templateService->template = 'tpl.'.$route;
                check(preg_match('~(\{% block PartialHead %\}.*?)\{% block PageBody %\}~s',readView($view),$part) === 1,'Missing system head');
                $loader->setTemplate('system-head-'.$route,'{% extends "layout" %}'.$part[1]);
                $ctx = $base;
                $ctx['category'] = null;
                $html = $twig->render('system-head-'.$route,$ctx);
                $expected = in_array($route,['checkout','login'],true) ? 'NOINDEX,NOFOLLOW' : 'NOINDEX,FOLLOW';
                check(str_replace(' ','',meta($html,'robots')) === $expected && canonical($html) !== null,'Incorrect system head');
            } finally {
                $templateService->template = $previous;
            }
        });
    }
    foreach (['de','en','fr','it','es'] as $language) {
        foreach (['hits','empty','filter','sort','fragment','category'] as $mode) {
            test("headings/$language/$mode", function () use ($twig,$base,$request,$templateService,$language,$mode,$searchLabels) {
                $previous = $templateService->template;
                try {
                    $templateService->template = $mode === 'category' ? 'tpl.category.item' : 'tpl.search';
                    $ctx = localizedContext($base,$language);
                    $ctx['isSearch'] = $mode !== 'category';
                    $ctx['searchString'] = 'Probe';
                    $ctx['itemCountTotal'] = $mode === 'empty' ? 0 : 12;
                    if ($ctx['isSearch']) $ctx['category'] = null;
                    $request->params = ['filter'=>['facets'=>'3'],'sort'=>['sorting'=>'item.score'],'fragment'=>['quickViewItem'=>'true']][$mode] ?? [];
                    $html = $twig->render('actual-regular',$ctx);
                    preg_match_all('~<h1\b[^>]*>(.*?)</h1>~si',$html,$headings);
                    check(count($headings[1]) === ($mode === 'fragment' ? 0 : 1),'Unexpected number of headings');
                    if ($ctx['isSearch'] && $mode !== 'fragment') {
                        $expected = $searchLabels[$language] . ' "Probe" (' . $ctx['itemCountTotal'] . ')';
                        check(html_entity_decode(trim($headings[1][0])) === $expected,'Wrong localized search heading');
                    }
                } finally {
                    $templateService->template = $previous;
                    $request->params = [];
                }
            });
        }
    }
    foreach ([
        ['tpl.home', [], 'home'], ['tpl.home.category', [], 'home'],
        ['tpl.category.content', ['category-home'], 'home'],
        ['tpl.basket', [], 'basket'], ['tpl.category.content', ['category-basket'], 'basket'],
        ['tpl.category.content', ['category-checkout'], null], ['tpl.category.item', [], null],
    ] as [$template,$classes,$layout]) {
        test('critical-layout/'.$template.'/'.implode('-',$classes), function () use ($template,$classes,$layout,$base,$twig,$templateService) {
            $previous = $templateService->template;
            try {
                $templateService->template = $template;
                $ctx = $base;
                $ctx['bodyClasses'] = $classes;
                $html = $twig->render('regular',$ctx);
                foreach (['home','basket'] as $name) {
                    check(substr_count($html, '<style data-eg-'.$name.'-layout-critical>') === (int)($layout === $name),'Incorrect early layout for '.$name);
                }
            } finally {
                $templateService->template = $previous;
            }
        });
    }
    test('b2c/canonical-original-query',function () use ($twig,$base,$request,$search,&$builderMode) {
        $builderMode='single'; $search->total=50;
        $ctx=$base; $ctx['category']['details'][0]['metaRobots']='INDEX,FOLLOW';
        foreach (['page=2'=>true,'page=2&sorting=price'=>false,'page=2&facets=3'=>false,'page=1&p%61ge=2'=>false,'page%5B%5D=1&page=2'=>false,'page=2&utm_content=page%3D2'=>true] as $raw=>$indexable) {
            parse_str($raw,$request->params); $request->rawUri='/damen/?'.$raw;
            $html=$twig->render('content',$ctx);
            check(canonical($html)==='https://example.test/damen/'.($indexable?'?page=2':''),'Canonical '.$raw);
            check(meta($html,'robots')===($indexable?'INDEX,FOLLOW':'NOINDEX,FOLLOW'),'Robots '.$raw);
        }
        $request->rawUri=null; $request->params=['page'=>'2']; $ctx['forceNoIndex']=true;
        check(canonical($twig->render('content',$ctx))==='https://example.test/damen/','forced NOINDEX canonical');
        $search->total=1654;
    });

    test('b2c/canonical-block-robots-and-explicit-target',function () use ($twig,$base,$request,$templateService,$urls) {
        $original = $templateService->template;
        try {
            $templateService->template='tpl.category.item';
            $ctx=$base+['currentPage'=>2,'pageMax'=>3,'itemCountTotal'=>25];
            $ctx['category']['details'][0]['metaRobots']='INDEX,FOLLOW';
            $request->params=['page'=>'2'];
            foreach (['NOINDEX,NOFOLLOW'=>false,'INDEX,NOFOLLOW'=>true] as $robots=>$indexable) {
                $html=$twig->render('block-robots',$ctx+['fixtureRobots'=>$robots]);
                check(meta($html,'robots')===$robots && canonical($html)===$urls->base.($indexable?'?page=2':''),'Child robots and canonical');
            }
            $request->rawUri='/damen/?page=1&page=2';
            $urls->configured='https://example.test/kuratiert/?ref=7';
            $html=$twig->render('block-robots',$ctx+['fixtureRobots'=>'INDEX,FOLLOW']);
            check(canonical($html)===$urls->configured && meta($html,'robots')==='NOINDEX,FOLLOW','Explicit canonical on duplicate page');
        } finally { $request->rawUri=null; $urls->configured=null; $templateService->template=$original; }
    });
    foreach ([null,'1','2','69','999','0','-1','abc','',[], '2abc','1.5'] as $raw) {
        test('category/page-' . json_encode($raw), function () use ($raw) {
            $query = $raw === null ? [] : ['page'=>$raw];
            $page = new SeoPage($query,'tpl.category.item',69, is_string($raw) && ctype_digit($raw) ? max(1,(int)$raw) : 1,1654);
            $p = $page->getPagination();
            $valid = in_array($raw,[null,'1','2','69'],true);
            check($p['valid'] === $valid,'Wrong valid range');
            check($p['noindex'] === !$valid,'Wrong indexing for invalid input');
            check($p['outOfRange'] === ($raw === '999'),'Range validity confused with syntax');
            return $p;
        });
    }
    foreach ([1,0,null] as $max) {
        test('category/bounds-' . json_encode($max),function () use ($max) {
            $page = new SeoPage(['page'=>'2'],'tpl.category.item',$max,2,$max === 0 ? 0 : null);
            $p = $page->getPagination(); check(!$p['valid'] && $p['noindex'],'Follow-up must not be indexed');
            check($p['outOfRange'] === ($max !== null),'Unknown max incorrectly treated as one');
            check($p['pageMax'] === $max,'Known zero/one and unknown must remain distinct'); return $p;
        });
    }
    foreach (['INDEX,FOLLOW','NOINDEX,FOLLOW','NOINDEX,NOFOLLOW','NOFOLLOW','ALL','NONE','NOINDEX_NOFOLLOW','NOINDEX,NOFOLLOW,NOARCHIVE'] as $robots) {
        foreach ([false,true] as $force) {
            test('robots/' . $robots . '/force-' . (int)$force,function () use ($robots,$force) {
                $page = new SeoPage(['page'=>'2'],'tpl.category.item',69,2,1654);
                $result = $page->getRobots('', $robots,$force);
                $noindex = $force || strpos($robots,'NOINDEX') !== false || $robots === 'NONE';
                $nofollow = strpos($robots,'NOFOLLOW') !== false || $robots === 'NONE';
                check(strpos($result,$noindex ? 'NOINDEX' : 'INDEX') === 0,'Index restriction lost');
                check(in_array($nofollow ? 'NOFOLLOW' : 'FOLLOW',explode(',',$result),true),'Follow restriction lost');
                if (strpos($robots,'NOARCHIVE') !== false) { check(strpos($result,'NOARCHIVE') !== false,'Additional restriction lost'); }
                return $result;
            });
        }
    }
    foreach (['query','currency','quickViewItem','quickviewitem','facets','sorting','Sorting','priceMin','priceMax','PriceMin','mypriceMin','notsorting','myquery','utm_source','gclid'] as $key) {
        foreach (['x','',[]] as $value) {
            test('parameters/' . $key . '/' . json_encode($value),function () use ($key,$value) {
                $page = new SeoPage([$key=>$value],'tpl.category.item',69,1,1654);
                $hard = in_array(strtolower($key),['query','currency','quickviewitem','facets','sorting','pricemin','pricemax'],true);
                check($page->getRobots('','INDEX,FOLLOW',false) === ($hard ? 'NOINDEX,FOLLOW' : 'INDEX,FOLLOW'),'Parameter-key recognition failed');
            });
        }
    }
    test('parameters/embedded-query-value',function () {
        check((new SeoPage(['utm_source'=>'sorting=x&currency=USD'],'tpl.category.item',69,1,1654))->getRobots('','ALL',false) === 'INDEX,FOLLOW','Substring in value matched');
    });

    foreach ([[],['page'=>'1'],['page'=>'2'],['page'=>'69'],['page'=>'999'],['page'=>'abc'],['page'=>'0'],['page'=>'-1'],['page'=>''],['page'=>[]],['page'=>'2','facets'=>'4,9','priceMin'=>'20','priceMax'=>'60','sorting'=>'texts.name1_asc']] as $params) {
        test('content/render-' . json_encode($params),function () use ($params,&$builderCalls,&$builderMode,$twig,$request,$search,$context,$categoryService,$base) {
            $request->params = $params; $search->calls = []; $builderCalls = 0; $builderMode = 'single';
            $before = serialize($context); $route = serialize($categoryService->getCurrentCategory());
            $html = $twig->render('content', $base);
            check($builderCalls === 1 && count($search->calls) === 1,'Builder/query executed more than once');
            check(serialize($context) === $before && serialize($categoryService->getCurrentCategory()) === $route,'Routed category changed');
            $page = $params['page'] ?? '1';
            $valid = in_array($page,['1','2','69'],true);
            $suffix = $valid && (int)$page > 1 ? '?page=' . $page : '';
            check(canonical($html) === 'https://example.test/damen/','NOINDEX content canonical');
            check(meta($html,'robots') === 'NOINDEX,NOFOLLOW','Route robots not preserved');
            check(preg_match('~<title>Route title' . ($suffix ? ' – Seite ' . $page : '') . '</title>~u',$html) === 1,'Source category title leaked or invalid suffix');
            check(meta($html,'description') === 'Route description' . ($suffix ? ' – Seite ' . $page : ''),'Source category description leaked');
            check(meta($html,'keywords') === 'Route keywords','Source category keywords leaked');
            check(strpos($html,'<section class="builder">') > strpos($html,'</head>'),'Builder HTML emitted before head');
            if (isset($params['facets'])) {
                $options = $search->calls[0]['itemList'];
                check($options['facets'] === [7,4,9] && $options['priceMin'] === 20.0 && $options['priceMax'] === 60.0,'Filters changed');
                check($options['sorting'] === 'texts.name1_asc','Sorting changed');
            }
            return ['queries'=>count($search->calls),'builderCalls'=>$builderCalls,'canonical'=>canonical($html)];
        });
    }
    foreach (['none','empty','multiple','simple'] as $mode) {
        foreach (['1','2','999'] as $number) {
            test('content/' . $mode . '/' . $number,function () use ($mode,$number,&$builderMode,&$builderCalls,$twig,$request,$search,$base) {
                $builderMode = $mode; $builderCalls = 0; $search->calls = []; $request->params = ['page'=>$number];
                $html = $twig->render('content',$base);
                check($builderCalls === 1,'Builder not once');
                check(count($search->calls) === (in_array($mode,['none','empty'],true) ? 0 : 2),'Unexpected query count');
                    check(canonical($html) === 'https://example.test/damen/','NOINDEX list received a page canonical');
                return ['queries'=>count($search->calls),'canonical'=>canonical($html)];
            });
        }
    }
    test('content/rendered-widget-seo', function () use (&$builderMode,&$builderSpecs,$request,$extension,$search,$twig,$base) {
        $oldMode = $builderMode;
        $oldSpecs = $builderSpecs;
        try {
            $search->total = 3241;
            $search->documentTotal = 3272;
            $builderMode = 'rendered-widget';
            $base['category']['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
            foreach ([2=>true,273=>true,274=>false] as $page=>$indexable) {
                $request->params = ['page'=>(string)$page];
                // The widget result exists before the outer layout requests its HTML.
                $builderSpecs['html'] = $extension->getItemsFromCat(60,12,[]);
                $search->calls = $search->boundaryCalls = [];
                $html = $twig->render('content',$base);
                check(canonical($html) === 'https://example.test/damen/' . ($indexable ? '?page='.$page : ''), 'Rendered widget lost its canonical on page '.$page);
                check(meta($html,'robots') === ($indexable?'INDEX,FOLLOW':'NOINDEX,FOLLOW'), 'Wrong robots on page '.$page);
                check(!$search->calls && !$search->boundaryCalls, 'Rendering cached widget triggered a search');
            }
        } finally {
            $builderMode = $oldMode;
            $builderSpecs = $oldSpecs;
            $search->total = 1654; $search->documentTotal = null;
            $request->params = [];
        }
    });
    test('capture/exception-and-repeated-prepare',function () use (&$builderMode,&$builderCalls,$extension,$request,$twig) {
        $request->params = ['page'=>'2']; $page = $extension->createSeoPage('tpl.category.content'); $builderMode = 'throw';
        try { $extension->prepareSeoContent($page,100); throw new RuntimeException('Expected render exception'); }
        catch (Twig\Error\RuntimeError $error) { check(strpos($error->getMessage(),'fixture builder failure') !== false,'Unexpected exception'); }
        $builderMode = 'single'; $extension->getItemsFromCat(200,24,[]);
        check(!$page->getPagination()['hasList'],'Capture survived failed render');
        $newPage = $extension->createSeoPage('tpl.category.content'); $builderCalls = 0;
        $extension->prepareSeoContent($newPage,100); $extension->prepareSeoContent($newPage,100);
        check($builderCalls === 1 && $newPage->getPagination()['valid'],'Repeated prepare queried again');
    });
    foreach ([0,-1,'abc','',12,24,48] as $size) {
        test('list/effective-size-' . json_encode($size),function () use ($size,$extension,$renderer,$request,$search) {
            $request->params = ['page'=>'2']; $search->calls = [];
            $extension->getItemsFromCat(200,$size,[]);
            check($renderer->params['itemCountPage'] === 12 && $renderer->params['pageMax'] === 138.0,'Validated size not used');
            check($renderer->params['query']['items'] === 12 && $renderer->params['pageLimit'] === 138.0,'List size/page limit inconsistent');
            check(count($search->calls) === 1,'Extra query for count');
        });
    }
    test('list/reject-changed-effective-size',function () use ($extension,$search) {
        $search->calls = []; SearchOptions::$overrideSize = 24;
        try { $extension->getItemsFromCat(200,24,[]); throw new RuntimeException('Expected invalid configuration failure'); }
        catch (UnexpectedValueException $error) { check(count($search->calls) === 0,'Queried a size other than twelve'); }
        finally { SearchOptions::$overrideSize = null; }
    });
    test('twig/shopbuilder-page-size',function () use ($twig,$request,$search) {
        $request->params = ['page'=>'2','items'=>'48'];
        foreach (['getItemsFromCat(60, 12, [])','getItemsFromCat(60, 24, [])',
                  'getItemsFromCat(60, 48, [])','getItemsFromCat(60, 24, [], [0,2000])'] as $call) {
            $search->calls = [];
            $html = $twig->createTemplate('{{ ' . $call . ' | raw }}')->render([]);
            preg_match_all('~class="item">(\d+)</span>~',$html,$cards);
            check(array_map('intval',$cards[1]) === range(13,24),'Widget delivered unexpected second page: ' . $call);
            check(count($search->calls) === 1,'Extra query for widget: ' . $call);
        }
        $search->calls = [];
        $simple = $twig->createTemplate('{{ getItemsFromCatSimple(60, 24, []) | raw }}')->render([]);
        check(strpos($simple,implode(',',range(1,24)) . ',') !== false,'Simple widget lost its configured size');
        check(count($search->calls) === 1 && $search->calls[0]['itemList']['itemsPerPage'] === 24,'Simple widget query changed');
    });
    test('list/validated-page',function () use ($extension,$renderer,$request) {
        $request->params = ['page'=>'2']; SearchOptions::$overridePage = 3;
        try { $extension->getItemsFromCat(200,24,[]); check($renderer->params['currentPage'] === 3,'Raw request reused instead of validated page'); }
        finally { SearchOptions::$overridePage = null; }
    });
    foreach ([0,10,1654] as $total) {
        test('list/total-' . $total,function () use ($total,$extension,$renderer,$search,$request) {
            $search->total = $total; $request->params = ['page'=>'2'];
            try { $extension->getItemsFromCat(200,24,[]); check($renderer->params['pageMax'] === ceil($total/12),'Incorrect page maximum'); }
            finally { $search->total = 1654; }
        });
    }
    // The search total and the documents available by offset can disagree in Plenty.
    // Check the actual ShopBuilder/Twig path including the link consumed by Infinite Scroll.
    foreach ([
        [1315,1323,110,12,110,111,true], [1315,1323,111,3,111,111,true], [1315,1323,112,0,110,110,false],
        [1314,1322,111,2,111,111,true],
        [1271,1277,107,5,107,107,true], [1271,1277,999,0,106,106,false], [1654,1658,139,2,139,139,true],
        [36,13,3,0,3,3,false], [24,24,2,12,2,2,true], [24,25,2,12,2,3,true], [3240,3276,270,12,270,273,true], [3241,3276,273,12,273,273,true], [3241,3276,274,0,273,273,false], [23,23,2,11,2,2,true],
        [2468,2460,205,12,206,206,true], [2468,2460,206,0,206,206,false],
        [0,13,1,12,1,2,true], [0,13,2,1,2,2,true], [0,0,2,0,0,0,false],
        [12,49,4,12,4,5,true], [12,49,5,1,5,5,true],
    ] as [$total,$available,$number,$docs,$seoMax,$navMax,$valid]) {
        test("documents/$total/$available/$number",function () use ($total,$available,$number,$docs,$seoMax,$navMax,$valid,$search,$renderer,$request,$twig,$base,&$builderCalls,&$builderMode) {
            $search->total = $total; $search->documentTotal = $available;
            $request->params = ['page'=>(string)$number]; $builderMode = 'single'; $search->calls = []; $builderCalls = 0;
            $ctx = $base; $ctx['category']['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
            try {
                $html = $twig->render('content',$ctx);
                $state = json_decode(meta($html,'o46-pagination'),true);
                $suffix = $valid && $number > 1 ? ' – Seite ' . $number : '';
                check(count($renderer->params['itemList']) === $docs && substr_count($html,'<article ') === $docs,'Wrong number of rendered documents');
                check($renderer->params['itemCountTotal'] === $available && (int)$renderer->params['pageMax'] === (int)ceil($available / 12),'Total or navigation bound changed unexpectedly');
                check($state['valid'] === $valid && $state['pageMax'] === (int)ceil($available / 12),'SEO bound conflicts with the page evidence');
                check(meta($html,'robots') === ($valid ? 'INDEX,FOLLOW' : 'NOINDEX,FOLLOW'),'Incorrect indexing decision');
                check(canonical($html) === 'https://example.test/damen/' . ($suffix ? '?page=' . $number : ''),'Incorrect canonical');
                check(strpos($html,'<title>Route title' . $suffix . '</title>') !== false && meta($html,'description') === 'Route description' . $suffix,'Incorrect page suffix');
                check((strpos($html,'class="pagination__next"') !== false) === ($number < ceil($available / 12)),'Incorrect Infinite Scroll next link');
                check($builderCalls === 1 && count($search->calls) === 1,'Extra render or query to discover a page');
                return $state;
            } finally { $search->total = 1654; $search->documentTotal = null; }
        });
    }
    foreach (['NOINDEX,FOLLOW','NOFOLLOW','force','facets','quickView','wrong-effective','invalid','ambiguous','simple-only'] as $mode) {
        test('documents/restriction-' . $mode,function () use ($mode,$search,$request,$twig,$base,&$builderMode) {
            $search->total = 47; $search->documentTotal = 75;
            $request->params = ['page'=>$mode === 'invalid' ? 'abc' : '3'];
            if ($mode === 'facets') { $request->params['facets'] = '7'; }
            if ($mode === 'quickView') { $request->params['quickViewItem'] = 'true'; }
            if ($mode === 'wrong-effective') { SearchOptions::$overridePage = 2; }
            $builderMode = ['ambiguous'=>'multiple','simple-only'=>'simple-only'][$mode] ?? 'single';
            $ctx = $base; $ctx['forceNoIndex'] = $mode === 'force';
            $ctx['category']['details'][0]['metaRobots'] = in_array($mode,['NOINDEX,FOLLOW','NOFOLLOW'],true) ? $mode : 'ALL';
            try {
                $html = $twig->render('content',$ctx);
                $valid = !in_array($mode,['wrong-effective','invalid','ambiguous','simple-only'],true);
                check(json_decode(meta($html,'o46-pagination'),true)['valid'] === $valid,'Unproven page accepted');
                check(meta($html,'robots') === ($mode === 'NOFOLLOW' ? 'INDEX,NOFOLLOW' : 'NOINDEX,FOLLOW'),'Restrictions lost on observed page');
                check(canonical($html) === 'https://example.test/damen/' . ($valid && in_array($mode,['NOFOLLOW','quickView'],true) ? '?page=3' : ''),'Wrong canonical with restrictions');
            } finally { $search->total = 1654; $search->documentTotal = null; SearchOptions::$overridePage = null; $builderMode = 'single'; }
        });
    }
    test('documents/missing-collection-keeps-reported-bound',function () use ($request,$twig,$base,$search) {
        $request->params = ['page'=>'2']; $search->omitDocuments = true;
        try {
            $ctx = $base; $ctx['category']['details'][0]['metaRobots'] = 'ALL';
            $html = $twig->render('content',$ctx);
            check(meta($html,'robots') === 'INDEX,FOLLOW' && canonical($html) === 'https://example.test/damen/?page=2','Missing evidence treated as a confirmed empty page');
        } finally { $search->omitDocuments = false; }
    });
    test('list/sort-label-follows-validated-query',function () use ($request,$twig,$base,$search) {
        $request->params = ['sorting'=>'texts.name1_asc']; $search->calls = [];
        $html = $twig->render('content',$base);
        check($search->calls[0]['itemList']['sorting'] === 'texts.name1_asc','Search sort changed');
        check(strpos($html,'<span class="sort-val">Name</span>') !== false,'Dropdown displays wrong active sort');
        check(strpos($html,'data-eg-sortval="variation.createdAt_desc"') !== false,'Default sort cannot be selected again');
    });
    foreach (['17,19x,0,17,19', ['17',['8'],'19x',17,19], []] as $rawFacets) {
        test('list/facet-input-' . json_encode($rawFacets), function () use ($rawFacets,$request,$extension,$search) {
            $request->params = ['facets'=>$rawFacets];
            $search->calls = [];
            $extension->getItemsFromCat(200, 24, [7]);
            $expected = $rawFacets === [] ? [7] : [7,17,19];
            check(count($search->calls) === 1 && $search->calls[0]['itemList']['facets'] === $expected, 'Incorrect IDs sent to category search');
            $visible = $extension->getFacetView([], [7])['selected'];
            check($visible === ($rawFacets === [] ? ['7'] : ['17','19','7']), 'Active facet IDs disagree with search');
        });
    }
    test('list/structured-sort-uses-configured-default', function () use ($request,$extension,$renderer,$search) {
        $request->params = ['sorting'=>['price']];
        $search->calls = [];
        $extension->getItemsFromCat(200, 24, []);
        check(count($search->calls) === 1 && $search->calls[0]['itemList']['sorting'] === 'variation.createdAt_desc',
            'A structured sort key reached the Ceres validator');
        check($renderer->params['itemSorting'] === '', 'Structured sort data leaked into the render context');
    });
    foreach ([null, '', 'false', 'true'] as $flag) {
        test('list/fragment-factories-' . json_encode($flag),function () use ($flag,$request,$extension,$renderer,$search) {
            $request->params = ['page'=>'2','sorting'=>'texts.name1_asc','facets'=>'17,19','priceMin'=>'8','priceMax'=>'21'];
            if ($flag !== null) { $request->params['quickViewItem'] = $flag; }
            $search->calls = [];
            SearchOptions::$menuReads = 0;
            $html = $extension->getItemsFromCat(200,24,[7]);
            $fragment = $flag === 'true';
            $expectedKeys = $fragment ? ['itemList'] : ['facets','itemList'];
            check(count($search->calls) === 1 && array_keys($search->calls[0]) === $expectedKeys,'Wrong searches for fragment flag');
            check(SearchOptions::$menuReads === ($fragment ? 0 : 1),'Wrong explicit sort menu preparations');
            $options = $search->calls[0]['itemList'];
            check($options === ['page'=>2,'itemsPerPage'=>12,'sorting'=>'texts.name1_asc','facets'=>[7,17,19],'categoryId'=>200,'priceMin'=>8.0,'priceMax'=>21.0],'Changed filtered category request');
            $fullParams = $renderer->params;
            // Render the same fragment with the full UI data the old helper always passed.
            $fullParams['facets'] = [['id'=>3, 'name'=>'Marke', 'values'=>[['id'=>17,'name'=>'A','count'=>5]]]];
            $fullParams['searchOptions'] = SearchOptions::get(SearchOptions::SCOPE_CATEGORY);
            if ($fragment) {
                check($html === $renderer->render('GuardiansBasic::Category.Item.CategoryItemContent',$fullParams),'Fragment output depends on omitted data');
            }
            preg_match_all('~class="item">(\d+)</span>~',$html,$cards);
            check(array_map('intval',$cards[1]) === range(13,24),'Wrong fragment cards');
            check(strpos($html,'class="pagination__next"') !== false,'Lost next page');
            return ['factoryKeys'=>$expectedKeys,'cards'=>24,'htmlSha256'=>hash('sha256',$html)];
        });
    }
    test('basket/legacy-helper',function () use ($extension,&$services) {
        $basket = new class {
            public $reads = 0;
            public function getBasketForTemplate() { return ['read'=>++$this->reads]; }
        };
        $services[IO\Services\BasketService::class] = $basket;
        try {
            check($extension->restGetContent('data:,unused') === ['read'=>1], 'Wrong first basket');
            check($extension->restGetContent('') === ['read'=>2], 'Basket was reused');
        } finally {
            unset($services[IO\Services\BasketService::class]);
        }
    });
    foreach (['1','2','69'] as $page) {
        test('html/baseline-page-' . $page,function () use ($page,$extension,$baselineExtension,$request,$context,$search) {
            $request->params = ['page'=>$page];
            $before = serialize($context);
            $search->calls = [];
            $current = $extension->getItemsFromCat(200,24,[7],[10,80]);
            $currentQueries = $search->calls;
            check(serialize($context) === $before,'Context altered by current list render');
            $search->calls = [];
            try { $legacy = $baselineExtension->getItemsFromCat(200,12,[7],[10,80]); }
            finally { foreach (get_object_vars(unserialize($before)) as $key=>$value) { $context->$key = $value; } }
            check($current === $legacy,'List HTML, structure or item order changed');
            check($currentQueries == $search->calls && count($search->calls) === 1,'Query options or count changed');
            check(serialize($context) === $before,'Context altered by list render');
            return ['bytes'=>strlen($current),'sha256'=>hash('sha256',$current),'identical'=>true];
        });
    }
    test('html/simple-baseline-and-context',function () use ($extension,$baselineExtension,$context,$search) {
        $before = serialize($context); $search->calls = [];
        $current = $extension->getItemsFromCatSimple(200,4,[7],[10,80]); $currentQueries = $search->calls;
        check(serialize($context) === $before,'Simple widget changed context'); $search->calls = [];
        try { $legacy = $baselineExtension->getItemsFromCatSimple(200,4,[7],[10,80]); }
        finally { foreach (get_object_vars(unserialize($before)) as $key=>$value) { $context->$key = $value; } }
        check($current === $legacy && $currentQueries === $search->calls && count($search->calls) === 1,'Simple widget output/query changed');
    });
    foreach ([69,1,0,null] as $max) {
        foreach (['1','2','69','999'] as $number) {
            test('regular-render/' . json_encode($max) . '/' . $number,function () use ($max,$number,$request,$twig,$base,$templateService) {
                $request->params = ['page'=>$number]; $templateService->template = 'tpl.category.item';
                $category = $base['category']; $category['details'][0]['metaRobots'] = 'ALL';
                $ctx = array_replace($base,['category'=>$category,'pageMax'=>$max,'currentPage'=>(int)$number,'itemCountTotal'=>$max === 0 ? 0 : null]);
                try {
                    $html = $twig->render('regular-head',$ctx);
                    $valid = $max !== null && (int)$number <= $max;
                    check(meta($html,'robots') === ($valid || ($number === '1' && ($max === null || $max === 0)) ? 'INDEX,FOLLOW' : 'NOINDEX,FOLLOW'),'Incorrect regular robots');
                    check(canonical($html) === 'https://example.test/damen/' . ($valid && $number !== '1' ? '?page=' . $number : ''),'Incorrect regular canonical');
                    if (!$valid) { check(strpos($html,'rel="prev"') === false && strpos($html,'rel="next"') === false,'Invalid page has prev/next'); }
                } finally { $templateService->template = 'tpl.category.content'; }
            });
        }
    }
    test('content/alternate-layout',function () use ($twig,$request,$base,&$builderCalls,&$builderMode,$search) {
        $twig->getLoader()->setTemplate('alternate-content',str_replace("{% extends getPartial('page-design') %}",'{% extends "alternate-layout" %}',readView('Category/Content/CategoryContent')));
        $request->params = ['page'=>'2']; $builderCalls = 0; $builderMode = 'single'; $search->calls = [];
        $html = $twig->render('alternate-content',$base);
        check($builderCalls === 1 && count($search->calls) === 1,'Alternate layout duplicated/dropped list');
        check(strpos($html,'<section class="builder">') !== false,'Alternate layout lost content');
        check(canonical($html) === 'https://example.test/damen/','Unprepared alternate layout invents page canonical');
    });
    test('robots/stronger-category-with-block',function () {
        $page = new SeoPage(['sorting'=>'x'],'tpl.category.item',69,1,1654);
        check($page->getRobots('ALL','NOINDEX,NOFOLLOW,NOARCHIVE,NOSNIPPET',true) === 'NOINDEX,NOFOLLOW,NOARCHIVE,NOSNIPPET','Stricter directives lost');
    });
    test('robots/valued-directives-preserved',function () {
        $page = new SeoPage(['page'=>'2'],'tpl.category.item',69,2,1654);
        $robots = $page->getRobots('INDEX,FOLLOW,MAX-SNIPPET:100','NOINDEX,NOFOLLOW,MAX-SNIPPET:0,UNAVAILABLE_AFTER: 25 JUN 2020 15:00:00 PST',false);
        check(strpos($robots,'NOINDEX,NOFOLLOW') === 0,'Valued directive parsing lost restrictions');
        check(strpos($robots,'MAX-SNIPPET:0') !== false && strpos($robots,'MAX-SNIPPET:100') !== false,'Configured directive values lost');
        check(strpos($robots,'UNAVAILABLE_AFTER: 25 JUN 2020 15:00:00 PST') !== false,'Valued directive was split');
    });
    foreach ([['1',12,true],['2',12,false],['1',0,false],['2',0,false],['2',1654,true],['138',1654,true],['999',1654,false],['abc',1654,false]] as [$number,$total,$valid]) {
        test('content/indexable-route/' . $number . '/' . $total,function () use ($number,$total,$valid,$twig,$request,$base,$search,&$builderMode) {
            $request->params = ['page'=>$number]; $search->total = $total; $builderMode = 'single';
            $category = $base['category']; $category['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
            try {
                $html = $twig->render('content',array_replace($base,['category'=>$category]));
                check(meta($html,'robots') === ($valid ? 'INDEX,FOLLOW' : 'NOINDEX,FOLLOW'),'Embedded list bounds not enforced');
                check(canonical($html) === 'https://example.test/damen/' . ($valid && (int)$number > 1 ? '?page=' . $number : ''),'Embedded list has wrong canonical');
            } finally { $search->total = 1654; }
        });
    }
    test('content/ambiguous-indexable-route',function () use ($twig,$request,$base,&$builderMode) {
        $request->params = ['page'=>'2']; $builderMode = 'multiple';
        $category = $base['category']; $category['details'][0]['metaRobots'] = 'ALL';
        try {
            $html = $twig->render('content',array_replace($base,['category'=>$category]));
            check(meta($html,'robots') === 'NOINDEX,FOLLOW' && canonical($html) === 'https://example.test/damen/','Ambiguous lists chose a main list');
        } finally { $builderMode = 'single'; }
    });
    foreach (['de_DE'=>'Seite','en_GB'=>'Page','fr_FR'=>'Page','it_IT'=>'Pagina','es_ES'=>'Página'] as $locale=>$label) {
        test('language/metadata-' . $locale,function () use ($locale,$label,$request,$twig,$base,&$builderMode) {
            $request->params = ['page'=>'2']; $builderMode = 'single';
            $ctx = localizedContext($base,substr($locale,0,2));
            $ctx['category']['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
            $html = $twig->render('content',$ctx + ['locale'=>$locale]);
            check(strpos($html,'<title>Route title – ' . $label . ' 2</title>') !== false,'Wrong localized title');
            check(meta($html,'description') === 'Route description – ' . $label . ' 2','Wrong localized description');
            foreach (['de','en','fr','it','es'] as $language) {
                $url = 'https://example.test/' . ($language === 'de' ? '' : $language . '/') . 'damen/';
                check(strpos($html,'lang="' . $language . '" content="' . $url . '"') !== false,'Missing language route');
            }
        });
    }
    // The preview supplies the active shop language, but renders a blank locale in the head.
    foreach (['de'=>'Seite','en'=>'Page','fr'=>'Page','it'=>'Pagina','es'=>'Página'] as $language=>$label) {
        foreach (['content'=>'tpl.category.content','regular-head'=>'tpl.category.item'] as $view=>$type) {
            $lastPage = $view === 'content' ? 138 : 69;
            $pages = $view === 'content' ? [1,2,52,103,104,$lastPage,$lastPage+1,204] : [1,2,$lastPage,$lastPage+1];
            foreach ($pages as $page) {
                test("language/server-html/$view/$language/$page", function () use ($view,$type,$page,$lastPage,$language,$label,$base,$twig,$request,$templateService) {
                    $request->params = $page === 1 ? [] : ['page'=>(string)$page];
                    $templateService->template = $type;
                    $ctx = localizedContext($base,$language) + ['currentPage'=>$page,'pageMax'=>69,'itemCountTotal'=>1654];
                    try {
                        $html = $twig->render($view,$ctx);
                        $suffix = $page > 1 && $page <= $lastPage ? ' – ' . $label . ' ' . $page : '';
                        check(strpos($html,'<title>Route title' . $suffix . '</title>') !== false,'Wrong server title');
                        check(meta($html,'description') === 'Route description' . $suffix,'Wrong server description');
                        check(canonical($html) === 'https://example.test/damen/','NOINDEX language page changed canonical');
                        check(meta($html,'robots') === 'NOINDEX,NOFOLLOW','Language changed category directives');
                    } finally { $templateService->template = 'tpl.category.content'; }
                });
            }
        }
    }
    foreach ([
        ['de','en_US','Seite'], [' IT-ch ','de_DE','Pagina'], ['ES_419','','Página'],
        ['', ' it_IT ', 'Pagina'], [null,'de-AT','Seite'], [null,null,'Page'], ['nl','de_DE','Page']
    ] as [$language,$locale,$label]) {
        test('language/source-' . json_encode([$language,$locale]), function () use ($language,$locale,$label,$base,$twig,$request) {
            $request->params = ['page'=>'2'];
            $ctx = localizedContext($base,$language) + ['locale'=>$locale,'seoPage'=>new SeoPage(['page'=>'2'],'tpl.category.item',2,2,48)];
            $html = $twig->render('metadata',$ctx);
            check(strpos($html,'<title>Route title – ' . $label . ' 2</title>') !== false,'Wrong language priority or normalization');
            check(meta($html,'description') === 'Route description – ' . $label . ' 2','Wrong description language');
        });
    }
    test('metadata/force-with-nofollow',function () use ($twig,$request,$base) {
        $request->params = ['page'=>'2'];
        $html = $twig->render('regular',array_replace($base,['forceNoIndex'=>true]));
        check(meta($html,'robots') === 'NOINDEX,NOFOLLOW','Forced noindex discarded nofollow');
        $home = $twig->render('home',array_replace($base,['forceNoIndex'=>true]));
        check(meta($home,'robots') === 'NOINDEX,NOFOLLOW','Homepage discarded configured nofollow');
    });
    test('metadata/quickview',function () use ($twig,$request,$base,&$builderCalls,&$builderMode,$search) {
        $request->params = ['page'=>'2','quickViewItem'=>'true']; $builderMode = 'single'; $builderCalls = 0; $search->calls = [];
        $html = $twig->render('content',$base);
        check(meta($html,'robots') === 'NOINDEX,NOFOLLOW','Quickview restriction lost');
        check($builderCalls === 1 && count($search->calls) === 1,'Quickview executes extra queries');
    });
    // These are actual per-page ShopBuilder function arguments, not theme URL mappings.
    foreach ([[[60,12,[]],25],[[60,24,[]],25],[[60,48,[]],25],[[60,24,[],[0,2000]],19],
              [[60,24,[727]],73],[[900,12,[5,77]],25],[[42,48,[12]],112],[[999,7,[]],15]] as [$arguments,$filteredTotal]) {
        $last = (int)ceil($filteredTotal / 12);
        foreach (array_unique([1,2,$last,$last+1]) as $number) {
            test('shopbuilder-arguments/' . json_encode($arguments) . '/page-' . $number,function () use ($arguments,$filteredTotal,$number,$last,$request,$twig,$base,$search,$renderer,&$builderMode,&$builderSpecs,&$builderCalls) {
                $builderMode = 'configured'; $builderSpecs = [$arguments]; $builderCalls = 0; $search->calls = [];
                $request->params = ['page'=>(string)$number];
                $search->totalResolver = function ($options, $key) use ($arguments,$filteredTotal) {
                    check($options['categoryId'] === $arguments[0], 'ShopBuilder source category changed');
                    check($key === 'boundary' ? ($options['itemsPerPage'] >= 12 && $options['itemsPerPage'] <= 49) : $options['itemsPerPage'] === 12, 'Unexpected document or boundary window');
                    check($options['facets'] === $arguments[2], 'ShopBuilder facets lost before filtered total');
                    check([$options['priceMin'],$options['priceMax']] === array_map('floatval',$arguments[3] ?? [0,0]), 'Widget price range changed');
                    return $filteredTotal;
                };
                $category = $base['category']; $category['details'][0]['metaRobots'] = 'ALL';
                try {
                    $html = $twig->render('content',array_replace($base,['category'=>$category]));
                    check($builderCalls === 1 && count($search->calls) === 1,'Actual ShopBuilder function queried twice');
                    check($renderer->params['itemCountTotal'] === $filteredTotal && $renderer->params['pageMax'] === (float)$last,'SEO total not from filtered item query');
                    check($renderer->params['itemCountPage'] === 12, 'List and query disagree on page size');
                    $valid = $number <= $last;
                    $firstId = ($number - 1) * 12 + 1;
                    $expectedIds = $valid ? range($firstId,min($firstId + 11,$filteredTotal)) : [];
                    $actualIds = array_map(static function ($item) { return $item['data']['variation']['id']; },$renderer->params['itemList']);
                    check($actualIds === $expectedIds, 'Unexpected article order or gap at the page boundary');
                    check(meta($html,'robots') === ($valid ? 'INDEX,FOLLOW' : 'NOINDEX,FOLLOW'),'SEO used a different filtered total/page size');
                    check(canonical($html) === 'https://example.test/damen/' . ($valid && $number > 1 ? '?page=' . $number : ''),'Wrong canonical for actual ShopBuilder arguments');
                    check(strpos($html,'<title>Route title') !== false,'Source category replaced route metadata');
                    return ['arguments'=>$arguments,'filteredTotal'=>$filteredTotal,'pageMax'=>$last,'queries'=>count($search->calls)];
                } finally { $search->totalResolver = null; $builderMode = 'single'; $builderSpecs = []; }
            });
        }
    }
    foreach (['none','simple-only'] as $mode) {
        test('shopbuilder-arguments/no-paginated-call-' . $mode,function () use ($mode,$request,$extension,$search,&$builderMode,&$builderCalls) {
            $request->params = ['page'=>'2']; $builderMode = $mode; $builderCalls = 0; $search->calls = [];
            $page = $extension->createSeoPage('tpl.category.content');
            try {
                $extension->prepareSeoContent($page,9876);
                $pagination = $page->getPagination();
                check(!$pagination['hasList'] && $pagination['pageMax'] === null && !$pagination['valid'],'Invented pagination without getItemsFromCat');
                check($builderCalls === 1 && count($search->calls) === ($mode === 'none' ? 0 : 1),'Non-paginated content queried unexpectedly');
                check($page->shopBuilderHtml !== '', 'Normal content/simple-only HTML lost');
            } finally { $builderMode = 'single'; }
        });
    }
    foreach (['tpl.category.item','tpl.category.content','tpl.item','tpl.home','tpl.search','tpl.page-not-found'] as $type) {
        foreach ([null,'1','2','999'] as $page) {
            test('metadata/' . $type . '/' . json_encode($page),function () use ($type,$page,$request,$twig,$base,$templateService) {
                $request->params = $page === null ? [] : ['page'=>$page]; $templateService->template = $type;
                $effective = $page === null ? 1 : (int)$page;
                $ctx = array_replace($base,['category'=>null,'pageMax'=>69,'currentPage'=>$effective,'itemCountTotal'=>1654]);
                try {
                    $html = $twig->render($type === 'tpl.home' ? 'home' : ($type === 'tpl.page-not-found' ? 'not-found' : 'regular'),$ctx);
                    $suffix = $type === 'tpl.category.item' && $page === '2' ? '?page=2' : '';
                    check(canonical($html) === 'https://example.test/damen/' . $suffix,'Page canonical on wrong page type');
                    $robots = meta($html,'robots');
                    if (in_array($type,['tpl.home','tpl.search','tpl.page-not-found'],true) || $page === '999' || ($page === '2' && $type !== 'tpl.category.item')) {
                        check(strpos($robots,'NOINDEX') === 0,'Invalid/non-list/search/404 indexed');
                    } else { check($robots === 'INDEX,FOLLOW','Valid page wrongly blocked'); }
                } finally { $templateService->template = 'tpl.category.content'; }
            });
        }
    }
    foreach (['INDEX,FOLLOW','NOINDEX,NOFOLLOW'] as $restriction) {
        test('search/history-robots/' . $restriction, function () use ($twig,$base,$request,$templateService,$restriction) {
            $templateService->template = 'tpl.search';
            $ctx = $base;
            $ctx['category']['details'][0]['metaRobots'] = $restriction;
            try {
                $request->params = ['query'=>'Hose','page'=>'2','sorting'=>'price.asc'];
                $direct = $twig->render('regular-head',$ctx);
                $request->params['quickViewItem'] = 'true';
                $fragment = $twig->render('regular-head',$ctx);
                $pagination = json_decode(meta($fragment,'o46-pagination'),true);
                check(!$pagination['hasList'] && !$pagination['valid'],'Expected search without category bounds');
                check(strpos($direct,'data-o46-history-robots=') === false,'Direct response marked as a fragment');
                check(preg_match('~<meta name="robots"[^>]* data-o46-history-robots="([^"]+)"~',$fragment,$match) === 1,'Missing direct robots in search fragment');
                check($match[1] === meta($direct,'robots') && meta($fragment,'robots') === $match[1],'Search restriction changed');
                $templateService->template = 'tpl.item';
                check(strpos($twig->render('regular-head',$ctx),'data-o46-history-robots=') === false,'Product Quickview marked as a search list');
            } finally { $templateService->template = 'tpl.category.content'; }
        });
    }
    foreach (['INDEX,FOLLOW','NOINDEX,FOLLOW','NOINDEX,NOFOLLOW','ALL',''] as $value) {
        test('metadata/block-robots-' . $value,function () use ($value,$twig,$base,$request) {
            $request->params = ['page'=>'2'];
            $html = $twig->render('block-robots',array_replace($base,['fixtureRobots'=>$value]));
            check(meta($html,'robots') === 'NOINDEX,NOFOLLOW','Block loosened category restriction');
        });
    }
    foreach (['configured','forced','missing','empty-forced'] as $mode) {
        test('canonical/' . $mode,function () use ($mode,$twig,$base,$request,$urls,$templateService) {
            $request->params = ['page'=>'2']; $templateService->template = 'tpl.category.item';
            $ctx = array_replace($base,['pageMax'=>69,'currentPage'=>2,'itemCountTotal'=>1654]);
            if ($mode === 'configured') { $urls->configured = 'https://external.test/special/?key=x&page=5'; }
            if ($mode === 'forced') { $ctx['forcedCanonicalUrl'] = 'https://external.test/forced/?page=7'; }
            if ($mode === 'missing') { $urls->base = null; }
            if ($mode === 'empty-forced') { $ctx['forcedCanonicalUrl'] = '  '; }
            try {
                $html = $twig->render('regular',$ctx);
                $expected = ['configured'=>'https://external.test/special/?key=x&page=5','forced'=>'https://external.test/forced/?page=7','missing'=>null,'empty-forced'=>'https://example.test/damen/'][$mode];
                check(canonical($html) === $expected,'Explicit/missing canonical mishandled');
                if ($mode === 'configured') check(strpos($html,'lang="en" content="https://example.test/en/damen/"') !== false,'Configured SEO canonical replaced the language navigation route');
                if ($mode === 'forced') {
                    check(strpos($html,'rel="alternate"') === false,'Forced canonical SEO policy changed');
                    check(substr_count($html,'name="o46-language-route"') === 5,'Forced canonical lost language navigation');
                }
            } finally { $urls->configured = null; $urls->base = 'https://example.test/damen/'; $templateService->template = 'tpl.category.content'; }
        });
    }
    test('render-isolation/page-filter-language',function () use ($request,$twig,$base,&$builderMode,$search) {
        $builderMode = 'single'; $seen = [];
        foreach ([['2','de_DE',1654],['999','en_GB',10],['1','fr_FR',0],['2','it_IT',50],['1','es_ES',24],['2','de_DE',1654]] as [$page,$locale,$total]) {
            $request->params = ['page'=>$page]; $search->total = $total;
            $ctx = localizedContext($base,substr($locale,0,2));
            $ctx['category']['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
            $html = $twig->render('content',$ctx + ['locale'=>$locale]);
            $seen[] = canonical($html);
            $expected = $page === '2' && $total > 12 ? 'https://example.test/damen/?page=2' : 'https://example.test/damen/';
            check(canonical($html) === $expected,'Data leaked across page/filter/language variants');
        }
        $search->total = 1654; check($seen[0] === $seen[5],'Repeated variant changed');
        return $seen;
    });
    foreach ([null,'1'] as $number) {
        foreach (['INDEX,FOLLOW','NOINDEX,FOLLOW','NOFOLLOW','NOINDEX,NOFOLLOW'] as $directive) {
            foreach ([false,true] as $force) {
                test('empty-category/first-' . json_encode($number) . '/' . $directive . '/force-' . (int)$force,function () use ($number,$directive,$force,$twig,$request,$base,$templateService,$search) {
                    $request->params = $number === null ? [] : ['page'=>$number];
                    $templateService->template = 'tpl.category.item'; $search->calls = [];
                    $category = $base['category']; $category['details'][0]['metaRobots'] = $directive;
                    try {
                        $html = $twig->render('regular-head',array_replace($base,['category'=>$category,'pageMax'=>0,'currentPage'=>1,'itemCountTotal'=>0,'forceNoIndex'=>$force]));
                        $expected = ($force || strpos($directive,'NOINDEX') !== false ? 'NOINDEX' : 'INDEX') . ',' . (strpos($directive,'NOFOLLOW') !== false ? 'NOFOLLOW' : 'FOLLOW');
                        check(meta($html,'robots') === $expected,'Empty first category page changed configured restrictions');
                        check(canonical($html) === 'https://example.test/damen/','Empty category lost base canonical');
                        $state = json_decode(meta($html,'o46-pagination'),true);
                        check($state['pageMax'] === 0 && $state['empty'] && !$state['valid'] && $state['currentPage'] === 1 && !$state['noindex'],'Known empty result became a paginated or blocked first page');
                        check(count($search->calls) === 0,'Robots exception introduced an item query');
                        check(strpos($html,'rel="next"') === false && strpos($html,'rel="prev"') === false,'Empty first category invented pagination');
                        check(strpos($html,'<title>Route title</title>') !== false && meta($html,'description') === 'Route description','Empty category changed the base metadata');
                    } finally { $templateService->template = 'tpl.category.content'; }
                });
            }
        }
    }
    foreach (['2','999','0','-1','abc',''] as $number) {
        test('empty-category/invalid-' . json_encode($number),function () use ($number,$twig,$request,$base,$templateService) {
            $request->params = ['page'=>$number];
            $templateService->template = 'tpl.category.item';
            $ctx = $base + ['pageMax'=>0,'currentPage'=>1,'itemCountTotal'=>0];
            $ctx['category']['details'][0]['metaRobots'] = 'NOFOLLOW';
            try {
                $html = $twig->render('regular-head',$ctx);
                $state = json_decode(meta($html,'o46-pagination'),true);
                check(meta($html,'robots') === 'NOINDEX,NOFOLLOW','Empty category follow-up or invalid input became indexable');
                check($state['pageMax'] === 0 && $state['empty'] && !$state['valid'],'Empty category gained valid follow-up pages');
                check(canonical($html) === 'https://example.test/damen/','Empty category follow-up got its own canonical');
                check(strpos($html,'<title>Route title</title>') !== false && meta($html,'description') === 'Route description','Empty category follow-up received a page suffix');
                check(strpos($html,'rel="next"') === false && strpos($html,'rel="prev"') === false,'Empty category follow-up has pagination links');
            } finally { $templateService->template = 'tpl.category.content'; }
        });
    }
    foreach ([['priceMin'=>0],['priceMax'=>0],['facets'=>'3118'],['sorting'=>''],['query'=>''],['currency'=>'EUR'],['quickViewItem'=>'true']] as $query) {
        test('empty-category/filtered-' . json_encode($query),function () use ($query) {
            $page = new SeoPage($query,'tpl.category.item',0,1,0);
            check($page->getRobots('','INDEX,FOLLOW',false) === 'NOINDEX,FOLLOW','Empty filtered results lost noindex');
        });
    }
    foreach ([null,'1'] as $number) {
        test('empty-category/content-policy-unchanged-' . json_encode($number),function () use ($number) {
            $page = new SeoPage($number === null ? [] : ['page'=>$number],'tpl.category.content');
            $page->addList(1,0,0);
            check($page->getRobots('','INDEX,FOLLOW',false) === 'NOINDEX,FOLLOW','Native-category exception changed embedded-list policy');
        });
    }
    // The native route supplies plausible grid bounds even though the rendered
    // directory is identical on every page. These are observed hreflang paths.
    $brandPaths = ['de'=>'/marken-online-outlet/','en'=>'/en/branded-online-outlet/',
        'fr'=>'/fr/marques/','it'=>'/it/marca/','es'=>'/es/marcas/'];
    foreach ($brandPaths as $lang=>$path) {
        foreach ([null,'1','2','99','0','abc','-1','',[]] as $number) {
            foreach (['INDEX,FOLLOW','NOINDEX,FOLLOW','INDEX,NOFOLLOW','NOINDEX,NOFOLLOW'] as $directive) {
                test('brands/' . $lang . '/' . json_encode($number) . '/' . $directive,function () use ($twig,$base,$request,$search,$templateService,$urls,$lang,$path,$number,$directive) {
                    $oldBase = $urls->base; $oldTemplate = $templateService->template;
                    try {
                        $urls->base = 'https://example.test' . $path; $templateService->template = 'tpl.category.item';
                        $request->params = $number === null ? [] : ['page'=>$number];
                        $ctx = localizedContext($base,$lang);
                        $ctx['category']['id'] = 60; $ctx['category']['details'][0]['metaRobots'] = $directive;
                        $ctx['category']['details'][0]['metaTitle'] = $title = 'Brands ' . $lang;
                        $ctx['category']['details'][0]['metaDescription'] = $description = 'Directory ' . $lang;
                        $ctx['categories'] = [['id'=>60,'details'=>[['lang'=>$lang,'nameUrl'=>basename($path)]],
                            'children'=>[['details'=>[['name'=>'Alpha','nameUrl'=>'alpha']]],
                                         ['details'=>[['name'=>'Beta','nameUrl'=>'beta']]]]]];
                        $ctx['pageMax'] = 100; $ctx['itemCountTotal'] = 2400;
                        $ctx['currentPage'] = in_array($number,['2','99'],true) ? (int)$number : 1;
                        $calls = count($search->calls);
                        $html = $twig->render('actual-regular',$ctx);
                        $state = json_decode(meta($html,'o46-pagination'),true);
                        check(!$state['hasList'] && $state['pageMax'] === null && $state['currentPage'] === 1,'Directory inherited grid bounds');
                        $first = $number === null || $number === '1';
                        $expectedRobots = $first ? $directive : 'NOINDEX,' . explode(',',$directive)[1];
                        check(meta($html,'robots') === $expectedRobots && canonical($html) === $urls->base,'Incorrect directory robots or canonical');
                        check(strpos($html,'<title>' . $title . '</title>') !== false && meta($html,'description') === $description,'Directory metadata gained a suffix');
                        check(strpos($html,'rel="next"') === false && strpos($html,'rel="prev"') === false,'Directory exposes a pager');
                        check(strpos($html,'brandTiles') !== false && strpos($html,'/alpha') !== false && strpos($html,'/beta') !== false,'Actual brand list missing');
                        check(strpos($html,'.brandTiles .col-xl-3 {') !== false,'Directory critical grid CSS missing');
                        check(strpos($html,verifiedThemeAssetPath('css/main.min.css') . '" media="print"') > strpos($html,'.brandTiles .col-xl-3 {'),'Directory stylesheet order or loading mode changed');
                        check(count($search->calls) === $calls,'Directory issued an embedded item query');
                    } finally { $urls->base = $oldBase; $templateService->template = $oldTemplate; }
                });
            }
        }
    }
    test('brands/language-order-and-valid-fragments',function () use ($twig,$base) {
        $children = [];
        foreach (['Mode','1A','Zwei','Mira','2B'] as $name) {
            $children[]=['details'=>[['name'=>$name,'nameUrl'=>strtolower($name)]]];
        }
        $html=$twig->render('GuardiansBasic::Category.Item.CategoryItemBrands',array_replace($base,[
            'categories'=>[['id'=>60,'details'=>[['lang'=>'it','nameUrl'=>'marche']],'children'=>$children]]
        ]));
        check(substr_count($html,'id="M"')===1 && strpos($html,'href="#M"')!==false,'One target for separated entries with the same initial');
        check(strpos($html,'href="#Q"')===false && strpos($html,'href="#Y"')===false,'Absent initial links nowhere');
        check(substr_count($html,'id="end"')===1 && strpos($html,'id="end"')<strpos($html,'/1a'),'Numeric link precedes its products');
    });
    test('assets/brand-first-row',function () use ($twig,$base) {
        $children = [['details'=>[]]];
        foreach (range(1,5) as $i) {
            $children[] = ['details'=>[['name'=>'Brand '.$i,'nameUrl'=>'brand-'.$i,'image2Path'=>'brands/'.$i.'.png']]];
        }
        $html = $twig->render('GuardiansBasic::Category.Item.CategoryItemBrands',array_replace($base,[
            'categories'=>[['id'=>60,'details'=>[['lang'=>'de','nameUrl'=>'marken']],'children'=>$children]]
        ]));
        preg_match_all('~<picture class="xy-150 rounded">(.*?)</picture>~s',$html,$tiles);
        check(count($tiles[1]) === 5,'Missing translated brands');
        foreach ($tiles[1] as $i=>$tile) {
            $url = '/documents/brands/' . ($i+1) . '.png';
            if ($i < 4) {
                check(strpos($tile,'<img src="'.$url.'"') !== false && strpos($tile,'data-src=') === false && substr_count($tile,'<img ') === 1,'First row requires JS or duplicates the image');
            } else {
                check(strpos($tile,'data-src="'.$url.'"') !== false && strpos($tile,'<noscript>') !== false,'Remaining brand logos lost lazy loading or fallback');
            }
        }
    });
    foreach ([['content','content',100,'cat'],['regular','regular-head',100,'cat'],
        ['editorial-main','content',31,'main']] as [$name,$view,$categoryId,$asset]) {
        test('asset-inclusion/' . $name,function () use ($name,$view,$categoryId,$asset,$twig,$request,$base,$templateService,&$builderMode) {
            $request->params = ['page'=>'2'];
            $builderMode = $name === 'editorial-main' ? 'none' : 'single';
            $templateService->template = $name === 'regular' ? 'tpl.category.item' : 'tpl.category.content';
            $category = $base['category']; $category['id'] = $categoryId;
            try {
                $html = $twig->render($view,array_replace($base,['category'=>$category,'pageMax'=>69,'currentPage'=>2,'itemCountTotal'=>1654]));
                preg_match_all('~<script[^>]*src="([^"]*/js/(?:cat|main|vue|item)\\.min\\.js\\?[^"]*)"[^>]*>~',$html,$scripts);
                check(count($scripts[1]) === 1,'Expected exactly one theme page bundle');
                $url = html_entity_decode($scripts[1][0],ENT_QUOTES,'UTF-8');
                $expected = '/plugins/GuardiansBasic/' . verifiedThemeAssetPath('js/' . $asset . '.min.js');
                check($url === $expected,'Wrong shipped asset or cache version');
                checkThemeAssetOrder($html, $asset, false);
                check(strpos($scripts[0][0],' defer') !== false,'Page bundle lost defer');
                check(strpos($html,'/js/cat.js') === false,'Unminified source loaded into shop');
                check(strpos($html,'.brandTiles .col-xl-3 {') === false,'Brand critical CSS leaked into an item list or editorial page');
                preg_match('/<style data-eg-logo-critical>(.*?)<\/style>/s', $html, $logoBlock);
                $logoCss = $logoBlock[1] ?? '';
                $hasLogoGeometry = strpos($logoCss, '.company-logo{') !== false
                    && strpos($logoCss, 'aspect-ratio:332 / 86') !== false
                    && strpos($logoCss, '.company-logo img{') !== false
                    && strpos($logoCss, 'aspect-ratio:332 / 112') !== false
                    && strpos($logoCss, 'object-fit:contain') !== false
                    && strpos($logoCss, 'clamp(198px, 18vw, 247px)') !== false;
                check($hasLogoGeometry,'Proportional logo geometry missing before async CSS');
                check(!preg_match('/\\.company-logo img\\s*\\{\\s*height:\\s*[0-9]+px/', $html),'Legacy fixed logo height remains in critical CSS');
                return ['script'=>$url];
            } finally { $builderMode = 'single'; $templateService->template = 'tpl.category.content'; }
        });
    }
    foreach (['de','en','fr','it','es'] as $language) {
        foreach (['tpl.item','tpl.search','tpl.category.item','tpl.home','tpl.basket'] as $type) {
            test('asset-inclusion/item-language/' . $type . '/' . $language,function () use ($language,$type,$twig,$base,$request,$urls,$templateService) {
                $oldBase = $urls->base;
                $request->params = [];
                $templateService->template = $type;
                // Exercise the actual PageDesign condition, not a copied fragment.
                $urls->base = 'https://example.test/product/a-123/?context=</script><img src=x>&"';
                try {
                    $context = localizedContext($base,$language);
                    $context['category'] = $type === 'tpl.category.item' ? $base['category'] : [];
                    $context['isSearch'] = $type === 'tpl.search'; // Supplied by Ceres ItemSearchContext.
                    $html = $twig->render('layout',$context);
                    $page = $type === 'tpl.item' ? 'item' : (in_array($type, ['tpl.search','tpl.category.item'], true) ? 'cat' : 'main');
                    checkThemeAssetOrder($html, $page, $type === 'tpl.item');
                    check(strpos($html,'data-eg-item-languages') === false,'Duplicate PDP language JSON remains');
                    if ($type === 'tpl.item') {
                        $withCategory = $twig->render('layout',array_replace($context,['category'=>$base['category']]));
                        checkThemeAssetOrder($withCategory, 'cat', true);
                        check(strpos($withCategory,'/js/facets.min.js') === false,'PDP with category context still loads facets');
                    }
                    preg_match_all('~<script src="([^"]*/js/facets.min.js[^\"]*)"([^>]*)>~',$html,$scripts);
                    $expected = in_array($type,['tpl.search','tpl.category.item'],true);
                    check(count($scripts[1]) === ($expected ? 1 : 0),'Wrong number of facet controller inclusions');
                    if ($expected) {
                        check(html_entity_decode($scripts[1][0], ENT_QUOTES, 'UTF-8') === '/plugins/GuardiansBasic/' . verifiedThemeAssetPath('js/facets.min.js'), 'Wrong facet asset URL or numeric version');
                        check(strpos($scripts[2][0],'defer') !== false,'Facet controller lost defer');
                    }
                    return ['itemContext'=>$type === 'tpl.item','facetScript'=>$expected];
                } finally { $urls->base = $oldBase; $templateService->template = 'tpl.category.content'; }
            });
        }
    }
    foreach ([['single','1'],['single','2'],['single','999'],['single','abc'],
        ['none','2'],['simple-only','2'],['multiple','2']] as [$mode,$number]) {
        test('client-pagination/content-' . $mode . '-' . $number,function () use ($mode,$number,$twig,$request,$base,&$builderMode,$search,&$builderCalls) {
            $request->params = ['page'=>$number]; $builderMode = $mode; $search->calls = []; $builderCalls = 0;
            try {
                $html = $twig->render('content',$base);
                $state = json_decode(meta($html,'o46-pagination'),true);
                $valid = $mode === 'single' && in_array($number,['1','2'],true);
                check(is_array($state) && $state['valid'] === $valid,'Client state disagrees with server decision');
                check($state['pageMax'] === ($mode === 'single' ? 138 : null),'Client invented/changed the bound');
                check($state['ambiguous'] === ($mode === 'multiple'),'Client lost ambiguity');
                check($state['hasList'] === in_array($mode,['single','multiple'],true),'Client promoted content/simple widget to main list');
                check($builderCalls === 1 && count($search->calls) === ['single'=>1,'none'=>0,'simple-only'=>1,'multiple'=>2][$mode],'Exporting client state ran another query/builder');
                preg_match('~<title>(.*?)</title>~s',$html,$title);
                return ['pagination'=>$state,'title'=>html_entity_decode($title[1],ENT_QUOTES,'UTF-8'),
                    'description'=>meta($html,'description'),'robots'=>meta($html,'robots'),'canonical'=>canonical($html)];
            } finally { $builderMode = 'single'; }
        });
    }
    foreach ([['known',69,2,1654,true],['unknown',null,2,1654,false],
        ['empty',0,2,0,false],['effective-mismatch',69,1,1654,false]] as [$name,$max,$effective,$total,$valid]) {
        test('client-pagination/regular-' . $name,function () use ($name,$max,$effective,$total,$valid,$twig,$request,$base,$templateService) {
            $request->params = ['page'=>'2']; $templateService->template = 'tpl.category.item';
            try {
                $html = $twig->render('regular-head',array_replace($base,['pageMax'=>$max,'currentPage'=>$effective,'itemCountTotal'=>$total]));
                $state = json_decode(meta($html,'o46-pagination'),true);
                check($state === (new SeoPage(['page'=>'2'],'tpl.category.item',$max,$effective,$total))->getPagination(),'Export changed existing pagination');
                check($state['valid'] === $valid,'Client regular validity differs');
                preg_match('~<title>(.*?)</title>~s',$html,$title);
                return ['pagination'=>$state,'title'=>html_entity_decode($title[1],ENT_QUOTES,'UTF-8'),
                    'description'=>meta($html,'description'),'robots'=>meta($html,'robots'),'canonical'=>canonical($html)];
            } finally { $templateService->template = 'tpl.category.content'; }
        });
    }
    foreach (['zero-int'=>[0,0],'zero-float'=>[0.0,0],'zero-string'=>['0',0],
        'one-float'=>[1.0,1],'ceil-float'=>[69.0,69],'fraction'=>[1.5,null],
        'negative'=>[-1.0,null],'nan'=>[NAN,null],'positive-infinity'=>[INF,null],
        'negative-infinity'=>[-INF,null],'huge-float'=>[1.0e100,null],
        'rounded-int-limit'=>[(float)PHP_INT_MAX,null],'array'=>[[],null],
        'numeric-text'=>['69',69],'invalid-text'=>['abc',null],'missing'=>[null,null]] as $name=>[$value,$expected]) {
        test('compatibility/numeric-bound/' . $name,function () use ($value,$expected) {
            $page = new SeoPage(['page'=>'2'],'tpl.category.item',$value,2,null);
            $pagination = $page->getPagination();
            check($pagination['pageMax'] === $expected,'Numeric bound accepted/converted incorrectly');
            check($pagination['outOfRange'] === ($expected !== null && $expected < 2),'Invalid number treated as a known page bound');
            return $pagination;
        });
        test('compatibility/numeric-total/' . $name,function () use ($value,$expected) {
            $page = new SeoPage(['page'=>'2'],'tpl.category.item',69,2,$value);
            check($page->getPagination()['empty'] === ($expected === 0),'Invalid total became an empty result');
        });
    }
    foreach (['nan'=>NAN,'positive-infinity'=>INF,'negative-infinity'=>-INF,'fraction'=>1.5,'zero'=>0,'negative'=>-1,'invalid-text'=>'abc','overflow-text'=>(string)PHP_INT_MAX . '0'] as $name=>$value) {
        test('compatibility/raw-page/' . $name,function () use ($value) {
            $page = new SeoPage(['page'=>$value],'tpl.category.item',69,1,1654);
            $pagination = $page->getPagination();
            check($pagination['invalidInput'] && !$pagination['valid'] && $pagination['noindex'],'Invalid raw page lost its restrictions');
        });
        foreach (['size','page'] as $option) {
            test('compatibility/effective-' . $option . '/' . $name,function () use ($value,$option,$request,$extension,$search,$context,&$factoryCalls) {
                $request->params = ['page'=>'2']; $search->calls = []; $factoryCalls = []; $before = serialize($context);
                if ($option === 'size') { SearchOptions::$overrideSize = $value; }
                else { SearchOptions::$overridePage = $value; }
                try {
                    $extension->getItemsFromCat(200,$option === 'size' ? 0 : 24,[]);
                    throw new RuntimeException('Expected invalid effective pagination rejection');
                } catch (UnexpectedValueException $error) {
                    check($error->getMessage() === 'Invalid effective category pagination after SearchOptions validation','Exception/message changed');
                    check(count($search->calls) === 0 && serialize($context) === $before,'Invalid option queried or changed route context');
                    check(count($factoryCalls) === 1 && $factoryCalls[0]['class'] === UnexpectedValueException::class,'Exception did not use pluginApp');
                } finally { SearchOptions::$overrideSize = null; SearchOptions::$overridePage = null; }
            });
        }
    }
    test('compatibility/transient-page-factory',function () use ($request,$extension,&$factoryCalls) {
        $request->params = ['page'=>'2','facets'=>'727']; $factoryCalls = [];
        $first = $extension->createSeoPage('tpl.category.content',69,2,1654);
        $first->addList(2,69,1654); $first->shopBuilderHtml = 'first-render';
        $second = $extension->createSeoPage('tpl.category.content',69,2,1654);
        check($first !== $second && $second->shopBuilderHtml === null && !$second->getPagination()['hasList'],'Render state was shared');
        check(count($factoryCalls) === 2 && $factoryCalls[0]['parameters'] === ['query'=>$request->params,'requestUri'=>$request->getRequestUri(),'template'=>'tpl.category.content','pageMax'=>69,'currentPage'=>2,'total'=>1654],'Constructor data not passed through the container');
        $request->params = ['page'=>'999']; $third = $extension->createSeoPage('tpl.category.item',69,999,1654);
        check($third->getPagination()['outOfRange'] && $first->getPagination()['valid'],'Request data leaked between factory instances');
    });
    test('compatibility/production-source',function () use ($root) {
        $fragments = ['SeoPage'=>file_get_contents($root . '/src/Helper/SeoPage.php')];
        $lines = file($root . '/src/Extensions/Base_Extension.php');
        foreach (['getStorefrontLanguageRoutes','categoryHasLanguage','categoryDocumentTotal','requestedFacetIds','getFacetView','createSeoPage','prepareSeoContent','getItemsFromCat','cardAttributeFactory','getCardAttributeMaps','restGetContent'] as $name) {
            $method = new ReflectionMethod(Base_Extension::class,$name);
            $fragments[$name] = "<?php\n" . implode('',array_slice($lines,$method->getStartLine()-1,$method->getEndLine()-$method->getStartLine()+1));
        }
        $audits = [];
        foreach ($fragments as $name=>$fragment) {
            $audits[$name] = checkPluginSource($fragment);
            check(!$audits[$name]['issues'],'Restricted construct/function in ' . $name . ': ' . json_encode($audits[$name]['issues']));
        }
        return $audits;
    });
    foreach (['de','en','fr','it','es'] as $language) {
        foreach (['ampersand'=>'Hemd & Hose', 'quotes'=>'Hemd "Sommer"',
            'tag-delimiters'=>'</title><script data-phase4-probe>void 0</script><title>'] as $kind=>$title) {
            foreach (['category','block'] as $source) {
                test('metadata/title-escaping/'.$language.'/'.$kind.'/'.$source, function () use ($language,$kind,$title,$source,$twig,$base) {
                    $ctx = localizedContext($base,$language);
                    $ctx['seoPage'] = new SeoPage([], 'tpl.category.item', 1, 1, 1);
                    $ctx['isQuickView'] = true;
                    $ctx['fixtureTitle'] = $title;
                    // An empty metaTitle must use the category name with the same safety contract.
                    $ctx['category']['details'][0]['metaTitle'] = $kind === 'ampersand' ? '' : $title;
                    $ctx['category']['details'][0]['name'] = $title;
                    $html = $twig->render($source === 'category' ? 'metadata' : 'metadata-block-title', $ctx);
                    check(strpos($html, '<script data-phase4-probe>') === false, 'Catalog title opened a script element');
                    check(substr_count($html, '<title>') === 1, 'Catalog title escaped its text element');
                    check(preg_match('~<title>(.*?)</title>~s', $html, $titleMatch) === 1, 'Title missing');
                    check(html_entity_decode($titleMatch[1], ENT_QUOTES|ENT_HTML5, 'UTF-8') === $title, 'Title changed or was double escaped');
                    foreach (['property'=>'og:title', 'name'=>'twitter:title'] as $attribute=>$name) {
                        check(preg_match('~<meta '.$attribute.'="'.$name.'" content="([^"]*)"~', $html, $match) === 1, 'Social title missing');
                        check(html_entity_decode($match[1], ENT_QUOTES|ENT_HTML5, 'UTF-8') === $title, 'Social title truncated or double escaped');
                    }
                    return ['source'=>$source,'language'=>$language,'kind'=>$kind,'textPreserved'=>true,'escaped'=>true];
                });
            }
        }
    }
    test('b2c/language-route-with-current-language-only',function () use ($twig,$request,$base,$templateService) {
        $templateService->template = 'tpl.category.content';
        $request->params = [];
        $ctx = $base;
        $ctx['category']['details'][0]['lang'] = 'de';
        $ctx['category']['details'][0]['metaRobots'] = 'ALL';
        $html = $twig->render('regular',$ctx);
        foreach (['de','en','fr','it','es'] as $code) {
            check(preg_match('~<(?:link rel="alternate" hreflang|meta name="o46-language-route" lang)="'. $code .'"~', $html) === 1, 'Missing resolved category language ' . $code);
        }
    });
    test('b2c/translated-targets-and-page-boundary',function () use ($twig,$request,$base,$categoryService,$templateService) {
        $ctx = $base;
        $ctx['category']['details'][0]['metaRobots'] = 'ALL';
        $ctx['pageMax'] = 3;
        $ctx['itemCountTotal'] = 25;
        $templateService->template = 'tpl.category.item';
        foreach (['de','en','fr','it','es'] as $missing) {
            $categoryService->languages = array_diff(['de','en','fr','it','es'],[$missing]);
            foreach ([1,2,3,4] as $page) {
                $ctx['currentPage'] = $page;
                $request->params = ['page'=>(string)$page];
                $html = $twig->render('regular',$ctx);
                check(substr_count($html,'rel="alternate"') === ($page === 1 ? 4 : 0),'Foreign page membership assumed');
                check(!preg_match('~<(?:link rel="alternate" hreflang|meta name="o46-language-route" lang)="'.preg_quote($missing,'~').'"~', $html),'Missing head translation emitted');
                check(meta($html,'robots') === ($page === 4 ? 'NOINDEX,FOLLOW' : 'INDEX,FOLLOW'),'Wrong robots at boundary');
            }
        }
        $categoryService->languages = ['de','en','fr','it','es'];
        $templateService->template = 'tpl.category.content';
        $request->params = [];
    });
    test('documents/catalog-change-between-primary-and-probe', function () use ($extension,$renderer,$search,$request) {
        try {
            $search->total = 3241;
            $search->documentTotal = 3276;
            foreach ([[273,3264],[274,3299],[274,3312]] as [$page,$availableAtProbe]) {
                $search->boundaryDocumentTotal = $availableAtProbe;
                $request->params = ['page'=>$page];
                $extension->getItemsFromCat(60,12,[]);
                check((int)$renderer->params['pageMax'] === 273, 'Pager contradicted the primary documents');
                check($renderer->params['itemCountTotal'] === 3276, 'Primary lower or upper bound was lost');
            }
        } finally {
            $search->total = 1654;
            $search->documentTotal = $search->boundaryDocumentTotal = null;
            $request->params = [];
        }
    });
    test('documents/contiguous-pages-and-probe-scope',function () use ($extension,$renderer,$search,$request) {
        $runs = [];
        try {
            foreach (['de'=>48,'en'=>49,'fr'=>37,'it'=>24,'es'=>25] as $language=>$available) {
                // Per-language inventories differ, with both low and high estimates.
                $search->total = $language === 'fr' ? 2400 : 24;
                $search->documentTotal = $available;
                $ids = [];
                $last = (int)ceil($available / 12);
                for ($page=1;$page<=$last+1;$page++) {
                    $search->calls = $search->boundaryCalls = [];
                    $request->params = ['page'=>$page,'sorting'=>'texts.name1_asc','facets'=>'12,27','priceMin'=>3,'priceMax'=>80];
                    $extension->getItemsFromCat(60,48,[27]);
                    $list = $renderer->params;
                    check(count($search->calls) === 1,'Expected one primary search');
                    $primary = $search->calls[0]['itemList'];
                    check($primary['itemsPerPage'] === 12,'Primary page size changed');
                    foreach ($search->boundaryCalls as $probe) {
                        check(array_keys($probe) === ['boundary'],'Facet or other search in boundary query');
                        $options = $probe['boundary'];
                        check($options['itemsPerPage'] <= 49,'Unbounded document probe');
                        foreach (['sorting','facets','categoryId','priceMin','priceMax'] as $key) {
                            check($options[$key] === $primary[$key],'Boundary query changed '.$key);
                        }
                    }
                    check($list['itemCountTotal'] === $available && (int)$list['pageMax'] === $last,'Inconsistent count');
                    $documents = $list['itemList'];
                    check(count($documents) === max(0,min(12,$available-($page-1)*12)),'Wrong page slice');
                    if ($documents && count($documents)<12) { check(!$search->boundaryCalls,'Short page triggered a probe'); }
                    foreach ($documents as $doc) { $ids[]=$doc['data']['variation']['id']; }
                    $runs[$language][] = ['page'=>$page,'documents'=>count($documents),'probes'=>count($search->boundaryCalls)];
                }
                check($ids === range(1,$available),'Missing or duplicate IDs on '.$language.' traversal');
            }
            return $runs;
        } finally {
            $search->total=1654; $search->documentTotal=null; $request->params=[];
            $search->calls=$search->boundaryCalls=[];
        }
    });
    test('b2c/boundary-probe-cost-and-cardinality',function () use ($search,$extension,$renderer,$request) {
        try {
            // Estimated and exact grouped totals, including estimates on either side of a block.
            foreach ([[3240,3276,1,1,42], [3241,3272,2,1,38], [3272,3272,1,1,8],
                [3272,3272,273,0,0], [3300,3271,2,null,null], [2400,25,3,0,0],
                [0,61,1,null,null], [3272,3271,274,null,null]] as [$estimate,$available,$page,$limitCalls,$limitIds]) {
                $search->total = $estimate;
                $search->documentTotal = $available;
                $search->calls = $search->boundaryCalls = [];
                $request->params = ['page'=>$page,'facets'=>'27','sorting'=>'texts.name1_asc'];
                $extension->getItemsFromCat(60,48,[]);
                check($renderer->params['itemCountTotal'] === $available,'Wrong total for '.$estimate.'/'.$available);
                $ids = 0;
                foreach ($search->boundaryCalls as $probe) {
                    $options = $probe['boundary'];
                    $width = $options['itemsPerPage'];
                    $offset = ($options['page'] - 1) * $width;
                    $ids += max(0,min($width,$available - $offset));
                }
                if ($limitCalls !== null) {
                    check(count($search->boundaryCalls) <= $limitCalls && $ids <= $limitIds,'Excess boundary work for '.$estimate.'/'.$available);
                }
                check(count($search->calls) === 1 && $search->calls[0]['itemList']['itemsPerPage'] === 12,'Unexpected primary query');
            }
        } finally {
            $search->total=1654; $search->documentTotal=null; $request->params=[];
            $search->calls=$search->boundaryCalls=[];
        }
    });
    test('b2c/storefront-route-matrix', function () use ($twig,$extension,$request,$base,$categoryService,$templateService,$urls,&$services) {
        $codes = ['de','en','fr','it','es'];
        $fixtureUrls = new class extends UrlService {
            public function getCanonicalURL($lang = null,$ignore = false) {
                return 'https://example.test/' . (!$lang || $lang === 'de' ? '' : $lang.'/');
            }
            public function getLanguageURLs() { return []; } // IO has no SEO alternatives for these fixtures.
            public function getCategoryURL($id,$lang) {
                $url = clone $this;
                $newsletter = ['de'=>'newsletter-anmeldung', 'en'=>'newsletter-sign-up', 'fr'=>'inscription-a-la-newsletter', 'it'=>'iscriviti-alla-newsletter', 'es'=>'inscribase-al-boletin'];
                $slug = $id === 113 ? $newsletter[$lang] : 'category-' . $id . '-' . $lang;
                $url->base = $this->getCanonicalURL($lang) . $slug . '/';
                return $url;
            }
            public function getVariationURL($itemId,$variationId,$lang) {
                return $this->getCategoryURL($itemId.'-'.$variationId,$lang);
            }
        };
        $kinds = [
            'home'=>['tpl.home',null], 'item'=>['tpl.item',null], 'category'=>['tpl.category.item',111],
            'shopbuilder'=>['tpl.category.content',112], 'newsletter'=>['tpl.category.content',113],
            'unsubscribe'=>['tpl.newsletter.opt-out',null], 'login'=>['tpl.login',null],
            'registration'=>['tpl.register',null], 'account'=>['tpl.my-account',null],
            'wish-list'=>['tpl.wish-list',null], 'basket'=>['tpl.basket',null],
            'checkout'=>['tpl.checkout',null], 'contact'=>['tpl.contact',null],
            'privacy'=>['tpl.privacy-policy',null], 'content'=>['tpl.category.content',114],
            'search'=>['tpl.search',null], 'tag'=>['tpl.tags',null], 'blog'=>['tpl.category.blog',117],
            'legal'=>['tpl.legal-disclosure',null], 'terms'=>['tpl.terms-conditions',null],
            'newsletter-content-unsubscribe'=>['tpl.category.content',118]
        ];
        $services[IO\Services\UrlService::class] = $fixtureUrls;
        $templateBefore = $templateService->template;
        $previousCategory = $categoryService->route;
        $uriBefore = $request->rawUri;
        IO\Helper\RouteConfig::$links = ['contact'=>115,'basket'=>116];
        $ctx = $base;
        $ctx['services']['url'] = $fixtureUrls;
        $ctx['isQuickView'] = true;
        $ctx['forceNoIndex'] = true;
        $ctx['itemData'] = ['item'=>['id'=>42],'variation'=>['id'=>101]];
        $result = [];
        try {
            foreach ($kinds as $kind=>[$template,$id]) {
                $templateService->template = $template;
                $ctx['category']['id'] = $id;
                $request->rawUri = $kind === 'tag' ? '/fr/sale_t207/?sorting=price_asc' : null;
                $html = $twig->render('head',$ctx);
                check(strpos($html,'rel="alternate"') === false,'Unexpected SEO alternate');
                preg_match_all('~<meta name="o46-language-route" lang="([^"]+)" content="([^"]+)">~',$html,$hits);
                $map = array_combine($hits[1],$hits[2]);
                check(array_keys($map) === $codes,'Incomplete functional routes on '.$kind);
                check(count(array_unique($map)) === 5,'Language targets collapsed');
                foreach ($map as $code=>$target) {
                    $prefix = $code === 'de' ? '/' : '/'.$code.'/';
                    $expected = [
                        'home'=>'', 'item'=>'category-42-101-'.$code.'/',
                        'contact'=>'category-115-'.$code.'/', 'basket'=>'category-116-'.$code.'/',
                        'unsubscribe'=>'newsletter/unsubscribe/', 'registration'=>'register/', 'account'=>'my-account/',
                        'tag'=>'sale_t207/', 'legal'=>'legal-disclosure/', 'terms'=>'gtc/',
                        'newsletter'=>['de'=>'newsletter-anmeldung/', 'en'=>'newsletter-sign-up/', 'fr'=>'inscription-a-la-newsletter/', 'it'=>'iscriviti-alla-newsletter/', 'es'=>'inscribase-al-boletin/'][$code],
                        'privacy'=>'privacy-policy/'
                    ][$kind] ?? ($id ? 'category-'.$id.'-'.$code.'/' : $kind.'/');
                    check($target === 'https://example.test'.$prefix.$expected,'Unexpected '.$kind.' target for '.$code);
                }
                $result[$kind] = $map;
            }
            $categoryService->languages = ['de','en','fr','it'];
            $categoryService->route = (object)['id'=>111,'details'=>[(object)['lang'=>'de'],(object)['lang'=>'en']]];
            $categoryService->reads = [];
            $fallback = $extension->getStorefrontLanguageRoutes('tpl.category.item', null, null, null);
            check(array_keys($fallback) === $categoryService->languages, 'Current category translation availability');
            check($categoryService->reads === [[111,'fr'],[111,'it'],[111,'es']], 'Only missing category details need a lookup');
            check($fallback === array_intersect_key($result['category'],array_flip($categoryService->languages)), 'Current category changes language URLs');
            $templateService->template = 'tpl.contact';
            $categoryService->languages = ['de','en','fr','it'];
            $html = $twig->render('head',$ctx);
            check(strpos($html,'name="o46-language-route" lang="es"') === false,'Untranslated linked category fell back');
            IO\Helper\RouteConfig::$disabled = ['login'];
            $templateService->template = 'tpl.login';
            check(strpos($twig->render('head',$ctx),'name="o46-language-route"') === false,'Disabled native route was offered');
            return $result;
        } finally {
            $request->rawUri = $uriBefore;
            $services[IO\Services\UrlService::class] = $urls;
            $templateService->template = $templateBefore;
            $categoryService->route = $previousCategory;
            $categoryService->languages = $codes;
            IO\Helper\RouteConfig::$links = IO\Helper\RouteConfig::$disabled = [];
        }
    });
    // Optional browser input. Render full and transport URLs independently through the real templates.
    if ($historyFile = getenv('O46_SEO_HISTORY_FIXTURES')) {
        $historyPages = [];
        $routes = ['de'=>'/alle-artikel/', 'en'=>'/en/all-articles/', 'fr'=>'/fr/tous-les-articles/', 'it'=>'/it/tutti-gli-articoli/', 'es'=>'/es/todos-los-articulos/'];
        foreach (array_keys($routes) as $language) {
            $historyUrls = new class($routes, $language) extends UrlService {
                public $search = false;
                public function __construct(private $routes, private $language) {}
                public function getCanonicalURL($lang = null, $ignore = false) {
                    $language = $lang ?: $this->language;
                    $route = $this->search ? ($language === 'de' ? '' : '/' . $language) . '/search/' : $this->routes[$language];
                    return $this->configured && !$ignore ? $this->configured : 'https://example.test' . $route;
                }
            };
            foreach (['plain'=>[], 'filtered'=>['facets'=>'9'], 'sorted'=>['sorting'=>'price.asc', 'utm_source'=>'A B'],
                      'configured'=>[], 'forced'=>[], 'search'=>['query'=>'Hose']] as $mode=>$query) {
                $historyUrls->search = $mode === 'search';
                $templateService->template = $historyUrls->search ? 'tpl.search' : 'tpl.category.item';
                $historyUrls->configured = $mode === 'configured' ? 'https://example.test/editorial/?keep=7' : null;
                foreach (($historyUrls->search ? [1, 2, 3] : [18, 19, 20]) as $number) foreach ([false, true] as $fragment) {
                    $request->params = ($number === 1 ? [] : ['page'=>(string)$number]) + $query;
                    if ($fragment) $request->params['quickViewItem'] = 'true';
                    $ctx = localizedContext($base, $language);
                    $ctx['services']['url'] = $historyUrls;
                    $ctx['category']['details'][0]['metaRobots'] = 'INDEX,FOLLOW';
                    $ctx['category']['details'][0]['metaTitle'] = 'Mode & Accessoires';
                    if ($mode === 'forced') $ctx['forcedCanonicalUrl'] = 'https://example.test/curated/?lang=' . $language;
                    $html = $twig->render('regular-head', $ctx + ['pageMax'=>20,'currentPage'=>$number,'itemCountTotal'=>240]);
                    preg_match('~<head>(.*?)</head>~s', $html, $match);
                    preg_match_all('~<title>.*?</title>|<meta (?:name="(?:o46-pagination|robots|o46-language-route|description|twitter:title|twitter:description)"|property="og:(?:title|description)")[^>]*>|<link rel="(?:canonical|alternate|prev|next)"[^>]*>~s', $match[1], $tags);
                    $historyPages[$language][$mode][$number][$fragment ? 'fragment' : 'direct'] = [
                        'url'=>$historyUrls->getCanonicalURL(null,true) . '?' . http_build_query($request->params),
                        'head'=>implode("\n", $tags[0])];
                }
            }
        }
        file_put_contents($historyFile, json_encode($historyPages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $report['comparison'] = 'NOT_RUN';
    if (isset($argv[3])) {
        test('compatibility/before-fix-comparison',function () use (&$report,$argv) {
            check(strpos($argv[3],'--compare=') === 0,'Expected --compare=<before-fix.json>');
            $reference = json_decode(file_get_contents(substr($argv[3],10)),true,512,JSON_THROW_ON_ERROR);
            check($reference['twig'] === $report['twig'] && $reference['casePass'] === 190 && !$reference['failures'],'Incomplete or wrong before-fix reference');
            foreach ($reference['cases'] as $name=>$expected) {
                check(isset($report['cases'][$name]) && $report['cases'][$name] === $expected,'Existing SEO behavior changed: ' . $name);
            }
            $report['comparison'] = '190/190 PASS';
            return $report['comparison'];
        });
    }
    $report['casePass'] = count($report['cases']);
    $report['caseFail'] = count($report['failures']);
    $report['deprecations'] = array_keys($deprecations);
    file_put_contents($argv[2],json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n");
    echo json_encode(['twig'=>$report['twig'],'pass'=>$report['casePass'],'fail'=>$report['caseFail'],'comparison'=>$report['comparison'],'failures'=>$report['failures']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n";
    exit($report['caseFail'] ? 1 : 0);
}
