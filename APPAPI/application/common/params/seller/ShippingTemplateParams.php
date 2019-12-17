<?php
namespace app\common\params\seller;

/**
 * 开发：钟宁
 * 功能：运费模板
 * 时间：2018-08-07
 */
class ShippingTemplateParams
{
    /**
     * 运费模板数据
     * @return array
     */
    public function listRules()
    {
        return[
            ['seller_id','require|integer'],
        ];
    }
}