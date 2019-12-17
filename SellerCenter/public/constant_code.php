<?php
/**
 * 常量定义
 */
/** API请求返回成功码 **/
define('API_RETURN_SUCCESS', "200");

/**
 * 产品状态
 */
//待审核（草稿）
define('PRODUCT_STATUS_REVIEWING', 0);
//已开通（正常销售）
define('PRODUCT_STATUS_SUCCESS', 1);
//预售
define('PRODUCT_STATUS_PRESALE', 2);
//暂时停售
define('PRODUCT_STATUS_STOP_PRESALE', 3);
//已下架
define('PRODUCT_STATUS_DOWN', 4);
//正常销售，编辑状态
define('PRODUCT_STATUS_SUCCESS_UPDATE', 5);
//已删除
define('PRODUCT_STATUS_DELETE', 10);
//审核失败
define('PRODUCT_STATUS_REJECT', 12);

/** queue define start **/
//队列：上传产品-运费模板异步处理
define('QUEUE_PRODUCT_SHIPPING_TEMPLATE', 'addProductShippingTemplateList');
//队列：上传产品-产品图片
define('QUEUE_PRODUCT_MAIN_IMAGES', 'addProductMainImagesList');
//队列：运费模板-修改-异步处理
define('QUEUE_SHIPPING_TEMPLATE_EDITOR', 'editorShippingTemplateListList');

/** queue define end **/

/** redis 缓存key配置 start **/
/*define('QUEUE_SHIPPING_TEMPLATE_EDITOR', 'editorShippingTemplateListList');*/

/** redis 缓存key配置 end **/

/** 配置文件读取配置 start **/
define('CONFIG_SELECT_ENV_LOCAL', 'local');
define('CONFIG_SELECT_ENV_TEST', 'test');
define('CONFIG_SELECT_ENV_ONLINE', 'online');
/** 配置文件读取配置 end **/