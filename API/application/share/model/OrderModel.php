<?php
namespace app\share\model;

use app\admin\model\OrderMessageTemplateModel;
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
    private $question = "sl_question";
    private $dx_sales_order_discount_exception = "dx_sales_order_exception";
    private $order_status_process = "dx_order_status_process";
    private $dx_sales_order_status_oms_record = "dx_sales_order_status_oms_record";
    private $dx_order_refund = "dx_order_refund";
    private $dx_sales_order_refund_operation = "dx_sales_order_refund_operation";
    private $dx_order_number_generate = "dx_order_number_generate_config";
    private $dx_order_pay_token = "dx_order_pay_token";

    /**
     * 订单邮寄地址记录表
     * @var string
     */
    private $order_shipping_address = "dx_order_shipping_address";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }
//['start_time'=>$start_time,'end_time'=>$end_time]
    public function getOrderData(array $where){
        return $this->db->table($this->order)
            ->where('create_on', '>=', $where['start_time'])
            ->where('create_on', '<=', $where['end_time'])
            ->select();
    }

    public function insertPayTypeData(array $data){
        return $this->db->table($this->dx_order_pay_token)->insert($data);
    }

    public function getPayTypeDataByOrderMasterNumber($order_master_number){
        return $this->db->table($this->dx_order_pay_token)->where(['order_master_number'=>$order_master_number])->find();
    }



}
