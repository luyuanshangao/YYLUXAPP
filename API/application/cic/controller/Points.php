<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class Points extends Base
{

    /*
 * 新增用户积分
 * @param int $ID
 * @param string
 * @param string
 * @Return: array
 * */
    public function addPoints(){
        $data['CustomerID'] = input("CustomerID");
        if(empty($data['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $type = input("type",1);
        $user_points = model("PointsBasicInfo")->getPointsBasicInfo($data['CustomerID'],$type);
        if(empty($user_points)){
            $data['ClientID'] = input("ClientID",1);
            $data['TotalCount'] = input("TotalCount",0);
            $data['UsedCount'] = input("UsedCount",0);
            $data['UsableCount'] = input("UsableCount",0);
            $data['InactiveCount'] = input("InactiveCount",0);
            $data['NewTotalCount'] = input("NewTotalCount",0);
            $data['Memo'] = input("Memo");
            $res = model("PointsBasicInfo")->addPoints($data,$type);
            if($res>0){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }else{
            return apiReturn(['code'=>1002,'msg'=>"The integral account already exists"]);
        }
    }

    /*
* 获取用户积分信息
* @param int $ID
* @param int CustomerID
* @Return: array
* */
    public function getPointsBasicInfo(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $type = input("type",1);
        $res = model("PointsBasicInfo")->getPointsBasicInfo($CustomerID,$type);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 获取用户积分详情列表
* @param int CustomerID
* @Return: array
* */
    public function getPointsDetailsList(){
        $paramData = request()->post();
        $where['CustomerID'] = isset($paramData['CustomerID'])?$paramData['CustomerID']:0;
        if(empty($where['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $where['CreateTime'] = isset($paramData['CreateTime'])?$paramData['CreateTime']:'';
        if(isset($where['CreateTime']) && is_array($where['CreateTime'])){
            foreach ($where['CreateTime'] as $key=>$value){
                $where['CreateTime'][$key] = trim($value);
            }
        }

        $where = array_filter($where);
        if(isset($paramData['Status']) && $paramData['Status'] !== ''){
            $where['Status'] = $paramData['Status'];
        }
        if(isset($paramData['IsNewDx'])){
            $where['IsNewDx'] = $paramData['IsNewDx'];
        }
        if(isset($paramData['OrderNumber'])){
            $where['OrderNumber'] = $paramData['OrderNumber'];
        }
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $type = input("type",1);
        $res = model("PointsDetails")->getPointsDetailsList($where,$page_size,$page,$path,$type);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 新增用户积分详情
* */
    public function addPointsDetails(){
        $data['CustomerID'] = input("CustomerID");
        if(empty($data['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $data['ClientID'] = input("ClientID",1);
        $data['OrderNumber'] = input("OrderNumber",0);
        $data['TransactionTime'] = input("TransactionTime",time());
        $data['OperateType'] = input("OperateType",1);
        $data['PointsCount'] = input("PointsCount",0);
        $data['ActiveFlag'] = input("ActiveFlag",1);
        $data['CreateTime'] = time();
        $data['Memo'] = input("Memo");
        $data['Reserve1'] = input("Reserve1");
        $data['DataSource'] = input("DataSource");
        $data['RequestClientID'] = input("RequestClientID");
        $data['Operator'] = input("Operator");
        $data['OperateReason'] = input("OperateReason");
        $data['ReasonDetail'] = input("ReasonDetail");
        $data['ManualOperateReason'] = input("ManualOperateReason");
        $data['Status'] = input("Status",0);
        $data['CurrencyType'] = input("CurrencyType");
        $data['IsNewDx'] = input("IsNewDx");
        $type = input("type",1);//积分类型 1-普通积分 2-介绍积分
        $res = model("PointsDetails")->addPointsDetails($data,$type);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 增加减少用户积分
     * */
    public function editPoints(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $edit_type = input("edit_type",'TotalCount');
        $number = input("number",0);
        $type = input("type",1);
        $res = model("PointsBasicInfo")->editPoints($CustomerID,$edit_type,$number,$type);
        if($res>0){
            $this->addPointsDetails();
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     *用户积分扣减
     * */
    public function DecPoints($paramData = ''){
        $paramData = !empty($paramData)?$paramData:request()->post();
        $CustomerID = $paramData["CustomerID"];
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }

        $data['CustomerID'] = $paramData["CustomerID"];
        $data['ClientID'] = isset($paramData["ClientID"])?$paramData["ClientID"]:1;
        $data['OrderNumber'] = isset($paramData["OrderNumber"])?$paramData["OrderNumber"]:'';
        $data['TransactionTime'] = input("TransactionTime",time());
        $data['OperateType'] = 0;
        $data['PointsCount'] = isset($paramData["PointsCount"])?$paramData["PointsCount"]:0;
        $data['ActiveFlag'] = isset($paramData["ActiveFlag"])?$paramData["ActiveFlag"]:1;
        $data['CreateTime'] = time();
        $data['Memo'] = isset($paramData["Memo"])?$paramData["Memo"]:"";
        $data['Reserve1'] = isset($paramData["Reserve1"])?$paramData["Reserve1"]:"";
        $data['DataSource'] = isset($paramData["DataSource"])?$paramData["DataSource"]:"";
        $data['RequestClientID'] = isset($paramData["RequestClientID"])?$paramData["RequestClientID"]:"";
        $data['Operator'] = isset($paramData["Operator"])?$paramData["Operator"]:"";
        $data['OperateReason'] = isset($paramData["OperateReason"])?$paramData["OperateReason"]:18;
        $data['ReasonDetail'] = isset($paramData["ReasonDetail"])?$paramData["ReasonDetail"]:"";
        $data['ManualOperateReason'] = isset($paramData["ManualOperateReason"])?$paramData["ManualOperateReason"]:"";
        $data['Status'] = isset($paramData["Status"])?$paramData["Status"]:0;
        $data['CurrencyType'] = isset($paramData["CurrencyType"])?$paramData["CurrencyType"]:"";
        $data['IsNewDx'] = isset($paramData["IsNewDx"])?$paramData["IsNewDx"]:1;

        $dx_points = isset($paramData["dx_points"])?$paramData["dx_points"]:0;
        $referral_points = isset($paramData["referral_points"])?$paramData["referral_points"]:0;
        if($dx_points>0){
            $dx_points_info = model("PointsBasicInfo")->getPointsBasicInfo($CustomerID,1);
            if($dx_points_info['UsableCount']<$dx_points){
                return apiReturn(['code'=>1002,'msg'=>'Lack of DXPoints']);
            }
        }
        if($referral_points>0){
            $referral_points_info = model("cic/PointsBasicInfo")->getPointsBasicInfo($CustomerID,2);
            if($referral_points_info['UsableCount']<$dx_points){
                return apiReturn(['code'=>1002,'msg'=>'Lack of ReferralPoints']);
            }
        }
        $res = model("cic/PointsBasicInfo")->DecPoints($CustomerID,$dx_points,$referral_points,$data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
    *用户积分增加
    * */
    public function IncPoints(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $data['CustomerID'] = input("CustomerID");
        $data['ClientID'] = input("ClientID",1);
        $data['OrderNumber'] = input("OrderNumber",'');
        $data['TransactionTime'] = input("TransactionTime",time());
        $data['OperateType'] = input("OperateType",1);
        $data['PointsCount'] = input("PointsCount",0);
        $data['ActiveFlag'] = input("ActiveFlag",0);
        $data['CreateTime'] = time();
        $data['Memo'] = input("Memo");
        $data['Reserve1'] = input("Reserve1");
        $data['DataSource'] = input("DataSource");
        $data['RequestClientID'] = input("RequestClientID");
        $data['Operator'] = input("Operator");
        $data['OperateReason'] = input("OperateReason",18);
        $data['ReasonDetail'] = input("ReasonDetail");
        $data['ManualOperateReason'] = input("ManualOperateReason");
        $data['Status'] = input("Status",0);
        $data['CurrencyType'] = input("CurrencyType");
        $data['IsNewDx'] = input("IsNewDx",1);
        $dx_points = input("dx_points",0);
        $referral_points = input("referral_points",0);
        $res = model("PointsBasicInfo")->IncPoints($CustomerID,$dx_points,$referral_points,$data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 订单取消扣减积分
     * */
    public function CancelOrderDecPoints(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $OrderNumber = input("OrderNumber");
        if(empty($OrderNumber)){
            return apiReturn(['code'=>1001]);
        }
        $OperateReason = input("OperateReason",5);
        $type = input("type",1);
        $where['CustomerID'] = $CustomerID;
        $where['OrderNumber'] = $OrderNumber;
        $where['OperateReason'] = $OperateReason;
        $PointsDetails = model("PointsDetails")->getPointsDetails($where,$type);
        if(!$PointsDetails){
            return apiReturn(['code'=>1006]);
        }
        $res = model("PointsBasicInfo")->CancelOrderDecPoints($CustomerID,$OrderNumber,$OperateReason,$type);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * affiliate订单取消扣减推荐积分
     * */
    public function CancelOrderDecReferralPoints(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $OrderNumber = input("OrderNumber");
        if(empty($OrderNumber)){
            return apiReturn(['code'=>1001]);
        }
        $OperateReason = input("OperateReason",8);
        $type = input("type",2);
        $where['CustomerID'] = $CustomerID;
        $where['OrderNumber'] = $OrderNumber;
        $where['OperateReason'] = $OperateReason;
        $PointsDetails = model("PointsDetails")->getPointsDetails($where,$type);
        if(!$PointsDetails){
            return apiReturn(['code'=>1006]);
        }
        $res = model("PointsBasicInfo")->CancelOrderDecReferralPoints($CustomerID,$OrderNumber,$OperateReason,$type);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * affiliate佣金提现审核不通过
     * */
    public function auditWithdrawalReferralPoints(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $OrderNumber = input("OrderNumber");
        if(empty($OrderNumber)){
            return apiReturn(['code'=>1001]);
        }
        $OperateReason = input("OperateReason",19);
        $type = input("type",2);
        $where['CustomerID'] = $CustomerID;
        $where['OrderNumber'] = $OrderNumber;
        $where['OperateReason'] = $OperateReason;
        //$where['Status'] = 0;
        $PointsDetails = model("PointsDetails")->getPointsDetails($where,$type);
        if(!$PointsDetails){
            return apiReturn(['code'=>1006]);
        }else{
            $where['OperateReason'] = 20;
            $alreadyAuditWithdrawal = model("PointsDetails")->getPointsDetails($where,$type);
            if($alreadyAuditWithdrawal){
                return apiReturn(['code'=>1001,"msg"=>"The commission has been returned, please do not operate again"]);
            }
        }
        $PointsCount = abs($PointsDetails['PointsCount']);
        if(empty($PointsCount)){
            return apiReturn(['code'=>1001,"msg"=>"PointsDetails is null"]);
        }
        $Reason = input("Reason");
        $OperateReason = input("OperateReason",20);
        $type = input("type",2);
        $Operator = input("Operator");
        $OrderNumber = input("OrderNumber");
        $res = model("PointsBasicInfo")->auditWithdrawalReferralPoints($CustomerID,$PointsCount,$OrderNumber,$Reason,$Operator,$OperateReason,$type);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
    * 获取用户DX，推荐可用积分
    * @param int $ID
    * @param int CustomerID
    * @Return: array
    * */
    public function getCustomerPoints(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $res = model("PointsBasicInfo")->getCustomerPoints($CustomerID);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }
    public function OrderAffiliateCredit(){
          $Affiliate_id = input("Affiliate_id");
          $res = model("PointsBasicInfo")->OrderAffiliateCredit($Affiliate_id);
          return $res;
    }
}
