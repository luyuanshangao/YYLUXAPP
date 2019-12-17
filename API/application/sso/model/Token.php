<?php
namespace app\sso\model;
use think\Model;
use think\Db;
/**
 * Token模型
 * @author
 * @version Kevin 2018/3/15
 */
class Token extends Model{
    /*
     * 获取用户token
     * @param int $cicID 用户ID
     * @Return: array
     * */
    public function getToken($where){
        $db = Db::connect('db_sso');
        $token = $db->name("usertoken")->where($where)->field("token,timeout,cicID,isremember")->find();
        return $token;
    }

    /*
     * 添加用户token
     * @param array $data 用户token信息
     * @Return: bool
     * */
    public function addToken($data){
        $db = Db::connect('db_sso');
        $res = $db->name("usertoken")->insert($data);
        return $res;
    }
    /*
         * 更改用户token
         * @param array $data 用户token信息
         * @Return: bool
         * */
    public function updateToken($data,$where=''){
        $db = Db::connect('db_sso');
        $res = $db->name("usertoken")->where($where)->update($data);
        return $res;
    }


}