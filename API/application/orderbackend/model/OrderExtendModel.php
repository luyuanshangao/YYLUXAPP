<?php
namespace app\orderbackend\model;

use think\Model;
use think\Db;

/**
 * 订单模型
 * Class OrderModel
 * @author tinghu.liu 2019/7/10
 * @package app\orderFront\model
 */
class OrderExtendModel extends Model{

    /**
     * 数据库连接对象
     * @var \think\db\Connection
     */
	private $db;
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

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }

    /**
     * 根据订单号获取订单数据
     * @param array $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getByOrderNumber(array $params){
        //子单号
        $order_number = $params['order_number'];
        //是否返回订单明细：0-不返回，1-返回
        $load_order_lines = isset($params['load_order_lines'])?$params['load_order_lines']:0;
        //是否返回订单状态变更历史：0-不返回，1-返回
        $load_order_status_history = isset($params['load_order_status_history'])?$params['load_order_status_history']:0;
        $where = ['order_number'=>$order_number];
        $order_fields = 'order_id,order_number,create_on,order_status,customer_id,customer_name,lock_status,goods_count,goods_total,discount_total,shipping_fee,total_amount,grand_total,captured_amount,refunded_amount,currency_code,order_type,exchange_rate,language_code';
        $data = $this->db->table($this->order)->where($where)->field($order_fields)->find();
        if (!empty($data)){
            $order_id = $data['order_id'];
            $data['shipping_address'] = $this->db->table($this->order_shipping_address)->where(['order_id'=>$order_id])->find();
            if ($load_order_lines == 1){
                $data['item_data'] = $this->db->table($this->order_item)->where(['order_id'=>$order_id])->select();
            }
            if ($load_order_status_history == 1){
                $data['order_status_history'] = $this->db->table($this->order_status_change)->where(['order_id'=>$order_id])->select();
            }
        }
        return $data;
    }

    /**
     * 根据订单号获取订单数据
     * @param array $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getByOrderNumbers(array $params){
        //子单号
        $order_number_arr = $params['order_numbers'];
        $order_fields = 'order_id,order_number,create_on,order_status,customer_id,customer_name,lock_status,goods_count,goods_total,discount_total,shipping_fee,total_amount,grand_total,captured_amount,refunded_amount,currency_code,order_type,exchange_rate,language_code';
        $data = $this->db->table($this->order)->where('order_number','in',$order_number_arr)->field($order_fields)->select();
        if (!empty($data)){
            foreach ($data as $k=>$v){
                $order_id = $v['order_id'];
                $data[$k]['shipping_address'] = $this->db->table($this->order_shipping_address)->where(['order_id'=>$order_id])->find();
                $data[$k]['item_data'] = $this->db->table($this->order_item)->where(['order_id'=>$order_id])->select();
                $data[$k]['order_status_history'] = $this->db->table($this->order_status_change)->where(['order_id'=>$order_id])->select();
            }
        }
        return $data;
    }

    /**
     * 根据用户ID获取订单数据
     * @param array $params
     * @return array
     */
    public function getByCustomerId(array $params){
        //用户ID
        $customer_id = $params['customer_id'];
        $order_fields = 'order_id,order_number,create_on,order_status,customer_id,customer_name,lock_status,goods_count,goods_total,discount_total,shipping_fee,total_amount,grand_total,captured_amount,refunded_amount,currency_code,order_type,exchange_rate,language_code';
        $query = $this->db->table($this->order)->where(['customer_id'=>$customer_id]);
        //分页参数设置
        $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 5;
        $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;
        $response = $query->field($order_fields)
            ->group('order_id')
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key){
                $order_id = $item['order_id'];
                $item['shipping_address'] = $this->db->table($this->order_shipping_address)->where(['order_id'=>$order_id])->find();
                $item['item_data'] = $this->db->table($this->order_item)->where(['order_id'=>$order_id])->select();
                $item['order_status_history'] = $this->db->table($this->order_status_change)->where(['order_id'=>$order_id])->select();
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }


}