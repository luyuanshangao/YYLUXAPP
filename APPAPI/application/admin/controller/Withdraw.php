<?php
namespace app\admin\controller;

use think\cache\driver\Redis;
use think\Controller;
use vendor\aes\aes;
use think\log;
class Withdraw extends Controller
{
    /**
     * [addWithdraw description]
     * @author Wang
     * @date 2019-01-09
     */
    public function addWithdraw(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"Withdraw.addWithdraw");
        if(empty($paramData['Affiliate_id']) || empty($paramData['order_id']) || empty($paramData['amount'])){
             return apiReturn(['code'=>1002,'msg'=>'The system has failed. Please refresh and try again.']);
        }
        $data['order_id']     = rtrim($paramData['order_id'],',') ;
        $getOrder = model("Withdraw")->getOrdermodel($data['order_id']);//获取对应订单时间
        // $getOrder = model("Withdraw")->saveWithdraw($data);
        if($getOrder['code'] != 200){
            return apiReturn($getOrder);
        }
        $data['affiliate_id'] = $paramData['Affiliate_id'];
        $data['start_time']   = $getOrder['data']['start_time'];
        $data['end_time']     = $getOrder['data']['end_time'];
        $data['amount']       = $paramData['amount'];
        $data['status']       = 0;
        $data['add_time']     = time();
        $affiliateApply = model("Withdraw")->affiliateApply($data);//提交数据
        if($affiliateApply['code'] != 200 ){
            return apiReturn($res);
        }
        $affiliateOrder = model("Withdraw")->affiliateOrder($data['order_id']);//提交数据
        return apiReturn($affiliateOrder);
    }
    /*
     * 添加提现申请
     * 弃用
     * */
    public function addWithdraw_1(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Withdraw.addWithdraw");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $SysConfigController = controller("mallextend/SysConfig");
            $CommissionWithdrawal = $SysConfigController->getSysCofigValue(["ConfigName"=>"CommissionWithdrawal"]);
            $OneMonthWithdrawAmount = model("Withdraw")->getMonthWithdrawAmount($paramData['customer_id']);
            if($CommissionWithdrawal['code'] == 200){
                $WithdrawalConfig = json_decode($CommissionWithdrawal['data'],true);
                if(isset($WithdrawalConfig['MinAmount']) && isset($WithdrawalConfig['MaxAmount'])){
                    $PointsBasicInfo = model("cic/PointsBasicInfo")->getPointsBasicInfo($paramData['customer_id'],2);
                    if(isset($PointsBasicInfo['NewTotalCount'])){
                        /*当用户提现金额最小金额大于用户总金额*/
                        if($WithdrawalConfig['MinAmount']>$PointsBasicInfo['NewTotalCount']){
                            return apiReturn(['code'=>1002,'msg'=>"Cash Withdrawal Commission Must Be Greater Than {$WithdrawalConfig['MinAmount']}"]);
                        }else{
                            $MaxAmount = $WithdrawalConfig['MaxAmount']-$OneMonthWithdrawAmount;
                        }
                    }else{
                        $MaxAmount = $WithdrawalConfig['MaxAmount']-$OneMonthWithdrawAmount;
                    }
                    if($paramData['amount'] < $WithdrawalConfig['MinAmount'] || $paramData['amount'] > $MaxAmount){
                        return apiReturn(['code'=>1002,'msg'=>"You have applied for withdraw $".$OneMonthWithdrawAmount." within one month,Withdrawal Amount Must be between $".$WithdrawalConfig['MinAmount']."-{$MaxAmount}"]);
                    }
                }
            }
            if(isset($paramData['email']) && is_email($paramData['email']) == true) {//传入账号是邮箱
                $email_array = explode("@", $paramData['email']);
                $EmailDomainName = $email_array[1];
                vendor('aes.aes');
                $aes = new aes();
                $EmailUserName = $aes->encrypt($email_array[0], 'AffiliateLevel', 'PayPalEU');//加密邮件前缀
                $data['PayPalEU'] = $EmailUserName;
                $data['PayPalED'] = $EmailDomainName;
            }
            $data['order_number'] = createNumner();
            $data['customer_id'] = input("customer_id");
            $data['customer_name'] = input("customer_name");
            $data['amount'] = input("amount");
            $data['status'] = input("status",1);
            $data['bank_withdrawals'] = input("seller_name");
            $data['bank_card'] = input("report_small_type");
            $data['bank_name'] = input("product_url");
            $data['customer_type'] = input('customer_type',2);
            $data['add_time'] = time();
            $res = model("Withdraw")->saveWithdraw($data);
            if($res){
                $point['CustomerID'] = $data['customer_id'];
                $point['PointsCount'] = $data['amount'];
                $point['Memo'] = "Apply for withdrawal";
                $point['OrderNumber'] = $data['order_number'];
                $point['OperateReason'] = 19;
                $point['ReasonDetail'] = "Apply for withdrawal";
                $point['referral_points'] = $data['amount'];
                $point['Operator'] = $data['customer_name'];
                $PointsController = controller("cic/Points");
                $DecPointsRes = $PointsController->DecPoints($point);
                if(!$DecPointsRes){
                    Log::record('DecPoints异常：'.$DecPointsRes['msg']);
                }
            }
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
    /**
     * 获取订单列表
     * [OrderAffiliateList description]
     * @author Wang
     * @date 2019-01-10
     */
    public function OrderAffiliateList(){
         if($data = request()->post()){
            $where = [];
            if(!empty($data['page_size'])){
                 $page_size = $data['page_size'];
            }else{
                 $page_size = 20;
            }
            if(!empty($data['page'])){
                 $page = $data['page'];
            }else{
                 $page = 1;
            }
            if(!empty($data['CustomerID'])){
                $where['cic_id'] = $data['CustomerID'];
            }
            if(!empty($data['affiliate_id'])){
                $where['affiliate_id'] = $data['affiliate_id'];
            }
            if(!empty($data['add_time'])){
                $where['add_time'] = array('egt',$data['add_time']);
            }
            if(!empty($data['add_time'])){
                $where['add_time'] = array('egt',$data['add_time']);
            }
            if(!empty($data['OrderNumber'])){
                $where['order_number'] = $data['OrderNumber'];
            }
            if(!empty($data['settlement_status'])){
                if($data['settlement_status'] != 1 && $data['settlement_status'] != 2 ){
                       $where['settlement_status'] = array('egt',3);
                }else{
                       $where['settlement_status'] = $data['settlement_status'];
                }
            }
            if(!empty($data['countPage'])){
                 $count = $data['countPage'];
            }else{
                 $count = '';
            }
            if(!empty($where)){
               $OrderAffiliateList = model("Withdraw")->OrderAffiliateList($where,$page,$page_size,$count);//提交数据
               return $OrderAffiliateList;
            }else{
               return ['code'=>1002,'msg'=>$OrderAffiliateList];
            }
         }else{
            return ['code'=>1002,'msg'=>'Data transmission error.'];
         }

    }
}
