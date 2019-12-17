<?php
namespace app\admin\model;
use think\Model;
use think\Db;
class User extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
        $this->table = 'dx_user';
    }
    /*
     * 获取用户
     * */
    public function getUserInfo($where)
    {
        $res = $this->db->table($this->table)
            ->where($where)
            ->field("id,username,group_id")
            ->find();
        return $res;
    }
}
