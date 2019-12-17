<?php
namespace app\admin\controller;

use app\admin\dxcommon\ExcelTool;
use app\admin\model\LogisticsLog;
use app\admin\model\OrderMessageTemplateModel;
use app\admin\model\OrderModel;
use app\admin\dxcommon\Token;
use app\common\helpers\CommonLib;
use think\Exception;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cache;
use app\admin\dxcommon\BaseApi;
use app\admin\dxcommon\Base;
use think\Log;


/**
 * 商城管理--订单管理--订单管理
 * Add by:zhangheng
 * AddTime:2018-06-04
 * Info:
 *     1.商城管理--订单管理--订单管理:查询，修改，删除
 */
class Order extends Action
{
    private  $sign_flag='syncPostTrackingNumber';
    private  $store_id=333;
    const public_log = 'public_log';
    public function __construct(){
        Action::__construct();
        define('REPORTS', 'reports');//Nosql数据表
        define('APPLY_LOG', 'order_after_sale_apply_log');//mysql数据表 仲裁回复表
        define('SALES_ORDER_MESSAGE', 'sales_order_message');
        define('ORDER_REMARKS', 'order_remarks');
        define('TEN_MILLION', '50000000');
    }

    /**
     * 订单管理--查询
     */
    public function index()
    {
        $result = [];
        $businessTypeHtml = '';
        //是否选择订单状态值
        $selectedOrderStatusValue = 0;
        // 业务类型
        $selectedBusinessType=0;
        //支付方式
        $selectedPaymentMethod='';
        //付款情况
        $selectedPaymentStatus=0;
        //发货情况
        $selectedFulfillmentStatus=0;
        //是否选择的国家简码
        $selectedCountryValue = '';#该值不可默认0
        $selectedorder_type = '';//订单类型
        $selectedorder_COD = '';
        $selectedorder_Lock = '';//锁

        // if($data = request()->post()){}
        $selectedOrderStatusValue = input('OrderStauts')?input('OrderStauts'):0;
        $selectedBusinessType = input('BusinessType')?input('BusinessType'):0;
        // $selectedPaymentMethod = input('PaymentMethod')?input('PaymentMethod'):0;
        $selectedPaymentMethod = input('paymentMethod_name')?input('paymentMethod_name'):'';
        $selectedPaymentStatus = input('PaymentStatus')?input('PaymentStatus'):0;
        $selectedFulfillmentStatus = input('FulfillmentStatus')?input('FulfillmentStatus'):0;
        $selectedCountryValue = input('ShippingCountryCode')?input('ShippingCountryCode'):'';
        $selectedorder_Lock = input('Lock')?input('Lock'):'';//锁
        $paymentMethod_name = input('paymentMethod_name')?input('paymentMethod_name'):'';//锁
        // dump($selectedPaymentMethod);
        if(!empty(input('OrderType')) || input('OrderType') == 0){
            $selectedorder_type = input('OrderType');
        }
        if(!empty(input('COD_order')) || input('COD_order') == 0){
            $selectedorder_COD  = input('COD_order');
        }

        //获取后台配置的订单状态
        $orderStautsDict = $this->dictionariesQuery('OrderStatusView');
        $orderStautsHtml = $this -> outSelectHtml($orderStautsDict,'OrderStauts',$selectedOrderStatusValue);

        //订单类型
        $orderTypeDict = $this->dictionariesQuery('OrderType');
        unset($orderTypeDict[1]);
        //dump($orderTypeDict);
        $orderType = $this->getDictValue($orderTypeDict,!empty($result["data"]['order_type'])?$result["data"]['order_type']:'');
        $orderTypeHtml = $this -> outSelectHtml($orderTypeDict,'OrderType',$selectedorder_type);
        //获取后台配置的订单来源
        $OrderFrom = $this->dictionariesQuery('OrderFrom');

        //是否COD订单
        $COD_order = $this->dictionariesQuery('COD_order');
        $COD_orderHtml = $this -> outSelectHtml($COD_order,'COD_order',$selectedorder_COD);

        //是否COD订单
        $Lock_order = $this->dictionariesQuery('Lock');
        $Lock_orderHtml = $this -> outSelectHtml($Lock_order,'Lock',$selectedorder_Lock);
        //获取后台配置的业务类型值
        // $businessTypeDict = $this->dictionariesQuery('OrderBusinessType');
        // $businessTypeHtml = $this -> outSelectHtml($businessTypeDict,'BusinessType',$selectedBusinessType);
        //获取后台配置的支付方式
        $paymentMethodDict = $this->dictionariesQuery('PaymentMethod');
        foreach ($paymentMethodDict as $k_pay => $v_pay) {
            $paymentMethodDict[$k_pay][0] = $v_pay[1];
        }
        $paymentMethodHtml = $this -> outSelectHtml($paymentMethodDict,'paymentMethod_name',$selectedPaymentMethod);
        //获取后台配置的付款情况
        $paymentStatusDict = $this->dictionariesQuery('PaymentStatus');
        $paymentStatusHtml = $this -> outSelectHtml($paymentStatusDict,'PaymentStatus',$selectedPaymentStatus);
        //获取后台配置的发货情况
        $fulfillmentStatusDict = $this->dictionariesQuery('FulfillmentStatus');
        $fulfillmentStatusHtml = $this -> outSelectHtml($fulfillmentStatusDict,'FulfillmentStatus',$selectedFulfillmentStatus);

        //收货国家
        $baseApi = new BaseApi();
        $countryList = $baseApi::getRegionData_AllCountryData();
        $shippingCountrySelectHtml ='';
        $shippingCountrySelectHtml ='<select name="ShippingCountryCode" id="" class="form-control input-small inline">';
        $shippingCountrySelectHtml .='<option value="">请选择</option>';
        if(!empty($countryList)){
            foreach ($countryList['data'] as $key => $value){
                $isSelected='';
                if($value['Code'] == $selectedCountryValue){
                    $isSelected =' selected = "selected" ';
                }
                $shippingCountrySelectHtml .='<option '.$isSelected . ' value="'.$value['Code'].'">'.$value['Name'].'</option>';
            }
        }
        $shippingCountrySelectHtml .='</select>';
        $ShippingServiceMethod = $this->dictionariesQuery('ShippingServiceMethod');
        if(!empty($ShippingServiceMethod)){
            foreach ($ShippingServiceMethod as $key=>$value){
                $ShippingServiceMethodData = explode('-',$value[1]);
                $ShippingServiceMethod[$key]['cn'] = $ShippingServiceMethodData[0];
                $ShippingServiceMethod[$key]['en'] = $ShippingServiceMethodData[1];
            }
        }
        
        $order_from = [];
        foreach ($OrderFrom as $value) {
            $order_from[$value[0]] = $value[1];
        }
        /*获取币种*/
        $currency_info_api = $baseApi::getCurrencyList();
        $currency_info_data = $currency_info_api['data'];
        $this->assign(['orderStautsHtml'=>$orderStautsHtml,'businessTypeHtml'=>$businessTypeHtml,
            'paymentMethodHtml'=>$paymentMethodHtml,'paymentStatusHtml'=>$paymentStatusHtml,
            'fulfillmentStatusHtml'=>$fulfillmentStatusHtml,'shippingCountrySelectHtml'=>$shippingCountrySelectHtml,
            'orderTypeHtml'=>$orderTypeHtml,
            'OrderFrom'=>$OrderFrom,
            'orderTypeDict'=>$orderTypeDict,
            'COD_orderHtml'=>$COD_orderHtml,
            'Lock_orderHtml' =>$Lock_orderHtml,
            'paymentMethod_name' =>$paymentMethod_name,
            'ShippingServiceMethod'=>$ShippingServiceMethod,
            'order_from' => $order_from,
            'currency_info_data' => $currency_info_data
        ]);
        //绑定列表区域订单字段的枚举值
        $this->assign(['orderStautsDict'=>$orderStautsDict,
            // 'businessTypeDict'=>$businessTypeDict,
            'paymentStatusDict'=>$paymentStatusDict,
            'fulfillmentStatusDict'=>$fulfillmentStatusDict
        ]);
        $this ->getOrderList();

        return View();
    }

    /**
     * 输出SelectHtml
     * @param array $dict
     * @param string $selectedValue
     * @return string select选择器的HTML
     */
    final function outSelectHtml(array $dict,$selectName,$selectedValue){
        $outHtml ='<select name="'.$selectName.'" id="'.$selectName.'" class="form-control input-small inline">';
        $outHtml .='<option value="">请选择</option>';
        if(!empty($dict)){
            foreach ($dict as $key => $value){
                if(count($value) ==2){
                    $isSelected='';
                    if($value[0] == $selectedValue){
                        $isSelected =' selected = "selected" ';
                    }
                    $outHtml .='<option '.$isSelected . ' value="'.$value[0].'">'.$value[1].'</option>';
                }
            }
        }
        $outHtml .='</select>';
        return $outHtml;
    }

    /**
     * 获取订单数据
     */
    final function getOrderList(){
        $page = input('page');
        if(!$page){
            $page = 1;
        }
        $data = request()->post();
        //判断是否为分页
        if(!$data){
            $data = input();
            if(isset($data["order_status"])){
                $data['OrderStauts'] = $data["order_status"];
            }
            if(isset($data["order_type"])){
                $data['OrderType'] = $data["order_type"];
            }
            if(isset($data["is_cod"])){
                $data['COD_order'] = $data["is_cod"];
            }
            if(isset($data["lock_status"])){
                $data['Lock'] = $data["lock_status"];
            }
            // if(isset($data["pay_channel"])){
            //     $data['paymentMethod_name'] = $data["pay_channel"];
            // }
            if(isset($data["logistics_provider"])){
                $data['ShippingMethod'] = $data["logistics_provider"];
            }
            if(isset($data["payment_status"])){
                $data['PaymentStatus'] = $data["payment_status"];
            }
            if(isset($data["fulfillment_status"])){
                $data['FulfillmentStatus'] = $data["fulfillment_status"];
            }
            if(isset($data["store_name"])){
                $data['store_name'] = $data["store_name"];
            }
            if(isset($data["store_id"])){
                $data['store_id'] = $data["store_id"];
            }
            if(isset($data["UserID"])){
                $data['UserID'] = $data["UserID"];

            }
            if(!empty($data["ShippingMethod"])){
                $data['ShippingMethod'] = $data["ShippingMethod"];
            }
            if(!empty($data["order_from"])){
                $data['order_from'] = $data["order_from"];
            }
            if(!empty($data["payment_system"])){
                $data['payment_system'] = $data["payment_system"];
            }
            if(!empty($data['currency_code'])){
                $data['currency_code'] = $data["currency_code"];
            }

        }
        foreach ((array)$data as $key => $value){
            if(is_array($data[$key]) && empty($data[$key])){
                unset($data[$key]);
            }else if(!is_array($data[$key]) && trim($data[$key]) ==''){
                unset($data[$key]);
            }
        }
        $resultTime = true;
        if(empty($data['OrderNumber']) && empty($data['TrackingNumber']) && empty($data['ThirdPartyTxnID'])){
            if(!empty($data["startTime"]) && !empty($data["endTime"])){
                $resultTime =  $this->TimeDetection($data["startTime"],$data["endTime"]);//时间限制验证
                // $data['orderBy'] = 'create_on desc';
            }else{
                /*默认传入查询三个月内时间参数*/
                $data['startTime'] = $startTime = date("Y-m-d H:i:s",strtotime('-3 month'));
                $data['endTime'] = $endTime   = date("Y-m-d H:i:s",time()-1);
                $this->assign("startTime",$startTime);
                $this->assign("endTime",$endTime);
            }
        }
        if($resultTime === true){
            $data['page'] = $page;
            $data['page_size'] = config('paginate.list_rows');
            $data['path'] ='/Order/index';
            $data['orderBy'] = 'create_on desc';
            // if(!empty($data['paymentMethod_name'])){
            //     $data['paymentMethod_name'] = str_replace(array('Boleto-','Transfer-'),array('',''),$data['paymentMethod_name']);
            // }
            $result = BaseApi::getOrderListForPage($data);
            if(!empty($result) && !empty($result["data"]["data"])){
                $data['page'] = $page;
                $this->assign(['orderList'=>$result["data"]["data"],
                    'page'=>$result["data"]["Page"],
                    'total'=>$result["data"]["total"],
                ]);
            }
        }else{
            $result = json_decode($resultTime,true);
            $this->assign(['error'=>$result["data"],]);
        }
	}
	/*
	 *  订单查询时间的限制
	 *  @author wang   2018-09-13
	 */
	public  function  TimeDetection($startTime,$endTime){
        //只能查询当前到三年前的时间，同时每次查询起始间隔为三个月
        if(!empty($startTime) && !empty($startTime)){
            $minTime = strtotime('-3 year');
            $startTime = strtotime($startTime);
            $endTime   = strtotime($endTime);
            if($startTime < $minTime || $endTime < $minTime){
                return json_encode(array('code'=>100,'data'=>'只能查询三年内的数据'));
            }else if($startTime>$endTime){
                return json_encode(array('code'=>100,'data'=>'结束时间必须大于开始时间'));
            }else {
                //获取三个月后的时间戳
                $intervalTime = strtotime("-3 month",$endTime );
                if($startTime < $intervalTime){
                    return json_encode(array('code'=>100,'data'=>'时间间隔相差超过三个月'));
                }
            }
        }
        return true;
    }
    /**
     * 展示所有子订单
     * [subset description]
     * @return [type] [description]
     *  @author wang   2018-09-13
     */
    public function subset($id=''){
       $data['orderNumber']= $id;
       $data['subset']     = true;
       $result = BaseApi::getOrderDetail($data);
       $this->assign(['orderDetail'=>$result["data"],]);
       return View();
    }

    /**
     * 获取更改订单状态权限相关配置
     * tinghu.liu 20190702
     * @return array
     */
    private function haveUpdateOrderStatusAuth(){
        $user_id = Session::get('userid');
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
	 * 订单管理--查看、编辑
	 * @param string $id(订单编码)
	 * @return \think\response\View
	 */
	public function edit($id=''){
		$data['orderNumber']= $id;
        $data['subset']     = false;
		$result = BaseApi::getOrderDetail($data);
        $remarks = [];
		//die();
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
			//收货地址信息
			// $shippedAddress = '';dump($result["data"]["address"]);
			// if($result["data"]["address"]['first_name'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['first_name'];
			// }

			// if($result["data"]["address"]['last_name'] !=''){
			// 	$shippedAddress .=' '.$result["data"]["address"]['last_name'];
			// }
			// if($result["data"]["address"]['mobile'] !=''){
			// 	$shippedAddress .=','. $result["data"]["address"]['mobile'];
			// }elseif($result["data"]["address"]['phone_number'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['phone_number'].',';
			// }
			// if($result["data"]["address"]['country'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['country'].',';
			// }
			// if($result["data"]["address"]['state'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['state'].',';
			// }
			// if($result["data"]["address"]['city'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['city'].',';
			// }
			// if($result["data"]["address"]['street1'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['street1'].',';
			// }
			// if($result["data"]["address"]['street2'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['street2'].',';
			// }
			// if($result["data"]["address"]['postal_code'] !=''){
			// 	$shippedAddress .=$result["data"]["address"]['postal_code'].',';
			// }
			// //如果最后一个字符是,则截取
			// if(substr($shippedAddress, -1)==','){
			// 	$shippedAddress = substr($shippedAddress,0,strlen($shippedAddress)-1);
			// }

			//dump($result["data"]['order_status_change']);
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
					$paymentTime=date('Y-m-d H:i:s', $paymentStatusRow['create_on']);
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
					$shippingTime=date('Y-m-d H:i:s',$shippingRow['create_on']);
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
            //$OrderRefunWhere['status'] =  3;
            $OrderRefundInfo = BaseApi::getOrderRefundList($OrderRefunWhere);
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
            $OrderRefunOperation = BaseApi::getOrderRefundOperation($OrderRefunOperationWhere);
            if ($OrderRefunOperation['code'] == 200){
                $result["data"]['order_refun_operation'] = $OrderRefunOperation['data'];
            }

            if(!empty($result["data"]['order_id'])){
                $remarks = Db::name(ORDER_REMARKS)->where(['order_id'=>$result["data"]['order_id']])->find();
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
            $group_id = session("group_id");
            $UserNewOrderMessageDataWhere['order_id'] = $result["data"]['order_id'];
            $UserNewOrderMessageDataWhere['message_type'] = 2;//dump($result["data"]);
            $UserNewOrderMessageData = model("OrderMessage")->getUserNewOrderMessageData($UserNewOrderMessageDataWhere);
            $message_template_where['order_status'] = $result["data"]['order_status'];
            $order_message_template = (new OrderMessageTemplateModel())->getOrderMessageTemplate($message_template_where);
            if(!empty($order_message_template)){
                foreach ($order_message_template as $key=>$value){
                    $order_message_template[$key]['content_en'] = replaceContent($value['content_en'],$result["data"]);
                }
            }

            //获取数据
            //重新获取订单对应的RMA数据，用于前端展示，全部显示 tinghu.liu 20191129
            $rma_order_list = '';
            $order_model = new OrderModel();
            $rma_order_list = $order_model->getRmaOrderInfoByOrderId($result["data"]["order_id"]);

            $order_from_data = [10=>"PC",20=>"Android",30=>"IOS",40=>"Pad",50=>"Mobile"];
		   $this->assign(['orderDetail'=>$result["data"],'paymentGreenStyle'=>$paymentGreenStyle,
					       'paymentTime'=>$paymentTime,'paymentGraybgStyle'=>$paymentGraybgStyle,
					       'shippingGreenStyle'=>$shippingGreenStyle,'shippingGraybgStyle'=>$shippingGraybgStyle,
					       'shippingTime'=>$shippingTime,'confirmShippingGreenStyle'=>$confirmShippingGreenStyle,
					       'confirmShippingGraybgStyle'=>$confirmShippingGraybgStyle,
                           'dx_mall_img_url'=>config('dx_mall_img_url'),
                           'dx_url'=>config('dx_url'),
                           'remarks'=>$remarks,
                            'admin_user'=>$admin_user,
                            'group_id'=>$group_id,
                            'UserNewOrderMessageData'=>$UserNewOrderMessageData,
                            'order_message_template'=>$order_message_template,
                            'order_from_data'=>$order_from_data,
                            //修改订单状态相关参数 tinghu.liu 20190702
                            'update_order_status_arr'=>$this->haveUpdateOrderStatusAuth(),
                            'order_status_all_data'=>$order_status_all_data,
                            'rma_order_list'=>$rma_order_list,
			              ]);
		}
		return View();
	}

    /**
     * 修改订单状态
     * tinghu.liu 20190702
     * @return \think\response\Json
     */
	public function updateOrderStatusSubmit(){
	    $rtn = ['code'=>100, 'msg'=>'修改失败'];
        if (!request()->isPost()){
            $rtn['msg'] = '非法访问';
            return json($rtn);
        }
        $param = request()->post();
        //验证数据 order_id=259176&from_status=100&to_status=400&reason=qwerfasfd
        $validate = $this->validate(
            $param,
            [
                ['order_id','require|integer','订单ID错误|订单ID错误'],
                ['from_status','require|integer','当前订单状态错误|当前订单状态错误'],
                ['to_status','require|integer','请选择订单状态|请选择订单状态'],
                ['reason','require', '请填写原因']
            ]
        );
        if(true !== $validate){
            $rtn['msg'] = $validate;
            return json($rtn);
        }
        try{
            $order_id = $param['order_id'];
            $from_status = $param['from_status'];
            $to_status = $param['to_status'];
            if ($from_status == $to_status){
                $rtn['msg'] = '改变后的状态和当前状态一致';
                return json($rtn);
            }
            $config = $this->haveUpdateOrderStatusAuth();
            if (
                $config['user_auth'] != 1
                || !in_array($from_status, $config['from_limit'])
                || !in_array($to_status, $config['to_limit'])
            ){
                $rtn['msg'] = '没有操作权限';
                return json($rtn);
            }
            //调用修改接口
            $_param['order_id'] = $order_id;
            $_param['order_status_from'] = $from_status;
            $_param['order_status'] = $to_status;
            $_param['change_reason'] = $param['reason'];
            $_param['create_on'] = time();
            $_param['create_by'] = 'Admin Manual:'.Session::get("username").'('.Session::get("userid").')';
            $_param['create_ip'] = $_SERVER['REMOTE_ADDR'];
            $_param['chage_desc'] = CommonLib::getOrderStatusChangeReasonStr($to_status);
            $result = BaseApi::OrderShutDown($_param);
            //判断修改结果
            if(!empty($result['code']) && $result['code'] == 200){
                $rtn['code'] = 200;
                $rtn['msg'] = '修改成功';
                return json($rtn);
            }else{
                Log::record('订单状态修改失败：'.json_encode($result), Log::NOTICE);
                $rtn['msg'] = '数据修改失败';
                return json($rtn);
            }
        }catch (Exception $e){
            $msg = '操作异常：'.$e->getMessage();
            Log::record($msg, Log::NOTICE);
            $rtn['msg'] = $msg;
            return json($rtn);
        }
    }

	/**
	 * 过滤查找
	 */
	final static function filterArrayByKey($input,$key,$val,$key1=null,$val1=null){
		$retArray = array_filter($input, function($t) use ($key,$val,$key1,$val1){
			if($t[$key] == $val){
				if(!is_null($key1)){
					return $t[$key1] == $val1;
				}
				return $t[$key] == $val;
			}
		});
		if(count($retArray)== count($retArray, 1)){
			return $retArray;
		}else{
			return array_shift($retArray);
		}
	}


	/**
	 * 获取字典配置的某项值的文本
	 */
	final function getDictValue(array $dict,$currentVale){
		$result ='';
		if(!empty($dict)){
			foreach ($dict as $key => $value){
				if(count($value) ==2){
					if($value[0] == $currentVale){
						$result = $value[1];
					}
				}
			}
		}
		return $result;
	}


    /**
     * 订单物流详情
     */
    public function logisticsDetail(){
        // $tracking_number = input('tracking_number');
        // $order_id = input('order_id');
        // if (
        //     empty($order_id) || !is_numeric($order_id) || $order_id < 0
        // ){
        //     $this->error('错误访问', url('Orders/all'));
        // }

        $tracking_number = 'RE404099797SE';
        //根据物流编号，获取物流详情
        // $service = new WcfService();

        $params = array(
            'GetPackageTrace' => array(
                'request' => array(
                    'HasAll' => true,
                    'TrackingNos' => array(
                        //RI256778026CN
                        'string'=>array(
                            $tracking_number,
                        )
                    )
                )
            )
        );
        // $detail = $service->lisServiceSoap('GetPackageTrace', $params);
        $detail = $this->lisServiceSoap($function_name, $params);

//        print_r($detail);die;
        $package_trace_list = isset($detail['GetPackageTraceResult']->PackageList->Package) && !empty($detail['GetPackageTraceResult']->PackageList->Package)?$detail['GetPackageTraceResult']->PackageList->Package->PackageTraceList->PackageTrace:'';
//        print_r($detail);
//        print_r($package_trace_list);
        //获取订单信息
        $order_info = Order::getOrderInfoByOrderId($order_id);
//        print_r($order_info);
        $this->assign('package_trace_list',$package_trace_list);
        $this->assign('order_info',$order_info);
        $this->assign('title','订单物流详情');
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
    }

	public function lisServiceSoap($function_name, $params){
        $wsdl_url_config = config('wsdl_url');
        $wsdl = $wsdl_url_config['lis_service_wsdl']['url'];
        $opts = $wsdl_url_config['lis_service_wsdl']['options'];
        $user_name = $wsdl_url_config['lis_service_wsdl']['user_name'];
        $password = $wsdl_url_config['lis_service_wsdl']['password'];
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);exit;
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            return $e;
        }
    }

    public function orderStatus(){
        $status   = input('status');
        $order_id = input('order_id');
        $order_number = input('order_number');
        if($status && $order_id){
        	$data['data']['order_id'] = $order_id;
            $data['data']['order_number'] = $order_number;
        	if($status == 60){
                 $before_fixing = '60:解锁';
                 $after_modification = '73:锁定';
                 $data['data']['status'] = 73;
        	}else if($status == 73){
                 $before_fixing = '73:锁定';
                 $after_modification = '60:解锁';
        		 $data['data']['status'] = 60;
        	}else{
                 echo json_encode(array('code'=>100,'data'=>'参数传递有误'));exit;
            }
        	$result = BaseApi::orderStatus($data);
            $log = [
                   'type'=>self::public_log,
                   'before_fixing'=>$before_fixing,
                   'after_modification'=>$after_modification,
                   'result'=>!empty($result["msg"])?$result["msg"]:'',
                   'remarks'=>'订单order_id：'.$order_id.',订单号：'.$order_number
                  ];
            $log = AdminLog($log);
            //记录日志
            AdminInsert(self::public_log,$log);
            echo json_encode($result,true);exit;
        }else{
            echo json_encode(array('code'=>100,'data'=>'获取参数失败'));exit;
        }


    }
    /**
     * 投诉管理
     * [order_accuse description]
     * @return [type] [description]
     * @author wang   2018-06-23
     */
    public function orderAccuse(){

        if($page = input('page')){
            $data['page'] = $page;
        }else{
            $data['page']      = 1;
        }
        $where = request()->post();
        //用于是否翻页
        if(!$where){
          $where = $_GET;
        }
        if($where){
             if(!empty($where['accuse_number'])){
                  $data['accuse_number']    = $where['accuse_number'];
             }
             if(!empty($where['order_number'])){
                  $data['order_number']     = $where['order_number'];
             }
             if(!empty($where['customer_name'])){
                  $data['customer_name']    = $where['customer_name'];
             }
             if(!empty($where['store_name'])){
                  $data['store_name']       = $where['store_name'];
             }
             if(!empty($where['accuse_status'])){
                  $data['accuse_status']    = $where['accuse_status'];
             }
             if(!empty($where['startTime']) && !empty($where['endTime'])){
                 $data['startTime'] = strtotime($where['startTime']);
                 $data['endTime']   = strtotime($where['endTime']);

             }
             if(!empty($where['PlaceAnOrderStartTime']) && !empty($where['PlaceAnOrderEndTime'])){
                 $data['PlaceAnOrderStartTime'] = strtotime($where['PlaceAnOrderStartTime']);
                 $data['PlaceAnOrderEndTime']   = strtotime($where['PlaceAnOrderEndTime']);
             }

        }
        //获取后台配置的付款情况
        $OrderComplaintStatus     = $this->dictionariesQuery('OrderComplaintStatus');
        $OrderComplaintStatusHtml = $this -> outSelectHtml($OrderComplaintStatus,'accuse_status',$where['accuse_status']);

        $data['page_size'] = config('paginate.list_rows');
        $result = BaseApi::orderAccuse($data);
        ///orderfrontend/OrderQuery/orderAccuse  str_replace("world","Shanghai","Hello world!");
        $CharacterString = '';
        unset($data['page'],$data['page_size']);
        if(!empty($data)){
             foreach ($data as $key => $value) {
                   if($CharacterString == ''){
                       $CharacterString  = $key.'='.$value.'&';
                   }else{
                       $CharacterString .= $key.'='.$value.'&';
                   }
             }
        }
        $apiConfig = BaseApi::apiConfig();//获取api  退款状态等信息

        foreach ((array)$result['data'] as $k => $v) {
             foreach ((array)$apiConfig["accuse_reason"] as $ke => $va) {
                if($v["accuse_reason"] == $va["code"]){
                    $result['data'][$k]["accuse_reason"] = $va["name"];
                    break;
                }
             }
             foreach ((array)$OrderComplaintStatus as $keComplaintStatus => $vaComplaintStatus) {
                if($v['accuse_status'] == $vaComplaintStatus[0]){
                    $result['data'][$k]["accuse_status"] = $vaComplaintStatus[1];
                    break;
                }
             }
             $add_time[$k]  = $v["add_time"];
             $result['data'][$k]['imgs'] = json_decode(str_replace(array('.jpg','.png'),array('_70x70.jpg','_70x70.png'),$v['imgs']),true);

        }

        $dx_mall_img_url_brand = config('dx_mall_img_url_brand');
        if(!empty($result['data'])){
            array_multisort($add_time, SORT_DESC,$result['data']);
        }

        $this->assign(['list'=>$result['data'],'page'=>str_replace("/orderfrontend/OrderQuery/orderAccuse?","/Order/orderAccuse?$CharacterString",$result['page']),'OrderComplaintStatusHtml'=>$OrderComplaintStatusHtml,'dx_mall_img_url_brand'=>$dx_mall_img_url_brand,]);
        return View();
    }
    /**
     * 退款申请
     * [orderRefund description]
     * @return [type] [description]
     * @author wang   2018-06-25
     */
    public function orderRefund(){

        if($page = input('page')){
            $data['page'] = $page;
        }else{
            $data['page']      = 1;
        }
        $where = request()->post();
        //用于是否翻页
        if(!$where){
          $where = $_GET;
        }
        if($where){
             if(!empty($where['after_sale_number'])){
                $data['after_sale_number'] = trim($where['after_sale_number']);
             }
             if(!empty($where['order_number'])){
                $data['order_number']      = trim($where['order_number']);
             }
             if(!empty($where['customer_name'])){
                $data['customer_name']     = trim($where['customer_name']);
             }
             if(!empty($where['store_name'])){
                $data['store_name']        = trim($where['store_name']);
             }
             if(!empty($where['after_sale_status'])){
                $data['after_sale_status'] = trim($where['after_sale_status']);
             }
             if(!empty($where['refunded_type'])){
                $data['refunded_type']     = trim($where['refunded_type']);
             }
            if(!empty($where['type'])){
                $data['type']     = trim($where['type']);
            }
             if(!empty($where['startTime']) && !empty($where['endTime'])){
                 $data['startTime'] = trim(strtotime($where['startTime']));
                 $data['endTime']   = trim(strtotime($where['endTime']));
             }
             if(!empty($where['PlaceAnOrderStartTime']) && !empty($where['PlaceAnOrderEndTime'])){
                 $data['PlaceAnOrderStartTime'] = trim(strtotime($where['PlaceAnOrderStartTime']));
                 $data['PlaceAnOrderEndTime']   = trim(strtotime($where['PlaceAnOrderEndTime']));
             }

        }
        //获取退款状态和退款类型
        $apiConfig = BaseApi::apiConfig();//api配置文件订单数据
        $data['page_size'] =config('paginate.list_rows');
        $result    = BaseApi::orderRefund($data);
        if (isset($result['data']) && !empty($result['data'])){
            foreach ((array)$result['data'] as $kinfo => $info){
                $type = $info['type'];
                $type_str = '-';
                $refunded_type = $info['refunded_type'];
                $refunded_type_str = '-';
                //退款类型
                foreach ((array)$apiConfig['after_sale_type'] as $val){
                    if ($type == $val['code']){
                        $type_str = $val['name'];
                        if ($type == 3){
                            //退款类型
                            foreach ($val['refunded_type'] as $rval){
                                if ($refunded_type == $rval['code']){
                                    $refunded_type_str = $rval['name'];
                                }
                                break;
                            }
                        }
                        break;
                    }
                }
                $result['data'][$kinfo]['type_str'] = $type_str;
                $result['data'][$kinfo]['refunded_type_str'] = $refunded_type_str;
                //退款图片处理
                $result['data'][$kinfo]['imgs'] = json_decode(htmlspecialchars_decode($info['imgs']), true);
                foreach ((array)$apiConfig['accuse_reason'] as $k => $v) {
                    if($info["after_sale_reason"] == $v["code"]){
                        $result['data'][$kinfo]["after_sale_reason"] = $v["name"];
                    }
                }
            }
        }
        //print_r($result);
        //用于翻页
        $CharacterString = '';
        unset($data['page'],$data['page_size']);
        if(!empty($data)){
            foreach ((array)$data as $key => $value) {
                if($CharacterString == ''){
                    $CharacterString  = $key.'='.$value.'&';
                }else{
                    $CharacterString .= $key.'='.$value.'&';
                }
            }
        }
        //dump($result['data']);
        $this->assign('url',json_encode([
            'Orders'=>url('Order/export'),
        ]));
        $this->assign(['list'=>$result['data'],'page'=>str_replace("/orderfrontend/OrderQuery/orderRefund?","/Order/orderRefund?$CharacterString",$result['page']),'apiConfig'=>$apiConfig,'where'=>$where]);
        return View();
    }

        /**
         * 普通退款申请
         * [orderRefund description]
         * @return [type] [description]
         * @author wang   2018-06-25
         */
        public function orderRefundList(){
            $baseApi = new BaseApi();
            $SellerLists = $baseApi::getStoreLists(['status'=>1]);
            $seller_data = isset($SellerLists['data'])?$SellerLists['data']:'';
            $refund_status = [1=>'申请待处理',2=>'退款成功',3=>'退款失败',4=>'退款审核失败'];
            $param_data = input();
            $where = array();
            $where['page_size']= input("page_size",20);
            $where['page'] = input("page",1);
            $where['path'] = url("Order/orderRefundList");
            $where['page_query'] = input();
            if(!empty($param_data['order_number'])){
                $where['order_number'] = trim($param_data['order_number']);
            }
            if(isset($param_data['customer_name']) && !empty($param_data['customer_name'])){
                if(is_numeric($param_data['customer_name'])){
                    $where['customer_id'] = trim($param_data['customer_name']);
                }else{
                    $where['customer_name'] = $param_data['customer_name'];
                }
            }
            if(!empty($param_data['store_id'])){
                $where['store_id'] = trim($param_data['store_id']);
            }
            if(!empty($param_data['status'])){
                $where['status'] = trim($param_data['status']);
            }
            if(!empty($param_data['startTime']) && !empty($param_data['endTime'])){
                $where['add_time'] = ['BETWEEN',[strtotime($param_data['startTime']),strtotime($param_data['endTime'])]];
            }else{
                if(isset($param_data['startTime']) && !empty($param_data['startTime'])){
                    $where['add_time'] = strtotime($param_data['startTime']);
                }
                if(isset($param_data['endTime']) && !empty($param_data['endTime'])){
                    $where['add_time'] = strtotime($param_data['endTime']);
                }
            }
            $result    = BaseApi::getAdminOrderRefundList($where);
            if (isset($result['data']) && !empty($result['data'])){
                $result_data = $result['data'];
                foreach ($result_data['data'] as $kinfo => &$info){
                    //售后图片处理
                    $info['imgs'] = json_decode(htmlspecialchars_decode($info['imgs']), true);
                }
            }

        //dump($result['data']);exit;
        $this->assign('url',json_encode([
            'Orders'=>url('Order/refundExport'),
        ]));
        $this->assign(['list'=>$result_data['data'],'page'=>$result_data['Page'],'seller_data'=>$seller_data,'refund_status'=>$refund_status]);
        return View();
    }

    /**
     * 售后详情
     * [afterSaleDetails description]
     * @return [type] [description]
     * @author wang   2018-06-27
     */
    public function afterSaleDetails(){
       $after_sale_number = input('after_sale_number');
       $data['after_sale_number'] = $after_sale_number;
       //获取售后状态和售后退款类型
       $apiConfig = BaseApi::apiConfig();//获取api  退款状态等信息
       $result    = BaseApi::afterSaleDetails($data);
       // $apiConfig = BaseApi::apiConfig();//
       //订单类型
       $orderTypeDict = $this->dictionariesQuery('OrderType');
       $orderType = $this->getDictValue($orderTypeDict,$result['order_type']);

       foreach ($apiConfig["after_sale_type"][2]["reason"] as $key => $value) {
          if($value["status"] = $result["status"]){
              $result['status_name'] = $value["name"];
              break;
          }
       }
       foreach ($apiConfig["after_sale_type"] as $k => $v) {
          if($v["code"] == $result["refunded_type"]){
               $result['refunded_type_name'] = $v["name"];
               break;
          }
       }
       $this->assign(['list'=>$result,'after_sale_status'=>$apiConfig["after_sale_status"],'orderType'=>$orderType]);
       return View();
    }


    /**
     * 退款详情
     * [afterSaleDetails description]
     * @return [type] [description]
     * @author kevin   2019-0509
     */
    public function refundInfo(){
        $refund_number = input('refund_number');
        $data['refund_number'] = $refund_number;
        $result    = BaseApi::getAdminOrderRefundInfo($data);
        //订单类型
        $orderTypeDict = $this->dictionariesQuery('OrderType');
        $orderType = $this->getDictValue($orderTypeDict,$result['order_type']);
        $refund_status = [1=>'申请待处理',2=>'退款成功',3=>'退款失败'];
        $this->assign(['list'=>!empty($result['data'])?$result['data']:'','orderType'=>$orderType,'refund_status'=>$refund_status]);
        return View();
    }

    /**
     * 风控管理
     * @author wang   2018-08-03
     */
    public function RiskManagement(){
         $riskConfig = BaseApi::RiskConfig();//api配置文件订单数据
         if($data = request()->post()){
            if($data['customer_name']){
                $where['customer_name'] = $data['customer_name'];
            }
            if($data['seller_name']){
                $where['seller_name']   = $data['seller_name'];
            }
            if($data['customer_id']){
                $where['customer_id']   = $data['customer_id'];
            }
            if($data['report_status']){
                $where['report_status'] = $data['report_status'];
            }
            if($data['seller_id']){
                $where['seller_id']     = $data['seller_id'];
            }
            if($data['startTime'] && $data['endTime']){
                $where['add_time'] =  array(array('egt',strtotime($data['startTime'])),array('elt',strtotime($data['endTime'])));
            }
            Cache::set('RiskManagement', $where,3600);

         }
         $status = input('status');
         if(!$where && $status){
             $where = Cache::get('RiskManagement');
         }
         if($where){
              $list = Db::name(REPORTS)->where($where)->order('add_time asc')->paginate(20);
              $page = str_replace("page","status=1&page",$list->render());
         }else{
              $list = Db::name(REPORTS)->order('add_time asc')->paginate(20);
              $page = $list->render();
         }
         $report_status = $data['report_status']?$data['report_status']:($where['report_status']?$where['report_status']:'');
         $list_items = $list->items();
         $statusSelectHtml = $this->statusSelect($riskConfig["data"]['report_status'],'report_status',$report_status);
         foreach ((array)$list_items as $key => $value) {
            foreach ((array)$riskConfig["data"]['report_status'] as $k => $v) {
                if((array)$value["report_status"] == $v["code"]){
                     $list_items[$key]["report_name"] = $v["name"];
                }
            }
         }

         $this->assign(['list'=>$list_items,'page'=>$page,'statusSelectHtml'=>$statusSelectHtml]);
        //dx_reports
        return View();
    }
    /**
     * 遍历风控状态
     * [statusSelect description]
     * @return [type] [description]
     * @author wang   2018-08-04
     */
    public function statusSelect($data=array(),$selectId='',$status){
       $html  = '';
       $select = '';
       $html .= '<select name="'.$selectId.'" id="'.$selectId.'" class="form-control input-small inline">';
       $html .= '<option value="">请选择</option>';
       foreach ((array)$data as $key => $value) {
          if($status == $value["code"]){
               $select = 'selected = "selected"';
          }
          $html .=  '<option '.$select.' value="'.$value["code"].'">'.$value["name"].'</option>';
          $select = '';
       }
        $html .= '</select>';
        return $html;
    }

    /**
     * 仲裁管理
     * [arbitration description]
     * @return [type] [description]
     * @author wang   2018-08-18
     */
    public function arbitration(){


        echo '尚未完全做完';
        echo '<br/>';
        echo '<br/>';
        echo '<br/>';
        exit;
        $apiConfig   = BaseApi::apiConfig();
        $arbitration = BaseApi::arbitration($data);
        foreach ((array)$arbitration["data"] as $key => $value) {
             if($value["orimgs"]){
                  $arbitration["data"][$key]["orimgs"] = json_decode($value["orimgs"]);
             }
        }
        $this->assign(['list'=>$arbitration["data"],'page'=>$page,'apiConfig'=>$apiConfig,'dx_mall_img_url'=>config('dx_mall_img_url'),]);
        return View();
    }
    /**
     * type: 1换货，2退货 3退款
     * user_type: 1买家 2卖家 3后台
     * after_sale_status:7拒绝，通过
     * [arbitrationManage description]
     * @return [type] [description]
     */
    public function arbitrationManage(){
           $data = request()->post();
           if($data){
             if(!$data['id'] || !$data['user_type'] || !$data['type']){
                 echo json_encode(array('code'=>100,'result'=>'获取对应订单数据失败请刷新重试'),true);exit;
             }
             if(!$data['status']){
                 echo json_encode(array('code'=>100,'result'=>'请选择仲裁结果'),true);exit;
             }
             if(!$data['content']){
                 echo json_encode(array('code'=>100,'result'=>'内容不能为空'),true);exit;
             }
             $where['log_type']      = 1;//是否仲裁0,1
             if($data['status'] == 7){
                 $where['title']         = '拒绝申请';
             }else if($data['status'] == 10){
                 $where['title']         = '仲裁通过';
             }


             if($data['type'] == 1){
                     if($data['user_type'] == 1){
                            if($data['status'] == 7){

                            }else if($data['status'] == 10){

                            }
                     }else if($data['user_type'] == 2){

                     }
             }else if($data['type'] == 2){

             }else if($data['type'] == 2){

             }

             $where['after_sale_id'] = $data['id'];
             $where['user_id'] = $data['user_id'];
             $where['user_name'] = $data['user_name'];
             // $where['status']  = $data['status'];//结果
             $where['content'] = $data['content'];//内容

             //dx_order_after_sale_apply_log//APPLY_LOG
             $applyLog = BaseApi::applyLog($where);
// dump($applyLog);exit;
             if($applyLog["code"] == 200){
                // $result = Db::name('order_after_sale_apply_log')->insert($where);
                echo json_encode(array('code'=>200,'result'=>'数据提交成功'),true);exit;
             }else{
                echo json_encode(array('code'=>100,'result'=>'数据提交失败'),true);exit;
             }
             //dx_order_after_sale_apply_log
             // $where['after_sale_id'] = $data['id'];
           }else{
                $id        = input('id');
                $user_type = input('user_type');
                $user_id   = input('user_id');
                $user_name = input('user_name');
                $type      = input('type');
                $apiConfig = BaseApi::apiConfig();
                // dump($apiConfig['after_sale_status']);
                // dump($data);
                $this->assign(['after_sale_status'=>$apiConfig['after_sale_status'],
                    'id'=>$id,
                    'user_type'=>$user_type,
                    'user_id'=>$user_id,
                    'user_name'=>$user_name,
                    'type'=>$type,
                    ]);
                return View();
           }

    }

    /*订单退款*/
    public function retrunOrder(){
        $order_number = input('id');//dump(input());
        if (empty($order_number) || !is_numeric($order_number) || $order_number<=0){
            abort(404);
        }
        $data['orderNumber']= $order_number;//dump($data['orderNumber']);
        $data['subset']     = false;
        $result = BaseApi::getOrderDetail($data);
        if ($result['code'] != 200){
            abort(404);
        }
        $order_info = [];
        if (isset($result['data']) && !empty($result['data'])){
            $order_info = $result['data'];
            //订单状态
            $order_status_str = BaseApi::getOrderStatus($order_info['order_status']);
            $order_info['order_status_str'] = isset($order_status_str['name'])?$order_status_str['name']:'-';
            //币种
            $order_info['currency_code_str'] = Base::getCurrencyCodeStr($order_info['currency_code']);

            //如果是ARS-Astropay-USD的需要将退款金额展示为USD，而非ARS（实际退款做判断）............
            if (
                strtolower($order_info['currency_code']) == strtolower('ARS')
                && strtolower($order_info['pay_channel']) == strtolower('Astropay')
                && strtolower($order_info['orderOther']['payment_currency_code']) == strtolower('USD')
            ){
                $order_info['total_amount'] = sprintf("%.2f", $order_info['total_amount']/$order_info['exchange_rate']);
                $order_info['grand_total'] = sprintf("%.2f", $order_info['grand_total']/$order_info['exchange_rate']);
                $order_info['captured_amount'] = sprintf("%.2f", $order_info['captured_amount']/$order_info['exchange_rate']);
                $order_info['currency_code_str'] = Base::getCurrencyCodeStr('USD');
            }
        }
//         dump($order_info);exit;
        $this->assign([
            'order_info'=>$order_info,
            'ajax_url'=>json_encode([
                'async_submitRefund'=>url('order/async_submitRefund'),
            ]),
        ]);
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
    }


    /*订单关闭退款*/
    public function retrunCloseOrder(){
        $order_number = input('id');//dump(input());
        if (empty($order_number) || !is_numeric($order_number) || $order_number<=0){
            abort(404);
        }
        $data['orderNumber']= $order_number;//dump($data['orderNumber']);
        $data['subset']     = false;
        $result = BaseApi::getOrderDetail($data);
        if ($result['code'] != 200){
            abort(404);
        }
        $order_info = [];
        if (isset($result['data']) && !empty($result['data'])){
            $order_info = $result['data'];
            //订单状态
            $order_status_str = BaseApi::getOrderStatus($order_info['order_status']);
            $order_info['order_status_str'] = isset($order_status_str['name'])?$order_status_str['name']:'-';
            //币种
            $order_info['currency_code_str'] = Base::getCurrencyCodeStr($order_info['currency_code']);
        }
        // dump($order_info);exit;
        $this->assign([
            'order_info'=>$order_info,
            'ajax_url'=>json_encode([
                'async_submitRefund'=>url('order/async_submitRefund'),
            ]),
        ]);
        $this->assign('parent_menu','order');
        $this->assign('child_menu','all');
        return $this->fetch();
    }

    /**
     * 提交售后申请数据
     * @return \think\response\Json
     * [
     *  'order_id'=>,
     *  'order_number'=>,
     *  'customer_id'=>, //后端拼
     *  'customer_name'=>, //后端拼
     *  'store_id'=>,
     *  'store_name'=>,
     *  'payment_txn_id'=>,
     *  'type'=>,
     *  'refunded_type'=>,
     *  'after_sale_reason'=>,
     *  'imgs'=>, //申请图片，json数组保存
     *  'remarks'=>, //描述
     *  'refunded_fee'=>, //退款金额（退款时有）-后端拼，根据选择的产品
     *  'captured_refunded_fee'=>, //实际退款金额（退款时有）-后端拼，根据选择的产品
     *  'item'=>[
     *              [
     *                  'product_id'=>,
     *                  'sku_id'=>,
     *                  'sku_num'=>, //产品表sku编码
     *                  'product_name'=>,
     *                  'product_img'=>,
     *                  'product_attr_ids'=>,
     *                  'product_attr_desc'=>,
     *                  'product_nums'=>, //商品（sku）数量
     *                  'product_price'=>, //商品售价（sku单价）
     *              ],
     *      ],
     * ]
     */
    public function async_submitRefund(){
        $rtn = ['msg'=>'', 'code'=>100];
        $param = input();
        $remarks = input("remarks");
        if(strlen($remarks)>200){
            $rtn['msg'] = "描述长度不能超过200个字符！";
            return $rtn;
        }
        //数据校验
        $base_api = new BaseApi();
        //item数据校验
        $param['initiator'] = 3;
        $order_info_api = $base_api::getOrderDetail(['orderNumber'=>$param['order_number']]);
        $refund_id = input("refund_id");
        /*防止订单获取失败后继续提交，20190409 kevin*/
        if(empty($order_info_api) || $order_info_api['code'] != 200){
            $rtn['msg'] = "订单数据错误";
            Log::record('async_refundAllConfirmApply->订单数据错误'.print_r($param, true));
            return json($rtn);
        }
        if(isset($order_info_api['data']['order_master_number']) && $order_info_api['data']['order_master_number'] == 0){
            $rtn['msg'] = "主订单不能退款";
            return json($rtn);
        }

        $_currency_code = $order_info_api['data']['currency_code'];
        $_pay_channel = $order_info_api['data']['pay_channel'];
        $_captured_amount_usd = $order_info_api['data']['captured_amount_usd'];
        $_payment_currency_code = $order_info_api['data']['orderOther']['payment_currency_code'];

        if(empty($refund_id)){
            if($param['captured_refunded_fee']>0 && $order_info_api['data']['captured_amount']>=$param['captured_refunded_fee']){
                $param['refunded_fee'] = $param['captured_refunded_fee'];
            }else{
                if($param['captured_refunded_fee']<=0){
                    $rtn['msg'] = "退款金额不能小于0，并且不能为空和带有空格";
                }

                if($order_info_api['data']['captured_amount']<$param['captured_refunded_fee']){
                    $rtn['msg'] = "退款金额不能为空";
                }

                Log::record('async_refundAllConfirmApply->订单数据错误'.print_r($param, true));
                return json($rtn);
                /*增加实收金额判断，20190409 kevin*/
                /*if(!empty($order_info_api['data']['captured_amount']) && $order_info_api['data']['captured_amount']>0){
                    $param['refunded_fee'] = $param['captured_refunded_fee'] = $order_info_api['data']['captured_amount'];
                }else{
                    $rtn['msg'] = "订单数据错误";
                    Log::record('async_refundAllConfirmApply->订单数据错误'.print_r($param, true));
                    return json($rtn);
                }*/
            }
            $param['applicant_admin_id'] = session("userid");
            $param['applicant_admin'] = session("username");

            //是ARS-Astropay-且实际支付金额为USD，则最大退款金额为实收金额的美元值 tinghu.liu 20191121
            if (
                strtolower($_currency_code) == strtolower('ARS')
                && strtolower($_pay_channel) == strtolower('Astropay')
                && strtolower($_payment_currency_code) == strtolower('USD')
            ){
                if ($param['captured_refunded_fee'] > $_captured_amount_usd){
                    $param['refunded_fee'] = $_captured_amount_usd;
                    $param['captured_refunded_fee'] = $_captured_amount_usd;
                }
            }

            $res = BaseApi::saveOrderRefund($param);
        }else{
            $res = ['code'=>1002,'msg'=>'此订单已经提交退款申请，请等待相关人员处理！'];
        }
        return json($res);
    }
    /**
     * 订单详情备注
     * [AddNotes description]
     */
    public function AddNotes(){

          $where = array();
          if($data = request()->post()){
               if(empty($data['order_id']) || empty($data['message']) ){
                    echo json_encode(array('code'=>100,'data'=>'数据提交失败'),true);exit;
               }

               $where['remarks'] = $data['message'];
               $where['status']  = 1;

               if($data['status'] == 1){
                   $where['edit_user_id']   = !empty(Session::get('userid'))?Session::get('userid'):0 ;
                   $where['edit_user_name'] = Session::get('username');
                   $where['edit_time']  = time();
                   $result = Db::name(ORDER_REMARKS)->where(['order_id'=>$data['order_id'],'status'=>1])->update($where);
                   // echo Db::name(ORDER_REMARKS)->getLastSql();
               }else{
                   $where['order_id']  = $data['order_id'];
                   $where['add_user_id']   = !empty(Session::get('userid'))?Session::get('userid'):0 ;
                   $where['add_user_name'] = Session::get('username');
                   $where['add_time']  = time();
                   $result = Db::name(ORDER_REMARKS)->insert($where);
               }
               if(!empty($result)){
                       echo json_encode(array('code'=>200,'data'=>'数据提交成功'),true);exit;
               }else{
                       echo json_encode(array('code'=>100,'data'=>'数据提交失败'),true);exit;
               }

          }
    }
    /**
     * 获取跟踪号节点
     * [package_number description]
     * @return [type] [description]
     * author  Wang
     * add_time 2018-12-14
     */
    public function LogisticsNode(){
         $package_id = input('package_id');
         $order_id = input("o_id");
         if(!empty($package_id)){
                $where['package_id'] = $package_id;
                if(!empty($order_id)){
                    $where['order_id'] = $order_id;
                }
                $result = BaseApi::AdminLisLogisticsDetail($where);
                /*增加获取物流节点配送地址 20190411 kevin*/
                if(isset($result) && $result["code"] == 200 && !empty($result["data"]['LogisticsDetail']["raw_data"])){
                   $package_trace_list['LogisticsDetail'] = json_decode($result["data"]['LogisticsDetail']["raw_data"],true);
                   if(!empty($result['data']['order_address'])){
                       $package_trace_list['order_address'] = $result['data']['order_address'];
                   }
                }else{
                   $package_trace_list = '';
                }
         }
         $this->assign(['package_trace_list'=>$package_trace_list,]);
         return View();
    }

    /*
    * 退款信息导出
    */
    public function export()
    {
        $where = request()->post();
        //用于是否翻页
        if(!$where){
            $where = $_GET;
        }
        if($where){
            if(!empty($where['after_sale_number'])){
                $data['after_sale_number'] = trim($where['after_sale_number']);
            }
            if(!empty($where['order_number'])){
                $data['order_number']      = trim($where['order_number']);
            }
            if(!empty($where['customer_name'])){
                $data['customer_name']     = trim($where['customer_name']);
            }
            if(!empty($where['store_name'])){
                $data['store_name']        = trim($where['store_name']);
            }
            if(!empty($where['after_sale_status'])){
                $data['after_sale_status'] = trim($where['after_sale_status']);
            }
            if(!empty($where['refunded_type'])){
                $data['refunded_type']     = trim($where['refunded_type']);
            }
            if(!empty($where['startTime']) && !empty($where['endTime'])){
                $data['startTime'] = trim(strtotime($where['startTime']));
                $data['endTime']   = trim(strtotime($where['endTime']));
                if($data['endTime']-$data['startTime']>7948800){
                    $this->error('只能导出3个月的数据');
                }
            }else{
                $this->error('时间不能为空,并且只能导出3个月的数据');
            }
            if(!empty($where['PlaceAnOrderStartTime']) && !empty($where['PlaceAnOrderEndTime'])){
                $data['PlaceAnOrderStartTime'] = trim(strtotime($where['PlaceAnOrderStartTime']));
                $data['PlaceAnOrderEndTime']   = trim(strtotime($where['PlaceAnOrderEndTime']));
                if($data['PlaceAnOrderEndTime']-$data['PlaceAnOrderStartTime']>7948800){
                    $this->error('只能导出3个月的数据');
                }
            }
        }

        $data['page_size'] = 10000;
        $list    = BaseApi::orderRefundExcel($data);
        if(isset($list['data']['data'])&&!empty($list['data']['data'])){
            $list_data= $list['data']['data'];
        }else{
            $this->error('没有数据');
        }
        $da=[];

        foreach ($list_data as $item){
            $da[] = [
                'order_number' => ' '.$item['order_number'],
                'goods_total' => $item['goods_total'],
                'refunded_fee' => $item['refunded_fee'],
                'currency_code' => $item['currency_code'],
                'country' => $item['country'].'('.$item['country_code'].')',
                'store_id' => $item['store_id'],
                'remarks' => $item['remarks'],
                'customer_name' => $item['customer_name'],
                'add_time' => $item['add_time'] ? date('Y-m-d', $item['add_time']) : '',
            ];
        }

        $title = ['订单号', '订单总额', '退款金额', '退款币种', '国家',
            '卖家账号', '退款备注', '退款人', '退款日期'
        ];
        $header_data =[
            'order_number' => '订单号',
            'goods_total' => '订单总额',
            'refunded_fee' => '退款金额',
            'currency_code' => '退款币种',
            'country' =>'国家',
            'store_id' => '卖家账号',
            'remarks' =>'退款备注',
            'customer_name' => '退款人',
            'add_time' => '退款日期',
        ];
        $tool = new ExcelTool();
        return  $tool ->export('退款订单',$header_data,$da);
    }


    /*
* 退款信息导出
*/
    public function refundExport()
    {
        $baseApi = new BaseApi();
        $SellerLists = $baseApi::getStoreLists(['status'=>1]);
        $seller_data = isset($SellerLists['data'])?$SellerLists['data']:'';
        $refund_status = [1=>'申请待处理',2=>'退款成功',3=>'退款失败'];
        $param_data = input();
        $where = array();
        $where['page_size']= input("page_size",1000000);
        $where['page'] = input("page",1);
        $where['path'] = url("Order/orderRefundList");
        $where['page_query'] = input();
        if(!empty($param_data['order_number'])){
            $where['order_number'] = trim($param_data['order_number']);
        }
        if(isset($param_data['customer_name']) && !empty($param_data['customer_name'])){
            if(is_numeric($param_data['customer_name'])){
                $where['customer_id'] = trim($param_data['customer_name']);
            }else{
                $where['customer_name'] = $param_data['customer_name'];
            }
        }
        if(!empty($param_data['store_id'])){
            $where['store_id'] = trim($param_data['store_id']);
        }
        if(!empty($param_data['status'])){
            $where['status'] = trim($param_data['status']);
        }
        if(!empty($param_data['startTime']) && !empty($param_data['endTime'])){
            $where['add_time'] = ['BETWEEN',[strtotime($param_data['startTime']),strtotime($param_data['endTime'])]];
        }else{
            if(isset($param_data['startTime']) && !empty($param_data['startTime'])){
                $where['add_time'] = strtotime($param_data['startTime']);
            }
            if(isset($param_data['endTime']) && !empty($param_data['endTime'])){
                $where['add_time'] = strtotime($param_data['endTime']);
            }
        }
        $list    = BaseApi::getAdminOrderRefundList($where);
        if(isset($list['data']['data'])&&!empty($list['data']['data'])){
            $list_data= $list['data']['data'];
        }else{
            $this->error('没有数据');
        }
        $da=[];

        foreach ($list_data as $item){
            $da[] = [
                'order_number' => ' '.$item['order_number'],
                'grand_total' => $item['grand_total'],
                'refunded_fee' => $item['refunded_fee'],
                'currency_code' => $item['currency_code'],
                'country' => $item['country'].'('.$item['country_code'].')',
                'store_id' => $item['store_id'],
                'remarks' => $item['remarks'],
                'customer_name' => $item['customer_name'],
                'add_time' => $item['add_time'] ? date('Y-m-d', $item['add_time']) : '',
            ];
        }

        $title = ['订单号', '订单总额', '退款金额', '退款币种', '国家',
            '卖家账号', '退款备注', '退款人', '退款日期'
        ];
        $header_data =[
            'order_number' => '订单号',
            'goods_total' => '订单总额',
            'refunded_fee' => '退款金额',
            'currency_code' => '退款币种',
            'country' =>'国家',
            'store_id' => '卖家账号',
            'remarks' =>'退款备注',
            'customer_name' => '退款人',
            'add_time' => '退款日期',
        ];
        $tool = new ExcelTool();
        return  $tool ->export('退款订单',$header_data,$da);
    }
    /*
     * 人工录入包裹信息
     * yxh by 2019-05-25
     */
    public function addTrackingNumber(){
        $post = request()->post();

        $validate=[
            'order_number'  => 'require',
            'tracking_number'   => 'require',
           // 'item_info'   => 'require',
        ];
        $url='/orderfrontend/TrackingNumber/post';
        //erp订单
        if($post['store_id']==$this->store_id || (!empty($post['type']))){
            //签名校验
            $sign_flag = $this->sign_flag.$post['store_id'].date('Y-m-d');
            $Token=new Token();

            $post['sign']= $Token->makeSign($sign_flag);//签名
            $url='/orderfrontend/TrackingNumber/syncPost';
            $validate['shipping_channel_name']='require';
        }else{
            unset($post['store_id']);
        }

        //参数校验
        $result = $this->validate($post,$validate);
        if(true !== $result){
            // 验证失败 输出错误信息
            return json(array('code'=>100,'msg'=>$result));
        }

        if(empty($post['type'])) {
            foreach ($post['item_info'] as $value) {
                if (!in_array($value['sku_id'], $post['sku_code'])) {
                    return json(array('code' => 100, 'msg' => 'SKU不存在本订单中'));
                }

                if (!is_numeric($value['sku_qty']) || $value['sku_qty'] < 1) {
                    return json(array('code' => 100, 'msg' => '数量请填写数字类型'));
                }
            }
        }
        unset($post['sku_code']);

        $list    = BaseApi::addTracking($url,$post);

        $post['return_msg']=json_encode($list);
        $post['operator_name'] = Session::get('username');
        $post['operator_id']  = Session::get('userid');
        $logisticsLog=new LogisticsLog();
        unset($post['sign'],$post['store_id']);
        unset($post['is_delete']);
        unset($post['shipping_channel_name_cn']);

        $res=$logisticsLog->save($post);
        return json($list);
    }
    /**
     * 退款查询页面
     * [CustomerServiceRefund description]
     */
    public function CustomerServiceRefund(){
        // $param = input();
        // $remarks = input("remarks");dump($param);
        // if(strlen($remarks)>200){
        //     $rtn['msg'] = "描述长度不能超过200个字符！";
        //     return $rtn;
        // }

       return View();
    }
     /**
     * 客服批量退款
     * [CustomerServiceRefund description]
     *
     */
    public function Refund(){
        // header("content-type:text/html;charset=utf-8");
        // echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
       $data = [];
       $order_data_string  = '';
       $order_data = [];
       if($post = request()->post()){
            if(empty($post['type']) || empty($post["OrderNumber"])){
               echo json_encode(array('code'=>100,'data'=>'必填字段不能为空'),true);exit;
            }
            $order_data_string = str_replace(["\n","\r\n","\r","，"],[';',';',';',','],$post["OrderNumber"]);
            $pattern = '/(;)+/i';
            $order_data_string = preg_replace($pattern,';',$order_data_string);
            $order_data = explode(';',$order_data_string);
 // dump($order_data);dump($post);
            if($post['type'] == 1){
                  $result = OrderModel::FullRefund($order_data);
            }else if($post['type'] == 2){
                  $result = OrderModel::SomeSkuRefunds($order_data);//dump($order_data);
            }else if($post['type'] == 3){
                  $result = OrderModel::PartialRefund($order_data);
            }
 // echo  json_encode(array('code'=>200,'data'=>'退款成功'));exit;
           echo $result;exit;

       }else{
           return View();
       }
    }
    /**
     * 关闭订单
     * order_id  订单号
     * order_status_from 更改前状态
     * order_status  更改后状态
     * change_reason 原因
     * create_by  修改所属系统
     * create_ip  修改人IP
     * chage_desc 原因
     * [OrderShutDown description]
     * @auther wang  2019-03-18
     */

    public function OrderShutDown(){
          // echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
          if($data = request()->post()){
                $where = [];
                if(empty($data['order_id'])  || empty($data['order_status'])){
                   echo json_encode(array('code'=>100,'result'=>'获取参数出异常'),true);exit;
                }
                $where['order_id'] = $data['order_id'];
                $where['order_status_from'] = $data['order_status'];
                $where['order_status'] = 1900;
                $where['change_reason'] = 'Close order.';
                $where['create_on'] = time();
                $where['create_by'] = 'Admin System,操作人'.Session::get("username");
                $where['create_ip'] = $_SERVER['REMOTE_ADDR'];
                $where['chage_desc'] = 'Close order.';
                $result = BaseApi::OrderShutDown($where);

                $log = [
                   'type'=>self::public_log,
                   'before_fixing'=>'订单开启',
                   'after_modification'=>'订单关闭',
                   // 'result'=>!empty($result["msg"])?$result["msg"]:'',
                   'remarks'=>'订单order_id：'.$data['order_id']
                ];
                if(!empty($result['code']) && $result['code'] == 200){
                      $log['result'] = 'Success';
                      $log = AdminLog($log);
                      //记录日志
                      AdminInsert(self::public_log,$log);
                      echo json_encode(array('code'=>200,'result'=>'数据提交成功'),true);exit;
                }else{
                      $log['result'] = 'error';
                      $log = AdminLog($log);
                      //记录日志
                      AdminInsert(self::public_log,$log);
                      echo json_encode(array('code'=>100,'result'=>$result['msg']),true);exit;
                }
          }
    }
    /**
     * 获取历史留言信息
     * [HistoryRecordList description]
     */
    public function HistoryRecordList(){
         if($data = request()->post()){
            if(!empty($data['UserID']) && !empty($data['order_id'])){
                // file_put_contents ('../runtime/log/201904/121.log',json_encode($data).',', FILE_APPEND|LOCK_EX);
                $page_size = config('paginate.list_rows');
                $page = !empty($data['page'])?$data['page']:1;
                $list = BaseApi::HistoryRecordList([
                                      'user_id'=>$data['UserID'],
                                      'order_id'=>$data['order_id'],
                                      'page_size'=>$page_size,
                                      'page'=>$page,
                                      'path'=>'/Order/HistoryRecordList'
                                      ]);
            return $list;
            }
         }
    }


    /**/
	
	    /*
     * 临时方法修复APP数据
     */
    public function test(){
        $OrderModel=new OrderModel();
        $sql="SELECT `order_id` FROM `dx_sales_order` WHERE `order_from` = '20' AND `transaction_id`>50000000  AND `payment_system`!=2";
        $order_id = $OrderModel->orderSql($sql);
        if($order_id){
            $order_id= array_column($order_id, 'order_id');
            $order_ids=implode(',',$order_id);
            $sql1="UPDATE `dx_sales_order` SET `payment_system`='2' WHERE `order_id` in (".$order_ids.")";
            $res= $OrderModel->orderSql($sql1);
            var_dump($res);
        }
    }

}