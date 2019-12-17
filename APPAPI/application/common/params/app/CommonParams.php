<?php
namespace app\common\params\app;

class CommonParams
{
    /**
     * saveCustomerFilter数据校验
     * @return array
     */
    public function saveCustomerFilterRules()
    {
        return[
            ['CustomerID','require'],
            ['BlackCategoryIds','require'],
        ];
    }


}