<?php
namespace app\common\params\mall;

/**
 * 参数校验 tinghu.liu 20191023
 * Class ProductVirtualParams
 * @package app\common\params\mall
 */
class ProductVirtualParams
{
    /**
     * 处理库存和销量处理数据校验
     * @return array
     */
    public function synInventoryAndSalesCountRules()
    {
        return[
            ['product_id','require', 'Parameter error.'],
            ['product_nums','require', 'Parameter error.'],
            ['order_number','require', 'Parameter error.'],
//            "flag":1, //回滚库存
//            ['flag','', 'Parameter error.'],
        ];
    }
}