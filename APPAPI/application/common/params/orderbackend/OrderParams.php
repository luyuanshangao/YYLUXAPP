<?php
namespace app\common\params\orderbackend;

/**
 * 订单参数校验类
 * Class OrderParams
 * @author tinghu.liu 2018/4/23
 * @package app\common\params\orderbackend
 */
class OrderParams
{
    /**
     * 获取订单数据校验
     *
     * 参数有：
     * product_name
     * order_number
     * create_on_start
     * create_on_end
     * customer_name
     * order_status
     *
     * page_size
     * page
     * path
     *
     * @return array
     */
    public function getOrderDataRules(){
        return [
            ['store_id','require|integer','store_id不能为空|store_id必须为整型'],

            ['order_status','integer','order_status必须为整型'],
            ['create_on_start','integer','create_on_start必须为时间戳'],
            ['create_on_end','integer|>=:create_on_start','create_on_start必须为时间戳'],

            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 订单状态数量
     * @return array
     */
    public function getOrderStatusNumRules(){
        return [
            ['store_id','require|integer','store_id不能为空|store_id必须为整型'],
        ];
    }

    /**
     * 修改价格规则校验
     * @return array
     */
    public function updateOrderPriceRules(){
        return [
            //要修改的订单ID
            ['order_id','require|integer','order_id字段不能为空|order_id必须为整型'],
            ['change_user_id','require|integer','change_user_id字段不能为空|change_user_id必须为整型'],
            ['change_user_name','require','change_user_name字段不能为空'],
            ['change_user_ip','integer','change_user_ip字段需要转换为整型'],
            //修改前的价格
            ['grand_total','require','grand_total参数不能为空'],
            //修改后的价格
            ['grand_total_changed','require','grand_total_changed参数不能为空'],
            //修改后的价格【转换为美元后】
            ['USD_captured_amount_changed','require','USD_captured_amount_changed参数不能为空'],
            //修改原因
            ['change_reason','require','change_reason参数不能为空'],
        ];
    }

    /**
     * 获取订单详情参数规则校验
     * @return array
     */
    public function getOrderInfoRules(){
        return [
            //订单ID
            ['order_id','require|integer','order_id字段不能为空|order_id必须为整型'],
        ];
    }

    /**
     * 更新订单备注参数规则校验
     * @return array
     */
    public function updateOrderRemarkRules(){
        return [
            //订单ID
            ['order_id','require|integer','order_id字段不能为空|order_id必须为整型'],
            ['remark','require','remark字段不能为空'],
        ];
    }

    /**
     * 增加订单留言信息参数规则校验
     * @return array
     */
    public function addOrderMessageRules(){
        return [
            //订单ID
            ['order_id','require|integer','order_id字段不能为空|order_id必须为整型'],
            ['message_type','require|integer','message_type字段不能为空|message_type必须为整型'],
            ['message','require','message字段不能为空'],
            ['statused','require','statused字段不能为空'],
            ['create_on','require','create_on字段不能为空'],
        ];
    }

    /**
     * 修改订单留言信息状态参数规则校验
     * @return array
     */
    public function updateOrderMessageStatusRules(){
        return [
            //订单ID
            ['order_id','require|integer','order_id字段不能为空|order_id必须为整型'],
            ['statused','require|integer','statused字段不能为空|statused必须为整型'],
        ];
    }

    /**
     * 修改订单留言信息状态参数规则校验
     * @return array
     */
    public function orderRefunGetListsRules(){
        return [
            ['store_id','require|integer','store_id不能为空|store_id必须为整型'],

            ['type','integer'],
            ['create_on_start','integer'],
            ['create_on_end','integer|>=:create_on_start'],
            ['status','integer'],
            ['count_down_type','integer'],
            ['is_platform_intervention','integer'],

            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 更新订单退款退货换货数据规则校验
     * @return array
     */
    public function updateApplyDataRules(){
        return [
            ['after_sale_id','require|integer','after_sale_id不能为空|after_sale_id必须为整型'],
            ['edit_time','integer|integer'],
        ];
    }

    /**
     * 获取RAM提交数据规则校验
     * @return array
     */
    public function getRamPostDataRules(){
        return [
            ['after_sale_id','require|integer','after_sale_id不能为空|after_sale_id必须为整型'],
        ];
    }

    /**
     * 增加订单售后申请操作记录规则校验
     * @return array
     */
    public function addApplyLogDataRules(){
        return [
            ['after_sale_id','require|integer','after_sale_id不能为空|after_sale_id必须为整型'],
            ['title','require'],
            ['log_type','integer'],
            ['user_type','require'],
            ['user_id','require'],
            ['user_name','require'],
            ['content','require'],
            ['add_time','integer|integer'],
        ];
    }

    /**
     * 修改订单留言信息状态参数规则校验
     * @return array
     */
    public function getComplaintListsRules(){
        return [
            ['store_id','require|integer','store_id不能为空|store_id必须为整型'],

            ['after_sale_type','integer'],
            ['complaint_status','integer'],
            ['create_on_start','integer','create_on_start必须为时间戳'],
            ['create_on_end','integer|>=:create_on_start','create_on_start必须为时间戳'],

            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 获取一个月内所有成功支付订单规则校验
     * @return array
     */
    public function getOrderTimeRules(){
        return [
            ['create_on_start','integer','create_on_start必须为时间戳'],
            ['create_on_end','integer|>=:create_on_start','create_on_start必须为时间戳'],
        ];
    }

    /**
     * 生成换货订单规则校验
     * @return array
     */
    public function createRmaOrderRules(){
        return [
            ['after_sale_id','require|integer'],
            ['order_id','require'],
            ['price','require'],
        ];
    }

    /**
     * 生成换货订单(产品数据)规则校验
     * @return array
     */
    public function createRmaOrderProductRules(){
        return [
            ['product_id','require|integer'],
            ['sku_id','require|integer'],
            ['sku_code','require'],
            ['sku_nums','require|integer'],
        ];
    }

    /**
     * 生成换货订单(产品数据)规则校验
     * @return array
     */
    public function refundOrderRules(){
        return [
            //售后单ID，来至seller退款
            ['after_sale_id','integer'],
            //订单ID
            ['order_id','require|integer'],

            //退款来源:1-seller售后退款；2-my退款；3-admin退款
            ['refund_from','require|integer'],
            //退款类型：1-陪保退款；2-售后退款；3-订单取消退款
            ['refund_type','require|integer'],
            //操作人类型：1-admin，2-seller，3-my
            ['operator_type','require|integer'],
            //操作人ID
            ['operator_id','require|integer'],
            //操作人名称
            ['operator_name','require'],
        ];
    }

    /**
     * 生成换货订单(产品数据)规则校验【admin】
     * @return array
     */
    public function refundOrderAdminRules(){
        return [
            ['amount','require'],
        ];
    }

    /**
     * 生成换货订单(产品数据)规则校验
     * @return array
     */
    public function updateOrderStatusForAutomaticPraiseRules(){
        return [
            ['to_order_status','require'],
            ['order_ids','require'],
            ['order_status_change_arr','require']
        ];
    }

    /**
     * 根据父级订单ID获取数据规则校验
     * @return array
     */
    public function getOrderDataByOrderMasterNumberRules(){
        return [
            ['order_master_number','require'],
        ];
    }

    /**
     * 下载订单数据规则校验
     * @return array
     */
    public function downloadOrderRules(){
        return [
            ['store_id','require|integer', '参数错误'],
            ['download_sign','require', '无访问权限'],
        ];
    }

    /**
     * 上传订单包裹数据规则校验
     * @return array
     */
    public function uploadPackageRules(){
        return [
            ['store_id','require|integer', '参数错误'],
            ['download_sign','require', '无访问权限'],
        ];
    }

    /**
     * 统计订单数量，用于后台用户管理
     * @return array
     */
    public function getAdminCustomerOrder(){
        return [
            ['customer_id','require|integer','customer_id不能为空|customer_id必须为整型'],
        ];
    }
}