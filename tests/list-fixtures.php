<?php
// In-memory substitutes for Plenty services. No HTTP, database, or shop access.
namespace Plenty\Plugin\Templates\Extensions { class Twig_Extension {} }
namespace Plenty\Plugin\Templates\Factories { class TwigFactory {} }
namespace Plenty\Plugin\Http {
    class Request {
        public $params = [];
        public $uri;
        public function query() { return $this->params; }
        public function get($key, $default = null) { return $this->params[$key] ?? $default; }
        public function getRequestUri() { return $this->uri; }
    }
}
namespace Plenty\Plugin\Templates {
    class Twig {
        public $render;
        public function render($name, $data) { return ($this->render)($name, $data); }
        public function addExtension($class) {}
    }
}
namespace Plenty\Plugin {
    class ServiceProvider {}
    class ConfigRepository { public function get($key) { return 'all'; } }
}
namespace Plenty\Plugin\Events {
    class Dispatcher {
        public $listeners = [];
        public function listen($event, $callback, $priority) { $this->listeners[$event][$priority][] = $callback; }
    }
}
namespace IO\Helper { class TemplateContainer {
    public $template;
    public function setTemplate($name) { $this->template = $name; }
} }
namespace IO\Extensions\Functions { class Partial {
    public $paths = [];
    public function set($key, $path) { $this->paths[$key] = $path; }
} }
namespace IO\Services {
    class TemplateService { public static $currentTemplateData; }
    class CategoryService {
        public $category;
        public $available;
        public function get($id) { return $this->category; }
        public function getDetails($category, $lang) { return $category && in_array($lang, $this->available, true) ? $category['details'][0] : null; }
    }
}
namespace Ceres\Helper {
    if (!defined('CERES_FIXTURE_SRC')) {
        class SearchOptions {
            const SCOPE_CATEGORY = 'category';
            public static function validateItemListOptions($options, $scope) {
                if (!$options['sorting']) $options['sorting'] = 'relevance';
                return $options;
            }
            public static function get($scope) { return ['sorting' => ['relevance' => 'Relevance']]; }
        }
    }
}
namespace Plenty\Modules\Webshop\ItemSearch\Helpers {
    class ResultFieldTemplate {
        const TEMPLATE_LIST_ITEM=1, TEMPLATE_SINGLE_ITEM=2, TEMPLATE_BASKET_ITEM=3, TEMPLATE_AUTOCOMPLETE_ITEM_LIST=4,
            TEMPLATE_CATEGORY_TREE=5, TEMPLATE_VARIATION_ATTRIBUTE_MAP=6;
        public function setTemplates($values) {}
    }
}
namespace Plenty\Modules\Webshop\ItemSearch\SearchPresets {
    class CategoryItems { public static function getSearchFactory($options) { return $options; } }
    class Facets extends CategoryItems {}
    class VariationAttributeMap extends CategoryItems {}
}
namespace Plenty\Modules\Webshop\ItemSearch\Services {
    class ItemSearchService {
        public $total;
        public $requests = [];
        public $boundaryRequests = [];
        public function getResults($params) {
            $key = isset($params['boundary']) ? 'boundary' : 'itemList';
            if ($key === 'boundary') { $this->boundaryRequests[] = $params; }
            else { $this->requests[] = $params; }
            $o = $params[$key];
            $ids = array_slice($this->total ? range(1, $this->total) : [], ($o['page'] - 1) * $o['itemsPerPage'], $o['itemsPerPage']);
            $docs = array_map(fn($id) => ['data' => ['item'=>['id'=>$id], 'variation'=>['id'=>$id,'itemId'=>$id], 'texts'=>['name1'=>'Artikel '.$id], 'images'=>['all'=>[['urlPreview'=>'/fixture-image']]], 'prices'=>['default'=>['unitPrice'=>['value'=>19.95,'formatted'=>'19,95 €']]]]], $ids);
            return [$key=>['total'=>$this->total,'documents'=>$docs], 'facets'=>[]];
        }
    }
}
namespace {
    function pluginApp($class, $args = []) {
        global $registry;
        return $registry[$class] ?? new $class(...$args);
    }
    class FixtureCallback {
        private $call;
        public function __construct($call) { $this->call = $call; }
        public function run(...$args) { return ($this->call)(...$args); }
    }
    class FixtureRoutes {
        public $lang, $canonical, $template, $available;
        public $slugs = ['de'=>'schuhe','en'=>'shoes','fr'=>'chaussures','it'=>'scarpe','es'=>'zapatos'];
        public function route($lang) {
            return 'https://b2c.example/' . ($lang === 'de' ? '' : $lang . '/') . $this->slugs[$lang] . '/';
        }
        public function getCanonicalURL($lang = null, $ignore = false) {
            return (!$ignore && $this->canonical) ? $this->canonical : $this->route($lang ?? $this->lang);
        }
        public function getCategoryURL($id, $lang) { return $this->getVariationURL(null, null, $lang); }
        public function getLanguageURLs() { return ['x-default'=>$this->getCanonicalURL('de')] + array_map(fn($code) => $this->getCanonicalURL($code), array_combine(array_keys($this->slugs), array_keys($this->slugs))); }
        public function getVariationURL($item, $variation, $code) {
            return new class(in_array($code, $this->available, true)) {
                public function __construct(private $available) {}
                public function getPath() { return $this->available ? 'translated-product' : null; }
            };
        }
        public function getLanguage() { return $this->lang; }
        public function getCurrentTemplate() { return $this->template; }
        public function isItem() { return $this->template === 'tpl.item'; }
        public function isSearch() { return $this->template === 'tpl.search'; }
    }
}
