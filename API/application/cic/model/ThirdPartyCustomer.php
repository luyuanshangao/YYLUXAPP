<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 第三方用户信息模型
 * @author
 * @version Kevin 2018/9/7
 */
class ThirdPartyCustomer extends Model{
    protected $table = 'cic_third_party_customer';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }

    /*判断第三方用户ID是否存在*/
    public function IsExistAccountID($where){
        $res = $this->db->table($this->table)->where($where)->value("CustomerID");
        return $res;
    }

    /*保存更改第三方用户信息*/
    public function saveThirdPartyCustomer($data,$where=''){
        if(empty($where)){
            $res = $this->db->table($this->table)->insertGetId($data);
        }else{
            $res = $this->db->table($this->table)->where($where)->update($data);
        }
        return $res;
    }

}