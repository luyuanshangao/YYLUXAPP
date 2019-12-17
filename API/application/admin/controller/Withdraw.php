<?php
namespace app\admin\controller;

use app\common\helpers\CommonLib;
use think\cache\driver\Redis;
use think\Controller;
use think\Exception;
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
        if(empty($data['order_id'])){
            return apiReturn(['code'=>1002,'msg'=>'The system has failed. Please refresh and try again.']);
        }
        $getOrder = model("Withdraw")->getOrdermodel($data['order_id']);//获取对应订单时间
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
     * 添加旧系统提现申请
     *
     * */
    public function addOldWithdraw(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Withdraw.addWithdraw");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
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
            $data['bank_withdrawals'] = input("bank_withdrawals");
            $data['bank_card'] = input("bank_card");
            $data['bank_name'] = input("bank_name");
            $data['customer_type'] = input('customer_type',2);
            $data['add_time'] = time();
            $res = model("Withdraw")->saveWithdraw($data);
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
            // if(!empty($data['CustomerID'])){
            //     $where['cic_id'] = $data['CustomerID'];
            // }
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
                $where['settlement_status'] = $data['settlement_status'];
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
               return ['code'=>1002];
            }
         }else{
            return ['code'=>1002,'msg'=>'Data transmission error.'];
         }
    }
    /**
     * 获取affiliate_id是否为黑名单状态
     * [BlacklistVerification description]
     * @author Wang
     * @date 2019-01-25
     */
    public function BlacklistVerification(){
         if($data = request()->post()){
              $where = [];
              if(!empty($data["Affiliate_id"])){
                  $where['affiliate_id'] = $data["Affiliate_id"];
              }
              if(empty($where)){
                  return ['code'=>1002,'msg'=>'An error occurred while sending data. Please try again later.'];
              }
              $BlacklistVerification = model("Withdraw")->BlacklistVerification($where);//提交数据
              return $BlacklistVerification;
         }
    }

    /*
     * 获取旧系统提现数量
     * */
    public function getOldWithdrawCount(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Withdraw.getOldWithdrawCount");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where['customer_id'] = $paramData['customer_id'];
            if(isset($paramData["add_time"])){
                $where['add_time'] = ['egt',$paramData['add_time']];
            }
            $res = model("Withdraw")->getOldWithdrawCount($where);//提交数据
            if($res !== '' && $res !== false){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }


    /**
     * 获取虚拟币提现记录列表
     * @return mixed
     */
    public function getBlockChainWithdrawList(){
        $paramData = request()->post();
        $paramData = array_filter($paramData);
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }else{
            apiReturn(['code'=>1001]);
        }
        /*提现申请单号*/
        if(isset($paramData['order_number'])){
            $where['withdraw_number'] = $paramData['order_number'];
        }
        /*提现状态*/
        if(isset($paramData['withdraw_status'])){
            $where['status'] = $paramData['withdraw_status'];
        }
        if(isset($paramData['start_time']) && isset($paramData['end_time'])){
            $where['add_time'] = ["between",[strtotime($paramData['start_time']),strtotime($paramData['end_time'])]];
        }else{
            /*申请开始时间*/
            if(isset($paramData['start_time'])){
                $where['add_time'] = ['gt',strtotime($paramData['start_time'])];
            }
            /*申请结束时间*/
            if(isset($paramData['end_time'])){
                $where['add_time'] = ['lt',strtotime($paramData['end_time'])];
            }
        }
        $page_size = input("post.page_size",20);
        $page = input("post.page",1);
        $path = input("post.path");
        $order = isset($paramData['order']) ? $paramData['order'] : "id desc";
        $page_query = isset($paramData['page_query']) ? $paramData['page_query'] : '';
        $list = model("Withdraw")->getBlockChainWithdrawList($where,$page_size,$page,$path,$order,$page_query);
        return apiReturn(['code'=>200,'data'=>$list]);
    }


    /**
     * 提现申请
     * @return mixed
     */
    public function addBlockChainWithdraw(){
        try{
            $rate = 0;
            $paramData = request()->post();
            $validate = $this->validate($paramData,"Withdraw.addBlockChainWithdraw");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            //BTC最低提现余额：0.0002（最低手续费0.0001+最低提现额度0.0001）
            if($paramData['virtual_currency_num'] < 0.0002){
                return apiReturn(['code'=>100002,"msg"=>'Minimum withdrawal 0.0002,Handling fee 0.0001!']);
            }
            //提现单号不能重复
            $findData = doCurl(CIC_API.'cic/blockChainTransaction/getTransaction',
                ['customer_id' => $paramData['customer_id'],'transaction_id' => $paramData['transaction_id']],null,true);
            //汇率
            $tickers = doCurl(MALL_API.'share/currency/getHuoBiTickers',null,null,true);
            if(!empty($tickers['data']['data'])){
                $rate = CommonLib::filterArrayByKey($tickers['data']['data'],'symbol','btcusdt');
                if(!empty($rate['close'])){
                    $rate = $rate['close'];
                }
            }
            if(empty($rate)){
                return apiReturn(['code'=>100003,"msg"=>'get Tickers error']);
            }

            if(!empty($findData['code']) && $findData['code'] == 200){
                $data = $findData['data'];
                //新增数据
                $insert['customer_id'] = $paramData['customer_id'];
                $insert['block_chain_transaction_id'] = $paramData['transaction_id'];
                $insert['withdraw_number'] = CommonLib::createOrderNumner().$paramData['transaction_id'];
                $insert['product_title'] = $data['product_name'];
                $insert['paypal_number'] = $paramData['paypal_number'];
                $insert['virtual_currency'] = $data['virtual_currency'];
                $insert['virtual_rate'] = $rate;
                $insert['withdraw_total_virtual_currency'] = $paramData['virtual_currency_num'];
                $insert['withdraw_virtual_currency'] = bcsub($paramData['virtual_currency_num'] , 0.0001,8);
                $insert['withdraw_amount'] = bcmul($insert['virtual_rate'] , $insert['withdraw_virtual_currency'],2);
                $insert['handling_fee'] = 0.0001;//手续费
                $insert['status'] = 0;//初始化数据
                $insert['add_time'] = time();//申请时间

                $res = model("Withdraw")->addBlockChainWithdraw($insert);//提交数据
                if($res > 0){
                    //扣除余额
                    $apiRes = doCurl(CIC_API.'cic/blockChainTransaction/operatorTransaction',
                        ['customer_id' => $paramData['customer_id'],'transaction_id' => $paramData['transaction_id'],'operator' => 1,'amount' => $paramData['virtual_currency_num']],
                        null,true);
                    //接口成功
                    if(!empty($apiRes['code']) && $apiRes['code'] == 200){
                        model("Withdraw")->updateBlockChainWithdraw(['id'=>$res],['status' => 1]);//待审核
                    }
                    return apiReturn(['code'=>200,'data'=>$res]);
                }else{
                    return apiReturn(['code'=>1002]);
                }
            }else{
                return apiReturn(['code'=>100003,"msg"=>'Cash withdrawal failure!']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }
}
