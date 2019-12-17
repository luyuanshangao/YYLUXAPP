<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\mallextend\product\ErpCreateProductParams;
use app\common\params\mallextend\product\ErpCreateProductSkuParams;
use app\common\params\mallextend\product\ProductParams;
use app\common\params\seller\product\CreateProductParams;
use app\common\params\seller\product\CreateProductSkuParams;
use app\common\params\mallextend\product\FindProductParams;
use app\common\params\seller\product\UpdateProductStatusParams;
use app\demo\controller\Auth;
use app\mallextend\model\ProductBrandModel;
use app\mallextend\model\ProductClassModel;
use app\mallextend\model\ProductHistoryModel;
use app\mallextend\model\ProductModel;
use app\mallextend\services\BaseService;
use app\mallextend\services\ProductService;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\WriteConcern;
use think\Exception;
use think\Log;
use think\Monlog;


/**
 * 功能：js时间戳
 * 开发：钟宁
 * 时间：2018-12-24
 */
class JavaScriptTime extends Base
{
    public $redis;

    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
    }

	/**
	 * 获取时间
	 */
	public function get(){
//        $time = '';
//        if(config('cache_switch_on')) {
//            $time = $this->redis->get('JAVASCRIPT_TIMESTAMP');
//        }
//        if(empty($time)){
            $time = time();
            $this->redis->set('JAVASCRIPT_TIMESTAMP',$time,CACHE_DAY*5);
//        }
        return $time;
	}
}
