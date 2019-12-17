<?php
namespace app\cic\controller;
use app\common\controller\Base;
use think\Exception;
use think\Log;
use vendor\aes\aes;
use think\Db;
use think\cache\driver\Redis;

class StoreCredit extends Base
{
    /*
* 获取用户SC信息
* @param int $ID
* @param int CustomerID
* @Return: array
* */
    public function getStoreCarditBasicInfo(){
        $CustomerID = input("CustomerID");
        $Currency = input("Currency");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $where['CustomerID'] = $CustomerID;
        if(!empty($Currency)){
            $where['CurrencyType'] = $Currency;
        }
        $res = model("StoreCarditBasicInfo")->getStoreCarditBasicInfo($where);
        if($res>0){
            $PaymentPassword = model("PaymentPassword")->checkPaymentPassword($CustomerID);
            if($PaymentPassword != true){
                $PaymentPasswordExistCheck = model("PaymentPassword")->PaymentPasswordExistCheck($CustomerID);
                if(!$PaymentPasswordExistCheck){
                    return apiReturn(['code'=>200,'data'=>$res,'PaymentPassword'=>false]);
                }else{
                    return apiReturn(['code'=>200,'data'=>$res,'PaymentPassword'=>true]);
                }
            }
            return apiReturn(['code'=>200,'data'=>$res,'PaymentPassword'=>$PaymentPassword]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
* 获取用户SC详情列表
* @param int CustomerID
* @Return: array
* */
    public function getStoreCreditDetailsList(){
        $paramData = request()->post();
        $where['CustomerID'] = isset($paramData['CustomerID'])?$paramData['CustomerID']:0;
        if(empty($where['CustomerID'])){
            return apiReturn(['code'=>1001]);
        }
        $where['CurrencyType'] = isset($paramData['CurrencyType'])?$paramData['CurrencyType']:'';
        $where['CreateTime'] = isset($paramData['CreateTime'])?$paramData['CreateTime']:'';
        if(isset($where['CreateTime']) && is_array($where['CreateTime'])){
            foreach ($where['CreateTime'] as $key=>$value){
                $where['CreateTime'][$key] = trim($value);
            }
        }
        $where = array_filter($where);
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $res = model("StoreCreditTransaction")->getStoreCreditTransactionList($where,$page_size,$page,$path);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 增加减少用户SC
     * 弃用
     * */
    public function editStoreCarditBasicInfo(){
        $CustomerID = input("CustomerID");
        if(empty($CustomerID)){
            return apiReturn(['code'=>1001]);
        }
        $edit_type = input("edit_type",'TotalAmount');
        $CurrencyType = input("CurrencyType","USD");
        $number = input("number",0);
        $res = model("StoreCarditBasicInfo")->editStoreCarditBasicInfo($CustomerID,$CurrencyType,$edit_type,$number);
        if($res>0){
            $this->addPointsDetails();
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }


    /*
* 新增用户SC详情
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
        $data['TransactionAmount'] = input("number",0);
        $data['CurrencyType'] = input("CurrencyType","USD");
        $data['ManualOperateReason'] = input("ManualOperateReason",1);
        $data['TransactionStatus'] = input("TransactionStatus","00");
        $data['TransactionType'] = input("TransactionType","AD");
        $data['CreateTime'] = time();
        $data['Memo'] = input("Memo");
        $data['DataSource'] = input("DataSource");
        $data['Status'] = 1;
        $data['Reserve1'] = input("Reserve1");
        $data['RequestClientID'] = input("RequestClientID",1);
        $res = model("StoreCreditTransaction")->addStoreCreditTransaction($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }


    /*
     * StoreCredit支付
     * */
    public function PaymentByStoreCredit(){
        try{
            $paramData = input();
            $data['ClientID'] = isset($paramData['ClientID'])?$paramData['ClientID']:1;
            $data['CurrencyType'] = $paramData['CurrencyType'];
            $data['CustomerID'] = $paramData['CustomerID'];
            $data['OrderNumber'] = $paramData['OrderNumber'];
            $data['PaymentAmount'] = $paramData['PaymentAmount'];
            $data['DataSource'] = $paramData['DataSource'];
            $StoreCreditUsableAmoutmodel = model("StoreCarditBasicInfo")->getStoreCreditUsableAmout(['CustomerID'=>$data['CustomerID'],'CurrencyType'=>$data['CurrencyType']]);
            if($StoreCreditUsableAmoutmodel<$data['PaymentAmount']){
                return apiReturn(['code'=>1002,'msg'=>"StoreCardit insufficient"]);
            }
            $CurrencyType = model("StoreCarditBasicInfo")->getCurrencyTypeByCustomerID($data['CustomerID']);
            if(!in_array($data['CurrencyType'],$CurrencyType)){
                return apiReturn(['code'=>1002,'msg'=>"CurrencyType Atypism"]);
            }
            $res = model("StoreCarditBasicInfo")->PaymentRefundByStoreCredit($data);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,"msg"=>$e->getMessage()]);
        }

    }

    /*
     *SC 退款接口
     * */
    public function RefundByStoreCredit(){
        $paramData = input();
        $data['CustomField'] = $paramData['CustomField'];
        $data['RefundAmount'] = $paramData['RefundAmount'];
        $data['TransactionAmount'] = $paramData['RefundAmount'];
        $data['DXBankOrderNumber'] = $paramData['DXBankOrderNumber'];
        $StoreCarditBasicInfo = model("StoreCarditBasicInfo")->getStoreCreditTransaction(['DXBankOrderNumber'=>$data['DXBankOrderNumber']],2);
        if(!$StoreCarditBasicInfo){
            return apiReturn(['code'=>1002,'msg'=>'Payment records are not found']);
        }
        $data['ClientID'] = $StoreCarditBasicInfo['RequestClientID'];
        $data['CurrencyType'] = $StoreCarditBasicInfo['CurrencyType'];
        $data['CustomerID'] = $StoreCarditBasicInfo['CustomerID'];
        $data['OrderNumber'] = $StoreCarditBasicInfo['OrderNumber'];
        $data['DataSource'] = $StoreCarditBasicInfo['DataSource'];
        $CurrencyType = model("StoreCarditBasicInfo")->getCurrencyTypeByCustomerID($data['CustomerID']);
        if(!in_array($data['CurrencyType'],$CurrencyType)){
            return apiReturn(['code'=>1002,'msg'=>"CurrencyType Atypism"]);
        }
        $res = model("StoreCarditBasicInfo")->PaymentRefundByStoreCredit($data,2);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 强制添加减少SC
     * */
    public function OperateStoreCredit(){
        $paramData = input();
        try{
            $data['ClientID'] = $paramData['ClientID'];
            $data['CurrencyType'] = $paramData['CurrencyType'];
            $data['CustomerID'] = $paramData['CustomerID'];
            $data['OrderNumber'] = isset($paramData['OrderNumber'])?$paramData['OrderNumber']:"";
            $data['TransationType'] = $paramData['TransationType'];
            $data['StoreCreditAmount'] = $paramData['StoreCreditAmount'];
            $data['Memo'] = isset($paramData['Memo'])?$paramData['Memo']:'';
            $data['DataSource'] = $paramData['DataSource'];
            if($data['TransationType'] == 0){
                $StoreCreditUsableAmoutmodel = model("StoreCarditBasicInfo")->getStoreCreditUsableAmout(['CustomerID'=>$data['CustomerID']]);
                if($StoreCreditUsableAmoutmodel<$data['StoreCreditAmount']){
                    return apiReturn(['code'=>1002,'msg'=>"StoreCardit insufficient"]);
                }
            }
            $CurrencyType = model("StoreCarditBasicInfo")->getCurrencyTypeByCustomerID($data['CustomerID']);
            $res = model("StoreCarditBasicInfo")->OperateStoreCredit($data);
            if(!in_array($data['CurrencyType'],$CurrencyType)){
                return apiReturn(['code'=>1002,'msg'=>"CurrencyType Atypism"]);
            }
        }catch (\Exception $e){
            return $e->getMessage();
            $res = false;
        }
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }

    /*
     * 5.查询StoreCredit交易记录
     * */
    public function QueryStoreCreditTransaction(){
        $paramData = input();
        if(isset($paramData['CurrencyType'])){
            $where['CurrencyType'] = $paramData['CurrencyType'];
        }
        if(isset($paramData['CustomerID'])){
            $where['CustomerID'] = $paramData['CustomerID'];
        }
        if(isset($paramData['DXBankOrderNumber'])){
            $where['DXBankOrderNumber'] = $paramData['DXBankOrderNumber'];
        }
        if(isset($paramData['OrderNumber'])){
            $where['OrderNumber'] = $paramData['OrderNumber'];
        }
        if(isset($paramData['TransactionType'])){
            $where['TransactionType'] = $paramData['TransactionType'];
        }
        if(isset($paramData['BeginDate']) && isset($paramData['EndDate'])){
            $where['CreateTime'] = ["between",[strtotime($paramData['BeginDate']),strtotime($paramData['EndDate'])]];
        }else{
            /*成交开始时间*/
            if(isset($paramData['BeginDate'])){
                $where['CreateTime'] = ['gt',strtotime($paramData['BeginDate'])];
            }

            /*成交结束时间*/
            if(isset($paramData['EndDate'])){
                $where['CreateTime'] = ['lt',strtotime($paramData['EndDate'])];
            }
        }
        $page_size = input("PageRecordCount",20);
        $page = input("CurrentPagination",1);
        $path = input("path");
        $res = model("StoreCarditBasicInfo")->QueryStoreCreditTransaction($where,$page_size,$page,$path);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1006]);
        }
    }
    /*
     * admin StoreCredit管理
     */
    public function  StoreCredit(){
        $data = input();
        $res = model("StoreCredit")->StoreCredit($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
    /*
     * admin StoreCredit管理用户详情
     */
    public function StoreCreditDetails(){
        $data = input();
        $res = model("StoreCredit")->StoreCreditDetails($data);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>100,'data'=>'提交数据有误']);
        }

    }
    /*
     * admin DxPoints管理
     */
    public function DxPoints(){
        $data = input();
        $res = model("StoreCredit")->DxPoints($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
     /*
     * admin DxPoints 详情
     */
    public function DxPointsDetails(){
        $data = input();
        $res = model("StoreCredit")->DxPointsDetails($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
     /*
     * admin Affililate佣金管理
     */
    public function Affililate(){
        $data = input();
        $res = model("StoreCredit")->Affililate($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
    /*
    * admin Affililate佣金管理
    */
    public function AffililateDetails(){
        $data = input();
        $res = model("StoreCredit")->AffililateDetails($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
    /**
     * admin Affiliate用户统计
     * [AffiliateUserStatistics description]
     */
    public function AffiliateUserStatistics(){
        $data = input();
        $res = model("StoreCredit")->AffiliateUserStatistics($data);
        return apiReturn(['code'=>200,'data'=>$res]);
    }
     /**
     * 获取Affililate新用户数量
     * @param $data
     * @return string
     */
    public function AffiliateIdSum(){
        $data = input();
        $res = model("StoreCredit")->AffiliateIdSum($data);
        return $res;
    }
    /*
    * admin 修改 cic   cic_referral_points_details 表
    */
    public function WithdrawStatus(){
        $data = input();
        return model("StoreCredit")->WithdrawStatus($data);
        // return apiReturn(['code'=>200,'data'=>$res]);
    }
    /**
     * admin  获取cic  id
     * [add_black description]
     */
    public function add_black(){
        $data = input();
        return model("StoreCredit")->add_black($data);
    }

    public function getAllStoreCarditBasicInfo(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Customer.getAdminCustomerInfo");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $type = isset($paramData['type'])?$paramData['type']:0;//是否获取币种符号，0不获取，1获取
            $where['CustomerID'] = $paramData['CustomerID'];
            $res = model("StoreCredit")->getAllStoreCarditBasicInfo($where);
            if($res){
                if($type){
                    foreach ($res as $key=>&$value){
                        $value = getCurrency('',$key).' '.$value;
                    }
                }
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
