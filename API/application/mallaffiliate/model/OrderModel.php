<?php
namespace app\mallaffiliate\model;

use think\Log;
use think\Model;
use think\Db;

/**
 * 订单模型
 * Class OrderModel
 * @author tinghu.liu 2018/8/26
 * @package app\orderFront\model
 */
class OrderModel extends Model{
    /**
     * 数据库连接对象
     * @var \think\db\Connection
     */
	private $db;
	private $db_mongo;

    private $dx_product = 'dx_product';
    /**
     * 订单主表
     * @var string
     */
	private $order = "dx_sales_order";
    /**
     * 订单子表
     * @var string
     */
	private $order_item = "dx_sales_order_item";
    /**
     * dx_sales_order_message表
     * @var string
     */
	private $order_message = "dx_sales_order_message";
    /**
     * 订单价格的更改记录表
     * @var string
     */
	private $order_price_change = "dx_order_price_change";
    /**
     * 订单邮寄地址记录表
     * @var string
     */
	private $order_shipping_address = "dx_order_shipping_address";
    /**
     * 订单退款退货换货表（售后单主表）
     * @var string
     */
	private $order_after_sale_apply = "dx_order_after_sale_apply";

    /**
     * 订单退款表
     * @var string
     */
    private $order_refund = "dx_order_refund";
    /**
     * 追踪号信息表
     * @var string
     */
	private $order_package = "dx_order_package";
    /**
     * 追踪号信息表
     * @var string
     */
	private $order_package_item = "dx_order_package_item";
    /**
     * 订单状态日志表
     * @var string
     */
	private $order_status_change = "dx_sales_order_status_change";
    /**
     * 交易明细表表
     * @var string
     */
	private $order_sales_txn = "dx_sales_txn";
    /**
     * 退款操作明细表表
     * @var string
     */
	private $order_sales_order_refund_operation = "dx_sales_order_refund_operation";
    /**
     *【NOC拆单】订单表
     * @var string
     */
	private $nocsplit_sales_order = "dx_nocsplit_sales_order";
    /**
     *【NOC拆单】订单商品表
     * @var string
     */
	private $nocsplit_sales_order_item = "dx_nocsplit_sales_order_item";
    /**
     *【NOC拆单】订单优惠券使用记录表
     * @var string
     */
	private $nocsplit_sales_order_coupon = "dx_nocsplit_sales_order_coupon";
    /**
     *OMS推送订单状态记录表
     * @var string
     */
    private $sales_order_status_oms_record = "dx_sales_order_status_oms_record";
    /**
     *订单折扣异常记录表
     * @var string
     */
    private $sales_order_discount_exception = "dx_sales_order_exception";

    //订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile
    const ORDER_FROM_PC             = 10;
    const ORDER_FROM_ANDROID        = 20;
    const ORDER_FROM_IOS            = 30;
    const ORDER_FROM_PAD            = 40;
    const ORDER_FROM_MOBILE         = 50;

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
        $this->db_mongo = Db::connect('db_mongodb');
    }

    /**
     * 获取订单统计
     * @param array $params 条件
     * @return array
     */
    public function getOrderStatistics(array $params){
        $time = time();
        $data =  [];
        //查询成功订单时候是否严格查询进入风控的单：1-严格模式，2-宽松模式
        $query_flag = isset($params['query_flag'])?$params['query_flag']:1;
        //只查询子单号
        $where['order_master_number'] = ['<>', 0];
        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $create_on_start = $params['create_on_start'];
            $create_on_end = $params['create_on_end'];
            $where['create_on'] = [
                ['>=', strtotime($params['create_on_start'])],
                ['<=', strtotime($params['create_on_end'])]
            ];
        }else{
            //默认取今天的数据（UTC）
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d H:i:s', $time);
            $where['create_on'] = [
                ['>=', strtotime(date('Y-m-d 00:00:00'))],
                ['<=', $time]
            ];
        }
        //根据国家来查询 tinghu.liu 20190902
        $country_code = (isset($params['country_code']) && !empty($params['country_code'])) ?$params['country_code']:'';
        if($country_code != ''){
            $where['country_code'] = $country_code;
        }

        $data['create_on_start'] = $create_on_start;
        $data['create_on_end'] = $create_on_end;
        $success_order_status_1 = '400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';
        $success_order_status_2 = '120,400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';

        /** 一、统计订单总量 start **/
        //1.总量
        $all_data = $this->db->table($this->order)
            ->where($where)
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        Log::record('sql_1_0:'.$this->db->getLastSql());

        $all_count = $all_data['order_count'];

        //2.支付成功量
        if ($query_flag == 1){
            $success_all_data = $this->db->table($this->order)
                ->where($where)
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }else{
            $success_all_data = $this->db->table($this->order)
                ->where($where)
                ->where('order_status','in', $success_order_status_2)
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }

        Log::record('sql_1_1:'.$this->db->getLastSql());
        $all_count_success = $success_all_data['order_count'];

        $all_count_success_rate_all = $all_count>0?round($all_count_success/$all_count, 4):0;

        $data['all'] = $all_count;
        $data['success'] = $all_count_success;
        $data['success_rate'] = $all_count_success_rate_all;

        /** 统计订单总量 end **/

        /** 二、根据支付渠道和方式统计 start **/
        //1.总量
        $pay_channel_data = $this->db->table($this->order)
            ->where($where)
            ->field('pay_channel, pay_type, COUNT(order_id) as `all`')
            ->group('pay_channel, pay_type')->select();
        Log::record('sql_2_0:'.$this->db->getLastSql());




        //2.支付成功量
        if ($query_flag == 1){
            $pay_channel_success_data = $this->db->table($this->order)
                ->where($where)
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('pay_channel, pay_type, COUNT(order_id) as order_count')
                ->group('pay_channel, pay_type')->select();
        }else{
            $pay_channel_success_data = $this->db->table($this->order)
                ->where($where)
                ->where('order_status','in', $success_order_status_2)
                ->field('pay_channel, pay_type, COUNT(order_id) as order_count')
                ->group('pay_channel, pay_type')->select();
        }

        Log::record('sql_2_1:'.$this->db->getLastSql());

        foreach ($pay_channel_data as $k=>$v){
            //初始化支付渠道成功率为0
            $pay_channel_data[$k]['success'] = 0;
            $pay_channel_data[$k]['success_rate'] = 0;
            foreach ($pay_channel_success_data as $k1=>$v1){
                //统一大小写后再比较 tinghu.liu 20191104
                if (
                    strtolower($v['pay_channel']) == strtolower($v1['pay_channel'])
                    && strtolower($v['pay_type']) == strtolower($v1['pay_type'])
                ){

                    $all_count_success_rate_pay = $v['all']>0?round($v1['order_count']/$v['all'], 4):0;

                    $pay_channel_data[$k]['success'] = $v1['order_count'];
                    $pay_channel_data[$k]['success_rate'] = $all_count_success_rate_pay;
                    break;
                }
            }

        }
        $data['pay_channel_data'] = $pay_channel_data;

        /** 根据支付渠道和方式统计 end **/

        /** 三、统计mobile数据 start **/
        //1.总量
        $all_data_mobile = $this->db->table($this->order)
            ->where($where)
            ->where(['order_from'=>self::ORDER_FROM_MOBILE])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        Log::record('sql_3_0:'.$this->db->getLastSql());
        $all_count_mobile = $all_data_mobile['order_count'];
        $map['pay_channel']=['<>',''];
        $map['pay_type']=['<>',''];
        //1.1支付量
        $pay_data_mobile = $this->db->table($this->order)
            ->where($where)
            ->where($map)
            ->where(['order_from'=>self::ORDER_FROM_MOBILE])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        $pay_count_mobile = $pay_data_mobile['order_count'];
        //2.支付成功量
        if ($query_flag == 1){
            $success_all_data_mobile = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_MOBILE])
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }else{
            $success_all_data_mobile = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_MOBILE])
                ->where('order_status','in', $success_order_status_2)
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }
        Log::record('sql_3_1:'.$this->db->getLastSql());
        $all_count_success_mobile = $success_all_data_mobile['order_count'];

        $all_count_success_rate_mobile = $pay_count_mobile>0?round($all_count_success_mobile/$pay_count_mobile, 4):0;//支付成功率
        $pay_count_success_rate_mobile = $all_count_mobile>0?round($pay_count_mobile/$all_count_mobile, 4):0;//支付率
        $data['mobile']['all'] = $all_count_mobile;
        $data['mobile']['success'] = $all_count_success_mobile;
        $data['mobile']['pay_count'] = $pay_count_mobile;
        $data['mobile']['pay_rate'] = $pay_count_success_rate_mobile;
        $data['mobile']['success_rate'] = $all_count_success_rate_mobile;

        /** 统计mobile数据 end **/

        /** 四、统计PC数据 start **/
        //1.总量
        $all_data_pc = $this->db->table($this->order)
            ->where($where)
            ->where(['order_from'=>self::ORDER_FROM_PC])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        Log::record('sql_4_0:'.$this->db->getLastSql());
        $all_count_pc = $all_data_pc['order_count'];
        //1.1支付量
        $pay_data_pc = $this->db->table($this->order)
            ->where($where)
            ->where($map)
            ->where(['order_from'=>self::ORDER_FROM_PC])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        $pay_count_pc = $pay_data_pc['order_count'];
        //2.支付成功量
        if ($query_flag == 1){
            $success_all_data_pc = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_PC])
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }else{
            $success_all_data_pc = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_PC])
                ->where('order_status','in', $success_order_status_2)
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }
        Log::record('sql_4_1:'.$this->db->getLastSql());
        $all_count_success_pc = $success_all_data_pc['order_count'];
        $all_count_success_rate_pc = $pay_count_pc>0?round($all_count_success_pc/$pay_count_pc, 4):0;
        $pay_count_success_rate_pc = $all_count_pc>0?round($pay_count_pc/$all_count_pc, 4):0;
        $data['pc']['all'] = $all_count_pc;
        $data['pc']['success'] = $all_count_success_pc;
        $data['pc']['success_rate'] = $all_count_success_rate_pc;
        $data['pc']['pay_count'] = $pay_count_pc;
        $data['pc']['pay_rate'] = $pay_count_success_rate_pc;
        /** 统计PC数据 end **/

        /** 五、统计Android数据 start **/
        //1.总量
        $all_data_android = $this->db->table($this->order)
            ->where($where)
            ->where(['order_from'=>self::ORDER_FROM_ANDROID])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        Log::record('sql_5_0:'.$this->db->getLastSql());
        $all_count_android = $all_data_android['order_count'];
        //1.1支付量
        $pay_data_android = $this->db->table($this->order)
            ->where($where)
            ->where($map)
            ->where(['order_from'=>self::ORDER_FROM_ANDROID])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        $pay_count_android = $pay_data_android['order_count'];
        //2.支付成功量
        if ($query_flag == 1){
            $success_all_data_android = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_ANDROID])
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }else{
            $success_all_data_android = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_ANDROID])
                ->where('order_status','in', $success_order_status_2)
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }
        Log::record('sql_5_1:'.$this->db->getLastSql());
        $all_count_success_android = $success_all_data_android['order_count'];

        $all_count_success_rate_android = $pay_count_android>0?round($all_count_success_android/$pay_count_android, 4):0;
        $pay_count_success_rate_android = $all_count_android>0?round($pay_count_android/$all_count_android, 4):0;

        $data['android']['all'] = $all_count_android;
        $data['android']['success'] = $all_count_success_android;
        $data['android']['success_rate'] = $all_count_success_rate_android;
        $data['android']['pay_coun'] = $pay_count_android;
        $data['android']['pay_rate'] = $pay_count_success_rate_android;
        /** 统计Android数据 end **/

        /** 六、统计IOS数据 start **/
        //1.总量
        $all_data_ios = $this->db->table($this->order)
            ->where($where)
            ->where(['order_from'=>self::ORDER_FROM_IOS])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        Log::record('sql_5_0:'.$this->db->getLastSql());
        $all_count_ios = $all_data_ios['order_count'];
        $pay_data_ios = $this->db->table($this->order)
            ->where($where)
            ->where($map)
            ->where(['order_from'=>self::ORDER_FROM_IOS])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        $pay_count_ios = $pay_data_ios['order_count'];
        //2.支付成功量
        if ($query_flag == 1){
            $success_all_data_ios = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_IOS])
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }else{
            $success_all_data_ios = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_IOS])
                ->where('order_status','in', $success_order_status_2)
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }
        Log::record('sql_5_1:'.$this->db->getLastSql());
        $all_count_success_ios = $success_all_data_ios['order_count'];

        $all_count_success_rate_ios = $pay_count_ios>0?round($all_count_success_ios/$pay_count_ios, 4):0;
        $pay_count_success_rate_ios = $all_count_ios>0?round($pay_count_ios/$all_count_ios, 4):0;
        $data['ios']['all'] = $all_count_ios;
        $data['ios']['success'] = $all_count_success_ios;
        $data['ios']['success_rate'] = $all_count_success_rate_ios;
        $data['ios']['pay_count'] = $pay_count_ios;
        $data['ios']['pay_rate'] = $pay_count_success_rate_ios;
        /** 统计IOS数据 end **/

        /** 六、统计pad数据 start **/
        //1.总量
        $all_data_pad = $this->db->table($this->order)
            ->where($where)
            ->where(['order_from'=>self::ORDER_FROM_PAD])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        Log::record('sql_5_0:'.$this->db->getLastSql());
        $all_count_pad = $all_data_pad['order_count']?$all_data_pad['order_count']:0;
        $pay_data_pad = $this->db->table($this->order)
            ->where($where)
            ->where($map)
            ->where(['order_from'=>self::ORDER_FROM_PAD])
            ->field('order_number,COUNT(order_id) as order_count')
            ->find();
        $pay_count_pad = $pay_data_pad['order_count']?$pay_data_pad['order_count']:0;
        //2.支付成功量
        if ($query_flag == 1){
            $success_all_data_pad = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_PAD])
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['order_status'=>120,'order_branch_status'=>105]);
                        });
                })
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }else{
            $success_all_data_pad = $this->db->table($this->order)
                ->where($where)
                ->where(['order_from'=>self::ORDER_FROM_PAD])
                ->where('order_status','in', $success_order_status_2)
                ->field('order_number,COUNT(order_id) as order_count')
                ->find();
        }
        Log::record('sql_5_1:'.$this->db->getLastSql());
        $all_count_success_pad = $success_all_data_pad['order_count']?$success_all_data_pad['order_count']:0;
        $all_count_success_rate_pad = $pay_count_pad>0?round($all_count_success_pad/$pay_count_pad, 4):0;
        $pay_count_success_rate_pad = $all_count_pad>0?round($pay_count_pad/$all_count_pad, 4):0;

        $data['pad']['all'] = $all_count_pad;
        $data['pad']['success'] = $all_count_success_pad;
        $data['pad']['success_rate'] = $all_count_success_rate_pad;
        $data['pad']['pay_count'] = $pay_count_pad;
        $data['pad']['pay_rate'] = $pay_count_success_rate_pad;
        /** 统计IOS数据 end **/
        return $data;
    }

    /**
     * 根据产品ID、收货地址国家、时间查询订单销量
     * @param array $params
     * @return array
     * {
        "create_on_start":"2019-08-23 00:00:00",
        "create_on_end":"2019-08-24 00:00:00",
        "product_ids":[
            2609629,2605119,2612688
        ],
        "country_code":"US"
        }
     */
    public function getOrderSales(array $params){
        $time = time();
        $data =  [];
        //只查询子单号
        $where['a.order_master_number'] = ['<>', 0];
        /** 搜索订单创建时间 start **/
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $create_on_start = $params['create_on_start'];
            $create_on_end = $params['create_on_end'];
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', strtotime($create_on_end)]
            ];
        }else{
            //默认取今天的数据（UTC）
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d H:i:s', $time);
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', $time]
            ];
        }
        /** 搜索订单创建时间 end **/

        $data['create_on_start'] = $create_on_start;
        $data['create_on_end'] = $create_on_end;

        $success_order_status_1 = '400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';

        /************ 根据产品ID查询 *************/
        $is_have_product_ids = false;
        $product_ids_img_arr = [];
        $product_ids_new = [];

        $product_ids =
            (
                isset($params['product_ids'])
                && !empty($params['product_ids'])
            )?$params['product_ids']:[];

        if (!empty($product_ids)){
            $where['b.product_id'] = ['in', $product_ids];
            $is_have_product_ids  = true;
            foreach ($product_ids as $k100=>$v100){
                $product_ids_new[] = (int)$v100;
            }
        }

        //获取产品主图
        if (!empty($product_ids_new)){
            $product_data = $this->db_mongo->table($this->dx_product)
                ->where('_id','in',$product_ids_new)
                ->field('_id,ImageSet')
                ->select();

            if (!empty($product_data)){
                foreach ($product_data as $k200=>$v200){
                    $product_ids_img_arr[$v200['_id']] = isset($v200['ImageSet']['ProductImg'][0])?IMG_DXCDN_URL.$v200['ImageSet']['ProductImg'][0]:'';

                }
            }

        }

        /************ 根据国家字段查询 *************/
        $country_code = (
            isset($params['country_code'])
            && !empty($params['country_code'])
        )?$params['country_code']:'';
        if (!empty($country_code)){
            $where['a.country_code'] = $country_code;
            $data['country_code'] = $country_code;
        }
        /************* 存在按照产品ID查询的情况 *************/

        if ($is_have_product_ids){

            //总量
            $all_data = $this->db->table($this->order)
                ->alias("a")
                ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
                ->field('b.product_id, SUM(b.product_nums) as `all`')
                ->where($where)
                ->group('b.product_id')
                ->select()
            ;

            Log::record('sql_1_1:'.$this->db->getLastSql());
            //$data['data'] = $all_data;
            //成功
            $success_all_data = $this->db->table($this->order)
                ->alias("a")
                ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
                ->field('b.product_id, SUM(b.product_nums) as product_count')
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('a.order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['a.order_status'=>120,'a.order_branch_status'=>105]);
                        });
                })
                ->where($where)
                ->group('b.product_id')
                ->select()
            ;
            Log::record('sql_1_2:'.$this->db->getLastSql());
            //付款确认中
            $process_all_data = $this->db->table($this->order)
                ->alias("a")
                ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
                ->field('b.product_id, SUM(b.product_nums) as product_count')
                ->where(['a.order_status'=>120])
                ->where('a.order_branch_status', '<>', 105)
                ->where($where)
                ->group('b.product_id')
                ->select()
            ;

            Log::record('sql_1_3:'.$this->db->getLastSql());

            /*************** 数据处理 start **************/
            if (!empty($product_data)){
                foreach ($product_data as $k1=>$v1){
                    //拼装产品图片地址
                    $data['data'][$k1]['product_id'] = $v1['_id'];
                    $data['data'][$k1]['product_img'] = $product_ids_img_arr[$v1['_id']];
                    //统一返回格式 start
                    $data['data'][$k1]['all'] = 0;
                    $data['data'][$k1]['success'] = 0;
                    $data['data'][$k1]['success_rate'] = 0;
                    $data['data'][$k1]['processing'] = 0;

                    //拼装成功的数据
                    if (!empty($all_data)){
                        foreach ($all_data as $k4=>$v4){
                            if ($v1['_id'] == $v4['product_id']){
                                $data['data'][$k1]['all'] = $v1['all']=$v4['all'];
                            }
                        }
                    }
                   // var_dump($data);die;
                    //统一返回格式 end
                    //拼装成功的数据
                    if (!empty($success_all_data)){
                        foreach ($success_all_data as $k2=>$v2){
                            if ($v1['_id'] == $v2['product_id']){
                                $data['data'][$k1]['success'] = $v2['product_count'];
                                $data['data'][$k1]['success_rate'] = ($v1['all'] != 0 && $v1['all']>0)?round($data['data'][$k1]['success']/$v1['all'], 4):0;
                            }
                        }
                    }
                    //拼装付款确认中的数据
                    if (!empty($process_all_data)){
                        foreach ($process_all_data as $k3=>$v3){
                            if ($v1['_id'] == $v3['product_id']){
                                $data['data'][$k1]['processing'] = $v3['product_count'];
                            }
                        }
                    }
                }
            }

            /*************** 数据处理 end **************/
        }
        else
        {
            //总量
            $all_data = $this->db->table($this->order)
                ->alias("a")
                ->field('a.order_number,COUNT(a.order_id) as order_count')
                ->where($where)
                ->find()
            ;

            Log::record('sql_2_1:'.$this->db->getLastSql());
            //成功
            $success_all_data = $this->db->table($this->order)
                ->alias("a")
                ->field('a.order_number,COUNT(a.order_id) as order_count')
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('a.order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['a.order_status'=>120,'a.order_branch_status'=>105]);
                        });
                })
                ->where($where)
                ->find()
            ;
            Log::record('sql_2_2:'.$this->db->getLastSql());
            //付款确认中
            $process_all_data = $this->db->table($this->order)
                ->alias("a")
                ->field('a.order_number,COUNT(a.order_id) as order_count')
                ->where(['a.order_status'=>120])
                ->where('a.order_branch_status', '<>', 105)
                ->where($where)
                ->find()
            ;
            Log::record('sql_2_3:'.$this->db->getLastSql());
            $data['data']['all'] = isset($all_data['order_count'])?$all_data['order_count']:0;
            $data['data']['success'] = isset($success_all_data['order_count'])?$success_all_data['order_count']:0;
            $data['data']['success_rate'] = ($data['data']['all'] != 0 && $data['data']['all']>0)?round($data['data']['success']/$data['data']['all'], 4):0;
            $data['data']['processing'] = isset($process_all_data['order_count'])?$process_all_data['order_count']:0;
        }
        return $data;
    }

    /**
     * 根据产品ID、收货地址国家、时间查询订单号
     * @param array $params
     * @return array
     * {
    "create_on_start":"2019-08-23 00:00:00",
    "create_on_end":"2019-08-24 00:00:00",
    "product_ids":[
    2609629,2605119,2612688
    ],
    "country_code":"US"
    }
     */
    public function getOrderList(array $params){
        $time = time();
        $data =  [];
        //只查询子单号
        $where['a.order_master_number'] = ['<>', 0];
        /** 搜索订单创建时间 start **/
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $create_on_start = $params['create_on_start'];
            $create_on_end = $params['create_on_end'];
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', strtotime($create_on_end)]
            ];
        }else{
            //默认取今天的数据（UTC）
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d H:i:s', $time);
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', $time]
            ];
        }
        /** 搜索订单创建时间 end **/

        $data['create_on_start'] = $create_on_start;
        $data['create_on_end'] = $create_on_end;

        $success_order_status_1 = '400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';

        /************ 根据产品ID查询 *************/
        $is_have_product_ids = false;
        $product_ids_img_arr = [];
        $product_ids_new = [];
        $product_ids =
            (
                isset($params['product_ids'])
                && !empty($params['product_ids'])
            )?$params['product_ids']:[];

        if (!empty($product_ids)){
            $where['b.product_id'] = ['in', $product_ids];
            $is_have_product_ids  = true;
            foreach ($product_ids as $k100=>$v100){
                $product_ids_new[] = (int)$v100;
            }
        }

        /************ 根据国家字段查询 *************/
        $country_code = (
            isset($params['country_code'])
            && !empty($params['country_code'])
        )?$params['country_code']:'';
        if (!empty($country_code)){
            $where['a.country_code'] = $country_code;
            $data['country_code'] = $country_code;
        }
        /************* 存在按照产品ID查询的情况 *************/
        $all_data = $this->db->table($this->order)
            ->alias("a")
            ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
            ->field('a.order_number,a.payment_status,a.country,a.country_code,a.order_status,b.product_id,b.sku_id,b.product_nums,b.product_name,b.product_img,b.captured_price_usd')
            ->where($where)
            ->order('a.order_id desc')
            ->select()
        ;

        if(!empty($all_data)){
            foreach($all_data as &$va){
                $va['product_img'] = isset($va['product_img'])?IMG_DXCDN_URL.$va['product_img']:'';
            }

        }
        Log::record('sql_1_1:'.$this->db->getLastSql());
        $data['data'] = $all_data;
        return $data;
    }

    public function getOrderStatusView(){
        $ConfigName = isset($paramData["ConfigName"])?$paramData["ConfigName"]:"OrderStatusView";
        $SysCofig = model("mallextend/SysConfig")->getSysCofig($ConfigName);
    }

    /**
     * 根据产品ID、收货地址国家、时间查询订单销量
     * @param array $params
     * @return array
     * {
    "create_on_start":"2019-08-23 00:00:00",
    "create_on_end":"2019-08-24 00:00:00",
    "product_ids":[
    2609629,2605119,2612688
    ],
    "country_code":"US"
    }
     */
    public function getOrderProduct(array $params,$page_size=10,$page=1){
        $time = time();
        $data =  [];
        //只查询子单号
        $where['a.order_master_number'] = ['<>', 0];
        /** 搜索订单创建时间 start **/
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $create_on_start = $params['create_on_start'];
            $create_on_end = $params['create_on_end'];
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', strtotime($create_on_end)]
            ];
            
        }else{
            //默认取今天的数据（UTC）
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d H:i:s', $time);
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', $time]
            ];
        }
        /** 搜索订单创建时间 end **/

        $data['create_on_start'] = $create_on_start;
        $data['create_on_end'] = $create_on_end;

        $success_order_status_1 = '400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';

        /************ 根据产品ID查询 *************/
        $product_ids_img_arr = [];
        $product_ids_new = [];

        /************ 根据国家字段查询 *************/
        $country_code = (
            isset($params['country_code'])
            && !empty($params['country_code'])
        )?$params['country_code']:'';
        if (!empty($country_code)){
            $where['a.country_code'] = $country_code;
            $data['country_code'] = $country_code;
        }
        /************* 存在按照产品ID查询的情况 *************/
        //总量
            $all_data = $this->db->table($this->order)
                ->alias("a")
                ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
                ->field('b.product_id, SUM(b.product_nums) as `all`, SUM(b.captured_price_usd*b.product_nums) as `product_price`')
                ->where($where)
                ->group('b.product_id')
                ->order($params['order'],$params['by'])
                ->paginate($page_size,false,[
                   // 'type' => 'Bootstrap',
                    'page' => $page
                ]);

            if($all_data) {
                $product_ids=$all_data->column('product_id');
                $data['data'] =$all_data->items();
                $data['total']     =$all_data->total();
                $data['per_page']     =$all_data->listRows();
                $data['current_page'] =$all_data->currentPage();
                $data['last_page']    =$all_data->lastPage();
            }else{
                return $all_data;
            }

            if (!empty($product_ids)){
                $where['b.product_id'] = ['in', $product_ids];
                foreach ($product_ids as $k100=>$v100){
                    $product_ids_new[] = (int)$v100;
                }
            }
            //获取产品主图
            if (!empty($product_ids_new)){
                $product_data = $this->db_mongo->table($this->dx_product)
                    ->where('_id','in',$product_ids_new)
                    ->field('_id,ImageSet,WishCount')
                    ->select();

                if (!empty($product_data)){
                    foreach ($product_data as $k200=>$v200){
                        $product_ids_img_arr[$v200['_id']]['ProductImg'] = isset($v200['ImageSet']['ProductImg'][0])?IMG_DXCDN_URL.$v200['ImageSet']['ProductImg'][0]:'';
                        $product_ids_img_arr[$v200['_id']]['WishCount'] = isset($v200['WishCount'])?$v200['WishCount']:0;
                    }
                }

            }

            //成功
            $success_all_data = $this->db->table($this->order)
                ->alias("a")
                ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
                ->field('b.product_id, SUM(b.product_nums) as product_count, SUM(b.captured_price_usd*b.product_nums) as `product_price`')
                ->where(function($q11) use ($success_order_status_1){
                    $q11->where('a.order_status','in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12){
                            $q12->where(['a.order_status'=>120,'a.order_branch_status'=>105]);
                        });
                })
                ->where($where)

                ->group('b.product_id')
                ->select()
            ;

        //$CartInfo=new CartInfo();
            /*************** 数据处理 start **************/
            if (!empty($data['data'])){
                foreach ($data['data'] as $k1=>$v1){

                    //拼装产品图片地址
                    $data['data'][$k1]['product_img'] = !empty($product_ids_img_arr[$v1['product_id']]["ProductImg"])?$product_ids_img_arr[$v1['product_id']]["ProductImg"]:'';
                    $data['data'][$k1]['WishCount'] = !empty($product_ids_img_arr[$v1['product_id']]["WishCount"])?$product_ids_img_arr[$v1['product_id']]["WishCount"]:0;

                    //统一返回格式 start
                    $data['data'][$k1]['success'] = 0;
                    $data['data'][$k1]['success_product_price'] = 0;
                    $data['data'][$k1]['success_rate'] = 0;

                    //统一返回格式 end
                    //拼装成功的数据
                    if (!empty($success_all_data)){
                        foreach ($success_all_data as $k2=>$v2){
                            if ($v1['product_id'] == $v2['product_id']){
                                $data['data'][$k1]['success'] = $v2['product_count'];
                                $data['data'][$k1]['success_product_price'] = $v2['product_price'];
                                $data['data'][$k1]['success_rate'] = ($v1['all'] != 0 && $v1['all']>0)?round($data['data'][$k1]['success']/$v1['all'], 4):0;
                            }
                        }
                    }
                }
            }

            /*************** 数据处理 end **************/

        return $data;
    }
    public function getOrderProductSuccess(array $params,$page_size=10,$page=1){
        $time = time();
        $data =  [];
        //只查询子单号
        $where['a.order_master_number'] = ['<>', 0];
        /** 搜索订单创建时间 start **/
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $create_on_start = $params['create_on_start'];
            $create_on_end = $params['create_on_end'];
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', strtotime($create_on_end)]
            ];

        }else{
            //默认取今天的数据（UTC）
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d H:i:s', $time);
            $where['a.create_on'] = [
                ['>=', strtotime($create_on_start)],
                ['<=', $time]
            ];
        }
        /** 搜索订单创建时间 end **/

        $data['create_on_start'] = $create_on_start;
        $data['create_on_end'] = $create_on_end;

        $success_order_status_1 = '400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';

        /************ 根据产品ID查询 *************/
        $product_ids_img_arr = [];
        $product_ids_new = [];

        /************ 根据国家字段查询 *************/
        $country_code = (
            isset($params['country_code'])
            && !empty($params['country_code'])
        )?$params['country_code']:'';
        if (!empty($country_code)){
            $where['a.country_code'] = $country_code;
            $data['country_code'] = $country_code;
        }

        /************* 存在按照产品ID查询的情况 *************/
        //成功
        $success_all_data = $this->db->table($this->order)
            ->alias("a")
            ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
            ->field('b.product_id, SUM(b.product_nums) as success, SUM(b.captured_price_usd*b.product_nums) as `success_product_price`')
            ->where(function($q11) use ($success_order_status_1){
                $q11->where('a.order_status','in', $success_order_status_1)
                    //进入风控的单也算成功
                    ->whereOr(function ($q12){
                        $q12->where(['a.order_status'=>120,'a.order_branch_status'=>105]);
                    });
            })
            ->where($where)
            ->order($params['order'],$params['by'])
            ->group('b.product_id')
            ->paginate($page_size,false,[
                // 'type' => 'Bootstrap',
                'page' => $page
            ]);
        ;




        if($success_all_data) {
            $product_ids=$success_all_data->column('product_id');
            $data['data'] =$success_all_data->items();
            $data['total']     =$success_all_data->total();
            $data['per_page']     =$success_all_data->listRows();
            $data['current_page'] =$success_all_data->currentPage();
            $data['last_page']    =$success_all_data->lastPage();
        }else{
            return $success_all_data;
        }

        if (!empty($product_ids)){
            $where['b.product_id'] = ['in', $product_ids];
            foreach ($product_ids as $k100=>$v100){
                $product_ids_new[] = (int)$v100;
            }
        }

        //获取产品主图
        if (!empty($product_ids_new)){
            $product_data = $this->db_mongo->table($this->dx_product)
                ->where('_id','in',$product_ids_new)
                ->field('_id,ImageSet,WishCount')
                ->select();

            if (!empty($product_data)){
                foreach ($product_data as $k200=>$v200){
                    $product_ids_img_arr[$v200['_id']]['ProductImg'] = isset($v200['ImageSet']['ProductImg'][0])?IMG_DXCDN_URL.$v200['ImageSet']['ProductImg'][0]:'';
                    $product_ids_img_arr[$v200['_id']]['WishCount'] = isset($v200['WishCount'])?$v200['WishCount']:0;
                }
            }

        }
        //总量
        $all_data = $this->db->table($this->order)
            ->alias("a")
            ->join($this->order_item." b","a.order_id = b.order_id","LEFT")
            ->field('b.product_id, SUM(b.product_nums) as `all`, SUM(b.captured_price_usd*b.product_nums) as `product_price`')
            ->where($where)
            ->group('b.product_id')
            ->select();


        //$CartInfo=new CartInfo();
        /*************** 数据处理 start **************/
        if (!empty($data['data'])){
            foreach ($data['data'] as $k1=>$v1){
                //拼装产品图片地址
                $data['data'][$k1]['product_img'] = !empty($product_ids_img_arr[$v1['product_id']]["ProductImg"])?$product_ids_img_arr[$v1['product_id']]["ProductImg"]:'';
                $data['data'][$k1]['WishCount'] = !empty($product_ids_img_arr[$v1['product_id']]["WishCount"])?$product_ids_img_arr[$v1['product_id']]["WishCount"]:0;

                //统一返回格式 start
                $data['data'][$k1]['all'] = 0;
                $data['data'][$k1]['product_price'] = 0;
                $data['data'][$k1]['success_rate'] = 0;
                //统一返回格式 end
                //拼装成功的数据

                if (!empty($all_data)){
                    foreach ($all_data as $k2=>$v2){
                        if ($v1['product_id'] == $v2['product_id']){
                            $data['data'][$k1]['all'] = $v2['all'];
                            $data['data'][$k1]['product_price'] = $v2['product_price'];
                            $data['data'][$k1]['success_rate'] = ($v1['success'] != 0 && $v2['all']>0)?round($data['data'][$k1]['success']/$v2['all'], 4):0;
                        }
                    }
                }
            }
        }

        /*************** 数据处理 end **************/

        return $data;
    }

    public function getOrder($where){
       return $all_data = $this->db->table($this->order)->where($where)->order('order_id desc')->find();
    }

    public function getOrderCount($where){
        return $all_data = $this->db->table($this->order)->where($where)->count();
    }

    /**
     * 获取订单统计
     * @param array $params 条件
     * @return array
     */
    public function getOrderStatisticsSum(array $params)
    {
        $time = time();
        $data = [];
        //查询成功订单时候是否严格查询进入风控的单：1-严格模式，2-宽松模式
        $query_flag = isset($params['query_flag']) ? $params['query_flag'] : 1;
        //只查询子单号
        $where['order_master_number'] = ['<>', 0];
        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ) {
            $create_on_start = $params['create_on_start'];
            $create_on_end = $params['create_on_end'];
            $where['create_on'] = [
                ['>=', strtotime($params['create_on_start'])],
                ['<=', strtotime($params['create_on_end'])]
            ];
        } else {
            //默认取今天的数据（UTC）
            $create_on_start = date('Y-m-d 00:00:00');
            $create_on_end = date('Y-m-d H:i:s', $time);
            $where['create_on'] = [
                ['>=', strtotime(date('Y-m-d 00:00:00'))],
                ['<=', $time]
            ];
        }
        //根据国家来查询 tinghu.liu 20190902
        $country_code = (isset($params['country_code']) && !empty($params['country_code'])) ? $params['country_code'] : '';
        if ($country_code != '') {
            $where['country_code'] = $country_code;
        }

        //根据来源来查询 `order_from` int(11) DEFAULT '10' COMMENT '订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile',
        $order_from = (isset($params['order_from']) && !empty($params['order_from'])) ? $params['order_from'] : '';
        if ($order_from != '') {
            $where['order_from'] = $order_from;
        }

        $data['create_on_start'] = $create_on_start;
        $data['create_on_end'] = $create_on_end;
        $success_order_status_1 = '400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';
        $success_order_status_2 = '120,400,407,500,600,700,900,920,1000,1100,1200,1300,1700,2000';

        /** 一、统计订单总量 start **/
        //1.总量
        $all_data = $this->db->table($this->order)
            ->where($where)
            ->field('COUNT(*) as order_count,COUNT(distinct customer_id) as customer_count')
            ->find();
        //echo  $this->db->table($this->order)->getLastSql();die;
        $all_count = $all_data['order_count'];
        $customer_count = $all_data['customer_count'];

        //2.支付成功量
        if ($query_flag == 1) {
            $success_all_data = $this->db->table($this->order)
                ->where($where)
                ->where(function ($q11) use ($success_order_status_1) {
                    $q11->where('order_status', 'in', $success_order_status_1)
                        //进入风控的单也算成功
                        ->whereOr(function ($q12) {
                            $q12->where(['order_status' => 120, 'order_branch_status' => 105]);
                        });
                })
                ->field('COUNT(*) as order_count,COUNT(distinct customer_id) as customer_count,SUM(captured_amount_usd) as captured_amount_usd')
                ->find();
        } else {

            $success_all_data = $this->db->table($this->order)
                ->where($where)
                ->where('order_status', 'in', $success_order_status_2)
                ->field('COUNT(*) as order_count,COUNT(distinct customer_id) as customer_count,SUM(captured_amount_usd) as captured_amount_usd')
                ->find();
        }

        Log::record('sql_1_1:' . $this->db->getLastSql());
        $all_count_success = $success_all_data['order_count'];
        $all_count_success_rate_all = $all_count > 0 ? round($all_count_success / $all_count, 4) : 0;

        $da['all'] = $all_data;
        $da['success'] = $success_all_data;
        $da['success_rate'] = $all_count_success_rate_all;
        return $da;
    }

    /**
     * 获取订单统计
     * @param array $params 条件
     * @return array
     */
    public function getCartSum(array $params)
    {
        $time = time();
        $data = [];
        if (
            !empty($params['create_on_start']) && !empty($params['create_on_end'])
        ) {
            $where['AddTime'] = [
                ['>=', strtotime($params['create_on_start'])],
                ['<=', strtotime($params['create_on_end'])]
            ];
        } else {
            //默认取今天的数据（UTC）
            $where['AddTime'] = [
                ['>=', strtotime(date('Y-m-d 00:00:00'))],
                ['<=', $time]
            ];
        }

        $CartInfo=new CartInfo();
        $CartInfoData = $CartInfo
            ->field('DataKey')
            ->where($where)
            ->select() ;
        return $CartInfoData;
    }


}