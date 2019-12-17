<?php

return [
    /** 生成订单后的支付过期时间（单位：天）**/
    'order_pay_expire_day'=>5,

    /** 买家支付后发货时间限制（单位：天）**/
    'delivery_time_limit_day'=>5,

    /** 提醒买家确认收货的时间限制（单位：天）**/
    'buyer_confirm_take_delivery_limit_day'=>60,

    /** CDN链接地址 **/
    //'cdn_url'=>'http://scs.dxcdn.com/Phoenix',
    'cdn_url'=>'//img.dxcdn.com',

    /*订单交易完成后可评价限制（单位：天）*/
    'order_review_limit_day'=>15,

    /*订单交易完成后可追加评价限制（单位：天）（未评价）*/
    'append_review_limit_day'=>30,


    /*订单交易完成后可追加评价限制（单位：天）（已评价）*/
    'append_have_review_limit_day'=>10,

    /*订单评论后卖家回复限制（单位：天）*/
    'reply_review_limit_day'=>30,

    /** 订单售后管理状态限制天数（一）（单位：天）**/
    'order_after_sale_status_limit_day1'=>15,

    /** 订单售后管理状态限制天数（二）（单位：天）**/
    'order_after_sale_status_limit_day2'=>30,

    /*用户评论 2-评论积分 2.1  评论-订单完成后，30天内评论单价≥$2的产品，可获得1积分（每个产品最多1个积分）；*/
    /*商品多少钱兑换1积分（单位：美元）*/
    'one_point_exchange'=>2,
    /*订单状态*/
    'order_status'=>[
        ['code'=>100,'name'=>'等待付款','en_name'=>'Pending Payment'],
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
        ['code'=>120,'name'=>'付款确认中','en_name'=>'Payment Processing'],
        ['code'=>200,'name'=>'付款完成','en_name'=>'Payment Confirmed'],
        ['code'=>300,'name'=>'付款处理中','en_name'=>'Risk Control Processing'],
        ['code'=>400,'name'=>'待发货','en_name'=>'Shipment Processing'],//erp
        ['code'=>401,'name'=>'部分发货','en_name'=>'Seller Partial'],//erp
        ['code'=>402,'name'=>'已发货','en_name'=>'Seller Shipped'],//erp
        ['code'=>403,'name'=>'已收货','en_name'=>'Full Received'],//erp
        ['code'=>404,'name'=>'入库','en_name'=>'In Storage'],//erp
        ['code'=>405,'name'=>'发货超期','en_name'=>'Over Time'],
        ['code'=>406,'name'=>'订单超期','en_name'=>'Order Delay'],
        ['code'=>500,'name'=>'部分发货','en_name'=>'Partial Shipped'],
        ['code'=>600,'name'=>'已发货','en_name'=>'Full Shipped'],
        ['code'=>700,'name'=>'待收货','en_name'=>'Awaiting Delivery'],
        ['code'=>800,'name'=>'已到货','en_name'=>'Full Shipment'],
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
        ['code'=>1000,'name'=>'待评价','en_name'=>'Awaiting Comment'],
        ['code'=>1100,'name'=>'已评价','en_name'=>'Complete evaluate'],
        ['code'=>1200,'name'=>'待追评','en_name'=>'Keep evaluate'],
        ['code'=>1300,'name'=>'已追评','en_name'=>'Keep evaluate'],
        ['code'=>1400,'name'=>'订单取消','en_name'=>'Cancelled'],
        ['code'=>1500,'name'=>'等待','en_name'=>'Hold'],
        ['code'=>1600,'name'=>'索赔','en_name'=>'Claim'],
        ['code'=>1700,'name'=>'纠纷中','en_name'=>'Disputes'],
        ['code'=>1800,'name'=>'争端订单','en_name'=>'Conflict'],
        ['code'=>1900,'name'=>'已关闭','en_name'=>'Closed'],
    ],
    /*
     * 订单取消原因
     * */
    "order_cancel_reason"=>[
        ['code'=>1,'name'=>'我不再需要这个订单了','en_name'=>'I do not want this order anymore'],
        ['code'=>2,'name'=>'我想更改这个订单','en_name'=>'I want to change this order'],
        ['code'=>3,'name'=>'我想更配送地址。','en_name'=>'I want to change the shipping address in this order'],
        ['code'=>4,'name'=>'我想更改这个订单的配送方式。','en_name'=>'I want to change the coupon I used for this order'],
        ['code'=>5,'name'=>'我不再需要这个订单了','en_name'=>'I want to change the shipping method for this order'],
        ['code'=>6,'name'=>'付款不成功','en_name'=>'Payment unsuccessful'],
        ['code'=>7,'name'=>'其他原因','en_name'=>'Other reasons'],
    ],

    /*************** 售后订单相关配置 start *********************/

    /*
     * 售后状态
     * */
    "after_sale_status"=>[
        ['code'=>1,'name'=>'申请待处理','en_name'=>'Apply for treatment'],
        ['code'=>2,'name'=>'待买家发货','en_name'=>'Delivery to the seller'],
        ['code'=>3,'name'=>'待卖家收货','en_name'=>'Wait for the seller to collect the goods'],
        ['code'=>4,'name'=>'换货成功','en_name'=>'Exchange Success'],
        ['code'=>5,'name'=>'退款成功','en_name'=>'Refunds Success'],
        ['code'=>6,'name'=>'仲裁处理中','en_name'=>'In arbitration'],
        ['code'=>7,'name'=>'已拒绝申请','en_name'=>'Have refused to apply for'],
        ['code'=>8,'name'=>'申请失败','en_name'=>'Application failure'],
        ['code'=>9,'name'=>'退款中','en_name'=>'Refunding'],
        ['code'=>10,'name'=>'关闭','en_name'=>'Close'],
    ],

    /*
     * 售后类型
     * 选择换货时，售后原因：---货物损坏、货不对版
选择退货时，售后原因：---货物损坏、功能不全、少配件、货不对版
选择退款时，售后原因：不想要/买错/买多、协商一致退款、缺货、未按约定时间发货、其他
     * */
    "after_sale_type"=>[
        ['code'=>1,'name'=>'换货','en_name'=>'Replacement',
            "reason"=>[ //售后原因
                ['code'=>1,'name'=>'货物损坏','en_name'=>'Cargo damage'],
                ['code'=>2,'name'=>'货不对版','en_name'=>'Non version of goods']
            ],
        ],
        ['code'=>2,'name'=>'退货','en_name'=>'Return',
            "reason"=>[
                ['code'=>1,'name'=>'货物损坏','en_name'=>'Cargo damage'],
                ['code'=>2,'name'=>'功能不全','en_name'=>'Dysfunction'],
                ['code'=>3,'name'=>'少配件','en_name'=>'Spare parts'],
            ],
        ],
        ['code'=>3,'name'=>'退款','en_name'=>'Refund',
            "reason"=>[
                ['code'=>1,'name'=>'不想要/买错/买多','en_name'=>'Do not want / buy wrong / buy more'],
                ['code'=>2,'name'=>'协商一致退款','en_name'=>'Unanimous refunds'],
                ['code'=>3,'name'=>'缺货','en_name'=>'Out of stock'],
                ['code'=>4,'name'=>'未按约定时间发货','en_name'=>'Not shipped at the agreed time'],
                ['code'=>5,'name'=>'其他','en_name'=>'Other']
            ],
            //退款类型（当售后类型after_sale_type为3时候）
            "refunded_type"=>[
                ['code'=>1,'name'=>'仅退款','en_name'=>'Only refunds'],
                ['code'=>2,'name'=>'退货并退款','en_name'=>'Return and refund'],
                ['code'=>3,'name'=>'不退货退款','en_name'=>'Non refundable refund'],
            ],
        ],
    ],

    /*
     * 退款类型（当售后类型after_sale_type为3时候）
     * */
    "refunded_type"=>[
        ['code'=>1,'name'=>'仅退款','en_name'=>'Only refunds'],
        ['code'=>2,'name'=>'退货并退款','en_name'=>'Return and refund'],
        ['code'=>3,'name'=>'不退货退款','en_name'=>'Non refundable refund'],
    ],

    /*************** 售后订单相关配置 end *********************/

    /*
     * 纠纷订单状态
     * */
    "complaint_status"=>[
        ['code'=>1,'name'=>'待处理','en_name'=>'To be treated'],
        ['code'=>2,'name'=>'卖家退款','en_name'=>"Seller refund"],
        ['code'=>3,'name'=>'生成退货单','en_name'=>'Generating refund list'],
        ['code'=>4,'name'=>'申请失败','en_name'=>'Application failure'],
    ],

    /*
     * 售后原因
     * */
    "after_sale_reason"=>[
        ['code'=>1,'name'=>'不想要/买错/买多','en_name'=>'Do not want / buy wrong / buy more'],
        ['code'=>2,'name'=>'协商一致退款','en_name'=>''],
        ['code'=>3,'name'=>'缺货','en_name'=>'Unanimous refunds'],
        ['code'=>4,'name'=>'未按约定时间发货','en_name'=>'Not shipped at the agreed time'],
        ['code'=>5,'name'=>'其他','en_name'=>'Other']
    ],

    /*
     * 订单投诉原因
     * */
    "accuse_reason"=>[
        ['code'=>1,'name'=>'卖家不退款','en_name'=>'The seller is not refundable'],
        ['code'=>2,'name'=>'卖家态度差','en_name'=>'A poor seller\'s attitude'],
        ['code'=>3,'name'=>'卖家恶意骚扰','en_name'=>'The seller maliciously harassed'],
        ['code'=>4,'name'=>'未按约定时间发货','en_name'=>'Not shipped at the agreed time'],
        ['code'=>5,'name'=>'卖家承诺没做到','en_name'=>'The seller promised not to do it'],
        ['code'=>6,'name'=>'卖家欺诈行为','en_name'=>"Seller's fraudulent behavior"]
    ],

    /**
     * 机器ID编号
     */
    'machine_id'	=>	'1001',

    /**
     * wsdl订单服务接口配置
     */
    'wsdl_order_url'=>[
        'lis_service_wsdl'=>
            [
                'url'=>'https://payment.dxqas.com/Service/v4.0/PaymentService.svc?wsdl',
                'user_name'=>'oms.go',
                'password'=>'Dx+12345',
                'options'=>[
                    'ssl'   => [
                        'verify_peer'          => false,
                        'allow_self_signed' => true
                    ],
                    'https' => [
                        'curl_verify_ssl_peer'  => false,
                        'curl_verify_ssl_host'  => false
                    ]
                ]
            ],
    ],
    /**
     * wsdl订单服务接口配置
     */
    'order_service_url'=>[
        'lis_service_wsdl'=>
            [
                'url'=>'https://svcml01.dxqas.com/oms/v4.5.5/OrderService.svc?wsdl',
                'user_name'=>'oms.go',
                'password'=>'Dx+12345',
                'options'=>[
                    'ssl'   => [
                        'verify_peer'          => false,
                        'allow_self_signed' => true
                    ],
                    'https' => [
                        'curl_verify_ssl_peer'  => false,
                        'curl_verify_ssl_host'  => false
                    ]
                ]
            ],
    ],
];