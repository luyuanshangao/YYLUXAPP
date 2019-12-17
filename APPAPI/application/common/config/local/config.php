<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

$config = [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => true,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => [],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT],
    // 默认输出类型
    'default_return_type'    => 'json',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'UTC',
    // 是否开启多语言
    'lang_switch_on'         => true,
    'header_accept_lang'	 => ['cn','en','cs','de','es','fi','fr','it','nl','no','pt','ru','sv','ja','ar'],
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => 'htmlspecialchars',
    // 默认语言
    'default_lang'           => 'en',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,
    //是否开启缓存
    'cache_switch_on'         => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'index',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => false,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'          => false,
    // 请求缓存有效期
    'request_cache_expire'   => null,
    // 全局请求缓存排除规则
    'request_cache_except'   => [],

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => [],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '\app\app\exception\Http',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],

    'mongodblog'   => [
        // 日志记录方式，内置 mongodb 支持扩展
        'type'  => 'Mongodb',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],

    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------

    'cache'                  => [

        'redis'   =>  [
            'type'   => 'redis',
            // 驱动方式
            'host'       => '127.0.0.1',
            //端口
            'port' =>'6379',
        ],
        // 驱动方式
        // 服务器地址
//        'host'       => '127.0.0.1'
    ],

    //Redis集群配置
    'redis_cluster_config' =>
        [
            'host'       => '127.0.0.1',
            'port'       => '6379',
            'password'   => '',
            'select'     => 0,
            'timeout'    => 0,
            'expire'     => 0,
            'persistent' => false,
            'prefix'     => '',
        ],

    //日志Redis集群配置
    'log_redis_cluster_config' =>
        [
            'host'=> '192.168.11.103',
            'port'=>7010,
            'password'=>"dxredis",
            // 缓存有效期(秒) 0表示永久缓存
            'expire'=>0
        ],

    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
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
        //上传数据至FTP路劲配置
        'UPLOAD_DIR'=>[
            'PRODUCT_IMAGES'=>'/',//产品图片同步上传至CDN目录 /productimages/
            'PRODUCT_IMAGES_SAVE'=>'/newprdimgs/',//产品图片上传保存数据库的前缀
            'ORDER_MESSAGE_IMAGES'=>'/diy/ordermessageimages/',//订单消息图片上传目录
            'ORDER_AFTER_SALE_IMAGES'=>'/diy/orderaftersaleimages/',//售后订单图片上传目录
            'SELLER_IMAGES'=>'/diy/sellerimages/',//seller图片上传目录
            'PHOTO_IMAGES'=>'/diy/photoimage/',//头像图片上传目录
            'REVIEWS_IMAGES'=>'/diy/reviewsimages/',//订单评论图片上传目录
            'ORDER_ACCUSE_IMAGES'=>'/diy/orderaccuseimages/',//订单评论图片上传目录
        ]
    ],

    /*
     * 上传原图FTP服务器配置
     * */
    'original_ftp_config'=>[
        // DxSCS 提供给 M0/IPS 的 FTP 地址
        'DX_FTP_SERVER_ADDRESS'=>'partner.img.dxcdn.com', //scs.dxcdn.com
        // DxSCS 提供给 M0/IPS 的 FTP 端口
        'DX_FTP_SERVER_PORT'=>'21021', //21990
        // DxSCS 提供给 M0/IPS 的 FTP 虚拟用户对应的用户名
        'DX_FTP_USER_NAME'=>'phoenix_srcphoto',//Phoenixftp
        // DxSCS 提供给 M0/IPS 的 FTP 虚拟用户对应的密码
        'DX_FTP_USER_PSD'=>'z*gOm31xYR3NyQLT',//44F6PU%A9MCrbcOX
        /** 上传到美国cdn ftp,sftp方式，需要切换不同目录 **/
        'SBN_FTP_SERVER_ADDRESS'=>'partner.img.dxcdn.com',//scs.dxcdn.com
        'SBN_FTP_SERVER_PORT'=>'21021',//21990
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
            'product_imgs'=>'', //产品图片预览地址目录，目录名称要和‘ftp_config’配置下的一致，下同 productimages/
            'order_message_imgs'=>'diy/ordermessageimages/', //订单消息图片预览地址目录
            'order_after_sale_imgs'=>'diy/orderaftersaleimages/', //售后订单图片预览地址目录
            'seller_imgs'=>'diy/sellerimages/', //seller图片预览地址目录
        ]
    ],

    /**
     * 基础上传目录
     */
    'base_upload_dir'=>ROOT_PATH . 'public' . DS . 'uploads' . DS,
    'upload_dir'=>ROOT_PATH . '../' . DS . '../'. DS . 'photo'. DS,


    /**
     * 产品图片上传目录
     */
    'product_pic_upload_dir'=>ROOT_PATH . 'public' . DS . 'uploads' . DS . 'product',

    //分页配置
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 40,
        'page_size' => 20,
    ],
    /**
     * 支付密码
     */
    'PaymentPasswordServicUrl'=>[
        'lis_service_wsdl'=>
            [
                'url'=>'https://svcml01.dxqas.com/CIC/v5.0/PaymentPasswordService.svc?wsdl',
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

    //关税保险配置文件
    'tariff_insurance' => ['US','CN'],
    //支付货到付款的国家配置
    'support_cod_country' =>[], //['US','CN','BR'],
    //NOCNOC国家配置 APP第一期暂时不做NOCNOC
    'nocnoc_country' => ['AR','BR'],//['AR','BR'],
    //Asiabill支持的信用卡Asiabill
    'asiabill_creditcard' => ['MasterCard','VISA'],
    //EGP支持的信用卡
    //沙特阿拉伯COD费用(SAR-->USD)
    'saudi_arabia_cod_free' => [[0.5,42.25],[1,59.05],[1.5,77.818],[2,86.65],[2.5,100.45],[3,114.25],[3.5,128.05],
        [4,141.85],[4.5,155.65],[5,169.45],[5.5,183.25],[6,197.05],[6.5,210.85],[7,224.65],[7.5,238.45],[8,252.25],
        [8.5,266.05],[9,279.85],[9.5,293.65],[10,307.45],[11,335.05],[12,362.65],[13,390.25],[14,417.85],[15,445.45],
        [16,473.05],[17,500.65],[18,528.25],[19,555.85],[20,582.45],[21,611.05],[22,638.65],[23,666.25],[24,693.85],
        [25,721.45],[26,749.05],[27,776.65],[28,804.25],[29,831.85],[30,859.45],[31,1040.3335],[32,1068.911],[33,1097.477],
        [34,1126.0545],[35,1154.632],[36,1183.2095],[37,1211.787],[38,1240.353],[39,1268.9305],[40,1297.508],[41,1326.0855],
        [42,1354.663],[43,1383.229],[44,1411.8065],[45,1440.384],[46,1468.9615],[47,1497.539],[48,1526.105],[49,1554.6825],
        [50,1583.26],[51,1588.918],[52,1618.174]],
    //阿联酋COD费用(AED-->USD)
    'united_arab_emirates_cod_free' => [[0.5,38],[1,38],[1.5,49],[2,49],[2.5,61],[3,61],[3.573,],[4,73],[4.5,85],[5,85],[5.5,99],
        [6,99],[6.5,112],[7,112],[7.5,166],[8,166],[8.5,140],[9,140],[9.5,153],[10,153],[11,167],[12,181],[13,194],[14,208],[15,222],
        [16,235],[17,249],[18,263],[19,276],[20,290],[21,304],[22,317],[23,331],[24,345],[25,358],[26,372],[27,386]],
    //paypal支持的币种
    'paypal_support_currency' => ['USD','EUR','MXN','GBP','AUD','CAD'],
    //dx支持的币种
    'dx_support_currency' => ['USD','EUR','MXN','GBP','AUD','CAD','ARS','BRL'],
    //paypal不支持的DX币种
    'paypal_not_support_currency' => ['ARS','BRL'],
    //关税保险金额--1.5$
    'tariff_insurance'=> 1.5,

    //是否开启coupon使用功能
    'use_coupon_switch_on'=>true,

    /**
     * Fulfillment Service配置
     */
    'fulfillment_service_url'=>[
        'lis_service_wsdl'=>
            [
                'url'=>'https://svcml01.dxqas.com/OMS/v4.0.5/FulfillmentService.svc?wsdl',
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
    /*注册赠送的优惠券ID*/
    'register_coupon'=>61,

    /** 同步发货数据至OMS **/
    'synchro_fulfillment_oms_post'=>[
        'url'=>'https://apiml01.dxqas.com/OMS/v4.5.5/api/Order/UpdateFulfillmentInfo',
        'user_name'=>'admin',
        'pass_word'=>'123456',
    ],
    /**
     * 通知payment订单关系
     */
    'synchro_payment_post'=>[
        'url'=>'https://apiml01.dxqas.com/Payment/api/OrderMap/Create',
        'user_name'=>'admin',
        'pass_word'=>'123456',
    ],

    /**
     * payment服务调用配置
     */
    'payment_wsdl_url'=>[
        'lis_service_wsdl'=>
            [
                'url'=>'https://payment.dxqas.com/Service/v4.0/PaymentServiceForMrdeal.svc?wsdl',
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
    /*上传绝对路径*/
    "uploads_absolute_path"=>DS."data".DS."uploads".DS."test",
    "new_payment"=>['egp','sc'],//
    /*aes加密
    "aes"=>[
        "Keys"=>[
            'Key1'=>'2D4d0T3J@qs$jRoN',
            'Key2'=>'fBclDC@cwoq$AS%5',
            'Key3'=>'M9hI0!z0L5F2HGD#',
            'Key4'=>'lMEe%SvuqPdUI$QO'
        ],
        "IVs"=>[
            'IV1'=>'qS2$MrMm@PT3GiVX',
            'IV2'=>'ZQDi$YfBsjhj%3LU',
            'IV3'=>'w!PqXsYNbjx$8iSy',
            'IV4'=>'C8ncqV5Tx*sbRC!J'
        ],
        "table_aes"=>[
            "Address"=>[
                "EmailUserName"=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
                "Phone"=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
                "Mobile"=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
                "Street1"=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
                "Street2"=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
                "Zip"=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
            ],
            "BankCard"=>[
                "EmailUserName"=>[
                    'Key'=>'Key3',
                    'IV'=>'IV3'
                ],
                "CardNumber"=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
                "Cvv"=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
                "CardHolder"=>[
                    'Key'=>'Key3',
                    'IV'=>'IV3'
                ],
                "BillingAgreement"=>[
                    'Key'=>'Key3',
                    'IV'=>'IV3'
                ],
                "Street1"=>[
                    'Key'=>'Key3',
                    'IV'=>'IV3'
                ],
                "Street2"=>[
                    'Key'=>'Key3',
                    'IV'=>'IV3'
                ],
                "Zip"=>[
                    'Key'=>'Key3',
                    'IV'=>'IV3'
                ],
            ],
            'Customer'=>[
                'EmailUserName'=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
                'Telephone'=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ]
            ],
            'LoginHistory'=>[
                'IPAddress'=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ]
            ],
            'LoginHistory'=>[
                'IPAddress'=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ]
            ],
            'OpenIDAccount'=>[
                'UserID'=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
                'EmailUserName'=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ],
            ],
            'OperationLog'=>[
                'UserID'=>[
                    'Key'=>'Key2',
                    'IV'=>'IV2'
                ]
            ],
            'CustomerEmail'=>[
                'EmailUserName'=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
            ],
            'DetailedCustomer'=>[
                'EmailUserName'=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
            ],
            'CreditCard'=>[
                'Token'=>[
                    'Key'=>'Key4',
                    'IV'=>'IV4'
                ],
            ],
            'AffiliateLevel'=>[
                'PayPalEU'=>[
                    'Key'=>'Key1',
                    'IV'=>'IV1'
                ],
            ]
        ]
    ],
*/
    'api_base_url'=>'http://api.localhost.com/',
    /*积分兑换优惠券ID*/
    'ExchangeCoupons'=>[(int)62,(int)63,(int)64],
];
$config_database = @include APP_PATH.'/common/config/'.THINK_ENV.'/database.php';
$config_apicode = @include APP_PATH.'/common/config/api.code.config.php';
$config_header = @include APP_PATH.'/common/config/header.config.php';
$config_orderstatus = @include APP_PATH.'/common/config/order.config.php';
$config_ordermapping = @include APP_PATH.'/common/config/order.mapping.config.php';
$config_base = @include APP_PATH.'/common/config/base.config.php';
$config_reports = @include APP_PATH.'/common/config/reports.config.php';
return array_merge($config,$config_database,$config_apicode,$config_header,$config_orderstatus,$config_ordermapping,$config_base,$config_reports);
