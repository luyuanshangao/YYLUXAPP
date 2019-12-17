<?php
/**
 * Created by PhpStorm.
 * User: heng.zhang
 * Date: 2018/8/9
 * Time: 13:11
 */

namespace app\mall\model;

use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

class NocModel extends Model
{
    const tableName = 'nocnoc_class';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }

    /**
     * 获取NOC类别映射数据
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getNocClassMap(){
        $result = $this->db->name(self::tableName)->field('class_id,pid')->select();
        return $result;
    }

}