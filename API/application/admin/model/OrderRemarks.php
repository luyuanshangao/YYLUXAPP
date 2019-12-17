<?php
namespace app\admin\model;
use think\Model;
use think\Db;
/**
 * 订单备注模型
 * @author
 * @version Kevin 2019/8/01
 */
class OrderRemarks extends Model{
    private $db;
    private $order_remarks = "dx_order_remarks";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }
    /*
    * 获取订单
    * */
    public function getOrderRemarks($where=''){
        $res = $this->db->table($this->order_remarks)->where($where)->group("order_id")->column("remarks","order_id");
        return $res;
    }

    /**
     * 新增数据
     * @param $data
     * @return int|string
     */
    public function addRemarks($data){
        return $this->db->table($this->order_remarks)->insert($data);
    }
}