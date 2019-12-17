<?php
namespace app\common\params\seller;

/**
 * 批发询价接口数据校验类
 * Class WholesaleInquiryParams
 * @author tinghu.liu 2018/06/11
 * @package app\common\params\seller
 */
class WholesaleInquiryParams
{
    /**
     * 新增数据校验
     * @return array
     */
    public function addDataRules()
    {
        return[
            ['seller_id','require|integer'],
            ['product_id','require|integer'],
            ['sku_id','require|integer'],
            //订货量
            ['order_quantity','require'],
            //询价者电子邮件地址
            ['email_address','require'],
            //询价者国家
            ['country','require'],
            //送货方式。1-Standard，2-Expedited，3-Other(e.g By own fowarder)
            ['shipping_method','require|integer'],
            //询价详情
            ['details','require'],
        ];
    }
}