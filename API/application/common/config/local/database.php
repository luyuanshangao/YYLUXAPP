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

return [
    'db_cic' =>[
        // 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '192.168.11.70',
        // 数据库名
        'database'        => 'DX_Phoenix_User',
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
        'prefix'          => 'cic_',
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

    'db_sso' =>[
        // 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '192.168.11.70',
        // 数据库名
        'database'        => 'DX_Phoenix_SSO',
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
        'prefix'          => 'sso_',
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

    'db_mongodb' =>[
    	'type'			=> '\think\mongo\Connection',
    	'hostname'		=> 'szmongodb01.dxqas.com',
    	'database'		=> 'PhoenixMall',
        'username'        => 'dev',
        // 密码
        'password'        => 'Dx+1234',
        // 端口
        'hostport'        => '37017',
        'prefix'          => 'dx_',
        // 数据库调试模式
        'debug'           => true,

    ],

    'db_mongodb_cart' =>[
    	'type'			=> '\think\mongo\Connection',
    	'hostname'		=> 'szmongodb01.dxqas.com',
    	'database'		=> 'PhoenixCart',
    	'username'      => 'dev',
    	// 密码
    	'password'      => 'Dx+1234',
    	// 端口
    	'hostport'      => '37017',
    	'prefix'        => 'dx_',
        // 数据库调试模式
        'debug'           => true,

    ],

    'db_admin' =>[
        // 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '192.168.11.70',
        // 数据库名
        'database'        => 'DX_Phoenix_Admin',
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
        'prefix'          => 'dx_',
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
    // 供应商系统数据库
    'db_seller' =>[
    		// 数据库类型
    		'type'            => 'mysql',
    		// 服务器地址
    		'hostname'        => '192.168.11.70',
    		// 数据库名
        	'database'        => 'DX_Phoenix_Seller',
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
    		//'prefix'          => 'dx_',
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
            'db_seller'       => 'db_seller',
    ],
    
    //order数据库
    'db_order' =>[
	    // 数据库类型
	    'type'            => 'mysql',
	    // 服务器地址
	    'hostname'        => '192.168.11.70',
    	// 数据库名
        'database'        => 'DX_Phoenix_Order',
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
   		 'prefix'          => 'dx_',
    	// 数据库调试模式
    	'debug'           => true,
    	// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    	'deploy'          => 1,
    	// 数据库读写是否分离 主从式有效
    	'rw_separate'     => false
        ,
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
    //Review数据库
    'db_reviews' =>[
        // 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '192.168.11.70',
        // 数据库名
        'database'        => 'DX_Phoenix_Reviews',
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
        'prefix'          => 'dx_',
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

    'log_mongodb' =>[
        /*
       'type'			=> '\think\mongo\Connection',
       'hostname'		=> 'szmongodb01.dxqas.com',
       'database'		=> 'LOG',
       'username'        => 'apiUser',
       'password'        => 'Dx+1234',
       'hostport'        => '37017',
       'params' => [
           PDO::ATTR_PERSISTENT              => false,
       ],
        */
        'type'			=> '\think\mongo\Connection',
        'hostname'		=> 'szmongodb01.dxqas.com',
        'database'		=> 'LOG',
        'username'        => 'apiUser',
        // 密码
        'password'        => 'Dx+1234',
        // 端口
        'hostport'        => '37017',
        'prefix'          => 'dx_',
        // 数据库调试模式
        'debug'           => true,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 1,
    ],
	//payment数据库
	'db_payment' =>[
		// 数据库类型
		'type'            => 'mysql',
		// 服务器地址
		'hostname'        => '192.168.11.70',
		// 数据库名
		'database'        => 'DX_Phoenix_Payment',
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
		'prefix'          => 'dx_',
		// 数据库调试模式
		'debug'           => true,
		// 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
		'deploy'          => 1,
		// 数据库读写是否分离 主从式有效
		'rw_separate'     => false
		,
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
    'db_crc' =>[
        // 数据库类型
        'type'            => 'mysql',
        // 服务器地址
        'hostname'        => '192.168.11.70',
        // 数据库名
        'database'        => 'DX_Phoenix_CRC',
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
        //'prefix'          => 'cic_',
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
];
