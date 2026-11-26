<?php
// php tests/variation_salability.php <IO plugin directory> <output.json>
// Run the original IO service and builders. Only the platform/repository boundary is synthetic.
namespace Plenty\Plugin { class Application { function getPlentyId() { return $GLOBALS['client']; } } }
namespace Plenty\Plugin\Templates\Extensions { class Twig_Extension {} }
namespace Plenty\Plugin\Log { trait Loggable {} }
namespace Plenty\Modules\Webshop\Contracts {
    class ContactRepositoryContract { function getContactClassId() { return $GLOBALS['customerClass']; } }
    class SessionStorageRepositoryContract {}
}
namespace IO\Helper { class Utils { static function getLang() { return $GLOBALS['language']; } } }
namespace Plenty\Modules\Item\DataLayer\Contracts {
    class ItemDataLayerRepositoryContract {
        public array $queries = [];
        public array $rows = [];
        public array $omitFromBatch = [];
        public function search($columns, $filter, $params) {
            $ids = $filter['variationBase.hasId']['id'];
            $rows = [];
            foreach (array_reverse($ids) as $id) {
                if (count($ids)>1 && in_array($id,$this->omitFromBatch,true)) continue;
                foreach ($this->rows[$id] ?? [] as $row) $rows[]=$row;
            }
            $this->queries[] = compact('columns','filter','params') + ['rows'=>count($rows)];
            return new \ArrayIterator($rows);
        }
    }
}
namespace {
    use Plenty\Modules\Item\DataLayer\Contracts\ItemDataLayerRepositoryContract;
    use Plenty\Modules\Webshop\Contracts\ContactRepositoryContract;
    use Plenty\Modules\Webshop\Contracts\SessionStorageRepositoryContract;
    use Plenty\Plugin\Application;
    use IO\Services\ItemService;
    $ioRoot=realpath($argv[1]).'/src/';
    $builderWarnings=[];
    set_error_handler(function($level,$message,$file,$line) use (&$builderWarnings) {
        // The IO 5.x column builder reads uninitialised optional array keys on PHP 8.
        if ($level===E_WARNING && basename($file)==='ItemColumnBuilder.php' && str_starts_with($message,'Undefined array key')) {
            $key=basename($file).':'.$line.' '.$message;
            $builderWarnings[$key]=($builderWarnings[$key]??0)+1;
            return true;
        }
        throw new ErrorException($message,0,$level,$file,$line);
    });
    spl_autoload_register(function($class) use ($ioRoot) {
        if (str_starts_with($class,'IO\\')) require $ioRoot.str_replace('\\','/',substr($class,3)).'.php';
    });
    require dirname(__DIR__).'/src/Extensions/Base_Extension.php';
    $repo=new ItemDataLayerRepositoryContract();
    $original=new ItemService(new Application(),$repo,new SessionStorageRepositoryContract());
    $theme=(new ReflectionClass(\GuardiansBasic\Extensions\Base_Extension::class))->newInstanceWithoutConstructor();
    function pluginApp($type) {
        global $original,$repo;
        if ($type===ItemService::class) return $original;
        if ($type===ItemDataLayerRepositoryContract::class) return $repo;
        return new $type();
    }
    function check($ok,$name) { if (!$ok) throw new RuntimeException($name); }
    function record($id,$limit,$stock) {
        return ['variationBase'=>['id'=>$id,'limitOrderByStockSelect'=>$limit],
            'variationStock'=>['stockPhysical'=>$stock,'stockNet'=>99],'variationRetailPrice'=>['basePrice'=>12]];
    }
    function compare($ids,$name) {
        global $original,$repo,$theme;
        $repo->queries=[];
        $before=[];$start=hrtime(true);
        foreach ($ids as $id) $before[$id]=!$original->getVariationIsSalable($id);
        $beforeUs=(hrtime(true)-$start)/1000;$baseline=$repo->queries;$repo->queries=[];
        $start=hrtime(true);$after=$theme->getVariationSalabilityMap($ids);$afterUs=(hrtime(true)-$start)/1000;
        ksort($before);ksort($after);check($before===$after,$name.' saleability by ID');
        foreach ($repo->queries as $query) {
            $ref=$baseline[0];
            check($query['params']===$ref['params'],$name.' original session parameters');
            $filter=$query['filter'];$filter['variationBase.hasId']=$ref['filter']['variationBase.hasId'];
            check($filter===$ref['filter'],$name.' original filters');
            $columns=$query['columns'];
            $columns['variationBase']=array_values(array_diff($columns['variationBase'],['id']));
            check($columns===$ref['columns'],$name.' original fields + ID');
        }
        return ['case'=>$name,'ids'=>count($ids),'beforeCalls'=>count($baseline),'afterCalls'=>count($repo->queries),
            'beforeRecords'=>array_sum(array_column($baseline,'rows')),'afterRecords'=>array_sum(array_column($repo->queries,'rows')),
            'beforeFixtureUs'=>$beforeUs,'afterFixtureUs'=>$afterUs];
    }
    $results=[];
    foreach (['de','en','fr','it','es'] as $i=>$language) {
        $client=10+$i;$customerClass=$i+1;
        for ($id=1;$id<=128;$id++) {
            $index=($id+$customerClass)%6;
            $repo->rows[$id]=[record($id,[0,1,1,1,2,'1'][$index],[0,0,-2,7,0,'0'][$index])];
        }
        foreach ([1,4,16,32,64,128] as $n) {
            $row=compare(range(1,$n),$language.'-'.$n);
            check($row['afterCalls']===1 && $row['afterRecords']===$n,$row['case'].' batch size');
            $results[]=$row;
        }
    }
    $repo->omitFromBatch=[2]; $repo->rows[3][] = record(3,0,2);
    $repo->rows[4]=[record(4,1,null)];
    $gaps=compare([1,2,3,4,4],'truncated batch, ambiguous ID, null stock and repeated input');
    check($gaps['afterCalls']===4,'Expected one batch and three calls to IO');
    $results[]=$gaps;
    $repo->queries=[];
    check($theme->getVariationSalabilityMap([])===[] && !$repo->queries,'empty map');
    // If the original IO service rejects a missing price row, the fallback must expose the same outcome.
    unset($repo->rows[1]);
    foreach ([fn()=>$original->getVariationIsSalable(1),fn()=>$theme->getVariationSalabilityMap([1,2])] as $run) {
        try { $run(); throw new RuntimeException('Expected original missing-row diagnostic'); }
        catch (ErrorException $error) { check(basename($error->getFile())==='ItemService.php','IO owns the missing-row case'); }
    }
    file_put_contents($argv[2],json_encode(['cases'=>$results,'ioBuilderWarnings'=>$builderWarnings,'missingPrice'=>'same IO diagnostic'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo count($results)." IO comparisons and empty input passed\n";
}
