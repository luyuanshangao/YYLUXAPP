<?php
namespace app\orderfrontend\controller;

use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\order\services\OrderService;
use app\common\helpers\RedisClusterBase;
use think\Log;

/**
 * 清单查询专用接口类
 * @author heng.zhang
 * @version 1.0
 * 2018-06-07
 */
class OrderQuery extends Auth
{
    /**
     * 订单列表
     */
    public function getOrderList(){
        $paramData = request()->post();
        //var_dump($paramData);
        //die();
       //$paramData = array_filter($paramData);//因为有、0状态  该函数不能用
        foreach ($paramData as $key => $value){
            if(trim($paramData[$key]) ==''){
                unset($paramData[$key]);
            }
        }
        $where = [];
        $PageQuery  = [];
        //参数校验
        /*订单号*/
        if(isset($paramData['OrderNumber']) && $paramData['OrderNumber']!=''){
            $where['order_number'] = preg_replace('/^( |\s)*|( |\s)*$/', '', $paramData['OrderNumber']);
        }

        /*物流跟踪号 TODO*/
        if(isset($paramData['TrackingNumber'])){
            $order_package['tracking_number'] = $paramData['TrackingNumber'];
            if(!empty($paramData['OrderNumber'])){
               $order_package['order_number'] = $paramData['OrderNumber'];
            }
            $TrackingNumber = model("OrderQuery")->TrackingNumber($order_package);
            if(empty($TrackingNumber)){
               //如果是NOC，则到NOC跟踪号表再查一遍
               if(substr($order_package['order_number'], 0, 2) === 'NC'){
                    $TrackingNumber = model("OrderQuery")->TrackingNumberNOC($order_package);
                    //NOC跟踪号表订单号比其他表后面多两位，要去掉
                    $TrackingNumber['order_number'] = mb_substr($TrackingNumber['order_number'], 0, mb_strlen($TrackingNumber['order_number']) - 2);
               }else{
                    return apiReturn(['code'=>200,'data'=>'']);
               }
            }
        	$where['order_number'] = $TrackingNumber['order_number'];
        }

        /*交易TxnID*/
        if(isset($paramData['ThirdPartyTxnID']) && $paramData['ThirdPartyTxnID'] !=''){
              $PageQuery['ThirdPartyTxnID'] =	$where['transaction_id'] = $paramData['ThirdPartyTxnID'];
        }
        /*下单时间*/
        if(isset($paramData['startTime']) && $paramData['startTime'] !=''){
        	if(isset($paramData['endTime']) && $paramData['endTime'] !=''){
        		  //$startTime = date('Y-m-d H:i:s', strtotime($paramData['endTime']));
                  //$endTime = date('Y-m-d H:i:s', strtotime($paramData['endTime']));
                  $PageQuery['startTime'] = $paramData['startTime'];
                  $PageQuery['endTime']   = $paramData['endTime'];
                  $startTime = strtotime($paramData['startTime']);
                  $endTime   = strtotime($paramData['endTime']);
                  // if(strtotime($paramData['startTime'])){
                  //     $PageQuery['startTime'] = $startTime = strtotime($paramData['startTime']);
                  //     $PageQuery['endTime']   = $endTime   = strtotime($paramData['endTime']);
                  // }else{
                  //     $PageQuery['startTime'] = $startTime = $paramData['startTime'];
                  //     $PageQuery['endTime']   = $endTime   = $paramData['endTime'];
                  // }

        		  $where['o.create_on'] = array('between',[$startTime,$endTime]);
        	}
        }
        /*用户ID*/
        if(isset($paramData['UserID']) && $paramData['UserID'] !=''){
        	$PageQuery['UserID'] = $where['customer_id'] = $paramData['UserID'];
        }
        /*订单状态*/
        if(isset($paramData['OrderStauts']) && $paramData['OrderStauts'] !=''){
        	$PageQuery['OrderStauts'] = $where['order_status'] = $paramData['OrderStauts'];
        }
        /*订单类型*/
        if(isset($paramData['OrderType']) && $paramData['OrderType'] !=''){
        	$PageQuery['OrderType'] = $where['order_type'] = $paramData['OrderType'];
        }
        /*是否是COD订单*/
        if(isset($paramData['COD_order']) && $paramData['COD_order'] !=''){
            $PageQuery['COD_order'] = $where['is_cod'] = $paramData['COD_order'];
        }
        /*是否是加锁*/
        if(isset($paramData['Lock']) && $paramData['Lock'] !=''){
            $PageQuery['Lock'] = $where['lock_status'] = $paramData['Lock'];
        }
        /*业务类型 TODO
        if(isset($paramData['BusinessType']) && $paramData['BusinessType'] !=''){
        	//$where[''] = $paramData['BusinessType'];
        }    */

        /*支付渠道*/
        if(isset($paramData['paymentMethod_name']) && $paramData['paymentMethod_name'] !=''){
        	$PageQuery['paymentMethod_name'] = $where['pay_type'] = $paramData['paymentMethod_name'];
            // $PageQuery['paymentMethod_name'] = $where['pay_channel'] = $paramData['paymentMethod_name'];
        }
        /*运输方式*/
        if(isset($paramData['ShippingMethod']) && $paramData['ShippingMethod'] !=''){
        	$PageQuery['ShippingMethod'] = $where['shipping_model'] = $paramData['ShippingMethod'];
        }
        /*付款情况*/
        if(isset($paramData['PaymentStatus']) && $paramData['PaymentStatus'] !=''){
        	$PageQuery['PaymentStatus'] = $where['payment_status'] = $paramData['PaymentStatus'];
        }
        /*发货情况 30 订单收到，未处理; 31  备货中; 32 发货处理中; 33 部分发货; 34  已发货; 35  已完成; 36 已取消*/
        if(isset($paramData['FulfillmentStatus']) && $paramData['FulfillmentStatus'] !=''){
        	$PageQuery['FulfillmentStatus'] = $where['fulfillment_status'] = $paramData['FulfillmentStatus'];
        }
        /*收货国家 TODO */
        if(isset($paramData['ShippingCountryCode']) && $paramData['ShippingCountryCode'] !=''){
        	$PageQuery['ShippingCountryCode'] = $where['country_code'] = $paramData['ShippingCountryCode'];
        }
        /*商铺名 */
        if(isset($paramData['store_name']) && $paramData['store_name'] !=''){
            $PageQuery['store_name'] = $where['store_name'] = $paramData['store_name'];
        }
        /*商铺ID */
        if(isset($paramData['store_id']) && $paramData['store_id'] !=''){
            $PageQuery['store_id'] = $where['store_id'] = $paramData['store_id'];
        }
        /*订单来源 */
        if(isset($paramData['order_from']) && $paramData['order_from'] !=''){
            $PageQuery['order_from'] = $where['order_from'] = $paramData['order_from'];
        }
        /* 支付系统查询 */
        if(isset($paramData['payment_system']) && $paramData['payment_system'] !=''){
            $PageQuery['payment_system'] = $where['payment_system'] = $paramData['payment_system'];
        }
        /*币种编码*/
        if(isset($paramData['currency_code']) && $paramData['currency_code'] !=''){
            $PageQuery['currency_code'] = $where['currency_code'] = $paramData['currency_code'];
        }
        $page_size = isset($paramData['page_size'])?$paramData['page_size']:20;//return $where;
        $page = isset($paramData['page'])?$paramData['page']:1;
        $path = isset($paramData['path'])?$paramData['path']:"";
        $order= isset($paramData['orderBy'])?$paramData['orderBy']:" create_on desc ";//pr($where);
        $list = model("OrderQuery")->getOrderList($where,$page_size,$page,$path,$order,$PageQuery);
        return apiReturn(['code'=>200,'data'=>$list]);
    }


    /**
     * 根据订单号码获取订单明细数据
     * @param string $orderNumber
     * @return 订单明细数据
     */
    public function getOrderDetail(){
    	try{
	    	$paramData = request()->post();
            if(isset($paramData['orderNumber']) && !empty($paramData['orderNumber']) && is_numeric($paramData['orderNumber'])){
                $orderNumber= $paramData['orderNumber'];
                //当subset存在时是获取子订单号
                $res = model("OrderQuery")->getOrderDetail($orderNumber);
                return apiReturn(['code'=>200,'data'=>$res]);
            }
            return apiReturn(['code'=>201,'msg'=>'参数传递为空']);

            // if($paramData['subset'] == true){
            //    $res = model("OrderQuery")->getOrderSubset($orderNumber);
            // }else{
            //    $res = model("OrderQuery")->getOrderDetail($orderNumber);
            // }

    	}catch(Exception $ex){
    		$msg = $ex -> getMessage();
    		Log::write('getOrderDetail-根据订单号码获取订单明细数据查询数据库异常，错误信息:'.$msg);
    		return apiReturn(['code'=>201,'msg'=>$msg]);
    	}
    }
    /**
     * 加锁解锁11
     * [holdAndUnhold description]
     * @return [type] [description]
     * @author wang   2018-06-21
     */
    public function holdAndUnhold(){
       $data = request()->post();
       $result = model("OrderQuery")->holdAndUnhold($data["data"]);//return $result ;
       if($result){
            $order = $data["data"];
            $redis = new RedisClusterBase();
            $redis->LPUSH('ORDER_LOCK_STATUS_CHANGE_FOR_OMS',json_encode(array('order_number'=>$order['order_number'],'lock_status'=>$order['status']),true));
            return apiReturn(['code'=>200,'data'=>'操作成功']);
       }else{
            return apiReturn(['code'=>100,'data'=>'操作失败']);
       }
    }
    /**
     * 投诉管理
     * [orderAccuse description]
     * @return [type] [description]
     * @author wang   2018-06-23
     */
    public function orderAccuse(){
       $data = request()->post();
       $result = model("OrderQuery")->orderAccuse($data);
       return $result;
    }
    /**
     * 售后管理
     * [orderRefund description]
     * @return [type] [description]
     * @author wang   2018-06-25
     */
    public function orderRefund(){
       $data = request()->post();
       $result = model("OrderQuery")->orderRefund($data);
       return $result;
    }
    /**
     * 获取配置信息
     * [apiConfig description]
     * @return [type] [description]
     * @author wang   2018-06-26
     */
    public function apiConfig(){
         $data = request()->post();
         $apiConfig['after_sale_type']   = config('after_sale_type');//售后类型
         $apiConfig['after_sale_status'] = config('after_sale_status');//售后状态
         $apiConfig['refunded_type']     = config('refunded_type');//退款类型
         $apiConfig['accuse_reason']     = config('accuse_reason');//投诉原因
         $apiConfig['sdd']     = 1;
         return $apiConfig;
    }
     /**
     * 售后详情
     * [afterSaleDetails description]after_sale_type
     * @return [type] [description]
     * @author wang   2018-06-27
     */
    public function afterSaleDetails(){
         $data = request()->post();
         $result = model("OrderQuery")->afterSaleDetails($data);
         return $result;
    }
    public function arbitration(){
        $data = request()->post();//return $data;
        $result = model("OrderQuery")->arbitration($data);
        return apiReturn(['code'=>100,'data'=>$result]);


    }
    /**
     * 仲裁回复
     * [applyLog description]
     * @return [type] [description]
     * @author wang   2018-08-21
     */
    public function applyLog(){
        $data = request()->post();
        if(!$data){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->applyLog($data);
        return $result;
    }
    /**
     * 产品举报
     * [CustomsInsurance description]
     * @author wang   2018-09-08
     */
    public function CustomsInsurance(){
        $data = request()->post();
        if(!$data){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->CustomsInsurance($data);
        return $result;
    }
    /**
     * 根据条件查询
     * [order description]
     * @return [type] [description]
     * @author wang   addtime 2018-09-27
     */
    public function order_query(){
        $data = $_POST;
        // file_put_contents ('../runtime/log/201812/rukou.log',json_encode($data).'----', FILE_APPEND|LOCK_EX);
        if(empty($data)){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->order_query($data);
        return $result;
    }

    /**
     * nocnoc面单地址查询
     * @return json
     * @author wangyj addtime 20190313
     */
    public function getNocOrderLabel(){

        try{
            $paramData = request()->post();
            if(isset($paramData['orderNumber']) && !empty($paramData['orderNumber']) && is_numeric($paramData['orderNumber'])){
                $orderNumber= $paramData['orderNumber'];
                /**
                 * 因为有NOCNOC拆单情况，所以这里要判断是不是NOCNOC拆单后的订单号，如果是，需要转换为源订单号
                 * 正常订单号 190412100138511822，长度为 18 位
                 * NOCNOC拆单后是在正常订单好加 01（nocnoc订单） 或 02（非nocnoc订单），一共20位
                 * tinghu.liu 20190415
                 */
                if (strlen($orderNumber) == 20){
                    $orderNumber = substr($orderNumber, 0, 18);
                }
                $res = model("OrderQuery")->getNocOrderLabel($orderNumber, 'response_json');
                if(!empty($res) && !empty($res['response_json'])){

                    $res = json_decode($res['response_json'], true);

                    if(is_array($res)){

                        $labels = [];

                        foreach ($res as $key => $value) {

                            $productsList = array();
                            if(isset($res['label_url'])){

                                if(isset($res['products']) && !empty($res['products'])){

                                    foreach ($res['products'] as $p) {
                                        $productsList[] = array('sku'=>$p['sku'], 'quantity'=>$p['quantity'], 'description'=>$p['description'], 'amount_usd'=>$p['amount_usd'], 'hs_code'=>(empty($p['hs_code'])?'':$p['hs_code']), 'pickup'=>$p['pickup']);
                                    }
                                }

                                $labels[] = ['label_url'=>$res['label_url'], 'tracking_id'=>$res['tracking_id'], 'id'=>$res['id'], 'products'=>$productsList, 'customer'=>$res['customer'], 'address'=>$res['address']];
                                break;
                            }elseif(is_array($value)){

                                if(isset($value['products']) && !empty($value['products'])){

                                    foreach ($value['products'] as $p) {
                                        $productsList[] = array('sku'=>$p['sku'], 'quantity'=>$p['quantity'], 'description'=>$p['description'], 'amount_usd'=>$p['amount_usd'], 'hs_code'=>(empty($p['hs_code'])?'':$p['hs_code']), 'pickup'=>$p['pickup']);
                                    }
                                }

                                if(isset($value['label_url'])){

                                    $labels[] = ['label_url'=>$value['label_url'], 'tracking_id'=>$value['tracking_id'], 'id'=>$value['id'], 'products'=>$productsList, 'customer'=>$value['customer'], 'address'=>$value['address']];
                                }
                            }
                        }
                    }
                }
                if(isset($labels) && !empty($labels)){

                    return apiReturn(['code'=>200,'data'=>$labels]);
                }else{

                    return apiReturn(['code'=>201,'msg'=>'未查询到数据']);
                }
            }
            return apiReturn(['code'=>201,'msg'=>'参数传递为空']);

        }catch(Exception $ex){
            $msg = $ex -> getMessage();
            Log::write('getNocOrderLabel-exception:'.$msg);
            return apiReturn(['code'=>201,'msg'=>$msg]);
        }
    }
    /**
     * 获取留言历史记录(用于admin订单详情)
     * [HistoryRecordList description]
     * @auther wang  2019-04-19
     */
    public function HistoryRecordList(){
        $data = $_POST;
        if(empty($data['user_id']) && empty($data['order_id'])){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->HistoryRecordList($data);
        return apiReturn(['code'=>200,'data'=>$result]);
    }

    /**
     * 订单信息，用于地图显示订单信息 add by zhongning 20190726
     */
    public function mapOrderList(){
        $data = array();
        $list = model("OrderQuery")->mapOrderList(10,1);
        if(!empty($list['data'])){
            foreach($list['data'] as $key => $val){
                $data[$key]['ID'] = $val['order_id'];
                $data[$key]['OrderNumber'] = $val['order_number'];
                $data[$key]['CustomerName'] = $val['customer_name'];
                $data[$key]['CreateOn'] = $val['add_time'];
                $data[$key]['TotalAmount'] = $val['captured_amount_usd'];
                $data[$key]['Country'] = strtolower($val['country_code']);
                $data[$key]['CustomerOrderth'] = strtolower($val['product_nums']);
            }
        }
        return jsonp($data);
    }

    /**
     * topseller 产品 定时获取订单产品
     * @return mixed
     */
    public function getTopSellerOrderData(){
        $params = input();
        if(empty($params['startTime'])){
            return apiReturn(['code'=>100,'data'=>'参数有误']);
        }
        $limit = !empty($params['limit']) ? $params['limit'] : null;
        $startTime = $params['startTime'];
        //默认取当天时间
        $endTime = !empty($params['endTime']) ? $params['endTime'] : strtotime(date('Ymd'));
        $data = array();
        $list = model("OrderQuery")->topSellerOrderData($startTime,$endTime,$limit);
        if(!empty($list)){
            $data = CommonLib::getColumn('product_id',$list);
        }
        return apiReturn(['code'=>200,'data'=>$data]);
    }
}
