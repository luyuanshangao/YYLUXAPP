<?php
namespace app\orderfrontend\model;

use app\common\controller\Email;
use app\common\helpers\CommonLib;
use app\common\services\CommonService;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use think\Log;

/**
 * 订单模型
 * @author gz
 * 2018-04-19
 */
class OrderModel extends Model{

	private $db;
    private $admin_db;
	private $order = "dx_sales_order";
	private $order_other = "dx_sales_order_other";
	private $order_item = "dx_sales_order_item";
	private $order_message = "dx_sales_order_message";
    private $shipping_address = "dx_order_shipping_address";
    private $order_coupon = "dx_sales_order_coupon";
    private $order_package = "dx_order_package";
    private $order_package_item = "dx_order_package_item";
	private $order_status_change = "dx_sales_order_status_change";
	private $order_sales_txn = "dx_sales_txn";
	private $order_after_sale_apply_log = "dx_order_after_sale_apply_log";
    private $order_after_sale_apply = "dx_order_after_sale_apply";
    private $order_affiliate = "dx_affiliate_order";
    /**
     * 订单邮寄地址记录表
     * @var string
     */
    private $order_shipping_address = "dx_order_shipping_address";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
        $this->admin_db = Db::connect('db_admin');
    }

    /**
     * 订单生成
     * @param array $params
     * @return true/false
     */
    public function submitOrder($params){
        Log::record('submitOrder, params:'.json_encode($params));
    	$_params = $params['cart_info_res']['slave'];
    	$_master_params = $params['cart_info_res']['master'];
    	$_order_id = array();
    	if(isset($_params[1]['order'])){
    		//多商家的订单，需拆分订单
    		$this->db->startTrans();
    		try {
    			//插入master订单
    			//$_order_id['master'][] = $this->insertOrder($_master_params);
    			$this->db->table($this->order)->insert($_master_params);
    			$_order_id['master']['order_id'] = $this->db->getLastInsID();
    			$_order_id['master']['order_number'] = $_master_params['order_number'];
    			//插入slave订单
    			foreach ($_params as $k=>$v){
    				$order = $v;
    				//写入到订单表，订单商品表，订单留言表
                    $insert_res = $this->insertOrder($order);
                    if (is_array($insert_res)){
                        $_order_id['slave'][] = $insert_res;
                    }else{
                        $this->db->rollback();
                        return false;
                    }
                }
    			if(count($_order_id) > 0){
                    $this->db->commit();
    				return $_order_id;
    			}else{
                    $this->db->rollback();
    				return false;
    			}
    		}catch (\Exception $e){
    			$this->db->rollback();
    			Log::record('insert order1:'.$e->getMessage().', params:'.json_encode($params),'error');
   				return false;
   		    }
    	}else{
    		//单个seller不生成主订单
    		$this->db->startTrans();
    		try {
    			//插入master订单
    			//$_order_id['master'][] = $this->insertOrder($_master_params);
    			//$this->db->table($this->order)->insert($_master_params);

    			//插入slave订单
    			//一个商家的订单，无需拆分订单
	    		$order = $_params[0];
	    		//
	    		$order['order_number'] = $_master_params['order_number'];
                $insert_res = $this->insertOrder($order);

                if (is_array($insert_res)){

                    $_order_id['slave'][] = $insert_res;

                    $_order_id['master']['order_id']  = $_order_id['slave'][0]['order_id'];
                    $_order_id['master']['order_number'] = $_master_params['order_number'];//$_order_id['slave']['order_number']

                    if(count($_order_id) > 0){
                        $this->db->commit();
                        return $_order_id;
                    }else{
                        $this->db->rollback();
                        return false;
                    }
                }else{
                    $this->db->rollback();
                    return false;
                }
    		}catch (\Exception $e){
                Log::record('insert order2:'.$e->getMessage().', params:'.json_encode($params),'error');
    			$this->db->rollback();
    			return false;
    		}
    	}
    }

    /**
     * 订单写入处理方法
     * 写入到订单表，订单商品表，订单留言表
     * @param $order
     * @return bool
     */
    public function insertOrder($order){
    	$_order_data = $order['order'];
    	try{
            $this->db->table($this->order)->insert($_order_data);
            $_order_id = $this->db->getLastInsID();
            foreach ($order['order_item'] as $k2=>$v2){
//                Log::record('order_item:'.json_encode($order['order_item']));
                $_order_item = $v2;
                $_order_item['order_id'] = $_order_id;

                $this->db->table($this->order_item)->insert($_order_item);

            }
            if(isset($order['order_item_coupon']) && count($order['order_item_coupon']) > 0){
                foreach ($order['order_item_coupon'] as $k2=>$v2){
//                    Log::record('order_item_coupon:'.json_encode($order['order_item_coupon']));
                    $_order_item = $v2;
                    $_order_item['order_id'] = $_order_id;
                    $this->db->table($this->order_item)->insert($_order_item);
                }
            }

            /** 订单状态变化记录 **/
            //默认是变为100
            $status_change['order_id'] = $_order_id;
            $status_change['order_status'] = 100;
            $status_change['create_on'] = time();
            $status_change['create_by'] = 'APIsystem';
            $status_change['chage_desc'] = 'create order';
            $this->db->table($this->order_status_change)->insert($status_change);
            //如果是金额为0且状态为200，则需要记录对应状态变化信息（0-100， 100-200）
            if ($_order_data['order_status'] == 200 &&  $_order_data['grand_total'] == 0){
                $_status_change['order_id'] = $_order_id;
                $_status_change['order_status_from'] = 100;
                $_status_change['order_status'] = 200;
                $_status_change['create_on'] = time();
                $_status_change['create_by'] = 'APIsystem';
                $_status_change['chage_desc'] = 'Payment verified. Order is being processed.';
                $this->db->table($this->order_status_change)->insert($_status_change);
            }
            $coupon_id = array();
            if(isset($order['coupon'])){
                foreach ($order['coupon'] as $k=>$v){
                    $_order_coupon = $v;
                    $_order_coupon['order_id'] = $_order_id;
                    $this->db->table($this->order_coupon)->insert($_order_coupon);
                    $coupon_id[] = isset($v['coupon_id'])?$v['coupon_id']:0;
                }
            }
            if(isset($order['shipping_address'])){
                $_order_shipping['shipping_address'] = isset($order['shipping_address'])?$order['shipping_address']:'';
                $_order_shipping['shipping_address']['order_id'] = $_order_id;
                $this->db->table($this->shipping_address)->insert($_order_shipping['shipping_address']);
            }
            //20190107 新增订单扩展表数据
            if(isset($order['order_other'])){
                $_insert_order_other = $order['order_other'];
                $_insert_order_other['order_id'] = $_order_id;
                $this->db->table($this->order_other)->insert($_insert_order_other);
            }
            $returnData['order_number'] = $_order_data['order_number'];
            $returnData['order_id'] = $_order_id;
            $returnData['coupon_id'] = $coupon_id;
            return $returnData;
        }catch(\Exception $e){
            Log::record('insert order3:'.$e->getMessage(),'error');
            return false;
        }
    }

    /**
     * 根据订单ID获取订单编号
     * @param array $params
     */
    public function getOrderNumberByOrderId($params){
    	$res = $this->db->table($this->order)->field("order_number")->where('order_id','in',$params)->select();

    	return $res;
    }



//     public function getPayOrderInfo($params){
//     	$_order_number = $params['order_number'];
//     	//$_order_number = "180510015347395316";
//     	$field = "order.goods_total,order.grand_total,item.product_nums,item.shipping_model,item.sku_num,
//     			item.product_name,item.sku_id,item.product_price,address.*,order.order_id,order.discount_total,
//     			order.shipping_fee,order.handling_fee,order.total_amount";
//     	$res = $this->db->table($this->order)->alias('order')->field($field)
//     	->join("dx_order_shipping_address address","address.order_id=order.order_id")
//     	->join("dx_sales_order_item item","item.order_id=order.order_id")
//     	->where("order.order_number=$_order_number")->select();
//     	return $res;
//     }

    /**
     * 根据订单ID获取订单收货信息
     * @param array $params
     */
    public function getOrderShippingAddress($params){
    	$_order_id = $params['order_id'];
    	//$_order_id = '236';
    	$res = $this->db->table($this->shipping_address)->field("*")->where("order_id=$_order_id")->find();

    	return $res;
    }


    /**
     * 根据订单编号获取订单收货信息
     * @param array $params
     */
    public function getOrderAddressByOrderNumber($params){
    	$_order_numbers = $params['OrderNumber'];
    	//$_order_id = '236';
    	$res = $this->db->table($this->shipping_address)->alias('shipadd')
    	->join("dx_sales_order order","order.order_id=shipadd.order_id")
    	->field("shipadd.*")->where("order.order_number=$_order_numbers")->find();

    	return $res;
    }

    /**
     * 根据订单主编号获取订单信息
     * @param array $params
     */
    public function getOrderInfoByOrderMasterNumber($params){
    	$fields = "grand_total,currency_code";
    	$_order_master_numbers = $params['order_master_number'];
    	$res = $this->db->table($this->order)->field($fields)->where("order_master_number=$_order_master_numbers")->find();

    	return $res;
    }

    /**
     * 根据订单号获取订单数据
     * @param $order_number
     * @param string $fields
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderInfoByOrderNumber($order_number, $fields='*'){
        return $this->db->table($this->order)->field($fields)->where(['order_number'=>$order_number])->find();
    }

    /**
     * 改变订单状态
     * @param $params
     * @return bool|int|string
     */
    public function changeOrderStatus($params){
    	if(!isset($params['order_id']) || !isset($params['order_status'])){
    		return false;
    	}
    	$where['order_id'] = $params['order_id'];
    	if(isset($params['status_type']) && $params['status_type'] == 2){
            $data['order_branch_status'] = $params['order_status'];
        }else{
            $data['order_status'] = $params['order_status'];
        }
    	$data['modify_by'] = isset($params['modify_by'])?$params['modify_by']:'';
    	$data['modify_on'] = time();
    	$res = $this->db->table($this->order)->where($where)->update($data);
    	/*同步更新affiliate订单状态*/
        $affiliate = $this->db->table($this->order)->where($where)->value("affiliate");
    	if($affiliate && $params['status_type'] == 2){
    	    $affiliate_where['order_number'] = $params['order_number'];
    	    $affiliate_data['order_status'] = $params['order_status'];
            $affiliate_update = $this->db_admin->table($this->order_affiliate)->where($affiliate_where)->update($affiliate_data);
        }
    	return $res;
    }

    /**
     * 订单查询处理方法
     * 查询订单表
     * @param array $order
     */
    public function getOrderList($where,$page_size=20,$page=1,$path='',$order='',$page_query=''){
        $page_query = !empty($page_query)?$page_query:$where;
        unset($page_query["delete_time"]);
        unset($page_query['customer_id']);

        $res = $this->db->table($this->order)
                ->alias("o")
                ->join($this->order_item." oi","o.order_id=oi.order_id")
                ->where($where)
                ->order($order)
                ->group('o.order_id')
                ->field("o.order_id,o.parent_id,o.order_number,o.store_id,o.transaction_id,o.shipping_fee,o.shipping_fee,o.store_name,o.payment_status,o.order_status,o.lock_status,o.goods_count,o.discount_total,o.shipping_fee,o.handling_fee,o.total_amount,o.goods_total,o.grand_total,o.captured_amount_usd,o.captured_amount,o.refunded_amount,o.currency_code,o.shipping_count,o.shipped_count,o.shipped_amount,o.order_type,o.exchange_rate,o.language_code,o.create_on,o.shipping_insurance_fee,o.boleto_url,o.pay_type,o.pay_channel,receivable_shipping_fee,order_master_number,adjust_price,affiliate,order_branch_status")
                ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query]);
        //echo $this->db->table($this->order)->getLastSql();

        $Page = $res->render();
        $data = $res->toArray();
        if($data['data']){
            foreach ($data['data'] as $key=>$value){
                $item_where['order_id'] = $value['order_id'];
                $data['data'][$key]['currency_value'] = getCurrency('',!empty($value['currency_code'])?$value['currency_code']:'USD');
                $data['data'][$key]['item'] = $this->db->table($this->order_item)->where($item_where)->field("order_id,product_id,sku_id,sku_num,first_category_id,discount_total,product_price,product_price,product_name,product_img,product_nums,product_attr_ids,product_attr_desc,captured_price,shipping_model")->select();
                foreach ($data['data'][$key]['item'] as $k=>$v){
                    $data['data'][$key]['item'][$k]['product_attr_names'] = '';
                    if(!empty($v['product_attr_desc'])){
                        $product_attr_arr = explode(",",$v['product_attr_desc']);
                        if($product_attr_arr){
                            foreach ($product_attr_arr as $k1=>$v1){
                                if(!empty($v1)){
                                    $product_attr_array = explode(":",$v1);
                                        if(isset($product_attr_array[1])){
                                            $color_attr_array = explode("|",$product_attr_array[1]);
                                            if(isset($color_attr_array[1])){
                                                $color_attr = $color_attr_array[0]. "<img src='".$color_attr_array[1]."'>";
                                                $product_attr_array[1] = $color_attr;
                                            }
                                        }
                                    if($k1 == 0){
                                        $data['data'][$key]['item'][$k]['product_attr_names'] = isset($product_attr_array[1])?$product_attr_array[1]:'';
                                    }else{
                                        $data['data'][$key]['item'][$k]['product_attr_names'] .= isset($product_attr_array[1])?" + ".$product_attr_array[1]:'';
                                    }
                                }
                            }
                        }
                    }
                    /*获取产品物流单号*/
                    if($value['order_status']>=500 && $value['order_status']<=1300){
                         $tracking_number= $this->db->table($this->order_package)
                                                                             ->alias("p")
                                                                             ->join($this->order_package_item." pi","p.package_id = pi.package_id")
                                                                            ->where("pi.sku_id = '{$v['sku_num']}' AND order_number={$value['order_number']}")
                                                                            ->value("tracking_number");
                        $data['data'][$key]['item'][$k]['tracking_number']=!empty($tracking_number)?$tracking_number:'';
                    }else{
                        $data['data'][$key]['item'][$k]['tracking_number'] = '';
                    }
                    $data['data'][$key]['item'][$k]['product_attr_ids'] = !empty($v['product_attr_ids'])?$v['product_attr_ids']:0;
                    /*订单已关闭时查询是否是售后*/
                    if($value['order_status']>=1900){
                        $data['data'][$key]['after_sale_id'] = 0;
                        $after_sale_id = $this->db->table($this->order_after_sale_apply)->where(['order_id'=>$value['order_id']])->value('after_sale_id');
                        if($after_sale_id){
                            $data['data'][$key]['after_sale_id'] = $after_sale_id;
                        }
                    }
                }
                $data['data'][$key]['shipping_address'] = $this->db->table($this->order_shipping_address)->where(['order_id'=>$value['order_id']])->find();
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 订单详情处理方法
     * 查询订单详情表
     * @param array $order
     */
    public function getOrderInfo($order_id,$sku_id='',$customer_id='',$order_number=''){
        if(!empty($customer_id)){
            $where['customer_id'] = $customer_id;
        }
        if(!empty($order_id)){
            $where['order_id'] = $order_id;
        }else{
            $where['order_number'] = $order_number;
        }
        $res = $this->db->table($this->order)
            ->where($where)
            ->field("order_id,parent_id,order_number,store_id,store_name,customer_name,payment_status,order_status,lock_status,goods_count,discount_total,goods_total,shipping_fee,handling_fee,total_amount,grand_total,captured_amount_usd,captured_amount,transaction_id,refunded_amount,currency_code,shipping_count,shipped_count,shipped_amount,order_type,exchange_rate,language_code,create_on,shipping_insurance_fee,receivable_shipping_fee,logistics_provider,tariff_insurance,pay_type,order_master_number,pay_time,adjust_price,boleto_url,affiliate,order_branch_status,pay_channel")
            ->find();
        if($res){
            // 重新划分订单状态（可通过判断状态区间来显示）以及相关倒计时提示功能。为了配合前端，1-买家下单、2-买家付款、3-卖家发货、4-订单完成
            $order_status = $res['order_status'];
            //状态
            $order_show_status = 1;
            //订单完成时，倒计时类型标识
            $count_down_finish_flag = 0;
            //倒计时秒数
            $count_down_time = 0;
            $time = time();
            if (
                ($order_status > 0 && $order_status < 200)
                || $order_status == 300
            ){
                $order_show_status = 1;
                //完成对本订单的付款剩余时间，倒计时从订单提交完成的时间起，开始倒数5天（可配置）的倒计时。该5天为工作日
                $order_pay_expire_time = config('order_pay_expire_day')*24*60*60;
                $create_on = $res['create_on'];
                $flag_time = ($create_on + $order_pay_expire_time) - $time;
                $count_down_time = $flag_time>0?$flag_time:0;
            }elseif (
                $order_status == 400
                || $order_status == 200
            ){
                $order_show_status = 2;
                //付款完成后开始可发货倒数时间，倒计时从付款完成的时间起，开始倒数5天（可配置）的倒计时。该5天为工作日
                $delivery_time_limit_time = config('delivery_time_limit_day')*24*60*60;
                $pay_time = $res['pay_time'];
                $flag_time = ($pay_time + $delivery_time_limit_time) - $time;
                $count_down_time = $flag_time>0?$flag_time:0;
            }elseif (
                $order_status > 400
                && $order_status <= 800
            ){
                $order_show_status = 3;
                //提醒买家确认收货的倒计时，倒计时从发货完成的时间起，开始倒数60天（可配置）的倒计时。该60天为工作日
                $buyer_confirm_time = config('buyer_confirm_take_delivery_limit_day')*24*60*60;
                $shipments_complete_time = isset($res['shipments_complete_time'])?$res['shipments_complete_time']:0;
                $flag_time = ($shipments_complete_time + $buyer_confirm_time) - $time;
                $count_down_time = $flag_time>0?$flag_time:0;
            }else{
                $order_show_status = 4;
                ///** 未评价 **/ 订单已完成，可及时对订单进行评价。买家还有 0天00小时00分钟00秒 //TODO进行评价。
                $order_status_info = $this->getOrderStatusInfoByWhere([
                    'order_id'=>$res['order_id'],
                    'order_status'=>$order_status
                ]);
                $order_status_time = $order_status_info['create_on'];//订单状态变化时间
                //订单交易完成后可评价限制（单位：天）
                $order_review_limit_day = config('order_review_limit_day')*24*60*60;
                //订单交易完成后可追加评价限制（单位：天）（未评价）
                $append_review_limit_day = config('append_review_limit_day')*24*60*60;
                //订单交易完成后可追加评价限制（单位：天）（已评价）
                $append_have_review_limit_day = config('append_have_review_limit_day')*24*60*60;
                switch ($order_status){
                    case 900://已完成
                    case 1000://待评价
                        //在评价期内，提醒买家评价。注释语：订单已完成，可及时对订单进行评价。买家还有（钟表控件）14天22小时15分钟31秒进行评价
                        if (
                            ($order_status_time + $order_review_limit_day) > $time
                        ){
                            $flag_time = ($order_status_time + $order_review_limit_day) - $time;
                            $count_down_time = $flag_time>0?$flag_time:0;
                            $count_down_finish_flag = 1;//待评价，但在评价期内的倒计时
                        }else{
                            //如果订单已过评价期，但未过追评期
                            if (
                                ($order_status_time + $append_review_limit_day) > $time
                            ){
                                $flag_time = ($order_status_time + $append_review_limit_day) - $time;
                                $count_down_time = $flag_time>0?$flag_time:0;
                                $count_down_finish_flag = 2;//待评价，超过评价期但在追评期内的倒计时
                            }
                        }
                        break;
                    case 1100://已评价。则注释语为：订单已评价，仍可进行追评。买家还有（钟表控件）14天22小时15分钟31秒进行追评
                        $flag_time = ($order_status_time + $append_have_review_limit_day) - $time;
                        $count_down_time = $flag_time>0?$flag_time:0;
                        $count_down_finish_flag = 3;//已评价，追评倒计时
                        break;
                }
            }
            $res['order_show_status'] = $order_show_status;
            $res['count_down_time'] = $count_down_time;
            $res['count_down_finish_flag'] = $count_down_finish_flag;

            $item_where['order_id'] = $res['order_id'];
            if(!empty($sku_id)){
                $item_where['sku_id'] = $sku_id;
            }
            $res['currency_value'] = getCurrency('',$res['currency_code']);
            $res['item'] = $this->db->table($this->order_item)->where($item_where)->field("item_id,order_id,product_id,sku_id,sku_num,captured_price,captured_price_usd,discount_total,product_price,product_price,product_name,product_img,product_nums,product_attr_ids,product_attr_desc,shipping_fee,shipping_model,delivery_time")->select();
            $address_where['order_id'] = $res['order_id'];
            $res['shipping_address'] = $this->db->table($this->shipping_address)->where($address_where)->find();
            $package_where['order_number'] = $res['order_number'];
            foreach ($res['item'] as $key=>$value){
                /*获取产品物流单号*/
                if($res['order_status']>=500 && $res['order_status']<=1300){
                     $tracking_number= $this->db->table($this->order_package)
                        ->alias("p")
                        ->join($this->order_package_item." pi","p.package_id = pi.package_id")
                        ->where("pi.sku_id = {$value['sku_id']} AND order_number={$res['order_number']}")
                        ->column("tracking_number");
                    if(!empty($tracking_number)&&is_array($tracking_number)){
                        $res['item'][$key]['tracking_number']=$tracking_number[0];//APP只需要一个物流号
                    }else{
                        $res['item'][$key]['tracking_number']='';
                    }
                }

                /*处理商品属性*/
                if(!empty($value['product_attr_desc'])){
                    $product_attr_arr = explode(",",$value['product_attr_desc']);
                    if($product_attr_arr){
                        foreach ($product_attr_arr as $k1=>$v1){
                            if(!empty($v1)){
                                $product_attr_array = explode(":",$v1);
                                if(isset($product_attr_array[1])){
                                    $color_attr_array = explode("|",$product_attr_array[1]);
                                    if(isset($color_attr_array[1])){
                                        $color_attr = $color_attr_array[0]. "<img src='".$color_attr_array[1]."'>";
                                        $product_attr_array[1] = $color_attr;
                                    }
                                }
                                if($k1 == 0){
                                    $res['item'][$key]['product_attr_names'] = isset($product_attr_array[1])?$product_attr_array[1]:'';
                                }else{
                                    $res['item'][$key]['product_attr_names'] .= isset($product_attr_array[1])?" + ".$product_attr_array[1]:'';
                                }
                            }
                        }
                    }
                }
            }

            $res['order_package'] = $this->db->table($this->order_package)->where($package_where)->select();

        }
        /*将订单消息改未已阅读*/
        $this->db->table($this->order_message)
            ->where(['order_id'=>$res['order_id'],'message_type'=>1])->update(['statused'=>1]);
        $res['order_status_change'] = $this->db->table($this->order_status_change)->where(['order_id'=>$res['order_id']])->order(" id desc")->select();

        return $res;
    }


    /*
     * 获取订单操作历史记录
     * */
    public function getOrderStatusChange($where){
        $res = $this->db->table($this->order_status_change)->where($where)->order(" id desc")->select();
        return $res;
    }

    /**
     * 订单详情处理方法
     * relpay
     * @param array $order
     */
    public function getPayOrderInfo($where){
        $order_master_number = $where['order_master_number'];
        $_where['order_master_number'] = $order_master_number;
        if (isset($where['customer_id'])){
            $_where['customer_id'] = $where['customer_id'];
        }
    	$order_file = "parent_id,order_id,order_number,order_status,store_id,store_name,payment_status,order_status,
    			lock_status,goods_count,discount_total,goods_total,shipping_fee,handling_fee,total_amount,
    			grand_total,captured_amount_usd,captured_amount,refunded_amount,adjust_price,currency_code,shipping_count,
    			shipped_count,shipped_amount,order_type,exchange_rate,language_code,create_on,shipping_insurance_fee,
    			receivable_shipping_fee,logistics_provider,tariff_insurance,pay_type,customer_id,is_tariff_insurance,tariff_insurance";
    	$res = $this->db->table($this->order)
    	->field($order_file)
    	//->where('order_master_number='.$order_master_number)
    	->where($_where)
    	->select();
    	if($res){
    		$returnData['order'] = $res;
    		foreach ($res as $k=>$v){
    			$where_order_id[] = $v['order_id'];
    		}
    		$returnData['item'] = $this->db->table($this->order_item)->where('order_id','in',$where_order_id)
    		->field("shipping_model,shipping_fee,delivery_time,product_unit,order_id,product_id,sku_id,sku_num,first_category_id,
    		discount_total,product_price,product_price,product_name,product_img,product_nums,product_attr_desc,active_id,order_item_type,message")->select();

    		$returnData['shipping_address'] = $this->db->table($this->shipping_address)->where('order_id','in',$where_order_id)->select();
            $returnData['order_coupon'] = $this->db->table($this->order_coupon)->where('order_id','in',$where_order_id)->select();
    		$returnData['master_order'] = $this->db->table($this->order)->field($order_file)->where('order_number='.$order_master_number)->find();
    	}
    	return $returnData;
    }


    /**
     * 订单详情处理方法
     * relpay
     * @param array $order
     */
    public function getBasicOrderInfo($where){
        $order_file = "parent_id,order_id,order_number,order_status,store_id,store_name,payment_status,order_status,
    			lock_status,goods_count,discount_total,goods_total,shipping_fee,handling_fee,total_amount,
    			grand_total,captured_amount_usd,captured_amount,refunded_amount,adjust_price,currency_code,shipping_count,
    			shipped_count,shipped_amount,order_type,exchange_rate,language_code,create_on,shipping_insurance_fee,
    			receivable_shipping_fee,logistics_provider,tariff_insurance,pay_type,customer_id,is_tariff_insurance,tariff_insurance,country_code,complete_on";
        $res = $this->db->table($this->order)
            ->field($order_file)
            ->where($where)
            ->find();
        $returnData = array();
        if($res){
            $returnData = $res;
            $returnData['item'] = $this->db->table($this->order_item)->where('order_id','in',$res['order_id'])
                ->field("shipping_model,shipping_fee,delivery_time,product_unit,order_id,product_id,sku_id,sku_num,first_category_id,
    		discount_total,product_price,product_price,product_name,product_img,product_nums,product_attr_desc,product_attr_ids,active_id,order_item_type,message")->select();
            $returnData['shipping_address'] = $this->db->table($this->shipping_address)->where('order_id','in',$res['order_id'])->find();
            $returnData['order_coupon'] = $this->db->table($this->order_coupon)->where('order_id','in',$res['order_id'])->select();
            $returnData['master_order'] = $this->db->table($this->order)->field($order_file)->where('order_number='.$res['order_number'])->find();
        }
        return $returnData;
    }
    public function getOrderNumberByOrderMasterNumber($where){
        $order_master_number = $where['order_master_number'];
        $order_file = "order_id,order_number,order_status,currency_code";
        try{
            $res = $this->db->table($this->order)
                ->field($order_file)
                ->where('order_master_number='.$order_master_number)
                ->select();
            Log::record('error','getOrderNumberByOrderMasterNumber_________');
        }catch (Exception $e){
            Log::record('error','getOrderNumberByOrderMasterNumber',$e->getMessage());
        }


        return $res;
    }

    /**
     * 订单消息处理方法
     * 查询订单详情表
     * @param array $order
     */
    public function getOrderMessage($where){
        $order = "create_on asc";
        return $this->db->table($this->order_message)->where($where)->field("id,order_id,parent_id,message_type,message,file_url,statused,create_on,user_id,user_name")->order($order)->select();
    }

    /**
     * 添加订单消息处理方法
     * 查询订单详情表
     * @param array $data
     */
    public function addOrderMessage($data){
        return $this->db->table($this->order_message)->insertGetId($data);
    }

    /**
     * 订单删除处理方法
     * @param int $order_d
     */
    public function delOrder($where){
        $data['delete_time'] = time();
        return $this->db->table($this->order)->where($where)->update($data);
    }

    /**
     * 统计订单数量
     * @param int $order_d
     */
    public function getOrderCount($where){
        $count = array();
        $order_status = [0,100,400,600,700,900,1700,1800];
        foreach ($order_status as $value){
            if($value == 600){
                $where['order_status'] = ['in',[600,700]];
            }elseif($value == 700){
                $where['order_status'] = ['in',[200,400,$value]];
            }elseif($value == 900){
                $where['order_status'] =$value;
                $where['order_branch_status'] = ['BETWEEN',[0,1100]];
            }else{
                if($order_status>0){
                    $where['order_status'] =$value;
                }
            }
            $where['delete_time'] = 0;
            $count[$value]= $this->db->table($this->order)->where($where)->count("order_id");
        }
        return $count;
    }

    /**
     * 批量添加追踪号信息
     * @param array $all_data 要添加的数据
     * @return bool|string
     * @throws \Exception
     * @throws \think\exception\PDOException
     */
    public function addTrackingNumberByAllData(array $all_data){
        $rtn = true;
        $this->db->startTrans();
        try{
            $time = time();
            //来源类型：1-OMS（默认），2-ERP
            $from_type = isset($all_data['from_type'])?$all_data['from_type']:1;
            //订单ID，ERP的时候会传
            /*if ($from_type == 2){
                $_order_info = $this->db->table($this->order)->where(['order_id'=>$all_data['order_id']])->find();
                $order_number = $all_data['order_number'] = $_order_info['order_number'];
                unset($all_data['order_id']);
            }else{
                $order_number = $all_data['order_number'];
            }*/
            $order_number = $all_data['order_number'];
            if (empty($order_number)){
                return 'order_number为空';
            }
            //1、先根据“package_number”判断是否有数据，若有则删除记录（包含item表数据），之后再同步数据
            $where['order_number'] = $order_number;
            if (isset($all_data['package_number']) && !empty($all_data['package_number'])){
                $where['package_number'] = $all_data['package_number'];
            }
            $where['tracking_number'] = $all_data['tracking_number'];
            $exist_data = $this->db->table($this->order_package)->where($where)->find();
            if (!empty($exist_data)){
                $exist_package_id = $exist_data['package_id'];
                $this->db->table($this->order_package_item)->where(['package_id'=>$exist_package_id])->delete();
                $this->db->table($this->order_package)->where($where)->delete();
            }
            /** 2、 同步追踪号操作 **/
            $item_data = $all_data['item_info'];
            unset($all_data['item_info']);
            unset($all_data['from_type']);
            $tracking_number = isset($all_data['tracking_number'])?$all_data['tracking_number']:'';
            /** 计算之前已经发货的数量，如果有的话 **/
            $_all_shipped_sku_count = 0; //已发货产品总数（之前已经发货数 + 本次发货产品数）
            $all_exist_data = $this->db->table($this->order_package)->where(['order_number'=>$order_number])->select();
            if (!empty($all_exist_data)){
                foreach ($all_exist_data as $k21=>$v21){
                    $all_exist_data_item = $this->db->table($this->order_package_item)->where(['package_id'=>$v21['package_id']])->select();
                    if (!empty($all_exist_data_item)){
                        foreach ($all_exist_data_item as $k22=>$v22){
                            //之前已经发货数
                            $_all_shipped_sku_count += $v22['sku_qty'];
                        }
                    }
                }
            }
            //写入dx_order_package表
            $_all_data['order_number'] = isset($all_data['order_number'])?$all_data['order_number']:'';
            $_all_data['weight'] = isset($all_data['weight'])?$all_data['weight']:'';
            $_all_data['shipping_fee'] = isset($all_data['shipping_fee'])?$all_data['shipping_fee']:0;
            $_all_data['triff_fee'] = isset($all_data['triff_fee'])?$all_data['triff_fee']:0;
            $_all_data['service_per_charge'] = isset($all_data['service_per_charge'])?$all_data['service_per_charge']:0;
            $_all_data['service_charge'] = isset($all_data['service_charge'])?$all_data['service_charge']:0;
            $_all_data['total_amount'] = isset($all_data['total_amount'])?$all_data['total_amount']:0;
            $_all_data['pic_path_when_check'] = isset($all_data['pic_path_when_check'])?$all_data['pic_path_when_check']:'';
            $_all_data['pic_path_when_weigh'] = isset($all_data['pic_path_when_weigh'])?$all_data['pic_path_when_weigh']:'';
            $_all_data['package_number'] = isset($all_data['package_number'])?$all_data['package_number']:'';
            $_all_data['tracking_number'] = $tracking_number;
            $_all_data['shipping_channel_name'] = isset($all_data['shipping_channel_name'])?$all_data['shipping_channel_name']:'';
            $_all_data['add_time'] = isset($all_data['add_time'])?$all_data['add_time']:'';
            $package_id = $this->db->table($this->order_package)->insertGetId($_all_data);
            //写入dx_order_package_item表
            $_package_lines = []; //拼装调用OMS修改订单状态PackageLines数据
            $_item_data = [];
            foreach ($item_data as &$item){
                $item['package_id'] = $package_id;
                //PackageLines
                $_temp_package_lines = [];
                $_temp_package_lines['Sku'] = $item['sku_id'];
                $_temp_package_lines['Qty'] = $item['sku_qty'];
                $_package_lines[] = $_temp_package_lines;
                //拼装包裹详情数据
                $_temp_item_data = [];
                $_temp_item_data['package_id'] = $package_id;
                $_temp_item_data['sku_id'] = $item['sku_id'];
                $_temp_item_data['sku_qty'] = $item['sku_qty'];
                $_item_data[] = $_temp_item_data;
                //本次发货产品数
                $_all_shipped_sku_count += $item['sku_qty'];
            }
            $this->db->table($this->order_package_item)->insertAll($_item_data);
            //3、同步发货状态（根据sku数量来判断是部分还是全部发货，之后更新发货状态 500:部分发货和600:全部发货）
            $fulfillment_status = 500; //发货状态 500:部分发货和600:全部发货
            $fulfillment_status_str = 'Partial Shipped';
            $order_info = $this->db->table($this->order)->where(['order_number'=>$order_number])->find();
            $order_item_data = $this->db->table($this->order_item)->where(['order_id'=>$order_info['order_id']])->select();
            /**
             * 20181219 解决部分发货不能变为全部发货情况
             */
            $_all_sku_count = 0; //订单产品总数
            //获取总订单产品数量
            foreach ($order_item_data as $k20=>$v20){
                $_all_sku_count += $v20['product_nums'];
            }
//            if (count($order_item_data) == count($item_data)){
            if ($_all_shipped_sku_count == $_all_sku_count || $_all_shipped_sku_count > $_all_sku_count){
                $fulfillment_status = 600;
                $fulfillment_status_str = 'Full Shipped';
            }
            /*$this->db->table($this->order)->where(['order_number'=>$order_number])->update([
                'fulfillment_status'=>$fulfillment_status,
                'order_status'=>$fulfillment_status,
                'shipments_time'=>$time,
                'modify_on'=>$time
            ]);
            //订单状态变化记录
            $status_change['order_id'] = $order_info['order_id'];
            $status_change['order_status_from'] = $order_info['order_status'];
            $status_change['order_status'] = $fulfillment_status;
            $status_change['create_on'] = $time;
            $status_change['create_by'] = 'APIsystem';
            $status_change['chage_desc'] = '';
            $status_change['create_ip'] = '';
            $this->order_status_change_log($status_change);*/
            //订单状态变化记录
            $up_status_data['is_start_trans'] = 2; //是否开启事务：1-开启（默认），2-不开启
            $up_status_data['order_id'] = $order_info['order_id'];
            $up_status_data['order_status_from'] = $order_info['order_status'];
            $up_status_data['order_status'] = $fulfillment_status;
            $up_status_data['change_reason'] = '';
            $up_status_data['create_on'] = $time;
            $up_status_data['create_by'] = 'APIsystem';
            $up_status_data['create_ip'] = 0;
            $up_status_data['chage_desc'] = '';
            // -- 可选选项 --
            $up_status_data['fulfillment_status'] = $fulfillment_status;
            $up_status_data['shipments_time'] = $time;
            $res = $this->updateOrderStatus($up_status_data);
            if (true === $res){
                /**
                 * 如果是ERP通过过来的数据，需要：
                 * 1、需要将包裹信息传递给LIS； TODO...暂时不做，等瑶瑶提供接口
                 * 2、需要将包裹信息同步给OMS **** 不做【但要调用OMS接口通知订单状态】 ****
                 */
                if ($from_type == 2){
                    //2、需要将包裹信息同步给OMS，********** 不做 **********
                    /*$common_service = new CommonService();
                    $_params = [
                        'CompleteShipments'=>[
                            'request'=>[
                                'RequestUserName'=>'',
                                'CompleteShipmentInfos'=>[
                                    'CompleteShipmentInfo'=>[
                                        [
                                            'CarrierID'=>'',
                                            'Packages'=>[
                                                [
                                                    'PackageDTO'=>[
                                                        [
                                                            'PackageID'=>'',
                                                            'PackageLine'=>[
                                                                'PackageLineDTO'=>[
                                                                    [
                                                                        'PackageID'=>'',
                                                                        'PackageNumber'=>'',
                                                                        'Qty'=>'',
                                                                        'Sku'=>'',
                                                                    ],
                                                                ]
                                                            ],
                                                        ],
                                                    ]
                                                ]
                                            ],
                                        ],
                                    ]
                                ],
                            ]
                        ]
                    ];
                    $full_res = $common_service->FulfillmentService('CompleteShipments', $_params);
                    Log::record('FulfillmentService_CompleteShipments_params:'.json_encode($_params).', res:'.json_encode($full_res));*/

                    //调用OMS接口通知订单状态
                    $post_config = config('synchro_fulfillment_oms_post');
                    $post_header = [];
                    $post_header[] = "Content-Type: application/json";
                    $post_header[] = "Authorization: Basic ".base64_encode($post_config['user_name'].":".$post_config['pass_word']);
                    $post_data = [];
                    /**
                     * [{
                    "OrderNumber": "180928100110099922",
                    "ShippedDate": "2018-10-23 09:56:30",
                    "PackageLines": [{
                    "Sku": 1000000003,
                    "Qty": 1
                    },
                    {
                    "Sku": 1138,
                    "Qty": 1
                    }]
                    }]
                     *
                     */
                    $_temp_post['OrderNumber'] = $order_number;
                    $_temp_post['ShippedDate'] = date('Y-m-d H:i:s');
                    $_temp_post['PackageLines'] = $_package_lines;
                    $post_data[] = $_temp_post;
                    $i = 1;
                    do{
                        $post_result = doCurl($post_config['url'], $post_data, null, true, $post_header);
                        Log::record('fulfillment_oms_post,config'.$order_number.':'.json_encode($post_config).', header:'.json_encode($post_header).', params:'.json_encode($post_data).', res:'.json_encode($post_result).', times:'.$i);
                        $post_result = json_decode(json_encode($post_result), true);
                        if (
                            isset($post_result['IsSuccess'])
                            && $post_result['IsSuccess'] === true
                        ){
                            $i = 4;
                        }else{
                            $i++;
                        }
                    }while($i<=3);
                }
                /**
                 * 发送发货成功邮件
                 */
                $url = MALL_API."/cic/Customer/getEmailsByCID";
                $user_res = doCurl($url,['id'=>$order_info['customer_id']],null,true);
                if (isset($user_res['code']) && $user_res['code'] == 200){
                    $to_email = isset($user_res['data'])?$user_res['data']:'';
                    if (!empty($to_email)){
                        //邮件标题
                        $_title_values['order_number'] = $order_info['order_number'];
                        //邮件内容
                        $_body_values['user_name'] = !empty($order_info['customer_name'])?$order_info['customer_name']:$order_info['customer_id'];
                        $_body_values['order_number'] = $order_info['order_number'];
                        $_body_values['tracking_number'] = $tracking_number;
                        $_body_values['order_status'] = $fulfillment_status_str;
                        //发送邮件
                        $i = 1;
                        do{
                            $mail_res = Email::sendEmail($to_email,602,$order_info['customer_id'],$_body_values, $_title_values,2);
                            if ($mail_res){
                                $i = 4;
                            }else{
                                $i++;
                                Log::record('发送发货成功邮件-失败，res：'.$mail_res.'，to_email：'.$to_email.'，id：602，to_name：'.$order_info['customer_id'].'，body：'.json_encode($_body_values).'，title：'.json_encode($_title_values));
                            }
                        }while($i<=3);
                    }else{
                        Log::record('发送发货成功邮件-失败，$to_email为空，url：'.$url.'，res'.json_encode($user_res));
                    }
                }else{
                    Log::record('发送发货成功邮件-失败，获取用户邮件失败，url：'.$url.'，res'.json_encode($user_res));
                }
                $this->db->commit();
            }else{
                $rtn = false;
                $this->db->rollback();
            }
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 获取追踪号信息
     * @param string $order_number 订单编码
     * @return int|string
     */
    public function getTrackingNumber($where){
        return $this->db->table($this->order_package)->where($where)->select();
    }

    /**
     * 根据条件更新订单表
     * @param array $where 条件
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateOrderByWhere(array $where, array $up_data){
        return $this->db->table($this->order)->where($where)->update($up_data);
    }

    /**
     * 新增交易明细数据
     * @param array $data 要新增的数据
     * @return bool|string
     * @throws \Exception
     */
    public function insertSalesTXN(array $data){
        $rtn = true;
        $this->db->startTrans();
        try{
            $_order_number = $data['order_number'];
            //1、通过third_party_txn_id和订单编号查找对应的数据进行删除操作（为了避免有多条数据的情况）
            $this->db->table($this->order_sales_txn)
                ->where([
                    'order_number'=>$_order_number,
                    'third_party_txn_id'=>$data['third_party_txn_id']
                ])->delete();
            //2、新增
            $this->db->table($this->order_sales_txn)->insert($data);
            //3、20181210 存在多笔支付的情况下，需要更新实收金额，为了避免重复支付，退款一笔而出现订单关闭（通过实收金额和退款金额对比，若相等则关闭订单）情况
            $_result = $this->db->table($this->order_sales_txn)
                ->where(['order_number'=>$_order_number])
                ->where('txn_type', 'in', ['Capture', 'Purchase'])
                ->select();
            if (
                !empty($_result) && is_array($_result)
                && count($_result) > 1
            ){
                $_amount = 0;
                foreach ($_result as $k=>$v){
                    $_amount += $v['amount'];
                }
                $_amount_usd = $_amount;
                //订单数据
                $order_info = $this->db->table($this->order)->where(['order_number'=>$_order_number])->find();
                $_exchange_rate = $order_info['exchange_rate'];
                //币种判断
                if (
                    isset($_result[0]['currency_code'])
                    && strtoupper($_result[0]['currency_code']) != 'USD'
                ){
                    $_amount_usd = sprintf("%.2f", $_amount_usd/$_exchange_rate);
                }
                $_update = [
                    'grand_total'=>$_amount, //实收总金额
                    'captured_amount_usd'=>$_amount_usd, //以美元为单的实收总金额（如果退款，这个金额会变动）
                    'captured_amount'=>$_amount //实收金额（如果退款，这个金额会变动）
                ];
                $this->db->table($this->order)
                    ->where(['order_number'=>$_order_number])
                    ->update($_update);
				//记录更新订单金额日志
                Log::record('insertSalesTXN update captured_amount'.$_order_number.', OrderInfo:'.json_encode($order_info).', update:'.json_encode($_update));
            }
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            $this->db->rollback();
        }
        return $rtn;
    }

    /*
     * 获取订单基础信息
     * */
    public function getOrderBasics($where){
        return $this->db->table($this->order)->where($where)->field("order_id,order_number,store_id,store_name,total_amount,tariff_insurance,order_status,receivable_shipping_fee,discount_total,create_on,currency_code,shipments_time,captured_amount,affiliate,customer_id,customer_name")->find();
    }

    /*
     * 记录订单状态更改记录
     * */
    public function order_status_change_log($data){
        $status_change['order_id'] = $data['order_id'];
        $status_change['order_status_from'] = isset($data['order_status_from'])?$data['order_status_from']:100;
        $status_change['order_status'] = isset($data['order_status'])?$data['order_status']:100;
        $status_change['create_on'] = time();
        $status_change['create_by'] = isset($data['create_by'])?$data['create_by']:'APIsystem';
        $status_change['chage_desc'] = isset($data['chage_desc'])?$data['chage_desc']:'';
        $status_change['create_ip'] = isset($data['create_ip'])?$data['create_ip']:'';
        $status_change['change_reason_id'] = isset($data['change_reason_id'])?$data['change_reason_id']:'';
        $status_change['change_reason'] = isset($data['change_reason'])?$data['change_reason']:'';
        $res = $this->db->table($this->order_status_change)->insert($status_change);
        return $res;
    }

    /**
     * 根据订单ID获取订单详情
     * @param $order_id 订单ID
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderInfoByOrderId($order_id){
        //订单基本信息
        $order_info = $this->db->table($this->order)
            ->where(['order_id'=>$order_id])
            ->where(['delete_time'=>0])
            ->find();
        //订单产品信息
        $order_info['item_data'] = $this->getOrderItemDataByWhere(['order_id'=>$order_info['order_id']]);
        //订单留言信息
        $message_data = $this->getOrderMessageDataByWhere(['order_id'=>$order_info['order_id']]);
        foreach ($message_data as &$message){
            //地址处理
            $file_real_url = $message['file_url'];
            $message_real_name = '';
            if (!empty($message['file_url'])){
                $file_real_url = config('cdn_url').$message['file_url'];
            }
            $message['file_real_url'] = $file_real_url;
            //留言人姓名,message_type：1表示卖家留言或回复，2表示买家留言或回复
            if ($message['message_type'] == 1){
                $message_real_name = $order_info['store_name'];
            }elseif ($message['message_type'] == 2){
                $message_real_name = $order_info['customer_name'];
            }
            $message['message_real_name'] = $message_real_name;
        }
        $order_info['message_data'] = $message_data;
        //订单收货地址信息
        $shipping_info = [];
        $shipping_data = $this->getOrderShippingAddressDataByWhere(['order_id'=>$order_info['order_id']]);
        if (!empty($shipping_data)){
            $shipping_info = $shipping_data[0];
        }
        $order_info['shipping_data'] = $shipping_info;
        // 重新划分订单状态（可通过判断状态区间来显示）以及相关倒计时提示功能。为了配合前端，1-买家下单、2-买家付款、3-卖家发货、4-订单完成
        $order_status = $order_info['order_status'];
        //状态
        $order_show_status = 1;
        //订单完成时，倒计时类型标识
        $count_down_finish_flag = 0;
        //倒计时秒数
        $count_down_time = 0;
        $time = time();
        if (
            ($order_status > 0 && $order_status < 200)
            || $order_status == 300
        ){
            $order_show_status = 1;
            //完成对本订单的付款剩余时间，倒计时从订单提交完成的时间起，开始倒数5天（可配置）的倒计时。该5天为工作日
            $order_pay_expire_time = config('order_pay_expire_day')*24*60*60;
            $create_on = $order_info['create_on'];
            $flag_time = ($create_on + $order_pay_expire_time) - $time;
            $count_down_time = $flag_time>0?$flag_time:0;
        }elseif (
            $order_status == 400
            || $order_status == 200
        ){
            $order_show_status = 2;
            //付款完成后开始可发货倒数时间，倒计时从付款完成的时间起，开始倒数5天（可配置）的倒计时。该5天为工作日
            $delivery_time_limit_time = config('delivery_time_limit_day')*24*60*60;
            $pay_time = $order_info['pay_time'];
            $flag_time = ($pay_time + $delivery_time_limit_time) - $time;
            $count_down_time = $flag_time>0?$flag_time:0;
        }elseif (
            $order_status > 400
            && $order_status <= 800
        ){
            $order_show_status = 3;
            //提醒买家确认收货的倒计时，倒计时从发货完成的时间起，开始倒数60天（可配置）的倒计时。该60天为工作日
            $buyer_confirm_time = config('buyer_confirm_take_delivery_limit_day')*24*60*60;
            $shipments_complete_time = $order_info['shipments_complete_time'];
            $flag_time = ($shipments_complete_time + $buyer_confirm_time) - $time;
            $count_down_time = $flag_time>0?$flag_time:0;
        }else{
            $order_show_status = 4;
            ///** 未评价 **/ 订单已完成，可及时对订单进行评价。买家还有 0天00小时00分钟00秒 //TODO进行评价。
            $order_status_info = $this->getOrderStatusInfoByWhere([
                'order_id'=>$order_info['order_id'],
                'order_status'=>$order_status
            ]);
            $order_status_time = $order_status_info['create_on'];//订单状态变化时间
            //订单交易完成后可评价限制（单位：天）
            $order_review_limit_day = config('order_review_limit_day')*24*60*60;
            //订单交易完成后可追加评价限制（单位：天）（未评价）
            $append_review_limit_day = config('append_review_limit_day')*24*60*60;
            //订单交易完成后可追加评价限制（单位：天）（已评价）
            $append_have_review_limit_day = config('append_have_review_limit_day')*24*60*60;
            switch ($order_status){
                case 900://已完成
                case 1000://待评价
                    //在评价期内，提醒买家评价。注释语：订单已完成，可及时对订单进行评价。买家还有（钟表控件）14天22小时15分钟31秒进行评价
                    if (
                        ($order_status_time + $order_review_limit_day) > $time
                    ){
                        $flag_time = ($order_status_time + $order_review_limit_day) - $time;
                        $count_down_time = $flag_time>0?$flag_time:0;
                        $count_down_finish_flag = 1;//待评价，但在评价期内的倒计时
                    }else{
                        //如果订单已过评价期，但未过追评期
                        if (
                            ($order_status_time + $append_review_limit_day) > $time
                        ){
                            $flag_time = ($order_status_time + $append_review_limit_day) - $time;
                            $count_down_time = $flag_time>0?$flag_time:0;
                            $count_down_finish_flag = 2;//待评价，超过评价期但在追评期内的倒计时
                        }
                    }
                    break;
                case 1100://已评价。则注释语为：订单已评价，仍可进行追评。买家还有（钟表控件）14天22小时15分钟31秒进行追评
                case 1200://待追评
                    $flag_time = ($order_status_time + $append_have_review_limit_day) - $time;
                    $count_down_time = $flag_time>0?$flag_time:0;
                    $count_down_finish_flag = 3;//已评价，追评倒计时
                    break;
            }
        }
        $order_info['order_show_status'] = $order_show_status;
        $order_info['count_down_time'] = $count_down_time;
        $order_info['count_down_finish_flag'] = $count_down_finish_flag;
        /*获取交易唯一ID*/
        $TransactionWhere['order_number'] = $order_info['order_number'];
        $order_info['transaction_id'] = $this->getTransactionID($TransactionWhere);
        return $order_info;
    }

    /*
     * 根据订单编号获取交易唯一ID
     * */
    public function getTransactionID($where){
        $TransactionData = $this->db->table($this->order_sales_txn)->where($where)->column("payment_txn_id","txn_type");
        if(isset($TransactionData['Capture'])){
            return $TransactionData['Capture'];
        }elseif (isset($TransactionData['Purchase'])){
            return $TransactionData['Purchase'];
        }else{
            return '';
        }
    }

    /**
     * 根据条件获取订单item详情
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderItemDataByWhere(array $where){
        return $this->db->table($this->order_item)->where($where)->select();
    }

    /**
     * 根据条件获取订单留言信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderMessageDataByWhere(array $where){
        return $this->db->table($this->order_message)->where($where)->select();
    }

    /**
     * 根据条件获取订单邮寄信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderShippingAddressDataByWhere(array $where){
        return $this->db->table($this->order_shipping_address)->where($where)->select();
    }

    /**
     * 根据条件获取订单状态数据
     * @param array $where
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderStatusInfoByWhere(array $where){
        return $this->db->table($this->order_status_change)->where($where)->order(['create_on'=>'desc'])->find();
    }

    /**
     * 获取买家订单相关数量
     * @param array $params
     * @return array
     */
    public function getOrderNumForUser(array $params){
        $rtn = ['all_order'=>0,'awaiting_payment'=>0,'awaiting_shipment'=>0,'awaiting_delivery'=>0,'awaiting_review'=>0,'dispute'=>0];
        $customer_id = $params['customer_id'];
        $base_where = ['customer_id'=>$customer_id,'order_master_number'=>['neq',0],'delete_time'=>0];
        //All orders 数量
        $rtn['all_order'] = $this->db->table($this->order)->where($base_where)->count();
        //Awaiting payment 数量
        $rtn['awaiting_payment'] = $this->db->table($this->order)
            ->where($base_where)
            ->where(['order_status'=>100])
            ->count();
        //Awaiting shipment 数量
        $rtn['awaiting_shipment'] = $this->db->table($this->order)
            ->where($base_where)
            ->where(['order_status'=>400])
            ->count();
        //Awaiting delivery 数量 700
        $rtn['awaiting_delivery'] = $this->db->table($this->order)
            ->where($base_where)
            ->where(['order_status'=>700])
            ->count();
        //Awaiting Review 数量 1000
        $rtn['awaiting_review'] = $this->db->table($this->order)
            ->where($base_where)
            ->where(['order_status'=>1000])
            ->count();
        //Dispute 数量
        $rtn['dispute'] = $this->db->table($this->order_after_sale_apply_log)
            ->where(['user_id'=>$customer_id, 'user_type'=>1, 'log_type'=>1])
            ->count();
        return $rtn;
    }

    /*
     * 获取订单编号
     * */
    public function getOrderNumberByTrackingNumber($tracking_number){
        $res = $this->db->table($this->order_package)->where(['tracking_number'=>$tracking_number])->value("order_number");
        return $res;
    }

    /**
     * 获取已发货、规定时间范围内的订单数据【同步信息至OMS专用】
     * @param array $params
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getProductSalesWhenFulfillment(array $params){
        $start_date = $params['start_date'];
        $end_date = $params['end_date'];
        return $this->db->table($this->order)->alias("o")
            ->join($this->order_item." oi","o.order_id = oi.order_id")
            ->where("o.fulfillment_status = 600 AND o.create_on >= $start_date AND o.create_on <= $end_date ")
            ->field('o.order_id, o.order_number, o.fulfillment_status, o.create_on, oi.product_id, oi.sku_id, oi.sku_num, oi.product_nums')
            ->select();
    }

    /**
     * 根据条件获取订单数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function geOrderDataByWhere(array $where){
        return $this->db->table($this->order)->where($where)->select();
    }

    /*
     * 订单商品
     * */
    public function getOrderItem($where){
        return $this->db->table($this->order_item)->where($where)->field("product_id,sku_id,product_nums")->select();
    }

    /**
     * @param $where
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function transactionIdProcess($data){
        if(isset($data['OrderNumber']) && isset($data['TransactionID'])){
            try{
                if(isset($data['risky']) && $data['risky']){
                    $orderUpdataForStatus['order_branch_status'] = 105;//进入风控
                    //$orderUpdataForStatus['lock_status'] = 70;
                }
                $orderUpdataForStatus['transaction_id'] = $data['TransactionID'];
                $orderUpdataForStatus['order_status'] = isset($data['OrderStatus'])?$data['OrderStatus']:120;
                $orderUpdataForStatus['pay_time'] = time();
                $orderUpdataForStatus['boleto_url'] = isset($data['boleto_url'])?$data['boleto_url']:'';
                $orderUpdataForStatusWhere['order_master_number'] = $data['OrderNumber'];
                $orderUpdataForStatusWhere['order_status'] = 100;
                $res = $this->db->table($this->order)->where($orderUpdataForStatusWhere)->update($orderUpdataForStatus);
                return $res;
            }catch (\Exception $e){
                Log::record('transactionIdProcess '.$e->getMessage());
                return false;
            }

        }
        return false;
    }

    /**
     * 退款结果订单相关处理逻辑
     * @param $order_number 要处理的订单号
     * @param $flag 标识：1-退款成功；2-退款失败
     * @return int|string
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function handleOrderInfoForRefund($order_number, $flag){
        $where = ['order_number'=>$order_number];
        $order_info = $this->db->table($this->order)->where($where)->find();
        //退款成功
        if ($flag == 1){
            //修改订单正在退款金额，已退款金额，实收金额
            $exchange_rate = $order_info['exchange_rate'];
            $refunded_amount = $order_info['refunding_amount'];
            $captured_amount = ($order_info['captured_amount'] - $refunded_amount)>0?($order_info['captured_amount'] - $refunded_amount):0;
            $captured_amount_usd = round(($captured_amount / $exchange_rate), 2);
            $res = $this->db->table($this->order)->where($where)->update([
                'refunding_amount'=>0, //退款中金额
                'refunded_amount'=>$refunded_amount, //已退款金额
                'captured_amount'=>$captured_amount, //实收金额（如果退款，这个金额会变动）
                'captured_amount_usd'=>$captured_amount_usd, //以美元为单的实收总金额（如果退款，这个金额会变动）
            ]);
        }elseif($flag == 2) {
            //退款失败 1).将订单状态修改为200 2).将退款中金额修改为0
            $res = $this->db->table($this->order)->where($where)->update([
                'refunding_amount'=>0, //退款中金额
                'order_status'=>200, //订单状态
            ]);
        }
        return $res;
    }

    /*
     * 增加订单退款中金额
     * */
    public function refundingAmount($where,$amount){
        return $this->db->table($this->order)->where($where)->setInc("refunding_amount",$amount);
    }


    /**
     * 根据条件更改订单状态【多个地方调用，修改时注意】
     * @param array $params 条件 格式如下：
     *  [
     *      'order_id'=>20, //订单ID
     *      'order_status_from'=>100, //修改前状态
     *      'order_status'=>200, //修改后状态
     *      'change_reason'=>, //修改原因
     *      'create_on'=>, //修改时间
     *      'create_by'=>, //修改人
     *      'create_ip'=>, //创建者IP
     *      'chage_desc'=>, //修改描述
     * ]
     * @return bool
     * @throws \Exception
     * @throws \think\exception\PDOException
     */
    public function updateOrderStatus(array $params){
        $rtn = true;
        //是否开启事务：1-开启（默认），2-不开启
        $is_start_trans = isset($params['is_start_trans'])?$params['is_start_trans']:1;
        if ($is_start_trans == 1){
            $this->db->startTrans();
        }
        try{
            $order_status_from = $params['order_status_from'];
            $order_status = $params['order_status'];//修改后的状态
            if(!isset($params['order_branch_status'])){
                if ($order_status_from == $order_status){
                    return $rtn;
                }
            }
            /** 1/更改订单状态 **/
            $where['order_id'] = $params['order_id'];
            $create_on = $params['create_on'];
            $create_by = $params['create_by'];
            //更新的数据
            $up_data['order_status'] = $order_status;
            if (isset($params['order_branch_status'])){
                $up_data['order_branch_status'] = $params['order_branch_status'];
            }
            if (isset($params['payment_status'])){
                $up_data['payment_status'] = $params['payment_status'];
            }
            //进入事后风控次数
            if (isset($params['risky_nums'])){
                $up_data['risky_nums'] = $params['risky_nums'];
            }
            //支付时间
            if (isset($params['pay_time'])){
                $up_data['pay_time'] = $params['pay_time'];
            }
            //发货状态
            if (isset($params['fulfillment_status'])){
                $up_data['fulfillment_status'] = $params['fulfillment_status'];
            }
            //发货时间
            if (isset($params['shipments_time'])){
                $up_data['shipments_time'] = $params['shipments_time'];
            }
            //状态回调成功将fulfillment_status修改为400:待发货
            if ($order_status == 200){
                $up_data['fulfillment_status'] = 400;
            }
            $up_data['modify_on'] = $create_on; //修改时间
            $up_data['modify_by'] = $create_by; //修改者

            //TODO 【不确定】是否根据状态判断同步更新：完成时间、修改者、修改时间、支付时间、发货时间、发货完成时间
            /*switch ($order_status){
                case 200:
                    break;
            }*/
            $this->db->table($this->order)->where($where)->update($up_data);
            /** 2/记录订单状态修改记录 **/
            $insert_data = [
                'order_id'=>$params['order_id'],
                'order_status_from'=>$params['order_status_from'],
                'order_status'=>$params['order_status'],
                'change_reason_id'=>isset($params['change_reason_id'])?$params['change_reason_id']:'',
                'change_reason'=>$params['change_reason'],
                'create_on'=>$create_on,
                'create_by'=>$create_by,
                'create_ip'=>$params['create_ip'],
                'chage_desc'=>$params['chage_desc'],
            ];
            $this->db->table($this->order_status_change)->insert($insert_data);
            /** 3/同步更新admin库下的dx_affiliate_order表下的order_status **/
            $order_info = $this->db->table($this->order)->where($where)->find();
            if (!empty($order_info['affiliate'])){
                $res = doCurl(
                    API_URL.'admin/Affiliate/updateAffiliateOrderStatus',
                    ['order_number'=>$order_info['order_number'], 'order_status'=>$order_status],
                    null,
                    true);
                //$base_api = new BaseApi();
                //$res = $base_api->updateAffiliateOrderStatus(['order_number'=>$order_info['order_number'], 'order_status'=>$order_status]);
                Log::record('修改订单状态系统-更新affiliate订单结果 '.json_encode($res));
            }
            /*if ($res['code'] != 200){
                throw new \Exception('修改affiliate订单状态失败'.$res['msg']);
            }*/
            if ($is_start_trans == 1){
                $this->db->commit();
            }
        }catch (\Exception $e){
            $rtn = false;
            Log::record('修改订单状态系统异常 '.$e->getMessage());
            if ($is_start_trans == 1){
                $this->db->rollback();
            }
        }
        return $rtn;
    }

    /*
     * 获取订单退款数据
     * */
    public function getRefundedAmount($where){
        $RefundedAmount = $this->db->table($this->order)->where($where)->group("currency_code")->field("SUM(refunded_amount) sum_refunded_amount,currency_code")->select();
        return $RefundedAmount;
    }
}