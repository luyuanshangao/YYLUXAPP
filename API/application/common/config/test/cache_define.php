<?php
//运费描述说明
define('FREE_SHIPPING', 'Free Shipping');
//运费描述说明
define('FREE_SHIPPING_IN_ONEDAY', 'Free Shipping In 24 Hrs');
//图片地址
defined('IMG_URL') or define('IMG_URL', '//photo.dxinterns.com');
//api_mall日志表
define('LOGS_MALL_API', "logs_mall_api");
//api_mallextend日志表
define('LOGS_MALLEXTEND_API', "logs_mallextend_api");
// 调取其他接口API通用地址
define('API_URL', "http://api.dxinterns.com/");
//mall商城地址
define('MALL_DOCUMENT', 'http://mall.dxinterns.com/');
//一小时
define('CACHE_HOUR', 1200);
//一天
define('CACHE_DAY', 1200);
//缓存key-币种菜单
define('CURRENCY_MENU', 'MALL_CURRENCY_MENU');
//缓存key-语种菜单
define('LANG_MENU', 'MALL_LANG_MENU');
//缓存key-国家菜单
define('COUNTRY_MENU', 'COUNTRY_MENU');
//缓存key-商城LOGO图片
define('MALL_HOME_LOGO', 'MALL_HOME_LOGO');
//缓存key-商城LOGO图片
define('MALL_HOME_TOP_BANNER', 'MALL_HOME_TOP_BANNER');
//默认语种
define('DEFAULT_LANG', 'en');
//默认币种
define('DEFAULT_CURRENCY', 'USD');
//默认币种符号
define('DEFAULT_CURRENCY_CODE', '$');
//谷歌推荐页面类型
define('PAGE_TYPE_HOME', 'Home');
//谷歌推荐页面类型
define('PAGE_TYPE_PRODUCT', 'Product');
//谷歌推荐页面类型
define('PAGE_TYPE_CATEGORY', 'Category');
//谷歌推荐页面类型
define('PAGE_TYPE_OTHER', 'Other');
//买了又买数据缓存
define('BOUGHT_ALSO_BOUGHT_', 'BOUGHT_ALSO_BOUGHT_');
//搜索栏下方热词数据缓存
define('SEARCH_HOT_KEY_', 'SEARCH_HOT_KEY_');
//首页闪购数据缓存
define('FLASH_DATA_', 'FLASH_DATA_');
//首页广告数据缓存
define('ADVERTISING_INFO_BY_', 'ADVERTISING_INFO_BY_');
//首页新品数据缓存，一级分类页面数据缓存
define('NEW_ARRIVALS_DATA_', 'NEW_ARRIVALS_DATA_');
//首页数据，dx_config_data配置key，通过spu_id 获取产品数据缓存
define('PRODUCT_CONFIG_DATA_BY_', 'PRODUCT_CONFIG_DATA_BY_');
//首页推荐品牌logo图片
define('HOME_BRAND_LOGO_IMG', 'HOME_BRAND_LOGO_IMG');
//google推荐label缓存
define('GOOGLE_CONVERSION_LABEL', 'GOOGLE_CONVERSION_LABEL');
//首页集成分类
define('INTEGRATION_CLASS', 'INTEGRATION_CLASS');
//产品详情缓存
define('PRODUCT_INFO_', 'PRODUCT_INFO_');
//根据pid查询子类的缓存
define('CHILD_CATEGORIES_LIST_BY_', 'CHILD_CATEGORIES_LIST_BY_');
//根据class_id查询分类的缓存
define('SELECT_CATEGORIES_LIST_BY_', 'SELECT_CATEGORIES_LIST_BY_');
//根据class_id查询品牌的缓存
define('CATEGORY_BRAND_BY_', 'CATEGORY_BRAND_BY_');
//根据class_id查询属性的缓存
define('CATEGORY_ATTR_BY_', 'CATEGORY_ATTR_BY_');
//产品运费信息
define('PRODUCT_SHIPPING_INFO_', 'PRODUCT_SHIPPING_INFO_');
//产品评分信息
define('REVIEWS_RATING_', 'REVIEWS_RATING_');
//产品内容信息
define('PRODUCT_DESCRIPTION_', 'PRODUCT_DESCRIPTION_');
//商品评论列表信息
define('REVIEWS_LIST_', 'REVIEWS_LIST_');
//所有分类列表
define('ALL_CATEGORY_LIST_', 'ALL_CATEGORY_LIST_');
//详情页面related推荐数据缓存
define('RELATED_PRODUCT_', 'RELATED_PRODUCT_');
//详情页面看了又看推荐数据缓存
define('VIEW_ALSO_VIEW_', 'VIEW_ALSO_VIEW_');
//查询该分类id的上级，上上级，上上级信息
define('CATEGORY_PID_INFO_', 'CATEGORY_PID_INFO_');
define('CATEGORY_INFO_BY_', 'CATEGORY_INFO_BY_');
//affiliate列表
define('AFFILIATE_LIST', 'AFFILIATE_LIST');
//config_data topsellers配置spu列表
define('TOPSELLERS_CONFIG', 'TOPSELLERS_CONFIG');
//config_data under_price配置spu列表
define('UNDERPRICE_CONFIG', 'UNDERPRICE_CONFIG');
//config_data under_price配置spu列表,获取产品列表
define('UNDERPRICE_CONFIG_PRODUCT_DATA_', 'UNDERPRICE_CONFIG_PRODUCT_DATA_');
//产品标题描述等多语言缓存
define('PRODUCT_LANGUAGE_', 'PRODUCT_LANGUAGE_');
//产品属性等多语言缓存
define('PRODUCT_ATTR_LANGUAGE_', 'PRODUCT_ATTR_LANGUAGE_');
//config_data ProuctTags配置缓存
define('PRODUCT_TAGS_CONFIG', 'PRODUCT_TAGS_CONFIG');
//config_data price lists配置缓存
define('CONFIG_PRICE_LIST', 'CONFIG_PRICE_LIST');
//config_data 详情页payment配置缓存
define('PRODUCT_PAYMENT_CONFIG', 'PRODUCT_PAYMENT_CONFIG');
//config_data Presale页配置缓存
define('PRESALE_CONFIG', 'PRESALE_CONFIG');
//config_data staffpicks页配置缓存
define('STAFFPICKS_CONFIG', 'STAFFPICKS_CONFIG');
//config_data配置sku 根据key缓存spu
define('SPU_CONFIG_DATA_BY_', 'SPU_CONFIG_DATA_BY_');
//config_data配置sku 根据key查找出spu，根据所属的一级分类分组，数量缓存
define('COUNT_CATEGORY_BY_', 'COUNT_CATEGORY_BY_');
//单个国家缓存
define('COUNTRY_BY_', 'COUNTRY_BY_');
//DX-NOC产品类别数据影响关系KEY
define('DX_NOC_CLASS_MAP_KEY', 'DX_NOC_CLASS_MAP_KEY');
//首页FLASH数据
define('HOME_FLASH_DATA', 'HOME_FLASH_DATA');
//更新运费模板存储产品ID以防重复
define('FREIGHT_FORMWORK_ID', 'FREIGHT_FORMWORK_ID');
//单个产品运费计算
define('COUNT_SHIPPING_PRODUCT_', 'COUNT_SHIPPING_PRODUCT_');
//汇率转换缓存
define('EXCHANGE_RATE_', 'EXCHANGE_RATE_');

//购物车
defined('SHOPPINGCART_') or define('SHOPPINGCART_', 'ShoppingCart_');
//checkout
defined('SHOPPINGCART_CHECKOUT_') or define('SHOPPINGCART_CHECKOUT_', 'ShoppingCart_CheckOut');
//BUY NOW
defined('SHOPPINGCART_BUYNOW_') or define('SHOPPINGCART_BUYNOW_', 'ShoppingCartBuyNow_');

//购物车产品缓存
define('CART_PRODUCT_', 'CART_PRODUCT_');
//写入日志表名
define('LOGS_MALL', 'logs_mall');
//写入日志表名--Cart 保持和商城配置的一致
define('LOGS_MALL_CART', 'logs_cart_new');
//dx admin redis缓存
define('REDIS_REVIEW_FILTERING', 'Redis_ReviewFiltering');
//保存获取卖家ID，及邮箱
define('DX_CUSTOMER_ID', 'DX_CUSTOMER_ID');
//五分钟
define('CACHE_FIVE_MIN', 300);
//十分钟
define('CACHE_MINUTE', 600);

//payment推送订单状态和交易明细日志记录表
defined('LOGS_ORDER_STATUS_DETAILS') or define('LOGS_ORDER_STATUS_DETAILS', 'logs_order_status_details');