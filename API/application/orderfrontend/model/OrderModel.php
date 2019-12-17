<?php
namespace app\orderfrontend\model;

use app\admin\model\OrderMessageTemplateModel;
use app\common\controller\Email;
use app\common\helpers\CommonLib;
use app\common\services\CommonService;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;
use think\Log;
use think\Monlog;

/**
 * 订单模型
 * @author gz
 * 2018-04-19
 */
class OrderModel extends Model{

	private $db;
    private $admin_db;
    private $mongo_db;
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
    private $question = "sl_question";
    private $dx_sales_order_discount_exception = "dx_sales_order_exception";
    private $order_status_process = "dx_order_status_process";
    private $dx_sales_order_status_oms_record = "dx_sales_order_status_oms_record";
    private $dx_order_refund = "dx_order_refund";
    private $dx_sales_order_refund_operation = "dx_sales_order_refund_operation";
    private $dx_order_number_generate = "dx_order_number_generate_config";
    private $dx_order_pay_token = "dx_order_pay_token";
    private $product = 'dx_product';
    private $coupon = 'dx_coupon';
    private $product_virtual = 'dx_product_virtual';

    private $dx_block_chain_order = 'dx_block_chain_order';
    private $dx_block_chain_order_item = 'dx_block_chain_order_item';
    private $dx_block_chain_order_shipping_address = 'dx_block_chain_order_shipping_address';
    private $dx_block_chain_order_status_change = 'dx_block_chain_order_status_change';
    private $dx_block_chain_order_sales_txn = 'dx_block_chain_order_sales_txn';

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
        $this->seller_db = Db::connect('db_seller');
        $this->mongo_db = Db::connect('db_mongodb');
    }

    /**
     * 订单生成
     * @param array $params
     * @return true/false
     */
    public function submitOrder($params){
        $_params = $params['cart_info_res']['slave'];
        $_master_params = $params['cart_info_res']['master'];
        $_order_master_number = $_master_params['order_number'];

        Log::record('submitOrder, params'.$_order_master_number.':'.json_encode($params));

        $_pay_token = CommonLib::generatePayToken($_order_master_number);
    	$_order_id = array('pay_token'=>$_pay_token);

    	//支付Token数据
        $_pay_token_params['order_master_number'] = $_order_master_number;
        $_pay_token_params['pay_token'] = $_pay_token;
        $_pay_token_params['create_on'] = $_master_params['create_on'];
        $_pay_token_params['add_time'] = $_master_params['add_time'];

        /**
         * 之前的订单号：      191104+1001+23299438
         * 新的的订单号：      191104+1001+23+29943+8
         *     18位 = 年月日（6位）+机器码（4位）+时（2位）+自增步长（5位，根据订单表自增ID来，计算后加上初始值。自增步长为1，初始值为10100）+随机数（1位）
         *
         * 增加用户每日下单量异常（比如：每日大于50个单）监控，有异常则自动禁用
         * 重新生成订单号 初始化数据 start
         * tinghu.liu 20191105
         */
        $current_day_time = strtotime(date('Y-m-d H:00:00'));
        $on_current_day_Ymd = mb_substr(date('Ymd'), 2, 6);
//        $on_machine_id = 1001;
        $on_machine_id = config('machine_id');
        $on_current_day_h = date('H');
        $order_arr_flag = $this->db->table($this->order)->where('create_on', '<', $current_day_time)->order('order_id','desc')->field('order_id,order_number,add_time,create_on')->find();
        $on_step_init = 10100;
        $on_step_flag = $order_arr_flag['order_id'];

//        pr($on_step_flag);
        $final_order_number_arr = [];
        /************* 重新生成订单号 初始化数据 end ******************/
    	if(isset($_params[1]['order'])){
    		//多商家的订单，需拆分订单
    		$this->db->startTrans();
    		try {
    			//1、插入master订单
    			//$_order_id['master'][] = $this->insertOrder($_master_params);
    			$this->db->table($this->order)->insert($_master_params);
                $master_order_id = $this->db->getLastInsID();
    			$_order_id['master']['order_id'] = $master_order_id;

    			/***** 重新生成主订单号 start  tinghu.liu 20191105 ****/
                $on_master_austep_5 = ($master_order_id - $on_step_flag) + $on_step_init;
                $on_master_austep_5 = str_pad($on_master_austep_5, 5, "0", STR_PAD_LEFT);
//                var_dump($on_master_austep_5);die;
                $_order_master_number = $on_current_day_Ymd.$on_machine_id.$on_current_day_h.$on_master_austep_5.mt_rand(0,9);
                $_order_master_number = mb_substr($_order_master_number, 0, 18);
                //重置支付Token主订单号
                $_pay_token_params['order_master_number'] = $_order_master_number;
                $_pay_token = CommonLib::generatePayToken($_order_master_number);
                $_pay_token_params['pay_token'] = $_pay_token;
                $_order_id['pay_token'] = $_pay_token;
                //更新主订单号
                $this->db->table($this->order)->where(['order_id'=>$master_order_id])->update(['order_number'=>$_order_master_number]);
                //建立原订单号和新单号的关联
                $final_order_number_arr[$_master_params['order_number']] = $_order_master_number;

                //记录入参订单号
                $_insert_order_other = [];
                $_insert_order_other['order_id'] = $master_order_id;
                $_insert_order_other['ref3'] = '入参订单号：'.$_master_params['order_number'];
                $_insert_order_other['create_on'] = isset($_master_params['create_on'])?$_master_params['create_on']:time();
                $this->db->table($this->order_other)->insert($_insert_order_other);

    			/***** 重新生成主订单号 end ****/
    			$_order_id['master']['order_number'] = $_order_master_number;

    			//2、插入slave订单
    			foreach ($_params as $k=>$v){
    				$order = $v;
    				//写入到订单表，订单商品表，订单留言表
                    $insert_res = $this->insertOrder($order, $on_current_day_Ymd, $on_machine_id, $on_current_day_h, $on_step_flag, $on_step_init, $_order_master_number, $final_order_number_arr);
                    if (is_array($insert_res)){
                        $_order_id['slave'][] = $insert_res;
                    }else{
                        $this->db->rollback();
                        return false;
                    }
                }

    			if(count($_order_id) > 0){
                    //生成支付Token
                    $this->db->table($this->dx_order_pay_token)->insert($_pay_token_params);

                    $this->db->commit();
                    $_order_id['order_number_relation'] = $final_order_number_arr;
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
                $insert_res = $this->insertOrder($order, $on_current_day_Ymd, $on_machine_id, $on_current_day_h, $on_step_flag, $on_step_init, '', $final_order_number_arr);


                if (is_array($insert_res)){
                    $_order_number = $insert_res['order_number'];

                    $_order_id['slave'][] = $insert_res;

                    $_order_id['master']['order_id']  = $_order_id['slave'][0]['order_id'];
//                    $_order_id['master']['order_number'] = $_master_params['order_number'];//$_order_id['slave']['order_number']
                    $_order_id['master']['order_number'] = $_order_number;//$_order_id['slave']['order_number']

                    if(count($_order_id) > 0){
                        //生成支付Token
                        $_pay_token_params['order_master_number'] = $_order_number;
                        $_pay_token = CommonLib::generatePayToken($_order_number);
                        $_pay_token_params['pay_token'] = $_pay_token;
                        $_order_id['pay_token'] = $_pay_token;

                        $this->db->table($this->dx_order_pay_token)->insert($_pay_token_params);

                        $this->db->commit();

                        $_order_id['order_number_relation'] = $final_order_number_arr;

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
     * 生成订单号
     *
     * 订单号后8位创建规则：
     * 1.初始值（A）：00010010
     * 2.获取增量后的值（B）（步长为X[1至66之间的随机数]），B以天为单位每天初始化开始值为8。如：初始增量（8） + 步长（X=1） = 9
     *
     * 3.获取1至255内随机数（C1），如：88；获取1至101内随机数（C2），如：99
     *
     * 4.获取当前微秒数随机数（D）。D = 当前秒（1566203115）后两位相加（1+5）+当前微妙数（0.65420000）相加（6+5+4+2）= 23
     *
     * 5.更新生成的增量B为：B+C1+C2+D；如果是新的一天，则初始化增量B为8；
     *
     * 6.根据前面几步获取最终8位数（$FN）。$FN = A+B+C1+C2+D 。如果$FN不足8位数，则在前面补0为8位数；如果$FN>99999999，则赋值为00010010
     *
     * 注：
     *      1.根据此规则，一天内生成的订单数大概至少为（99999999 - 10010010）/(8+66+255+101+9*6) = 185929 个
     *      2.考虑并发情况（所以使用随机数）
     *
     * @param int $site_id 站点ID：1-DX
     * @return string
     */
    public function generateOrderNumber($site_id = 1){
        $microtime = microtime();
        list($micro, $sec) = explode(" ", $microtime);
        $time = time();
        $ymd_new = date('Y-m-d', $time);
        $ymd_new_for_order_number = date('Ymd', $time);
        $ymdhis_new = date('Y-m-d H:i:s', $time);
        $rand_min = 1;
        $order_number_8_arr = [];
        $order_number = '';
        //如果是新的一天，则需要重置初始值为 8
        $params_init_b = 8;

        $config_data = $this->db->table($this->dx_order_number_generate)->where(['site_id'=>$site_id])->find();
        //初始值（A）
        $A = $config_data['init_number'];
        //获取增量后的值（B）
        $B = $config_data['current_number'];
        //机器ID
        $machine_id = $config_data['machine_id'];
        //随机步长X
        $random_step = $config_data['random_step'];
        //随机数（C1）
        $random1 = $config_data['random1'];
        //随机数（C2）
        $random2 = $config_data['random2'];
        //当前天（存储在数据库的）
        $current_date = $config_data['current_date'];
        //当前天（实际的）
        $current_date_new = $ymd_new;
        //更新时间
        $update_time = $ymdhis_new;
        //生成的订单数量（当前天，每天都会从0开始）
        $order_number_nums = $config_data['order_number_nums'];
        $order_number_nums_new = $order_number_nums +1;

        $X = rand($rand_min, $random_step);
        $C1 = rand($rand_min, $random1);
        $C2 = rand($rand_min, $random2);

        /********** 获取D start ************/
        $d_str = substr($sec, -2, 2).($micro*1000000);
        $d_str_arr = str_split($d_str,1);
        $D = array_sum($d_str_arr);
        /********** 获取D end ************/

        /********** 获取订单号后8位，不足8位填充0 start ***************/
        $order_number_8_arr[0] = $A;
        $order_number_8_arr[1] = $B;
        $order_number_8_arr[2] = $X;
        $order_number_8_arr[3] = $C1;
        $order_number_8_arr[4] = $C2;
        $order_number_8_arr[5] = $D;
        $order_number_8_end = str_pad(array_sum($order_number_8_arr), 8, "0", STR_PAD_LEFT);
        /********** 获取订单号后8位，不足8位填充0 end ***************/

        /***************** 获取初始化值 start ******************/
        $order_number_8_arr_update = $order_number_8_arr;
        //去掉初始值A
        unset($order_number_8_arr_update[0]);
        $B_update = array_sum($order_number_8_arr_update);
        //如果是新的一天，则需要重置初始值为 8
        if ($current_date != $current_date_new){
            $B_update = $params_init_b;
            $order_number_nums_new = 0;
        }
        /***************** 获取初始化值 end   ******************/
        //组装生成的订单号
        $order_number = $ymd_new_for_order_number.$machine_id.$order_number_8_end;
        //更新当前值。每天都会初始化值为8，每次生成订单号，会更新；每一次生成订单号会使用这个值
        $res = $this->db->table($this->dx_order_number_generate)
            ->where(['site_id'=>$site_id])
            ->update([
                'current_number'=>$B_update,
                'current_date'=>$current_date_new,
                'order_number_nums'=>$order_number_nums_new,
                'update_time'=>$update_time,
                'update_remark'=>'最新订单号：'.$order_number,
            ]);

        $this->db->table('order_number_test')->insert(['order_number'=>$order_number, 'add_time'=>date('Y-m-d H:i:s', $time).'.'.($micro*1000000)]);
        return $order_number;
    }

    /**
     * 订单写入处理方法
     * 写入到订单表，订单商品表，订单留言表
     * @param $order
     * @return bool
     */
    public function insertOrder($order, $on_current_day_Ymd, $on_machine_id, $on_current_day_h, $on_step_flag, $on_step_init, $_new_order_master_number='', &$final_order_number_arr=[]){
        $_order_data = $order['order'];
        try{
            $_create_on = isset($_order_data['create_on'])?$_order_data['create_on']:time();
            //入参时候的订单号
            $_old_order_number = $_order_data['order_number'];
            $_currency_code = $_order_data['currency_code'];
            $_payment_currency_code = $_currency_code;
            $_pay_channel = $_order_data['pay_channel'];

            //如果是ARS且是Astropay，更新支付实收金额为USD tinghu.liu 20191121
            if (strtolower($_currency_code) == strtolower('ARS') && strtolower($_pay_channel) == strtolower('Astropay')){
                $_payment_currency_code == 'USD';
            }

            $this->db->table($this->order)->insert($_order_data);
            $_order_id = $this->db->getLastInsID();

            /***** 重新生成子订单号 start  tinghu.liu 20191105 ****/
            $on_austep_5 = ($_order_id - $on_step_flag) + $on_step_init;
            $on_austep_5 = str_pad($on_austep_5, 5, "0", STR_PAD_LEFT);
            $_order_number = $on_current_day_Ymd.$on_machine_id.$on_current_day_h.$on_austep_5.mt_rand(0,9);
//            pr($_order_number);
            $_order_number = mb_substr($_order_number, 0, 18);
            //更新子订单号
            $_order_master_number = $_order_number;//初始化，单店铺的情况
            if (!empty($_new_order_master_number)){//多店铺的情况
                $_order_master_number = $_new_order_master_number;
            }
            $this->db->table($this->order)->where(['order_id'=>$_order_id])->update(['order_number'=>$_order_number,'order_master_number'=>$_order_master_number]);

            //建立原订单号和新单号的关联
//            $tmp_order_number = [];
//            $tmp_order_number['old_number'] = $_order_data['order_number'];
//            $tmp_order_number['new_number'] = $_order_number;
//            $tmp_order_number[$_order_data['order_number']] = $_order_number;
//            $final_order_number_arr[] = $tmp_order_number;
            $final_order_number_arr[$_order_data['order_number']] = $_order_number;


            /***** 重新生成子订单号 end ****/

            //是否是nocnoc订单，0-不是，1-是 tinghu.liu 20190411
            $is_nocnoc = 0;
            //是否有除了nocnoc之外的其他运输方式，0-不是，1-是 tinghu.liu 20190413
            $is_no_nocnoc = 0;
            $is_category_err = 0;
            $category_err_arr = [];
            foreach ($order['order_item'] as $k2=>$v2){
//                Log::record('order_item:'.json_encode($order['order_item']));
                $_order_item = $v2;
                $_order_item['order_id'] = $_order_id;
                //nocnoc订单判断，0-不是，1-是 tinghu.liu 20190411
                if (
                    isset($v2['shipping_model'])
                    && !empty($v2['shipping_model'])
                    && strtolower($v2['shipping_model']) == 'nocnoc'
                ){
                    $is_nocnoc = 1;
                }
                //nocnoc订单判断，0-不是，1-是 tinghu.liu 20190411
                if (
                    isset($v2['shipping_model'])
                    && !empty($v2['shipping_model'])
                    && strtolower($v2['shipping_model']) != 'nocnoc'
                ){
                    $is_no_nocnoc = 1;
                }
                //增加产品一级、二级分类为0提醒 tinghu.liu 20191023
                if (
                    isset($v2['first_category_id'])
                    && isset($v2['second_category_id'])
                    &&
                    (
                        (
                            $v2['first_category_id'] == 0
                            && $v2['second_category_id'] == 0
                        )
                        || $v2['first_category_id'] == 0
                        || $v2['second_category_id'] == 0
                    )
                ){
                    $is_category_err = 1;
                    $category_err_arr[] = $v2['product_id'];
                }
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
            $status_change['chage_desc'] = 'Create order';
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
                    /**
                     * 更新CIC生成的Coupon使用记录订单号数据 tinghu.liu 20191113
                     */
                    $url = CIC_API."/cic/MyCoupon/updateCouponForOrder";
                    $coupon_update_params = [
                        'coupon_id'=>$_order_coupon['coupon_id'],
                        'coupon_code'=>$_order_coupon['coupon_code'],
                        'order_number'=>$_old_order_number,
                        'new_order_number'=>$_order_number];
                    $use_coupon_res = doCurl($url,$coupon_update_params,null,true);
                    if (
                        empty($use_coupon_res)
                        || !isset($use_coupon_res['code'])
                        || $use_coupon_res['code'] != 200
                    ){
                        $err_msg = '更新CIC生成的Coupon使用记录订单号数据失败。params：'.json_encode($coupon_update_params).', res：'.json_encode($use_coupon_res);
                        Log::record($err_msg, Log::ERROR);
                        Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_order_master_number,[],$url,$err_msg, $_order_data['customer_id'], $_order_master_number, $_order_number);
                    }
                }
            }
            if(isset($order['shipping_address'])){
                $_order_shipping['shipping_address'] = isset($order['shipping_address'])?$order['shipping_address']:'';
                $_order_shipping['shipping_address']['order_id'] = $_order_id;
                $this->db->table($this->shipping_address)->insert($_order_shipping['shipping_address']);
            }
            //20190107 新增订单扩展表数据
            if(isset($order['order_other']) && !empty($order['order_other'])){
                $_insert_order_other = $order['order_other'];
                $_insert_order_other['order_id'] = $_order_id;
                if (isset($_insert_order_other['ref3'])){
                    $_insert_order_other['ref3'] .= ', 入参订单号：'.$_old_order_number;
                }else{
                    $_insert_order_other['ref3'] = '入参订单号：'.$_old_order_number;
                }
                $_insert_order_other['payment_currency_code'] = $_payment_currency_code;
                $this->db->table($this->order_other)->insert($_insert_order_other);
            }else{
                $_insert_order_other['order_id'] = $_order_id;
                $_insert_order_other['ref3'] = '入参订单号：'.$_old_order_number;
                $_insert_order_other['create_on'] = $_create_on;
                $_insert_order_other['payment_currency_code'] = $_payment_currency_code;
                $this->db->table($this->order_other)->insert($_insert_order_other);
            }

            $returnData['order_number'] = $_order_number;
            //只有在“有nocnoc运输方式 且 有其他运输方式”的情况下，才标识为可以进行nocnoc拆单。tinghu.liu 20190413
            $returnData['is_nocnoc'] = ($is_nocnoc == 1 && $is_no_nocnoc == 1)?1:0;
            $returnData['order_id'] = $_order_id;
            $returnData['coupon_id'] = $coupon_id;
            $returnData['category_err_flag'] = ['is_category_err'=>$is_category_err, 'err_product_arr'=>$category_err_arr];
            return $returnData;
        }catch(\Exception $e){
            Log::record('insert order3:'.$e->getMessage(),'error');
            return false;
        }
    }

    /**
     * 根据订单ID获取订单编号
     * @param $params
     * @return false|\PDOStatement|string|\think\Collection
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
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderInfoByOrderMasterNumber($params){
        if (!isset($params['order_master_number']) || empty($params['order_master_number'])){
            return [];
        }
        $fields = "order_id,order_number,order_master_number,grand_total,currency_code,country,country_code";
        $_order_master_numbers = $params['order_master_number'];
        $res = $this->db->table($this->order)->field($fields)
//            ->where("order_master_number=$_order_master_numbers")
            ->where(['order_master_number'=>$_order_master_numbers])
            ->find();

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
     * 根据订单号获取订单数据【区块链】
     * @param $order_number
     * @param string $fields
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderInfoByOrderNumberForBlockChain($order_number, $fields='*'){
        return $this->db->table($this->dx_block_chain_order)->field($fields)->where(['order_number'=>$order_number])->find();
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
            ->field("o.order_id,o.parent_id,o.order_number,o.store_id,o.store_name,o.payment_status,o.order_status,o.tariff_insurance,o.lock_status,o.goods_count,o.discount_total,o.shipping_fee,o.handling_fee,o.total_amount,o.goods_total,o.grand_total,o.captured_amount_usd,o.captured_amount,o.refunded_amount,o.currency_code,o.shipping_count,o.shipped_count,o.shipped_amount,o.order_type,o.exchange_rate,o.language_code,o.create_on,o.shipping_insurance_fee,o.boleto_url,o.pay_type,o.pay_channel,receivable_shipping_fee,order_master_number,adjust_price,affiliate,order_branch_status,o.country,o.country_code")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query]);
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
                        $data['data'][$key]['item'][$k]['tracking_number'] = $this->db->table($this->order_package)
                            ->alias("p")
                            ->join($this->order_package_item." pi","p.package_id = pi.package_id")
                            ->where("pi.sku_id = '{$v['sku_num']}' AND order_number='{$value['order_number']}' AND p.is_delete=0")
                            ->value("tracking_number");
                    }
                    /*订单已关闭时查询是否是售后*/
                    if($value['order_status']>=1900){
                        $data['data'][$key]['after_sale_id'] = 0;
                        $after_sale_id = $this->db->table($this->order_after_sale_apply)->where(['order_id'=>$value['order_id']])->order("after_sale_id","DESC")->value('after_sale_id');
                        if($after_sale_id){
                            $data['data'][$key]['after_sale_id'] = $after_sale_id;
                        }
                    }
                }
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
            $res['item'] = $this->db->table($this->order_item)->where($item_where)->field("item_id,order_id,product_id,sku_id,sku_num,captured_price,captured_price_usd,discount_total,product_price,product_price,product_name,product_img,product_nums,product_attr_ids,product_attr_desc,shipping_fee,shipping_model,delivery_time,shipping_model")->select();
            $address_where['order_id'] = $res['order_id'];
            $res['shipping_address'] = $this->db->table($this->shipping_address)->where($address_where)->find();
            $package_where['order_number'] = $res['order_number'];
            foreach ($res['item'] as $key=>$value){
                /*获取产品物流单号*/
                if($res['order_status']>=500 && $res['order_status']<=1300){
                    $res['item'][$key]['tracking_number'] = $this->db->table($this->order_package)
                        ->alias("p")
                        ->join($this->order_package_item." pi","p.package_id = pi.package_id")
                        ->where("pi.sku_id = {$value['sku_id']} AND order_number={$res['order_number']} AND p.is_delete=0")
                        ->column("p.package_id,tracking_number");
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
        $order_master_number = isset($where['order_master_number'])?$where['order_master_number']:'';
        $is_check_order = isset($where['is_check_order'])?$where['is_check_order']:0;
        //有传payToken，则根据支付token去拿主单号 tinghu.liu 20190909
        if (isset($where['pay_token']) && !empty($where['pay_token'])){
            $token_info = $this->db->table($this->dx_order_pay_token)->where(['pay_token'=>$where['pay_token']])->find();
            $order_master_number = isset($token_info['order_master_number'])?$token_info['order_master_number']:'';
            if ($order_master_number === ''){
                return [];
            }
        }
        $_where['order_master_number'] = $order_master_number;

        /**
         * 检查是否有过期的活动或coupon，有则重新计算和更新订单金额 tinghu.liu 20190919
         * TODO 主从数据库会有延迟导致更新后随即再取值取不到最新数据的问题？？？？需要确认
         */
        $check_res = false;
        if ($is_check_order == 1){
            $check_res = $this->checkActivityAndCouponExpire($order_master_number);
        }

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
        $returnData = [];
        if($res){
            $returnData['order'] = $res;
            foreach ($res as $k=>$v){
                $where_order_id[] = $v['order_id'];
            }
            $returnData['item'] = $this->db->table($this->order_item)->where('order_id','in',$where_order_id)
                ->field("shipping_model,shipping_fee,delivery_time,product_unit,order_id,product_id,sku_id,sku_num,first_category_id,
    		discount_total,product_price,active_price,captured_price,captured_price_usd,product_name,product_img,product_nums,product_attr_desc,active_id,order_item_type,message")->select();

            $returnData['shipping_address'] = $this->db->table($this->shipping_address)->where('order_id','in',$where_order_id)->select();
            $returnData['order_coupon'] = $this->db->table($this->order_coupon)->where('order_id','in',$where_order_id)->select();
            $order_master_data = $this->db->table($this->order)->field($order_file)->where(['order_number'=>$order_master_number])->find();
            $returnData['master_order'] = $order_master_data;

            //如果已经根据活动或coupon过期更改过，则返回提示
            $order_master_other_data = $this->db->table($this->order_other)->where(['order_id'=>$order_master_data['order_id']])->find();
            if (isset($order_master_other_data['ref4']) && $order_master_other_data['ref4'] == 1000){
                $check_res = true;
            }

            $is_update_order_activity_coupon = 0;
            if ($check_res){
                $is_update_order_activity_coupon = 1;
            }
            $returnData['is_update_order_activity_coupon'] = $is_update_order_activity_coupon;
        }
        return $returnData;
    }

    public function getOrderNumberByOrderMasterNumber($where){
        $order_master_number = $where['order_master_number'];
        $order_file = "order_id,order_number,order_status,currency_code,pay_channel,pay_type";
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
        return $this->db->table($this->order_message)->where($where)->field("id,order_id,parent_id,message_type,message,file_url,statused,create_on,user_id,user_name,first_category,second_category")->order($order)->select();
    }

    /**
     * 添加订单消息处理方法
     * 查询订单详情表
     * @param array $data
     */
    public function addOrderMessage($data){
        $res = $this->db->transaction(function() use ($data) {
            if(!empty($data['order_id']) && $data['message_type']==2){
                /*获取已分配用户为当前添加的记录的分配人 added by kevin 20190319*/
                $distribution_where['distribution_admin_id'] = ['gt',0];
                $distribution_where['order_id'] = $data['order_id'];
                $distribution_where['message_type'] = 2;
                $data['is_new'] = 1;
                $distribution_data = $this->db->table($this->order_message)->where($distribution_where)->order("id DESC")->field("distribution_admin_id,distribution_admin,is_crash")->find();
                if(!empty($distribution_data)){
                    if(strpos($distribution_data['distribution_admin'],"Seller") === false){
                        if(!empty($distribution_data['distribution_admin'])){
                            $data['distribution_admin_id'] = $distribution_data['distribution_admin_id'];
                            $data['distribution_admin'] = $distribution_data['distribution_admin'];
                        }
                    }
                    /*如果之前是紧急处理消息，者后面增加的也是 20190422 kevin*/
                    $data['is_crash'] = $distribution_data['is_crash'];
                }
                /*判断是否第一次提交这个订单的信息,没有则判断是第一次提交 20190531*/
                $is_earliest_where['order_id'] = $data['order_id'];
                $order_message_count = $this->db->table($this->order_message)->where($is_earliest_where)->count();
                if($order_message_count == 0){
                    $data['is_earliest'] = 1;
                }
                /*获取用户未回复留言记录数 kevin,如果加上此条留言超过后台设置自动回复模板条数则设置为紧急处理状态并自动回复*/
                $OrderMessageTemplateInfo = (new OrderMessageTemplateModel())->getOrderMessageTemplateInfo(['type'=>2,'status'=>1]);
                if($OrderMessageTemplateInfo){
                    $buyer_no_reply_where['order_id'] = $data['order_id'];
                    $buyer_no_reply_where['message_type'] = 2;
                    $buyer_no_reply_where['is_reply'] = 1;
                    $buyer_no_reply_count = $this->db->table($this->order_message)->where($buyer_no_reply_where)->count();
                    if($buyer_no_reply_count >= ($OrderMessageTemplateInfo['number_reply']-1)){
                        /*判断系统是否有自动回复过，并判断自动回复模板是否设置紧急，没有系统自动回复*/
                        $is_system_where['order_id'] = $data['order_id'];
                        $is_system_where['message_type'] = 3;
                        $is_system_where['user_name'] = "system";
                        $is_system_count = $this->db->table($this->order_message)->where($is_system_where)->count();
                        /*判断自动回复模板设置是否设为紧急*/
                        if($OrderMessageTemplateInfo['is_crash'] == 1){
                            $data['is_crash'] = 0;
                        }
                        $res = $this->db->table($this->order_message)->insertGetId($data);
                        if($is_system_count == 0){
                            /*自动回复*/
                            $automatic_reply['order_id'] = $data['order_id'];
                            $automatic_reply['user_name'] = "system";
                            $automatic_reply['message_type'] = 3;
                            $automatic_reply['message'] = $OrderMessageTemplateInfo['content_en'];
                            $automatic_reply['statused'] = -1;
                            $automatic_reply['create_on'] = time();
                            $automatic_reply_res = $this->db->table($this->order_message)->insert($automatic_reply);
                            if(!$automatic_reply_res){
                                Log::write("Automatic reply error,data:".json_encode($automatic_reply));
                            }
                        }
                    }else{
                        $res = $this->db->table($this->order_message)->insertGetId($data);
                    }
                }else{
                    $res = $this->db->table($this->order_message)->insertGetId($data);
                }
            }

            if(isset($data['order_id']) && !empty($data['order_id']) && isset($data['message_type']) && $data['message_type']==2){
                /*买家回复，则更改卖家留言回复状态 added by wangyj in 20190218*/
                $update_where['order_id'] = $data['order_id'];
                $update_where['message_type'] = 1;
                /*买家回复后将此订单留言不是买家信息的改成已回复 20190428 kevin*/
                $update_where_or['message_type'] = ['neq',2];
                $this->db->table($this->order_message)->where($update_where)->whereOr($update_where_or)->update(['is_reply'=>2]);
                /*买家回复，不是之前最新状态数据改成不是最新数据 added by kevin in 20190403*/
                $update_new_where['order_id'] = $data['order_id'];
                $update_new_where['message_type'] = 2;
                $update_new_where['id'] = ["neq",$res];
                $update_new_where['is_new'] = 1;
                $this->db->table($this->order_message)->where($update_new_where)->update(['is_new'=>2]);
            }
            return $res;
        });
        return $res;
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
                if($value>0){
                    $where['order_status'] =$value;
                }else{
                    unset($where['order_status']);
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
     * @param string $request_data 要添加的数据
     * @return bool|string
     * @throws \Exception
     * @throws \think\exception\PDOException
     */
    public function addTrackingNumberByAllData(array $all_data, $request_data=''){
        $rtn = true;
        $this->db->startTrans();
        try{
            $time = time();
            //来源类型：1-OMS（默认），2-ERP
            $from_type = isset($all_data['from_type'])?$all_data['from_type']:1;
            //类型：1-正常同步追踪号（默认），2-换单，3-正常同步追踪号（一个订单多个追踪号）
            $type = (isset($all_data['type']) && !empty($all_data['type']))?$all_data['type']:1;
            //订单ID，ERP的时候会传
            /*if ($from_type == 2){
                $_order_info = $this->db->table($this->order)->where(['order_id'=>$all_data['order_id']])->find();
                $order_number = $all_data['order_number'] = $_order_info['order_number'];
                unset($all_data['order_id']);
            }else{
                $order_number = $all_data['order_number'];
            }*/
            $order_number = $all_data['order_number'];
            //是否删除其他的追踪号信息，只保存传的这个信息。0-不删除（默认），1-删除
            $is_delete = isset($all_data['is_delete'])?$all_data['is_delete']:0;
            /**
             * 因为有NOCNOC拆单情况，所以这里要判断是不是NOCNOC拆单后的订单号，如果是，需要转换为源订单号
             * 正常订单号 190412100138511822，长度为 18 位
             * NOCNOC拆单后是在正常订单好加 01（nocnoc订单） 或 02（非nocnoc订单），一共20位
             * tinghu.liu 20190413
             */
            if (strlen($order_number) == 20){
                $order_number = substr($order_number, 0, 18);
            }
            if (empty($order_number)){
                return 'order_number为空';
            }
            //当订单状态为已取消、已关闭状态时，不允许回传追踪号 tinghu.liu 20190318
            $order_info = $this->db->table($this->order)->where(['order_number'=>$order_number])->find();
            if (empty($order_info)) return 'Order data is error.';
            $order_status = $order_info['order_status'];
            if (in_array($order_status, [1400, 1900])){
                Log::record('Order status has been banned . params:'.json_encode($all_data).', order_status:'.$order_status);
//                return 'Order status has been banned.';
                return true;
            }
            /**
             * 增加换单逻辑支持 start
             * 只需要更新订单下的对应追踪号为新追踪号、具体运输渠道名称，其他不做操作
             * BY tinghu.liu IN 20190305
             **/
            if (
                $type == 2
                && isset($all_data['old_tracking_number']) && !empty($all_data['old_tracking_number'])
            ){
                $change_order_where = [
                    'order_number'=>$order_number,
                    'tracking_number'=>$all_data['old_tracking_number']
                ];
                $change_order_data = $this->db->table($this->order_package)->where($change_order_where)->find();
                if (!empty($change_order_data)){
                    //只有在存在的基础上才进行更改追踪号操作
                    $update_change_order = [
                        'tracking_number'=>$all_data['tracking_number'],
                        'shipping_channel_name'=>$all_data['shipping_channel_name'],
                        'shipping_channel_name_cn'=>''
                    ];
                    //增加运输渠道中文名称字段 tinghu.liu 20190401
                    if (isset($all_data['shipping_channel_name_cn'])){
                        $update_change_order['shipping_channel_name_cn'] = $all_data['shipping_channel_name_cn'];
                    }
                    //如果是OMS回传，且是巴西达(BR_BR	巴西达-巴西专线)，需要将英文名称和ERP回传的巴西达（BXD）名称一致，为了使用时更好的判断是否是巴西达 start tinghu.liu 20190402
                    if ($from_type == 1 && $all_data['shipping_channel_name'] == 'BR_BR'){
                        $update_change_order['shipping_channel_name'] = 'BXD';
                    }
                    $_remark = "shipping_channel_name:".$all_data['shipping_channel_name'].","."shipping_channel_name_cn:".$update_change_order['shipping_channel_name_cn'];
                    $update_change_order['remark'] = $_remark;
                    //end
                    $this->db->table($this->order_package)
                        ->where($change_order_where)
                        ->update($update_change_order);
                    $this->db->commit();
                    return $rtn;
                }else{
                    //如果不存在，则走正常的回传追踪号逻辑
                    return '换单失败，没有符合数据';
                }
            }
            /** 增加换单逻辑支持 end **/

            $_all_shipped_sku_count = 0; //已发货产品总数（之前已经发货数 + 本次发货产品数）

            $order_item_data = $this->db->table($this->order_item)->where(['order_id'=>$order_info['order_id']])->select();
            /**
             * 20181219 解决部分发货不能变为全部发货情况
             */
            $_all_sku_count = 0; //订单产品总数
            //获取总订单产品数量
            foreach ($order_item_data as $k20=>$v20){
                $_all_sku_count += $v20['product_nums'];
            }

            //增加“如果订单只有一个产品，且已经上传过追踪号(订单状态至少为600)，当再次上传追踪号时需要将之前上传的追踪号数据删除”逻辑 tinghu.liu 20190529

            if ($_all_sku_count == 1 && $order_status >= 600){
                $is_delete = 1;
            }

            /** 增加一个订单上传多个追踪的支持，但要兼容之前的单个上传，因为此方法除了erp外还有OMS也会调用 tinghu.liu 20190326 **/
            $_tracking_number_str = ''; //发送邮件使用
            $new_package_id_arr = []; //新生成的包裹ID
            $current_product_qty = 0; //本次上传的产品数量
            if ($type == 3){ //正常同步追踪号（一个订单多个追踪号）
                $data = $all_data['data'];
                $_tracking_number_arr = [];
                foreach ($data as $k30=>$v30){
                    //1、先根据“tracking_number”判断是否有数据，若有则删除记录（包含item表数据），之后再同步数据（避免重复上传追踪号情况）
                    $_tracking_number = $v30['tracking_number'];
                    $_tracking_number_arr[] = $_tracking_number;
                    $where['order_number'] = $order_number;
                    if (isset($v30['package_number']) && !empty($v30['package_number'])){
                        $where['package_number'] = $v30['package_number'];
                    }
                    if ($from_type == 2){ //如果来至ERP，需要增加追踪号的判断，因为ERP回传没有包裹号，但一个订单有可能有多个追踪号 20190402 tinghu.liu
                        $where['tracking_number'] = $_tracking_number;
                    }
                    if ($is_delete == 1){
                        //如果是删除订单的所有已存在的所有追踪号信息 tinghu.liu 20190525
                        $is_delete_where = ['order_number'=>$order_number];
                        $exist_data = $this->db->table($this->order_package)->where($is_delete_where)->select();
                        if (!empty($exist_data)){
                            /*//删除item数据
                            $package_id_arr = [];
                            foreach ($exist_data as $k300=>$v300){
                                $package_id_arr[] = $v300['package_id'];
                            }
                            $this->db->table($this->order_package_item)->where('package_id', 'in', $package_id_arr)->delete();
                            //删除主表数据
                            $this->db->table($this->order_package)->where($is_delete_where)->delete();*/
                            //删除修改为逻辑删除
                            $this->db->table($this->order_package)->where($is_delete_where)->update(['is_delete'=>1]);
                        }
                    }else{
                        $exist_data = $this->db->table($this->order_package)->where($where)->find();
                        if (!empty($exist_data)){
                            /*$exist_package_id = $exist_data['package_id'];
                            $this->db->table($this->order_package_item)->where(['package_id'=>$exist_package_id])->delete();
                            $this->db->table($this->order_package)->where($where)->delete();*/
                            //删除修改为逻辑删除
                            $this->db->table($this->order_package)->where($where)->update(['is_delete'=>1]);
                        }
                    }
                    /** 2、 同步追踪号操作 **/
                    /** 计算之前已经发货的数量，如果有的话 **/
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
                    $_all_data = [];
                    $_all_data['order_number'] = $order_number;
                    $_all_data['weight'] = isset($v30['weight'])?$v30['weight']:'';
                    $_all_data['shipping_fee'] = isset($v30['shipping_fee'])?$v30['shipping_fee']:0;
                    $_all_data['triff_fee'] = isset($v30['triff_fee'])?$v30['triff_fee']:0;
                    $_all_data['service_per_charge'] = isset($v30['service_per_charge'])?$v30['service_per_charge']:0;
                    $_all_data['service_charge'] = isset($v30['service_charge'])?$v30['service_charge']:0;
                    $_all_data['total_amount'] = isset($v30['total_amount'])?$v30['total_amount']:0;
                    $_all_data['pic_path_when_check'] = isset($v30['pic_path_when_check'])?$v30['pic_path_when_check']:'';
                    $_all_data['pic_path_when_weigh'] = isset($v30['pic_path_when_weigh'])?$v30['pic_path_when_weigh']:'';
                    $_all_data['package_number'] = isset($v30['package_number'])?$v30['package_number']:'';
                    $_all_data['tracking_number'] = $_tracking_number;
                    //增加“运输渠道中文名称”、“备注”字段 start tinghu.liu in 20190402
                    $_shipping_channel_name = $_shipping_channel_name_o = isset($v30['shipping_channel_name'])?$v30['shipping_channel_name']:'';
                    $_shipping_channel_name_cn = isset($v30['shipping_channel_name_cn'])?$v30['shipping_channel_name_cn']:'';
                    //如果是OMS回传，且是巴西达(BR_BR	巴西达-巴西专线)，需要将英文名称和ERP回传的巴西达（BXD）名称一致，为了使用时更好的判断是否是巴西达 tinghu.liu 20190402
                    if ($from_type == 1 && $_shipping_channel_name == 'BR_BR'){
                        $_shipping_channel_name = 'BXD';
                    }
                    $_all_data['shipping_channel_name'] = $_shipping_channel_name;
                    $_all_data['shipping_channel_name_cn'] = $_shipping_channel_name_cn;
                    $_remark = "shipping_channel_name:".$_shipping_channel_name_o.","."shipping_channel_name_cn:".$_shipping_channel_name_cn;
                    $_all_data['remark'] = $_remark;
                    //end
                    $_all_data['add_time'] = isset($all_data['add_time'])?$all_data['add_time']:'';
                    //增加请求参数记录 tinghu.liu 20191118
                    $_all_data['request_data'] = $request_data;
                    $package_id = $this->db->table($this->order_package)->insertGetId($_all_data);
                    $new_package_id_arr[] = $package_id;
                    //写入dx_order_package_item表
                    $_package_lines = []; //拼装调用OMS修改订单状态PackageLines数据
                    $_item_data = [];
                    /**
                     * 产品发货ERP变化历史 tinghu.liu 20191118
                     * sku_list=[
                            sku_code = 222
                        ]
                        sku_code = 111 被修改

                        //数组为空，没有修改日志
                        skus_change_log = [
                            新增
                            333=>333_333,
                            修改
                            222=> 111_222, //表示从111修改为222
                            删除
                            222=>222_0,
                        ]
                     */
                    $skus_change_log = isset($v30['skus_change_log']) && !empty($v30['skus_change_log'])?$v30['skus_change_log']:[];
                    foreach ($v30['item_info'] as &$item){
                        /** ERP发货产品信息变化处理 start tinghu.liu 20191118 **/
                        $_sku_id = $item['sku_id'];//下单时候DX的sku code
                        $_true_sku_id = $item['sku_id'];//真正发货时候的sku code
                        if (isset($skus_change_log[$_sku_id])){
                            $sku_change_sku_arr = explode('_', $skus_change_log[$_sku_id]);
                            if (count($sku_change_sku_arr) == 2){
                                $_sku_id = $sku_change_sku_arr[0];
                            }
                        }
                        /** ERP发货产品信息变化处理 end **/
                        $item['package_id'] = $package_id;
                        //PackageLines
                        $_temp_package_lines = [];
                        $_temp_package_lines['Sku'] = $_sku_id;
                        $_temp_package_lines['Qty'] = $item['sku_qty'];
                        $_package_lines[] = $_temp_package_lines;
                        //拼装包裹详情数据
                        $_temp_item_data = [];
                        $_temp_item_data['package_id'] = $package_id;
                        $_temp_item_data['sku_id'] = $_sku_id;
                        $_temp_item_data['sku_qty'] = $item['sku_qty'];
                        $_temp_item_data['true_sku_id'] = $_true_sku_id;
                        $_item_data[] = $_temp_item_data;
                        //本次发货产品数
                        $current_product_qty += $item['sku_qty'];
                        $_all_shipped_sku_count += $item['sku_qty'];
                    }
                    $this->db->table($this->order_package_item)->insertAll($_item_data);
                }
                if (!empty($_tracking_number_arr))
                    $_tracking_number_str = implode(',', array_unique($_tracking_number_arr));
            }else { //正常同步追踪号（实际上是type==1的场景处理）
                //1、先根据“package_number”判断是否有数据，若有则删除记录（包含item表数据），之后再同步数据
                $where['order_number'] = $order_number;
                if (isset($all_data['package_number']) && !empty($all_data['package_number'])){
                    $where['package_number'] = $all_data['package_number'];
                }
                if ($from_type == 2){ //如果来至ERP，需要增加追踪号的判断，因为ERP回传没有包裹号，但一个订单有可能有多个追踪号 20190402 tinghu.liu
                    $where['tracking_number'] = $all_data['tracking_number'];//根据订单号和包裹号来判断，不需要包裹号 20190219 tinghu.liu
                }
                if ($is_delete == 1){
                    //如果是删除订单的所有已存在的所有追踪号信息 tinghu.liu 20190525
                    $is_delete_where = ['order_number'=>$order_number];
                    $exist_data = $this->db->table($this->order_package)->where($is_delete_where)->select();
                    if (!empty($exist_data)){
                        /*//删除item数据
                        $package_id_arr = [];
                        foreach ($exist_data as $k300=>$v300){
                            $package_id_arr[] = $v300['package_id'];
                        }
                        $this->db->table($this->order_package_item)->where('package_id', 'in', $package_id_arr)->delete();
                        //删除主表数据
                        $this->db->table($this->order_package)->where($is_delete_where)->delete();*/

                        //删除修改为逻辑删除
                        $this->db->table($this->order_package)->where($is_delete_where)->update(['is_delete'=>1]);
                    }
                }else{
                    $exist_data = $this->db->table($this->order_package)->where($where)->find();
                    if (!empty($exist_data)){
                        /*$exist_package_id = $exist_data['package_id'];
                        $this->db->table($this->order_package_item)->where(['package_id'=>$exist_package_id])->delete();
                        $this->db->table($this->order_package)->where($where)->delete();*/
                        //删除修改为逻辑删除
                        $this->db->table($this->order_package)->where($where)->update(['is_delete'=>1]);
                    }
                }
                /** 2、 同步追踪号操作 **/
                $item_data = $all_data['item_info'];
                unset($all_data['item_info']);
                unset($all_data['from_type']);
                $tracking_number = isset($all_data['tracking_number'])?$all_data['tracking_number']:'';
                $_tracking_number_str = $tracking_number;
                /** 计算之前已经发货的数量，如果有的话 **/
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
                $_all_data['order_number'] = $order_number;
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
                //增加“运输渠道中文名称”、“备注”字段 start tinghu.liu in 20190402
                $_shipping_channel_name = $_shipping_channel_name_o = isset($all_data['shipping_channel_name'])?$all_data['shipping_channel_name']:'';
                $_shipping_channel_name_cn = isset($all_data['shipping_channel_name_cn'])?$all_data['shipping_channel_name_cn']:'';
                //如果是OMS回传，且是巴西达(BR_BR	巴西达-巴西专线)，需要将英文名称和ERP回传的巴西达（BXD）名称一致，为了使用时更好的判断是否是巴西达 tinghu.liu 20190402
                if ($from_type == 1 && $_shipping_channel_name == 'BR_BR'){
                    $_shipping_channel_name = 'BXD';
                }
                $_all_data['shipping_channel_name'] = $_shipping_channel_name;
                $_all_data['shipping_channel_name_cn'] = $_shipping_channel_name_cn;
                $_remark = "shipping_channel_name:".$_shipping_channel_name_o.","."shipping_channel_name_cn:".$_shipping_channel_name_cn;
                $_all_data['remark'] = $_remark;
                //end
                $_all_data['add_time'] = isset($all_data['add_time'])?$all_data['add_time']:'';
                //增加请求参数记录 tinghu.liu 20191118
                $_all_data['request_data'] = $request_data;
                $package_id = $this->db->table($this->order_package)->insertGetId($_all_data);
                $new_package_id_arr[] = $package_id;
                //写入dx_order_package_item表
                $_package_lines = []; //拼装调用OMS修改订单状态PackageLines数据
                $_item_data = [];
                /**
                 * 产品发货ERP变化历史 tinghu.liu 20191118
                 * sku_list=[
                        sku_code = 222
                    ]
                    sku_code = 111 被修改

                    //数组为空，没有修改日志
                    skus_change_log = [
                        新增
                        333=>333_333,
                        修改
                        222=> 111_222, //表示从111修改为222
                        删除
                        222=>222_0,
                    ]
                 */
                $skus_change_log = isset($all_data['skus_change_log']) && !empty($all_data['skus_change_log'])?$all_data['skus_change_log']:[];
                foreach ($item_data as &$item){
                    /** ERP发货产品信息变化处理 start tinghu.liu 20191118 **/
                    $_sku_id = $item['sku_id'];//下单时候DX的sku code
                    $_true_sku_id = $item['sku_id'];//真正发货时候的sku code
                    if (isset($skus_change_log[$_sku_id])){
                        $sku_change_sku_arr = explode('_', $skus_change_log[$_sku_id]);
                        if (count($sku_change_sku_arr) == 2){
                            $_sku_id = $sku_change_sku_arr[0];
                        }
                    }
                    /** ERP发货产品信息变化处理 end **/
                    $item['package_id'] = $package_id;
                    //PackageLines
                    $_temp_package_lines = [];
                    $_temp_package_lines['Sku'] = $_sku_id;
                    $_temp_package_lines['Qty'] = $item['sku_qty'];
                    $_package_lines[] = $_temp_package_lines;
                    //拼装包裹详情数据
                    $_temp_item_data = [];
                    $_temp_item_data['package_id'] = $package_id;
                    $_temp_item_data['sku_id'] = $_sku_id;
                    $_temp_item_data['sku_qty'] = $item['sku_qty'];
                    $_temp_item_data['true_sku_id'] = $_true_sku_id;
                    $_item_data[] = $_temp_item_data;
                    //本次发货产品数
                    $current_product_qty += $item['sku_qty'];
                    $_all_shipped_sku_count += $item['sku_qty'];
                }
                $this->db->table($this->order_package_item)->insertAll($_item_data);
            }

            //如果本次上传的追踪号产品数量等于订单所有的数量，需要将之前上传的追踪号数据删除 tinghu.liu 20190530
            if ($current_product_qty == $_all_sku_count && !empty($new_package_id_arr)){
                $this->db->table($this->order_package)
                    ->where('order_number', '=', $order_number)
                    ->where('package_id', 'not in', $new_package_id_arr)
                    ->update(['is_delete'=>1]);
                Log::record('本次上传的追踪号产品数量等于订单所有的数量，需要将之前上传的追踪号数据删除.$order_number:'.$order_number, Log::NOTICE);
            }
            //3、同步发货状态（根据sku数量来判断是部分还是全部发货，之后更新发货状态 500:部分发货和600:全部发货）
            $fulfillment_status = 500; //发货状态 500:部分发货和600:全部发货
            $fulfillment_status_str = 'Partial Shipped';
//            $order_item_data = $this->db->table($this->order_item)->where(['order_id'=>$order_info['order_id']])->select();
//            /**
//             * 20181219 解决部分发货不能变为全部发货情况
//             */
//            $_all_sku_count = 0; //订单产品总数
//            //获取总订单产品数量
//            foreach ($order_item_data as $k20=>$v20){
//                $_all_sku_count += $v20['product_nums'];
//            }
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

            //订单状态判断保护（为了解决发货完成后修改追踪号，再次同步追踪号时订单状态改变问题），但仍需处理ERP相关逻辑以及更改状态后的逻辑 20190219 tinghu.liu
            if ($order_info['order_status'] < $fulfillment_status){
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
                $update_res = $this->updateOrderStatus($up_status_data);
                if ($update_res){
                    $res = true;
                }else{
                    $res = false;
                    Log::record('回传追踪号更新状态失败，up_status_data:'.json_encode($up_status_data).', res:'.$update_res);
                }
            }else{
                $res = true;
            }
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
                $url = CIC_API."/cic/Customer/getEmailsByCID";
                $user_res = doCurl($url,['id'=>$order_info['customer_id']],null,true);
                if (isset($user_res['code']) && $user_res['code'] == 200){
                    $to_email = isset($user_res['data'])?$user_res['data']:'';
                    if (!empty($to_email)){
                        //邮件标题
                        $_title_values['order_number'] = $order_info['order_number'];
                        //邮件内容
                        $_body_values['user_name'] = !empty($order_info['customer_name'])?$order_info['customer_name']:$order_info['customer_id'];
                        $_body_values['order_number'] = $order_info['order_number'];
                        $_body_values['tracking_number'] = $_tracking_number_str;
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
            //排除退款类型，不做删除处理，因为会存在一个订单多次退款的情况 tinghu.liu 20190325
            $refund_type_arr = [strtolower('Reversed'), strtolower('RefundToSC'), strtolower('Refund'), strtolower('RemoteRefund')];
            if (!in_array(strtolower($data['txn_type']), $refund_type_arr)){
                $_delete_where = [
                    'order_number'=>$_order_number,
                    'third_party_txn_id'=>$data['third_party_txn_id'],
                    'txn_type'=>$data['txn_type']
                ];
                //增加payment txn id判断，为了解决交易明细不全的情况 20190408 tinghu.liu
                if (isset($data['payment_txn_id'])){
                    $_delete_where['payment_txn_id'] = $data['payment_txn_id'];
                }
                $this->db->table($this->order_sales_txn)
                    ->where($_delete_where)->delete();
            }
            //2、新增
            $this->db->table($this->order_sales_txn)->insert($data);
            //3、20181210 存在多笔支付的情况下，需要更新实收金额，为了避免重复支付，退款一笔而出现订单关闭（通过实收金额和退款金额对比，若相等则关闭订单）情况
            $_result = $this->db->table($this->order_sales_txn)
                ->where(['order_number'=>$_order_number])
                ->where('txn_type', 'in', ['Capture', 'Purchase'])
                //只累加统计支付成功的 tinghu.liu 20190815
                ->where('txn_result', 'in', ['Success', 'success'])
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
//                    'grand_total'=>$_amount, //实收总金额
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

    /**
     * 【新payment专用】新增交易明细数据
     * @param array $data 要新增的数据
     * @return bool|string
     * @throws \Exception
     */
    public function insertSalesTXNV2(array $data, $pay_type, $pay_channel){
        $rtn = true;
        $this->db->startTrans();
        try{
            $_order_number = $data['order_number'];
            //1、通过third_party_txn_id和订单编号查找对应的数据进行删除操作（为了避免有多条数据的情况）
            //排除退款类型，不做删除处理，因为会存在一个订单多次退款的情况 tinghu.liu 20190325
            $refund_type_arr = [strtolower('Reversed'), strtolower('RefundToSC'), strtolower('Refund'), strtolower('RemoteRefund')];
            if (!in_array(strtolower($data['txn_type']), $refund_type_arr)){
                $_delete_where = [
                    'order_number'=>$_order_number,
                    'third_party_txn_id'=>$data['third_party_txn_id'],
                    'txn_type'=>$data['txn_type']
                ];
                //增加payment txn id判断，为了解决交易明细不全的情况 20190408 tinghu.liu
                if (isset($data['payment_txn_id'])){
                    $_delete_where['payment_txn_id'] = $data['payment_txn_id'];
                }
                $this->db->table($this->order_sales_txn)
                    ->where($_delete_where)->delete();
            }else{
                //交易明细退款时金额应该为负数[因为推送过来的是正数] tinghu.liu 20190824
                $data['amount'] = -$data['amount'];
            }
            //2、新增
            $this->db->table($this->order_sales_txn)->insert($data);
            //3、20181210 存在多笔支付的情况下，需要更新实收金额，为了避免重复支付，退款一笔而出现订单关闭（通过实收金额和退款金额对比，若相等则关闭订单）情况
            /*if (strtolower($pay_type) == 'paypal' || strtolower($pay_channel) == 'paypal'){
                //paypal的去掉Purchase类型，因为新版PayPal类型为Purchase是create订单的，不是实际支付 tinghu.liu 20190926
                $_result = $this->db->table($this->order_sales_txn)
                    ->where(['order_number'=>$_order_number])
                    ->where('txn_type', 'in', ['Capture'])
                    //只累加统计支付成功的 tinghu.liu 20190815
                    ->where('txn_result', 'in', ['Success', 'success'])
                    ->select();
            }else{
                $_result = $this->db->table($this->order_sales_txn)
                    ->where(['order_number'=>$_order_number])
                    ->where('txn_type', 'in', ['Capture', 'Purchase'])
                    //只累加统计支付成功的 tinghu.liu 20190815
                    ->where('txn_result', 'in', ['Success', 'success'])
                    ->select();
            }*/
            $_result = $this->db->table($this->order_sales_txn)
                ->where(['order_number'=>$_order_number])
                ->where('txn_type', 'in', ['Capture', 'Purchase'])
                //只累加统计支付成功的 tinghu.liu 20190815
                ->where('txn_result', 'in', ['Success', 'success'])
                ->select();
            if (
                !empty($_result) && is_array($_result)
//                && count($_result) > 1
            ){
                //去掉PayPal支付记录中的“Purchase”记录（PayPal的实际支付金额为“Capture”记录为准），因为存在用户先选择了PayPal支付，后面再选择其他支付方式进行支付，就会存在金额累加错误的情况 tinghu.liu 20191012
                foreach ($_result as $k101=>$v101){
                    if (
                        (
                            strtolower($v101['third_party_method']) == 'paypal'
                            || strtolower($v101['payment_method']) == 'paypal'
                        )
                        && strtolower($v101['txn_type']) == 'purchase'
                    ){
                        unset($_result[$k101]);
                    }
                }
                sort($_result);
                if (count($_result) > 1){
                    $_amount = 0;
                    foreach ($_result as $k=>$v){
                        $_amount += $v['amount'];
                    }
                    $_amount_usd = $_amount;
                    //订单数据
                    $order_info = $this->db->table($this->order)->where(['order_number'=>$_order_number])->find();
                    $_exchange_rate = $order_info['exchange_rate'];
                    //获取币种修改，为了避免PayPal删除后获取币种失败问题 tinghu.liu 20191015
                    $_currency_code = isset($_result[0]['currency_code'])?$_result[0]['currency_code']:(isset($order_info['currency_code'])?$order_info['currency_code']:'');
                    //币种判断
                    if (
                        strtoupper($_currency_code) != strtoupper('USD')
                    ){
                        $_amount_usd = sprintf("%.2f", $_amount_usd/$_exchange_rate);
                    }
                    $_update = [
//                    'grand_total'=>$_amount, //实收总金额
                        'captured_amount_usd'=>$_amount_usd, //以美元为单的实收总金额（如果退款，这个金额会变动）
                        'captured_amount'=>$_amount //实收金额（如果退款，这个金额会变动）
                    ];
                    $this->db->table($this->order)
                        ->where(['order_number'=>$_order_number])
                        ->update($_update);
                    //记录更新订单金额日志
                    Log::record('insertSalesTXN update captured_amount'.$_order_number.', OrderInfo:'.json_encode($order_info).', update:'.json_encode($_update));
                }
            }
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = $e->getMessage().', '.$e->getFile().'['.$e->getLine().']';
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 【区块链】【新payment专用】新增交易明细数据
     * @param array $data 要新增的数据
     * @return bool|string
     * @throws \Exception
     */
    public function insertSalesTXNV2ForBlockChain(array $data, $pay_type, $pay_channel){
        $rtn = true;
        $this->db->startTrans();
        try{
            $_order_number = $data['order_number'];
            //1、通过third_party_txn_id和订单编号查找对应的数据进行删除操作（为了避免有多条数据的情况）
            //排除退款类型，不做删除处理，因为会存在一个订单多次退款的情况 tinghu.liu 20190325
            $refund_type_arr = [strtolower('Reversed'), strtolower('RefundToSC'), strtolower('Refund'), strtolower('RemoteRefund')];
            if (!in_array(strtolower($data['txn_type']), $refund_type_arr)){
                $_delete_where = [
                    'order_number'=>$_order_number,
                    'third_party_txn_id'=>$data['third_party_txn_id'],
                    'txn_type'=>$data['txn_type']
                ];
                //增加payment txn id判断，为了解决交易明细不全的情况 20190408 tinghu.liu
                if (isset($data['payment_txn_id'])){
                    $_delete_where['payment_txn_id'] = $data['payment_txn_id'];
                }
                $this->db->table($this->dx_block_chain_order_sales_txn)
                    ->where($_delete_where)->delete();
            }else{
                //交易明细退款时金额应该为负数[因为推送过来的是正数] tinghu.liu 20190824
                $data['amount'] = -$data['amount'];
            }
            //2、新增
            $this->db->table($this->dx_block_chain_order_sales_txn)->insert($data);
            //3、20181210 存在多笔支付的情况下，需要更新实收金额，为了避免重复支付，退款一笔而出现订单关闭（通过实收金额和退款金额对比，若相等则关闭订单）情况
            $_result = $this->db->table($this->dx_block_chain_order_sales_txn)
                ->where(['order_number'=>$_order_number])
                ->where('txn_type', 'in', ['Capture', 'Purchase'])
                //只累加统计支付成功的 tinghu.liu 20190815
                ->where('txn_result', 'in', ['Success', 'success'])
                ->select();
            if (
                !empty($_result) && is_array($_result)
//                && count($_result) > 1
            ){
                //去掉PayPal支付记录中的“Purchase”记录（PayPal的实际支付金额为“Capture”记录为准），因为存在用户先选择了PayPal支付，后面再选择其他支付方式进行支付，就会存在金额累加错误的情况 tinghu.liu 20191012
                foreach ($_result as $k101=>$v101){
                    if (
                        (
                            strtolower($v101['third_party_method']) == 'paypal'
                            || strtolower($v101['payment_method']) == 'paypal'
                        )
                        && strtolower($v101['txn_type']) == 'purchase'
                    ){
                        unset($_result[$k101]);
                    }
                }
                sort($_result);
                if (count($_result) > 1){
                    $_amount = 0;
                    foreach ($_result as $k=>$v){
                        $_amount += $v['amount'];
                    }
                    $_amount_usd = $_amount;
                    //订单数据
                    $order_info = $this->db->table($this->dx_block_chain_order)->where(['order_number'=>$_order_number])->find();
                    $_exchange_rate = $order_info['exchange_rate'];
                    //获取币种修改，为了避免PayPal删除后获取币种失败问题 tinghu.liu 20191015
                    $_currency_code = isset($_result[0]['currency_code'])?$_result[0]['currency_code']:(isset($order_info['currency_code'])?$order_info['currency_code']:'');
                    //币种判断
                    if (
                        strtoupper($_currency_code) != strtoupper('USD')
                    ){
                        $_amount_usd = sprintf("%.2f", $_amount_usd/$_exchange_rate);
                    }
                    $_update = [
//                    'grand_total'=>$_amount, //实收总金额
                        'captured_amount_usd'=>$_amount_usd, //以美元为单的实收总金额（如果退款，这个金额会变动）
                        'captured_amount'=>$_amount //实收金额（如果退款，这个金额会变动）
                    ];
                    $this->db->table($this->dx_block_chain_order)
                        ->where(['order_number'=>$_order_number])
                        ->update($_update);
                    //记录更新订单金额日志
                    Log::record('insertSalesTXN update captured_amount'.$_order_number.', OrderInfo:'.json_encode($order_info).', update:'.json_encode($_update));
                }
            }
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = $e->getMessage().', '.$e->getFile().'['.$e->getLine().']';
            $this->db->rollback();
        }
        return $rtn;
    }

    /*
     * 获取订单基础信息
     * */
    public function getOrderBasics($where){
        return $this->db->table($this->order)->where($where)->field("order_id,order_number,order_master_number,store_id,store_name,total_amount,tariff_insurance,order_status,receivable_shipping_fee,discount_total,create_on,currency_code,shipments_time,captured_amount,affiliate,customer_id,customer_name,country_code")->find();
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

    /*
    * 根据订单编号获取交易唯一ID
    * */
    public function getTransaction($where){
        $TransactionData = $this->db->table($this->order_sales_txn)->where($where)->order('txn_id desc')->find();
        return $TransactionData;
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
        return $this->db->table($this->order_status_change)->where($where)->order(['id'=>'desc'])->find();
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

    /**
     * 根据条件获取订单数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function geOrderDataByWhereForBlockChain(array $where){
        return $this->db->table($this->dx_block_chain_order)->where($where)->select();
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
        $res = 0;
        //退款成功
        if ($flag == 1){
            //修改订单正在退款金额，已退款金额，实收金额
            $exchange_rate = $order_info['exchange_rate'];
            $refunded_amount = !empty($order_info['refunding_amount'])?$order_info['refunding_amount']:0;
            $captured_amount = ($order_info['captured_amount'] - $refunded_amount)>0?($order_info['captured_amount'] - $refunded_amount):0;
            $captured_amount_usd = round(($captured_amount / $exchange_rate), 2);
            //已退款金额需要改为累加的形式，因为会存在一个订单多次退款的情况和kaiwen讨论后 tinghu.liu 20190325
            $refunded_amount_before = !empty($order_info['refunded_amount'])?$order_info['refunded_amount']:0;
            $refunded_amount += $refunded_amount_before;
            $res = $this->db->table($this->order)->where($where)->update([
                'refunding_amount'=>0, //退款中金额
                'refunded_amount'=>$refunded_amount, //已退款金额
                'captured_amount'=>$captured_amount, //实收金额（如果退款，这个金额会变动）
                'captured_amount_usd'=>$captured_amount_usd, //以美元为单的实收总金额（如果退款，这个金额会变动）
            ]);
            Log::record('handleOrderInfoForRefund, flag:'.$flag.', sql:'.$this->db->getLastSql());
        }elseif($flag == 2) {
            //退款失败 1).将订单状态修改为200 2).将退款中金额修改为0
            /*** 退款失败不需要修改订单状态，为了避免新支付系统推送退款处理中出现状态问题 **/
            /*$res = $this->db->table($this->order)->where($where)->update([
                'refunding_amount'=>0, //退款中金额
                'order_status'=>200, //订单状态
            ]);
            Log::record('handleOrderInfoForRefund, flag:'.$flag.', sql:'.$this->db->getLastSql());
            */
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
            //是否记录状态改变日志，1-记录（默认），0-不记录
            $is_record_change_info = isset($params['is_record_change_info'])?$params['is_record_change_info']:1;
            $order_status_from = $params['order_status_from'];
            $order_status = $params['order_status'];//修改后的状态
            if(!isset($params['order_branch_status'])){
                if ($order_status_from == $order_status){
                    return $rtn;
                }
            }
            //如果修改的状态和当前状态一致，不修改 tinghu.liu 20190702
            $info = $this->db->table($this->order)->where(['order_id'=>$params['order_id']])->field('order_status')->find();
            if ($info['order_status'] == $order_status) return $rtn;

            /*2019.2.14 kevin 如果更改是完成状态，修改订单完成时间*/
            if($order_status == 900){
                $up_data['complete_on'] = time();
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
            $updata_res = $this->db->table($this->order)->where($where)->update($up_data);
            /*判断是否更改订单状态成功，不成功返回false 20190714 kevin*/
            if(!$updata_res){
                $rtn = false;
                Log::record('修改订单状态失败：where:'.json_encode($where).",up_data:",json_encode($up_data));
                if ($is_start_trans == 1){
                    $this->db->rollback();
                }
                return $rtn;
            }
            /** 2/记录订单状态修改记录 **/
            if ($is_record_change_info == 1 ){
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
            }
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
    /**
     * 客服统计报表获取订单信息
     * [OrderInformation description]
     *  auther wang  2019-02-20
     */
    public function OrderInformation($data = array()){
        $where = [];
        $where['distribution_admin_id'] = ['neq',0];
        if(!empty($data["add_time"])){
            $where['add_time'] = $data["add_time"];
        }
        $question_info = '';
        //获取订单信息
        $order_info = $this->StatisticalOrderInformation($where);
        //产品Q&A
        $question_info = $this->productQA($where);
        return apiReturn(['code'=>200,'data'=>json_encode($order_info),'question_info'=>json_encode($question_info)]);
    }
    //获取订单信息
    public function StatisticalOrderInformation($data = array()){
        if(!empty($data['add_time'])){
            $where['create_on'] = $data['add_time'];
            // $where['create_on'] = array(array('egt',1550620800),array('elt',1553126399));
        }
        if(!empty($data["distribution_admin_id"])){
            $where['distribution_admin_id'] = $data["distribution_admin_id"];
        }//return $where;
        $order_info = $this->db->table($this->order_message)->where($where)->field('count(distribution_admin_id) as distribution_admin_count,distribution_admin_id,distribution_admin,AVG(aging) AS aging')->group('distribution_admin_id')->select();
        if(!empty($order_info)){
            foreach ($order_info as $k => $v) {
                //已解决任务数量
                $where['is_reply']    = 3;
                $where['distribution_admin_id'] = $v['distribution_admin_id'];
                $order_info[$k]['NumberOfSolutions'] =  $this->db->table($this->order_message)->where($where)->count();
                //回复数量
                $where['is_reply']    = array(array('eq',3),array('eq',2), 'or');
                $order_info[$k]['NumberOfResponses'] =  $this->db->table($this->order_message)->where($where)->count();
                //每个人的所有任务数
                unset($where['is_reply']);
                $order_info[$k]['AllTasks'] =  $this->db->table($this->order_message)->where($where)->count();
            }
        }
        return $order_info;
    }
    //产品Q&A
    public function productQA($data){
        if(!empty($data['add_time'])){
            $where['reply_time'] = $data['add_time'];

        }
        if(!empty($data["distribution_admin_id"])){
            $where['distribution_admin_id'] = $data["distribution_admin_id"];
        }
        $question_info = $this->seller_db->table($this->question)->where($where)->field('count(distribution_admin_id) as distribution_admin_count,distribution_admin_id,distribution_admin,AVG(aging) AS aging')->group('distribution_admin_id')->select();
        if(!empty($question_info)){
            foreach ($question_info as $k => $v) {
                //已解决任务数量
                $where['is_answer']    = 3;
                $where['distribution_admin_id'] = $v['distribution_admin_id'];
                $question_info[$k]['NumberOfSolutions'] = $this->seller_db->table($this->question)->where($where)->count();
                //回复数量
                $where['is_answer']    = array(array('eq',3),array('eq',2), 'or');
                $question_info[$k]['NumberOfResponses'] =  $this->seller_db->table($this->question)->where($where)->count();
                //每个人的所有任务数
                unset($where['is_answer']);
                $question_info[$k]['AllTasks'] = $this->seller_db->table($this->question)->where($where)->count();
            }
        }
        return $question_info;
    }

    /**
     * 根据主单号获取全部订单数据【包含主单数据】
     * @param $order_master_number
     * @param string $field
     * @return array
     */
    public function getAllOrderDataByMasterNumber($order_master_number, $field='*'){
        $find_data = $this->db->table($this->order)->where(['order_number'=>$order_master_number])->field($field)->find();
        $data = [];
        $child_data = [];
        if (!empty($find_data)){
            //获取产品信息
            $find_data['item_data'] = $this->db->table($this->order_item)->where(['order_id'=>$find_data['order_id']])->select();

            $data[] = $find_data;
            //拆单情况
            if ($find_data['order_master_number'] == 0){
                $child_data = $this->db->table($this->order)->where(['order_master_number'=>$order_master_number])->field($field)->select();
                foreach ($child_data as $k=>&$v){
                    $v['item_data'] = $this->db->table($this->order_item)->where(['order_id'=>$v['order_id']])->select();
                }
            }
        }
        if (!empty($child_data))
            $data = array_merge($data, $child_data);
        return $data;
    }
    /**
     * 关闭订单
     * [OrderShutDown description]
     * @auther wang  2019-03-18
     */
    public function OrderShutDown($data = array()){
        return $this->db->table($this->order)->where(['order_id'=>$data['order_id']])->update(['order_status'=>1900]);
    }
    /**
     * 导出退款订单订单
     * [OrderShutDown description]
     * @auther wang  2019-03-19
     */
    public function ExportRefundOrder($data = array()){
        $where['txn_type'] = array('in',['Refund','Reversed']) ;//只查退款
         $where['txn_result'] = 'Success';
        if(!empty($data['currency_code'])){
            $where['ot.currency_code'] = $data['currency_code'];
        }
        if(!empty($data['order_number'])){
            $where['ot.order_number'] = ['IN',$data['order_number']];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['ot.create_on'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $where['ot.create_on'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $where['ot.create_on'] = strtotime($data['endTime']);
            }
        }
         $page_size = !empty($data['page_size'])?$data['page_size']:20;
         $page = !empty($data['page'])?$data['page']:1;
         $path = !empty($data['path'])?$data['path']:'';
         $parent_where = array();
        if(!empty($data['payment_txn_id'])){
            $parent_where['payment_txn_id'] = ['IN',$data['payment_txn_id']];
        }
        if(!empty($data['third_party_txn_id'])){
            $parent_where['third_party_txn_id'] = ['IN',$data['third_party_txn_id']];
        }
        if(!empty($parent_where)){
            $parent_order_number = $this->db->table($this->order_sales_txn)->where($parent_where)->group("order_number")->column("order_number");
            if(!empty($parent_order_number)){
                $where['ot.order_number'] = ['IN',$parent_order_number];
            }
        }

         $order_list = $this->db->table($this->order_sales_txn)
                        ->alias("ot")
                        ->join($this->order." o","o.order_id=ot.order_id","LEFT")
                        ->join($this->dx_order_refund." or","ot.order_id=or.order_id","LEFT")
                        ->join($this->dx_sales_order_refund_operation." oro","oro.refund_id=or.refund_id","LEFT")
                        ->where($where)
                        ->order("txn_id desc")
                        ->field('o.order_id,o.order_number,o.order_master_number,o.exchange_rate,o.refunded_amount,o.country_code,o.pay_time,o.pay_channel,o.grand_total,ot.payment_txn_id,txn_time,txn_type,ot.amount,ot.currency_code,ot.create_on,or.remarks,oro.operator_type,oro.operator_id,oro.operator_name')
                        ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($data)?$data:$where]);
        $Page = $order_list->render();
        $data = $order_list->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 根据主订单更新支付方式和支付渠道
     * @param $order_master_number
     * @param $pay_type
     * @param $pay_channel
     * @param $payment_system //使用的支付系统。1-旧系统（.net）;2-新系统（php）
     * @param $cpf CPF税号
     * @return int|string
     *
     *  ['order_master_number', 'require'],
    ['pay_type', 'require'],
    ['pay_channel', 'require'],
     */
    public function updateOrderPaytypeAndChannel($order_master_number, $pay_type, $pay_channel, $payment_system='', $cpf=''){
        //如果更新的内容和数据库一致，则返回成功 tinghu.liu 20190717
        $data = $this->db->table($this->order)
            ->whereOr(['order_number'=>$order_master_number])
            ->whereOr(['order_master_number'=>$order_master_number])->field('order_id,pay_type, pay_channel, payment_system,currency_code,pay_channel')->select();
        $payment_system = ($payment_system == '')?$data[0]['payment_system']:$payment_system;
        $data_pay_type = isset($data[0]['pay_type'])?$data[0]['pay_type']:'';
        $data_pay_channel = isset($data[0]['pay_channel'])?$data[0]['pay_channel']:'';
        $data_payment_system = isset($data[0]['payment_system'])?$data[0]['payment_system']:'';

        Log::record('updateOrderPaytypeAndChannel:'.json_encode($data));

        //如果是ARS-Astropay-需要将更新实际支付币种 tinghu.liu 20191121
        foreach ($data as $k=>$v){
            $other_update_data['payment_currency_code'] = $v['currency_code'];
            //增加CPF字段更新 tinghu.liu 20191125
            if (!empty($cpf)){
                $other_update_data['cpf'] = $cpf;
            }
            if (
                strtolower($v['currency_code']) == strtolower('ARS')
                && strtolower($pay_channel) == strtolower('Astropay')
            ){
                $other_update_data['payment_currency_code'] = 'USD';
            }
            $this->db->table($this->order_other)->where(['order_id'=>$v['order_id']])->update($other_update_data);
        }
        if (!empty($cpf)){
            $this->db->table($this->order_shipping_address)->where(['order_id'=>$v['order_id']])->update(['cpf'=>$cpf]);
        }
        //更新操作
        $res = $this->db->table($this->order)
            ->whereOr(['order_number'=>$order_master_number])
            ->whereOr(['order_master_number'=>$order_master_number])
            ->update(['pay_type'=>$pay_type, 'pay_channel'=>$pay_channel, 'payment_system'=>$payment_system]);
        if ($data_pay_type == $pay_type && $data_pay_channel == $pay_channel && $data_payment_system == $payment_system){
            $res = true;
        }
        return $res;
    }

    /**
     * 获取售后订单详情
     *add 20190411 kevin
     */
    public function getOrderAfterSaleApply($where){
        return $this->db->table($this->order_after_sale_apply)
            ->where($where)
            ->field("after_sale_id,after_sale_number,order_id,order_number,customer_id,customer_name,store_name,store_id")
            ->find();
    }

    /**
     * 根据主订单获取订单收货地址
     * @param $order_master_number
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderAddressByOrderMasterNumber($order_master_number){
        $trn = [];
        $data = $this->db->table($this->order)->where(['order_master_number'=>$order_master_number])->field('order_id')->find();
        if (!empty($data)){
            $trn = $this->db->table($this->shipping_address)->where(['order_id'=>$data['order_id']])->find();
        }
        return $trn;
    }

    /**
     * 根据PayToken获取订单基本信息
     * @param $pay_token
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderBaseInfoByPayToken($pay_token){
        $data = [];
        $pay_token_info = $this->db->table($this->dx_order_pay_token)->where(['pay_token'=>$pay_token])->find();
        if (!empty($pay_token_info)){
            $data['token_info'] = $pay_token_info;
            $order_master_number = $pay_token_info['order_master_number'];
            $order_field = 'order_id,order_number,order_master_number,customer_id,customer_name,currency_code,exchange_rate,language_code,payment_system,affiliate,pay_type,pay_channel,order_status,order_branch_status,lock_status,grand_total';
            $data['order_data']['master'] = $this->db->table($this->order)
                ->where(['order_number'=>$order_master_number])
                ->field($order_field)
                ->find();
            $data['order_data']['slave'] = $this->db->table($this->order)
                ->where(['order_master_number'=>$order_master_number])
                ->field($order_field)
                ->select();
            if (isset($data['order_data']['slave'][0]['order_id'])){
                $data['order_data']['address'] = $this->db->table($this->order_shipping_address)->where(['order_id'=>$data['order_data']['slave'][0]['order_id']])->find();
            }
        }


        return $data;
    }

    /**
     * 根据主单号获取支付token信息
     * @param $order_master_number
     * @return array|false|\PDOStatement|string|Model
     */
    public function getPayTokenInfoByOrderMasterNumber($order_master_number){
        $data = $this->db->table($this->dx_order_pay_token)->where(['order_master_number'=>$order_master_number])->find();
        if (empty($data)){
            //如果不存在支付token则生成对应的支付token，为了修复老数据
            $create_on = time();
            $add_time = date('Y-m-d H:i:s', $create_on);
            //新建支付Token信息
            $pay_token = CommonLib::generatePayToken($order_master_number);
            //支付Token数据
            $_pay_token_params['order_master_number'] = $order_master_number;
            $_pay_token_params['pay_token'] = $pay_token;
            $_pay_token_params['create_on'] = $create_on;
            $_pay_token_params['add_time'] = $add_time;
            $this->db->table($this->dx_order_pay_token)->insert($_pay_token_params);
            $pay_token_id = $this->db->getLastInsID();
            if ($pay_token_id){
                $_pay_token_params['id'] = $pay_token_id;
                $data = $_pay_token_params;
            }
        }
        return $data;
    }

    /**
     * 根据订单号获取最新订单处理状态
     * @param $order_number
     * @param $field
     * @return array|false|\PDOStatement|string|Model
     * added by wangyj in 20190423
     */
    public function getLastOrderStatusProcess($order_number, $field='id'){

        return $this->db->table($this->order_status_process)->field($field)->where(['order_number'=>$order_number])->order('id desc')->find();
    }

    /**
     * 接收OMS订单状态推送异常处理
     * @param array $params
     *      {"OrderNumber":"190317100117356928","Status":"36","ChangeOn":"2019-04-25 09:55:35","ChangeBy":"Payment ipn","access_token":"726345e2e22af4d86b799290161f5ec4"}
     * @param $order_status_from 收到OMS订单状态时订单的状态
     * @param $order_status_oms OMS推送的订单状态映射后的值
     * @return bool
     */
    public function synOrderStatusExceptionHandle(array $params, $order_status_from, $order_status_oms){
        $rtn = true;
        $this->db->startTrans();
        try{
            $time = time();
            $time_array = explode(' ', microtime());
            $msec_time = intval(substr(floatval($time_array[0])*1000,0,3));
            $order_number = $params['OrderNumber'];
            //1、增加订单备注
            $remark1 = '待发货（400或407）时收到OMS取消订单的通知';
            $this->db->table($this->order)->where(['order_number'=>$order_number])->update(['remark1'=>$remark1]);
            //2、记录订单状态记录表 dx_sales_order_status_oms_record
            $insert_data['order_number']        = $order_number;
            $insert_data['order_status_from']   = $order_status_from;
            $insert_data['order_status']        = $order_status_oms;
            $insert_data['order_status_oms']    = $params['Status'];
            //记录类型：1-待发货（400或407）时收到取消订单的通知
            $insert_data['record_type']         = 1;
            $insert_data['change_on']           = $params['ChangeOn'];
            $insert_data['change_by']           = $params['ChangeBy'];
            $insert_data['add_time']            = date('Y-m-d H:i:s', $time).'.'.$msec_time;
            $this->db->table($this->dx_sales_order_status_oms_record)->insert($insert_data);
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = false;
            Log::record('synOrderStatusExceptionHandle系统异常 '.$e->getMessage());
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 处理折扣异常订单
     * @param $master_order
     * @param $slave_order
     * @param $remark
     * @return bool
     */
    public function handleDiscoutExceptionOrder($master_order, $slave_order, $remark){
        $rtn = true;
        $this->db->startTrans();
        try{
            $time = time();
            //a.自动加锁;
            $order_master_number = $master_order['order_number'];
            $all_order_number[] = $order_master_number; //所有订单数据，包含主单
            $order_child_number = [];//子单数据
            foreach ($slave_order as $k30=>$v30){
                $all_order_number[] = $v30['order']['order_number'];
                $order_child_number[] = $v30['order']['order_number'];
            }
            $all_order_number = array_unique($all_order_number);
            if (!empty($all_order_number)){
                $this->db->table($this->order)->where('order_number', 'in', $all_order_number)->update(['lock_status'=>73, 'remark1'=>'折扣异常订单，系统自动加锁，需要客户审核后再处理解锁']);
            }
            //b.记录异常订单（只记录子单），在amdin显示
            if (!empty($order_child_number)){
                $insert_data = [];
                foreach ($order_child_number as $k31=>$v31){
                    $tmp = [];
                    $tmp['order_number']            = $v31;
                    $tmp['order_master_number']     = $order_master_number;
                    $tmp['type']                    = 1;//异常类型：1-订单折扣异常
                    $tmp['remark']                  = $remark;
                    $tmp['create_on']               = $time;
                    $insert_data[] = $tmp;
                }
                $this->db->table($this->dx_sales_order_discount_exception)->insertAll($insert_data);
            }
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = false;
            Log::record('handleDiscoutExceptionOrder系统异常 '.$e->getMessage());
            $this->db->rollback();
        }
        return $rtn;

    }

    /**
     * 更新订单币种
     * @param $order_master_number
     * @param $to_currency
     * @return bool
     */
    public function updateOrderCurrency($order_master_number, $to_currency){
        $rtn = true;

        if ($to_currency != 'USD'){
            //TODO 目前只存在其他币种转换为USD的情况，不支持非美元之间的币种互转
            $rtn = false;
            return $rtn;
        }

        $this->db->startTrans();
        try{
            $order_data = $this->db->table($this->order)
                ->where(['order_number'=>$order_master_number])
                ->whereOr(['order_master_number'=>$order_master_number])
                ->field('order_id,order_number,order_master_number,currency_code,exchange_rate')
                ->select();
            if (!empty($order_data)){
                //如果币种相等，则直接返回
                if ($order_data[0]['currency_code'] == $to_currency){
                    $this->db->commit();
                    return $rtn;
                }
                foreach ($order_data as $k=>$v){
                    /**
                     * 涉及到的表
                     *
                     *  $this->order

                    $this->order_item
                    $this->order_status_change
                    $this->order_coupon
                    $this->shipping_address

                    $this->order_other
                     */






                }
            }
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = false;
            Log::record('updateOrderCurrency系统异常 '.$e->getMessage(), Log::ERROR);
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 检查是否有过期的活动或coupon，有则重新计算和更新订单金额
     * @param $order_master_number
     * @return bool
     */
    private function checkActivityAndCouponExpire($order_master_number){
        $time = time();
        $backup_data =  []; //备份的订单数据
        $data = [];
        $order_data = $this->db->table($this->order)
            ->where(['order_master_number'=>$order_master_number])
            ->whereOr(['order_number'=>$order_master_number])
            ->select();
        $have_active_coupon_is_expire = false;
        foreach ($order_data as $k=>$v){
            //只处理子单
            if (!empty($v['order_master_number']) && $v['order_master_number'] != 0){
                $order_info = $v;
                $order_id = $v['order_id'];
                $exchange_rate = $v['exchange_rate'];
                $backup_tmp = [];
                $tmp = [];

                $backup_tmp['order'] = $order_info;

                ///////////////////// item start ////////////////
                $item_data = $this->db->table($this->order_item)->where(['order_id'=>$order_id])->select();
                $backup_tmp['item'] = $item_data;
                $all_active_discount_flag = 0; //要回滚的活动价格
                foreach ($item_data as $k1=>$v1){
                    $product_id = (int)$v1['product_id'];
                    //产品售价
                    $product_price = $v1['product_price'];
                    //销售价格,批发价或者是活动价
                    $active_price = $v1['active_price'];
                    $product_nums = $v1['product_nums'];
                    //产品售价和活动价的优惠价格
                    $active_discount_price = sprintf("%.2f", ($product_price - $active_price));
                    $active_discount_price = $active_discount_price>=0?$active_discount_price:0;
                    $active_discount_price_usd = sprintf("%.2f", $active_discount_price/$exchange_rate);
                    $sku_id = $v1['sku_id'];
                    $sku_code = $v1['sku_num'];
                    //活动产品活动是否有已经结束的：false-没有，true-有
                    $active_is_expire = false;
                    //购买的是活动产品
                    if ($v1['active_id'] > 0){
                        $product_info = $this->mongo_db->table($this->product)
                            ->where(['_id' => $product_id])
                            ->field('_id,StoreID,Skus,IsActivity,ProductStatus,StoreName')
                            ->find();
                        if (!isset($product_info['IsActivity']) || empty($product_info['IsActivity'])){
                            $active_is_expire = true;
                            $have_active_coupon_is_expire = true;
                        }
                        if (isset($product_info['Skus'])){
                            foreach ($product_info['Skus'] as $k2=>$v2){
                                if ($v2['_id'] == $sku_id && $v2['Code'] == $sku_code){
                                    if(
                                        !isset($product_info['Skus'][$k2]['ActivityInfo']['SalesLimit'])
                                        || $product_info['Skus'][$k2]['ActivityInfo']['SalesLimit'] <= 0
                                    ){
                                        $active_is_expire = true;
                                        $have_active_coupon_is_expire = true;
                                    }
                                }
                            }
                        }
                    }
                    $item_data[$k1]['active_is_expire'] = $active_is_expire;
                    if ($active_is_expire){
                        /**** 重置item价格数据，去掉活动价，使用产品销价【实收-加，折扣总量-减】 *****/
                        $item_data[$k1]['active_price'] = $product_price;
                        $item_data[$k1]['captured_price'] = sprintf("%.2f", $item_data[$k1]['captured_price'] + $active_discount_price);
                        $item_data[$k1]['captured_price_usd'] = sprintf("%.2f", $item_data[$k1]['captured_price']/$exchange_rate);;
                        $item_data[$k1]['coupon_price'] = $item_data[$k1]['coupon_price'];
                        $item_data[$k1]['discount_total'] = sprintf("%.2f", $item_data[$k1]['discount_total'] - $active_discount_price);
                        //活动的活动表ID
                        $item_data[$k1]['active_id'] = 0;
                        //该sku参与的活动类型(0：不参与任何活动，1：批发价，2：活动价，3：coupon价)
                        if ($item_data[$k1]['active_type'] == 2){
                            $item_data[$k1]['active_type'] = 0;
                        }
                        $all_active_discount_flag += sprintf("%.2f", ($active_discount_price*$product_nums));
                    }
                }

                ///////////////////// item end ////////////////

                ///////////////////// coupon start ////////////////
                $coupon_data = $this->db->table($this->order_coupon)
                    ->where(['order_id'=>$order_id])
                    ->where('USD_discount', '>', 0)
                    ->where('captured_discount', '>', 0)
                    ->select();
                $backup_tmp['coupon'] = $coupon_data;
                $all_coupon_discount_flag = 0; //要减掉的coupon价格
                if (!empty($coupon_data)){
                    foreach ($coupon_data as $k20=>$v20){
                        //coupon是否已经结束：0-未结束，1-结束
                        $coupon_is_expire = false;
                        $coupon_id = (int)$v20['coupon_id'];
                        $product_info = $this->mongo_db->table($this->coupon)
                            ->where(['CouponId'=>$coupon_id])
                            ->field('CouponId,CouponTime,CouponStatus')
                            ->find();
                        if (!empty($product_info)){
                            if (
                                $product_info['CouponStatus'] != 3
                                || ( isset($product_info['CouponTime']['EndTime']) && $time > $product_info['CouponTime']['EndTime'])
                            ){
                                $coupon_is_expire = true;
                                $have_active_coupon_is_expire = true;
                                $all_coupon_discount_flag += $v20['captured_discount'];
                                /******* 重置优惠额度为0【coupon折扣-减】 ********/
                                $coupon_data[$k20]['USD_discount'] = 0;
                                $coupon_data[$k20]['captured_discount'] = 0;
                                $coupon_data[$k20]['coupon_desc'] = $coupon_data[$k20]['coupon_desc'].'(coupon已过期，重置captured_discount为0.时间：'.date('Y-m-d H:i:s').', 原来数据：'.json_encode($v20).')';
                            }
                        }
                        $coupon_data[$k20]['active_is_expire'] = $coupon_is_expire;
                    }
                }

                ///////////////////// coupon end ////////////////

                ////////////////////// order info start /////////////////////////////
                //订单总价（产品原价总额，不包含运费折扣等），原产品售价的总和，不变
//                $order_info['goods_total'] = $order_info['goods_total'];
                //折扣总价，【要减】
                $order_info['discount_total'] = sprintf("%.2f", $order_info['discount_total'] - ($all_active_discount_flag + $all_coupon_discount_flag));
                //使用coupon折扣的总价【要减】
                $order_info['coupon_price_total'] = sprintf("%.2f", $order_info['coupon_price_total'] - $all_coupon_discount_flag);
                //包含产品总金额、运费总金额、手续费等、含优惠的金额（为goods_total+运费，，因为goods_total不动，所以这里也不需要不用动）
//                $order_info['total_amount'] = $order_info['total_amount'];
                //实收总金额，【要加】
                $order_info['grand_total'] = sprintf("%.2f", $order_info['grand_total']+($all_active_discount_flag + $all_coupon_discount_flag));
                //实收金额（如果退款，这个金额会变动），因为这里订单状态还处于待支付状态，不会存在退款，所以实收和grand_total一样
                $order_info['captured_amount'] = $order_info['grand_total'];
                //以美元为单的实收总金额（如果退款，这个金额会变动）
                $order_info['captured_amount_usd'] = sprintf("%.2f", $order_info['grand_total']/$exchange_rate);
                ////////////////////// order info end /////////////////////////////
                $tmp['order'] = $order_info;
                $tmp['item'] = $item_data;
                $tmp['coupon'] = $coupon_data;
                $data[] = $tmp;
                $backup_data[] = $backup_tmp;
            }
        }
        /*********** 重新计算订单相关价格，且更新订单相关价格数据 ************/
        $rtn = false;
        if ($have_active_coupon_is_expire){
            $update_res = $this->checkActivityAndCouponExpireForUpdate($data, $order_master_number);
            if ($update_res){
                //更新成功，则记录变更日志
                //错误日志
                Monlog::write('logs_order_price_change_backup','info',__METHOD__,'activeCouponExpireCheck',$backup_data,null,$data,0,$order_master_number,$order_master_number);
                $rtn = true;
            }
        }
        return $rtn;
    }

    /**
     * 检查是否有过期的活动或coupon，有则重新计算和更新订单金额【实际更新操作】
     * @param $data
     * @param $order_master_number
     * @return bool
     */
    private function checkActivityAndCouponExpireForUpdate($data, $order_master_number){
        $rtn = true;
        //多商家的订单，需拆分订单
        $this->db->startTrans();
        try {
            $master_discount_total = 0;
            $master_coupon_price_total = 0;
            $master_grand_total = 0;
            $master_captured_amount = 0;
            $master_captured_amount_usd = 0;

            foreach ($data as $k100=>$v100){
                $item_data = $v100['item'];
                $coupon_data = $v100['coupon'];
                $order_info = $v100['order'];

                /********** 更新item start ************/
                foreach ($item_data as $k101=>$v101){
                    $this->db->table($this->order_item)
                        ->where(['item_id'=>$v101['item_id']])
                        ->update([
                            'active_price'=>$v101['active_price'],
                            'captured_price'=>$v101['captured_price'],
                            'captured_price_usd'=>$v101['captured_price_usd'],
                            'coupon_price'=>$v101['coupon_price'],
                            'discount_total'=>$v101['discount_total'],

                            'active_id'=>$v101['active_id'],
                            'active_type'=>$v101['active_type']
                        ]);
                }
                /********** 更新item end ************/

                /********** 更新coupon start ************/
                foreach ($coupon_data as $k102=>$v102){
                    $this->db->table($this->order_coupon)
                        ->where(['id'=>$v102['id']])
                        ->update([
                            'USD_discount'=>$v102['USD_discount'],
                            'captured_discount'=>$v102['captured_discount'],
                            'coupon_desc'=>$v102['coupon_desc'],
                        ]);
                }
                /********** 更新coupon end ************/

                /********** 更新order start ************/
                $master_discount_total += $order_info['discount_total'];
                $master_coupon_price_total += $order_info['coupon_price_total'];
                $master_grand_total += $order_info['grand_total'];
                $master_captured_amount += $order_info['captured_amount'];
                $master_captured_amount_usd += $order_info['captured_amount_usd'];

                $this->db->table($this->order)
                    ->where(['order_id'=>$order_info['order_id']])
                    ->update([
                        'discount_total'=>$order_info['discount_total'],
                        'coupon_price_total'=>$order_info['coupon_price_total'],
                        'grand_total'=>$order_info['grand_total'],
                        'captured_amount'=>$order_info['captured_amount'],
                        'captured_amount_usd'=>$order_info['captured_amount_usd'],
                    ]);

                /********** 更新order end ************/

                /********** 更新order other start ************/
                $order_id = $order_info['order_id'];
                $order_judge = $this->db->table($this->order_other)->where(['order_id'=>$order_id])->find();
                if (!empty($order_judge)){
                    $this->db->table($this->order_other)->where(['order_id'=>$order_id])
                        ->update(['ref4'=>1000]);
                }else{
                    $this->db->table($this->order_other)->insert([
                        'order_id'=>$order_id,
                        'ref4'=>1000,
                        'create_on'=>time()
                    ]);
                }

                /********** 更新order other end ************/

            }

            //如果存在多个子单，需要更新对应主单数据
            if (count($data) > 1){
                $this->db->table($this->order)
                    ->where(['order_number'=>$order_master_number])
                    ->update([
                        'discount_total'=>$master_discount_total,
                        'coupon_price_total'=>$master_coupon_price_total,
                        'grand_total'=>$master_grand_total,
                        'captured_amount'=>$master_captured_amount,
                        'captured_amount_usd'=>$master_captured_amount_usd,
                    ]);

                $order_master_data = $this->db->table($this->order)->where(['order_number'=>$order_master_number])->field('order_id')->find();
                $order_master_id = $order_master_data['order_id'];
                $order_master_judge = $this->db->table($this->order_other)->where(['order_id'=>$order_master_id])->find();
                if (!empty($order_master_judge)){
                    $this->db->table($this->order_other)->where(['order_id'=>$order_master_id])
                        ->update(['ref4'=>1000]);
                }else{
                    $this->db->table($this->order_other)->insert([
                        'order_id'=>$order_master_id,
                        'ref4'=>1000,
                        'create_on'=>time()
                    ]);
                }

            }
            $this->db->commit();
        }catch (\Exception $e){
            $this->db->rollback();
            $rtn = false;
        }
        return $rtn;
    }



    /**
     * 订单生成【区块链】
     * @param array $params
     * @return true/false
     */
    public function submitOrderForBlockChain($params){
        $_order_params = $params['order'];
        $_item_params = $params['item'];
        $_address_params = $params['shipping_address'];
        $_order_number = $_order_params['order_number'];
        Log::record('submitOrderForBlockChain, params'.$_order_number.':'.json_encode($params));
        //单个seller不生成主订单
        $this->db->startTrans();
        try {
            //插入订单表
            $this->db->table($this->dx_block_chain_order)->insert($_order_params);
            $order_id = $this->db->table($this->dx_block_chain_order)->getLastInsID();
            //插入订单item表
            foreach ($_item_params as $k=>$v){
                $v['order_id'] = $order_id;
                $this->db->table($this->dx_block_chain_order_item)->insert($v);
            }
            //插入订单地址表
            $_address_params['order_id'] = $order_id;
            $this->db->table($this->dx_block_chain_order_shipping_address)->insert($_address_params);
            $res_data['order_id'] = $order_id;
            $res_data['order_number'] = $_order_number;
            $res_data['grand_total'] = $_order_params['grand_total'];
            $this->db->commit();
            return $res_data;
        }catch (\Exception $e){
            Log::record('submitOrderForBlockChain-Exception:'.$e->getMessage().','.$e->getFile().'['.$e->getLine().']'.', params:'.json_encode($params),'error');
            $this->db->rollback();
            return false;
        }
    }

    /**
     * 订单查询处理方法
     * 查询订单表
     * @param array $order
     */
    public function getBlockChainOrderList($where,$page_size=20,$page=1,$path='',$order='',$page_query=array()){
        $res = $this->db->table($this->dx_block_chain_order)
            ->alias("o")
            ->join($this->dx_block_chain_order_item." oi","o.order_id=oi.order_id")
            ->where($where)
            ->order($order)
            ->field("o.order_id,o.order_number,o.customer_id,goods_count,grand_total,o.order_status,product_id,pay_type,o.create_on as order_create_on,
            o.complete_on as order_complete_on,
            currency_code_blockchain as virtual_currency,exchange_rate_blockchain as virtual_currency_rate")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$page_query]);
        $Page = $res->render();
        $data = $res->toArray();
        if($data['data']){
            foreach ($data['data'] as $key => $value){
                $product_info = $this->mongo_db->table($this->product_virtual)
                    ->where(['_id' => $value['product_id']])->field('_id,Title,ContractTerm')->find();
                $data['data'][$key]['product_name'] = !empty($product_info['Title']) ? $product_info['Title'] : '';
                $data['data'][$key]['contract_term'] = !empty($product_info['ContractTerm']) ? $product_info['ContractTerm'] : '';
                if(!empty($value['order_complete_on'])){
                    $data['data'][$key]['effective_time'] = strtotime(date('Y-m-d H:i:s',$value['order_complete_on']) . '+1days');
                }else{
                    $data['data'][$key]['effective_time'] = '';
                }
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取订单上一个状态【dispute状态回滚用】
     * @param $order_id 订单ID
     * @return int
     */
    public function getOrderLastStatusForDisputeRollback($order_id){
        $last_order_status = 0;
        $order_change_data = $this->db->table($this->order_status_change)->where(['order_id'=>$order_id])->order('id desc')->field('id,order_id,order_status_from,order_status')->select();
        if (!empty($order_change_data)){
            foreach ($order_change_data as $k=>$v){
                //去掉1700-纠纷中的状态
                if (!in_array($v['order_status_from'], [1700]) && !in_array($v['order_status'], [1700])){
                    $last_order_status = $v['order_status'];
                    if (in_array($v['order_status'], [200,400])){
                        $last_order_status = 400;
                    }
                    break;
                }
            }
        }
        return $last_order_status;
    }

}
