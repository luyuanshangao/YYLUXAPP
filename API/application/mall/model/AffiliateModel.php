<?php
namespace app\mall\model;

use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * Affiliate Model层
 */
class AffiliateModel extends Model{

    protected $db;
    const tableName = 'affiliate_code';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * Affiliate所有列表信息
     */
    public function lists(){
        return $this->db->name(self::tableName)->field(['_id','Html'])->select();
    }

    /**
     * 查找某个affiliate
     */
    public function find($id){
        return $this->db->name(self::tableName)->where(['_id' =>(int)$id])->field(['_id','Html'])->find();
    }

}