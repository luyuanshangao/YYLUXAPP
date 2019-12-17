<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 产品相关表模型，为了避免都在产品模型建立造成臃肿
 * 开发：钟宁
 * 时间：2019-05-14
 */
class ProductExtendModel extends Model{


    protected $db;
    const product_under = 'product_under5';//低于5美金按国家区分

    //0.99对应关系
    public $underPrice = [
        '0.99' => 1,
        '1.99' => 2,
        '2.99' => 3,
        '3.99' => 4,
        '4.99' => 5,
    ];

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
    public function add($data){
        return $this->db->name(self::product_under)->insert($data);
    }

    /**
     * 查找
     * @param array|callable|null|string|\think\db\Query $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function find($params){
        return $this->db->name(self::product_under)->where(['Country' => trim($params['country']),'Type' => (int)$params['type']])
            ->field(['_id'=>false])->find();
    }

    /**
     * 修改
     * @param $params
     * @param $update
     * @return array|false|\PDOStatement|string|Model
     */
    public function upd($params,$update){
        return $this->db->name(self::product_under)->where(['Country' => trim($params['country']),'Type' => (int)$params['type']])->update($update);
    }
}