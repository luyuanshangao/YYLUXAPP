<?php
namespace app\common\params\orderfrontend;

/**
 * 追踪号参数校验类
 * Class TrackingNumberParams
 * @author tinghu.liu 2018/06/08
 * @package app\common\params\orderfrontend
 */
class TrackingNumberParams
{
    /**
     * 接收追踪号数据，同步到订单数据库数据校验
     * @return array
     */
    public function postRules(){
        return [
            ['order_number','require'],
            //['weight','require'],

            //['shipping_fee','require|float'],
            //['triff_fee','require|float'],
            //['service_per_charge','require|float'],
            //['service_charge','require|float'],
            //['total_amount','require|float'],

            //['pic_path_when_check','require'],
            //['pic_path_when_weigh','require'],
            //['package_number','require'],
            //['tracking_number','require']
        ];
    }
    /**
     * 接收追踪号数据，同步到订单数据库数据校验【产品】
     * @return array
     */
    public function postItemRules(){
        return [
            ['sku_id','require|integer'],
            ['sku_qty','require|integer'],
        ];
    }

    /**
     * 接收追踪号数据，同步到订单数据库数据校验
     * @return array
     */
    public function syncPostRules(){
        return [
            ['sign','require'],
            ['tracking_number','require'],
            ['order_number','require'],
            ['shipping_channel_name','require'],//具体的运输渠道名称
            ['item_info','require'],
        ];
    }

    /**
     * 接收追踪号数据，同步到订单数据库数据校验【产品】
     * @return array
     */
    public function syncPostItemRules(){
        return [
            ['sku_id','require|integer'],
            ['sku_qty','require|integer'],
        ];
    }


}