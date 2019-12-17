<?php
namespace app\common\model;

use think\Db;
use think\Model;
/**
 * MongoDB自增键模型
 * Created by tinghu.liu
 * Date: 2018/5/11
 * @package app\common\model
 */
class AutoIncrement extends Model
{
    /**
     * 广告管理相关数据设置
     * @var string
     */
    protected $db;
    protected $table = 'dx_auto_increment';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect("db_mongodb");
    }

    /**
     * 获取单条数据
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfo(){
        return $this->db->table($this->table)->find();
    }

    /**
     * 更新数据
     * @param array $where 更新条件
     * @param $update 更新的数据
     * @return int|string
     */
    public function updateDataByWhere(array $where, $update){
        return $this->db->table($this->table)->where($where)->update($update);
    }



}