<?php

/**
 * 基础配置（因为环境变化会发生相应变化）
 * @author tinghu.liu
 * @date 2018-06-16
 */
return [

    //卖家注册协议链接地址
    'seller_registration_protocol_url'=>'http://help.dxinterns.com/seller/Info/cate_id/40/article_id/31.html',

    /**************  redis缓存相关配置 start *************/

    //Redis集群配置
    'redis_cluster_config' =>
        [
            'host'=>[
                'redis.dxinterns.com:7000',
                'redis.dxinterns.com:7001',
                'redis.dxinterns.com:7002',
                'gw1.dxqas.com:7003',
                'gw1.dxqas.com:7004',
                'gw1.dxqas.com:7005',
            ],
            // 缓存有效期(秒) 0表示永久缓存
            'expire'=>60*60
        ],

    //redis缓存默认前缀
    'redis_cache_default_prefix'=>'seller_redis_',

    //使用redis缓存：true||false
    'redis_switch_on'=> true,

    /**************  redis缓存相关配置 end *************/

    /**
     * 调用API地址
     */
    'api_base_url'=>'http://api.localhost.com',

    /**
     * .net服务地址配置
     */
    'wsdl_url'=>[
        //lis服务配置
        'lis_service_wsdl'=>
            [
                /*'url'=>'https://svcml01.dxqas.com/LIS/V4.0_WCFByUSA/Wcf/LisService.svc?wsdl',
                'user_name'=>'oms.go',
                'password'=>'Dx+12345',*/

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
        'send_mail_service_wsdl'=>
            [
                //'url'=>'https://svcml01.dxqas.com/MSS/v4.0/Interface/SendMailService.svc?wsdl',
                'url'=>'https://svcml01.dxqas.com/MSS/v4.0/Interface/SendOtherMailService.svc?wsdl',
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
            ]

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
        'accessKeySecret'=>'nJctBa5RMvmlBcfLSHzuBpqyy1ptNk',
        'SignName'=>'浪子虎',
        'expiryTime'=>60 /** 短信验证码过期时间，单位：秒 **/
    ],

    /**
     * CDN链接地址
     */
    'cdn_url'=>'http://scs.dxcdn.com/Phoenix/',

    /**
     * 本地图片上传地址
     */
    'seller_img_url'=>'http://seller.localhost.com/uploads',

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
        //上传数据至FTP路劲配置
        'UPLOAD_DIR'=>[
            'PRODUCT_IMAGES'=>'/',//产品图片同步上传至CDN目录 /productimages/
            'PRODUCT_IMAGES_SAVE'=>'/newprdimgs/',//产品图片上传保存数据库的前缀
            'ORDER_MESSAGE_IMAGES'=>'/diy/ordermessageimages/',//订单消息图片上传目录
            'ORDER_AFTER_SALE_IMAGES'=>'/diy/orderaftersaleimages/',//售后订单图片上传目录
            'SELLER_IMAGES'=>'/diy/sellerimages/',//seller图片上传目录
        ]
    ],

    /**
     * CDN预览地址配置
     */
    'cdn_url_config'=>[
        'url'=>'//photo.dxinterns.com/', //CDN地址
        'dir'=>[
            'product_imgs'=>'/', //产品图片预览地址目录，目录名称要和‘ftp_config’配置下的一致，下同 productimages/
            'order_message_imgs'=>'diy/ordermessageimages/', //订单消息图片预览地址目录
            'order_after_sale_imgs'=>'diy/orderaftersaleimages/', //售后订单图片预览地址目录
            'seller_imgs'=>'diy/sellerimages/', //seller图片预览地址目录
        ]
    ],

    /**
     * CDN生成小图地址(70*70 && 210*210)
     */
    'cdn_thumbnail_url'=>'http://api.dxinterns.com:8795',

    /**
     * 图片地址配置
     */
    //'product_images_url_config'=>'//photo.dxinterns.com/productImage/',

    /**
     * 商城地址配置
     */
    'mall_url_config'=>'http://mall.localhost.com/',

    /**
     * 商城首页地址
     */
    'mall_index_url'=>'http://www.dx.com',

    /**
     * 通栏顶部-平台规则地址
     */
    'platform_rules_url'=>'http://help.dxinterns.com/seller/helpList/cate_id/40.html',

    /**
     * 首页-最新公告‘更多’地址
     */
    'platform_notice_more_url'=>'http://help.dxinterns.com/seller/helpList/cate_id/39.html',

    /**
     * 首页-新手必读‘更多’地址
     */
    'novice_must_read_more_url'=>'http://help.dxinterns.com/seller/helpList/cate_id/40.html',

    /**
     * 17track地址
     */
    'track17_url'=>'https://www.17track.net',

    /**
     * 历史产品数据同步运费模板，运费模板配置
     */
    'sync_history_product_shipping_template_config'=>[
        '666'=>[ //店铺ID
            //是否带电：1-为普货，2-为纯电，3-为带电
            '1'=>['id'=>1,'name'=>'NF01'],//普货运费模板
            '2'=>['id'=>2,'name'=>'BF01'],//纯电运费模板
            '3'=>['id'=>3,'name'=>'EF01'],//带电运费模板
        ],
        '888'=>[ //店铺ID
            //是否带电：1-为普货，2-为纯电，3-为带电
            '1'=>['id'=>4,'name'=>'NF916'],//普货运费模板
            '2'=>['id'=>5,'name'=>'BF916'],//纯电运费模板
            '3'=>['id'=>6,'name'=>'EF916'],//带电运费模板
        ],
        '999'=>[ //店铺ID
            //是否带电：1-为普货，2-为纯电，3-为带电
            '1'=>['id'=>7,'name'=>'NFHK'],//普货运费模板
            '2'=>['id'=>8,'name'=>'BFHK'],//纯电运费模板
            '3'=>['id'=>9,'name'=>'EFHK'],//带电运费模板
        ],
        '333'=>[ //店铺ID
            //是否带电：1-为普货，2-为纯电，3-为带电
            '1'=>['id'=>15,'name'=>'Normal'],//普货运费模板
            '2'=>['id'=>14,'name'=>'Electric'],//纯电运费模板
            '3'=>['id'=>13,'name'=>'HaveElectric'],//带电运费模板
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
    //日志库配置
    'db_log'=>[
        // 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '192.168.11.70',
        // 数据库名
        'database'        => 'DX_Phoenix_Log',
        // 用户名
        'username'        => 'root',
        // 密码
        'password'        => 'Dx+1234',
        // 端口
        'hostport'        => '3306',
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => [],
        // 数据库编码默认采用utf8
        'charset'         => 'utf8mb4',
        // 数据库表前缀
        'prefix'          => 'log_',
        // 数据库调试模式
        'debug'           => true,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'     => false,
        // 读写分离后 主服务器数量
        'master_num'      => 1,
        // 指定从服务器序号
        'slave_no'        => '',
        // 是否严格检查字段是否存在
        'fields_strict'   => true,
        // 数据集返回类型
        'resultset_type'  => 'array',
        // 自动写入时间戳字段
        'auto_timestamp'  => false,
        // 时间字段取出后的默认时间格式
        'datetime_format' => 'Y-m-d H:i:s',
        // 是否需要进行SQL性能分析
        'sql_explain'     => false,
    ],

    /**
     * 调用API地址【CIC】
     */
    'api_base_url_cic'=>'http://api.localhost.com',


];
