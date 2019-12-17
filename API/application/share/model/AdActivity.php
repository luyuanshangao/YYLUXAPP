<?php
namespace app\share\model;

use think\Db;
use think\Model;
/**
 * 广告管理
 */
class AdActivity extends Model{

    protected $db;
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

/*
 * 获取广告
 * */
    public function getAdActivityByKey($Key){
        $where['Key'] = $Key;
        return $this->db->name('ad_activity')->where($where)->field(['Banners','Skus','Keyworks','Key','_id'])->find();
    }
}