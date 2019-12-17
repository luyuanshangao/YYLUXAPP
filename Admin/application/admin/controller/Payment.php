<?php
namespace app\admin\controller;

use app\admin\dxcommon\ExcelTool;
use think\View;
use think\Controller;
use app\admin\dxcommon\BaseApi;
use think\Log;
use think\Request;

/**
 * payment管理
 * Add by:Hai.Ouyang
 * AddTime:2019-06-04
 */
class Payment extends Action{

    public function __construct(){
        Action::__construct();
    }

    /**
     * payment 单笔交易查询
     */
    public function singleTransaction(Request $request){
        $param_data = input();
        $transactionId = input('transactionId');
        $where = array();
        //交易号
        if(!empty($transactionId) && is_numeric($transactionId)){
            $where['transaction_id'] = $transactionId;
        }
        //订单号
        if(!empty($param_data['order_number']) && is_numeric($param_data['order_number'])){
            $where['order_master_number'] = $param_data['order_number'];
        }
        //TXNID
        /*if(!empty($param_data['order_number']) && is_numeric($param_data['order_number'])){
            $where['order_master_number'] = $param_data['order_number'];
        }*/
        //第三方交易ID
        if(!empty($param_data['invoice_id']) && is_numeric($param_data['invoice_id'])){
            $where['invoice_id'] = $param_data['invoice_id'];
        }
        if(empty($where)){
            return View();
        }
        $this->assign(['hasQuery'=>true]);
        $transactionData = model('Payment')->getTransactionById($where);
        if( empty($transactionData) ){
            return View();
        } 
        $canRefund = false;
        if( $transactionData['transaction_channel']=='paypal' && $transactionData['transaction_action']=='Capture' && $transactionData['response_status']==1 ){
            $canRefund = true;
        }else if( $transactionData['transaction_channel']!='paypal' && $transactionData['transaction_action']=='Purchase' && $transactionData['response_status']==1 ){
            $canRefund = true;
        }
        $this->assign([
            'transactionData' => $transactionData,
            'transactionId'   => $transactionData['transaction_id'],
            'canRefund'       => $canRefund,
        ]);
        return View();
    }

    /**
     * payment 订单交易查询
     */
    public function multiTransaction(){
        $param_data = input();
        $transactionId = input('transactionId');
        $where = array();
        //交易号
        if(!empty($transactionId) && is_numeric($transactionId)){
            $where['transaction_id'] = $transactionId;
        }
        //订单号
        if(!empty($param_data['order_number']) && is_numeric($param_data['order_number'])){
            $where['order_master_number'] = ["in",$param_data['order_number']];
        }
        //TXNID
        /*if(!empty($param_data['order_number']) && is_numeric($param_data['order_number'])){
            $where['order_master_number'] = $param_data['order_number'];
        }*/
        //第三方交易ID
        if(!empty($param_data['invoice_id'])){
            $where['invoice_id'] = $param_data['invoice_id'];
        }
        if(empty($where)){
            return View();
        }
        //dump($where);exit;
        $this->assign(['hasQuery'=>true]);
        $transactionData = model('Payment')->getTransactionByOrderNumber($where);
        if(empty($transactionData) ){
            $this->assign(['hasQuery'=>true]);
            return View();
        }
        $this->assign([
            'transactionData' => $transactionData,
        ]);

        return View();
    }

    /**
     * payment-第三方 交易对账查询
     */
    public function singleContrast(){
        $transactionId = input('transactionId');

        if( empty($transactionId) || !is_numeric($transactionId) ){
            return View();
        }
        
        $this->assign(['hasQuery'=>true]);
        $transactionData = model('Payment')->getTransactionById(['transaction_id'=>$transactionId]);
        if( empty($transactionData) ){
            return View();
        } 

        $query_data = array(
            'transaction_id'    => $transactionData['transaction_id'],
            'transaction_channel'=> $transactionData['transaction_channel'],
            'transaction_action'=> $transactionData['transaction_action'],
            'invoice_id'        => $transactionData['invoice_id'],
            'capture_id'        => $transactionData['response_capture_id'],
            'order_master_number' => $transactionData['order_master_number'],
            'ThirdpartyMethod'  => $transactionData['transaction_account_id'],
            'CurrencyCode'  => $transactionData['currency_code'],
        );

        $result = BaseApi::getThirdpartyData($query_data);
        
        if( empty($result['code']) || $result['code']!='200' ){
            return View();
            
        }

        $this->assign([
            'transactionData' => $transactionData,
            'thirdpartyData'  => $result['data'],
        ]);

        return View();
    }

    /**
     *
     */
    public function refund(){
        $params = request()->post();

        if( empty($params['TransactionId'])  || empty($params['RefundAmount']) || empty($params['Note']) ){
            echo json_encode(['code'=>400,'msg'=>'params error']);exit;
        }

        $transactionData = model('Payment')->getTransactionById(['transaction_id'=>$params['TransactionId']]);
        if( empty($transactionData) || $transactionData['response_status']!='1' ){
            echo json_encode(['code'=>400,'msg'=>'transaction data error']);exit;   
        }

        //获取用户邮箱
        $customer_data = BaseApi::getCustomerByID($transactionData['customer_id']);
        if( empty($customer_data['data']['email']) ){
            echo json_encode(['code'=>400,'msg'=>'get email failed']);exit;   
        }

        if( $params['RefundAmount']!=$transactionData['amount'] ){
            echo json_encode(['code'=>400,'msg'=>'目前只支持全额退款']);exit;   
        }

        $email = $customer_data['data']['email'];

        $data = array(
            'RefundAmount'        => $params['RefundAmount'],
            'Note'                => $params['Note'],
            'TransactionSouce'    => $transactionData['transaction_souce'],
            'CurrencyType'        => $transactionData['currency_type'],
            'TransactionId'       => $transactionData['transaction_id'],
            'CustomerIp'          => $transactionData['customer_ip'],
            'ChildrenOrderNumber' => $transactionData['order_number_list'],
            'CustomerEmail'       => $email,
            'ChildOrderPrice'     => $transactionData['child_order_price'],
        );

        $result = BaseApi::paymentRefund($data);
        
        if( empty($result['code']) ){
            Log::record("payment interface error【transactionId-{$params['TransactionId']}】");
            echo json_encode(['code'=>400,'msg'=>'payment interface error']);exit;
        }else if( $result['code']!='200' || empty($result['data']) || $result['data']['status']=='failure' ){
            Log::record("refund failed【transactionId-{$params['TransactionId']}】result:".print_r($result,1));
            echo json_encode(['code'=>400,'msg'=>'refund failed']);exit;
        }

        echo json_encode(['code'=>200,'msg'=>'success']);
    }

    /**
     * 根据第三方实时数据更新payment数据库。增加notification队列就行
     */
    public function updateTransaction(){
        $params = request()->post();

        if( empty($params['TradeStatus']) || empty($params['TradeStatusSummary']) || empty($params['TransactionId']) || !isset($params['Amount']) || !isset($params['CurrencyCode']) || empty($params['InvoiceId']) ){
            echo json_encode(['code'=>400,'msg'=>'params error']);exit;
        }
        /*
        $md5 = md5( json_encode($params) );
        $notificationExist = model('Payment')->getNotification($md5);
        if(!empty($notificationExist)){
            echo json_encode(['code'=>400,'msg'=>'Add update task success,please check the results later']);exit;
        }
        */
        $transactionData = model('Payment')->getTransactionById(['transaction_id'=>$params['TransactionId']]);
        if( empty($transactionData) ){
            echo json_encode(['code'=>400,'msg'=>'transaction data is null']);exit;   
        }

        if( $params['TradeStatus']==$transactionData['response_status'] || $params['TradeStatusSummary']==$transactionData['response_summary']){
            echo json_encode(['code'=>400,'msg'=>'payment data is the same as thirdparty data']);exit;   
        }

        $params['TransactionChannel'] = $transactionData['transaction_channel'];
        $params['TransactionAction'] = $transactionData['transaction_action'];

        $result = BaseApi::updatePaymentStatus($params);

        if( !isset($result['code']) ){
            echo json_encode(['code'=>400,'msg'=>'update failed']);exit;   
        }else if( $result['code']!='200' ){
            echo json_encode(['code'=>400,'msg'=>$result['msg']]);exit;   
        }
        /*
        $params['TransactionChannel'] = $transactionData['transaction_channel'];
        $params['md5'] = $md5;

        $res = model('Payment')->addNotification($params);
        
        if( $res<=0 ){
            echo json_encode(['code'=>400,'msg'=>'add update task failed']);exit;   
        }
        */
        echo json_encode(['code'=>200,'msg'=>'update status success']);exit;   
    }
}