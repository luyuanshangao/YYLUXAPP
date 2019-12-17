<?php

/**
 * 应用配置
 * @author tinghu.liu
 * @date 2018-05-29
 */

return [
    /**
     * 调用API地址
     */
    'api_base_url'=>'http://api.dxinterns.com',
//    'api_base_url'=>'http://localhost/DEV/API/public/index.php',

    /**
     * 联盟营销主推产品个数限制
     */
    'affiliate_main_product_num_limit'=>60,
    /*注册后赠送的积分*/
    'registration_bonus_points'=>50,
    /*取消订阅原因*/
    /*
     * 订单取消原因
     * */
    "subscriber_cancel_reason"=>[
        ['code'=>1,'name'=>'就像我们的交易，但不想收到那么多电子邮件？','en_name'=>"Like our deals but don't want to receive as many e-mails?"],
        ['code'=>2,'name'=>'价格太高','en_name'=>'Price is too high'],
        ['code'=>3,'name'=>'折扣不足。','en_name'=>'Discount is insufficient'],
        ['code'=>4,'name'=>'产品质量低劣','en_name'=>'Low-quality of products'],
        ['code'=>5,'name'=>'对我们的产品不感兴趣','en_name'=>'Not interested in our products'],
        ['code'=>6,'name'=>'电子邮件页面设计欠佳','en_name'=>'Poor Email page design'],
        ['code'=>7,'name'=>'更改电子邮件地址。','en_name'=>'Change email address.'],
    ],
    /**
     * 支付密码
     */
    'PaymentPasswordServicUrl'=>[
        'lis_service_wsdl'=>
            [
                'url'=>'https://cic.svc.tradeglobals.com/v5.0/PaymentPasswordService.svc?wsdl',
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
     * .net服务地址配置
     */
    'wsdl_url'=>[
        //邮件服务配置 邮件服务配置已经更新至线上，为了解决发送发货成功邮件失败问题 20190327 tinghu.liu
        'send_mail_service_wsdl'=>
            [
                //'url'=>'https://svcml01.dxqas.com/MSS/v4.0/Interface/SendMailService.svc?wsdl',
                'url'=>'https://mss2.tradeglobals.com:2445/Interface/SendOtherMailService.svc?wsdl',
                'user_name'=>'phoenix.go',
                'password'=>'JCQ.S6BWCj',
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
            ]

    ],

    /**
     * 运输方式配置【除了专线Exclusive】
     */
    'shipping_model_except_exclusive'=>[
        'Standard',
        'SuperSaver',
        'Expedited'
    ],

    /**
     * 最大发货时间（30天）
     */
    'max_delivery_time'=>30*24*60*60,

    /**
     * 最大发货时间（3天）
     */
    'max_delivery_time_mvp'=>3*24*60*60,

    //paypal支付请求payment缓存时间
    'pay_pal_submit_info_time' => 3600,

    //APP允许的支付方式
    'app_allow_pay_type'=>[
        'COD','sc','PayPal','CreditCard','WebMoney','Boleto-Astropay','Transfer-Astropay','IDeal'
    ],

    //APP允许的支付渠道
    'app_allow_pay_chennel'=>[
        'EGP','PayPal','Paypal','Asiabill','SC','Astropay'
    ],
];
