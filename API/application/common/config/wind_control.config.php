<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/4/10
 * Time: 11:54
 */
/*风控配置*/
return [
    /*存检查必填字段*/
    "WindControlVerification"=>["CICID","ShippingEmail","BillingEmail","ShippingAddress","BillingAddress","IPAddress","Bin","CardNumber"],
    /*风控类型*/
    "WindControlType"=>["黑名单","白名单"],
    "SpecialType"=>["CICID","Email","TokenHash","IP"],
    "RiskLevel"=>["Normal","High"],
    "status"=>["使用","禁用"],
    /*风控渠道*/
    "WindControlPaymentMethod"=>[
        "online"=>["egp","globebill","astropay","paypal"],
        "Offline"=>["webmoney","boleto-astropay","transfer-astropay","ideal"]
    ],
    /*3D付款频率规则*/
    /*"3drule"=>[
        "bin_daily"=>30,//
        "bin_monthly"=>100,
        "ip_daily"=>3,
        "ip_monthly"=>7,
        "cardholder_daily"=>3,
        "cardholder_monthly"=>7,
        "cart_number_daily"=>3,
        "cart_number_weekly"=>5,
        "cart_number_monthly"=>7,
        "cart_number_quarterly"=>15,
        "cart_number_halfyearly"=>30,
        "cart_number_yearly"=>60,
        "email_daily"=>3,
        "email_monthly"=>7,
        "billing_address_daily"=>3,
        "billing_address_monthly"=>7,
        "shipping_address_daily"=>3,
        "shipping_address_monthly"=>7,

    ],*/

    /*非3D付款频率规则*/
    /*"non3drule"=>[
        "bin_daily"=>40,
        "bin_monthly"=>500,
        "ip_daily"=>3,
        "ip_monthly"=>7,
        "cardholder_daily"=>10,
        "cardholder_monthly"=>20,
        "cart_number_daily"=>3,
        "cart_number_monthly"=>7,
        "cart_number_yearly"=>30,
        "email_daily"=>3,
        "email_monthly"=>7,
        "billing_address_daily"=>3,
        "billing_address_monthly"=>7,
        "shipping_address_daily"=>3,
        "shipping_address_monthly"=>7,
    ],*/

    /*风控阈值*/
    /*"transaction_limit"=>[
        "SetHighRiskCityAmountMin"=>0,
        "SetHighRiskCityAmountMax"=>40,
        "SetAstroPayAmountMin"=>0,
        "SetAstroPayAmountMax"=>200,
        "IsCheckAstroPayOrdersCount"=>1,
        "IsCheckAstroPayTxnCount"=>1,
        "SetAstroPayDaySpanMax"=>1,
        "SetAstroPayOrdersCountMax"=>3,
        "SetAstroPayTxnCountMax"=>3,
        "IsCheckGlobebillTurkeyCountMax"=>6,
        "SetGlobebillTurkeyAmountMax"=>1.5,
        "SetGlobebillHourSpanMax"=>1,
        "SetGlobebillTxnCountMax"=>10,
        "SetGlobebillDaySpanMax"=>1,
        "SetGlobebillCountryName"=>"TR",
        "SetPaypalSpecialCountry"=>"UA",
    ],*/

    /*售后频率*/
    "AfterSalesFrequency"=>[
        "ReplacementFrequency"=>0.5,//换货频率
        "ReturnFrequency"=>0.5,//退货频率
        "RefundFrequency"=>0.5,//退款频率
    ],

    /*支付渠道规则*/
    "WindControlPaymentMethodRule"=>[
        "egp"=>[
            'SetHighRiskAmountMin'=>0,//最小交易金额(低等于于这个数进入风控)
            'SetHighRiskAmountMax'=>40//最大交易金额（超出该金额则进入事后风控）
        ],
        /**
         * Astropay
         * 检测客户每天的订单数量，最多3单
         * 检测客户每笔订单每天最多的交易数量，最多3笔
         * Astropay 交易金额
         * 币种美金：0-200，超出200进入事后风控
         * Astropay
         * 高风险城市
         */
        "astropay"=>[
            'SetHighRiskAmountMin'=>0,//最小交易金额(低等于于这个数进入风控)
            'SetHighRiskAmountMax'=>200,//最大交易金额（超出该金额则进入事后风控）
            'IsCheckOrdersCount'=>[
            [
                'Status'=>1,
                'SetDaySpanMax'=>1,//间隔的天数（过去的几天）
                'SetOrdersCountMax'=>3,// 在SetDaySpanMax天数内，客户端最大订单数量
                'SetTxnCountMax'=>3,// 在SetDaySpanMax天数内，客户端单个订单的最大交易数量
            ]
            ],//是否检测客户的订单数量
        ],
        "pagsmile"=>[
            'SetHighRiskAmountMin'=>0,//最小交易金额(低等于于这个数进入风控)
            'SetHighRiskAmountMax'=>200,//最大交易金额（超出该金额则进入事后风控）
            'IsCheckOrdersCount'=>[
            [
                'Status'=>1,
                'SetDaySpanMax'=>1,//间隔的天数（过去的几天）
                'SetOrdersCountMax'=>3,// 在SetDaySpanMax天数内，客户端最大订单数量
                'SetTxnCountMax'=>3,// 在SetDaySpanMax天数内，客户端单个订单的最大交易数量
            ]
            ],//是否检测客户的订单数量
        ],
        //该结果还需要 asiabill 返回成功的结果进行记录，再执行我们的风控
        "asiabill"=>[
            'SetHighRiskAmountMin'=>0, //最小交易金额(低等于于这个数进入风控)
            'SetHighRiskAmountMax'=>40,//最大交易金额（超出该金额则进入事后风控）

            // 'IsCheckOrdersCount'=>[
            //     [
            //         'Status'=>1,
            //         'SetCountMax'=>6,//在某一时间最大下单量
            //         'SetAmountMax'=>1.5,//订单金额小于次参数
            //         'SetHourSpanMax'=>1,//几个小时内限制下单
            //         'SetTxnCountMax'=>10,//支付次数
            //         'SeCountryName'=>"TR",//限制的国家
            //         //'SetDaySpanMax'=>1,//在SetDaySpanMax天数内，客户端单个订单的最大交易数量
            //     ]
            // ],
        ],

        "paypal"=>[
            'IsCheckOrdersCount'=>[
                [
                    'Status'=>1,
                    'SeCountryName'=>"UA",//限制的国家
                    'SetAmountMax'=>10,//订单金额小于次参数
                    'SetDaySpanMax'=>1,//天数
                    'SetCountMax'=>2,//在某一时间最大下单量(超过或等于两个进去风控)
                ],
                [
                    'Status'=>1,
                    'SeCountryName'=>"UA",//限制的国家
                    'SetAmountMax'=>10,//订单金额小于次参数
                    'SetDaySpanMax'=>5,//天数
                    'SetCountMax'=>3//在某一时间最大下单量(连续超过或等于3个进去风控)
                ],
            ],

        ],
        "default"=>[
            'SetHighRiskAmountMin'=>0,//最小交易金额
            'SetHighRiskAmountMax'=>90,//最大交易金额（超出该金额则进入事后风控）
            'SetRefuseAmountMax'=>330//最大交易金额（超出该金额则进入事后风控）
        ]
    ]

];