<?php
namespace app\reviews\model;
use think\Model;
use think\Db;
/**
 * 模型
 * @author
 * @version Kevin 2018/4/27
 */
class OrderItem extends Model{
    protected $table = 'dx_sales_order_item';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }
    /*
    *
    * */
    public function getOrderReviewsPoints($where){
       $data = $this->db->table($this->table)->where($where)->value("product_nums");
       $res = !empty($data)?$data:0;
       return $res;
    }

}