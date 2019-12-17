<?php
namespace app\share\model;

use think\Db;
use think\Model;
/**
 * 验证码数据表
 */
class VerificationCode extends Model{

    protected $db;
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 新增验证码
     */
    public function createVerificationCode($data){
        $newData = $this->db->name('verification_code')->insertGetId($data);

    	 return $newData;
    }

    /*
     * 删除验证码
     * */
    public function deleteVerificationCode($where){
        $res =  $this->db->name('verification_code')->where($where)->delete();
        return $res;
    }

    /*
      * 验证验证码
      * */
    public function checkVerificationCode($where){
        $res =  $this->db->name('verification_code')->where($where)->count();
        return $res;
    }
}