<?php
/**
 * 订单相关映射配置
 * @author tinghu.liu
 * 2018-06-14
 */
return [
    /**
     * OMS订单状态 和 新平台order订单状态 映射数据配置
     * 1、OMS订单状态：
     *      0:等待付款;1:部分付款;2:收到付款;3:付款确认;
     *      30:订单收到，未处理;31:备货中;32:发货处理中;33:部分发货;34:已发货;35:已完成;36:已取消;
     *      60:正常，未加锁;61:客户正在修改，超时将自动解锁;62:客服正在修改，超时将自动解锁;63:正在联系客户，超时将自动解锁;
     *      64:正在生成发货批次，超时将自动解锁;
     *      65:PayPal 纠纷事件，不超时，需手动解锁,纠纷事件,不区分PayPal或EGP,统一用 Dispute;
     *      66:PayPal 索赔事件，不超时，需手动解锁,索赔事件,不区分PayPal或EGP,统一用 Claim;
     *      67:PayPal 退单事件，不超时，需手动解锁,退单事件,不区分PayPal或EGP,统一用 Chargeback;
     *      68:EGP 纠纷事件，不超时，需手动解锁;
     *      69:EGP 退单事件，不超时，需手动解锁;
     *      70:付款处理中;
     *      71:涉嫌欺诈;
     *      72:定性欺诈;
     *      73:强制锁住，需手动解锁;
     *
     * 2、新平台order订单状态：
     *      ['code'=>100,'name'=>'等待付款','en_name'=>'Pending Payment'],
            ['code'=>101,'name'=>'进入事前风控','en_name'=>'Before Control'],
            ['code'=>102,'name'=>'通过事前风控','en_name'=>'Complete Control'],
            ['code'=>103,'name'=>'第三方支付验证','en_name'=>'Payment validation'],
            ['code'=>104,'name'=>'第三方支付验证通过','en_name'=>'Pending Payment'],
            ['code'=>105,'name'=>'进入事后风控','en_name'=>'After Control'],
            ['code'=>106,'name'=>'通过事后风控','en_name'=>'End Control'],
            ['code'=>107,'name'=>'进入人工风控','en_name'=>'Artificial Control'],
            ['code'=>108,'name'=>'通过人工风控','en_name'=>'Artificial through'],
            ['code'=>109,'name'=>'进入付款页面失败','en_name'=>'Payment Confirmed'],
            ['code'=>110,'name'=>'涉嫌欺诈','en_name'=>'FraudSuspected'],
            ['code'=>111,'name'=>'定性欺诈','en_name'=>'FraudAdjusted'],
     *
     *       ['code'=>120,'name'=>'付款确认中','en_name'=>'Payment Processing'],
            ['code'=>200,'name'=>'付款完成','en_name'=>'Payment Confirmed'],

            ['code'=>400,'name'=>'待发货','en_name'=>'Shipment Processing'],//erp
            ['code'=>401,'name'=>'部分发货','en_name'=>'Seller Partial'],//erp
            ['code'=>402,'name'=>'已发货','en_name'=>'Seller Shipped'],//erp
            ['code'=>403,'name'=>'已收货','en_name'=>'Full Received'],//erp
            ['code'=>404,'name'=>'入库','en_name'=>'In Storage'],//erp
            ['code'=>405,'name'=>'发货超期','en_name'=>'Over Time'],
            ['code'=>406,'name'=>'订单超期','en_name'=>'Order Delay'],
            ['code'=>500,'name'=>'部分发货','en_name'=>'Partial Shipped'],
            ['code'=>600,'name'=>'已发货','en_name'=>'Full Shipped'],
            ['code'=>700,'name'=>'待收货','en_name'=>'Unreceived'],
            ['code'=>800,'name'=>'已到货','en_name'=>'Order Received'],
            ['code'=>900,'name'=>'已完成','en_name'=>'Completed'],
            ['code'=>901,'name'=>'申请退货','en_name'=>'Application back'],
            ['code'=>902,'name'=>'退货中','en_name'=>'In return'],
            ['code'=>903,'name'=>'确认收到退货','en_name'=>'Received return'],
            ['code'=>904,'name'=>'退货单完成','en_name'=>'Complete Return'],
            ['code'=>905,'name'=>'取消退货申请','en_name'=>'Cancel return'],
            ['code'=>906,'name'=>'申请换货','en_name'=>'Application exchange'],//换货
            ['code'=>907,'name'=>'退货中','en_name'=>'In Exchange'],//换货
            ['code'=>908,'name'=>'确认收到退货','en_name'=>'Received exchange'],//换货
            ['code'=>909,'name'=>'换货单发货','en_name'=>'New Order'],//换货
            ['code'=>910,'name'=>'取消换货申请','en_name'=>'Cancel Exchange'],//换货
            ['code'=>1000,'name'=>'待评价','en_name'=>'Not evaluated'],
            ['code'=>1100,'name'=>'已评价','en_name'=>'Complete evaluate'],
            ['code'=>1200,'name'=>'待追评','en_name'=>'Keep evaluate'],
            ['code'=>1300,'name'=>'已追评','en_name'=>'Keep evaluate'],
            ['code'=>1400,'name'=>'订单取消','en_name'=>'Cancelled'],
            ['code'=>1500,'name'=>'等待','en_name'=>'Hold'],
            ['code'=>1600,'name'=>'索赔','en_name'=>'Claim'],
            ['code'=>1700,'name'=>'纠纷中','en_name'=>'Disputes'],
            ['code'=>1800,'name'=>'争端订单','en_name'=>'Conflict'],
            ['code'=>1900,'name'=>'已关闭','en_name'=>'Closed'],
     */
    'oms_order_mapping_data' => [
        [
            'oms_status'=>0, //OMS订单状态
            'order_status'=>100 //新平台order对应订单状态 下同
        ],
        [
            'oms_status'=>1,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>2,
            'order_status'=>105
        ],
        [
            'oms_status'=>3,
            'order_status'=>200
        ],
        [
            'oms_status'=>30,
            'order_status'=>100
        ],
        [
            'oms_status'=>31,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>32,
            'order_status'=>400
        ],
        [
            'oms_status'=>33,
            'order_status'=>500
        ],
        [
            'oms_status'=>34,
            'order_status'=>600
        ],
        [
            'oms_status'=>35,
            'order_status'=>900
        ],
        [
            'oms_status'=>36,
            'order_status'=>1400
        ],
        [
            'oms_status'=>60,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>61,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>62,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>63,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>64,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>65,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>66,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>67,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>68,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>69,
            'order_status'=>-1 //新平台没有
        ],
        [
            'oms_status'=>70,
            'order_status'=>120
        ],
        [
            'oms_status'=>71,
            'order_status'=>110
        ],
        [
            'oms_status'=>72,
            'order_status'=>111
        ],
        [
            'oms_status'=>73,
            'order_status'=>-1 //新平台没有
        ],
    ],

    /**
     * OMS支付（Payment）状态 和 新平台order支付（Payment）状态 映射数据配置
     * 1、OMS支付（Payment）状态：
     *    0:等待付款;1:部分付款;2:收到付款;3:付款确认
     * 2、新平台order支付（Payment）状态：
     *    100:待付款;101:事前风控;102:通过事前风控;103:第三方支付验证;104:第三方支付验证通过;105:事后风控;106:通过事后风控;107:人工风控;108:通过人工风控;109:支付失败(待付款);110:涉嫌欺诈;111:定性欺诈;180:部分付款(不存在);200:全部付款;300:付款处理中
     */
    'oms_order_pay_mapping_data' => [
        [
            'oms_pay_status'=>0, //OMS支付状态
            'order_pay_status'=>100 //新平台order对应支付状态 下同
        ],
        [
            'oms_pay_status'=>1,
            'order_pay_status'=>-1 //新平台没有
        ],
        [
            'oms_pay_status'=>2,
            'order_pay_status'=>300
        ],
        [
            'oms_pay_status'=>3,
            'order_pay_status'=>200
        ],
    ],

    /**
     * OMS发货状态 和 新平台order发货状态 映射数据配置
     * 1、OMS发货状态：
     *    30:订单收到，未处理;31:备货中;32:发货处理中;33:部分发货;34:已发货;35:已完成;36:已取消
     * 2、新平台order发货状态：
     *    400:待发货;401:部分发货(Seller);402:全部发货(Seller);403:已收货(Seller);404:已入库;405:发货超期;500:部分发货;600:全部发货;
     */
    'oms_order_fulfillment_mapping_data' => [
        [
            'oms_fulfillment_status'=>30, //OMS发货状态
            'order_fulfillment_status'=>400 //新平台order对应发货状态 下同
        ],
        [
            'oms_fulfillment_status'=>31,
            'order_fulfillment_status'=>406
        ],
        [
            'oms_fulfillment_status'=>32,
            'order_fulfillment_status'=>-1 //新平台没有
        ],
        [
            'oms_fulfillment_status'=>33,
            'order_fulfillment_status'=>500
        ],
        [
            'oms_fulfillment_status'=>34,
            'order_fulfillment_status'=>600
        ],
        [
            'oms_fulfillment_status'=>35,
            'order_fulfillment_status'=>600
        ],
        [
            'oms_fulfillment_status'=>36,
            'order_fulfillment_status'=>-1 //新平台没有
        ],
    ],
];