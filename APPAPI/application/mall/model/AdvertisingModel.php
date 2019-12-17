<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 广告
 */
class AdvertisingModel extends Model{

    protected $db;
    const tableName = 'ad_activity';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 根据key获取 --单个详情
     */
    public function find($params){
        $where = array();
        $query = $this->db->name(self::tableName);

        if(isset($params['id']) && $params['id']){
            $where = ['_id' => (int)$params['id']];
        }
        if(isset($params['key']) && $params['key']){
            $where = ['Key' => $params['key']];
        }
        $query->field(['Banners','Skus','Keyworks','Key','_id']);
        return $query->where($where)->find();
    }


    /**
     * 广告列表
     */
    public function lists($params){
        $where = array();
        $query = $this->db->name(self::tableName);

        if(isset($params['key']) && $params['key']){
            $where = ['Key' => ['in',$params['key']]];
        }
        $query->field(['Banners','Skus','Keyworks','Key','_id']);
        return $query->where($where)->select();

    }

}