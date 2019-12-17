<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class Affiliate extends Base
{
    /*
     * affiliate用户加入黑名单
     * */
    public function joinBlacklist(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Affiliate.joinBlacklist");
            if(true !== $validate || empty($paramData)){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where['RCode'] = $paramData['RCode'];
            $where['CustomerID'] = $paramData['CustomerID'];
            $AffiliateLevelRes = model("Affiliate")->getAffiliateLevel($where);
            if(!$AffiliateLevelRes){
                return apiReturn(['code'=>1002,'msg'=>"Data does not exist"]);
            }
            $update_data['IsBlacklist'] = 1;
            $update_data['LastChangeLevelTime'] = time();
            $AffiliateLevelRes = model("Affiliate")->saveAffiliateLevel($update_data,$where);
            if($AffiliateLevelRes){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * affiliate用户移除黑名单
     * */
    public function removeBlacklist(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Affiliate.removeBlacklist");
            if(true !== $validate || empty($paramData)){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where['RCode'] = $paramData['RCode'];
            $where['CustomerID'] = $paramData['CustomerID'];
            $AffiliateLevelRes = model("Affiliate")->getAffiliateLevel($where);
            if(!$AffiliateLevelRes){
                return apiReturn(['code'=>1002,'msg'=>"Data does not exist"]);
            }
            $update_data['IsBlacklist'] = 2;
            $update_data['LastChangeLevelTime'] = time();
            $AffiliateLevelRes = model("Affiliate")->saveAffiliateLevel($update_data,$where);
            if($AffiliateLevelRes){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
