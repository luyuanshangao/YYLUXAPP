<?php
namespace app\common\params\orderfrontend;

/**
 * 订单参数校验类
 * Class OrderParams
 * @author tinghu.liu 2018/4/23
 * @package app\common\params\orderbackend
 */
class OrderParams
{
    /**
     * 生成换货订单(产品数据)规则校验
     * @return array
     */
    public function refundOrderRules(){
        return [
            ['order_id','require|integer'],
            ['change_reason_id','require|integer'],
        ];
    }

    /**
     * 增加订单售后申请操作记录规则校验
     * @return array
     */
    public function addApplyLogDataRules(){
        return [
            ['after_sale_id','require|integer'],
            ['title','require'],
            ['log_type','integer'],
            ['user_type','require'],
            ['user_id','require'],
            ['user_name','require'],
            ['content','require']
        ];
    }

    /**
     * 更新订单退款退货换货数据规则校验
     * @return array
     */
    public function updateApplyDataRules(){
        return [
            ['after_sale_id','require|integer'],
            ['edit_time','integer|integer'],
        ];
    }

    /**
     * 取消仲裁数据规则校验
     * @return array
     */
    public function cancelArbitrationRules(){
        return [
            ['log_type','require|integer'],
            ['after_sale_id','require|integer'],
            ['title','require'],
            ['user_type','require|integer'],
            ['user_id','require|integer'],
            ['user_name','require'],
            ['content','require'],
        ];
    }

    /**
     * 获取买家订单数量数据规则校验
     * @return array
     */
    public function getOrderNumForUserRules(){
        return [
            ['customer_id','require|integer'],
        ];
    }

    /**
     * 改变订单状态数据规则校验
     * @return array
     */
    public function realChangeOrderStatusRules(){
        return [
            ['order_id','require|integer'],
            ['order_status_from','require|integer'],
            ['order_status','require|integer'],
            ['change_reason','require|length:1,200'],
            ['create_on','require|integer'],
            ['create_by','require'],
            ['create_ip','require'],
            ['chage_desc','require|length:1,500']
        ];
    }

    /**
     * 新改变订单状态数据规则校验
     * @return array
     */
    public function realChangeOrderStatusNewRules(){
        return [
            ['customer_id','require|integer'],
            ['order_number','require|integer'],
            ['order_status','require|integer'],
            ['change_reason','require'],
            /*['create_on','require|integer'],*/
            /*['create_by','require'],*/
            ['create_ip','require'],
            /*['chage_desc','require']*/
        ];
    }

    /**
     * 回退订单状态数据规则校验
     * @return array
     */
    public function rollbackOrderStatus(){
        return [
            ['after_sale_id','require|integer'],
            ['create_ip','require'],
            /*['chage_desc','require']*/
        ];
    }

    /**
     * 根据订单号获取订单数据规则校验
     * @return array
     */
    public function getOrderInfoByOrderNumberRules(){
        return [
            ['order_number','require']
        ];
    }

    /**
     * 根据订单号获取订单数据规则校验
     * @return array
     */
    public function getAllOrderDataByMasterNumberRules(){
        return [
            ['order_master_number','require']
        ];
    }

    /**
     * 根据订单号获取订单数据规则校验
     * @return array
     */
    public function getExportRefundOrder(){
        return [

        ];
    }

    /**
     * 根据主订单获取订单收货地址规则校验
     * @return array
     */
    public function getOrderAddressByOrderMasterNumberRules(){
        return [
            ['order_master_number','require']
        ];
    }

    /**
     * 根据PayToken获取订单基本信息规则校验
     * @return array
     */
    public function getOrderBaseInfoByPayTokenRules(){
        return [
            ['pay_token','require']
        ];
    }

    /**
     * 根据主单号获取支付token信息规则校验
     * @return array
     */
    public function getPayTokenInfoByOrderMasterNumberRules(){
        return [
            ['order_master_number','require']
        ];
    }

    /**
     * 根据主订单号更新repay次数数据规则校验
     * @return array
     */
    public function updateOrderRepayNumsByOrderMasterNumberRules(){
        return [
            ['order_master_number','require'],
            ['repay_nums','require|integer'],
        ];
    }

    /**
     * 根据主订单更新支付方式和支付渠道规则校验
     * @return array
     */
    public function updateOrderPaytypeAndChannelRules(){
        return [
            ['order_master_number', 'require'],
            ['pay_type', 'require'],
            ['pay_channel', 'require'],
        ];
    }

    /**
     * 根据主订单更新支付方式和支付渠道规则校验
     * @return array
     */
    public function updateOrderCurrencyRules(){
        return [
            ['order_master_number', 'require'],
            ['to_currency', 'require'],
        ];
    }
}