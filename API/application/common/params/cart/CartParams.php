<?php
namespace app\common\params\cart;

class CartParams
{
    /**
     * 添加购物车数据校验
     * @return array
     */
    public function getPayTypeRules()
    {
        return[
            ['Currency','require', 'The Currency is require.'],
        ];
    }
    
    /**
     * @return array
     */
    public function addTempCartKeyRules()
    {
        return[
            ['DataKey','require', 'The DataKey is require.'],
        ];
    }

}