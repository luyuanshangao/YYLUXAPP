<?php
namespace app\common\params\mall;

class ProductParams
{
    /**
     * getProduct数据校验
     * @return array
     */
    public function getProductRules()
    {
        return[
            ['product_id','require'],
        ];
    }

    /**
     * newArrivals数据校验
     * @return array
     */
    public function newArrivalsRule()
    {
        return[
            ['isNewProduct','require'],
            ['lang','require'],
        ];
    }

    /**
     * 产品详情页
     * @return array
     */
    public function getProductInfoRules()
    {
        return[
            ['spu','require'],
        ];
    }

    /**
     * 二级分类下的产品数据
     * @return array
     */
    public function secProductRules()
    {
        return[
            ['lang','require'],
            ['firstCategory','require'],
            ['secondCategory','require']
        ];
    }

    /**
     * 浏览历史数据推荐
     * @return array
     */
    public function getViewHistoryRules()
    {
        return[
            ['spu','require'],
            ['lang','require'],
        ];
    }

    /**
     * 浏览历史数据推荐
     * @return array
     */
    public function getShippingRules()
    {
        return[
            ['spu','require'],
            ['country','require'],
        ];
    }

    /**
     * 评分
     * @return array
     */
    public function getRatingRules()
    {
        return[
            ['spu','require'],
        ];
    }

    /**
     * 评分
     * @return array
     */
    public function getSupReviewsRule()
    {
        return[
            ['product_id','require'],
        ];
    }

    /**
     * 获取noc类别ID
     * @return array
     */
    public function nocGetClassRules()
    {
        return[
            ['product_id','require'],
        ];
    }

    /**
     * spu列表
     * @return array
     */
    public function getList()
    {
        return[
            ['skus','require'],
        ];
    }

    /**
     * spu列表
     * @return array
     */
    public function getSpusList()
    {
        return[
            ['spus','require'],
        ];
    }


}