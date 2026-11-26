<?php
// Compile the real template inventory. Only known IO/Ceres/theme symbols are registered.
require $argv[1];
$root = dirname(__DIR__).'/resources/views';
$syntax = new Twig\Environment(new Twig\Loader\FilesystemLoader($root), ['cache'=>false]);
$functions = ['getCardLinks','sortSizes','sortCatByKey','component','config','csrf_token','getAttributesWithImages','getCardAttributeMaps','getBasePriceList','getItemPropertiesById',
    'getFacetView','getBrandGroups','getFeedbackItemList','getImageAttributeList','getListFeedbackAverages','getVariationSalabilityMap','getSalableAttributeValues',
    'getPartial','get_additional_scripts','get_additional_styles','get_consent_scripts',
    'get_filtered_tags','get_json_data','get_shop_builder_scripts','plugin_path','queryString',
    'shop_builder_category_template','ssr_entry','trans','getStorefrontLanguageRoutes','createSeoPage','prepareSeoContent'];
$filters = ['addressOptionType','filterFields','filterTabs','formatDateTime','formatMonetary',
    'getObjectValue','int','itemName','itemURL','orderByKey','specialOffer','trimNewlines'];
$syntaxOnly = function () { throw new RuntimeException('Syntax-only Plenty placeholder was executed'); };
foreach ($functions as $name) { $syntax->addFunction(new Twig\TwigFunction($name, $syntaxOnly)); }
foreach ($filters as $name) { $syntax->addFilter(new Twig\TwigFilter($name, $syntaxOnly)); }

$count=0; $errors=[];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
    if ($file->getExtension() !== 'twig') continue;
    $name = str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
    try { $syntax->compileSource($syntax->getLoader()->getSourceContext($name)); ++$count; }
    catch (Throwable $error) { $errors[$name]=$error->getMessage(); }
}
echo json_encode(['twig'=>Twig\Environment::VERSION,'compiled'=>$count,'failures'=>$errors],JSON_THROW_ON_ERROR), PHP_EOL;
exit($errors ? 1 : 0);
