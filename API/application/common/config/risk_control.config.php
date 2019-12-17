<?php
/**
 * author : Hai.Ouyang
 * time   : 2019-07-08
 */

return [
	"israeli_risk_config"=>[
        'api_key' => '0d690c95faf172becacbafdb26719a70ca10065b56593f6748b198c12deb3606',
        'url'     => 'http://ccdf100.cloudapp.net:80/rest/cybercloud/_table/orders_p',
        'shop_id' => 1028,
    ],

    //支付渠道规则
    "payment_channel_config"=>[
        //egp
        TRANSACTION_CHANNEL_EGP=>[
           	//最大交易金额（美元）
           	"max_amount"		=> 40,
           	"min_amount"		=> 0,
           	//大于该值，直接进入人工审核
           	"max_risk_amount"	=> 100,
           	//一天最大订单数
           	"order_num_day_max"	=> 3,
        ],

        //mercadopago
        TRANSACTION_CHANNEL_MERCADOPAGO=>[
        	//一天最大订单数
           	"order_num_day_max"	=> 3,
           	//同一订单一天最大次数
           	"same_num_day_max"	=> 3,

           	//最大交易金额（美元）
           	"max_amount"		=> 60,
           	"min_amount"		=> 0,
           	//大于该值，直接进入人工审核
           	"max_risk_amount"	=> 100,
        ],

      	//astropay
        TRANSACTION_CHANNEL_ASTROPAY=>[
           //一天最大订单数
           	"order_num_day_max"	=> 3,
           	//同一订单一天最大次数
           	"same_num_day_max"	=> 3,

           	//最大交易金额（美元）
           	"max_amount"		=> 60,
           	"min_amount"		=> 0,
           	//大于该值，直接进入人工审核
           	"max_risk_amount"	=> 100,
        ],
        //astropay
        TRANSACTION_CHANNEL_DLOCAL=>[
           //一天最大订单数
            "order_num_day_max" => 3,
            //同一订单一天最大次数
            "same_num_day_max"  => 3,

            //最大交易金额（美元）
            "max_amount"    => 60,
            "min_amount"    => 0,
            //大于该值，直接进入人工审核
            "max_risk_amount" => 100,
        ],

        //pagsmile
        TRANSACTION_CHANNEL_PAGSMILE=>[
        	//一天最大订单数
           	"order_num_day_max"	=> 3,
           	//同一订单一天最大次数
           	"same_num_day_max"	=> 3,

           	//最大交易金额（美元）
           	"max_amount"		=> 60,
           	"min_amount"		=> 0,
           	//大于该值，直接进入人工审核
           	"max_risk_amount"	=> 100,
        ],

        //asiabill
        TRANSACTION_CHANNEL_ASIABILL=>[
           	//最大交易金额（美元）
           	"max_amount"		=> 40,
           	"min_amount"		=> 0,
           	//大于该值，直接进入人工审核
           	"max_risk_amount"	=> 100,
           	//一天最大订单数
           	"order_num_day_max"	=> 3,
        ],

        //paypal
        TRANSACTION_CHANNEL_PAYPAL=>[
        	//是否进行以色列风控校验
        	"is_check_isaeli"		=> true,
        	"currency_code"		=> "US",
        	//大于该值，直接进入人工审核
            "max_amount" 		=> 300,
            //大于等于该值，进入以色列风控
          	"israeli_max_amount"=> 10,
          	
          	//特殊国家特殊处理方式
          	"special_country"	=> [
          		//乌克兰
          		"UA" => [
          			//小于该值，并在下面某一条件下直接进入人工审核
          			"special_min_amount"=> 10,	
          			//当天订单大于此值，直接进入人工审核
          			"one_day_span_max"	=> 1,
          			"other_day"			=> 5,
          			//other_day天内大于此值，直接进入人工审核
          			"other_day_span_max"=> 2,
          		],
          		//美国
          		"US" => [
          			//小于该值，并在下面某一条件下直接进入人工审核
          			"special_min_amount"=> 10,	
          			//当天订单大于此值，直接进入人工审核
          			"one_day_span_max"	=> 1,
          			"other_day"			=> 5,
          			//other_day天内大于此值，直接进入人工审核
          			"other_day_span_max"=> 2,
          		],
          		//墨西哥
          		"MX" => [
          			//小于该值，并在下面某一条件下直接进入人工审核
          			"special_min_amount"=> 10,	
          			//当天订单大于此值，直接进入人工审核
          			"one_day_span_max"	=> 1,
          			"other_day"			=> 5,
          			//other_day天内大于此值，直接进入人工审核
          			"other_day_span_max"=> 2,
          		],
          		
          	],
        ],

        "default"=>[
          	
        ]
    ],

    'risk_email_users' => [
        'ouyh@comepro.com',
        'yanxh@comepro.com',
    ],
];