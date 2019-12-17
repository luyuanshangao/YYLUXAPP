<?php
namespace app\orderfrontend\controller;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\OrderLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\orderfrontend\SynOmsParams;
use app\demo\controller\Auth;
use app\orderfrontend\model\OrderModel;
use think\Log;
use think\Request;
use think\Monlog;

/**
 * 订单与OMS、Payment等交互接口类【与OMS、Payment等交互专用类】
 * @author tinghu.liu 2018/06/14
 * @package app\orderfrontend\controller
 */
class SynOrder extends Auth
{
    //区块链订单中的机器号标识
    const MACHINE_ID_FOR_BLOCK_CHAIN = 2001;
    public $base_api;
    public $order_model;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->base_api = new BaseApi();
        $this->order_model = new OrderModel();
    }

    /**
     * 同步OMS订单状态至Order
     * @return mixed
     */
    public function status(){
        $function_name = 'status';
        try{
            /**
             * <Notification>
             *    <ChangeID>474369</ChangeID>
             *    <OrderNumber>180626001042388898</OrderNumber>
             *    <Status>3</Status>
             *    <ChangeOn>2018-06-26T10:19:37.7377262</ChangeOn>
             *    <ChangeBy>Payment IPN</ChangeBy>
             *    <Notes></Notes>
             * </Notification>
             */
            $redis_cluster = new RedisClusterBase();
            //$post_data = htmlspecialchars_decode(file_get_contents("php://input"));
            Log::record('同步OMS订单至Order，接收的数据-php://input：'.print_r(file_get_contents("php://input"), true),'notice');
            //$data = simplexml_load_string($post_data);
            //$data_json = json_encode($data);
            //记录收通知时间 tinghu.liu 20190613
            $redis_cluster->set('omsStatusNotificationTime', time());

            //$data_json = htmlspecialchars_decode(file_get_contents("php://input"));
            $params = json_decode(file_get_contents("php://input"),true);
            Log::record('同步OMS订单至Order，接收的数据array：'.print_r($params, true),'notice');

            $order_number = isset($params['OrderNumber'])?$params['OrderNumber']:'';
            $function_name .= $order_number;
            if (!empty($params)){
                Monlog::write(LOGS_ORDER_STATUS_DETAILS,'info',__METHOD__,$function_name,$params,null,null,0,'',$order_number);
            }

            /** 参数校验 **/
            $validate = $this->validate($params,(new SynOmsParams())->orderStatusRules());
            if(true !== $validate){
                //return apiReturn(['code'=>2001, 'msg'=>$validate]);
                Log::record('同步OMS订单至Order，validate：'.$validate,'notice');
                echo '<Response>0</Response>';exit();
            }
            /** OMS订单状态和Order订单状态映射 **/
            $order_status = OrderLib::mappingOrderStatusByOMSStatus($params['Status']);
            \think\Log::pathlog('APIRequest',$order_status,'mall_order_success_change_order_status');
            if ($order_status != -1){

                $order_status_res = $this->order_model->getOrderInfoByOrderNumber($params['OrderNumber'], 'order_status,order_branch_status');
                if(!empty($order_status_res)){

                    //订单状态已是200，接收OMS状态若是70需处理为付款确认中
                    //从订单状态处理表中判断最新一条是否订单状态200  added by wangyj in 20190423
                    $payment_processing_flag = false;
                    if($order_status==120){

                        $last_order_status_process = $this->order_model->getLastOrderStatusProcess($params['OrderNumber'], 'order_status');
                        if(!empty($last_order_status_process) && $last_order_status_process['order_status']==200){

                            $payment_processing_flag = true;
                            Log::record('(PaymentProcessing)同步OMS订单至Order，order_status:'.$order_status_res['order_status'].' oms order_status:'.$order_status,'notice');
                        }

                    }

                    if(
                        $order_status_res['order_branch_status'] != 105
                        && $order_status_res['order_status'] >= $order_status
                        && !$payment_processing_flag
                        //当订单状态为纠纷或者chargeback时，需要修改状态 tinghu.liu 20190520
                        && !in_array($order_status_res['order_status'], [1700, 2000])
                    ){

                        Log::record('同步OMS订单至Order，order_status:'.$order_status_res['order_status'].' oms order_status:'.$order_status,'notice');
                        echo '<Response>1</Response>';exit();
                    }
                }else{

                    Log::record('同步OMS订单至Order，order_info null','error');
                    echo '<Response>1</Response>';exit();
                }
                //如果订单状态为检货中、检货完成（400,407），在接收到订单取消通知时，记录错误日志，发送邮件通知【暂时不发】 tinghu.liu 20190419
                if (
                    in_array($order_status_res['order_status'], [400,407])
                    && $order_status == 1400
                ){
                    $notice_msg = '同步OMS订单至Order，检货中的订单不允许取消,order_status_res status:'.$order_status_res['order_status'].', params:'.json_encode($params);
                    //错误日志
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'SynOrderStatus'.$params['OrderNumber'],$params,null,$notice_msg);
                    Log::record($notice_msg,'error');
                    \think\Log::pathlog('APIRequest',$notice_msg,'SynOrderStatus400_407_1400');

                    //这种情况不做订单状态的处理，记录信息 恒总定 tinghu.liu 20190525
                    $this->order_model->synOrderStatusExceptionHandle($params, $order_status_res['order_status'], $order_status);
                    echo '<Response>1</Response>';exit();
                }
                $create_on = isset($params['ChangeOn'])?strtotime($params['ChangeOn']):time();
                $create_by = isset($params['ChangeBy'])?$params['ChangeBy']:'orderfrontendAPI';
                $queue_data = json_encode(
                    [
                        'order_number'=>$params['OrderNumber'],
                        'order_status'=>$order_status,
                        /*'create_on'=>strtotime($params['ChangeOn']),
                        'create_by'=>$params['ChangeBy']*/
                        'create_on'=>$create_on,
                        'create_by'=>$create_by
                    ]
                );
                //写入队列，之后返回处理成功
                $res = $redis_cluster->lPush(
                    'mall_order_success_change_order_status',
                    $queue_data
                );
                \think\Log::pathlog('APIRequest','$redis_cluster:'.$res,'mall_order_success_change_order_status');
                if ($res){
                    //return apiReturn(['code'=>200, 'msg'=>'Success']);
                    echo '<Response>1</Response>';exit();
                }else{
                    //eturn apiReturn(['code'=>2002, 'msg'=>'Failure']);
                    echo '<Response>0</Response>';exit();
                }
                Log::record('同步OMS订单至Order，data-:'.$queue_data.', $res：'.$res,'notice');
                /**
                 * 更改订单状态
                 */
                /*$model = new OrderModel();
                $where = [
                    'order_number'=>$params['order_number']
                ];
                $up_data = [
                    'order_status'=>$order_status,
                ];
                //【消费队列处理】是否根据状态判断同步更新：完成时间、修改者、修改时间、支付时间、发货时间、发货完成时间
                if ($model->updateOrderByWhere($where, $up_data)){
                    return apiReturn(['code'=>200, 'msg'=>'Success']);
                }else{
                    return apiReturn(['code'=>2002, 'msg'=>'Failure']);
                }*/
            }else{
                Log::record('同步OMS订单至Order，状态更新无效。','notice');
                //return apiReturn(['code'=>200, 'msg'=>'Success']);
                echo '<Response>1</Response>';exit();
            }
        }catch (\Exception $e){
            //return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
            Log::record('同步OMS订单至Order，异常：'.$e->getMessage(),'notice');
            echo '<Response>0</Response>';exit();
        }
    }

    /**
     * 同步OMS订单状态至Order【新payment系统专用】
     * http://api.localhost.com/orderfrontend/SynOrder/statusV2
     * 新payment状态汇总：
     *
     *  101 - 事前风控不通过【不处理】
        102 - 事前风控通过【不处理】
        105 - 进入事后风控审核
        106 - 事后风控不通过【即支付失败】
        107 - 事后风控通过【即支付成功】
        100 - 等待付款
        120 - 付款处理中
        121 - 付款失败
        200 - 付款完成（成功）
        1400 - 用户取消订单
        1600 - 索赔（CLAIM）
     *
        1700 - 用户在其他平台发起纠纷（DISPUTE）
        1701 - 纠纷处理中（DISPUTE_PROCESSING）
        1702 - 纠纷已解决，没有给用户退款（ORDERSTATUS_PAYMENT_DISPUTE_RESOLVED）最终状态。如果订单状态为1700，需要将订单状态回滚至上一个状态
        1710 - 因纠纷，交易取消，第三方给用户退款（ORDERSTATUS_PAYMENT_DISPUTE_REVERSED）最终状态。
     *
        2000 - 用户在其他平台已申请退款成功（CHARGEBACK）
        2001 - 退款中（CHARGEBACK_PROCESS）
        2002 - 不予退款（CHARGEBACK_REFUSED）
        2003 - 收款金额大于实际金额（仅boleto）
        2004 - 收款金额小于实际金额（仅boleto）
        3000 - 未知状态
     */
    public function statusV2(){
        $function_name = 'statusV2';
        $RESULT_SUCCESS = 'success';
        $RESULT_FAILURE = 'failure';
        $time = time();
        try{
            //TODO 安全性校验（签名或者用户名密码形式）。。。。。。。。。

            //TODO 推送的状态和订单状态对应。。。。
            //TODO 订单状态要和payment那边进行校验？？？？？？？？为了数据安全和一致性。。。。【可以增加IP白名单限制】
            /**
             * {
             *
             * "order_number":"",
             * "order_status":"",
             * "change_on":"",
             * "change_by":"",
             * "reason":"",
             * }
             *
             */
            $params = request()->post();
            Log::record('statusV2_同步OMS订单至Order，接收的数据array：'.json_encode($params), 'notice');
            //增加状态监控 tinghu.liu 20191011
            $redis_cluster = new RedisClusterBase();
            $redis_cluster->set('omsStatusNotificationTime', $time);

            $order_number = isset($params['order_number'])?$params['order_number']:'';
            $function_name .= $order_number;
            if (!empty($params)){
                Monlog::write(LOGS_ORDER_STATUS_DETAILS,'info',__METHOD__,$function_name,$params,null,null,0,'',$order_number);
            }
            /** 参数校验 **/
            $validate = $this->validate($params,(new SynOmsParams())->orderStatusForV2Rules());
            if(true !== $validate){
                Log::record('同步OMS订单至Order，validate：'.$validate,'notice');
                return apiReturn(['code'=>2001, 'msg'=>$validate, 'bug_code'=>501]);
            }

            /** 订单状态处理 start **/
            $order_status = $params['order_status'];
            //101 - 事前风控不通过 、102 - 事前风控通过
            //TODO 放开1702限制，“纠纷已解决，第三方没有给用户退款”，如果当前订单状态为1700，需要将订单状态回滚至上一个状态 tinghu.liu 20191119
            if (in_array($order_status, [100, 101,102, 120,300,301,310,320,1701,1710,1998,1999,2001,2002,2003,2004,2101,2102,3000])){
                Log::record('不处理的订状态，params：'.json_encode($params), 'notice');
                return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>502]);
            }
            //106 - 事后风控不通过【即支付失败】
            $order_status = ($order_status == 106)?121:$order_status;
            //107 - 事后风控通过【即支付成功】
            $order_status = ($order_status == 107)?200:$order_status;
            /** 订单状态处理 end **/

            /********** 区块链订单特殊处理 tinghu.liu 20191022 start ************/
            $machine_id = mb_substr($order_number, 6, 4);
            if ($machine_id == self::MACHINE_ID_FOR_BLOCK_CHAIN){
                //TODO 区块链订单相关通知处理
                $params['order_status'] = $order_status;
                return $this->statusV2ForBlockChain($params, $RESULT_SUCCESS, $RESULT_FAILURE, $time);
            }
            /********** 区块链订单特殊处理 end ************/

            $order_status_res = $this->order_model->getOrderInfoByOrderNumber($params['order_number'], 'order_id,order_status,order_branch_status, payment_system');
            //payment_system:使用的支付系统。1-旧系统（.net）;2-新系统（php）
            if(!empty($order_status_res) && isset($order_status_res['payment_system']) && $order_status_res['payment_system'] == 2){

                //订单状态已是200，接收OMS状态若是70需处理为付款确认中
                //从订单状态处理表中判断最新一条是否订单状态200  added by wangyj in 20190423
                $payment_processing_flag = false;

                if(
                    $order_status_res['order_branch_status'] != 105 && $order_status != 105
                    && $order_status_res['order_status'] >= $order_status
                    && !$payment_processing_flag
                    //当订单状态为纠纷或者chargeback时，需要修改状态 tinghu.liu 20190520
                    && !in_array($order_status_res['order_status'], [1700, 2000])
                ){

                    Log::record('同步OMS订单至Order，order_status:'.$order_status_res['order_status'].' oms order_status:'.$order_status,'notice');
                    return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>503]);
                }
            }else{

                Log::record('同步OMS订单至Order，order_info error.order_res_data:'.json_encode($order_status_res), 'error');
                //如果不是新支付系统的，需要直接返回200，因为会存在“用户使用新支付系统支付失败，后又换成旧支付系统进行支付，从而导致接收不了新支付系统支付失败通知”的情况 tinghu.liu 20190826
                return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>504]);
            }
            //如果订单状态为检货中、检货完成（400,407），在接收到订单取消通知时，记录错误日志，发送邮件通知【暂时不发】 tinghu.liu 20190419
            if (
                in_array($order_status_res['order_status'], [400,407])
                && $order_status == 1400
            ){
                $notice_msg = '同步OMS订单至Order，检货中的订单不允许取消,order_status_res status:'.$order_status_res['order_status'].', params:'.json_encode($params);
                //错误日志
                Monlog::write(LOGS_ORDER_STATUS_DETAILS,'error',__METHOD__,'SynOrderStatus'.$params['order_number'],$params,null,$notice_msg,0,'',$order_number);
                Log::record($notice_msg,'error');
                \think\Log::pathlog('APIRequest',$notice_msg,'SynOrderStatus400_407_1400(V2)');

                //这种情况不做订单状态的处理，记录信息 恒总定 tinghu.liu 20190525
                $this->order_model->synOrderStatusExceptionHandle($params, $order_status_res['order_status'], $order_status);
                return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>505]);
            }
            /**
             * 放开1702限制，“纠纷已解决，第三方没有给用户退款”，如果当前订单状态为1700（纠纷处理中），需要将订单状态回滚至上一个状态 tinghu.liu 20191119
             */
            $is_dispute_rollback = 0;
            if ($order_status == 1702){
                if ($order_status_res['order_status'] == 1700){
                    $last_order_status = $this->order_model->getOrderLastStatusForDisputeRollback($order_status_res['order_id']);
                    if ($last_order_status != 0){
                        $notice_msg = 'Dispute不退款，状态回滚，回滚的状态：'.$last_order_status;
                        Monlog::write(LOGS_ORDER_STATUS_DETAILS,'notice',__METHOD__,$function_name,$params,null,$notice_msg,0,'',$order_number);
                        $order_status = $last_order_status;
                        $is_dispute_rollback = 1;
                    }
                }else{
                    return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>510]);
                }
            }
            $create_on = isset($params['change_on'])?$params['change_on']:$time;
            $create_by = isset($params['change_by'])?$params['change_by']:'orderfrontendAPI';
            $reason = isset($params['reason'])?$params['reason']:'';
            $queue_data = json_encode(
                [
                    'order_number'=>$params['order_number'],
                    'order_status'=>$order_status,
                    /*'create_on'=>strtotime($params['ChangeOn']),
                    'create_by'=>$params['ChangeBy']*/
                    'create_on'=>$create_on,
                    'create_by'=>$create_by,
                    'reason'=>$reason,
                    'from_flag'=>100, //用于标识来至新payment支付
                    'is_dispute_rollback'=>$is_dispute_rollback, //是否是dispute没有给用户退款回滚订单状态
                ]
            );
            //写入队列，之后返回处理成功
            $redis_cluster = new RedisClusterBase();
            $res = $redis_cluster->lPush(
                'mall_order_success_change_order_status',
                $queue_data
            );
            \think\Log::pathlog('APIRequest','$redis_cluster:'.$res,'mall_order_success_change_order_status');
            if ($res){
                return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>506]);
            }else{
                return apiReturn(['code'=>2003, 'msg'=>$RESULT_FAILURE, 'bug_code'=>507]);
            }
            Log::record('同步OMS订单至Order，data-:'.$queue_data.', $res：'.$res,'notice');
        }catch (\Exception $e){
            Log::record('同步OMS订单至Order，异常：'.$e->getMessage(),'notice');
            return apiReturn(['code'=>2000, 'msg'=>$e->getMessage(), 'bug_code'=>580]);
        }
    }

    /**
     * 状态处理【区块链】
     * @param array $params
     * @param $RESULT_SUCCESS
     * @param $RESULT_FAILURE
     * @param $time
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function statusV2ForBlockChain(array $params, $RESULT_SUCCESS, $RESULT_FAILURE, $time){
        //队列增加标识，比如：flag == 600，在处理队列时候特殊处理
        $order_status = $params['order_status'];

        $order_status_res = $this->order_model->getOrderInfoByOrderNumberForBlockChain($params['order_number'], 'order_status,order_branch_status');

        //订单状态已是200，接收OMS状态若是70需处理为付款确认中
        //从订单状态处理表中判断最新一条是否订单状态200  added by wangyj in 20190423
        $payment_processing_flag = false;

        if(
            $order_status_res['order_branch_status'] != 105 && $order_status != 105
            && $order_status_res['order_status'] >= $order_status
            && !$payment_processing_flag
            //当订单状态为纠纷或者chargeback时，需要修改状态 tinghu.liu 20190520
            && !in_array($order_status_res['order_status'], [1700, 2000])
        ){
            Log::record('同步OMS订单至Order，order_status:'.$order_status_res['order_status'].' oms order_status:'.$order_status,'notice');
            return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>601]);
        }


        //如果订单状态为检货中、检货完成（400,407），在接收到订单取消通知时，记录错误日志，发送邮件通知【暂时不发】 tinghu.liu 20190419
        if (
            in_array($order_status_res['order_status'], [400,407])
            && $order_status == 1400
        ){
            $notice_msg = '同步OMS订单至Order，检货中的订单不允许取消,order_status_res status:'.$order_status_res['order_status'].', params:'.json_encode($params);
            //错误日志
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'SynOrderStatus'.$params['order_number'],$params,null,$notice_msg);
            Log::record($notice_msg,'error');
            \think\Log::pathlog('APIRequest',$notice_msg,'SynOrderStatus400_407_1400(V2)');

            //这种情况不做订单状态的处理，记录信息 恒总定 tinghu.liu 20190525
            $this->order_model->synOrderStatusExceptionHandle($params, $order_status_res['order_status'], $order_status);
            return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'bug_code'=>602]);
        }

        $create_on = isset($params['change_on'])?$params['change_on']:$time;
        $create_by = isset($params['change_by'])?$params['change_by']:'orderfrontendAPI';
        $reason = isset($params['reason'])?$params['reason']:'';
        $queue_data = json_encode(
            [
                'order_number'=>$params['order_number'],
                'order_status'=>$order_status,
                /*'create_on'=>strtotime($params['ChangeOn']),
                'create_by'=>$params['ChangeBy']*/
                'create_on'=>$create_on,
                'create_by'=>$create_by,
                'reason'=>$reason,
                'from_flag'=>600, //用于标识来至新payment支付
            ]
        );

        //写入队列，之后返回处理成功
        $redis_cluster = new RedisClusterBase();
        $res = $redis_cluster->lPush(
            'mall_order_success_change_order_status',
            $queue_data
        );
        \think\Log::pathlog('APIRequest','$redis_cluster:'.$res,'mall_order_success_change_order_status');
        if ($res){
            return apiReturn(['code'=>200, 'msg'=>$RESULT_SUCCESS, 'res'=>$res, 'bug_code'=>603]);
        }else{
            return apiReturn(['code'=>2003, 'msg'=>$RESULT_FAILURE, 'bug_code'=>680]);
        }
    }


    /**
     * 同步交易明细至Order
     * [
            'order_number'=>'',
            'notification_id'=>0,
            'txn_data'=>'',
            'parent_txn_ref'=>'',
            'amount'=>0.00,
            'currency_code'=>'',
            'txn_type'=>'',
            'notes'=>'',
            'third_party_txn_id'=>'',
            'third_party_parent_txn_id'=>'',
            'third_party_method'=>'',
            'txn_result'=>'',
            'risk_control_status'=>'',
            'payment_method'=>'',
            'payment_txn_id'=>'',
            'payment_parent_txn_id'=>'',
            'refunding_amount'=>0.00,
        ]
     * @return mixed
     */
    public function salesDetail(){
        $function_name = 'salesDetail';
        try{
            $params = json_decode(file_get_contents("php://input"), true);
            Log::record('同步交易明细-params：'.json_encode($params));

            $order_number = isset($params['order_number'])?$params['order_number']:'';
            $function_name .= $order_number;
            if (!empty($params)){
                Monlog::write(LOGS_ORDER_STATUS_DETAILS,'info',__METHOD__,$function_name,$params,null,null,0,'',$order_number);
            }

            /** 参数校验 **/
            $validate = $this->validate($params,(new SynOmsParams())->salesDetailRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            /** 记录交易明细数据 **/
            $model = new OrderModel();
            //拼装$data
            $order_id = 0;
            $order_data = $model->geOrderDataByWhere(['order_number'=>$params['order_number']]);
            //使用的支付系统。1-旧系统（.net）;2-新系统（php）
            $payment_system = 1;
            if (!empty($order_data)){
                $order_id = isset($order_data[0]['order_id'])?$order_data[0]['order_id']:0;
                $payment_system = isset($order_data[0]['payment_system'])?$order_data[0]['payment_system']:$payment_system;
            }
            //新支付系统支付的订单接收到旧支付系统推送的交易明细时，不处理(避免接收到Coupon交易明细导致实收金额不正确的问题) tinghu.liu 20191025
            if ($payment_system == 2){
                return apiReturn(['code'=>2004, 'msg'=>'订单（'.$order_number.'）不是.NET支付系统']);
            }
            $params['order_id'] = $order_id;
            $params['create_on'] = time();
            $res = $model->insertSalesTXN($params);
            if (true === $res){
                $this->refundResultHandle($params);
                return apiReturn(['code'=>200, 'msg'=>'Success']);
            }else{
                Log::record('salesDetail-error,params:'.json_encode($params).', res:'.$res);
                return apiReturn(['code'=>2002, 'msg'=>'Failure '.$res]);
            }
        }catch (\Exception $e){
            $tips = 'Internal anomaly, '.$e->getMessage();
            Log::record($tips);
            return apiReturn(['code'=>2003, 'msg'=>$tips]);
        }
    }

    /**
     * 【新payment专用】同步交易明细至Order
     * [
            'order_number'=>'',
            'notification_id'=>0,
            'txn_data'=>'',
            'parent_txn_ref'=>'',
            'amount'=>0.00,
            'currency_code'=>'',
            'txn_type'=>'',
            'notes'=>'',
            'third_party_txn_id'=>'',
            'third_party_parent_txn_id'=>'',
            'third_party_method'=>'',
            'txn_result'=>'',
            'risk_control_status'=>'',
            'payment_method'=>'',
            'payment_txn_id'=>'',
            'payment_parent_txn_id'=>'',
            'refunding_amount'=>0.00,
        ]
     * @return mixed
     */
    public function salesDetailV2(){
        $function_name = 'salesDetailV2';
        try{
            $params = json_decode(htmlspecialchars_decode(file_get_contents("php://input")), true);
            Log::record('同步交易明细-params：'.json_encode($params));

            $order_number = isset($params['order_number'])?$params['order_number']:'';
            $function_name .= $order_number;
            if (!empty($params)){
                Monlog::write(LOGS_ORDER_STATUS_DETAILS,'info',__METHOD__,$function_name,$params,null,null,0,'',$order_number);
            }

            /** 参数校验 **/
            $validate = $this->validate($params,(new SynOmsParams())->salesDetailRulesV2());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }

            $machine_id = mb_substr($order_number, 6, 4);
            if ($machine_id == self::MACHINE_ID_FOR_BLOCK_CHAIN){
                //区块链订单相关通知处理
                return $this->salesDetailV2ForBlockChain($params);
                exit();
            }

            /** 记录交易明细数据 **/
            $model = new OrderModel();
            //拼装$data
            $order_id = 0;
            $pay_type = $pay_channel = '';
            $order_data = $model->geOrderDataByWhere(['order_number'=>$params['order_number']]);
            if (!empty($order_data)){
                $order_id = isset($order_data[0]['order_id'])?$order_data[0]['order_id']:0;
                $pay_type = isset($order_data[0]['pay_type'])?$order_data[0]['pay_type']:'';
                $pay_channel = isset($order_data[0]['pay_channel'])?$order_data[0]['pay_channel']:'';
            }else{
                return apiReturn(['code'=>2003, 'msg'=>'无效的订单号：'.$params['order_number']]);
            }
            $params['order_id'] = $order_id;
            $params['create_on'] = time();
            $res = $model->insertSalesTXNV2($params, $pay_type, $pay_channel);
            if (true === $res){
                $this->refundResultHandleV2($params);
                return apiReturn(['code'=>200, 'msg'=>'Success']);
            }else{
                Log::record('salesDetail-error,params:'.json_encode($params).', res:'.$res);
                return apiReturn(['code'=>2002, 'msg'=>'Failure '.$res]);
            }
        }catch (\Exception $e){
            $tips = 'Internal anomaly, '.$e->getMessage().', '.$e->getFile().'['.$e->getLine().']';
            Log::record($tips);
            return apiReturn(['code'=>2003, 'msg'=>$tips]);
        }
    }

    /**
     * 交易明细处理【区块链】
     * @param array $params
     * @return mixed
     * @throws \Exception
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function salesDetailV2ForBlockChain(array $params){

        /** 记录交易明细数据 **/
        $model = new OrderModel();
        //拼装$data
        $order_id = 0;
        $pay_type = $pay_channel = '';
        $order_data = $model->geOrderDataByWhereForBlockChain(['order_number'=>$params['order_number']]);
        if (!empty($order_data)){
            $order_id = isset($order_data[0]['order_id'])?$order_data[0]['order_id']:0;
            $pay_type = isset($order_data[0]['pay_type'])?$order_data[0]['pay_type']:'';
            $pay_channel = isset($order_data[0]['pay_channel'])?$order_data[0]['pay_channel']:'';
        }else{
            return apiReturn(['code'=>2003, 'msg'=>'无效的订单号：'.$params['order_number']]);
        }
        $params['order_id'] = $order_id;
        $params['create_on'] = time();
        $res = $model->insertSalesTXNV2ForBlockChain($params, $pay_type, $pay_channel);
        if (true === $res){
            //区块链订单没有这个逻辑
            //$this->refundResultHandleV2($params);
            return apiReturn(['code'=>200, 'msg'=>'Success']);
        }else{
            Log::record('salesDetail-error,params:'.json_encode($params).', res:'.$res);
            return apiReturn(['code'=>2002, 'msg'=>'Failure '.$res]);
        }

    }

    /**
     * 退款&其他订单操作结果处理
     * @param $data
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function refundResultHandle($data){
//    public function refundResultHandle(){
        /*$data = [
            'order_number'=>'',
            'notification_id'=>0,
            'txn_data'=>'',
            'parent_txn_ref'=>'',
            'amount'=>0.00,
            'currency_code'=>'',
            'txn_type'=>'Refund',
            'notes'=>'',
            'third_party_txn_id'=>'',
            'third_party_parent_txn_id'=>'',
            'third_party_method'=>'',
            'txn_result'=>'',
            'risk_control_status'=>'',
            'payment_method'=>'',
            'payment_txn_id'=>'',
            'payment_parent_txn_id'=>'',
            'refunding_amount'=>0.00,
        ];*/
        //*** 类型&&结果 判断统一转为小写 ***
        $order_number = $data['order_number'];
        $txn_type = strtolower($data['txn_type']);
        $txn_result = strtolower($data['txn_result']);
        $txn_result_success = [strtolower('Success'),strtolower('SuccessWithWarning')];
        //退款类型
        $refund_type_arr = [strtolower('Reversed'), strtolower('RefundToSC'), strtolower('Refund'), strtolower('RemoteRefund')];
        //取消退款类型 TODO
        $cancel_refund_type_arr = [strtolower('CancelReversal'),strtolower('CancelRefundToSC')];
        //加锁类型
        $add_lock_type_arr = [strtolower('TemporaryHold'), strtolower('Reversed')];
        //解锁类型
        $cancel_lock_type_arr = [strtolower('CancelTemporaryHold'), strtolower('CancelReversal')];
        $order_info = $this->order_model->getOrderInfoByOrderNumber($order_number,'customer_id, affiliate');
        /** 订单退款处理 start **/
        if (in_array($txn_type, $refund_type_arr)){
            //1.如果是退款的数据，需要对相关数据进行处理
            if (in_array($txn_result, $txn_result_success)){
                //2.退款成功：1).修改订单正在退款金额，已退款金额，实收金额 2).返回赠送积分、佣金
                // 1).修改订单正在退款金额，已退款金额，实收金额
                $res = $this->order_model->handleOrderInfoForRefund($order_number, 1);
                // 2).返回赠送积分、佣金 TODO
                if ($res){
                    $p_res = $this->base_api->cancelOrderDecPoints(['CustomerID'=>$order_info['customer_id'], 'OrderNumber'=>$order_number]);
                    if (empty($p_res) || !isset($p_res['code']) || $p_res['code'] != 200){
                        Log::record('退款成功-扣减积分失败$order_number：'.$order_number.'->$p_res'.json_encode($p_res),'notice');
                    }
                    //20181029 如果是affiliate订单，需要调用“affiliate订单取消扣减推荐积分”接口
                    //20190114 kaiwen确定不用调
                    /*if (
                        isset($order_info['affiliate'])
                        && !empty($order_info['affiliate'])
                    ){
                        $_affiliate_params = [
                            'CustomerID'=>$order_info['customer_id'],
                            'OrderNumber'=>$order_number
                        ];
                        $affiliate_res = $this->base_api->CancelOrderDecReferralPoints($_affiliate_params);
                        if (empty($affiliate_res) || !isset($affiliate_res['code']) || $affiliate_res['code'] != 200){
                            Log::record('退款成功-affiliate订单取消扣减推荐积分失败$order_number：'.$order_number.'->params:'.json_encode($_affiliate_params).',$affiliate_res'.json_encode($affiliate_res),'notice');
                        }
                    }*/
                }else{
                    Log::record('退款成功-处理失败$order_number：'.$order_number,'notice');
                }
            }else{
                //3.退款失败：1).将订单状态修改为200（状态回退） 2).将退款中金额修改为0 3).已调的取消接口要回退？？？TODO
                $res = $this->order_model->handleOrderInfoForRefund($order_number, 2);
                if (!$res){
                    Log::record('退款失败-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 订单退款处理 end **/

        /** 加锁处理 start **/
        if (in_array($txn_type, $add_lock_type_arr)){
            if (in_array($txn_result, $txn_result_success)){
                $res = $this->order_model->updateOrderByWhere(['order_number'=>$order_number], ['lock_status'=>73]);
                if (!$res){
                    Log::record('加锁成功-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 加锁处理 end **/

        /** 解锁处理 start **/
        if (in_array($txn_type, $cancel_lock_type_arr)){
            if (in_array($txn_result, $txn_result_success)){
                $res = $this->order_model->updateOrderByWhere(['order_number'=>$order_number], ['lock_status'=>60]);
                if (!$res){
                    Log::record('解锁成功-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 解锁处理 end **/
    }

    /**
     * 【新payment专用】退款&其他订单操作结果处理
     * @param $data
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function refundResultHandleV2($data){
//    public function refundResultHandle(){
        /*$data = [
            'order_number'=>'',
            'notification_id'=>0,
            'txn_data'=>'',
            'parent_txn_ref'=>'',
            'amount'=>0.00,
            'currency_code'=>'',
            'txn_type'=>'Refund',
            'notes'=>'',
            'third_party_txn_id'=>'',
            'third_party_parent_txn_id'=>'',
            'third_party_method'=>'',
            'txn_result'=>'',
            'risk_control_status'=>'',
            'payment_method'=>'',
            'payment_txn_id'=>'',
            'payment_parent_txn_id'=>'',
            'refunding_amount'=>0.00,
        ];*/
        //*** 类型&&结果 判断统一转为小写 ***
        $order_number = $data['order_number'];
        $txn_type = strtolower($data['txn_type']);
        $txn_result = strtolower($data['txn_result']);
        $txn_result_success = [strtolower('Success'),strtolower('SuccessWithWarning')];
        //退款类型
        $refund_type_arr = [strtolower('Reversed'), strtolower('RefundToSC'), strtolower('Refund'), strtolower('RemoteRefund')];
        //取消退款类型 TODO
        $cancel_refund_type_arr = [strtolower('CancelReversal'),strtolower('CancelRefundToSC')];
        //加锁类型
        $add_lock_type_arr = [strtolower('TemporaryHold'), strtolower('Reversed')];
        //解锁类型
        $cancel_lock_type_arr = [strtolower('CancelTemporaryHold'), strtolower('CancelReversal')];
        $order_info = $this->order_model->getOrderInfoByOrderNumber($order_number,'customer_id, affiliate');
        /** 订单退款处理 start **/
        if (in_array($txn_type, $refund_type_arr)){
            //1.如果是退款的数据，需要对相关数据进行处理
            if (in_array($txn_result, $txn_result_success)){
                //2.退款成功：1).修改订单正在退款金额，已退款金额，实收金额 2).返回赠送积分、佣金
                // 1).修改订单正在退款金额，已退款金额，实收金额
                $res = $this->order_model->handleOrderInfoForRefund($order_number, 1);
                // 2).返回赠送积分、佣金 TODO
                if ($res){
                    $p_res = $this->base_api->cancelOrderDecPoints(['CustomerID'=>$order_info['customer_id'], 'OrderNumber'=>$order_number]);
                    if (empty($p_res) || !isset($p_res['code']) || $p_res['code'] != 200){
                        Log::record('退款成功-扣减积分失败$order_number：'.$order_number.'->$p_res'.json_encode($p_res),'notice');
                    }
                    //20181029 如果是affiliate订单，需要调用“affiliate订单取消扣减推荐积分”接口
                    //20190114 kaiwen确定不用调
                    /*if (
                        isset($order_info['affiliate'])
                        && !empty($order_info['affiliate'])
                    ){
                        $_affiliate_params = [
                            'CustomerID'=>$order_info['customer_id'],
                            'OrderNumber'=>$order_number
                        ];
                        $affiliate_res = $this->base_api->CancelOrderDecReferralPoints($_affiliate_params);
                        if (empty($affiliate_res) || !isset($affiliate_res['code']) || $affiliate_res['code'] != 200){
                            Log::record('退款成功-affiliate订单取消扣减推荐积分失败$order_number：'.$order_number.'->params:'.json_encode($_affiliate_params).',$affiliate_res'.json_encode($affiliate_res),'notice');
                        }
                    }*/
                }else{
                    Log::record('退款成功-处理失败$order_number：'.$order_number,'notice');
                }
            }else{
                //3.退款失败：1).将订单状态修改为200（状态回退） 2).将退款中金额修改为0 3).已调的取消接口要回退？？？TODO
                $res = $this->order_model->handleOrderInfoForRefund($order_number, 2);
                if (!$res){
                    Log::record('退款失败-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 订单退款处理 end **/

        /** 【新payment没有】加锁处理 start **/
        if (in_array($txn_type, $add_lock_type_arr)){
            if (in_array($txn_result, $txn_result_success)){
                $res = $this->order_model->updateOrderByWhere(['order_number'=>$order_number], ['lock_status'=>73]);
                if (!$res){
                    Log::record('加锁成功-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 加锁处理 end **/

        /** 【新payment没有】解锁处理 start **/
        if (in_array($txn_type, $cancel_lock_type_arr)){
            if (in_array($txn_result, $txn_result_success)){
                $res = $this->order_model->updateOrderByWhere(['order_number'=>$order_number], ['lock_status'=>60]);
                if (!$res){
                    Log::record('解锁成功-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 解锁处理 end **/
    }

    /**
     * 获取已发货的产品销量
     * @return mixed
     */
    public function getProductSalesForFulfillment(){
        try{
            Log::record('getProductSalesForFulfillment:php://input:'.print_r(file_get_contents("php://input"), true),'notice');
            Log::record('getProductSalesForFulfillment:input:'.print_r(input(), true),'notice');
            Log::record('getProductSalesForFulfillment:request-post:'.print_r(request()->post(), true),'notice');
            $params = json_decode(file_get_contents("php://input"), true);
            //参数校验
            $validate = $this->validate($params,(new SynOmsParams())->getProductSalesRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            /** 获取数据 **/
            $model = new OrderModel();
            $params['start_date'] = strtotime($params['start_date']);
            $params['end_date'] = strtotime($params['end_date']);
            $data = $model->getProductSalesWhenFulfillment($params);
            /** 拼装$data start **/
            //获取产品ID
            $product_id_arr = [];
            foreach ($data as $info){
                $product_id_arr[] = $info['product_id'];
            }
            $product_id_arr = array_unique($product_id_arr);
            //获取产品对应销量
            $rtn = [];
            foreach ($product_id_arr as $p){
                $tem = [];
                $sales_num = 0;
                foreach ($data as $val){
                    if ($val['product_id'] == $p){
                        $sales_num += $val['product_nums'];
                    }
                }
                $tem['spu_id'] = $p;
                $tem['sales_num'] = $sales_num;
                $rtn[] = $tem;
            }
            /** 拼装$data end **/
            if (!empty($rtn)){
                return apiReturn(['code'=>200, 'data'=>$rtn]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
        }
    }

}
