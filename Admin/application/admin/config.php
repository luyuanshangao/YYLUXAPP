<?php

return [
    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------
    //ajax返回配置
    'ajax_return_data'=>[
        'code'=>-1,
        'msg'=>''
    ],
    'access_token'=>Api_token(),//api密码
    //Redis集群配置
    'redis_cluster_config' =>
        [
            'host'=>[
                '192.168.11.70:7000',
                '192.168.11.70:7001',
                '192.168.11.70:7002',
                '192.168.11.70:7003',
                '192.168.11.70:7004',
                '192.168.11.70:7005'
            ],
            // 缓存有效期(秒) 0表示永久缓存
            'expire'=>0
        ],
    //邮件配置
    'email'=>[
        'host'=>'mail.comepro.com', //SMTP 服务器
        'port'=>25, //SMTP 服务器 端口
        'username'=>'liuth@volumerate.com', //SMTP服务器用户名
        'password'=>'vr654321',//SMTP服务器用户名密码
        'setform_address'=>'liuth@volumerate.com', //发件人邮箱 sellerAdmin@dx.com
        'setform_name'=>'DX Seller' //发件人姓名
    ],

    //阿里云短信配置
    'aliyun_sms'=>[
        'accessKeyId'=>'Uf7jmNUNfcEHlh70',
        'accessKeySecret'=>'nJctBa5RMvmlBcfLSHzuBpqyy1ptNk'
    ],

    /**
     * FTP服务器连接配置
     */
    'ftp_config'=>[
        // DxSCS 提供给 M0/IPS 的 FTP 地址
        'DX_FTP_SERVER_ADDRESS'=>'scs.dxcdn.com', //scs.dxcdn.com
        // DxSCS 提供给 M0/IPS 的 FTP 端口
        'DX_FTP_SERVER_PORT'=>'21990', //21990
        // DxSCS 提供给 M0/IPS 的 FTP 虚拟用户对应的用户名
        'DX_FTP_USER_NAME'=>'ftp',//Phoenixftp
        // DxSCS 提供给 M0/IPS 的 FTP 虚拟用户对应的密码
        'DX_FTP_USER_PSD'=>'Dx+123456',//44F6PU%A9MCrbcOX
        /** 上传到美国cdn ftp,sftp方式，需要切换不同目录 **/
        'SBN_FTP_SERVER_ADDRESS'=>'192.168.11.70',//scs.dxcdn.com
        'SBN_FTP_SERVER_PORT'=>'21',//21990
        'DX_FTP_ACCESS_PATH'=>'/newprdimgs', //产品图片上传的路径
        'DX_FTP_ACCESS_PATH_BRAND'=>'/brand', //品牌图片上传的路径
        'DX_FTP_ACCESS_PATH_DIY'=>'/diy', //自定义图片上传的路径
        //上传数据至FTP路劲配置
        'UPLOAD_DIR'=>[
            'AFFILIATE_IMAGES'=>'/diy/affiliateimages/',//affiliate图片上传目录
            'ARTICLE_IMAGES'=>'/diy/articleimages/',//affiliate图片上传目录
            'PRODUCT_IMAGES'=>'/',//产品图片同步上传至CDN目录 /productimages/
            'PRODUCT_IMAGES_SAVE'=>'/newprdimgs/',//产品图片上传保存数据库的前缀
            'ORDER_MESSAGE_IMAGES'=>'/diy/ordermessageimages/',//订单消息图片上传目录
            'ORDER_AFTER_SALE_IMAGES'=>'/diy/orderaftersaleimages/',//售后订单图片上传目录
            'SELLER_IMAGES'=>'/diy/sellerimages/',//seller图片上传目录
            'FILE_UPLOAD'=>'/document/',//文件上传路径,excel,xml等文件
        ]
    ],
    /**
     * EDN文件上传ftp
     */
    'ftp_edm_config'=>[
        //EDN 提供的FTP地址
        'DX_FTP_EDN_SERVER_ADDRESS'=>'192.168.11.58',
        //EDN 提供FTP端口
        'DX_FTP_EDN_SERVER_PORT'=>'21',
        //EDN 提供FTP用户名
        'DX_FTP_EDN_USER_NAME'=>'pssftp',
        //EDN 提供FTP密码
        'DX_FTP_EDN_USER_PSD'=>'123456',
         //文件原路径
        'DX_FILE_URL'=>'./uploads/edm/',
        //FTP存放路径
        'DX_FTP_FILE_URL'=>'/edm/',
    ],


    //.net服务地址配置
    // 'wsdl_url'=>[
    //     'lis_service_wsdl'=>
    //         [
    //             'url'=>'https://svcml01.dxqas.com/LIS/V4.0_WCFByUSA/Wcf/LisService.svc?wsdl',
    //             'user_name'=>'oms.go',
    //             'password'=>'Dx+12345',
    //             'options'=>[
    //                 'ssl'   => [
    //                     'verify_peer'          => false,
    //                     'allow_self_signed' => true
    //                 ],
    //                 'https' => [
    //                     'curl_verify_ssl_peer'  => false,
    //                     'curl_verify_ssl_host'  => false
    //                 ]
    //             ]
    //         ],

    // ],

    /**
     * 调用API地址
     */
    // 'api_base_url'=>'api.dxinterns.com/',//测试环境
    'api_base_url'=>'api.localhost.com/',

    'cic_api_url' => 'api.localhost.com/',
    /*调用公用api调用地址*/
    'api_share_url'=>'api.localhost.com/',
    'payment_base_url'=>'payment.dxinterns.com/',
	/**
	 * 调用API地址--订单前端接口Order Frontend
	 */
	'api_base_order_frontend_url'=>'api.localhost.com/',
	/**
	 * 调用API地址--订单前端接口Order Frontend-Token
	 */
    'api_base_order_frontend_url_token' =>'a79c722fe7693088532c4ed8b8db2691',

	/**
	 * 前端商城域名
	 */
	'mall_url'=>'//mall.dxinterns.com/',
    /**
     * DX商城域名
     */
    'dx_url'=>'https://www.dx.com/',
	//define("DX_FTP_SERVER_ADDRESS", "scs.dxcdn.com");
	/**
	 * 商城图片路径--产品图片
	 */
	'dx_mall_img_url'=>'//photo.dxinterns.com',
    /**
     * 商城图片路径--品牌图片
     */
    'dx_mall_img_url_brand'=>'//photo.dxinterns.com',
    /*帮助中心域名*/
    'help_url' =>"http://help.localhost.com/",
	/**
	 * 前端商城产品审核页面token
	 */
	'mall_url_token'=>'123456',

     // 商户修改
    'merchant'=>[
        'url'=>'seller/seller/update',
        'access_token' =>'a79c722fe7693088532c4ed8b8db2691',
    ],
    // 商户删除auther wang   2018-04-03
    'MerchantDelete'=>[
        'url'=>'http://api.dxinterns.com/seller/seller/del',
        'access_token' =>'a79c722fe7693088532c4ed8b8db2691',
    ],
     //商家重置密码auther wang   2018-04-04
    'reset_password'=>[
        'url'=>'http://api.dxinterns.com/seller/seller/resetPassword',
        'access_token' =>'a79c722fe7693088532c4ed8b8db2691',
        'subject'=>'密码修改',//主题
        'body'=>'你好！你的密码管理员已帮你重新生成为xxx,如非本人操作请忽略本信息',//内容
    ],

    /**
     * 产品图片上传目录
     */
    'product_pic_upload_dir'=>ROOT_PATH . 'public' . DS . 'uploads' . DS . 'product',
    'product_list'=>'http://api.dxinterns.com/mallextend/product/lists',//产品列表   不用了

    //产品上下架http://api.dxinterns.com/mallextend/product/changeStatus
    'ProductStatus'=>[
        // 'url'=>'http://api.dxinterns.com/seller/product/changeStatus',
        'url'=>'http://api.dxinterns.com/mallextend/product/changeStatus',
        'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
    ],
     //添加物流
    'AddLogistics'=>[
        'url'=>'http://api.dxinterns.com/seller/Seller/AddLogistics',
        'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
    ],
    //物流列表
    'LogisticsList'=>[
        'url'=>'http://api.dxinterns.com/seller/seller/LogisticsList',
        'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
    ],
     //物流修改
    'EditLogistics'=>[
        'url'=>'http://api.dxinterns.com/seller/seller/EditLogistics',
        'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
    ],
    //物流删除
    'deleteLogistics'=>[
        'url'=>'http://api.dxinterns.com/seller/seller/deleteLogistics',
        'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
    ],
    'web_config'=>[
        'web_title'=>'后台管理系统',
        'web_name'=>'后台系统',
        'web_desc'=>'后台管理',//后台描述
    ],
    //产品审核
    'productExamine'=>[
        'product_list'=>'http://api.dxinterns.com/mallextend/product/lists',//产品列表
        'url'=>'http://api.dxinterns.com/mallextend/product/audit',//产品审核
        'access_token' =>'b282b0093fbd49bdb77554faf4c188cf',
    ],
    //会员列表
    'getCustomerList'=>[
        'url'=>'http://api.localhost.com/cic/Customer/getCustomerList',//列表
        'urlStatus'=>'http://api.localhost.com/cic/Customer/updateStatus',//状态
        'access_token' =>'e3b4c395f2501d9d20d50b65c694ad43',
    ],
     //多语言接口
    'langs'=>[
        'url'=>'http://api.dxinterns.com/share/header/langs',//列表
        'access_token' =>'19cee9b7e54c5f83cdf1bdf6d4fe0ab3',
    ],

    //币种
    'getCurrencyList'=>[
       'url'=>'http://api.dxinterns.com/share/Currency/getCurrencyList',
       'access_token' =>'19cee9b7e54c5f83cdf1bdf6d4fe0ab3',
    ],

     /**
      * LMS物流数据同步接口
      */
    'lms'=>[
        'url'=>'http://www.api.com:5678/lms/logistics/logistics',//列表
        'access_token' =>'19cee9b7e54c5f83cdf1bdf6d4fe0ab3',
    ],
    //加锁解锁接口
    'orderStatus'=>[
       'url'=>'orderfrontend/OrderQuery/holdAndUnhold',
    ],
     //投诉管理接口
    'orderAccuse'=>[
       'url'=>'orderfrontend/OrderQuery/orderAccuse',
    ],
    'apiConfig'=>[
       'url'=>'orderfrontend/OrderQuery/apiConfig',
    ],
     //投诉管理接口
    'orderRefund'=>['url'=>'orderfrontend/OrderQuery/orderRefund',],
     //售后详情
    'afterSaleDetails'=>['url'=>'orderfrontend/OrderQuery/afterSaleDetails',],

    //定时器用于触发物流模板更新秘钥
    'lms_logistics_token'=>'19cee9b7e54c5f83cdf1bdf6d4fe0ab3',
    //获取风控状态
    'RiskConfig'=>['url'=>'/admin/OrderComplaint/RiskConfig',],
    //仲裁
    'arbitration'=>['url'=>'orderfrontend/OrderQuery/arbitration',],
    //仲裁回复
    'applyLog'=>['url'=>'orderfrontend/OrderQuery/applyLog',],
    //关税赔宝
    'CustomsInsurance'=>['url'=>'orderfrontend/OrderQuery/CustomsInsurance',],
    //后台退款
    'refundOrder'=>['url'=>'orderbackend/Order/refundOrder'],
    //EDM营销  查询
    'order_query'=>['url'=>'orderfrontend/OrderQuery/order_query',],
    'shop_name'=>['url'=>'seller/seller/shop_name',],
	//.net服务地址配置
	'wsdl_url'=>[

        'lis_service_wsdl'=>[
            'url'=>'https://svcml01.dxqas.com/LIS/V4.0_WCFByUSA/Wcf/LisService.svc?wsdl',
            'user_name'=>'oms.go',
            'password'=>'Dx+12345',
            'options'=>[
                    'ssl'   =>[
                                'verify_peer'          => false,
                                'allow_self_signed' => true
                              ],
                    'https' => [
                                'curl_verify_ssl_peer'  => false,
                                'curl_verify_ssl_host'  => false
                            ]
                    ]
        ],
        //邮件服务配置
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
            ],

        //邮件服务配置
        'risk_process_service_wsdl'=>
            [
                'url'=>'https://payment.dxqas.com/Service/v4.0/RiskProcessService.svc?wsdl',
                'user_name'=>'crc.go',
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
    /*mss邮件模板配置*/
    'send_email'=>[
        'CustomerID'=>'',
        'EmailAddressBCC'=>'',
        'EmailAddressCC'=>'',
        'From'=>'sales@dx.com',
        'MSSUserName'=>'phoenix.go',
        'SiteID'=>1,
    ],

    'send_email_config' => [
        'warning'=>[
            //是否可以发送html格式邮件
            'isHtml' => true,
            'secureType' => 'ssl',
            'host' => 'smtp.comepro.com',
            'port' => 25,
            'serverName' => 'warning@comepro.com',
            'serverPassword' => 'vDe7wA75bC',
            'fromEmail' => 'warning@comepro.com',
            'fromName' => 'warning',
            //SMTP调试功能 0=关闭 1 = 错误和消息 2 = 消息
            'debug' => 0,
        ],

        'notice'=>[
            //是否可以发送html格式邮件
            'isHtml' => true,
            'secureType' => 'ssl',
            'host' => 'smtp.comepro.com',
            'port' => 25,
            'serverName' => 'notice@comepro.com',
            'serverPassword' => 'dx654321',
            'fromEmail' => 'notice@comepro.com',
            'fromName' => 'notice',
            //SMTP调试功能 0=关闭 1 = 错误和消息 2 = 消息
            'debug' => 0,
        ],
        'sales'=>[
            //是否可以发送html格式邮件
            'isHtml' => true,
            'secureType' => 'ssl',
            'host' => 'smtp.comepro.com',
            'port' => 25,
            'serverName' => '',
            'serverPassword' => '',
            'fromEmail' => 'sales@dx.com',
            'fromName' => 'sales',
            //SMTP调试功能 0=关闭 1 = 错误和消息 2 = 消息
            'debug' => 0,
        ],
    ],

    /**
     * 订单状态
     */
    'order_status_data' => [
        ['code'=>100, 'name'=>'等待付款'],
        ['code'=>120, 'name'=>'付款确认中'],
        /*['code'=>300, 'name'=>'付款处理中'],*/
        ['code'=>200, 'name'=>'付款完成'],
        ['code'=>400, 'name'=>'待发货'],
        ['code'=>500, 'name'=>'部分发货'],
        ['code'=>600, 'name'=>'已发货'],
        ['code'=>700, 'name'=>'待收货'],
        ['code'=>800, 'name'=>'妥投'],
        ['code'=>900, 'name'=>'已完成'],
        ['code'=>1000, 'name'=>'待评价'],
        ['code'=>1100, 'name'=>'已评价'],
        ['code'=>1200, 'name'=>'待追评'],
        ['code'=>1300, 'name'=>'已追评'],
        ['code'=>1400, 'name'=>'订单取消'],
        ['code'=>1500, 'name'=>'等待'],
        ['code'=>1600, 'name'=>'索赔'],
        ['code'=>1700, 'name'=>'纠纷中'],
        ['code'=>1800, 'name'=>'争端订单'],
        ['code'=>1900, 'name'=>'已关闭'],
    ],
      /**
     * EDN文件上传ftp
     */
    'ftp_edm_config'=>[
        //EDN 提供的FTP地址
        'DX_FTP_EDN_SERVER_ADDRESS'=>'192.168.11.58',
        //EDN 提供FTP端口
        'DX_FTP_EDN_SERVER_PORT'=>'21',
        //EDN 提供FTP用户名
        'DX_FTP_EDN_USER_NAME'=>'pssftp',
        //EDN 提供FTP密码
        'DX_FTP_EDN_USER_PSD'=>'123456',
         //文件原路径
        'DX_FILE_URL'=>'./uploads/edm/',
        //FTP存放路径
        'DX_FTP_FILE_URL'=>'/edm/',
    ],
    'SiteID'=>'dxWeb',
];
