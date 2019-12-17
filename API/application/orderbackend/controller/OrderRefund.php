<?php
namespace app\orderbackend\controller;

use app\admin\model\Reports;
use app\common\params\orderbackend\OrderParams;
use app\demo\controller\Auth;
use app\orderbackend\model\OrderModel;
use app\orderbackend\model\OrderRefundModel;
use app\common\services\CommonService;
use think\Log;
use app\admin\dxcommon\BaseApi;
use think\Monlog;

/**
 * 订单类
 * Class OrderRefund
 * @author tinghu.liu 2018/5/16
 * @package app\orderbackend\controller
 */
class OrderRefund extends Auth
{
    /**
     * 获取订单退款列表数据（含分页）
     * @return mixed
     */
    public function getLists(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->orderRefunGetListsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $data = $model->getListDataForPage($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 获取订单退款列表和订单表数据（含分页）
     * @return mixed
     */
    public function getOrderLists(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->orderRefunExcelGetListsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $data = $model->getOrderListDataForPage($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 获取RAM提交数据
     * @return mixed
     */
    public function getRamPostData(){
        try{
            $param = request()->post();
            //参数校验
            $validate = $this->validate($param,(new OrderParams())->getRamPostDataRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $model = new OrderRefundModel();
            $data = $model->getRamPostData($param['after_sale_id']);
            if (!empty($data)){
                return apiReturn(['code'=>200, 'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 更新订单退款退货换货数据
     * @return mixed
     */
    public function updateApplyData(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->updateApplyDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $after_sale_id = $param['after_sale_id'];
        unset($param['after_sale_id']);
        $res = $model->updateApplyDataByWhere(['after_sale_id'=>$after_sale_id], $param);
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 增加订单售后申请操作记录数据
     * @return mixed
     */
    public function addApplyLogData(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->addApplyLogDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $res = $model->addApplyLogData($param);
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 获取纠纷列表（含分页）
     * @return mixed
     */
    public function getComplaintLists(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getComplaintListsRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderRefundModel();
        $data = $model->getComplaintDataForPage($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 获取售后订单数量
     * add 20190415 kevin
     * @return mixed
     */
    public function getUserAfterSaleCount(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,"OrderRefund.getUserAfterSaleCount");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $model = new OrderRefundModel();
        $where['customer_id'] = !empty($param['customer_id'])?$param['customer_id']:'';
        $data = $model->getUserAfterSaleCount($where);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /*
    * 添加订单退款接口
    * add 20190416
    * */
    public function saveOrderRefund(){
        $paramData = request()->post();
        try{
            if(isset($paramData['refund_id'])){
                $data['refund_id'] = $paramData['refund_id'];
            }
            /*订单号*/
            if(isset($paramData['order_id'])){
                $data['order_id'] = $paramData['order_id'];
            }
            /*订单编号*/
            if(isset($paramData['order_number'])){
                $data['order_number'] = $paramData['order_number'];
            }
            if(isset($paramData['customer_id'])){
                $data['customer_id'] = $paramData['customer_id'];
            }
            if(isset($paramData['customer_name'])){
                $data['customer_name'] = $paramData['customer_name'];
            }
            if(isset($paramData['store_name'])){
                $data['store_name'] = $paramData['store_name'];
            }
            if(isset($paramData['store_id'])){
                $data['store_id'] = $paramData['store_id'];
            }
            /*if(isset($paramData['payment_txn_id'])){
                $data['payment_txn_id'] = $paramData['payment_txn_id'];
            }*/
            if(isset($paramData['type'])){
                $data['type'] = $paramData['type'];
            }
            if(isset($paramData['refunded_fee'])){
                $data['refunded_fee'] = $paramData['refunded_fee'];
            }
            if(isset($paramData['imgs'])){
                $data['imgs'] = json_encode($paramData['imgs']);
            }
            if(isset($paramData['remarks'])){
                $data['remarks'] = $paramData['remarks'];
            }
            if(isset($paramData['from'])){
                $data['from'] = $paramData['from'];
            }
            if(isset($paramData['reports_id'])){
                $data['reports_id'] = $paramData['reports_id'];
            }
            $data = array_filter($data);
            if(isset($paramData['status']) && isset($paramData['status'])){
                $data['status'] = $paramData['status'];
            }
            if(isset($paramData['captured_refunded_fee'])){
                $data['captured_refunded_fee'] = $paramData['captured_refunded_fee'];
            }
            if(isset($paramData['initiator'])){
                $data['initiator'] = $paramData['initiator'];
            }
            if(isset($paramData['item'])){
                $data['item'] = $paramData['item'];
            }
            if(isset($paramData['create_ip'])){
                $data['create_ip'] = $paramData['create_ip'];
            }
            if(isset($paramData['applicant_admin_id'])){
                $data['applicant_admin_id'] = $paramData['applicant_admin_id'];
            }
            if(isset($paramData['applicant_admin'])){
                $data['applicant_admin'] = $paramData['applicant_admin'];
            }
            if(isset($paramData['sku_refund'])){
                $data['sku_refund'] = $paramData['sku_refund'];
            }
            $model = new OrderRefundModel();
            $res = $model->saveOrderRefund($data);
            if($res>0){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                if ($res == -99){
                    //重复提交售后申请情况
                    return apiReturn(['code'=>1002,'msg'=>'请勿重复提交退款']);
                }elseif ($res == -98){
                    //有退款payment未返回成功订单
                    return apiReturn(['code'=>1002,'msg'=>'payment有未返回成功订单，请稍后再试']);
                }else{
                    return apiReturn(['code'=>1002]);
                }
            }
        }catch (\Exception $e){
            Log::write("error:".$e->getMessage());
            Log::write("paramData:".json_encode($paramData));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 判断退款是否有记录
     * [order_refund description]
     * @return [type] [description]
     */
    public function getOrderRefundInfo(){
        $param = request()->post();
        try{
            //参数校验
            $validate = $this->validate($param,"OrderRefund.getOrderRefundInfo");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $model = new OrderRefundModel();
            $where['order_number'] = $param['order_number'];
            $res = $model->getOrderRefundInfo($where);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("error:".$e->getMessage());
            Log::write("paramData:".json_encode($param));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取退款记录
     * [order_refund description]
     * @return [type] [description]
     */
    public function getOrderRefund(){
        $param = request()->post();
        try{
            //参数校验

            $validate = $this->validate($param,['refund_id'  => 'require']);
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $model = new OrderRefundModel();
            $where['refund_id'] = $param['refund_id'];
            $res = $model->getOrderRefundInfo($where);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("error:".$e->getMessage());
            Log::write("paramData:".json_encode($param));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 判断退款是否有记录
     * [order_refund description]
     * @return [type] [description]
     */
    public function getOrderRefundList(){
        $param = request()->post();
        try{
            //参数校验
            $validate = $this->validate($param,"OrderRefund.getOrderRefundList");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $model = new OrderRefundModel();
            $res = $model->getOrderRefundList($param);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("error:".$e->getMessage());
            Log::write("paramData:".json_encode($param));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 后台获取退款列表
     * [order_refund description]
     * @return [type] [description]
     */
    public function getAdminOrderRefundList(){
        $param = request()->post();
        try{
            //参数校验
            $validate = $this->validate($param,"OrderRefund.getAdminOrderRefundList");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where = array();
            $model = new OrderRefundModel();
            if(isset($param['order_number'])){
                $where['or.order_number'] = trim($param['order_number']);
            }
            if(isset($param['customer_name'])){
                $where['or.customer_name'] = $param['customer_name'];
            }
            if(isset($param['customer_id'])){
                $where['or.customer_id'] = trim($param['customer_id']);
            }
            if(isset($param['store_id'])){
                $where['or.store_id'] = trim($param['store_id']);
            }
            if(isset($param['status'])){
                $where['or.status'] = trim($param['status']);
            }
            if(isset($param['add_time'])){
                $where['or.add_time'] = $param['add_time'];
                if(is_array($param['add_time'])){
                    $where['or.add_time'][0] = trim($param['add_time'][0]);
                }
            }
            $page_size = input("post.page_size",20);
            $page = input("post.page",1);
            $path = input("post.path");
            $order = isset($paramData['order'])?$param['order']:"refund_id desc";
            $page_query = isset($param['page_query'])?$param['page_query']:'';
            $res = $model->getAdminOrderRefundList($where,$page_size,$page,$path,$order,$page_query);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            Log::write("error:".$e->getMessage());
            Log::write("paramData:".json_encode($param));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 订单退款
     */
    public function refundOrder(){
        $paramData = request()->post();
        try{
            $validate = $this->validate($paramData,(new OrderParams())->refundOrderRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
			//根据订单ID获取订单信息
            $order_id = $paramData['order_id'];
            $model = new OrderModel();
            $order_info = $model->getOrderByInfoWhere(['order_id'=>$order_id]);
            if(empty($order_info['order_number'])){
                return apiReturn(['code'=>1002, 'msg'=>'order_info无法获取']);
            }
            //获取交易ID
            //增加支付系统判断【1-旧系统（.net）;2-新系统（php）】 tinghu.liu 20191115
            if(strtolower($order_info['pay_channel'])=='paypal' && $order_info['payment_system'] == 2){
                $transactionWherer['order_number']=$order_info['order_number'];
                $transactionWherer['txn_type'] = 'Capture';
                $transactionWherer['txn_result']='Success';//交易结果
            }else{
                $transactionWherer['order_number']=$order_info['order_number'];
                $transactionWherer['txn_type'] = ['in','Purchase,Capture'];
                $transactionWherer['txn_result']='Success';//交易结果
            }
            $transaction_id = $model->getTransactionID($transactionWherer);
            if(empty($transaction_id)){
                Log::record('refundOrder_params:'.json_encode($paramData).',res-$transaction_id:'.$transaction_id.',[transaction_id无法获取]');
                return apiReturn(['code'=>1002, 'msg'=>'transaction_id无法获取']);
            }
            //退款金额
            //$refund_grand_total = $order_info['captured_amount'];
            //后台退款
            $refund_id = $paramData['refund_id'];
            $refund_data = (new OrderRefundModel())->getOrderRefundInfo(['refund_id'=>$refund_id]);
            if(empty($refund_data['refund_id'])){
                return apiReturn(['code'=>1002, 'msg'=>'refund_data无法获取']);
            }
            if(isset($order_info['refunding_amount']) && $order_info['refunding_amount']>0 && isset($refund_data['status']) && $refund_data['status'] == 1){
                return apiReturn(['code'=>1002, 'msg'=>'payment有未返回成功订单，请稍后再试']);
            }
            //退款来源：1-正常订单退款，2-关税陪保退款 tinghu.liu 20191112
            $_from = isset($refund_data['from'])?$refund_data['from']:1;
            $_reports_id = isset($refund_data['reports_id'])?$refund_data['reports_id']:-1;
            //支付系统，1旧版，2新版
            $payment_system = $order_info['payment_system'];
            $refund_grand_total = $refund_data['captured_refunded_fee'];
            /*传入退款子单单号，不传的话payment回传不了信息 20190620 kevin*/
            $refund_order_number = [$order_info['order_number']];
            $service = new CommonService();
            $service_post_data = array(
                'DoRefund' => array(
                    'request' => array(
                        'CurrencyType' => 'Cash', //类型： Unknow (未知的) = 0,  Cash (现金) = 1, StoreCredit (虚拟货币) = 2,GiftCard (虚拟货币) = 3
                        'RefundAmount' => $refund_grand_total, //退款金额
                        'TransactionID' => $transaction_id, //$order_info['transaction_id'], //交易唯一ID
                        //'UniqueID' => $order_info['customer_id'], //用户ID
                        'UniqueID' => NULL, //用户ID
                        'ChildrenOrderNumber' => $refund_order_number, //子单单号，数组类型
                    )
                )
            );
            if($payment_system==2){//新版退款
                Log::record('refundOrderNew');
                $params['TransactionId']=$transaction_id;
                $params['RefundAmount']=$refund_grand_total;
                $params['Note']='refundOrderNew';
				$params['order_number']=$order_info['order_number'];
            	$order_json = $model->getOrderJson($order_id,$refund_grand_total);
	            if(empty($order_json)){
	                return apiReturn(['code'=>1002, 'msg'=>'order_json有未返回数据，请稍后再试']);
	            }
            	$params['json']=json_encode($order_json);
                Log::record('退款参数1：'.print_r(json_encode($params), true));
                $refund_res = $service->refund($params, $order_info['customer_id'], $order_info['order_master_number']);
                Log::record('退款结果9：'.print_r($refund_res, true));

                if (
                    !empty($refund_res['code'])
                    && ($refund_res['code']==200)
                ){
                    if ($refund_res['data']['status'] == 'failure'){
                        $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                        if(!$updata_refund_res){
                            Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                        }
                        Log::record('退款结果（操作异常3）-> request：'.$refund_res['data']['error_info']);
                        return apiReturn(['code'=>1003, 'msg'=>'退款操作失败 '.$refund_res['data']['error_info']]);
                    }
               }else{//接口返回失败
                   $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                   if(!$updata_refund_res){
                       Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                   }
                   Log::record('退款结果（操作异常2）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                   return apiReturn(['code'=>1004, 'msg'=>'退款操作失败，操作异常']);
               }
            }else{//旧版退款
                $refund_res = $service->payment('DoRefund', $service_post_data);
                Log::record('退款参数：'.print_r(json_encode($service_post_data), true));
                Log::record('退款结果：'.print_r($refund_res, true));

                Monlog::write(LOGS_MALL_CART.'_payment','info',__METHOD__,'refund',$service_post_data,'DoRefund',json_encode($refund_res), $order_info['customer_id'], $order_info['order_master_number'], $order_info['order_number']);

                if (
                    property_exists($refund_res, 'DoRefundResult')
                    && property_exists($refund_res->DoRefundResult, 'ResponseResult')
                ){
                    if ($refund_res->DoRefundResult->ResponseResult == 'Failure'){
                        $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                        if(!$updata_refund_res){
                            Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                        }
                        Log::record('退款结果（操作异常）-> request：'.$refund_res->DoRefundResult->Error->ShortMessage);
                        return apiReturn(['code'=>1003, 'msg'=>'退款操作失败 '.$refund_res->DoRefundResult->Error->ShortMessage]);
                    }else{
                        //如果是退款订单，将退款订单状态修改为“退款完成”,并且保存退款交易号到退款订单中 20190702 kevin
                        if(
                            property_exists($refund_res->DoRefundResult, 'ResponseTransactionInfo')
                            &&  property_exists($refund_res->DoRefundResult->ResponseTransactionInfo, 'TransactionID')
                        ){
                            $update_order_refund_data['payment_txn_id'] = $refund_res->DoRefundResult->ResponseTransactionInfo->TransactionID;
                        }else{
                            Log::write("TransactionID Is Empty!");
                        }
                    }
                }else{
                    $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                    if(!$updata_refund_res){
                        Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                    }
                    Log::record('退款结果（操作异常）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                    return apiReturn(['code'=>1004, 'msg'=>'退款操作失败，操作异常']);
                }
            }
            //退款成功处理
            $update_order_refund_data['status'] = 2;
            $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], $update_order_refund_data);
            //如果来至关税赔保的退款，成功后需要同步退款时间 tinghu.liu 20191112
            if ($_from == 2){
                (new Reports())->updateReports(['id'=>$_reports_id], ['refund_time'=>time()]);
            }
            if(!$updata_refund_res){
                Log::record('退款成功（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
            }
            if(($order_info['order_status'] < 500 || $order_info['order_status'] == 1700) && $refund_grand_total == $order_info['captured_amount']){
                $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['order_status'=>1900]);
                $OrderStatusData['order_id'] = $order_id;
                $OrderStatusData['order_status_from'] = $order_info['order_status'];
                $OrderStatusData['order_status'] = 1900;
                $OrderStatusData['change_reason'] = $paramData['change_reason'];
                $OrderStatusData['create_on'] = time();
                $OrderStatusData['create_by'] = $paramData['create_by'];
                $OrderStatusData['create_ip'] = $paramData['create_ip'];
                $OrderStatusData['chage_desc'] = isset($paramData['chage_desc'])?$paramData['chage_desc']:$paramData['change_reason'];
                $model->insertOrderStatusChange($OrderStatusData);
            }
            //修改退款中金额数据
            $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['refunding_amount'=>$refund_grand_total]);
            //记录操作日志
            $model->insertOrderRefundOperationData([
                'order_id'=>$order_id,
                'refund_id'=>$paramData['refund_id'],
                //退款来源:1-seller售后退款；2-my退款；3-admin退款
                'refund_from'=>$paramData['refund_from'],
                //退款类型：1-陪保退款；2-售后退款；3-订单取消退款;4-订单后台退款
                'refund_type'=>4,
                //操作人类型：1-admin，2-seller，3-my
                'operator_type'=>$paramData['operator_type'],
                //操作人ID
                'operator_id'=>$paramData['operator_id'],
                //操作人名称
                'operator_name'=>$paramData['operator_name'],
                'refund_amount'=>$refund_grand_total,
                'reason'=>isset($paramData['reason'])?$paramData['reason']:'',
                'add_time'=>time(),
            ]);
            if(!empty($refund_data['sku_refund'])){
                $model->update_sku(unserialize(htmlspecialchars_decode($refund_data['sku_refund'])));//OMS删除对应产品
            }
            return apiReturn(['code'=>200, 'msg'=>'退款成功']);
        }catch (\Exception $e){
            Log::record('退款结果（操作异常）-> request：'.$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>'请求异常：'.$e->getMessage()]);
        }
    }

    /**
     * 新版订单退款
     */
    public function refundOrderNew(){
        $paramData = request()->post();
        try{
            $validate = $this->validate($paramData,(new OrderParams())->refundOrderRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //根据订单ID获取订单信息
            $order_id = $paramData['order_id'];
            $model = new OrderModel();
            $order_info = $model->getOrderInfoByOrderId($order_id);
            if(empty($order_info['order_number'])){
                return apiReturn(['code'=>1002, 'msg'=>'order_info无法获取']);
            }
            //获取交易ID
            if(strtolower($order_info['pay_channel'])=='paypal'){
                $transactionWherer['order_number']=$order_info['order_number'];
                $transactionWherer['txn_type'] = 'Capture';
                $transactionWherer['txn_result']='Success';//交易结果
            }else{
                $transactionWherer['order_number']=$order_info['order_number'];
                $transactionWherer['txn_type'] = ['in','Purchase,Capture'];
                $transactionWherer['txn_result']='Success';//交易结果
            }
            $transaction_id = $model->getTransactionID($transactionWherer);
            if(empty($transaction_id)){
                return apiReturn(['code'=>1002, 'msg'=>'transaction_id无法获取']);
            }
            //退款金额
            //$refund_grand_total = $order_info['captured_amount'];
            //后台退款
            $refund_id = $paramData['refund_id'];
            $refund_data = (new OrderRefundModel())->getOrderRefundInfo(['refund_id'=>$refund_id]);
            if(empty($refund_data['refund_id'])){
                return apiReturn(['code'=>1002, 'msg'=>'refund_data无法获取']);
            }
            if(isset($order_info['refunding_amount']) && $order_info['refunding_amount']>0 && isset($refund_data['status']) && $refund_data['status'] == 1){
                return apiReturn(['code'=>1002, 'msg'=>'payment有未返回成功订单，请稍后再试']);
            }
            $refund_grand_total = $refund_data['captured_refunded_fee'];
            /*传入退款子单单号，不传的话payment回传不了信息 20190620 kevin*/
            $refund_order_number = [$order_info['order_number']];
            //进行退款操作
            $service_post_data = array(
                'DoRefund' => array(
                    'request' => array(
                        'CurrencyType' => 'Cash', //类型： Unknow (未知的) = 0,  Cash (现金) = 1, StoreCredit (虚拟货币) = 2,GiftCard (虚拟货币) = 3
                        'RefundAmount' => $refund_grand_total, //退款金额
                        'TransactionID' => $transaction_id, //$order_info['transaction_id'], //交易唯一ID
                        //'UniqueID' => $order_info['customer_id'], //用户ID
                        'UniqueID' => NULL, //用户ID
                        'ChildrenOrderNumber' => $refund_order_number, //子单单号，数组类型
                    )
                )
            );
            $service = new CommonService();
            $params['TransactionId']=$transaction_id;
            $params['RefundAmount']=$refund_grand_total;
            $params['Note']='refundOrderNew';
            $params['order_number']=$order_info['order_number'];
            $order_json = $model->getOrderJson($order_id,$refund_grand_total);
            Log::record('$order_json：'.json_encode($order_json));
            if(empty($order_json)){
                return apiReturn(['code'=>1002, 'msg'=>'order_json有未返回数据，请稍后再试']);
            }
            $params['json']=json_encode($order_json);
            Log::record('退款参数1：'.print_r(json_encode($params), true));
            $refund_res = $service->refund($params, $order_info['customer_id'], $order_info['order_master_number']);
            Log::record('退款结果9：'.print_r($refund_res, true));
            if (
                !empty($refund_res['code'])
                && ($refund_res['code']==200)
            ){
                if ($refund_res['data']['status'] != 'failure'){
                    //如果是退款订单，将退款订单状态修改为“退款完成”,并且保存退款交易号到退款订单中 20190702 kevin
                    if(
                        !empty($refund_res['data'])
                        &&    !empty($refund_res['data']['transaction_id'])
                    ){
                        $update_order_refund_data['payment_txn_id'] =$refund_res['data']['transaction_id'];
                    }else{
                        Log::write("TransactionID Is Empty!");
                    }
                    $update_order_refund_data['status'] = 2;
                    Log::record($paramData['refund_id'].'退款状态异常'.json_encode($update_order_refund_data));
                    $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], $update_order_refund_data);
                    if(!$updata_refund_res){
                        Log::record('退款成功（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                    }
                    if(($order_info['order_status'] < 500 || $order_info['order_status'] == 1700) && $refund_grand_total == $order_info['captured_amount']){
                        $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['order_status'=>1900]);
                        $OrderStatusData['order_id'] = $order_id;
                        $OrderStatusData['order_status_from'] = $order_info['order_status'];
                        $OrderStatusData['order_status'] = 1900;
                        $OrderStatusData['change_reason'] = $paramData['change_reason'];
                        $OrderStatusData['create_on'] = time();
                        $OrderStatusData['create_by'] = $paramData['create_by'];
                        $OrderStatusData['create_ip'] = $paramData['create_ip'];
                        $OrderStatusData['chage_desc'] = isset($paramData['chage_desc'])?$paramData['chage_desc']:$paramData['change_reason'];
                        $model->insertOrderStatusChange($OrderStatusData);
                    }
                    //修改退款中金额数据
                    $res9=$model->updateOrderInfoByWhere(['order_id'=>$order_id], ['refunding_amount'=>$refund_grand_total]);
                    Log::record($res9.'修改退款金额.$order_id：'.$order_id.json_encode(['refunding_amount'=>$refund_grand_total]));
                    //记录操作日志
                    $RefundOperationData=[
                        'order_id'=>$order_id,
                        'refund_id'=>$paramData['refund_id'],
                        //退款来源:1-seller售后退款；2-my退款；3-admin退款
                        'refund_from'=>$paramData['refund_from'],
                        //退款类型：1-陪保退款；2-售后退款；3-订单取消退款;4-订单后台退款
                        'refund_type'=>4,
                        //操作人类型：1-admin，2-seller，3-my
                        'operator_type'=>$paramData['operator_type'],
                        //操作人ID
                        'operator_id'=>$paramData['operator_id'],
                        //操作人名称
                        'operator_name'=>$paramData['operator_name'],
                        'refund_amount'=>$refund_grand_total,
                        'reason'=>isset($paramData['reason'])?$paramData['reason']:'',
                        'add_time'=>time(),
                    ];
                    Log::record('记录操作日志'.$order_id.json_encode($RefundOperationData));
                    $model->insertOrderRefundOperationData($RefundOperationData);
                    return apiReturn(['code'=>200, 'msg'=>'退款成功']);
                }else{
                    $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                    if(!$updata_refund_res){
                        Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                    }
                    Log::record('退款结果（操作异常3）-> request：'.$refund_res['data']['error_info']);
                    return apiReturn(['code'=>1003, 'msg'=>'退款操作失败 '.$refund_res['data']['error_info']]);
                }
            }else{
                $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                if(!$updata_refund_res){
                    Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                }
                Log::record('退款结果（操作异常2）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                $msg=!empty($refund_res['msg'])?$refund_res['msg']:'退款操作失败，操作异常';
                return apiReturn(['code'=>1004, 'msg'=>$msg]);
            }
        }catch (\Exception $e){
            Log::record('退款结果（操作异常1）-> request：'.$e->getMessage().$e->getLine().$e->getFile());
            return apiReturn(['code'=>1002, 'msg'=>'请求异常：'.$e->getMessage()]);
        }
    }

    /**
     * 新增退款产品详情
     * [order_after_sale_apply_item description]
     * @return [type] [description]
     */
    public function save_order_refund_item(){
        if($data = request()->post()){
            $res = '';
            if(!empty($data["param_sku"])){
                foreach ($data['param_sku'] as $k => $v) {
                    $where = [];
                    if(!empty($v['refund_id'])){
                        $where['refund_id'] = $v['refund_id'];
                    }
                    if(!empty($v['product_id'])){
                        $where['product_id'] = $v['product_id'];
                    }
                    if(!empty($v['sku_id'])){
                        $where['sku_id'] = $v['sku_id'];
                    }
                    if(!empty($v['sku_num'])){
                        $where['sku_num'] = $v['sku_num'];
                    }
                    if(!empty($v['product_name'])){
                        $where['product_name'] = $v['product_name'];
                    }
                    if(!empty($v['product_img'])){
                        $where['product_img'] = $v['product_img'];
                    }
                    if(!empty($v['product_nums'])){
                        $where['product_nums'] = $v['product_nums'];
                    }
                    if(!empty($v['product_price'])){
                        $where['product_price'] = $v['product_price'];
                    }
                    if(!empty($where)){
                        $res_1 = (new OrderRefundModel())->save_order_refund_item($where);
                        // return apiReturn(['code'=>100212,'msg'=>$res_1]);
                        if(empty($res_1)){
                            Log::record('退款添加sku详情失败Error：'.json_encode($where));
                            $res = apiReturn(['code'=>1002,'msg'=>'新增详情失败']);
                        }else{
                            $res = apiReturn(['code'=>200,'msg'=>'新增详情成功']);
                        }

                    }else{
                        $res = apiReturn(['code'=>1002,'msg'=>'传参出错']);
                    }
                }

            }else{
                $res = apiReturn(['code'=>1002,'msg'=>'传参出错']);
            }
            return $res;
        }
    }


    /**
     * 获取售后订单数据
     * @return mixed
     */
    public function getAdminOrderRefundInfo(){
        $paramData = request()->post();
        if(isset($paramData['order_number'])){
            $where['so.order_number'] = $paramData['order_number'];
        }elseif(isset($paramData['refund_number'])){
            $where['refund_number'] = $paramData['refund_number'];
        }else{
            return apiReturn(["code"=>1001]);
        }
        if(isset($paramData['status'])){
            $where['or.status'] = $paramData['status'];
        }
        $res = (new OrderRefundModel())->getAdminOrderRefundInfo($where);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 测试
     */
    public function test(){
        $TransactionById['transaction_id']=50000026;
        $tDatass = (new BaseApi)->getTransactionById($TransactionById);
        log::record('$tDatass'.json_encode($tDatass));
        var_dump($tDatass);
        return  $tDatass;
    }

    /*
     * 获取退款信息
     * add by 20191022 kevin
     * */
    public function getOrderRefundSummary(){
        $paramData = request()->post();
        try{
            $validate = $this->validate($paramData,(new OrderParams())->getOrderRefundSummaryRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $res = (new OrderRefundModel())->getOrderRefundSummary($paramData);
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (\Exception $e){
            Log::record('获取退款信息失败：'.$e->getMessage().$e->getLine().$e->getFile());
            return apiReturn(['code'=>1002, 'msg'=>'请求异常：'.$e->getMessage()]);
        }
    }

    /*
     * 更改退款状态
     * add by kevin 20191031
     * */
    public function updateOrderRefund(){
        $paramData = request()->post();
        try{
            $validate = $this->validate($paramData,(new OrderParams())->updateOrderRefundRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $res = (new OrderRefundModel())->updateOrderRefund($paramData);
            return apiReturn(['code'=>200,'data'=>$res]);
        }catch (\Exception $e){
            Log::record('获取退款修改信息失败：'.$e->getMessage().$e->getLine().$e->getFile());
            return apiReturn(['code'=>1002, 'msg'=>'请求异常：'.$e->getMessage()]);
        }
    }

    public function test1()
    {
        $model = new OrderModel();
        $transactionWherer['order_number'] = '191111100101101056';
        $transactionWherer['txn_type'] = ['in','Purchase,Capture'];
        $transactionWherer['txn_result'] = 'Success';//交易结果
        $transaction_id = $model->getTransactionID($transactionWherer);
        var_dump($transaction_id);
    }
}
