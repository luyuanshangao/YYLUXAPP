<?php
namespace app\mall\model;

use app\common\controller\Mongo;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use think\mongo\Query;

/**
 * 开发：钟宁
 * 功能：兼容历史数据，sku与spu映射关系
 * 时间：2018-07-23
 */
class ProductMappingsModel extends Model{

    protected $mapping = 'product_mappings';
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 获取spu
     * @param $id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function find($id){
        return $this->db->name($this->mapping)->where(['_id'=>(int)$id])->field(['newId'=>true,'_id'=>true])->find();
    }

}