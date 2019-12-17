<?php
namespace app\mallextend\model;
use think\Model;
use think\Db;
class SysConfig extends Model{

    public $db;
    public $table;
    public function __construct(){
        $this->db="db_mongodb";
        $this->table="dx_sys_config";
    }
    public function getSysCofig($ConfigName){
        $where['ConfigName'] = $ConfigName;
        return Db::connect($this->db)->table($this->table)->where($where)->find();
    }


}