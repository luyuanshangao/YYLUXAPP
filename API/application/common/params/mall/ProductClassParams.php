<?php
namespace app\common\params\mall;

class ProductClassParams
{
    /**
     * getProduct数据校验
     * @return array
     */
    public function Rules()
    {
        return[
            ['lang','require'],
        ];
    }


    public function getClassRules()
    {
        return[
            ['pid','require'],
        ];
    }

    public function selectClassRules()
    {
        return[
            ['class_id','require'],
            ['lang','require']
        ];
    }

    public function getErpClassIdByClassIdRules()
    {
        return[
            ['class_id','require']
        ];
    }
}