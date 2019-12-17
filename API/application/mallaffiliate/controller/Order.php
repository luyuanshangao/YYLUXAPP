<?php
namespace app\mallaffiliate\controller;

use app\common\controller\BaseOrder;
use app\common\helpers\RedisClusterBase;
use app\mallaffiliate\model\OrderModel;
use app\mallaffiliate\model\ProductModel;
use app\mallaffiliate\dxcommon\BaseApi;
use think\Controller;
use think\Exception;
use think\Db;
/**
 * 开发：tinghu.liu
 * 功能：订单相关功能处理类
 * 时间：2018-08-26
 */
class Order extends BaseOrder
{
    public $redis;
    public $orderModel;
    public $productModel;
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
        $this->orderModel = new OrderModel();
        $this->productModel = new ProductModel();
        define('REPORTS', 'reports');//Nosql数据表
        define('APPLY_LOG', 'order_after_sale_apply_log');//mysql数据表 仲裁回复表
        define('SALES_ORDER_MESSAGE', 'sales_order_message');
        define('ORDER_REMARKS', 'order_remarks');
    }

    /**
     * 获取订单统计
     * @return mixed
     * mallaffiliate/Order/getOrderStatistics
     * {
        "create_on_start":"2019-08-01 00:00:00",
        "create_on_end":"2019-09-01 00:00:00",
        "query_flag":2
        }
     */
    public function getOrderStatistics(){
        $params = request()->post();
        try{
            $data = $this->orderModel->getOrderStatistics($params);
            return (['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return (['code'=>3000, 'msg'=>'System error']);
        }
    }

    /**
     * 根据产品ID、收货地址国家、时间查询订单销量
     * @return array
     *
     * mallaffiliate/Order/getOrderSales
     *
     * {
        "create_on_start":"2018-01-23 00:00:00",
        "create_on_end":"2019-08-24 00:00:00",
        "product_ids":[
            2609629,2605119,2612688
        ],
        "country_code":"US"
        }
     *
     */
    public function getOrderSales(){
        $params = request()->post();
        try{
            $data = $this->orderModel->getOrderSales($params);
            return (['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return (['code'=>3000, 'msg'=>$e->getMessage()]);
        }
    }

    public function getOrderProduct(){
        $params = request()->post();
        try{
            $page_size=!empty($params['page_size'])?$params['page_size']:10;
            $page=!empty($params['page'])?$params['page']:1;
            $params['order']=!empty($params['order'])?$params['order']:'all';
            $params['by']=!empty($params['by'])?$params['by']:'desc';
            if($params['order']=='all'){
                $data = $this->orderModel->getOrderProduct($params,$page_size,$page);
            }else{
                $data = $this->orderModel->getOrderProductSuccess($params,$page_size,$page);
            }


            return $data;
        }catch (Exception $e){
            return (['code'=>3000, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据产品ID、获取订单列表
     * @return array
     */
    public function getOrderList(){
        $params = request()->post();
        try{
            $data = $this->orderModel->getOrderlist($params);
            if(!empty($data['data'])){
                $ProductModel=new ProductModel();
                $orderStautsDict = $ProductModel->dictionariesQuery('OrderStatusView');
                $payStautsDict = $ProductModel->dictionariesQuery('PaymentStatus');
                foreach($data['data'] as &$value){
                    $value['order_status_name'] =!empty($orderStautsDict[$value['order_status']])?$orderStautsDict[$value['order_status']]:'';
                    $value['payment_status_name'] =!empty($orderStautsDict[$value['payment_status']])?$orderStautsDict[$value['payment_status']]:'未付款';
                }
            }

            return (['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return (['code'=>3000, 'msg'=>$e->getMessage()]);
        }
    }

    public function getProductSkus(){
        $params = request()->post();
        $result = array();
        try{
            if(empty($params['spus'])){
                return (['code'=>10002, 'msg'=>'Error in input parameter']);
            }
            $params['spus'] = explode(',',$params['spus']);
            $data = $this->productModel->selectProductSku($params);
            if(!empty($data)){
                foreach($data as $key => $product){
                    $result[$key]['spu'] = $product['_id'];
                    if(!empty($product['Skus'])){
                        foreach($product['Skus'] as $skey => $skus){
                            $result[$key]['sku'][$skey]['code'] = $skus['Code'];
                            $result[$key]['sku'][$skey]['sales_price'] = (string)$skus['SalesPrice'];
                        }
                    }
                }
            }
            return (['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            return (['code'=>3000, 'msg'=>'System error']);
        }

    }

    /**
     * 订单管理--查看、编辑 yxh-2019-10-08
     * @param string $id(订单编码)
     * @return \think\response\View
     */
    public function edit($id=''){
        $data['orderNumber']= $id;
        $data['subset']     = false;
        $BaseApi=new BaseApi();
        $result = $BaseApi->getOrderDetail($data);
        $remarks = [];

        if(!empty($result)){
            //获取后台配置的订单状态
            $orderStautsDict = $this->dictionariesQuery('OrderStatusView');

            /*订单全部状态，包括小状态*/
            $order_status_all = $this->getSysConfig('order_status_all');
            $order_status_all_data = array();
            if(!empty($order_status_all)){
                $order_status_all = json_decode($order_status_all,true);
                foreach ($order_status_all as $key=>$value){
                    $order_status_all_data[$value['code']] = $value['name'];
                }
            }
            $customerName =$result["data"]["address"]['first_name'].' '.$result["data"]["address"]['last_name'];
            $order_status_txt = $this->getDictValue($orderStautsDict,$result["data"]['order_status']);
            $OrderMessageTemplate = $this->dictionariesQuery("OrderMessageTemplate");
            $result['data']['currency_code_str'] = Base::getCurrencyCodeStr($result['data']['currency_code']);

            foreach($result["data"]['order_status_change'] as $key => $value){
                $order_change_status_from_txt = $this->getDictValue($orderStautsDict,$value['order_status_from']);
                $order_change_status_txt = $this->getDictValue($orderStautsDict,$value['order_status']);
                if(strlen($order_change_status_txt)>0){
                    $result["data"]['order_status_change'][$key]['order_status_from_txt'] = $order_change_status_from_txt;
                    $result["data"]['order_status_change'][$key]['order_status_txt'] =$order_change_status_txt;
                }
            }
            //dump($result["data"]['order_status_change']);
            //变化状态
            //$order_change_status_txt = $this->getDictValue($orderStautsDict,$result["data"]['order_status_change']['order_status']);
            //$result["data"]['order_status_change']['order_change_status_txt'] = $order_change_status_txt;
            //订单类型
            $orderTypeDict = $this->dictionariesQuery('OrderType');
            //dump($orderTypeDict);
            $orderType = $this->getDictValue($orderTypeDict,$result["data"]['order_type']);
            $result["data"]['order_status_txt'] = $order_status_txt;
            // $result["data"]['shippedAddress'] = $shippedAddress;
            //dump($orderType);
            $result["data"]['order_type_txt'] = $orderType;
            //dump($result["data"]);
            //die();
            //支付订单
            $paymentGreenStyle= 'glyphicon-time gray';
            $paymentGraybgStyle = 'gray-bg';
            $paymentTime='';
            $paymentStatusRow= $this -> filterArrayByKey($result["data"]['order_status_change'], 'order_status', 200);
            if(!empty($paymentStatusRow)){
                if(isset($paymentStatusRow['create_on'])){
                    $paymentGreenStyle = 'glyphicon-ok green';
                    $paymentGraybgStyle='';
                    $paymentTime=$paymentStatusRow['create_on'];
                }
            }

            //平台发货
            $shippingGreenStyle= 'glyphicon-time gray';
            $shippingGraybgStyle = 'gray-bg';
            $shippingTime='';
            $shippingRow= $this -> filterArrayByKey($result["data"]['order_status_change'], 'order_status', 600);
            if(!empty($shippingRow)){
                if(isset($shippingRow['create_on'])){
                    $shippingGreenStyle= 'glyphicon-ok green';
                    $shippingGraybgStyle='';
                    $shippingTime=$shippingRow['create_on'];
                }
            }
            //确认收货
            $confirmShippingGreenStyle= 'glyphicon-time gray';
            $confirmShippingGraybgStyle = 'gray-bg';
            $confirmShippingTime='';
            $confirmShippingRow= $this -> filterArrayByKey($result["data"]['order_status_change'], 'order_status', 800);
            if(!empty($confirmShippingRow)){
                if(isset($confirmShippingRow['create_on'])){
                    $confirmShippingGreenStyle= 'glyphicon-ok green';
                    $confirmShippingGraybgStyle='';
                }
            }

            //数据重组
            foreach ($result["data"]['itemList'] as $k => $v) {
                $attribute_html = '';
                $product_attr_desc = explode(',', $v["product_attr_desc"]);
                foreach ($product_attr_desc as $ke => $va) {
                    $attribute = explode('|', $va);
                    if(isset($attribute[1])){
                        $img = str_replace(array('.jpg','.png'),array('_70x70.jpg','_70x70.png'),$attribute[1]);
                        $attribute_html .= $attribute[0].'<img src="'.$img.'" >';
                    }else{
                        $attribute_html .= $attribute[0];
                    }
                }
                $result["data"]['itemList'][$k]['product_attr_desc_html'] = $attribute_html;
            }
            /*
             * 获取退款订单信息，当退款失败后可再次退款
             * */
            $OrderRefunWhere['order_number'] =  $result["data"]['order_number'];
            $OrderRefunWhere['status'] =  3;
            $OrderRefundInfo = $BaseApi->getOrderRefundList($OrderRefunWhere);
            if ($OrderRefundInfo['code'] == 200){
                if(isset($OrderRefundInfo['data'][0]['status']) && !empty($OrderRefundInfo['data'][0]['status'])){
                    $result["data"]['order_refund_data'] = $OrderRefundInfo['data'];
                    $result["data"]['order_refund_status'] = $OrderRefundInfo['data'][0]['status'];
                    $result["data"]['refund_id'] = isset($OrderRefundInfo['data'][0]['refund_id'])?$OrderRefundInfo['data'][0]['refund_id']:'';
                }
            }

            if(!empty($result["data"]['after_sale_apply'][0]['status'])){
                $result["data"]['after_sale_apply_data'] = $OrderRefundInfo['data'];
                $result["data"]['after_sale_apply_status'] = $result["data"]['after_sale_apply'][0]['status'];
            }
            $OrderRefunOperationWhere['order_id'] = $result["data"]['order_id'];
            $OrderRefunOperation = $BaseApi->getOrderRefundOperation($OrderRefunOperationWhere);
            if ($OrderRefunOperation['code'] == 200){
                $result["data"]['order_refun_operation'] = $OrderRefunOperation['data'];
            }

            if(!empty($result["data"]['order_id'])){
                $remarks = (Db::connect("db_admin")->name(ORDER_REMARKS)->where(['order_id'=>$result["data"]['order_id']])->find());
            }else{
                echo "订单不存在";exit;
            }

            /*配置回复模板显示地址*/
            $address =
                <<<EOF
                                        <br/>FirstName: {$result['data']['address']['first_name']}<br/>
                        LastName:{$result['data']['address']['last_name']}<br/>
                        Phone Number: {$result['data']['address']['mobile']}<br/>
                        Country/Region: {$result['data']['address']['country']}<br/>
                        State/Province:{$result['data']['address']['state']}<br/>
                        City: {$result['data']['address']['city']}<br/>
                        Street1: {$result['data']['address']['street1']}<br/>
                        Street2:{$result['data']['address']['street2']}<br/>
                        Postal Code: {$result['data']['address']['postal_code']}<br/>
EOF;
            $result["data"]['address_text'] = $address;
            $admin_user = getCustomerService();

            $UserNewOrderMessageDataWhere['order_id'] = $result["data"]['order_id'];
            $UserNewOrderMessageDataWhere['message_type'] = 2;//dump($result["data"]);

            $UserNewOrderMessageData = model("OrderMessage")->getUserNewOrderMessageData($UserNewOrderMessageDataWhere);

            $message_template_where['order_status'] = $result["data"]['order_status'];
            $order_message_template = model("OrderMessageTemplateModel")->getOrderMessageTemplate($message_template_where);
            if(!empty($order_message_template)){
                foreach ($order_message_template as $key=>$value){
                    $order_message_template[$key]['content_en'] = replaceContent($value['content_en'],$result["data"]);
                }
            }
            $order_from_data = [10=>"PC",20=>"Android",30=>"IOS",40=>"Pad",50=>"Mobile"];
            return ['orderDetail'=>$result["data"],'paymentGreenStyle'=>$paymentGreenStyle,
                'paymentTime'=>$paymentTime,'paymentGraybgStyle'=>$paymentGraybgStyle,
                'shippingGreenStyle'=>$shippingGreenStyle,'shippingGraybgStyle'=>$shippingGraybgStyle,
                'shippingTime'=>$shippingTime,'confirmShippingGreenStyle'=>$confirmShippingGreenStyle,
                'confirmShippingGraybgStyle'=>$confirmShippingGraybgStyle,
                'remarks'=>$remarks,
                'admin_user'=>$admin_user,
                'UserNewOrderMessageData'=>$UserNewOrderMessageData,
                'order_message_template'=>$order_message_template,
                'order_from_data'=>$order_from_data,
                //修改订单状态相关参数 tinghu.liu 20190702
                //'update_order_status_arr'=>$this->haveUpdateOrderStatusAuth(1),
                'order_status_all_data'=>$order_status_all_data
            ];
        }

    }
    /**
     * 获取更改订单状态权限相关配置
     * tinghu.liu 20190702
     * @return array
     */
    private function haveUpdateOrderStatusAuth($user_id){
        //更改订单状态配置获取
        $update_order_status_config = json_decode(htmlspecialchars_decode(htmlspecialchars_decode($this->getSysConfig('UpdateOrderStatusConfig'))), true);

        //订单状态配置
        $order_status_config = $this->dictionariesQuery('OrderStatusView');

        //当前用户是否有更改订单状态的权限：1-有，2-没有
        $user_auth = in_array($user_id, $update_order_status_config['user_id'])?1:2;
        //允许修改的状态
        $from_limit = $update_order_status_config['from_limit'];
        $from_limit_arr = [];
        foreach ($from_limit as $k1=>$v1){
            foreach ($order_status_config as $k2=>$v2){
                if ($v2[0] == $v1){
                    $from_limit_arr[$v1] = $v2;
                }
            }
        }
        //允许修改后的状态
        $to_limit = $update_order_status_config['to_limit'];
        $to_limit_arr = [];
        foreach ($to_limit as $k3=>$v3){
            foreach ($order_status_config as $k4=>$v4){
                if ($v4[0] == $v3){
                    $to_limit_arr[$v3] = $v4;
                }
            }
        }
        return [
            'user_auth'=>$user_auth,
            'from_limit_arr'=>$from_limit_arr,
            'to_limit_arr'=>$to_limit_arr,
            'from_limit'=>$from_limit,
            'to_limit'=>$to_limit,
        ];
    }

    /**
     * 获取订单统计
     * @return mixed
     * mallaffiliate/Order/getOrderStatistics
     * {
    "create_on_start":"2019-08-01 00:00:00",
    "create_on_end":"2019-09-01 00:00:00",
    "query_flag":2
    }
     */
    public function getOrderStatisticsSum(){
        $params = request()->post();
        try{
            $data = $this->orderModel->getOrderStatisticsSum($params);
            return (['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return (['code'=>3000, 'msg'=>'System error']);
        }
    }

    public function getCartNum(){
        $params = request()->post();
        $data = $this->orderModel->getCartSum($params);
        $data = collection($data)->toArray();
        $result = array_column($data,'DataKey');
        $result= array_unique($result);

        $BaseApi=new BaseApi();
        $wish_num = $BaseApi->getWishNum($params);
        $da['cart_num']=count($result);
        $da['wish_num']=$wish_num;
        return (['code'=>200, 'data'=>$da]);
    }

}
