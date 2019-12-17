<?php
namespace app\common\params\orderfrontend;

/**
 * 订单与OMS交互接口参数校验类
 * Class SynOmsParams
 * @author tinghu.liu 2018/06/14
 * @package app\common\params\orderfrontend
 */
class SynOmsParams
{
    /**
     * 同步OMS订单至Order数据校验
     * @return array
     */
    public function orderStatusRules(){
        return [
            /*['order_number','require'],
            ['order_status','require|integer'],*/
            ['OrderNumber','require'],
            ['Status','require|integer'],
        ];
    }
    /**
     * 同步交易明细至Order
     * @return array
     */
    public function salesDetailRules(){
        return [
            ['order_number','require'],
            ['notification_id','require'],
            //['txn_data','require'],
            //['parent_txn_ref','require'],
            ['amount','require'],
            ['currency_code','require'],
            ['txn_type','require'],
            //['notes','require'],
            //['third_party_txn_id','require'],
            //['third_party_parent_txn_id','require'],
            ['third_party_method','require'],
            //['txn_result','require'],
            //['risk_control_status','require'],
            ['payment_method','require'],
            //['payment_txn_id','require'],
            //['payment_parent_txn_id','require'],
            //['refunding_amount','require'],
        ];
    }

    /**
     * 获取产品销量数据校验
     * @return array
     */
    public function getProductSalesRules(){
        return [
            ['start_date','require'],
            ['end_date','require'],
        ];
    }


}