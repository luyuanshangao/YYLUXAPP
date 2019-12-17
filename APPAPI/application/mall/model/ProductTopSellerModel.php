<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 每天有销量的产品表模型
 */
class ProductTopSellerModel extends Model{

    const TableProductActivity = 'product_topseller_day';//每天有销量的产品

    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 新增
     * @param $data
     * @return int|string
     */
    public function addTopProduct($data){
        return $this->db->name(self::TableProductActivity)->insert($data);
    }

    /**
     * 更新
     * @return int|string
     */
    public function updateTopProduct($where,$update){
        return $this->db->name(self::TableProductActivity)->where($where)->update($update);
    }

    /**
     * 查找
     * @return int|string
     */
    public function findTopProduct($where,$field){
        return $this->db->name(self::TableProductActivity)->where($where)->field($field)->find();
    }

    /**
     * 获取最新一天的top产品
     */
    public function getNewDataTopData($field){
        return $this->db->name(self::TableProductActivity)->order('date','desc')->field($field)->find();

    }


}