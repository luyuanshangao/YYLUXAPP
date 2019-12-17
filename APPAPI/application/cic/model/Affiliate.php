<?php
namespace app\cic\model;
use think\Model;
use think\Db;
/**
 * affiliate模型
 * @author
 * @version Kevin 2018/3/25
 */
class Affiliate extends Model{
    public function getAffiliateLevel($where){
        $db = Db::connect('db_cic');
        $res = $db->name("affiliate_level")->where($where)->field("ID,RCode,CustomerID,WebsiteURL,RegistrationTimestamp,Active,CommissionRate,IsPartner,LastChangeLevelTime,LevelIndex,PayPalEU,PayPalED,IsNew")->find();
        return $res;
    }

    /*
    * 保存用户AffiliateLevel
    * */
    public function saveAffiliateLevel($data,$where = ''){
        $db = Db::connect('db_cic');
        if(empty($data['ID']) && empty($where)){
            $count = $db->name('affiliate_level')->where(['CustomerID'=>$data['CustomerID']])->count();
            if($count<1){
                $res = $db->name('affiliate_level')->insertGetId($data);
            }else{
                $res = false;
            }
        }else{
            $res = $db->name('affiliate_level')->where($where)->update($data);
            if($res){
                $res = $db->name('affiliate_level')->where($where)->value("ID");
            }
        }
        return $res;
    }

    /*
     * 添加AffiliateLevelLog
     * */
    public function addAffiliateLevelLog($data){
        $db = Db::connect('db_cic');
        $res = $db->name('affiliate_level_log')->insert($data);
        return $res;
    }
}