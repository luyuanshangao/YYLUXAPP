<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * 用户等级模型
 * @author
 * @version Kevin 2018/4/17
 */
class Viplevel extends Model{
    public function getCount($CustomerID){
        $db = Db::connect('db_cic');
        $where['CustomerID'] = $CustomerID;
        $res = $db->name("viplevel")->where($where)->count();
        return $res;
    }
    /*
    * 保存用户收货地址
    * */
    public function saveViplevel($data){
        $db = Db::connect('db_cic');
        if(empty($data['ID'])){
            $count = $this->getCount($data['CustomerID']);
            if($count<1){
                $data['LastChangeLevelTime'] = time();
                $res = $db->name('viplevel')->insert($data);
            }else{
                return false;
            }
        }else{
            $data['LastChangeLevelTime'] = time();
            $res = $db->name('viplevel')->update($data);
        }
        return $res;
    }
}