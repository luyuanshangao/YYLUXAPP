<?php
namespace app\mallextend\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

//产品记录表
class ProductRecordModel extends Model{

    protected $db;
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }


    /**
     * 新增产品记录
     * @param $action
     * @param array $fields
     * @param $product_id
     * @param $sku_id
     * @return bool
     */
    public function add($action,$fields = [],$product_id,$sku_id){
        $params['action'] = $action;
        $params['fields'] = [];
        $params['product_id'] = (int)$product_id;
        $params['sku_id'] = (int)$sku_id;
        $params['create_time'] = date('Y-m-d H:i:s',time());

        $ret = $this->db->name('product_record')->insert($params);
        if(!$ret){
            return false;
        }
        return true;
    }
}