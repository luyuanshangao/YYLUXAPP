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
    protected $affiliate_level = 'affiliate_level';
    protected $customer = 'customer';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_cic');
    }
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
    /**
     * 获取用户信息
     * [FinancialAudit description]
     * @author: wang
     * @AddTime:2019-01-28
     * -------------------------------------------------------------------------------
     * 把原来获取用户邮件改成PayPal地址顺便把查询改成联合查询减少对数据表的访问
     * @author: wang
     * @editTime:2019-05-16
     *
     */
    public function FinancialAudit($data){
          $customer_id = '';
          $list_customer = [];
          //已生效佣金,未生效佣金
          $list = $this->db->name($this->affiliate_level)
          ->alias('AL')
          ->join($this->customer.' C','AL.CustomerID = C.ID')
          ->where($data)
          ->field('AL.RCode,AL.CustomerID,AL.IsBlacklist,AL.PayPalEU,AL.PayPalED,C.UserName,C.NickName')
          ->select();
          // $list = $this->db->name($this->affiliate_level)->where($data)->field('RCode,CustomerID,IsBlacklist,PayPalEU,PayPalED')->select();
          // if(empty($list)){
          //    return  apiReturn(['code'=>1002,'msg'=>'没有找到数据']);
          // }
          // foreach ($list as $k => $v) {
          //     if(!empty($v["CustomerID"])){
          //        $list_customer = $this->db->name($this->customer)->where(['ID'=>$v["CustomerID"]])->field('ID,UserName,NickName,EmailUserName,EmailDomainName')->find();
          //        if(!empty($list_customer)){
          //            $list[$k]  = array_merge($v,$list_customer);
          //        }
          //     }
          // }
          // 'sql'=>$this->db->name($this->affiliate_level)->getLastSql()
          return  apiReturn(['code'=>200,'data'=>['list'=>$list]]);
    }
}