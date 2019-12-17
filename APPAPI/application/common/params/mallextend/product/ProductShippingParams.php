<?php
namespace app\common\params\mallextend\product;

/**
 * 产品运费相关校验类
 * Class ProductParams
 * @author tinghu.liu 2018/06/21
 * @package app\common\params\mallextend\product
 */
class ProductShippingParams
{
    /**
     * 同步处理SKU库存和SPU销量数据数据校验
     * @return array
     */
    public function updateShippingRule()
    {
        return[
            ['product_id','require|integer'],
            ['template_id','require|integer'],
            ['template_name','require'],
        ];
    }

    /**
     * 同步处理SKU库存和SPU销量数据数据校验
     * @return array
     */
    public function getProductShippingRule()
    {
        return[
            ['product_id','require|integer'],
        ];
    }

}