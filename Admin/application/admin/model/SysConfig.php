<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 2018/4/11
 * Time: 10:55
 */
namespace app\admin\model;
use think\Model;
use think\Db;
class SysConfig  extends Model{
    public function __construct(){
        $this->db="db_mongo";
        $this->table="dx_sys_config";
    }
    public function getSysCofig($ConfigName){
        $where['ConfigName'] = $ConfigName;
        return Db::connect($this->db)->table($this->table)->where($where)->find();
    }
}