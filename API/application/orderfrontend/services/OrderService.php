<?php
namespace app\orderfrontend\services;

use app\mallextend\controller\SysConfig;
use app\orderfrontend\model\OrderModel;
use app\common\helpers\RedisClusterBase;
use app\share\controller\EmailHandle;
use think\Cache;
use think\Log;
use think\Monlog;

/**
 * 订单接口处理逻辑
 * @author gz
 * 2018-05-01
 */
class OrderService
{
    const CACHE_KEY = 'Order:';
    const CACHE_TIME = 360;

    private $model;
    public $redis;
    public function __construct(){
        $this->redis = new RedisClusterBase();
    	
    }
    
    /**
     * 提交订单
     * @param unknown $params
     * @return Ambigous <\app\orderfrontend\model\true/false, boolean, multitype:NULL , multitype:string >|boolean
     */
    public function submitOrder($params){
//     	$ret = (new OrderModel())->submitOrder($params);
//     	exit;
    	if(!empty($params)){

    		$ret = (new OrderModel())->submitOrder($params);

    		if($ret){
                $is_category_err = 0;
                $all_category_err_product = [];
    		    /** 增加超过5分钟没有订单，则发送邮件提醒功能 张（勇）总定 tinghu.liu 20190829 **/
                $this->redis->set('submitOrderCreateNewestTime', time());
    		    /** 增加统计用户下单数量功能 start tinghu.liu 20190404 **/
                $_params = isset($params['cart_info_res']['slave'])?$params['cart_info_res']['slave']:[];
                $customer_id = isset($params['cart_info_res']['master']['customer_id'])?$params['cart_info_res']['master']['customer_id']:0;
                //主订单号以返回的为准 tinghu.liu 20191108
                $order_master_number = isset($ret['master']['order_number'])?$ret['master']['order_number']:'';
                $order_count = count($_params);
                if ($customer_id != 0 && !empty($order_master_number)){
                    $this->incCustomerOrderCountQueue($customer_id,$order_master_number, $order_count);
                }
                /*** end ***/
                /** 增加nocnoc拆单逻辑（此拆单是在子单的基础上根据nocnoc运输方式进行拆分） start tinghu.liu 20190411 **/
                if (isset($ret['slave']) && !empty($ret['slave'])){
                    foreach ($ret['slave'] as $k=>$v){
                        if ($v['is_nocnoc'] == 1){
                            $_count_order_res = $this->redis->lPush('nocnocOrderSplit', json_encode([
                                'order_master_number'=>$order_master_number,
                                'order_number'=>$v['order_number']
                            ]));
                            if (!$_count_order_res){
                                Log::record('队列nocnocOrderSplit错误：data:'.json_encode($ret));
                                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,[],null,'队列nocnocOrderSplit错误：data:'.json_encode($ret), $customer_id, $order_master_number);
                            }
                        }

                        if (
                            isset($v['category_err_flag']['is_category_err'])
                            && $v['category_err_flag']['is_category_err'] == 1
                            && isset($v['category_err_flag']['err_product_arr'])
                            && !empty($v['category_err_flag']['err_product_arr'])
                        ){
                            $is_category_err = 1;
                            foreach ($v['category_err_flag']['err_product_arr'] as $k100=>$v100){
                                $all_category_err_product[] = $v100;
                            }
                        }
                    }
                }
                /** end **/
                /**
                 * 如果优惠比例大于10% ，需要特殊处理   tinghu.liu 20190619
                 * 1. 自动加锁；
                 * 2. 记录异常订单，在amdin显示
                 * 3. 异常订单自动邮件通知报警；
                 */
                $this->handleOrderDiscountException($params, $order_master_number);

                /**
                 * 如果有产品一级、二级分类为0，则发送邮件提醒 tinghu.liu 20191023
                 */
                if ($is_category_err == 1 && !empty($all_category_err_product)){
                    $email = $this->getOrderDiscountExceptionReceiveEmail();
                    $all_category_err_product_json = json_encode($all_category_err_product);

                    $email_content = <<< CCT
<div>
<p>异常描述：生成订单号异常，有产品一级或二级分类为0 </p>
<p>主订单号：{$order_master_number} </p>
<p>异常产品：{$all_category_err_product_json} </p>
</div>
CCT;
                    $paramsEmail['to_email'] = $email;
                    $paramsEmail['title'] = '('.THINK_ENV.') 生成订单号异常，有产品一级或二级分类为0 ';
                    $paramsEmail['content'] = $email_content;
                    (new EmailHandle())->sendEmail($paramsEmail);
                }
    			return $ret;
    		}else{
    			return false;
    		}
    		
    	}
    	return false;
    
    }

    /**
     * 记录用户下单数量
     * @param $customer_id
     * @param $order_master_number
     * @param $order_count
     */
    private function incCustomerOrderCountQueue($customer_id,$order_master_number, $order_count){
        $_count_order_data = json_encode([
            'CustomerID'=>$customer_id,
            'OrderCount'=>$order_count
        ]);
        $_count_order_res = $this->redis->lPush("IncCustomerOrderCountKey", $_count_order_data);
        if (!$_count_order_res){
            Log::record('IncCustomerOrderCountKey- data:'.$_count_order_data.', res:'.$_count_order_res);
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_count_order_data,'IncCustomerOrderCountKey',$_count_order_res, $customer_id, $order_master_number);
        }
    }
    
    /**
     * 根据订单ID获取订单的编号
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderNumberByOrderId($params){
    	 
    	if(!empty($params)){
    		 //数组转成字符串
    		$params = implode(',',$params);

    		$ret = (new OrderModel())->getOrderNumberByOrderId($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }
    
    /**
     * 获取待支付的订单信息
     * @param unknown $params
     * @return unknown|boolean
     */
    public function getPayOrderInfo($params){
    	if(!empty($params)){
    		$ret = (new OrderModel())->getPayOrderInfo($params);
    		//return '123456789';
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	//return '999';
    	return false;
    
    }

    public function getOrderNumberByOrderMasterNumber($params){
        if(!empty($params)){
            $ret = (new OrderModel())->getOrderNumberByOrderMasterNumber($params);
            if($ret){
                return $ret;
            }else{
                return false;
            }

        }
        //return '999';
        return false;

    }
    
    /**
     * 获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderShippingAddress($params){
    
    	if(!empty($params)){
    		$ret = (new OrderModel())->getOrderShippingAddress($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }
    
    /**
     * 根据订单编号获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderAddressByOrderNumber($params){
    
    	if(!empty($params)){
    		$ret = (new OrderModel())->getOrderAddressByOrderNumber($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }
    
    /**
     * 改变订单状态
     * @param array $params
     * @return unknown|boolean
     */
    public function changeOrderStatus($params){
    
    	if(!empty($params)){
    		$ret = (new OrderModel())->changeOrderStatus($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }

    /**
     * 根据订单编号获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderInfoByOrderMasterNumber($params){
    	if(!empty($params)){
    		$ret = (new OrderModel())->getOrderInfoByOrderMasterNumber($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    	}else{
    		return false;
    	}
    }

    /**
     * 根据订单编号获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function transactionIdProcess($params){
        if(!empty($params)){
            $ret = (new OrderModel())->transactionIdProcess($params);
            if($ret){
                return $ret;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 处理订单折扣异常
     * @param $params
     * @param $order_master_number
     * @return bool
     */
    public function handleOrderDiscountException($params, $order_master_number){
        $master_order = isset($params['cart_info_res']['master'])?$params['cart_info_res']['master']:[];
        $slave_order = isset($params['cart_info_res']['slave'])?$params['cart_info_res']['slave']:[];
        $master_discount_total = isset($master_order['discount_total'])?$master_order['discount_total']:0;
        $master_goods_total = isset($master_order['goods_total'])?$master_order['goods_total']:0;
        //实收（已减去折扣，包含coupon、活动价、批发价）
        $master_grand_total = isset($master_order['grand_total'])?$master_order['grand_total']:0;
        //运费
        $master_shipping_fee = isset($master_order['shipping_fee'])?$master_order['shipping_fee']:0;
        //使用coupon的总价
        $master_coupon_price_total = isset($master_order['coupon_price_total'])?$master_order['coupon_price_total']:0;
        if (empty($master_order) || empty($slave_order) || $master_goods_total <= 0){
            return true;
        }
        //1.获取异常配置
        $res = (new SysConfig())->getSysCofigValue(['ConfigName'=>'OrderDiscountException']);
        $res = json_decode(json_encode($res), true);
        if (isset($res['code']) && $res['code'] == 200 && isset($res['data']) ){
            $config = $res['data'];
            $discount_limit = $config['discount_limit'];
            $email = $config['email'];
        }else{
            //默认值
            $discount_limit = 0.1;
            $email = ["zhangheng@comepro.com", "liukai@comepro.com", "liuth@comepro.com"];
        }
//        $discount = round($master_discount_total/$master_goods_total, 4);
        /**
         * 折扣比例，只计算coupon和产品实收价格的比例，公式：Coupon优惠金额/（商品总计-FlashDeal的活动折扣价、批发价折扣）
         * tinghu.liu 20190814
         * 订单实收 = （$_order_goods_total + $_order_shipping_fee） - （活动/批发价折扣+coupon折扣）
         */
        $total_flag = ($master_grand_total-$master_shipping_fee)+$master_coupon_price_total;
        $discount = round($master_coupon_price_total/$total_flag, 4);
        if (
            $discount > $discount_limit
        ){
            Log::record('getSysCofigValue,$res:'.json_encode($res));
            /**
             * 2.如果符合则：
             *      a.自动加锁
             *      b.记录异常订单，在amdin显示
             *      c.异常订单自动邮件通知报警；
             */
            //a.自动加锁; b.记录异常订单，在amdin显示
            $remark = 'Coupon总折扣和产品实付总额比例超过：'.($discount_limit*100).'%。（master_coupon_price_total:'.$master_coupon_price_total.', master_goods_total:'.$total_flag.'）';
            $res1 = (new OrderModel())->handleDiscoutExceptionOrder($master_order, $slave_order, $remark);
            Log::record('handleDiscoutExceptionOrder, $res1: '.$res1, Log::NOTICE);
            //c.异常订单自动邮件通知报警；
            $paramsEmail['to_email'] = $email;
            $paramsEmail['title'] = '('.THINK_ENV.') API Order discounts exceed '.($discount_limit*100).'%. order_master_number:'.$order_master_number;
            $paramsEmail['content'] = 'Order discounts exceed '.($discount_limit*100).'%. order_master_number:'.$order_master_number.', $discount:'.($discount*100).'%($master_coupon_price_total:'.$master_coupon_price_total.',  $master_goods_total:'.$total_flag.')';
            $res2 = (new EmailHandle())->sendEmail($paramsEmail);
            Log::record('handleDiscoutExceptionOrder,sendEmail, $res2: '.json_encode($res2), Log::NOTICE);
        }
    }
    
    /**
     * 提交订单
     * @param unknown $params
     * @return Ambigous <\app\orderfrontend\model\true/false, boolean, multitype:NULL , multitype:string >|boolean
     */
    public function submitOrderForBlockChain($params){
    	if(!empty($params)){
    		$ret = (new OrderModel())->submitOrderForBlockChain($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    	}
    	return false;

    }
}
