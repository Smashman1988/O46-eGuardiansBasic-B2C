<?php
// php tests/attribute_lookups.php [previous Base_Extension file with class renamed to PreviousBaseExtension]
namespace Plenty\Plugin\Templates\Extensions { class Twig_Extension {} }
namespace {
    use GuardiansBasic\Extensions\Base_Extension;
    require dirname(__DIR__).'/src/Extensions/Base_Extension.php';

    function pluginApp($class) { return $GLOBALS['services'][$class]; }
    function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

    $lookup = (new ReflectionClass(Base_Extension::class))->newInstanceWithoutConstructor();
    $pair = ['attributeId'=>'03', 'attributeValueId'=>'007'];
    $variations = [
        ['variationId'=>11, 'attributes'=>[$pair, (object)['attributeId'=>4,'attributeValueId'=>7]]],
        ['variationId'=>12, 'attributes'=>[new ArrayObject(['attributeId'=>3,'attributeValueId'=>8])]],
        ['variationId'=>13, 'attributes'=>[['attributeId'=>3,'attributeValueId'=>9]]],
        ['variationId'=>14, 'attributes'=>null],
        ['variationId'=>15, 'attributes'=>[null, []]]
    ];
    check($lookup->getSalableAttributeValues($variations,[11=>true,12=>true,13=>false,14=>true,15=>true])
        === [3=>[7=>true,8=>true],4=>[7=>true]], 'Blocked, mixed-shape or empty attribute records');
    $maps = new class {
        public $reads = 0, $filter, $rows;
        function setFilters($filter) { $this->filter = $filter; }
        function search() {
            ++$this->reads;
            check($this->filter['itemId'] === 42 && $this->filter['attributeId'] === 3, 'Image filter context');
            return $this->rows[$this->filter['valueId']];
        }
    };
    $images = new class {
        public $reads = [], $request = 0, $bytes = 0;
        function show($id) {
            $this->reads[] = $id;
            $result = $id === 998 ? null : ['id'=>$id,'urlMiddle'=>$this->request.'/'.$id];
            $this->bytes += strlen(json_encode($result));
            return $result;
        }
    };
    $GLOBALS['services'] = [
        \Plenty\Modules\Item\Attribute\Contracts\AttributeValueImageRepositoryContract::class=>$maps,
        \Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract::class=>$images,
        \Plenty\Modules\Authorization\Services\AuthHelper::class=>new class {function processUnguarded($read) {return $read();}}
    ];
    $previous = null;
    if (isset($argv[1])) {
        require $argv[1];
        $previous = (new ReflectionClass(\GuardiansBasic\Extensions\PreviousBaseExtension::class))->newInstanceWithoutConstructor();
    }
    $rows = [];
    foreach ([1,12,24,50,100] as $count) {
        foreach (['shared','distinct','mixed'] as $shape) {
            ++$images->request;
            $maps->rows = $values = $expected = $allReads = [];
            foreach (range(1,$count) as $valueId) {
                $values[$valueId] = 'Value '.$valueId;
                $ids = $shape === 'shared' ? [100] : [$valueId];
                if ($shape === 'mixed') {
                    $sequence = [[], [null], [17, 18], [17, null], [998, 998], [17, 18, 17]];
                    $ids = $sequence[$valueId % count($sequence)];
                }
                $maps->rows[$valueId] = array_map(fn($id)=>['imageId'=>$id],$ids);
                $allReads = array_merge($allReads,array_values(array_filter($ids)));
                $last = $ids ? end($ids) : null;
                $expected[$valueId] = $last === null ? '' : ($last === 998 ? null : ['id'=>$last,'urlMiddle'=>$images->request.'/'.$last]);
            }
            $input = [3=>['values'=>$values]];
            $maps->reads = 0; $images->reads = []; $images->bytes = 0;
            $after = $lookup->getImageAttributeList(42,$input);
            check($after === [3=>['values'=>$values,'images'=>$expected]], 'Image mapping, last assignment or request isolation');
            $distinct = array_values(array_unique($allReads));
            check($images->reads === $distinct && $maps->reads === $count,'One lookup per distinct image, original value filters');
            $bytesAfter = strlen(json_encode($after));
            $returnedAfter = $images->bytes;
            $returnedBefore = null;
            if ($previous) {
                $maps->reads = 0; $images->reads = []; $images->bytes = 0;
                $before = $previous->getImageAttributeList(42,$input);
                check($before === $after && $images->reads === $allReads && $maps->reads === $count, 'Original repository calls and output');
                $returnedBefore = $images->bytes;
            }
            $rows[] = ['values'=>$count,'shape'=>$shape,'filteredSearches'=>$count,'readsBefore'=>count($allReads),
                'readsAfter'=>count($distinct),'repositoryFixtureBytesBefore'=>$returnedBefore,
                'repositoryFixtureBytesAfter'=>$returnedAfter,'outputBytes'=>$bytesAfter];
        }
    }
    echo json_encode(['comparedToOriginal'=>(bool)$previous,'cases'=>$rows],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
}
