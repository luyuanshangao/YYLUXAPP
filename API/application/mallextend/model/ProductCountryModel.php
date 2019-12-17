<?php
namespace app\mallextend\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

class ProductCountryModel extends Model{

    protected $db;
    protected $table_name = 'product_regions_price';
    protected $table_blacklist = 'product_regions_price_blacklist';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 更新国家产品价格
     * @param $where
     * @param $update
     * @return int|string
     */
    public function updateCountryProductSkuPrice($where,$update){
        return $this->db->name($this->table_name)->where($where)->update($update);
    }

    /**
     * 查询国家产品价格
     * @param $where
     * @return int|string
     */
    public function findCountryProductSkuPrice($where){
        return $this->db->name($this->table_name)->where($where)->field(['_id'=>false])->find();
    }

    /**
     * 新增国家产品价格
     * @param $insert
     * @return int|string
     */
    public function insertCountryProductSkuPrice($insert){
        return $this->db->name($this->table_name)->insert($insert);
    }

    //单个查询国家
    public function findCountry($country){
        return $this->db->name('region')->where(['ParentID'=>0,'Code'=>$country])->find();
    }

    /**
     * 多个查询所有国家产品价格
     * @param $where
     * @return int|string
     */
    public function selectCountryProduct($where){
        return $this->db->name($this->table_name)->where($where)->field(['_id'=>false])->select();
    }

    //删除国家
    public function deleteCountryProduct($where){
        return $this->db->name($this->table_name)->where($where)->delete();
    }

    /**
     * 分页查询
     * @param $params
     * @return array
     */
    public function paginateCountryProduct($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : 10;
        $page = isset($params['page']) ? $params['page'] : 1;
        $query = $this->db->name($this->table_name);
        //搜索字段
        $query->field(['_id'=>false]);
        return $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
    }

    /**
     * 查询国家产品价格黑名单
     * @param $where
     * @return int|string
     */
    public function findCountryProductBlacklist($where){
         return $this->db->name($this->table_blacklist)->where($where)->field(['_id'=>false])->find();
    }

    /**
     * 查询国家产品价格黑名单
     * @param $where
     * @return int|string
     */
    public function deleteCountryProductBlacklist($where){
        return $this->db->name($this->table_blacklist)->where($where)->delete();
    }

    /**
     * 新增国家产品价格黑名单
     * @param $data
     * @return int|string
     */
    public function addCountryProductBlacklist($data){
        return $this->db->name($this->table_blacklist)->insert($data);
    }
}