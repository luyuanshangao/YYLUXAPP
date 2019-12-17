<?php
namespace app\admin\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;

/**
 * 日志模板
 * 开发：钟宁
 * 创建时间：2019-10-11
 */
class LogModel extends Model{

    /**
     * 日志表名
     */
    const DB_LOG_CART = 'logs_cart_new';  //商城购物车日志
    const DB_LOG_MALL = 'logs_mall';  //商城日志
    const DB_LOG_MALL_API = 'logs_mall_api';  //商城api接口日志
    const DB_LOG_MALLEXTEND_API = 'logs_mallextend_api';  //商城api扩展日志
    const DB_LOG_MOBILE_CART = 'logs_mobile_cart_new';  //移动端购物车日志
    const DB_LOG_MOBILE_MALL = 'logs_moblie_mall';  //移动端商城日志
    const DB_LOG_ORDER_STATUS_DETAILS = 'logs_order_status_details';  //
    const DB_LOG_ORDRE_TRACE = 'logs_order_trace';    //
    const DB_LOG_ORDERFRONTEND_TASK = 'logs_orderfrontend_task';    //
    const DB_LOG_PAYMENT = 'logs_payment';    //
    const DB_LOG_WIND_CONTROL = 'logs_wind_control';    //


    /**
     * 日志表数组
     * @var array
     */
    public static $tableLogArr = [
        self::DB_LOG_CART,
        self::DB_LOG_MALL,
        self::DB_LOG_MALL_API,
        self::DB_LOG_MALLEXTEND_API,
        self::DB_LOG_MOBILE_CART,
        self::DB_LOG_MOBILE_MALL,
        self::DB_LOG_ORDER_STATUS_DETAILS,
        self::DB_LOG_ORDRE_TRACE,
        self::DB_LOG_ORDERFRONTEND_TASK,
        self::DB_LOG_PAYMENT,
        self::DB_LOG_WIND_CONTROL,
    ];

    /**
     * @var array 日志类型
     */
    public static $logType = ['log', 'error', 'info', 'sql', 'notice', 'alert', 'debug'];

    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongo_log');
    }


    /**
     * 获取信息【分页】
     * @param string $table 表名
     * @param array $where 条件查询
     * @param int $page_size 分页大小
     * @param array $params 查询条件
     * @return $this
     */
    public function getLogPaginate($table,$where = array(), $page_size=10,$params = array()){
        return $this->db->name($table)->where($where)->order('_id','desc')->paginate($page_size,false,['query'=>$params]);
    }


}