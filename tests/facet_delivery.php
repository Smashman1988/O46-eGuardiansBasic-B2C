<?php
// php tests/facet_delivery.php <Twig autoload> <output.json>
require $argv[1];
$layout=file_get_contents(dirname(__DIR__).'/resources/views/PageDesign/PageDesign.twig');
$start=strpos($layout,'{% if (category.id or isSearch)');
$end=strpos($layout,'{# Keep the existing page selection',$start);
$pathsStart=strpos($layout,'{% set newsletterPaths =');
$pathsEnd=strpos($layout,'{% set currentPath =',$pathsStart);
$source=substr($layout,$pathsStart,$pathsEnd-$pathsStart).substr($layout,$start,$end-$start);
$twig=new Twig\Environment(new Twig\Loader\ArrayLoader(['asset'=>$source]));
function fixturePlugin($id){return '/plugin/'.strtolower($id);}
function fixtureConfig($key){return $key==='IO.routing.category_login'?58:null;}
$twig->addFunction(new Twig\TwigFunction('plugin_path','fixturePlugin'));
$twig->addFunction(new Twig\TwigFunction('config','fixtureConfig'));
$base=['currentTemplate'=>'tpl.category.content','currentPath'=>'information','category'=>['id'=>570]];
$cases=[
    ['Login-Template',['currentTemplate'=>'tpl.login'],'conditional'],
    ['Login-Content',['bodyClasses'=>['category-login']],'conditional'],
    ['Login-Konfiguration',['category'=>['id'=>58]],'conditional'],
    ['Content',[],'direct'],['Kategorie',['currentTemplate'=>'tpl.category.item'],'direct'],
    ['Suche',['category'=>[],'isSearch'=>true,'currentTemplate'=>'tpl.search'],'direct'],
    ['Markenverzeichnis',['rendersItemGrid'=>false],'none'],['PDP',['isItem'=>true],'none'],['Warenkorb',['isBasketPage'=>true],'none']
];
foreach(['newsletter-anmeldung','en/newsletter-sign-up','fr/inscription-a-la-newsletter','it/iscriviti-alla-newsletter','es/inscribase-al-boletin'] as $p)$cases[]=[$p,['currentPath'=>$p],'conditional'];
$results=[];$loader=null;
foreach($cases as [$key,$ctx,$expected]){
    $html=trim($twig->render('asset',$ctx+$base));
    $actual=str_contains($html,'data-facets-src=')?'conditional':(str_contains($html,'<script src=')?'direct':'none');
    if ($actual!==$expected) throw new RuntimeException($key.' expected '.$expected.' got '.$actual);
    if ($actual==='conditional') $loader=$html;
    $results[$key]=$actual;
}
file_put_contents($argv[2],json_encode(['cases'=>$results,'loader'=>$loader],JSON_THROW_ON_ERROR));
echo count($results)." facet delivery routes passed\n";
