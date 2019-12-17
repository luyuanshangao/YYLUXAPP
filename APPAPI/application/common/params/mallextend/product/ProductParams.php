<?php
namespace app\common\params\mallextend\product;

/**
 * 产品相关校验类
 * Class ProductParams
 * @author tinghu.liu 2018/06/21
 * @package app\common\params\mallextend\product
 */
class ProductParams
{
    /**
     * 同步处理SKU库存和SPU销量数据数据校验
     * @return array
     */
    public function synInventoryAndSalesCountsRules()
    {
        return[
            ['spu_id','require|integer'],
            ['sku_id','require|integer'],
            ['sku_inventory','require|integer','sku扣减库存不能为空|sku扣减库存量必须为整数'],
        ];
    }

    /**
     * 更新产品活动数据【定时任务】数据校验
     * @return array
     */
    public function updateActivityFortaskRules()
    {
        return[
            ['product_id_arr','require']
        ];
    }

    /**
     * 根据多个产品ID获取产品数据数据校验
     * @return array
     */
    public function getPruductDataByIdsRules()
    {
        return[
            ['product_id_arr','require']
        ];
    }

    /**
     * 获取产品浏览历史数据【my使用】数据校验
     * @return array
     */
    public function getProductViewHistoryDataForMyRules()
    {
        return[
            ['product_id_arr','require'],
            ['category_id','integer'],
            ['product_status','integer'],
        ];
    }

    public function getProductListsByCategory()
    {
        return[
            ['first_category','require'],
        ];
    }

    public function getProductRecommendations()
    {
        return[
            ['lang','require']
        ];
    }

    public function updateSalesRank()
    {
        return[
            ['product_id','require'],
            ['sales_rank','require']
        ];
    }

    public function historyRule()
    {
        return[
            ['startTime','require']
        ];
    }

    /**
     * 产品拆分参数校验
     * @return array
     */
    public function splitProductRule()
    {
        return[
            ['product_id','require'],
            ['store_id','require'],
            ['data','require'],
        ];
    }

    /**
     * 产品拆分详情参数校验
     * @return array
     */
    public function splitProductDataRule()
    {
        return[
            ['title','require'],
            ['sku_codes','require'],
        ];
    }
}